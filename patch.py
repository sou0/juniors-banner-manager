import sys
import re

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'r') as f:
    content = f.read()

# 1. Add Global to $stages
stages_search = "'topo' => $label_topo,"
stages_replace = "'global' => 'Banner Global (Todos os Posts)',\n            'topo' => $label_topo,"
content = content.replace(stages_search, stages_replace)

# 2. Inject Checkbox to Global tab
tab_marker = """                        <hr style="margin: 20px 0;">
                        
                        <?php if ($key === 'padrao'): ?>"""
new_tab_marker = """                        <hr style="margin: 20px 0;">
                        
                        <?php if ($key === 'global'): ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="fcm_global_allow_multiple"><strong>Exibição Simultânea</strong></label></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fcm_settings[global_allow_multiple]" id="fcm_global_allow_multiple" value="1" <?php checked(!empty($options['global_allow_multiple'])); ?>>
                                            Permitir que este banner apareça ao mesmo tempo que banners de Funil/Padrão/Override (desde que em posições diferentes).
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <hr style="margin: 20px 0;">
                        <?php endif; ?>

                        <?php if ($key === 'padrao'): ?>"""
content = content.replace(tab_marker, new_tab_marker)

# 3. Handle cb_allow_multiple save
cb_save_search = "'position' => isset($_POST['cb_position']) ? sanitize_text_field($_POST['cb_position']) : 'middle',"
cb_save_replace = "'position' => isset($_POST['cb_position']) ? sanitize_text_field($_POST['cb_position']) : 'middle',\n                'allow_multiple' => isset($_POST['cb_allow_multiple']) ? 1 : 0,"
content = content.replace(cb_save_search, cb_save_replace)

# 4. Add Checkbox to Custom Banner UI
cb_pos_search = """                            <tr>
                                <th scope="row"><label><strong>Posição de Injeção no Texto</strong></label></th>"""
cb_pos_replace = """                            <tr>
                                <th scope="row"><label><strong>Exibição Simultânea</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
                                        <input type="checkbox" name="cb_allow_multiple" id="cb_allow_multiple" value="1">
                                        Permitir múltiplos banners nesta página (desde que posições diferentes). Se desmarcado, ele bloqueia todos os outros níveis (Funil, Padrão, Global).
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><strong>Posição de Injeção no Texto</strong></label></th>"""
content = content.replace(cb_pos_search, cb_pos_replace)

# 5. Add UI logic for cb_allow_multiple in JS
content = content.replace(
    "$('#cb_schedule').prop('checked', false).trigger('change');",
    "$('#cb_schedule').prop('checked', false).trigger('change');\n                $('#cb_allow_multiple').prop('checked', false);"
)
content = content.replace(
    "$('#cb_schedule').prop('checked', data.schedule == 1).trigger('change');",
    "$('#cb_schedule').prop('checked', data.schedule == 1).trigger('change');\n                $('#cb_allow_multiple').prop('checked', data.allow_multiple == 1);"
)

# 6. Add Log Tab Nav
nav_search = """<a href="#tab-list" class="nav-tab <?php echo $active_tab === 'list' ? 'nav-tab-active' : ''; ?>">Posts Classificados</a>"""
nav_replace = """<a href="#tab-list" class="nav-tab <?php echo $active_tab === 'list' ? 'nav-tab-active' : ''; ?>">Posts Classificados</a>\n                <a href="#tab-logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>" style="color: #d63638;"><span class="dashicons dashicons-warning" style="margin-top:4px;"></span> Logs de Conflito</a>"""
content = content.replace(nav_search, nav_replace)

# 7. Add Log Tab Content
tab_list_end_search = """                        </tbody>
                    </table>
                </div>"""
