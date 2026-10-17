=== ML Gif Pro ===
Contributors: mlopesdesign
Tags: gif, animated gif, image, media, banner
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gerador profissional de GIF animado físico para WordPress. Salva o arquivo .gif real em /wp-content/uploads/ para uso direto em banners e carrosséis.

== Description ==

ML Gif Pro gera arquivos GIF animados reais a partir de imagens da Media Library do WordPress.

O arquivo .gif é salvo fisicamente em /wp-content/uploads/ e a URL resultante pode ser colada diretamente em qualquer campo que aceite imagem — banners, carrosséis, sliders, etc.

Recursos principais:
* Seleção múltipla de imagens da Media Library
* Reordenação por drag-and-drop
* Configuração de dimensões, duração, transições e modo de ajuste
* Geração via PHP Imagick (motor real, sem fake)
* Otimizador de tamanho integrado (3 níveis: básico, moderado, agressivo)
* Redução de tamanho de 20% a 70% sem perda de qualidade visível
* URL copiável e botão de download
* Regeneração após mudança de configurações
* Gerenciamento de projetos com histórico completo
* Licença pelo site tools.mlopesdesign.com.br: teste grátis de 30 dias, licença anual ou Pacote Agência

== Installation ==

1. Faça upload da pasta ml-gif-pro para /wp-content/plugins/
2. Ative o plugin no painel WordPress
3. Acesse ML Gif Pro no menu lateral

== Requirements ==

* PHP Imagick habilitado no servidor
* PHP 8.1+
* WordPress 6.0+
* /wp-content/uploads/ com permissão de escrita

== Changelog ==

= 1.2.0 =
* Licença pelo site tools.mlopesdesign.com.br (ML License Hub). O endereço antigo de validação (hub.mlopesdesign.com.br) não é mais consultado.
* Aceita a chave do Pacote Agência (todos os plugins ML em vários sites).
* Teste grátis de 30 dias por site. Quem já usava o plugin recebe o teste automaticamente na primeira visita ao painel.
* Sem teste ou licença: o painel fica só para leitura (criar, editar, gerar e apagar projetos e salvar configurações). Os GIFs já publicados continuam funcionando no site e nada é apagado.
* Aba Licença refeita: sem campos de endpoint, plano e status manuais; com teste grátis, ativar, conferir e remover deste site.

= 1.1.4.6 =
* Atualizações automáticas passam a vir do repositório público de releases (ml-gif-pro-releases); o código-fonte passa a ser privado.
* Motor de atualização aceita somente o ZIP da própria versão (sem fallback para "qualquer ZIP"), informa "sem atualização" ao WordPress (no_update) e mostra os ícones do plugin.
* Desinstalar não apaga mais os GIFs gerados (eles podem estar em uso em banners e páginas), nem projetos, configurações e licença; remove só o cache do updater.

= 1.1.4.5 =
* Inclui campos de cor de fundo e margem interna no editor (adicionados em v1.1.4.3 mas não instalados corretamente via update automático).

= 1.1.4.4 =
* Fix: opcache_reset() chamado no after_update e no activation hook — garante que o PHP novo é carregado imediatamente após update via GitHub, sem necessidade de desativar/reativar manualmente.

= 1.1.4.3 =
* Feature: campo de cor de fundo no editor (color picker + input hex + botão limpar). Default transparente.
* Feature: campo de margem interna em pixels. Default 0 (sem margens).
* Engine: bg_color aplicado ao canvas nos modos contain e cover+margem. Transparente = canal alpha ativado.
* Engine: margin aplica espacamento entre a imagem e as bordas do GIF em todos os modos.

= 1.1.4.2 =
* Fix: updater aceita nome do asset com pontos OU underscores na versão (ml-gif-pro-v1.1.4.2.zip ou ml-gif-pro-v1_1_4_2.zip).
* Fix: cache invalidado quando versão em cache = versão instalada, forçando redetecção de nova versão disponível.

= 1.1.4.1 =
* Fix crítico: updater não usa mais zipball_url automático do GitHub como fallback — o zipball tem estrutura mlopesdesign-ml-gif-pro-hash/ que quebra a instalação. Se não houver asset ZIP real no release, o update não é oferecido.
* Fix: get_asset_url tenta 3 níveis de matching (nome canônico → prefixo slug → qualquer .zip) antes de desistir.

