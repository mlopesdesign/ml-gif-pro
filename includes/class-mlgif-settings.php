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
        // 1.2.0: os antigos campos de licença (endpoint, Product ID etc.) não são mais usados;
        // se existirem no banco, continuam guardados como estavam.

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
