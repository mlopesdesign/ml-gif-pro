<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLGIF_DB {
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'mlgif_projects';
    }

    private static bool $table_checked = false;

    // BUG-I FIX: verificação lazy chamada no hook init (garante tabela para shortcode no frontend)
    public static function maybe_create_table(): void {
        if (self::$table_checked) {
            return;
        }
        self::$table_checked = true;
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            self::create_table();
        }
    }

    public static function create_table(): void {
        global $wpdb;
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            images longtext NOT NULL DEFAULT '',
            settings_json longtext NOT NULL DEFAULT '',
            output_path varchar(500) NOT NULL DEFAULT '',
            output_url varchar(500) NOT NULL DEFAULT '',
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(32) NOT NULL DEFAULT 'draft',
            error_message text NOT NULL DEFAULT '',
            optimize_level tinyint(1) unsigned NOT NULL DEFAULT 1,
            optimize_colors smallint(4) unsigned NOT NULL DEFAULT 256,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            generated_at datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Adicionar colunas se a tabela já existia sem elas (upgrade silencioso)
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        if (is_array($cols)) {
            if (!in_array('optimize_level', $cols, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN optimize_level tinyint(1) unsigned NOT NULL DEFAULT 1");
            }
            if (!in_array('optimize_colors', $cols, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN optimize_colors smallint(4) unsigned NOT NULL DEFAULT 256");
            }
        }
    }

    public static function get_all_projects(): array {
        global $wpdb;
        $table   = self::table();
        // Corrigir status incorreto: se output_url existe, o GIF foi gerado — forçar status 'generated'
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("UPDATE `{$table}` SET status = 'generated', error_message = '' WHERE status IN ('generating','error','draft') AND output_url != ''");


        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id DESC", ARRAY_A);
        return is_array($results) ? $results : [];
    }

    public static function get_project(int $id): ?array {
        global $wpdb;
        $table = self::table();

        // BUG 3 FIX: aplicar correção de status antes de retornar
        // A tela de edição usa get_project diretamente, não passa por get_all_projects
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "UPDATE `{$table}` SET status = 'generated', error_message = '' WHERE id = %d AND status IN ('generating','error','draft') AND output_url != ''",
            $id
        ));

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }
    public static function save_project(array $data): int {
        global $wpdb;
        $table = self::table();
        $id    = (int) ($data['id'] ?? 0);
        $row   = [
            'title'         => sanitize_text_field((string) ($data['title'] ?? '')),
            'images'        => wp_json_encode(array_map('intval', (array) ($data['images'] ?? []))),
            'settings_json' => wp_json_encode((array) ($data['settings'] ?? [])),
            'status'        => sanitize_key((string) ($data['status'] ?? 'draft')),
        ];
        if ($id > 0) {
            $row['updated_at'] = current_time('mysql');
            $wpdb->update($table, $row, ['id' => $id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
            return $id;
        }
        $row['created_at'] = current_time('mysql');
        $wpdb->insert($table, $row, ['%s', '%s', '%s', '%s', '%s']);
        return (int) $wpdb->insert_id;
    }

    public static function update_status(int $id, string $status, string $message = ''): void {
        global $wpdb;
        $table = self::table();
        $wpdb->update(
            $table,
            [
                'status'        => sanitize_key($status),
                'error_message' => $message,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public static function update_output(int $id, string $path, string $url, int $attachment_id = 0): void {
        global $wpdb;
        $table = self::table();
        $wpdb->update(
            $table,
            [
                'output_path'    => $path,
                'output_url'     => $url,
                'attachment_id'  => max(0, $attachment_id),
                'status'         => 'generated',
                'error_message'  => '',
                'generated_at'   => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public static function update_optimize_meta(int $id, int $size_bytes, int $optimize_level, int $optimize_colors): void {
        global $wpdb;
        $table = self::table();
        $wpdb->update(
            $table,
            [
                'optimize_level'  => max(1, min(3, $optimize_level)),
                'optimize_colors' => max(1, $optimize_colors),
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%d', '%s'],
            ['%d']
        );
    }


    public static function update_attachment_id(int $id, int $attachment_id): void {
        global $wpdb;
        $table = self::table();
        $wpdb->update(
            $table,
            ['attachment_id' => max(0, $attachment_id), 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );
    }

    public static function update_error(int $id, string $message): void {
        global $wpdb;
        $table = self::table();
        $wpdb->update(
            $table,
            [
                'status'        => 'error',
                'error_message' => $message,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public static function delete_project(int $id): void {
        global $wpdb;
        $table   = self::table();
        $project = self::get_project($id);
        if ($project) {
            $attachment_id = isset($project['attachment_id']) ? (int) $project['attachment_id'] : 0;
            if ($attachment_id > 0 && get_post($attachment_id)) {
                wp_delete_attachment($attachment_id, true);
            } elseif (!empty($project['output_path']) && file_exists((string) $project['output_path'])) {
                @unlink((string) $project['output_path']);
            }
        }
        $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    public static function count_projects(): int {
        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    public static function count_generated(): int {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'generated'));
    }
}
