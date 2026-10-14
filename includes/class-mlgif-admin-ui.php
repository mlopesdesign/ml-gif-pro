<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLGIF_Admin_UI {

    public function render(array $settings, array $license_state, array $license_config, string $active_tab, string $toast_message = '', string $toast_type = 'success', ?MLGIF_License $license_manager = null): void {
        $tabs = [
            'dashboard' => 'Dashboard',
            'projects'  => 'Projetos GIF',
            'settings'  => 'Configurações',
        ];
        if (($settings['enable_license_tab'] ?? '1') === '1') {
            $tabs['license'] = 'Licença';
        }
        if (!isset($tabs[$active_tab])) {
            $active_tab = 'dashboard';
        }
        $license_manager = $license_manager ?: new MLGIF_License();
        $total           = MLGIF_DB::count_projects();
        $generated       = MLGIF_DB::count_generated();
        $imagick_ok      = MLGIF_Helpers::imagick_available();
        ?>
        <div class="wrap mlpb-admin-wrap" data-toast-message="<?php echo esc_attr($toast_message); ?>" data-toast-type="<?php echo esc_attr($toast_type); ?>" data-mlgif-nonce="<?php echo esc_attr(wp_create_nonce('mlgif_ajax')); ?>">
            <div id="mlpb-toast-area" aria-live="polite" aria-atomic="true"></div>

            <section class="mlpb-hero">
                <div class="mlpb-hero-brand">
                    <div class="mlpb-hero-mark"><img src="<?php echo esc_url(MLGIF_Helpers::logo_url()); ?>" alt="ML Lopes Design"></div>
                    <div class="mlpb-hero-copy">
                        <span class="mlpb-hero-eyebrow">ML Lopes Design · GIF Generator</span>
                        <h1><?php echo esc_html($settings['plugin_title'] ?? 'ML Gif Pro'); ?></h1>
                        <p class="mlpb-intro"><?php echo esc_html($settings['plugin_subtitle'] ?? 'Gerador profissional de GIF animado físico para WordPress'); ?></p>
                    </div>
                </div>
                <div class="mlpb-hero-meta">
                    <span class="mlpb-version-badge">v<?php echo esc_html(MLGIF_VERSION); ?></span>
                    <div class="mlpb-hero-tags">
                        <span class="mlpb-chip <?php echo $imagick_ok ? '' : 'mlgif-chip-error'; ?>"><?php echo $imagick_ok ? 'Imagick OK' : 'Imagick ausente'; ?></span>
                        <span class="mlpb-chip"><?php echo esc_html($license_manager->get_plan_label((string) ($license_state['plan'] ?? 'free'))); ?></span>
                    </div>
                </div>
            </section>

            <?php if (!$imagick_ok): ?>
            <div class="mlgif-alert mlgif-alert-error">
                <strong>⚠ PHP Imagick não está disponível.</strong> A geração de GIF requer a extensão Imagick habilitada no servidor. Contate seu provedor de hospedagem para ativar a extensão <code>php-imagick</code>.
            </div>
            <?php endif; ?>

            <nav class="mlpb-tab-nav" aria-label="Navegação do plugin">
                <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                    <button type="button" class="mlpb-tab-button <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>" data-tab-target="mlgif-tab-<?php echo esc_attr($tab_key); ?>"><?php echo esc_html($tab_label); ?></button>
                <?php endforeach; ?>
            </nav>

            <!-- DASHBOARD -->
            <section id="mlgif-tab-dashboard" class="mlpb-tab-panel <?php echo $active_tab === 'dashboard' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'dashboard' ? '' : 'hidden'; ?>>
                <div class="mlpb-summary-grid">
                    <article class="mlpb-summary-card">
                        <span class="mlpb-summary-label">Total de Projetos</span>
                        <strong><?php echo esc_html($total); ?></strong>
                        <small>Projetos criados no banco.</small>
                    </article>
                    <article class="mlpb-summary-card">
                        <span class="mlpb-summary-label">GIFs Gerados</span>
                        <strong><?php echo esc_html($generated); ?></strong>
                        <small>Arquivos físicos em /uploads/.</small>
                    </article>
                    <article class="mlpb-summary-card">
                        <span class="mlpb-summary-label">Motor GIF</span>
                        <strong><?php echo $imagick_ok ? 'PHP Imagick' : 'Indisponível'; ?></strong>
                        <small><?php echo $imagick_ok ? 'Motor principal ativo e pronto.' : 'Contate o suporte do servidor.'; ?></small>
                    </article>
                    <article class="mlpb-summary-card">
                        <span class="mlpb-summary-label">Licença</span>
                        <strong><?php echo esc_html($license_manager->get_plan_label((string) ($license_state['plan'] ?? 'free'))); ?></strong>
                        <small>Status: <?php echo esc_html($license_manager->get_status_label((string) ($license_state['status'] ?? 'inactive'))); ?></small>
                    </article>
                </div>
                <div class="mlpb-grid">
                    <article class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Como usar o ML Gif Pro</h2><p class="mlpb-muted">Fluxo completo para gerar seu GIF físico.</p></div></div>
                        <ul class="mlpb-list">
                            <li><strong>1.</strong> Acesse a aba <strong>Projetos GIF</strong> e clique em <em>Novo Projeto</em>.</li>
                            <li><strong>2.</strong> Dê um título ao projeto e selecione as imagens da Media Library.</li>
                            <li><strong>3.</strong> Arraste para reordenar os frames conforme desejado.</li>
                            <li><strong>4.</strong> Configure largura, altura, duração, transição, modo de ajuste e <strong>nível de otimização</strong>.</li>
                            <li><strong>5.</strong> Clique em <strong>Salvar e Gerar GIF</strong>.</li>
                            <li><strong>6.</strong> Copie a URL gerada e cole no campo de imagem do seu banner ou carrossel.</li>
                        </ul>
                    </article>
                    <article class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Requisitos do servidor</h2><p class="mlpb-muted">O que é necessário para gerar GIFs.</p></div></div>
                        <ul class="mlpb-list">
                            <?php
                            $upload_dir  = wp_upload_dir();
                            $upload_path = trailingslashit($upload_dir['basedir']);
                            $writable    = is_writable($upload_path);
                            $imagick_ver = '';
                            $imagick_formats = [];
                            if ($imagick_ok) {
                                try {
                                    $im = new Imagick();
                                    $imagick_ver = $im->getVersion()['versionString'] ?? '';
                                    $imagick_formats = $im->queryFormats('GIF');
                                    $im->destroy();
                                } catch (Exception $e) {}
                            }
                            ?>
                            <li><?php echo $imagick_ok ? '✅' : '❌'; ?> PHP Imagick <?php echo $imagick_ok ? 'habilitado' : '<strong>NÃO disponível</strong>'; ?><?php if ($imagick_ver) echo ' — <small>' . esc_html(substr($imagick_ver, 0, 60)) . '</small>'; ?></li>
                            <li><?php echo (!empty($imagick_formats)) ? '✅' : '⚠'; ?> Suporte GIF no Imagick: <?php echo !empty($imagick_formats) ? 'OK' : '<strong>NÃO detectado</strong>'; ?></li>
                            <li><?php echo $writable ? '✅' : '❌'; ?> /wp-content/uploads/ <?php echo $writable ? 'com escrita' : '<strong>SEM permissão de escrita</strong>'; ?></li>
                            <li>✅ WordPress <?php echo esc_html(get_bloginfo('version')); ?></li>
                            <li>✅ PHP <?php echo esc_html(PHP_VERSION); ?></li>
                        </ul>
                        <?php
                        $error_projects = array_filter(MLGIF_DB::get_all_projects(), fn($p) => $p['status'] === 'error');
                        if (!empty($error_projects)) :
                        ?>
                        <div class="mlgif-alert mlgif-alert-error" style="margin-top:12px;">
                            <strong>⚠ <?php echo count($error_projects); ?> projeto(s) com erro:</strong>
                            <?php foreach ($error_projects as $ep) : ?>
                            <div style="margin-top:6px;font-size:12px;">
                                <strong>#<?php echo (int)$ep['id']; ?> <?php echo esc_html($ep['title']); ?>:</strong>
                                <?php echo esc_html($ep['error_message'] ?: 'Sem mensagem de erro.'); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="mlpb-note">Os GIFs são salvos em <code>/wp-content/uploads/</code>. A URL é usável em qualquer campo de imagem.</div>
                    </article>
                </div>
            </section>

            <!-- PROJECTS LIST / EDIT -->
            <section id="mlgif-tab-projects" class="mlpb-tab-panel <?php echo $active_tab === 'projects' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'projects' ? '' : 'hidden'; ?>>
                <?php $this->render_projects_panel($settings, $imagick_ok); ?>
            </section>

            <!-- SETTINGS -->
            <section id="mlgif-tab-settings" class="mlpb-tab-panel <?php echo $active_tab === 'settings' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'settings' ? '' : 'hidden'; ?>>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mlgif_save_settings'); ?>
                    <input type="hidden" name="action" value="mlgif_save_settings">
                    <div class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Configurações iniciais</h2><p class="mlpb-muted">Defina o padrão usado ao criar novos projetos GIF.</p></div></div>
                        <div class="mlpb-form-grid">
                            <div class="mlpb-field"><label for="mlgif-plugin-title">Título do plugin</label><input id="mlgif-plugin-title" type="text" name="mlgif_settings[plugin_title]" value="<?php echo esc_attr($settings['plugin_title'] ?? ''); ?>"></div>
                            <div class="mlpb-field"><label for="mlgif-menu-label">Rótulo do menu</label><input id="mlgif-menu-label" type="text" name="mlgif_settings[menu_label]" value="<?php echo esc_attr($settings['menu_label'] ?? ''); ?>"></div>
                            <div class="mlpb-field" style="grid-column:1/-1;"><label for="mlgif-plugin-subtitle">Subtítulo</label><input id="mlgif-plugin-subtitle" type="text" name="mlgif_settings[plugin_subtitle]" value="<?php echo esc_attr($settings['plugin_subtitle'] ?? ''); ?>"></div>
                            <div class="mlpb-field mlgif-field-highlight">
                                <label for="mlgif-max-width">⚠ Largura máxima permitida (px)</label>
                                <input id="mlgif-max-width" type="number" min="100" max="2000" name="mlgif_settings[max_width]" value="<?php echo esc_attr($settings['max_width'] ?? '1200'); ?>">
                                <p class="mlpb-muted" style="margin:4px 0 0;font-size:12px;">Nenhum GIF pode ser gerado com largura acima deste valor. Padrão recomendado: 1200px.</p>
                            </div>
                            <div class="mlpb-field mlgif-field-highlight">
                                <label for="mlgif-max-height">⚠ Altura máxima permitida (px)</label>
                                <input id="mlgif-max-height" type="number" min="100" max="2000" name="mlgif_settings[max_height]" value="<?php echo esc_attr($settings['max_height'] ?? '800'); ?>">
                                <p class="mlpb-muted" style="margin:4px 0 0;font-size:12px;">Nenhum GIF pode ser gerado com altura acima deste valor. Padrão recomendado: 800px.</p>
                            </div>
                            <div class="mlpb-field" style="grid-column:1/-1;">
                                <label><input type="checkbox" name="mlgif_settings[lock_ratio_default]" value="1" <?php checked(($settings['lock_ratio_default'] ?? '1') === '1'); ?>> Habilitar trava de proporção por padrão nos novos projetos</label>
                                <p class="mlpb-muted" style="margin:4px 0 0;font-size:12px;">Ao alterar largura, a altura é recalculada automaticamente para manter a proporção.</p>
                            </div>
                            <div class="mlpb-field"><label for="mlgif-default-width">Largura padrão (px)</label><input id="mlgif-default-width" type="number" min="1" max="<?php echo esc_attr($settings['max_width'] ?? '1200'); ?>" name="mlgif_settings[default_width]" value="<?php echo esc_attr($settings['default_width'] ?? '800'); ?>"></div>
                            <div class="mlpb-field"><label for="mlgif-default-height">Altura padrão (px)</label><input id="mlgif-default-height" type="number" min="1" max="<?php echo esc_attr($settings['max_height'] ?? '800'); ?>" name="mlgif_settings[default_height]" value="<?php echo esc_attr($settings['default_height'] ?? '400'); ?>"></div>
                            <div class="mlpb-field"><label for="mlgif-default-duration">Duração padrão do frame (cs)</label><input id="mlgif-default-duration" type="number" min="1" max="500" name="mlgif_settings[default_frame_duration]" value="<?php echo esc_attr($settings['default_frame_duration'] ?? '100'); ?>"></div>
                            <div class="mlpb-field"><label for="mlgif-default-quality">Qualidade padrão (1–100)</label><input id="mlgif-default-quality" type="number" min="1" max="100" name="mlgif_settings[default_quality]" value="<?php echo esc_attr($settings['default_quality'] ?? '85'); ?>"></div>
                            <div class="mlpb-field"><label for="mlgif-default-fit">Modo de ajuste padrão</label><select id="mlgif-default-fit" name="mlgif_settings[default_fit_mode]"><option value="cover" <?php selected(($settings['default_fit_mode'] ?? 'cover'), 'cover'); ?>>Cover (recortar para preencher)</option><option value="contain" <?php selected(($settings['default_fit_mode'] ?? 'cover'), 'contain'); ?>>Contain (encaixar com fundo)</option><option value="stretch" <?php selected(($settings['default_fit_mode'] ?? 'cover'), 'stretch'); ?>>Stretch (esticar)</option></select></div>
                            <div class="mlpb-field"><label for="mlgif-default-transition">Transição padrão</label><select id="mlgif-default-transition" name="mlgif_settings[default_transition]"><option value="none" <?php selected(($settings['default_transition'] ?? 'none'), 'none'); ?>>Nenhuma</option><option value="fade" <?php selected(($settings['default_transition'] ?? 'none'), 'fade'); ?>>Fade (dissolve)</option><option value="slide" <?php selected(($settings['default_transition'] ?? 'none'), 'slide'); ?>>Slide (deslizar)</option><option value="zoom" <?php selected(($settings['default_transition'] ?? 'none'), 'zoom'); ?>>Zoom sutil</option></select></div>
                            <div class="mlpb-field"><label for="mlgif-default-loop-mode">Loop padrão</label><select id="mlgif-default-loop-mode" name="mlgif_settings[default_loop_mode]"><option value="infinite" <?php selected(($settings['default_loop_mode'] ?? 'infinite'), 'infinite'); ?>>Infinito</option><option value="fixed" <?php selected(($settings['default_loop_mode'] ?? 'infinite'), 'fixed'); ?>>Quantidade fixa</option></select></div>
                            <div class="mlpb-field"><label for="mlgif-default-loop-count">Repetições padrão</label><input id="mlgif-default-loop-count" type="number" min="1" max="999" name="mlgif_settings[default_loop_count]" value="<?php echo esc_attr($settings['default_loop_count'] ?? '3'); ?>"></div>
                            <div class="mlpb-field" style="grid-column:1/-1;">
                                <label for="mlgif-default-optimize">Nível de otimização padrão</label>
                                <select id="mlgif-default-optimize" name="mlgif_settings[default_optimize_level]">
                                    <?php foreach (MLGIF_Optimizer::level_labels() as $val => $label) : ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected((string) ($settings['default_optimize_level'] ?? '1'), $val); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mlpb-muted" style="margin:6px 0 0;font-size:12px;">Afeta apenas novos projetos. Cada projeto pode ter seu próprio nível ao editar.</p>
                            </div>
                        </div>
                        <div class="mlpb-actions" style="margin-top:18px;"><button type="submit" class="button button-primary mlpb-action-button">Salvar configurações</button></div>
                    </div>
                </form>

                <!-- GitHub Updater Diagnostics -->
                <div class="mlpb-card" style="margin-top:18px;">
                    <div class="mlpb-card-header"><div><h2>🔄 GitHub Auto-Updater</h2><p class="mlpb-muted">Status do mecanismo de atualização automática via GitHub Releases.</p></div></div>
                    <?php
                    $gh_repo      = 'mlopesdesign/ml-gif-pro-releases';
                    $gh_url       = 'https://github.com/' . $gh_repo;
                    $cache_key    = 'mlgif_updater_release_cache';
                    $cached       = get_transient($cache_key);
                    $latest_tag   = $cached ? ltrim((string)($cached['tag_name'] ?? ''), 'v') : '—';
                    $token_set    = defined('MLMD_GITHUB_TOKEN_ML_GIF_PRO') && MLMD_GITHUB_TOKEN_ML_GIF_PRO !== '';
                    $token_filter = (string) apply_filters('mlmd_github_token_ml_gif_pro', '');
                    $has_token    = $token_set || $token_filter !== '';
                    $update_avail = $latest_tag !== '—' && version_compare(MLGIF_VERSION, $latest_tag, '<');
                    ?>
                    <ul class="mlpb-list">
                        <li><strong>Repositório:</strong> <a href="<?php echo esc_url($gh_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($gh_repo); ?></a></li>
                        <li><strong>Versão instalada:</strong> <?php echo esc_html(MLGIF_VERSION); ?></li>
                        <li><strong>Última release detectada:</strong> <?php echo esc_html($latest_tag); ?><?php if ($update_avail) echo ' <span style="color:#d92d20;font-weight:700;">⬆ Update disponível!</span>'; ?></li>
                        <li><strong>Cache da API:</strong> <?php echo $cached ? '✅ Ativo (12h)' : '⏳ Não consultado ainda'; ?></li>
                        <li><strong>Token configurado:</strong> <?php echo $has_token ? '✅ Sim' : '— Não (repositório público não precisa)'; ?></li>
                        <li><strong>Constante para token privado:</strong> <code>MLMD_GITHUB_TOKEN_ML_GIF_PRO</code> no wp-config.php</li>
                    </ul>
                    <div class="mlpb-note">
                        Updates aparecem automaticamente na tela <strong>Plugins → Atualizações</strong> do WordPress quando uma nova release for publicada no GitHub com um ZIP nomeado <code>ml-gif-pro-vX_X_X_X.zip</code>.
                    </div>
                    <?php if ($update_avail) : ?>
                    <div class="mlgif-alert" style="background:#fffbeb;border:1px solid #fcd34d;color:#92400e;margin-top:12px;padding:12px 14px;border-radius:10px;">
                        ⬆ Nova versão <strong><?php echo esc_html($latest_tag); ?></strong> disponível. Vá em <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">Plugins</a> para atualizar.
                    </div>
                    <?php endif; ?>
                    <div class="mlpb-actions" style="margin-top:14px;">
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'ml-gif-pro', 'tab' => 'settings', 'mlgif_flush_updater' => '1', '_wpnonce' => wp_create_nonce('mlgif_flush_updater')])); ?>" class="button mlpb-action-button mlpb-action-button--secondary">🔄 Limpar cache e verificar agora</a>
                    </div>
                </div>
            </section>

            <!-- LICENSE -->
            <?php if (($settings['enable_license_tab'] ?? '1') === '1') : ?>
            <section id="mlgif-tab-license" class="mlpb-tab-panel <?php echo $active_tab === 'license' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'license' ? '' : 'hidden'; ?>>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mlgif_save_license'); ?>
                    <input type="hidden" name="action" value="mlgif_save_license">
                    <div class="mlpb-grid-2" style="display:grid;grid-template-columns:1.2fr .8fr;gap:18px;">
                        <article class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Licença ML Gif Pro</h2><p class="mlpb-muted">Serial, plano e validação remota.</p></div></div>
                            <div class="mlpb-form-grid">
                                <div class="mlpb-field"><label for="mlgif-license-plan">Plano</label>
                                    <select id="mlgif-license-plan" name="mlgif_license[plan]">
                                        <?php foreach (['free' => 'Free', 'trial' => 'Trial', 'full' => 'Full', 'lifetime' => 'Vitalício'] as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected(($license_state['plan'] ?? 'free') === $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mlpb-field"><label for="mlgif-license-status">Status</label>
                                    <select id="mlgif-license-status" name="mlgif_license[status]">
                                        <?php foreach (['inactive' => 'Inativo', 'active' => 'Ativo', 'expired' => 'Expirado', 'blocked' => 'Bloqueado'] as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected(($license_state['status'] ?? 'inactive') === $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mlpb-field"><label for="mlgif-license-email">E-mail</label><input id="mlgif-license-email" type="text" name="mlgif_license[email]" value="<?php echo esc_attr($license_state['email'] ?? ''); ?>"></div>
                                <div class="mlpb-field"><label for="mlgif-license-expires">Expira em</label><input id="mlgif-license-expires" type="text" name="mlgif_license[expires_at]" value="<?php echo esc_attr($license_state['expires_at'] ?? ''); ?>"></div>
                                <div class="mlpb-field" style="grid-column:1/-1;"><label for="mlgif-license-serial">Serial</label><input id="mlgif-license-serial" type="text" name="mlgif_license[serial]" value="<?php echo esc_attr($license_state['serial'] ?? ''); ?>"></div>
                            </div>
                            <div class="mlpb-actions" style="margin-top:18px;"><button type="submit" class="button button-primary mlpb-action-button">Salvar licença</button></div>
                        </article>
                        <article class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Estado atual</h2></div></div>
                            <ul class="mlpb-list">
                                <li><strong>Plano:</strong> <?php echo esc_html($license_manager->get_plan_label((string) ($license_state['plan'] ?? 'free'))); ?></li>
                                <li><strong>Status:</strong> <?php echo esc_html($license_manager->get_status_label((string) ($license_state['status'] ?? 'inactive'))); ?></li>
                                <li><strong>Serial:</strong> <?php echo esc_html((string) ($license_state['serial'] ?? '')); ?></li>
                                <li><strong>Última validação:</strong> <?php echo esc_html((string) ($license_state['last_check'] ?? '')); ?></li>
                                <li><strong>Endpoint:</strong> <?php echo esc_html((string) ($license_config['endpoint'] ?? 'Não configurado')); ?></li>
                                <li><strong>Mensagem:</strong> <?php echo esc_html((string) ($license_state['message'] ?? '')); ?></li>
                            </ul>
                        </article>
                    </div>
                </form>
            </section>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_projects_panel(array $global_settings, bool $imagick_ok): void {
        $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        if ($edit_id > 0) {
            $project = MLGIF_DB::get_project($edit_id);
            if ($project) {
                $this->render_edit_project($project, $imagick_ok, $global_settings);
                return;
            }
        }
        if (isset($_GET['action']) && $_GET['action'] === 'new') {
            $this->render_edit_project(null, $imagick_ok, $global_settings);
            return;
        }
        $this->render_project_list();
    }

    private function render_project_list(): void {
        $projects = MLGIF_DB::get_all_projects();
        ?>
        <div class="mlpb-card">
            <div class="mlpb-card-header">
                <div><h2>Projetos GIF</h2><p class="mlpb-muted">Todos os projetos criados. Clique para editar ou gerar o GIF.</p></div>
                <div class="mlpb-actions">
                    <button type="button" class="button button-primary mlpb-action-button" onclick="mlgifOpenNew()">+ Novo Projeto</button>
                </div>
            </div>
            <?php if (empty($projects)) : ?>
                <div class="mlpb-note">Nenhum projeto ainda. Clique em <strong>Novo Projeto</strong> para começar.</div>
            <?php else : ?>
                <table class="mlgif-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Dimensões</th>
                            <th>Status</th>
                            <th>Gerado em</th>
                            <th>URL do GIF</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $p) :
                        $s = json_decode((string) ($p['settings_json'] ?? '{}'), true);
                        $w = $s['width'] ?? '—';
                        $h = $s['height'] ?? '—';
                        $status_label = ['draft' => 'Rascunho', 'generating' => 'Gerando', 'generated' => 'Gerado', 'error' => 'Erro'][$p['status']] ?? $p['status'];
                        $status_class = ['draft' => 'mlgif-status-draft', 'generating' => 'mlgif-status-draft', 'generated' => 'mlgif-status-ok', 'error' => 'mlgif-status-error'][$p['status']] ?? '';
                    ?>
                        <tr>
                            <td><?php echo esc_html($p['id']); ?></td>
                            <td><strong><?php echo esc_html($p['title']); ?></strong></td>
                            <td><?php echo esc_html($w . ' × ' . $h); ?></td>
                            <td>
                                <span class="mlgif-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                <?php if ($p['status'] === 'error' && !empty($p['error_message'])) : ?>
                                    <div class="mlgif-inline-error" title="<?php echo esc_attr($p['error_message']); ?>">
                                        ⚠ <?php echo esc_html(mb_strimwidth((string)$p['error_message'], 0, 80, '…')); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($p['generated_at'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($p['output_url'])) : ?>
                                    <code class="mlgif-url-code"><?php echo esc_url($p['output_url']); ?></code>
                                <?php else : ?>
                                    <span class="mlpb-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="mlgif-actions-cell">
                                <button type="button" class="button mlpb-action-button mlpb-action-button--secondary mlgif-btn-sm" onclick="mlgifEditProject(<?php echo (int) $p['id']; ?>)">Editar</button>
                                <?php if (!empty($p['output_url'])) : ?>
                                    <button type="button" class="button mlpb-action-button mlpb-action-button--secondary mlgif-btn-sm" onclick="mlgifCopyUrl('<?php echo esc_js($p['output_url']); ?>')">Copiar URL</button>
                                <?php endif; ?>
                                <button type="button" class="button mlgif-btn-danger mlgif-btn-sm" onclick="mlgifDeleteProject(<?php echo (int) $p['id']; ?>, '<?php echo esc_js($p['title']); ?>')">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_edit_project(?array $project, bool $imagick_ok, array $global_settings): void {
        $id               = (int) ($project['id'] ?? 0);
        $title            = (string) ($project['title'] ?? '');
        $images           = $id > 0 ? json_decode((string) ($project['images'] ?? '[]'), true) : [];
        $project_settings = $id > 0 ? json_decode((string) ($project['settings_json'] ?? '{}'), true) : [];
        $default_optimize = (int) ($global_settings['default_optimize_level'] ?? 1);
        $default_settings = [
            'width'          => (int) ($global_settings['default_width'] ?? 800),
            'height'         => (int) ($global_settings['default_height'] ?? 400),
            'frame_duration' => (int) ($global_settings['default_frame_duration'] ?? 100),
            'quality'        => (int) ($global_settings['default_quality'] ?? 85),
            'fit_mode'       => (string) ($global_settings['default_fit_mode'] ?? 'cover'),
            'transition'     => (string) ($global_settings['default_transition'] ?? 'none'),
            'loop_infinite'  => (($global_settings['default_loop_mode'] ?? 'infinite') !== 'fixed'),
            'loop_count'     => (int) ($global_settings['default_loop_count'] ?? 3),
            'optimize_level' => $default_optimize,
        ];
        $settings       = is_array($project_settings) ? wp_parse_args($project_settings, $default_settings) : $default_settings;
        $output_url     = (string) ($project['output_url'] ?? '');
        $output_path    = (string) ($project['output_path'] ?? '');
        $status         = (string) ($project['status'] ?? 'draft');
        $error_msg      = (string) ($project['error_message'] ?? '');

        // Se há output_url, o GIF existe — ignorar status incorreto do banco
        if (!empty($output_url)) {
            $status    = 'generated';
            $error_msg = '';
        }

        $width          = (int) ($settings['width'] ?? 800);
        $height         = (int) ($settings['height'] ?? 400);
        $frame_duration = (int) ($settings['frame_duration'] ?? 100);
        $loop_infinite  = isset($settings['loop_infinite']) ? (bool) $settings['loop_infinite'] : true;
        $loop_count     = (int) ($settings['loop_count'] ?? 3);
        $fit_mode       = (string) ($settings['fit_mode'] ?? 'cover');
        $transition     = (string) ($settings['transition'] ?? 'none');
        $quality        = (int) ($settings['quality'] ?? 85);
        $optimize_level = max(1, min(3, (int) ($settings['optimize_level'] ?? 1)));
        $images_json    = wp_json_encode(is_array($images) ? array_map('intval', $images) : []);

        // Limites de dimensão e trava de proporção
        $max_dim_w     = max(100, (int) ($global_settings['max_width']  ?? 1200));
        $max_dim_h     = max(100, (int) ($global_settings['max_height'] ?? 800));
        $lock_ratio_on = ($global_settings['lock_ratio_default'] ?? '1') === '1';
        // Clamp dos valores salvos aos limites actuais
        $width  = min($max_dim_w, $width);
        $height = min($max_dim_h, $height);

        // Tamanho do GIF gerado (se existir)
        $gif_size_bytes = 0;
        $gif_size_str   = '';
        if ($output_path && file_exists($output_path)) {
            $gif_size_bytes = (int) filesize($output_path);
            $gif_size_str   = MLGIF_Helpers::format_bytes($gif_size_bytes);
        }

        $opt_level_saved  = (int) ($project['optimize_level'] ?? 1);
        $opt_colors_saved = (int) ($project['optimize_colors'] ?? 256);
        $opt_labels       = MLGIF_Optimizer::level_labels();
        ?>
        <div class="mlpb-card mlgif-edit-card">
            <div class="mlpb-card-header">
                <div>
                    <h2><?php echo $id > 0 ? 'Editar Projeto #' . esc_html($id) : 'Novo Projeto GIF'; ?></h2>
                    <p class="mlpb-muted">Configure e gere o GIF físico em /wp-content/uploads/</p>
                </div>
                <div class="mlpb-actions">
                    <button type="button" class="button mlpb-action-button mlpb-action-button--secondary" onclick="mlgifBackToList()">← Voltar</button>
                </div>
            </div>

            <?php if ($status === 'error' && $error_msg && empty($output_url)) : ?>
                <div class="mlgif-alert mlgif-alert-error"><?php echo esc_html($error_msg); ?></div>
            <?php endif; ?>

            <?php if (($status === 'generated' || ($status === 'error' && $output_url)) && $output_url) : ?>
            <div class="mlgif-result-panel" id="mlgif-result-panel">
                <div class="mlgif-result-preview">
                    <img src="<?php echo esc_url($output_url); ?>" alt="GIF Gerado" class="mlgif-preview-img">
                </div>
                <div class="mlgif-result-meta">
                    <h3>GIF Gerado com sucesso!</h3>
                    <div class="mlpb-field">
                        <label>URL do GIF (copie e cole em qualquer campo de imagem)</label>
                        <div class="mlgif-url-row">
                            <input type="text" id="mlgif-output-url-field" value="<?php echo esc_url($output_url); ?>" readonly class="mlgif-url-input">
                            <button type="button" class="button button-primary mlpb-action-button" onclick="mlgifCopyUrl('<?php echo esc_js($output_url); ?>')">Copiar URL</button>
                            <a href="<?php echo esc_url($output_url); ?>" download class="button mlpb-action-button mlpb-action-button--secondary">Download</a>
                        </div>
                    </div>
                    <?php if ($gif_size_str) : ?>
                    <div class="mlgif-optimize-badge">
                        <span class="mlgif-opt-size">💾 <?php echo esc_html($gif_size_str); ?></span>
                        <span class="mlgif-opt-level">🎨 <?php echo esc_html($opt_labels[$opt_level_saved] ?? 'Nível ' . $opt_level_saved); ?></span>
                        <span class="mlgif-opt-colors">🎭 <?php echo esc_html($opt_colors_saved); ?> cores</span>
                    </div>
                    <?php endif; ?>
                    <div class="mlpb-note">Shortcode: <code>[ml_gif_pro id="<?php echo esc_html($id); ?>"]</code></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="mlgif-editor-grid" id="mlgif-editor-anchor">
                <div class="mlgif-editor-left">
                    <div class="mlpb-field">
                        <label for="mlgif-title">Título do Projeto <span class="mlgif-required">*</span></label>
                        <input type="text" id="mlgif-title" value="<?php echo esc_attr($title); ?>" placeholder="Ex: Banner Produto Verão 2026">
                    </div>

                    <div class="mlgif-section-title">Frames / Imagens</div>
                    <div class="mlgif-frames-area" id="mlgif-frames-area">
                        <div id="mlgif-frames-list" class="mlgif-frames-list"></div>
                        <button type="button" class="button mlpb-action-button mlpb-action-button--secondary mlgif-add-images-btn" id="mlgif-media-btn">
                            + Adicionar Imagens da Media Library
                        </button>
                        <p class="mlpb-muted" style="margin-top:8px;font-size:12px;">Arraste os frames para reordenar. Clique no × para remover.</p>
                    </div>

                    <div class="mlgif-section-title" style="margin-top:20px;">Configurações do GIF</div>
                    <div class="mlpb-form-grid mlgif-settings-grid">
                        <div class="mlpb-field">
                            <label for="mlgif-width">
                                Largura (px)
                                <span class="mlgif-dim-max">(máx <?php echo esc_html($max_dim_w); ?>px)</span>
                            </label>
                            <div class="mlgif-dim-row">
                                <input type="number" id="mlgif-width" value="<?php echo esc_attr($width); ?>" min="1" max="<?php echo esc_attr($max_dim_w); ?>" data-dim="w">
                                <button type="button" id="mlgif-lock-ratio" class="mlgif-lock-btn <?php echo $lock_ratio_on ? 'is-locked' : ''; ?>" title="Travar/destravar proporção">
                                    <?php echo $lock_ratio_on ? '🔒' : '🔓'; ?>
                                </button>
                            </div>
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-height">
                                Altura (px)
                                <span class="mlgif-dim-max">(máx <?php echo esc_html($max_dim_h); ?>px)</span>
                            </label>
                            <input type="number" id="mlgif-height" value="<?php echo esc_attr($height); ?>" min="1" max="<?php echo esc_attr($max_dim_h); ?>" data-dim="h">
                            <div id="mlgif-size-indicator" class="mlgif-size-indicator"></div>
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-duration">Duração do frame (cs = 1/100s)</label>
                            <input type="number" id="mlgif-duration" value="<?php echo esc_attr($frame_duration); ?>" min="1" max="500">
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-quality">Qualidade (1–100)</label>
                            <input type="number" id="mlgif-quality" value="<?php echo esc_attr($quality); ?>" min="1" max="100">
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-fit">Modo de ajuste</label>
                            <select id="mlgif-fit">
                                <option value="cover" <?php selected($fit_mode, 'cover'); ?>>Cover (recortar para preencher)</option>
                                <option value="contain" <?php selected($fit_mode, 'contain'); ?>>Contain (encaixar com fundo)</option>
                                <option value="stretch" <?php selected($fit_mode, 'stretch'); ?>>Stretch (esticar)</option>
                            </select>
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-transition">Transição</label>
                            <select id="mlgif-transition">
                                <option value="none" <?php selected($transition, 'none'); ?>>Nenhuma</option>
                                <option value="fade" <?php selected($transition, 'fade'); ?>>Fade (dissolve)</option>
                                <option value="slide" <?php selected($transition, 'slide'); ?>>Slide (deslizar)</option>
                                <option value="zoom" <?php selected($transition, 'zoom'); ?>>Zoom sutil</option>
                            </select>
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-loop-mode">Loop</label>
                            <select id="mlgif-loop-mode">
                                <option value="infinite" <?php selected($loop_infinite, true); ?>>Infinito</option>
                                <option value="fixed" <?php selected($loop_infinite, false); ?>>Número fixo de repetições</option>
                            </select>
                        </div>
                        <div class="mlpb-field" id="mlgif-loop-count-wrap" style="<?php echo $loop_infinite ? 'display:none' : ''; ?>">
                            <label for="mlgif-loop-count">Repetições</label>
                            <input type="number" id="mlgif-loop-count" value="<?php echo esc_attr($loop_count); ?>" min="1" max="100">
                        </div>

                        <!-- FUNDO E MARGENS -->
                        <div class="mlpb-field">
                            <label for="mlgif-bg-color">Cor de fundo</label>
                            <div class="mlgif-color-row">
                                <input type="color" id="mlgif-bg-color-picker" value="<?php echo esc_attr(($settings['bg_color'] ?? '') ?: '#000000'); ?>" class="mlgif-color-picker" title="Escolher cor">
                                <input type="text" id="mlgif-bg-color" value="<?php echo esc_attr($settings['bg_color'] ?? ''); ?>" placeholder="transparente" maxlength="20" class="mlgif-color-text">
                                <button type="button" class="mlgif-btn-transparent" title="Limpar — transparente">✕</button>
                            </div>
                            <p class="mlpb-muted" style="margin:4px 0 0;font-size:12px;">Deixe vazio para transparente. Hex (#ffffff) ou nome (white). Aplica-se ao modo Contain e transições.</p>
                        </div>
                        <div class="mlpb-field">
                            <label for="mlgif-margin">Margem interna (px)</label>
                            <input type="number" id="mlgif-margin" value="<?php echo esc_attr((int) ($settings['margin'] ?? 0)); ?>" min="0" max="200" placeholder="0">
                            <p class="mlpb-muted" style="margin:4px 0 0;font-size:12px;">Espaço em pixels entre a imagem e as bordas do GIF. Default: 0 (sem margens).</p>
                        </div>

                        <!-- OPTIMIZER -->
                        <div class="mlpb-field" style="grid-column:1/-1;">
                            <label for="mlgif-optimize-level">Otimização de tamanho</label>
                            <select id="mlgif-optimize-level">
                                <?php foreach (MLGIF_Optimizer::level_labels() as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($optimize_level, (int) $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mlpb-muted" style="margin:6px 0 0;font-size:12px;">
                                <strong>Básica:</strong> 256 cores, sem perdas visíveis.
                                <strong>Moderada:</strong> 128 cores, −20 a −40% de tamanho.
                                <strong>Agressiva:</strong> 64 cores + dithering, −40 a −70% (recomendado para banners web).
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mlpb-actions mlgif-editor-actions">
                <button type="button" class="button button-primary mlpb-action-button" id="mlgif-save-generate-btn" <?php echo !$imagick_ok ? 'disabled title="Imagick não disponível"' : ''; ?>>
                    <?php echo $status === 'generated' ? '🔄 Regenerar GIF' : '⚡ Salvar e Gerar GIF'; ?>
                </button>
                <button type="button" class="button mlpb-action-button mlpb-action-button--secondary" id="mlgif-save-only-btn">
                    Salvar Rascunho
                </button>
            </div>

            <div id="mlgif-generating-overlay" class="mlgif-overlay" style="display:none;">
                <div class="mlgif-overlay-inner">
                    <div class="mlgif-spinner"></div>
                    <p>Gerando GIF... Aguarde.</p>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var PROJECT_ID   = <?php echo (int) $id; ?>;
            var imageIds     = <?php echo wp_json_encode(is_array($images) ? array_map('intval', $images) : []); ?>;
            var imageData    = {};
            var nonce        = '<?php echo esc_js(wp_create_nonce('mlgif_ajax')); ?>';
            var ajaxUrl      = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var adminBase    = '<?php echo esc_js(admin_url('admin.php')); ?>';
            var mediaFrame;
            var OPT_LABELS   = <?php echo wp_json_encode(MLGIF_Optimizer::level_labels()); ?>;
            var MAX_W        = <?php echo (int) $max_dim_w; ?>;
            var MAX_H        = <?php echo (int) $max_dim_h; ?>;
            var ratioLocked  = <?php echo $lock_ratio_on ? 'true' : 'false'; ?>;
            var currentRatio = <?php echo ($height > 0) ? round($width / $height, 6) : 2.0; ?>;

            /* ── Toast ── */
            function showToast(msg, type){
                var area = document.getElementById('mlpb-toast-area');
                if(!area) return;
                var t = document.createElement('div');
                t.className = 'mlpb-toast mlpb-toast-' + (type || 'success');
                t.textContent = msg;
                area.appendChild(t);
                requestAnimationFrame(function(){ t.classList.add('is-visible'); });
                setTimeout(function(){
                    t.classList.remove('is-visible');
                    setTimeout(function(){ t.parentNode && t.parentNode.removeChild(t); }, 220);
                }, 4200);
            }

            /* ── Lock de proporção ── */
            function updateRatioBtnUI(){
                var btn = document.getElementById('mlgif-lock-ratio');
                if(!btn) return;
                btn.textContent = ratioLocked ? '🔒' : '🔓';
                btn.classList.toggle('is-locked', ratioLocked);
                btn.title = ratioLocked ? 'Proporção travada — clique para destravar' : 'Proporção livre — clique para travar';
            }

            /* ── Indicador de tamanho estimado ── */
            function updateSizeIndicator(){
                var wEl = document.getElementById('mlgif-width');
                var hEl = document.getElementById('mlgif-height');
                var ind = document.getElementById('mlgif-size-indicator');
                if(!wEl || !hEl || !ind) return;
                var w = parseInt(wEl.value, 10) || 0;
                var h = parseInt(hEl.value, 10) || 0;
                if(w <= 0 || h <= 0){ ind.textContent = ''; return; }
                var frames  = Math.max(1, imageIds.length);
                var est     = Math.round(w * h * frames * 3 / 4);
                var estStr  = est >= 1048576 ? (est / 1048576).toFixed(1) + ' MB' : Math.round(est / 1024) + ' KB';
                var cls     = 'mlgif-size-indicator';
                var warn    = (w > MAX_W * 0.8 || h > MAX_H * 0.8 || est > 2097152);
                if(warn) cls += ' is-warning';
                var txt = '≈ ' + w + '×' + h + 'px · ' + frames + ' frames · ~' + estStr;
                if(w >= MAX_W){ txt += ' ⚠ largura máxima'; cls = 'mlgif-size-indicator is-error'; }
                if(h >= MAX_H){ txt += ' ⚠ altura máxima'; cls = 'mlgif-size-indicator is-error'; }
                ind.className   = cls;
                ind.textContent = txt;
            }

            /* ── Color picker de fundo ── */
            (function initColorPicker(){
                var picker  = document.getElementById('mlgif-bg-color-picker');
                var textEl  = document.getElementById('mlgif-bg-color');
                var clearBtn = document.querySelector('.mlgif-btn-transparent');

                if(picker && textEl){
                    // picker → text
                    picker.addEventListener('input', function(){
                        textEl.value = this.value;
                    });
                    // text → picker (só se hex válido)
                    textEl.addEventListener('input', function(){
                        var v = this.value.trim();
                        if(/^#[0-9a-fA-F]{6}$/.test(v)){
                            picker.value = v;
                        }
                    });
                }
                if(clearBtn && textEl && picker){
                    clearBtn.addEventListener('click', function(){
                        textEl.value  = '';
                        picker.value  = '#000000';
                    });
                }
            })();

            /* ── Listeners de dimensão ── */
            (function initDimListeners(){
                var wEl     = document.getElementById('mlgif-width');
                var hEl     = document.getElementById('mlgif-height');
                var lockBtn = document.getElementById('mlgif-lock-ratio');

                updateRatioBtnUI();
                updateSizeIndicator();

                if(lockBtn){
                    lockBtn.addEventListener('click', function(){
                        ratioLocked = !ratioLocked;
                        if(ratioLocked && wEl && hEl){
                            var w = parseInt(wEl.value, 10) || 1;
                            var h = parseInt(hEl.value, 10) || 1;
                            currentRatio = w / h;
                        }
                        updateRatioBtnUI();
                    });
                }

                if(wEl){
                    wEl.addEventListener('input', function(){
                        var w = Math.min(MAX_W, Math.max(1, parseInt(this.value, 10) || 1));
                        this.value = w;
                        if(ratioLocked && hEl && currentRatio > 0){
                            hEl.value = Math.min(MAX_H, Math.max(1, Math.round(w / currentRatio)));
                        }
                        updateSizeIndicator();
                    });
                    wEl.addEventListener('change', function(){
                        if(ratioLocked && hEl && currentRatio > 0){
                            var w = parseInt(this.value, 10) || 1;
                            hEl.value = Math.min(MAX_H, Math.max(1, Math.round(w / currentRatio)));
                        }
                        updateSizeIndicator();
                    });
                }

                if(hEl){
                    hEl.addEventListener('input', function(){
                        var h = Math.min(MAX_H, Math.max(1, parseInt(this.value, 10) || 1));
                        this.value = h;
                        if(ratioLocked && wEl && currentRatio > 0){
                            wEl.value = Math.min(MAX_W, Math.max(1, Math.round(h * currentRatio)));
                        } else if(wEl){
                            var w = parseInt(wEl.value, 10) || 1;
                            if(h > 0) currentRatio = w / h;
                        }
                        updateSizeIndicator();
                    });
                    hEl.addEventListener('change', function(){
                        if(ratioLocked && wEl && currentRatio > 0){
                            var h = parseInt(this.value, 10) || 1;
                            wEl.value = Math.min(MAX_W, Math.max(1, Math.round(h * currentRatio)));
                        }
                        updateSizeIndicator();
                    });
                }
            })();

            /* ── Frames / renderização ── */
            function renderFrames(){
                var list = document.getElementById('mlgif-frames-list');
                if(!list) return;
                list.innerHTML = '';
                if(imageIds.length === 0){
                    list.innerHTML = '<p class="mlpb-muted mlgif-no-frames">Nenhuma imagem selecionada.</p>';
                    updateSizeIndicator();
                    return;
                }
                imageIds.forEach(function(id, idx){
                    var info  = imageData[id] || {};
                    var thumb = info.thumb || '';
                    var div   = document.createElement('div');
                    div.className = 'mlgif-frame-item';
                    div.setAttribute('draggable', 'true');
                    div.dataset.id  = id;
                    div.dataset.idx = idx;
                    div.innerHTML   = '<span class="mlgif-frame-grip">☰</span>'
                        + '<button type="button" class="mlgif-frame-remove" data-id="' + id + '">×</button>'
                        + (thumb
                            ? '<img src="' + thumb + '" class="mlgif-frame-thumb" alt="Frame ' + (idx + 1) + '">'
                            : '<div class="mlgif-frame-thumb" style="background:#eef4f6;display:flex;align-items:center;justify-content:center;color:#52606d;font-size:11px;">sem thumb</div>')
                        + '<span class="mlgif-frame-label">' + (idx + 1) + '. ' + (info.title || 'ID ' + id) + '</span>';
                    list.appendChild(div);
                });
                initDragDrop();
                updateSizeIndicator();
            }

            function loadImageData(ids, cb){
                if(!ids || ids.length === 0){ cb && cb(); return; }
                var toLoad = ids.filter(function(id){ return !imageData[id]; });
                if(toLoad.length === 0){ cb && cb(); return; }
                var fd = new FormData();
                fd.append('action', 'mlgif_get_image_data');
                fd.append('nonce', nonce);
                fd.append('ids', JSON.stringify(toLoad));
                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if(data.success && data.data){
                            Object.keys(data.data).forEach(function(k){ imageData[k] = data.data[k]; });
                        }
                        cb && cb();
                    }).catch(function(){ cb && cb(); });
            }

            loadImageData(imageIds, renderFrames);

            /* ── Media Library ── */
            document.getElementById('mlgif-media-btn').addEventListener('click', function(){
                mediaFrame = wp.media({
                    title:   'Selecionar Imagens para o GIF',
                    multiple: true,
                    library: { type: 'image' }
                });
                mediaFrame.on('select', function(){
                    var selected = mediaFrame.state().get('selection').toJSON();
                    selected.forEach(function(att){
                        if(imageIds.indexOf(att.id) === -1){ imageIds.push(att.id); }
                        imageData[att.id] = {
                            thumb: att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url,
                            title: att.title || att.filename || ('ID ' + att.id)
                        };
                    });
                    renderFrames();
                });
                mediaFrame.open();
            });

            /* ── Remover frame ── */
            document.getElementById('mlgif-frames-list').addEventListener('click', function(e){
                var btn = e.target.closest('.mlgif-frame-remove');
                if(!btn) return;
                var rid = parseInt(btn.dataset.id, 10);
                imageIds = imageIds.filter(function(x){ return x !== rid; });
                renderFrames();
            });

            /* ── Loop mode toggle ── */
            document.getElementById('mlgif-loop-mode').addEventListener('change', function(){
                document.getElementById('mlgif-loop-count-wrap').style.display = this.value === 'fixed' ? '' : 'none';
            });

            /* ── Drag-drop reorder ── */
            document.getElementById('mlgif-frames-list').addEventListener('dragover', function(e){ e.preventDefault(); });

            function initDragDrop(){
                var items   = document.querySelectorAll('.mlgif-frame-item');
                var dragged = null;
                items.forEach(function(item){
                    item.addEventListener('dragstart', function(){ dragged = item; item.classList.add('mlgif-dragging'); });
                    item.addEventListener('dragend',   function(){ item.classList.remove('mlgif-dragging'); dragged = null; });
                    item.addEventListener('dragover',  function(e){ e.preventDefault(); });
                    item.addEventListener('drop', function(e){
                        e.preventDefault();
                        if(!dragged || dragged === item) return;
                        var fromIdx = parseInt(dragged.dataset.idx, 10);
                        var toIdx   = parseInt(item.dataset.idx, 10);
                        var moved   = imageIds.splice(fromIdx, 1)[0];
                        imageIds.splice(toIdx, 0, moved);
                        renderFrames();
                    });
                });
            }

            /* ── Coleta de settings do formulário ── */
            function getSettings(){
                var bgColorEl = document.getElementById('mlgif-bg-color');
                var marginEl  = document.getElementById('mlgif-margin');
                return {
                    width:          Math.min(MAX_W, parseInt(document.getElementById('mlgif-width').value, 10)    || 800),
                    height:         Math.min(MAX_H, parseInt(document.getElementById('mlgif-height').value, 10)   || 400),
                    frame_duration: parseInt(document.getElementById('mlgif-duration').value, 10) || 100,
                    quality:        parseInt(document.getElementById('mlgif-quality').value, 10)  || 85,
                    fit_mode:       document.getElementById('mlgif-fit').value,
                    transition:     document.getElementById('mlgif-transition').value,
                    loop_infinite:  document.getElementById('mlgif-loop-mode').value === 'infinite',
                    loop_count:     parseInt(document.getElementById('mlgif-loop-count').value, 10) || 3,
                    optimize_level: parseInt(document.getElementById('mlgif-optimize-level').value, 10) || 1,
                    bg_color:       bgColorEl ? bgColorEl.value.trim() : '',
                    margin:         marginEl  ? (parseInt(marginEl.value, 10) || 0) : 0
                };
            }

            /* ── Painel de resultado ── */
            function ensureResultPanel(){
                var p = document.getElementById('mlgif-result-panel');
                if(!p){
                    p = document.createElement('div');
                    p.id        = 'mlgif-result-panel';
                    p.className = 'mlgif-result-panel';
                    var anchor  = document.getElementById('mlgif-editor-anchor');
                    if(anchor){ anchor.parentNode.insertBefore(p, anchor); }
                }
                return p;
            }

            function renderGeneratingPanel(pid){
                var p = ensureResultPanel();
                p.innerHTML = '<div class="mlgif-result-preview"><div class="mlgif-spinner"></div></div>'
                    + '<div class="mlgif-result-meta"><h3>Gerando GIF...</h3>'
                    + '<p>Geração em andamento em segundo plano. Aguarde — o resultado aparecerá aqui automaticamente.</p>'
                    + '<div class="mlpb-note">Projeto ID: <code>' + pid + '</code></div></div>';
                p.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function renderGeneratedPanel(pid, gifUrl, size, optLevel, optColors){
                var p        = ensureResultPanel();
                var optLabel = OPT_LABELS[optLevel] || ('Nível ' + optLevel);
                var badge    = size
                    ? '<div class="mlgif-optimize-badge">'
                      + '<span class="mlgif-opt-size">💾 ' + size + '</span>'
                      + '<span class="mlgif-opt-level">🎨 ' + optLabel + '</span>'
                      + (optColors ? '<span class="mlgif-opt-colors">🎭 ' + optColors + ' cores</span>' : '')
                      + '</div>'
                    : '';
                // Força reload do GIF sem cache
                var cacheBuster = gifUrl + '?t=' + Date.now();
                p.innerHTML = '<div class="mlgif-result-preview"><img src="' + cacheBuster + '" alt="GIF Gerado" class="mlgif-preview-img"></div>'
                    + '<div class="mlgif-result-meta">'
                    + '<h3>✅ GIF gerado com sucesso!' + (size ? ' (' + size + ')' : '') + '</h3>'
                    + '<div class="mlpb-field"><label>URL do GIF</label>'
                    + '<div class="mlgif-url-row">'
                    + '<input type="text" value="' + gifUrl + '" readonly class="mlgif-url-input" id="mlgif-output-url-field">'
                    + '<button type="button" class="button button-primary mlpb-action-button" onclick="mlgifCopyUrl(\'' + gifUrl + '\')">Copiar URL</button>'
                    + '<a href="' + gifUrl + '" download class="button mlpb-action-button mlpb-action-button--secondary">Download</a>'
                    + '</div></div>'
                    + badge
                    + '<div class="mlpb-note">Shortcode: <code>[ml_gif_pro id="' + pid + '"]</code></div>'
                    + '</div>';
                var btn = document.getElementById('mlgif-save-generate-btn');
                if(btn){ btn.textContent = '🔄 Regenerar GIF'; btn.disabled = false; }
            }

            /* ── Polling de status ──
               FIX: poll máximo 240 tentativas × 2.5s = 10 min
               FIX: ao detectar 'generated' renderiza imediatamente
               FIX: ao detectar 'error' para o poll e exibe mensagem
               FIX: poll interrompe se o componente não existe mais (navegação)
            ── */
            var _pollTimer = null;
            function pollGenerationStatus(pid, attempts){
                attempts = attempts || 0;
                if(!document.getElementById('mlgif-save-generate-btn')){ return; } // saiu da página

                var fd = new FormData();
                fd.append('action', 'mlgif_project_status');
                fd.append('nonce', nonce);
                fd.append('id', pid);

                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if(!data.success){
                            throw new Error((data.data && data.data.error) || 'Falha ao consultar status.');
                        }
                        var st = (data.data && data.data.status) || '';

                        if(st === 'generated' && data.data.url){
                            renderGeneratedPanel(pid, data.data.url, data.data.size || '', data.data.optimize_level || 1, data.data.optimize_colors || 0);
                            showToast('GIF gerado! URL copiável acima.', 'success');
                            return; // PARA o poll
                        }

                        if(st === 'error'){
                            var errMsg = (data.data && data.data.error) || 'Erro ao gerar GIF.';
                            showToast(errMsg, 'error');
                            var errBox = document.getElementById('mlgif-debug-error');
                            if(!errBox){
                                errBox = document.createElement('div');
                                errBox.id = 'mlgif-debug-error';
                                errBox.className = 'mlgif-alert mlgif-alert-error';
                                errBox.style.marginTop = '14px';
                                var anchor = document.getElementById('mlgif-editor-anchor');
                                if(anchor){ anchor.parentNode.insertBefore(errBox, anchor); }
                            }
                            errBox.style.display = 'block';
                            errBox.textContent = '❌ ' + errMsg;
                            var btn = document.getElementById('mlgif-save-generate-btn');
                            if(btn){ btn.disabled = false; btn.textContent = '⚡ Salvar e Gerar GIF'; }
                            return; // PARA o poll
                        }

                        // ainda gerando
                        if(attempts < 240){
                            _pollTimer = setTimeout(function(){ pollGenerationStatus(pid, attempts + 1); }, 2500);
                        } else {
                            showToast('A geração está demorando mais que o esperado. Verifique a lista de projetos.', 'warning');
                            var btn = document.getElementById('mlgif-save-generate-btn');
                            if(btn){ btn.disabled = false; }
                        }
                    })
                    .catch(function(err){
                        if(attempts < 20){
                            _pollTimer = setTimeout(function(){ pollGenerationStatus(pid, attempts + 1); }, 3000);
                            return;
                        }
                        showToast('Erro ao consultar geração: ' + err.message, 'error');
                        var btn = document.getElementById('mlgif-save-generate-btn');
                        if(btn){ btn.disabled = false; }
                    });
            }

            /* ── AJAX salvar/gerar ── */
            function saveProject(generate, cb){
                var fd = new FormData();
                fd.append('action',   generate ? 'mlgif_generate' : 'mlgif_save_project');
                fd.append('nonce',    nonce);
                fd.append('id',       PROJECT_ID);
                fd.append('title',    document.getElementById('mlgif-title').value);
                fd.append('images',   JSON.stringify(imageIds));
                fd.append('settings', JSON.stringify(getSettings()));

                if(generate){
                    var ov = document.getElementById('mlgif-generating-overlay');
                    if(ov){
                        ov.innerHTML = '<div class="mlgif-overlay-inner"><div class="mlgif-spinner"></div><p>Iniciando geração...</p></div>';
                        ov.style.display = 'flex';
                    }
                    var btn = document.getElementById('mlgif-save-generate-btn');
                    if(btn) btn.disabled = true;
                }

                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.text(); })
                    .then(function(raw){
                        var ov = document.getElementById('mlgif-generating-overlay');
                        if(ov) ov.style.display = 'none';

                        var data;
                        try { data = JSON.parse(raw); } catch(e){
                            var btn2 = document.getElementById('mlgif-save-generate-btn');
                            if(btn2){ btn2.disabled = false; }
                            showToast('Resposta inválida do servidor. Verifique os logs PHP.', 'error');
                            var errBox = document.getElementById('mlgif-debug-error');
                            if(!errBox){
                                errBox = document.createElement('div');
                                errBox.id = 'mlgif-debug-error';
                                errBox.className = 'mlgif-alert mlgif-alert-error';
                                errBox.style.cssText = 'margin-top:14px;word-break:break-all;white-space:pre-wrap;font-size:12px;font-family:monospace;max-height:200px;overflow:auto;';
                                var anchor = document.getElementById('mlgif-editor-anchor');
                                if(anchor){ anchor.parentNode.insertBefore(errBox, anchor); }
                            }
                            errBox.style.display = 'block';
                            errBox.textContent   = 'RESPOSTA RAW:\n' + raw.substring(0, 2000);
                            return;
                        }
                        if(cb) cb(data);
                    })
                    .catch(function(err){
                        var ov = document.getElementById('mlgif-generating-overlay');
                        if(ov) ov.style.display = 'none';
                        var btn3 = document.getElementById('mlgif-save-generate-btn');
                        if(btn3){ btn3.disabled = false; }
                        showToast('Erro de rede: ' + err.message, 'error');
                    });
            }

            /* ── Botão Salvar e Gerar ── */
            document.getElementById('mlgif-save-generate-btn').addEventListener('click', function(){
                if(!document.getElementById('mlgif-title').value.trim()){
                    showToast('Informe o título do projeto.', 'error'); return;
                }
                if(imageIds.length === 0){
                    showToast('Adicione pelo menos uma imagem.', 'error'); return;
                }
                if(_pollTimer){ clearTimeout(_pollTimer); _pollTimer = null; }

                saveProject(true, function(data){
                    if(data.success){
                        var pid = (data.data && data.data.project_id) || PROJECT_ID;
                        if(!PROJECT_ID && pid){ PROJECT_ID = pid; }

                        // Fluxo padrão: geração síncrona — servidor retorna URL diretamente
                        if(data.data && data.data.url){
                            renderGeneratedPanel(pid, data.data.url, data.data.size || '', data.data.optimize_level || 1, data.data.optimize_colors || 0);
                            showToast('GIF gerado com sucesso!', 'success');
                            return;
                        }

                        // Fallback: servidor retornou sem URL (fluxo async legado ou timeout)
                        // Inicia polling para aguardar conclusão
                        renderGeneratingPanel(pid);
                        showToast('Geração em andamento...', 'success');
                        pollGenerationStatus(pid, 0);

                    } else {
                        var errMsg = (data.data && data.data.error) || 'Erro ao gerar GIF.';
                        showToast(errMsg, 'error');
                        var btn4 = document.getElementById('mlgif-save-generate-btn');
                        if(btn4){ btn4.disabled = false; }
                        var errBox2 = document.getElementById('mlgif-debug-error');
                        if(!errBox2){
                            errBox2 = document.createElement('div');
                            errBox2.id = 'mlgif-debug-error';
                            errBox2.className = 'mlgif-alert mlgif-alert-error';
                            errBox2.style.marginTop = '14px';
                            var anchor2 = document.getElementById('mlgif-editor-anchor');
                            if(anchor2){ anchor2.parentNode.insertBefore(errBox2, anchor2); }
                        }
                        errBox2.style.display = 'block';
                        errBox2.textContent   = '❌ ' + errMsg;
                    }
                });
            });

            /* ── Botão Salvar Rascunho ── */
            document.getElementById('mlgif-save-only-btn').addEventListener('click', function(){
                if(!document.getElementById('mlgif-title').value.trim()){
                    showToast('Informe o título do projeto.', 'error'); return;
                }
                saveProject(false, function(data){
                    if(data.success){
                        showToast('Projeto salvo.', 'success');
                        var pid = (data.data && data.data.project_id) || PROJECT_ID;
                        if(!PROJECT_ID && pid){
                            PROJECT_ID = pid;
                            setTimeout(function(){
                                window.location.href = adminBase + '?page=ml-gif-pro&tab=projects&edit=' + pid;
                            }, 600);
                        }
                    } else {
                        showToast((data.data && data.data.error) || 'Erro ao salvar.', 'error');
                    }
                });
            });

        })();
        </script>
        <?php
    }
}
