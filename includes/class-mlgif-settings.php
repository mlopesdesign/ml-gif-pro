<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLGIF_Settings {
    public function get_defaults(): array {
        return [
            'plugin_title'        => 'ML Gif Pro',
            'plugin_subtitle'     => 'Gerador profissional de GIF animado físico para WordPress',
            'menu_label'          => 'ML Gif Pro',
            'primary_status'      => 'Motor ativo',
            'secondary_status'    => 'Imagick pronto',
            'enable_license_tab'  => '1',
            'license_product_id'  => 'ml-gif-pro',
            'license_endpoint'    => 'https://hub.mlopesdesign.com.br/wp-json/ml-license/v1/validate',
            'license_item_name'   => 'ML Gif Pro',
            'license_seller_name' => 'ML Lopes Design',
            'license_cta_title'   => 'ML Gif Pro - Licença Comercial',
            'license_cta_text'    => 'Ative sua licença para desbloquear todos os recursos do ML Gif Pro.',
            'max_width'            => '1200',
            'max_height'           => '1920',
            'lock_ratio_default'   => '1',
            'default_width'        => '800',
            'default_height'       => '400',
            'default_frame_duration' => '100',
            'default_quality'      => '85',
            'default_fit_mode'     => 'cover',
            'default_transition'   => 'none',
            'default_loop_mode'    => 'infinite',
            'default_loop_count'   => '3',
        ];
    }

    public function get_settings(): array {
        $saved = get_option(MLGIF_OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return wp_parse_args($saved, $this->get_defaults());
    }

    public function register(): void {
        register_setting('mlgif_settings_group', MLGIF_OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => $this->get_defaults(),
        ]);
    }

    public function sanitize($input): array {
        $input   = is_array($input) ? $input : [];
        $current = $this->get_settings();
        $current['plugin_title']        = sanitize_text_field((string) ($input['plugin_title'] ?? $current['plugin_title']));
        $current['plugin_subtitle']     = sanitize_text_field((string) ($input['plugin_subtitle'] ?? $current['plugin_subtitle']));
        $current['menu_label']          = sanitize_text_field((string) ($input['menu_label'] ?? $current['menu_label']));
        $current['primary_status']      = sanitize_text_field((string) ($input['primary_status'] ?? $current['primary_status']));
        $current['secondary_status']    = sanitize_text_field((string) ($input['secondary_status'] ?? $current['secondary_status']));
        if (array_key_exists('enable_license_tab', $input)) {
            $current['enable_license_tab'] = empty($input['enable_license_tab']) ? '0' : '1';
        }
        $current['license_product_id']  = sanitize_text_field((string) ($input['license_product_id'] ?? $current['license_product_id']));
        $current['license_endpoint']    = esc_url_raw((string) ($input['license_endpoint'] ?? $current['license_endpoint']));
        $current['license_item_name']   = sanitize_text_field((string) ($input['license_item_name'] ?? $current['license_item_name']));
        $current['license_seller_name'] = sanitize_text_field((string) ($input['license_seller_name'] ?? $current['license_seller_name']));
        $current['license_cta_title']   = sanitize_text_field((string) ($input['license_cta_title'] ?? $current['license_cta_title']));
        $current['license_cta_text']    = sanitize_textarea_field((string) ($input['license_cta_text'] ?? $current['license_cta_text']));

        // Limites globais de dimensão (trava anti-GIF gigante)
        $current['max_width']  = (string) min(2000, max(100, (int) ($input['max_width']  ?? $current['max_width'])));
        $current['max_height'] = (string) min(1920, max(100, (int) ($input['max_height'] ?? $current['max_height'])));
        $current['lock_ratio_default'] = empty($input['lock_ratio_default']) ? '0' : '1';

        // Defaults de projeto nunca podem exceder os limites globais
        $max_w = (int) $current['max_width'];
        $max_h = (int) $current['max_height'];
        $current['default_width']  = (string) min($max_w, max(1, (int) ($input['default_width']  ?? $current['default_width'])));
        $current['default_height'] = (string) min($max_h, max(1, (int) ($input['default_height'] ?? $current['default_height'])));
        $current['default_frame_duration'] = (string) min(500, max(1, (int) ($input['default_frame_duration'] ?? $current['default_frame_duration'])));
        $current['default_quality']        = (string) min(100, max(1, (int) ($input['default_quality'] ?? $current['default_quality'])));

        $fit_modes = ['cover', 'contain', 'stretch'];
        $fit_mode = (string) ($input['default_fit_mode'] ?? $current['default_fit_mode']);
        $current['default_fit_mode'] = in_array($fit_mode, $fit_modes, true) ? $fit_mode : 'cover';

        $transitions = ['none', 'fade', 'slide', 'zoom'];
        $transition = (string) ($input['default_transition'] ?? $current['default_transition']);
        $current['default_transition'] = in_array($transition, $transitions, true) ? $transition : 'none';

        $loop_mode = (string) ($input['default_loop_mode'] ?? $current['default_loop_mode']);
        $current['default_loop_mode'] = in_array($loop_mode, ['infinite', 'fixed'], true) ? $loop_mode : 'infinite';
        $current['default_loop_count'] = (string) min(999, max(1, (int) ($input['default_loop_count'] ?? $current['default_loop_count'])));

        return $current;
    }
}