= 1.1.4.0 =
* GitHub pipeline: .github/workflows/release.yml — build e publicação automática do ZIP em cada tag v*.
* GitHub pipeline: .github/workflows/wporg-deploy.yml — template desabilitado para futura publicação no WordPress.org.
* Admin: painel de diagnóstico do GitHub Auto-Updater na aba Configurações — mostra repositório, versão instalada, última release, status do cache e token.
* Admin: botão "Limpar cache e verificar agora" força nova consulta à API GitHub.
* Fix: register_output_attachment envolvido em try/catch (Throwable) — wp_generate_attachment_metadata não mata mais o fluxo principal.
* Fix: wp_generate_attachment_metadata protegido com try/catch e memory_limit aumentado.
* Infra: .gitignore e .distignore criados para GitHub e WordPress.org.

= 1.1.3.3 =
* Fix: register_output_attachment envolvido em try/catch — wp_generate_attachment_metadata estourava memória em GIFs grandes e matava o processo PHP silenciosamente antes de retornar o JSON, deixando o projeto preso em 'generating'.
* Fix: wp_generate_attachment_metadata também protegido individualmente com try/catch e memory_limit aumentado.
* Fix: falha no registro da Media Library nunca mais cancela o fluxo principal — GIF gerado e status 'generated' são garantidos independente do que aconteça com o metadata.

= 1.1.3.2 =
* Fix definitivo: removida query que forçava status 'error' por timeout — ela marcava projetos recem-gerados como erro se o usuário abria a lista enquanto o AJAX ainda processava. Projetos 'generating' sem output_url ficam como 'generating' até o usuário regenerar, nunca como 'error' automático.

= 1.1.3.1 =
* Diagnóstico: Dashboard agora mostra versão do Imagick, suporte GIF, permissão de uploads, e lista de projetos com erro e suas mensagens.
* Diagnóstico: mensagem de erro exibida diretamente na listagem de projetos (abaixo do badge Erro) — sem precisar entrar no projeto para ver o que falhou.

= 1.1.3.0 =
* Fix definitivo: quando output_url está preenchida, status e error_message são forçados para 'generated' e '' diretamente no PHP antes de renderizar — sem depender de UPDATE prévio no banco.
* Fix: queries de correção de status removem condição AND output_path != '' — output_url sozinha é suficiente para confirmar que o GIF existe.

= 1.1.2.9 =
* Fix: ajax_save_project preserva o status 'generated' ao salvar — projeto gerado não voltava para 'draft' ao clicar Salvar Rascunho.
* Fix: query de correção de status agora cobre projetos 'draft' com output_url preenchida (além de 'generating' e 'error').
* Fix: get_project() aplica correção de status antes de retornar — tela de edição não mostrava mais o alerta de erro + painel verde simultaneamente.

= 1.1.2.8 =
* Fix: query de correção de status ('generated' quando output_url existe) agora roda ANTES da query de timeout — projetos com GIF gerado não são mais marcados como 'error'.
* Fix: query de timeout só aplica 'error' em projetos SEM output_url (genuinamente presos).
* Fix: na tela de edição, mensagem de erro é suprimida quando output_url está preenchida — evita mostrar alerta e painel de sucesso simultaneamente.
* Fix: limpeza do error_message quando status é corrigido para 'generated'.

= 1.1.2.7 =
* Fix: projetos presos em status 'generating' com output_url já preenchido são automaticamente corrigidos para 'generated' ao carregar a listagem.
* Fix: projetos presos em 'generating' há mais de 10 minutos (timeout/servidor reiniciado) são resetados para 'error' com mensagem explicativa.

= 1.1.2.6 =
* Fix crítico: geração ficava em loop infinito porque send_json_and_continue não libera a conexão em servidores sem fastcgi_finish_request. Reescrito como fluxo síncrono — gera, salva, responde com URL em uma única requisição AJAX. O polling permanece apenas como fallback para projetos antigos presos em 'generating'.
* Fix: JS renderiza o GIF imediatamente ao receber a URL na resposta — sem depender de poll.
* Fix: altura máxima corrigida para 1920px.

= 1.1.2.5 =
* Fix: limite máximo de altura corrigido para 1920px (era 800px definido sem autorização).

