<?php
/**
 * Licença do ML Gif Pro.
 *
 * 1.2.0: a licença passa a ser emitida e conferida pelo site tools.mlopesdesign.com.br
 * (módulo ML License Hub, cliente ML_License_Client_V2). O endpoint antigo
 * (hub.mlopesdesign.com.br/wp-json/ml-license/v1/validate) não é mais consultado.
 * Regras do padrão ML: 1 licença = 1 site (o Pacote Agência vale em vários sites);
 * teste grátis de 30 dias por site; sem teste/licença válidos, o painel fica só para
 * leitura (criar, editar, gerar, apagar projetos e salvar configurações) — os GIFs já
 * gerados continuam no site (arquivos em /uploads e shortcode [ml_gif_pro]) e nenhum
 * dado é apagado.
 *
 * Quem já usava o plugin antes da 1.2.0 recebe o teste grátis automaticamente na
 * primeira visita ao painel (o site não trava de surpresa após a atualização).
 *
 * A option antiga (mlgif_license_state) é preservada no banco (não é mais usada).
 *
 * @package ML_Gif_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-ml-license-client.php';

final class MLGIF_License {
    const API_BASE    = 'https://tools.mlopesdesign.com.br/wp-json/mllh/v1';
    const PUBLIC_KEY  = 'VGZQp2X3RJMuDmQBlIEXS4fqsZD1CnFlIJgfVtY/pHo=';
    const PRODUCT_ID  = 'ml-gif-pro';
    const BUY_URL     = 'https://tools.mlopesdesign.com.br/precos/';
    const ACCOUNT_URL = 'https://tools.mlopesdesign.com.br/minha-conta/';
    const AUTO_TRIAL  = 'mlgif_auto_trial_tried';

    /** @var ML_License_Client_V2|null */
    private static $client = null;

    /** @var bool|null Resultado guardado durante a requisição (zera quando a licença muda). */
    private static $locked = null;

    public static function client(): ML_License_Client_V2 {
        if (null === self::$client) {
            self::$client = new ML_License_Client_V2([
                'api_base'   => self::API_BASE,
                'product'    => self::PRODUCT_ID,
                'public_key' => self::PUBLIC_KEY,
                'version'    => MLGIF_VERSION,
            ]);
        }
        return self::$client;
    }

    /** Teste ou licença válidos neste site. */
    public static function is_active(): bool {
        return self::client()->is_premium();
    }

    /** Painel travado para gravar (sem teste nem licença). */
    public static function is_locked(): bool {
        if (null === self::$locked) {
            self::$locked = !self::is_active();
        }
        return self::$locked;
    }

    public static function forget(): void {
        self::$locked = null;
    }

    /* ------------------------------------------------------------------ ganchos */

    public static function boot(): void {
        self::client(); // agenda a renovação diária
        add_action('admin_init', [__CLASS__, 'maybe_auto_trial'], 5);
        add_action('admin_post_mlgif_activate_license', [__CLASS__, 'handle_activate_request']);
        add_action('admin_post_mlgif_sync_license', [__CLASS__, 'handle_sync_request']);
        add_action('admin_post_mlgif_remove_license', [__CLASS__, 'handle_remove_request']);
        $opt = self::client()->option_name();
        foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) {
            add_action($hook, function ($name) use ($opt) {
                if ($name === $opt) {
                    MLGIF_License::forget();
                }
            });
        }
    }

    /** Primeira visita ao painel: começa o teste grátis sozinho (uma tentativa por site). */
    public static function maybe_auto_trial(): void {
        if (wp_doing_ajax() || !current_user_can('manage_options') || get_option(self::AUTO_TRIAL)) {
            return;
        }
        update_option(self::AUTO_TRIAL, time(), false);
        $state = self::client()->state();
        if (empty($state['active']) && 'none' === $state['mode'] && empty($state['trial_used'])) {
            self::client()->start_trial();
        }
    }

    /**
     * Para as ações de gravação do painel (salvar configurações, salvar/gerar/apagar projeto).
     * Sem licença: AJAX responde erro 403 com mensagem; admin-post volta para a aba Licença.
     */
    public static function guard_write(bool $ajax = false): void {
        if (!self::is_locked()) {
            return;
        }
        $msg = self::locked_message();
        if ($ajax) {
            wp_send_json_error(['error' => $msg, 'message' => $msg, 'locked' => true], 403);
        }
        self::redirect_with_notice('error', $msg);
    }

    public static function locked_message(): string {
        return 'Ative a licença (ou comece o teste grátis) para criar, editar, gerar e apagar GIFs ou salvar configurações. Os GIFs já publicados continuam funcionando no site.';
    }

    /* ------------------------------------------------------------------ tela */

    /** Estado pronto para a tela. */
    public function get_license_summary(): array {
        $state  = self::client()->state();
        $active = !empty($state['active']);
        $mode   = (string) ($state['mode'] ?? 'none');
        $plan   = strtolower((string) ($state['plan'] ?? ''));
        $days   = $state['days_left'] ?? null;

        $plans = [
            'trial'    => 'Teste grátis',
            'monthly'  => 'Mensal',
            'annual'   => 'Anual',
            'yearly'   => 'Anual',
            'lifetime' => 'Vitalícia',
            'manual'   => 'Cortesia',
            'courtesy' => 'Cortesia',
        ];
        $plan_label = $plans[$plan] ?? ('' !== $plan ? ucfirst($plan) : '—');

        if ($active && 'trial' === $mode) {
            $label   = 'Teste';
            $tone    = 'trial';
            $message = null === $days ? 'Teste grátis ativo neste site.' : sprintf(1 === (int) $days ? 'Teste grátis ativo: falta %d dia.' : 'Teste grátis ativo: faltam %d dias.', (int) $days);
        } elseif ($active) {
            $label   = 'Ativa';
            $tone    = 'full';
            $message = 'Licença ativa neste site.';
        } else {
            $label = 'Sem licença';
            $tone  = 'free';
            if ('trial_expired' === ($state['status'] ?? '')) {
                $message = 'O teste grátis terminou. Ative uma licença para voltar a criar e editar GIFs. Os GIFs já publicados continuam funcionando no site.';
            } elseif ('' !== (string) ($state['message'] ?? '')) {
                $message = (string) $state['message'];
            } else {
                $message = 'Comece o teste grátis de 30 dias ou ative a sua licença.';
            }
        }

        $expires_label = '';
        if (!empty($state['expires_at'])) {
            $ts = strtotime((string) $state['expires_at']);
            if ($ts) {
                $expires_label = wp_date(get_option('date_format', 'd/m/Y'), $ts);
            }
        }

        return [
            'active'          => $active,
            'mode'            => $mode,
            'is_trial'        => $active && 'trial' === $mode,
            'plan'            => $plan,
            'label'           => $label,
            'tone'            => $tone,
            'message'         => $message,
            'plan_label'      => $plan_label,
            'expires_label'   => $expires_label,
            'days_left'       => $days,
            'key_masked'      => (string) ($state['key_masked'] ?? ''),
            'can_start_trial' => !$active && empty($state['trial_used']) && 'license' !== $mode,
            'domain'          => (string) wp_parse_url(home_url(), PHP_URL_HOST),
        ];
    }

    /* ------------------------------------------------------------------ ações */

    public static function handle_activate_request(): void {
        self::guard_admin_request('mlgif_activate_license');
        $key    = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash((string) $_POST['license_key'])) : '';
        $result = self::client()->activate($key);
        if (is_wp_error($result)) {
            self::redirect_with_notice('error', $result->get_error_message());
        }
        self::redirect_with_notice('success', 'Licença ativada neste site.');
    }

    /** Começa o teste grátis ou confere a licença agora. */
    public static function handle_sync_request(): void {
        self::guard_admin_request('mlgif_sync_license');
        $op     = isset($_POST['mlgif_op']) ? sanitize_key(wp_unslash((string) $_POST['mlgif_op'])) : 'check';
        $result = 'trial' === $op ? self::client()->start_trial() : self::client()->refresh();
        if (is_wp_error($result)) {
            self::redirect_with_notice('error', $result->get_error_message());
        }
        self::redirect_with_notice('success', 'trial' === $op ? 'Teste grátis de 30 dias ativo neste site.' : 'Licença conferida.');
    }

    public static function handle_remove_request(): void {
        self::guard_admin_request('mlgif_remove_license');
        self::client()->deactivate();
        self::redirect_with_notice('success', 'Licença removida deste site. A chave pode ser usada em outro site.');
    }

    private static function guard_admin_request(string $nonce_action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'ml-gif-pro'));
        }
        check_admin_referer($nonce_action);
    }

    public static function redirect_with_notice(string $type, string $message): void {
        $url = add_query_arg([
            'page'             => 'ml-gif-pro',
            'tab'              => 'license',
            'mlgif_toast'      => rawurlencode($message),
            'mlgif_toast_type' => 'error' === $type ? 'error' : 'success',
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