tab_list_end_replace = """                        </tbody>
                    </table>
                </div>

                <!-- TAB: Monitor de Conflitos -->
                <div id="tab-logs" class="tab-content" style="display: <?php echo $active_tab === 'logs' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Monitor de Conflitos e Substituições</h2>
                    <p class="description">Esta ferramenta analisa as configurações ativas e identifica como os banners estão agindo (bloqueando ou sobrescrevendo outros).</p>
                    <hr style="margin: 20px 0;">
                    <button type="button" id="btn-run-conflict-analysis" class="button button-primary"><span class="dashicons dashicons-chart-pie" style="margin-top:4px;"></span> Analisar Cenário Atual</button>
                    <span class="spinner" id="fcm-analysis-spinner" style="float:none; margin-top:3px;"></span>
                    <div id="fcm-conflict-results" style="margin-top: 20px;"></div>
                </div>"""
content = content.replace(tab_list_end_search, tab_list_end_replace)

# 8. JS for Log Analysis
js_log_search = """$('#fcm-run-import').click(function(){"""
js_log_replace = """$('#btn-run-conflict-analysis').click(function(){
                $('#fcm-analysis-spinner').addClass('is-active');
                $.post(ajaxurl, {action: 'fcm_analyze_conflicts'}, function(res){
                    $('#fcm-analysis-spinner').removeClass('is-active');
                    $('#fcm-conflict-results').html(res);
                });
            });

            $('#fcm-run-import').click(function(){"""
content = content.replace(js_log_search, js_log_replace)

# 9. Inject the Backend Log Ajax function and action
ajax_hook_search = "add_action('wp_ajax_fcm_search_posts', [$this, 'handle_post_search']);"
ajax_hook_replace = "add_action('wp_ajax_fcm_search_posts', [$this, 'handle_post_search']);\n        add_action('wp_ajax_fcm_analyze_conflicts', [$this, 'handle_conflict_analysis']);"
content = content.replace(ajax_hook_search, ajax_hook_replace)

ajax_method = """
    public function handle_conflict_analysis() {
        if (!current_user_can('manage_options')) wp_die();
        $options = get_option($this->option_name);
        $custom_banners = get_option($this->custom_banners_option, []);
        $html = '';

        $global_allow_multiple = !empty($options['global_allow_multiple']);
        $global_active = $this->is_banner_active($options, 'global');

        $html .= '<ul style="margin: 0; padding: 0; list-style: none;">';

        if ($global_active) {
            $html .= '<li style="background:#fff; border-left:4px solid '.($global_allow_multiple ? '#00a32a' : '#dba617').'; padding:15px; margin-bottom:10px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">';
            $html .= '<strong>Banner Global (Aparece em todos os posts)</strong><br>';
            if ($global_allow_multiple) {
                $html .= 'Modo simultâneo <strong>ATIVADO</strong>. O banner Global aparecerá junto com banners de Estágios, a não ser que tentem ocupar a mesma posição (' . esc_html($options['global_position'] ?? 'middle') . '). ';
            } else {
                $html .= 'Modo simultâneo <strong>DESATIVADO</strong>. Se o post tiver uma classificação de Funil com imagem, o Banner Global será "engolido" e não aparecerá. ';
            }
            $html .= '<br><a href="#" onclick="jQuery(\'.fcm-go-to-tab[data-target=\\\'#tab-global\\\']\').click(); return false;">Configurar Banner Global</a>';
            $html .= '</li>';
        } else {
            $html .= '<li style="background:#fff; border-left:4px solid #ccc; padding:15px; margin-bottom:10px; border-radius:4px;">Banner Global inativo.</li>';
        }

        foreach ($custom_banners as $cb) {
            if ($cb['status'] !== 'active') continue;
            
            $targets = array_map('trim', explode("\\n", $cb['targets']));
            $targets = array_filter($targets);
            if (empty($targets)) continue;

            $allow_multiple = !empty($cb['allow_multiple']);
            
            $html .= '<li style="background:#fff; border-left:4px solid '.($allow_multiple ? '#2271b1' : '#d63638').'; padding:15px; margin-bottom:10px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">';
            $html .= '<strong>Banner de Override: ' . esc_html($cb['name']) . '</strong><br>';
            if (!$allow_multiple) {
                $html .= '⚠️ Este banner está configurado como <strong>EXCLUSIVO</strong>. Ele está <strong>bloqueando / engolindo TODOS os outros banners</strong> (Global e Estágios) em <strong>' . count($targets) . ' links</strong>. ';
            } else {
                $html .= 'ℹ️ Este banner permite múltiplos simultâneos, porém <strong>irá bloquear e sobrescrever qualquer outro banner que tente usar a posição (' . esc_html($cb['position'] ?? 'middle') . ')</strong> nos seus <strong>' . count($targets) . ' links</strong> afetados. ';
            }
            $html .= '<br><a href="#" onclick="jQuery(this).next(\\'div\\').slideToggle(); return false;">Exibir/Ocultar links afetados</a>';
            $html .= ' | <a href="#" class="btn-edit-custom-banner" data-banner=\\''.esc_attr(json_encode($cb)).'\\'>Configurar este Banner</a>';
            
            $html .= '<div style="display:none; margin-top:10px; background:#f9f9f9; padding:10px; border:1px solid #eee;">';
            foreach ($targets as $t) {
                $html .= '<code style="display:block; margin-bottom:3px;">' . esc_html($t) . '</code>';
            }
            $html .= '</div>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        echo $html;
        wp_die();
    }
"""

