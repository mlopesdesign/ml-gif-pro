<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLGIF_Plugin {
    private MLGIF_Settings $settings_manager;
    private MLGIF_License  $license_manager;
    private MLGIF_Admin_UI $admin_ui;
    private MLGIF_Engine   $engine;

    public function __construct() {
        $this->settings_manager = new MLGIF_Settings();
        $this->license_manager  = new MLGIF_License();
        $this->admin_ui         = new MLGIF_Admin_UI();
        $this->engine           = new MLGIF_Engine();

        add_action('admin_menu',            [$this, 'register_admin_menu']);
        add_action('admin_init',            [$this->settings_manager, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        // BUG-I FIX: criar tabela no init garante disponibilidade para shortcode no frontend
        add_action('init', [MLGIF_DB::class, 'maybe_create_table']);
        add_action('admin_post_mlgif_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_init', [$this, 'handle_flush_updater_cache']);

        add_action('wp_ajax_mlgif_save_project',    [$this, 'ajax_save_project']);
        add_action('wp_ajax_mlgif_generate',        [$this, 'ajax_generate']);
        add_action('wp_ajax_mlgif_project_status',  [$this, 'ajax_project_status']);
        add_action('wp_ajax_mlgif_delete_project',  [$this, 'ajax_delete_project']);
        add_action('wp_ajax_mlgif_get_image_data',  [$this, 'ajax_get_image_data']);
    }

    public static function get_version(): string {
        return MLGIF_VERSION;
    }

    public function register_admin_menu(): void {
        add_menu_page(
            'ML Gif Pro',
            'ML Gif Pro',
            'manage_options',
            'ml-gif-pro',
            [$this, 'render_admin_page'],
            'dashicons-format-image',
            57
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_ml-gif-pro') {
            return;
        }
        wp_enqueue_style('mlgif-admin', MLGIF_URL . 'assets/admin.css', [], MLGIF_VERSION);
        wp_enqueue_script('mlgif-admin', MLGIF_URL . 'assets/admin.js', [], MLGIF_VERSION, true);
        wp_enqueue_media();
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        MLGIF_DB::create_table();
        $settings       = $this->settings_manager->get_settings();
        $license        = $this->license_manager->get_license_summary();
        $active_tab     = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'dashboard';
        $toast_message  = isset($_GET['mlgif_toast']) ? sanitize_text_field(wp_unslash((string) $_GET['mlgif_toast'])) : '';
        $toast_type     = isset($_GET['mlgif_toast_type']) ? sanitize_key((string) $_GET['mlgif_toast_type']) : 'success';
        $this->admin_ui->render($settings, $license, $active_tab, $toast_message, $toast_type);
    }

    public function handle_save_settings(): void {
        MLGIF_License::guard_write();
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ml-gif-pro'));
        }
        check_admin_referer('mlgif_save_settings');
        $raw      = isset($_POST['mlgif_settings']) ? wp_unslash($_POST['mlgif_settings']) : [];
        $sanitized = $this->settings_manager->sanitize($raw);
        update_option(MLGIF_OPTION_KEY, $sanitized, false);
        $this->redirect_with_toast('settings', 'Configurações salvas.', 'success');
    }

    public function ajax_save_project(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized.']);
        }
        if (!check_ajax_referer('mlgif_ajax', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido.']);
        }
        MLGIF_License::guard_write(true);
        $id       = (int) ($this->post('id') ?? 0);
        $title    = sanitize_text_field((string) ($this->post('title') ?? ''));
        $images   = json_decode(wp_unslash((string) ($this->post('images') ?? '[]')), true);
        $settings = json_decode(wp_unslash((string) ($this->post('settings') ?? '{}')), true);

        if ($title === '') {
            wp_send_json_error(['error' => 'Título obrigatório.']);
        }
        if (!is_array($images)) {
            $images = [];
        }
        if (!is_array($settings)) {
            $settings = [];
        }
        if (isset($settings['optimize_level'])) {
            $settings['optimize_level'] = max(1, min(3, (int) $settings['optimize_level']));
        }

        // Preservar status atual se projeto já foi gerado — não regredir para 'draft'
        $current_status = 'draft';
        if ($id > 0) {
            $existing = MLGIF_DB::get_project($id);
            if ($existing && in_array((string) ($existing['status'] ?? ''), ['generated', 'generating'], true)) {
                $current_status = (string) $existing['status'];
            }
        }

        $project_id = MLGIF_DB::save_project([
            'id'       => $id,
            'title'    => $title,
            'images'   => $images,
            'settings' => $settings,
            'status'   => $current_status,
        ]);

        wp_send_json_success(['project_id' => $project_id]);
    }

    public function ajax_generate(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized.']);
        }
        if (!check_ajax_referer('mlgif_ajax', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido.']);
        }
        MLGIF_License::guard_write(true);

        $id       = (int) ($this->post('id') ?? 0);
        $title    = sanitize_text_field((string) ($this->post('title') ?? ''));
        $images   = json_decode(wp_unslash((string) ($this->post('images') ?? '[]')), true);
        $settings = json_decode(wp_unslash((string) ($this->post('settings') ?? '{}')), true);

        if ($title === '') {
            wp_send_json_error(['error' => 'Título obrigatório.']);
        }
        if (!is_array($images) || count($images) === 0) {
            wp_send_json_error(['error' => 'Nenhuma imagem selecionada.']);
        }
        if (!is_array($settings)) {
            $settings = [];
        }
        if (isset($settings['optimize_level'])) {
            $settings['optimize_level'] = max(1, min(3, (int) $settings['optimize_level']));
        }

        // Salvar projeto
        $project_id = MLGIF_DB::save_project([
            'id'       => $id,
            'title'    => $title,
            'images'   => $images,
            'settings' => $settings,
            'status'   => 'generating',
        ]);

        $project = MLGIF_DB::get_project($project_id);
        if (!$project) {
            wp_send_json_error(['error' => 'Projeto não encontrado após salvar.']);
        }

        // Remover GIF anterior
        if (!empty($project['attachment_id']) && (int) $project['attachment_id'] > 0 && get_post((int) $project['attachment_id'])) {
            wp_delete_attachment((int) $project['attachment_id'], true);
        } elseif (!empty($project['output_path']) && file_exists((string) $project['output_path'])) {
            @unlink((string) $project['output_path']);
        }

        // Limites para geração
        @set_time_limit(600);
        @ini_set('memory_limit', '768M');

        // Geração síncrona — responde com URL após concluir.
        // send_json_and_continue removido: depende de fastcgi_finish_request
        // que não está disponível neste servidor, causando polling infinito.
        $result = $this->engine->generate($project_id, $project);

        if (empty($result['success'])) {
            MLGIF_DB::update_error($project_id, (string) ($result['error'] ?? 'Erro desconhecido.'));
            wp_send_json_error([
                'error'      => $result['error'] ?? 'Erro ao gerar GIF.',
                'project_id' => $project_id,
            ]);
        }

        MLGIF_DB::update_optimize_meta(
            $project_id,
            (int) ($result['size_bytes'] ?? 0),
            (int) ($result['optimize_level'] ?? 1),
            (int) ($result['optimize_colors'] ?? 256)
        );

        // Resposta com URL final — JS renderiza imediatamente, sem polling
        wp_send_json_success([
            'project_id'      => $project_id,
            'url'             => $result['url'],
            'size'            => $result['size'] ?? '',
            'size_bytes'      => $result['size_bytes'] ?? 0,
            'optimize_level'  => $result['optimize_level'] ?? 1,
            'optimize_colors' => $result['optimize_colors'] ?? 256,
            'warnings'        => $result['warnings'] ?? [],
        ]);
    }
    public function ajax_project_status(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized.']);
        }
        if (!check_ajax_referer('mlgif_ajax', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido.']);
        }
        $id = (int) ($this->post('id') ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['error' => 'ID inválido.']);
        }
        $project = MLGIF_DB::get_project($id);
        if (!$project) {
            wp_send_json_error(['error' => 'Projeto não encontrado.']);
        }
        $size       = '';
        $size_bytes = 0;
        if (!empty($project['output_path']) && file_exists((string) $project['output_path'])) {
            $size_bytes = (int) filesize((string) $project['output_path']);
            $size       = MLGIF_Helpers::format_bytes($size_bytes);
        }

        wp_send_json_success([
            'project_id'      => $id,
            'status'          => (string) ($project['status'] ?? ''),
            'url'             => (string) ($project['output_url'] ?? ''),
            'attachment_id'   => isset($project['attachment_id']) ? (int) $project['attachment_id'] : 0,
            'size'            => $size,
            'size_bytes'      => $size_bytes,
            'optimize_level'  => (int) ($project['optimize_level'] ?? 1),
            'optimize_colors' => (int) ($project['optimize_colors'] ?? 256),
            'error'           => (string) ($project['error_message'] ?? ''),
        ]);
    }

    public function ajax_delete_project(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized.']);
        }
        if (!check_ajax_referer('mlgif_ajax', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido.']);
        }
        MLGIF_License::guard_write(true);
        $id = (int) ($this->post('id') ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['error' => 'ID inválido.']);
        }
        MLGIF_DB::delete_project($id);
        wp_send_json_success(['deleted' => $id]);
    }

    public function ajax_get_image_data(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized.']);
        }
        if (!check_ajax_referer('mlgif_ajax', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido.']);
        }
        $ids = json_decode(wp_unslash((string) ($this->post('ids') ?? '[]')), true);
        if (!is_array($ids)) {
            wp_send_json_success([]);
        }
        $data = [];
        foreach ($ids as $id) {
            $id    = (int) $id;
            $thumb = wp_get_attachment_image_src($id, 'thumbnail');
            $title = get_the_title($id);
            $data[$id] = [
                'thumb' => $thumb ? $thumb[0] : '',
                'title' => $title ?: ('ID ' . $id),
            ];
        }
        wp_send_json_success($data);
    }

    private function post(string $key): mixed {
        return isset($_POST[$key]) ? $_POST[$key] : null;
    }

    private function send_json_and_continue(array $payload): void {
        if (!headers_sent()) {
            status_header(200);
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            header('X-MLGIF-Background: 1');
        }
        echo wp_json_encode($payload);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        flush();
    }

    public function handle_flush_updater_cache(): void {
        if (!isset($_GET['mlgif_flush_updater'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('mlgif_flush_updater');
        delete_transient('mlgif_updater_release_cache');
        $this->redirect_with_toast('settings', 'Cache do updater limpo. Verificando GitHub...', 'success');
    }

    private function redirect_with_toast(string $tab, string $message, string $type): void {
        $url = add_query_arg([
            'page'              => 'ml-gif-pro',
            'tab'               => $tab,
            'mlgif_toast'       => rawurlencode($message),
            'mlgif_toast_type'  => $type,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
