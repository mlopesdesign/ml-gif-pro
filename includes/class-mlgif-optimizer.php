<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MLGIF_Optimizer
 *
 * Reduz o tamanho do GIF gerado via três estratégias progressivas:
 *
 *  Level 1 — padrão (já aplicado no engine): quantizeImages(256) + deconstructImages + optimizeImageLayers
 *  Level 2 — otimização moderada: paleta reduzida (128 cores), remapeamento, lossy inter-frame diff
 *  Level 3 — otimização agressiva: paleta mínima (64 cores), dithering Floyd-Steinberg, max layer diff
 *
 * Integração: chamado ao final de MLGIF_Engine::generate(), ANTES de writeImages().
 * O objeto Imagick otimizado é retornado com metadados de redução.
 */
final class MLGIF_Optimizer {

    /**
     * Aplica o nível de otimização ao objeto Imagick.
     *
     * @param Imagick $animation  Objeto com os frames já montados (antes de writeImages).
     * @param int     $level      1 = básico, 2 = moderado, 3 = agressivo.
     * @return array{imagick: Imagick, colors: int, dither: bool}
     */
    public function optimize(Imagick $animation, int $level): array {
        $level = max(1, min(3, $level));

        switch ($level) {
            case 3:
                return $this->apply_aggressive($animation);
            case 2:
                return $this->apply_moderate($animation);
            default:
                return $this->apply_basic($animation);
        }
    }

    /**
     * Level 1 — básico (256 cores, sem dithering extra).
     * O engine já faz deconstructImages + optimizeImageLayers; aqui apenas quantize.
     */
    private function apply_basic(Imagick $animation): array {
        $animation->quantizeImages(
            256,
            Imagick::COLORSPACE_SRGB,
            0,
            false,
            false
        );
        return ['imagick' => $animation, 'colors' => 256, 'dither' => false];
    }

    /**
     * Level 2 — moderado (128 cores, remapeamento adaptativo).
     */
    private function apply_moderate(Imagick $animation): array {
        // Remapear paleta global antes de quantizar
        $animation->resetIterator();

        // Quantize com remapeamento (último param = true = remap)
        $animation->quantizeImages(
            128,
            Imagick::COLORSPACE_SRGB,
            0,
            false,
            true
        );

        // Reaplica layer diff para maximizar transparência entre frames
        $deconstructed = $animation->deconstructImages();
        $animation->destroy();

        $optimized = $deconstructed->optimizeImageLayers();
        $deconstructed->destroy();

        return ['imagick' => $optimized, 'colors' => 128, 'dither' => false];
    }

    /**
     * Level 3 — agressivo (64 cores, Floyd-Steinberg dithering, max inter-frame).
     */
    private function apply_aggressive(Imagick $animation): array {
        // Dithering Floyd-Steinberg via quantize com measureError = false, dither = true
        // Imagick::DITHER_FS não é constante em todas as builds; usamos quantize com o flag
        $animation->quantizeImages(
            64,
            Imagick::COLORSPACE_SRGB,
            0,
            true,   // measureError = true aplica erro difuso (Floyd-Steinberg via Imagick)
            true    // remapColors
        );

        $deconstructed = $animation->deconstructImages();
        $animation->destroy();

        $optimized = $deconstructed->optimizeImageLayers();
        $deconstructed->destroy();

        // Segunda passagem de otimização de camadas
        $optimized2 = $optimized->optimizeImageLayers();
        $optimized->destroy();

        return ['imagick' => $optimized2, 'colors' => 64, 'dither' => true];
    }

    /**
     * Calcula a redução de tamanho e retorna string legível.
     *
     * @param int $size_before Bytes antes da otimização.
     * @param int $size_after  Bytes após a otimização.
     * @return string Ex: "1,2 MB → 780 KB (−37%)"
     */
    public static function reduction_info(int $size_before, int $size_after): string {
        if ($size_before <= 0) {
            return '';
        }
        $pct = round((1 - ($size_after / $size_before)) * 100);
        return sprintf(
            '%s → %s (%s%d%%)',
            MLGIF_Helpers::format_bytes($size_before),
            MLGIF_Helpers::format_bytes($size_after),
            $pct > 0 ? '−' : '+',
            abs($pct)
        );
    }

    /**
     * Labels para a UI admin.
     */
    public static function level_labels(): array {
        return [
            '1' => 'Básica — 256 cores (padrão)',
            '2' => 'Moderada — 128 cores (−20 a −40%)',
            '3' => 'Agressiva — 64 cores + dithering (−40 a −70%)',
        ];
    }
}