content = content.replace("    /* -------------------------------------------------------------------------", ajax_method + "\n    /* -------------------------------------------------------------------------", 1)


# 10. Completely replace inject_cta_via_js logic
inject_start = "    public function inject_cta_via_js() {"
inject_end = "    public function render_fcm_banner_shortcode($atts) {"

start_idx = content.find(inject_start)
end_idx = content.find(inject_end)

new_inject_logic = """    public function generate_banner_html_from_options($options, $prefix) {
        $type = isset($options[$prefix . '_type']) ? $options[$prefix . '_type'] : 'image';
        if ($type === 'image') {
            $img_id = isset($options[$prefix]) ? $options[$prefix] : '';
            if (!$img_id) return '';
            $desktop_img = wp_get_attachment_image($img_id, 'full', false, ['style' => 'max-width: 100%; height: auto; display: block; margin: 0 auto;']);
            $mobile_img_id = isset($options[$prefix . '_mobile']) ? $options[$prefix . '_mobile'] : '';
            $url = isset($options[$prefix . '_url']) ? $options[$prefix . '_url'] : '#';
            
            $img_html = '';
            if ($mobile_img_id) {
                $mobile_url = wp_get_attachment_url($mobile_img_id);
                $img_html .= '<picture>';
                $img_html .= '<source media="(max-width: 768px)" srcset="' . esc_url($mobile_url) . '">';
                $img_html .= $desktop_img;
                $img_html .= '</picture>';
            } else {
                $img_html = $desktop_img;
            }

            return sprintf(
                '<div class="fcm-cta-container" style="margin: 40px 0; text-align: center;">
                    <a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block;">%s</a>
                </div>',
                esc_url($url),
                $img_html
            );
        } else {
            $html = isset($options[$prefix . '_html']) ? $options[$prefix . '_html'] : '';
            if (empty(trim($html))) return '';
            return '<div class="fcm-cta-container" style="margin: 40px 0;">' . do_shortcode($html) . '</div>';
        }
    }

    public function generate_custom_banner_html($cb) {
        if ($cb['type'] === 'image') {
            if (empty($cb['image'])) return '';
            $desktop_img = wp_get_attachment_image($cb['image'], 'full', false, ['style' => 'max-width: 100%; height: auto; display: block; margin: 0 auto;']);
            $mobile_img_id = isset($cb['image_mobile']) ? $cb['image_mobile'] : '';
            
            $img_html = '';
            if ($mobile_img_id) {
                $mobile_url = wp_get_attachment_url($mobile_img_id);
                $img_html .= '<picture>';
                $img_html .= '<source media="(max-width: 768px)" srcset="' . esc_url($mobile_url) . '">';
                $img_html .= $desktop_img;
                $img_html .= '</picture>';
            } else {
                $img_html = $desktop_img;
            }

            return sprintf(
                '<div class="fcm-cta-container fcm-custom-cta" style="margin: 40px 0; text-align: center;">
                    <a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block;">%s</a>
                </div>',
                esc_url($cb['url']),
                $img_html
            );
        } else {
            if (empty(trim($cb['html']))) return '';
            return '<div class="fcm-cta-container fcm-custom-cta" style="margin: 40px 0;">' . do_shortcode($cb['html']) . '</div>';
        }
    }

    public function inject_cta_via_js() {
        if (!is_single()) return;

        $post_id = get_the_ID();
        $current_url = get_permalink($post_id);
        $options = get_option($this->option_name);
        
        $final_banners = [];
        $override_blocks_others = false;

        $custom_banners = get_option($this->custom_banners_option, []);
        foreach ($custom_banners as $cb) {
            if ($cb['status'] !== 'active') continue;
            
            if (!empty($cb['schedule'])) {
                $now = time();
                $start = !empty($cb['start']) ? $this->get_utc_timestamp($cb['start']) : 0;
                $end = !empty($cb['end']) ? $this->get_utc_timestamp($cb['end']) : 0;
                if ($start && $now < $start) continue;
                if ($end && $now > $end) continue;
            }

            $targets = array_map('trim', explode("\\n", $cb['targets']));
            $matched = false;
            foreach ($targets as $t) {
                if (empty($t)) continue;
                if (strpos($current_url, $t) !== false || $t == $post_id) {
                    $matched = true; break;
                }
            }
            
            if ($matched) {
                $html = $this->generate_custom_banner_html($cb);
                if ($html) {
                    $pos = isset($cb['position']) && $cb['position'] ? $cb['position'] : 'middle';
                    $pCount = isset($cb['paragraph']) ? (int)$cb['paragraph'] : 3;
                    $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'override'];
                    
                    if (empty($cb['allow_multiple'])) {
                        $override_blocks_others = true;
                    }
                }
                break;
            }
        }

        $has_stage_banner = false;

        if (!$override_blocks_others) {
            $stage = get_post_meta($post_id, '_fcm_stage', true);
            $stage_to_use = null;

            if ($stage && in_array($stage, ['topo', 'meio', 'fundo'])) {
                if ($this->is_banner_active($options, $stage)) {
                    $stage_to_use = $stage;
                } else {
                    $default_status = isset($options['default_status']) ? $options['default_status'] : 'active';
                    $excluded_posts = isset($options['excluded_posts']) ? (array)$options['excluded_posts'] : [];
                    
                    if ($default_status === 'active' && !in_array($post_id, $excluded_posts) && $this->is_banner_active($options, 'padrao')) {
                        $stage_to_use = 'padrao';
                    }
                }
            }

            if ($stage_to_use) {
                $html = $this->generate_banner_html_from_options($options, $stage_to_use);
                if ($html) {
                    $pos = isset($options[$stage_to_use . '_position']) && $options[$stage_to_use . '_position'] ? $options[$stage_to_use . '_position'] : 'middle';
                    $pCount = isset($options[$stage_to_use . '_paragraph']) ? (int)$options[$stage_to_use . '_paragraph'] : 3;
                    $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'stage'];
                    $has_stage_banner = true;
                }
            }
        }

        if (!$override_blocks_others) {
            $allow_global = !empty($options['global_allow_multiple']);
            if (!$has_stage_banner || $allow_global) {
                if ($this->is_banner_active($options, 'global')) {
                    $html = $this->generate_banner_html_from_options($options, 'global');
                    if ($html) {
                        $pos = isset($options['global_position']) && $options['global_position'] ? $options['global_position'] : 'middle';
                        $pCount = isset($options['global_paragraph']) ? (int)$options['global_paragraph'] : 3;
                        $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'global'];
                    }
                }
            }
        }

        if (empty($final_banners)) return;

        $custom_selectors_str = isset($options['custom_selectors']) ? $options['custom_selectors'] : '';
        $custom_selectors = [];
        if (!empty(trim($custom_selectors_str))) {
            $lines = explode("\\n", $custom_selectors_str);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) $custom_selectors[] = $line;
            }
        }

        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var banners = <?php echo wp_json_encode($final_banners); ?>;
            var customSelectors = <?php echo wp_json_encode($custom_selectors); ?>;
            var selectors = [
                '#conteudo-postagem',
                '.elementor-widget-theme-post-content',
                '.elementor-widget-text-editor',
                '.blog-post',
                '.post',
                '.entry-content',
                '.post-content',
                'article'
            ].concat(customSelectors);

            var mainContainer = null;
            var maxP = 0;

            for (var i = 0; i < selectors.length; i++) {
                var els = document.querySelectorAll(selectors[i]);
                els.forEach(function(el) {
                    var pList = el.querySelectorAll('p');
                    if (pList.length > maxP) {
                        maxP = pList.length;
                        mainContainer = el;
                    }
                });
            }

            if (!mainContainer) return;

            var usedPositions = {};

            banners.forEach(function(b) {
                var posKey = b.pos === 'after_p' ? 'after_p_' + b.pCount : b.pos;
                if (usedPositions[posKey]) {
                    console.log('FCM: Banner (' + b.source + ') ocultado por conflito de posição: ' + posKey);
                    return;
                }
                usedPositions[posKey] = true;

                var div = document.createElement('div');
                div.innerHTML = b.html;
                var bannerEl = div.firstElementChild || div;

                if (b.pos === 'top') {
                    mainContainer.insertBefore(bannerEl, mainContainer.firstChild);
                    return;
                }
                if (b.pos === 'bottom') {
                    mainContainer.appendChild(bannerEl);
                    return;
                }

                var paragraphs = Array.from(mainContainer.querySelectorAll('p'));
                var total = paragraphs.length;
                
                if (total === 0) {
                    mainContainer.appendChild(bannerEl);
                    return;
                }

                var insertIndex = -1;

                if (b.pos === 'after_p') {
                    insertIndex = b.pCount - 1;
                    if (insertIndex < 0) insertIndex = 0;
                    if (insertIndex >= total) insertIndex = total - 1;
                } else { 
                    var middle = Math.floor(total / 2);
                    
                    for (var i = middle; i < total - 1; i++) {
                        var nextEl = paragraphs[i].nextElementSibling;
                        var isBadNext = nextEl && nextEl.matches && nextEl.matches('h1, h2, h3, h4, h5, h6, ul, ol, img, figure, iframe, video, audio, table, blockquote');
                        var isBadCurr = paragraphs[i].matches('h1, h2, h3, h4, h5, h6');
                        
                        if (!isBadNext && !isBadCurr) {
                            insertIndex = i;
                            break;
                        }
                    }
                    
                    if (insertIndex === -1) {
                        for (var i = middle - 1; i >= 0; i--) {
                            var nextEl = paragraphs[i].nextElementSibling;
                            var isBadNext = nextEl && nextEl.matches && nextEl.matches('h1, h2, h3, h4, h5, h6, ul, ol, img, figure, iframe, video, audio, table, blockquote');
                            var isBadCurr = paragraphs[i].matches('h1, h2, h3, h4, h5, h6');
                            
                            if (!isBadNext && !isBadCurr) {
                                insertIndex = i;
                                break;
                            }
                        }
                    }
                    if (insertIndex === -1) insertIndex = 0;
                }

                if (paragraphs[insertIndex]) {
                    paragraphs[insertIndex].parentNode.insertBefore(bannerEl, paragraphs[insertIndex].nextSibling);
                }
            });
        });
        </script>
        <?php
    }

"""

content = content[:start_idx] + new_inject_logic + content[end_idx:]

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'w') as f:
    f.write(content)
