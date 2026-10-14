<?php
/**
 * Desinstalação do ML Gif Pro.
 *
 * Remove somente o cache do updater. Os arquivos .gif gerados ficam em /uploads e podem estar em
 * uso em banners, carrosséis e páginas: apagá-los quebraria imagens do site. Projetos (tabela
 * mlgif_projects), configurações e licença também são preservados para uma reinstalação.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_transient('mlgif_updater_release_cache');
