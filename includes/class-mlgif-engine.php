<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLGIF_Engine {

    public function generate(int $project_id, array $project): array {
        if (!MLGIF_Helpers::imagick_available()) {
            return ['success' => false, 'error' => 'PHP Imagick não está disponível neste servidor. Ative a extensão php-imagick.'];
        }

        $settings = json_decode((string) ($project['settings_json'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $image_ids = json_decode((string) ($project['images'] ?? '[]'), true);
        if (!is_array($image_ids) || count($image_ids) === 0) {
            return ['success' => false, 'error' => 'Nenhuma imagem selecionada para o projeto.'];
        }

        // Trava de dimensão: nunca excede o limite global configurado nas Settings
        $global_settings = (new MLGIF_Settings())->get_settings();
        $max_w  = max(100, (int) ($global_settings['max_width']  ?? 1200));
        $max_h  = max(100, (int) ($global_settings['max_height'] ?? 1920));
        $width  = max(1, min($max_w, (int) ($settings['width']  ?? 800)));
        $height = max(1, min($max_h, (int) ($settings['height'] ?? 400)));
        $duration       = max(2, (int) ($settings['frame_duration'] ?? 100));
        $loop_flag      = isset($settings['loop_infinite']) ? (bool) $settings['loop_infinite'] : true;
        $loop           = $loop_flag ? 0 : max(1, (int) ($settings['loop_count'] ?? 1));
        $fit_mode       = in_array((string) ($settings['fit_mode'] ?? 'cover'), ['cover', 'contain', 'stretch'], true) ? (string) $settings['fit_mode'] : 'cover';
        $transition     = in_array((string) ($settings['transition'] ?? 'none'), ['none', 'fade', 'slide', 'zoom'], true) ? (string) $settings['transition'] : 'none';
        $optimize_level = max(1, min(3, (int) ($settings['optimize_level'] ?? 1)));

        // Cor de fundo e margens
        $bg_color_raw = trim((string) ($settings['bg_color'] ?? ''));
        $margin       = max(0, min(500, (int) ($settings['margin'] ?? 0)));
        // Validar bg_color: aceitar hex (#fff, #ffffff) ou nome CSS básico ou vazio (transparente)
        $bg_color_safe = '';
        if ($bg_color_raw !== '') {
            if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bg_color_raw)) {
                $bg_color_safe = $bg_color_raw;
            } elseif (preg_match('/^[a-zA-Z]{1,30}$/', $bg_color_raw)) {
                // Nome CSS (white, black, transparent, etc.)
                $bg_color_safe = strtolower($bg_color_raw);
            }
        }
        // Transparente = 'none' para o ImagickPixel; cor sólida = o valor
        $use_transparent_bg = ($bg_color_safe === '' || $bg_color_safe === 'transparent');
        $imagick_bg = $use_transparent_bg ? 'none' : $bg_color_safe;

        $frames = [];
        $errors = [];

        foreach ($image_ids as $image_id) {
            $file_path = get_attached_file((int) $image_id);
            if (!$file_path || !file_exists($file_path)) {
                $errors[] = 'Imagem ID ' . (int) $image_id . ' não encontrada.';
                continue;
            }
            $mime = mime_content_type($file_path);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                $errors[] = 'Imagem ID ' . (int) $image_id . ' tipo inválido: ' . $mime;
                continue;
            }
            $frames[] = $file_path;
        }

        if (count($frames) === 0) {
            return ['success' => false, 'error' => 'Nenhuma imagem válida. ' . implode(' | ', $errors)];
        }

        $upload    = wp_upload_dir();
        $base_path = trailingslashit((string) $upload['basedir']);
        $base_url  = trailingslashit((string) $upload['baseurl']);
        $filename  = 'ml-gif-pro-' . $project_id . '-' . date('Ymd-His') . '.gif';
        $out_path  = $base_path . $filename;
        $out_url   = $base_url . $filename;

        if (!is_writable($base_path)) {
            return ['success' => false, 'error' => 'Sem permissão de escrita em: ' . $base_path];
        }

        try {
            $animation = new Imagick();
            $animation->setFormat('GIF');
            $bg_color  = new ImagickPixel($imagick_bg);

            $processed_frames = [];

            foreach ($frames as $frame_path) {
                $src = new Imagick($frame_path);

                if ($src->getNumberImages() > 1) {
                    $src->setIteratorIndex(0);
                    $single = $src->getImage();
                    $src->destroy();
                } else {
                    $single = $src;
                }

                $single->setImageFormat('gif');
                $single->setImageColorspace(Imagick::COLORSPACE_SRGB);
                $single->stripImage();

                $src_w = $single->getImageWidth();
                $src_h = $single->getImageHeight();

                switch ($fit_mode) {
                    case 'stretch':
                        $single->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
                        break;

                    case 'contain':
                        // Área disponível descontando margens
                        $avail_w  = max(1, $width  - $margin * 2);
                        $avail_h  = max(1, $height - $margin * 2);
                        $ratio    = min($avail_w / $src_w, $avail_h / $src_h);
                        $new_w    = (int) round($src_w * $ratio);
                        $new_h    = (int) round($src_h * $ratio);
                        $offset_x = (int) round(($width  - $new_w) / 2);
                        $offset_y = (int) round(($height - $new_h) / 2);
                        $single->resizeImage($new_w, $new_h, Imagick::FILTER_LANCZOS, 1);
                        $canvas = new Imagick();
                        $canvas->newImage($width, $height, $bg_color, 'gif');
                        if ($use_transparent_bg) {
                            $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
                        }
                        $canvas->compositeImage($single, Imagick::COMPOSITE_OVER, $offset_x, $offset_y);
                        $single->destroy();
                        $single = $canvas;
                        break;

                    case 'cover':
                    default:
                        // Com margem no cover: redimensionar para a área disponível e centralizar
                        if ($margin > 0) {
                            $avail_w = max(1, $width  - $margin * 2);
                            $avail_h = max(1, $height - $margin * 2);
                            $ratio   = min($avail_w / $src_w, $avail_h / $src_h);
                            $new_w   = (int) round($src_w * $ratio);
                            $new_h   = (int) round($src_h * $ratio);
                            $offset_x = (int) round(($width  - $new_w) / 2);
                            $offset_y = (int) round(($height - $new_h) / 2);
                            $single->resizeImage($new_w, $new_h, Imagick::FILTER_LANCZOS, 1);
                            $canvas_m = new Imagick();
                            $canvas_m->newImage($width, $height, $bg_color, 'gif');
                            if ($use_transparent_bg) {
                                $canvas_m->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
                            }
                            $canvas_m->compositeImage($single, Imagick::COMPOSITE_OVER, $offset_x, $offset_y);
                            $single->destroy();
                            $single = $canvas_m;
                        } else {
                            $ratio = max($width / $src_w, $height / $src_h);
                            $new_w = (int) ceil($src_w * $ratio);
                            $new_h = (int) ceil($src_h * $ratio);
                            $single->resizeImage($new_w, $new_h, Imagick::FILTER_LANCZOS, 1);
                            $off_x = (int) floor(($new_w - $width) / 2);
                            $off_y = (int) floor(($new_h - $height) / 2);
                            $single->cropImage($width, $height, $off_x, $off_y);
                            $single->setImagePage($width, $height, 0, 0);
                        }
                        break;
                }

                $processed_frames[] = $single;
            }

            // Montar animação com ou sem transições
            if ($transition !== 'none' && count($processed_frames) > 1) {
                $steps    = 5;
                $step_dur = max(2, (int) round($duration / ($steps + 1)));

                foreach ($processed_frames as $idx => $pframe) {
                    $pframe->setImageDelay($duration);
                    $pframe->setImageFormat('gif');
                    $animation->addImage($pframe);

                    $next = $processed_frames[($idx + 1) % count($processed_frames)];

                    for ($s = 1; $s <= $steps; $s++) {
                        $t      = $s / ($steps + 1);
                        $canvas = new Imagick();
                        $canvas->newImage($width, $height, $bg_color, 'gif');

                        if ($transition === 'fade') {
                            // BUG-C FIX: deep copy via blob em vez de clone PHP
                            $a = $this->clone_frame($pframe);
                            $b = $this->clone_frame($next);
                            $a->setImageOpacity(1.0 - $t);
                            $b->setImageOpacity($t);
                            $canvas->compositeImage($a, Imagick::COMPOSITE_OVER, 0, 0);
                            $canvas->compositeImage($b, Imagick::COMPOSITE_OVER, 0, 0);
                            $a->destroy();
                            $b->destroy();
                        } elseif ($transition === 'slide') {
                            $shift = (int) round($width * $t);
                            $a     = $this->clone_frame($pframe);
                            $b     = $this->clone_frame($next);
                            $canvas->compositeImage($a, Imagick::COMPOSITE_OVER, -$shift, 0);
                            $canvas->compositeImage($b, Imagick::COMPOSITE_OVER, $width - $shift, 0);
                            $a->destroy();
                            $b->destroy();
                        } elseif ($transition === 'zoom') {
                            $scale = 1.0 + ($t * 0.08);
                            $zw    = (int) round($width * $scale);
                            $zh    = (int) round($height * $scale);
                            $a     = $this->clone_frame($pframe);
                            $a->resizeImage($zw, $zh, Imagick::FILTER_LANCZOS, 1);
                            $ox    = (int) floor(($zw - $width) / 2);
                            $oy    = (int) floor(($zh - $height) / 2);
                            $a->cropImage($width, $height, $ox, $oy);
                            $a->setImagePage($width, $height, 0, 0);
                            $canvas->compositeImage($a, Imagick::COMPOSITE_OVER, 0, 0);
                            $a->destroy();
                        }

                        $canvas->setImageDelay($step_dur);
                        $canvas->setImageFormat('gif');
                        $animation->addImage($canvas);
                        $canvas->destroy();
                    }
                }
            } else {
                foreach ($processed_frames as $pframe) {
                    $pframe->setImageDelay($duration);
                    $pframe->setImageFormat('gif');
                    $animation->addImage($pframe);
                }
            }

            // Setar loop antes de deconstruct
            $animation->resetIterator();
            foreach ($animation as $frame) {
                $frame->setImageIterations($loop);
            }
            $animation->resetIterator();

            // BUG-D FIX: capturar delays antes de optimizeImageLayers
            $delays_before = [];
            $animation->resetIterator();
            foreach ($animation as $i => $frame) {
                $delays_before[$i] = $frame->getImageDelay();
            }
            $animation->resetIterator();

            // deconstructImages + optimizeImageLayers retornam NOVO objeto
            $deconstructed = $animation->deconstructImages();
            $animation->destroy();

            $optimized_base = $deconstructed->optimizeImageLayers();
            $deconstructed->destroy();

            // BUG-D FIX: reaplicar delays e loop no objeto otimizado base
            $optimized_base->resetIterator();
            $i = 0;
            foreach ($optimized_base as $frame) {
                $frame->setImageDelay($delays_before[$i] ?? $duration);
                $frame->setImageIterations($loop);
                $i++;
            }
            $optimized_base->resetIterator();

            // Passar pelo MLGIF_Optimizer
            $optimizer    = new MLGIF_Optimizer();
            $opt_result   = $optimizer->optimize($optimized_base, $optimize_level);
            $final_object = $opt_result['imagick'];

            // Reaplicar loop no objeto final pós-optimizer
            $final_object->resetIterator();
            foreach ($final_object as $frame) {
                $frame->setImageIterations($loop);
            }
            $final_object->resetIterator();

            $final_object->writeImages($out_path, true);
            $final_object->destroy();

            foreach ($processed_frames as $pf) {
                try { $pf->destroy(); } catch (Exception $e) {}
            }

            // BUG-A + BUG-B FIX: patch binário correto
            if (file_exists($out_path)) {
                $this->patch_gif_loop($out_path, $loop);
            }

        } catch (ImagickException $e) {
            return ['success' => false, 'error' => 'Imagick: ' . $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Erro: ' . $e->getMessage()];
        }

        if (!file_exists($out_path) || filesize($out_path) === 0) {
            return ['success' => false, 'error' => 'GIF não gravado. Path: ' . $out_path];
        }

        $final_size = (int) filesize($out_path);

        // FIX: setar status 'generated' no DB ANTES de registrar na Media Library.
        // O poll do JS detecta 'generated' imediatamente.
        // wp_generate_attachment_metadata pode ser lento — não deve bloquear o status.
        MLGIF_DB::update_output($project_id, $out_path, $out_url, 0);

        // Registrar na Media Library — envolvido em try/catch
        // wp_generate_attachment_metadata pode estourar memória em GIFs grandes
        // Nunca deve impedir o retorno do resultado
        try {
            $attachment_id = $this->register_output_attachment($project_id, $project, $out_path, $out_url);
            if ($attachment_id > 0) {
                MLGIF_DB::update_attachment_id($project_id, $attachment_id);
            }
        } catch (Throwable $e) {
            // Falha silenciosa — GIF já foi salvo e status já é 'generated'
            // O GIF funciona normalmente mesmo sem entrada na Media Library
        }

        $result = [
            'success'         => true,
            'url'             => $out_url,
            'path'            => $out_path,
            'size'            => MLGIF_Helpers::format_bytes($final_size),
            'size_bytes'      => $final_size,
            'optimize_level'  => $optimize_level,
            'optimize_colors' => $opt_result['colors'],
        ];
        if (!empty($errors)) {
            $result['warnings'] = $errors;
        }
        return $result;
    }

    /**
     * BUG-C FIX: Deep copy real de frame Imagick via blob.
     * PHP clone não copia o recurso interno do Imagick.
     */
    private function clone_frame(Imagick $src): Imagick {
        $copy = new Imagick();
        $copy->readImageBlob($src->getImageBlob());
        $copy->setImageFormat('gif');
        return $copy;
    }

    /**
     * Registra o GIF final na Biblioteca de Mídia sem alterar o arquivo físico.
     */
    private function register_output_attachment(int $project_id, array $project, string $out_path, string $out_url): int {
        if (!file_exists($out_path)) {
            return 0;
        }

        $existing_id = attachment_url_to_postid($out_url);
        if ($existing_id > 0) {
            return (int) $existing_id;
        }

        $previous_attachment_id = isset($project['attachment_id']) ? (int) $project['attachment_id'] : 0;
        if ($previous_attachment_id > 0 && get_post($previous_attachment_id)) {
            wp_delete_attachment($previous_attachment_id, true);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $title = sanitize_text_field((string) ($project['title'] ?? ''));
        if ($title === '') {
            $title = 'ML Gif Pro #' . $project_id;
        }

        $filetype = wp_check_filetype(basename($out_path), null);
        $attachment = [
            'guid'           => $out_url,
            'post_mime_type' => !empty($filetype['type']) ? $filetype['type'] : 'image/gif',
            'post_title'     => $title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $out_path);
        if (is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
            return 0;
        }

        // Proteger contra estouro de memória em GIFs grandes
        @ini_set('memory_limit', '768M');
        try {
            $metadata = wp_generate_attachment_metadata((int) $attachment_id, $out_path);
            if (is_array($metadata)) {
                wp_update_attachment_metadata((int) $attachment_id, $metadata);
            }
        } catch (Throwable $e) {
            // Falha no metadata não cancela o registro do attachment
        }

        update_post_meta((int) $attachment_id, '_mlgif_project_id', $project_id);
        update_post_meta((int) $attachment_id, '_mlgif_generated', '1');

        return (int) $attachment_id;
    }

    /**
     * BUG-A + BUG-B FIX: Patch binário correto do bloco NETSCAPE2.0 no GIF89a.
     *
     * Estrutura do Application Extension Block:
     *   0x21 0xFF 0x0B [11 bytes: "NETSCAPE2.0"] 0x03 0x01 [lo] [hi] 0x00
     *
     * strpos() aponta para 'N' de "NETSCAPE2.0" (pos).
     *   pos+11 = 0x03  (Sub-block size)
     *   pos+12 = 0x01  (Sub-block ID)
     *   pos+13 = lo    (loop count low byte)   ← FIX: era pos+14
     *   pos+14 = hi    (loop count high byte)  ← FIX: era pos+15
     *   pos+15 = 0x00  (Block Terminator)
     *
     * Se o bloco não existir, injeta na posição correta após o LSD + GCT.
     */
    private function patch_gif_loop(string $path, int $loop): void {
        $data = file_get_contents($path);
        if ($data === false || strlen($data) < 13) {
            return;
        }

        $netscape = 'NETSCAPE2.0';
        $pos      = strpos($data, $netscape);

        $lo = $loop & 0xFF;
        $hi = ($loop >> 8) & 0xFF;

        if ($pos !== false) {
            // BUG-A FIX: offsets corretos
            $data[$pos + 11] = chr(0x03);
            $data[$pos + 12] = chr(0x01);
            $data[$pos + 13] = chr($lo);
            $data[$pos + 14] = chr($hi);
            // pos+15 = 0x00 terminator — preservado
        } else {
            // BUG-B FIX: calcular posição correta após LSD + GCT
            // Header GIF89a = 6 bytes, LSD = 7 bytes
            // Se bit 7 do packed field (byte 10) = 1, há GCT de tamanho 3 * 2^(bits 0-2 + 1)
            $packed  = ord($data[10]);
            $has_gct = ($packed & 0x80) !== 0;
            $gct_size = $has_gct ? (3 * (2 ** (($packed & 0x07) + 1))) : 0;
            $inject   = 6 + 7 + $gct_size;

            if ($inject > strlen($data)) {
                $inject = 13; // fallback seguro
            }

            $block = "\x21\xFF\x0B" . $netscape . "\x03\x01" . chr($lo) . chr($hi) . "\x00";
            $data  = substr($data, 0, $inject) . $block . substr($data, $inject);
        }

        file_put_contents($path, $data);
    }
}
