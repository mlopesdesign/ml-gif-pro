<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MLGIF_Updater
 *
 * Auto-updater via GitHub Releases para o ML Gif Pro.
 *
 * Comportamento:
 * - Consulta https://api.github.com/repos/mlopesdesign/ml-gif-pro-releases/releases/latest
 *   (repositorio PUBLICO so com os ZIPs; o codigo-fonte e privado)
 * - Compara a tag do release com a versão instalada
 * - Se tag > versão instalada, injeta em response; senão registra em no_update
 * - Download usa o asset ZIP do release (não o zipball automático do GitHub)
 * - Asset ZIP deve se chamar exatamente: ml-gif-pro-vX.X.X.X.zip (ou com _ no lugar de .)
 * - Repo público: sem token necessário
 * - Repo privado: token via constante MLMD_GITHUB_TOKEN_ML_GIF_PRO ou filtro mlmd_github_token_ml_gif_pro
 * - Cache do resultado: 12 horas (transient WordPress)
 * - Após update, reativa o plugin automaticamente
 */
final class MLGIF_Updater {

    private const SLUG        = 'ml-gif-pro';
    private const MAIN_FILE   = 'ml-gif-pro/ml-gif-pro.php';
    private const GITHUB_REPO = 'mlopesdesign/ml-gif-pro-releases';
    private const API_URL     = 'https://api.github.com/repos/mlopesdesign/ml-gif-pro-releases/releases/latest';
    private const CACHE_KEY   = 'mlgif_updater_release_cache';
    private const CACHE_TTL   = 43200; // 12 horas

    private string $plugin_file;
    private string $current_version;

    public function __construct(string $plugin_file, string $current_version) {
        $this->plugin_file     = $plugin_file;
        $this->current_version = $current_version;
    }

    public function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'after_update'], 10, 2);
    }

    /**
     * Injeta o update no transient do WordPress se houver nova versão.
     */
    public function check_for_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $transient;
        }

        $remote_version = ltrim((string) ($release['tag_name'] ?? ''), 'v');
        if (empty($remote_version)) {
            return $transient;
        }

        $download_url = $this->get_asset_url($release);
        if ($download_url === '') {
            return $transient;
        }

        $item = (object) [
            'id'            => self::GITHUB_REPO,
            'slug'          => self::SLUG,
            'plugin'        => self::MAIN_FILE,
            'new_version'   => $remote_version,
            'url'           => 'https://github.com/' . self::GITHUB_REPO,
            'package'       => $download_url,
            'icons'         => $this->icons(),
            'banners'       => [],
            'banners_rtl'   => [],
            'tested'        => '6.8',
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'compatibility' => new stdClass(),
        ];

        if (version_compare($this->current_version, $remote_version, '<')) {
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = [];
            }
            $transient->response[self::MAIN_FILE] = $item;
            if (isset($transient->no_update[self::MAIN_FILE])) {
                unset($transient->no_update[self::MAIN_FILE]);
            }
        } else {
            // Sem versao nova: no_update (o WP mostra o estado correto e o controle de auto-update).
            $item->new_version = $this->current_version;
            if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                $transient->no_update = [];
            }
            $transient->no_update[self::MAIN_FILE] = $item;
            if (isset($transient->response[self::MAIN_FILE])) {
                unset($transient->response[self::MAIN_FILE]);
            }
        }

        return $transient;
    }

    /**
     * Fornece informações do plugin para o popup "Ver detalhes da versão".
     */
    public function plugin_info(mixed $result, string $action, object $args): mixed {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (!isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        $remote_version = ltrim((string) ($release['tag_name'] ?? ''), 'v');
        $download_url   = $this->get_asset_url($release);
        $body           = (string) ($release['body'] ?? '');

        return (object) [
            'name'          => 'ML Gif Pro',
            'slug'          => self::SLUG,
            'version'       => $remote_version,
            'author'        => '<a href="https://mlopesdesign.com.br">ML Lopes Design</a>',
            'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'download_link' => $download_url,
            'icons'         => $this->icons(),
            'sections'      => [
                'description' => 'Gerador profissional de GIF animado físico para WordPress.',
                'changelog'   => nl2br(esc_html($body)),
            ],
        ];
    }

    /**
     * Reativa o plugin automaticamente após update.
     */
    public function after_update(WP_Upgrader $upgrader, array $hook_extra): void {
        if (
            isset($hook_extra['action'], $hook_extra['type'], $hook_extra['plugins']) &&
            $hook_extra['action'] === 'update' &&
            $hook_extra['type'] === 'plugin' &&
            in_array(self::MAIN_FILE, (array) $hook_extra['plugins'], true)
        ) {
            // Limpar OPcache para garantir que o PHP novo seja carregado imediatamente
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            delete_transient(self::CACHE_KEY);
            activate_plugin(self::MAIN_FILE);
        }
    }

    /**
     * Busca o release mais recente da API GitHub com cache de 12h.
     */
    private function get_latest_release(): ?array {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            // Cache valido mesmo quando a tag e igual a versao instalada (resultado: no_update).
            // Antes ele era apagado nesse caso, e todo site em dia consultava a API a cada verificacao.
            return $cached;
        }

        $args = [
            'timeout'    => 15,
            'user-agent' => 'ML-Gif-Pro-Updater/' . $this->current_version . '; WordPress/' . get_bloginfo('version'),
            'headers'    => ['Accept' => 'application/vnd.github.v3+json'],
        ];

        $token = $this->get_token();
        if ($token) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get(self::API_URL, $args);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ((int) $code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }

    /**
     * Extrai a URL do asset ZIP do release.
     * Asset deve se chamar: ml-gif-pro-vX_X_X.zip
     * Se não encontrar o asset da versão, não oferece atualização.
     */
    private function get_asset_url(array $release): string {
        $assets = (array) ($release['assets'] ?? []);
        $tag    = ltrim((string) ($release['tag_name'] ?? ''), 'v');
        $slug   = self::SLUG;

        // Nomes canônicos aceitos: underscores OU pontos na versão
        // ex: ml-gif-pro-v1_1_4_1.zip  ou  ml-gif-pro-v1.1.4.1.zip
        $expected_underscores = $slug . '-v' . str_replace('.', '_', $tag) . '.zip';
        $expected_dots        = $slug . '-v' . $tag . '.zip';

        foreach ($assets as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if ($name === $expected_underscores || $name === $expected_dots) {
                return (string) ($asset['browser_download_url'] ?? '');
            }
        }

        // Sem fallback: "qualquer ZIP" podia instalar arquivo de outra versao ou de outro tipo.
        // NUNCA usar zipball_url — estrutura incorreta (mlopesdesign-ml-gif-pro-{hash}/)
        return '';
    }

    private function icons(): array {
        return [
            '1x'      => MLGIF_URL . 'assets/images/icon-128x128.png',
            '2x'      => MLGIF_URL . 'assets/images/icon-256x256.png',
            'default' => MLGIF_URL . 'assets/images/icon-256x256.png',
        ];
    }

    /**
     * Retorna o token GitHub se configurado (repo privado).
     * Constante: MLMD_GITHUB_TOKEN_ML_GIF_PRO no wp-config.php
     * Filtro:    mlmd_github_token_ml_gif_pro
     */
    private function get_token(): string {
        $token = '';

        if (defined('MLMD_GITHUB_TOKEN_ML_GIF_PRO')) {
            $token = (string) MLMD_GITHUB_TOKEN_ML_GIF_PRO;
        }

        $token = (string) apply_filters('mlmd_github_token_ml_gif_pro', $token);

        return $token;
    }
}
