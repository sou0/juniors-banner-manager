import sys

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'r') as f:
    content = f.read()

# 1. Update UI in Global Tab
tab_search = """                        <?php if ($key === 'global'): ?>
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
                        <?php endif; ?>"""

tab_replace = """                        <?php if ($key === 'global'): ?>
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
                                <tr>
                                    <th scope="row"><label><strong>Links Excluídos (Bloqueio)</strong></label></th>
                                    <td>
                                        <textarea name="fcm_settings[global_excluded_targets]" rows="4" class="large-text" placeholder="/url-do-post-1/&#10;/url-do-post-2/"><?php echo esc_textarea(isset($options['global_excluded_targets']) ? $options['global_excluded_targets'] : ''); ?></textarea>
                                        <p class="description">Cole as URLs dos posts onde este banner Global <strong>NÃO</strong> deve aparecer (uma por linha).</p>
                                    </td>
                                </tr>
                            </table>
                            <hr style="margin: 20px 0;">
                        <?php endif; ?>"""

content = content.replace(tab_search, tab_replace)

# 2. Update Injection Logic
inject_search = """        if (!$override_blocks_others) {
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
        }"""

inject_replace = """        if (!$override_blocks_others) {
            $allow_global = !empty($options['global_allow_multiple']);
            if (!$has_stage_banner || $allow_global) {
                if ($this->is_banner_active($options, 'global')) {
                    $global_excluded = false;
                    $global_excluded_targets_str = isset($options['global_excluded_targets']) ? $options['global_excluded_targets'] : '';
                    if (!empty(trim($global_excluded_targets_str))) {
                        $ex_targets = array_filter(array_map('trim', explode("\\n", $global_excluded_targets_str)));
                        foreach ($ex_targets as $et) {
                            if (strpos($current_url, $et) !== false || $et == $post_id) {
                                $global_excluded = true; break;
                            }
                        }
                    }
                    
                    if (!$global_excluded) {
                        $html = $this->generate_banner_html_from_options($options, 'global');
                        if ($html) {
                            $pos = isset($options['global_position']) && $options['global_position'] ? $options['global_position'] : 'middle';
                            $pCount = isset($options['global_paragraph']) ? (int)$options['global_paragraph'] : 3;
                            $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'global'];
                        }
                    }
                }
            }
        }"""

content = content.replace(inject_search, inject_replace)


# 3. Update Conflict Log Logic
conflict_search = """        if ($global_active) {
            $html .= '<li style="background:#fff; border-left:4px solid '.($global_allow_multiple ? '#00a32a' : '#dba617').'; padding:15px; margin-bottom:10px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">';
            $html .= '<strong>Banner Global (Aparece em todos os posts)</strong><br>';
            if ($global_allow_multiple) {
                $html .= 'Modo simultâneo <strong>ATIVADO</strong>. O banner Global aparecerá junto com banners de Estágios, a não ser que tentem ocupar a mesma posição (' . esc_html($options['global_position'] ?? 'middle') . '). ';
            } else {
                $html .= 'Modo simultâneo <strong>DESATIVADO</strong>. Se o post tiver uma classificação de Funil com imagem, o Banner Global será "engolido" e não aparecerá. ';
            }
            $html .= '<br><a href="#" onclick="jQuery(\\\'.fcm-go-to-tab[data-target=\\\\\\\'#tab-global\\\\\\\']\\\').click(); return false;">Configurar Banner Global</a>';
            $html .= '</li>';
        }"""

conflict_replace = """        if ($global_active) {
            $global_ex_str = isset($options['global_excluded_targets']) ? $options['global_excluded_targets'] : '';
            $global_ex_count = count(array_filter(array_map('trim', explode("\\n", $global_ex_str))));

            $html .= '<li style="background:#fff; border-left:4px solid '.($global_allow_multiple ? '#00a32a' : '#dba617').'; padding:15px; margin-bottom:10px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">';
            $html .= '<strong>Banner Global (Aparece em todos os posts)</strong><br>';
            if ($global_allow_multiple) {
                $html .= 'Modo simultâneo <strong>ATIVADO</strong>. O banner Global aparecerá junto com banners de Estágios, a não ser que tentem ocupar a mesma posição (' . esc_html($options['global_position'] ?? 'middle') . '). ';
            } else {
                $html .= 'Modo simultâneo <strong>DESATIVADO</strong>. Se o post tiver uma classificação de Funil com imagem, o Banner Global será "engolido" e não aparecerá. ';
            }
            
            if ($global_ex_count > 0) {
                $html .= '<br><span style="color:#b32d2e;">⚠️ Está configurado explicitamente para ser <strong>bloqueado/ocultado</strong> em ' . $global_ex_count . ' links através da lista de exclusão.</span>';
            }

            $html .= '<br><a href="#" onclick="jQuery(\\\'.fcm-go-to-tab[data-target=\\\\\\\'#tab-global\\\\\\\']\\\').click(); return false;">Configurar Banner Global</a>';
            $html .= '</li>';
        }"""

content = content.replace(conflict_search, conflict_replace)


with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'w') as f:
    f.write(content)