= 1.1.2.4 =
* Fix crítico: bloco JavaScript do editor reconstruído — função showToast havia sido removida acidentalmente, quebrando toda a UI de geração.
* Fix crítico: poll de status não encerrava após conclusão — renderGeneratedPanel e reativação do botão agora ocorrem corretamente ao detectar status 'generated' ou 'error'.
* Fix: botão "Salvar e Gerar" é desabilitado durante geração e reabilitado em caso de erro ou timeout — elimina duplos cliques.
* Fix: update_output chamado ANTES de register_output_attachment — poll detecta 'generated' imediatamente sem esperar wp_generate_attachment_metadata.
* Fix: lock de proporção reconstruído com initDimListeners IIFE — listeners de input e change funcionam corretamente.
* Fix: currentRatio calculado via PHP (w/h) e passado diretamente para o JS — elimina race condition do cálculo inline.
* Fix: _pollTimer com clearTimeout ao clicar gerar novamente — sem polls duplicados.
* Fix: getSettings() clampeia width/height ao MAX_W/MAX_H antes de enviar ao servidor.
* Fix: ensureResultPanel usa getElementById('mlgif-editor-anchor') — sem querySelector frágil.
* Melhoria: mensagem "Geração em andamento" mais clara; scrollIntoView para o painel de resultado.

= 1.1.2.3 =
* Feature: trava de proporção (🔒/🔓) no editor — ao alterar largura, altura é recalculada automaticamente.
* Feature: indicador de tamanho estimado em tempo real (dimensões × frames × compressão GIF).
* Feature: limites globais configuráveis max_width (padrão 1200px) e max_height (padrão 800px) nas Configurações.
* Fix: engine clampeia dimensões ao limite global mesmo para projetos salvos com valores antigos.
* Fix: inputs de largura e altura no editor respeitam o máximo configurado como atributo HTML max=.
* Fix: valores de width/height carregados do banco são clampeados aos limites actuais antes de renderizar.

= 1.1.2.2 =
* Fix A: patch_gif_loop — offsets corretos para bytes de loop no bloco NETSCAPE2.0 (pos+13/14 em vez de pos+14/15).
* Fix B: patch_gif_loop — injeção do bloco calcula posição após GCT real em vez de offset fixo (evita corrupção em GIFs com Global Color Table).
* Fix C: clone_frame() via getImageBlob()/readImageBlob() — deep copy real do recurso Imagick em transições fade/slide/zoom.
* Fix D: delays dos frames capturados antes e reaplicados após optimizeImageLayers.
* Fix E: json_decode + wp_unslash em vez de stripslashes nos handlers AJAX.
* Fix F: mediaFrame recriado a cada abertura da Media Library — elimina selection state velho.
* Fix G: insertBefore usa getElementById('mlgif-editor-anchor') em vez de querySelector frágil (3 ocorrências).
* Fix H: dragover listener adicionado ao container mlgif-frames-list para suporte a drop no Firefox.
* Fix I: MLGIF_DB::maybe_create_table() no hook init garante tabela disponível para shortcode no frontend.
* Fix J: queries get_all_projects e count_projects com backticks nas tabelas.
* Fix K: status 'generating' adicionado ao mapa de labels da listagem de projetos.

= 1.1.2.1 =
* Feature: Auto-updater via GitHub Releases (class-mlgif-updater.php).
* Consulta api.github.com a cada 12h e injeta atualização no painel WordPress.
* Download via asset ZIP do release: ml-gif-pro-v1_1_2_1.zip.
* Suporte a repo privado via MLMD_GITHUB_TOKEN_ML_GIF_PRO no wp-config.php.
* Reativa automaticamente após update via activate_plugin().

= 1.1.2 =
* Feature: Otimizador de GIF integrado com 3 níveis de compressão.
* Nível 1 (Básico): 256 cores, deconstructImages + optimizeImageLayers (padrão anterior).
* Nível 2 (Moderado): 128 cores + remapeamento adaptativo + nova passagem de layer diff (-20% a -40%).
* Nível 3 (Agressivo): 64 cores + dithering Floyd-Steinberg + dupla passagem de otimização (-40% a -70%).
* UI: Seletor de nível de otimização na tela de edição de projeto.
* UI: Badge de resultado exibindo tamanho final, nível aplicado e número de cores.
* DB: Novas colunas optimize_level e optimize_colors com upgrade silencioso de tabelas existentes.
* Configurações: Nível de otimização padrão configurável para novos projetos.

= 1.1.1 =
* Versão anterior estável.

= 1.0.5 =
* Configurações agora controlam os padrões iniciais de novos projetos GIF.

= 1.0.4 =
* Fix: patch binário do bloco NETSCAPE2.0 garante loop correto.
* Fix: setImageIterations aplicado antes e depois de deconstructImages/optimizeImageLayers.
* Fix: resposta AJAX raw interceptada.
* Fix: resultado GIF exibido inline sem depender de reload.

= 1.0.2 =
* UI: thumbnails dos frames ampliados.
* Fix: deconstructImages() e optimizeImageLayers() capturam o objeto retornado corretamente.

= 1.0.0 =
* Versão inicial.
