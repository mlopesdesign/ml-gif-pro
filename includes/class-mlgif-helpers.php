<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLGIF_Helpers {
    public static function logo_url(): string {
        return esc_url(MLGIF_URL . 'assets/images/logo-wordpress.png');
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public static function safe_filename(string $prefix, int $project_id): string {
        return $prefix . '-' . $project_id . '-' . date('Ymd-His') . '.gif';
    }

    public static function imagick_available(): bool {
        return class_exists('Imagick');
    }

    public static function get_upload_dir(): array {
        return wp_upload_dir();
    }
}
