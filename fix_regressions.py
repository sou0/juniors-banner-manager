import sys

with open('juniors-banner-manager.php', 'r') as f:
    content = f.read()

# 1. Fix handle_conflict_analysis to handle structured targets (array vs string)
old_conflict = """    public function handle_conflict_analysis() {
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
                $html .= 'Modo simultâneo <strong>ATIVADO</strong>. O banner Global aparecerá junto com banners de Estágios, a não ser que tentem ocupar a mesma posição (\' . esc_html($options['global_position'] ?? \'middle\') . \'). \';
            } else {
                $html .= 'Modo simultâneo <strong>DESATIVADO</strong>. Se o post tiver uma classificação de Funil com imagem, o Banner Global será "engolido" e não aparecerá. \';
            }
            $html .= '<br><a href="#" onclick="jQuery(\\\'.fcm-go-to-tab[data-target=\\\\\\\'#tab-global\\\\\\\']\\\').click(); return false;">Configurar Banner Global</a>';
            $html .= '</li>';
        }

        foreach ($custom_banners as $cb) {
            if ($cb['status'] !== 'active') continue;
            $targets = array_map('trim', explode("\\n", $cb['targets']));
            $targets = array_filter($targets);
            if (empty($targets)) continue;
            $allow_multiple = !empty($cb['allow_multiple']);
            $html .= '<li style="background:#fff; border-left:4px solid '.($allow_multiple ? '#2271b1' : '#d63638').'; padding:15px; margin-bottom:10px; border-radius:4px;">';
            $html .= '<strong>Banner de Override: \' . esc_html($cb[\'name\']) . \'</strong><br>\';
            $html .= \'<a href="#" class="btn-edit-custom-banner" data-banner=\\\'\'.esc_attr(json_encode($cb)).\'\\\'>Configurar este Banner</a></li>\';
        }
        $html .= \'</ul>\';
        echo $html;
        wp_die();
    }"""

new_conflict = """    public function handle_conflict_analysis() {
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
                $html .= 'Modo simultâneo <strong>ATIVADO</strong>. O banner Global aparecerá junto com banners de Estágios, a não ser que tentem ocupar a mesma posição (\' . esc_html($options[\'global_position\'] ?? \'middle\') . \'). \';
            } else {
                $html .= 'Modo simultâneo <strong>DESATIVADO</strong>. Se o post tiver uma classificação de Funil com imagem, o Banner Global será "engolido" e não aparecerá. \';
            }
            $html .= \'<br><a href="#" onclick="jQuery(\\\'.fcm-go-to-tab[data-target=\\\\\\\'#tab-global\\\\\\\']\\\').click(); return false;">Configurar Banner Global</a>\';
            $html .= \'</li>\';
        } else {
            $html .= \'<li style="background:#fff; border-left:4px solid #ccc; padding:15px; margin-bottom:10px; border-radius:4px;">Banner Global inativo.</li>\';
        }

        foreach ($custom_banners as $cb) {
            if ($cb[\'status\'] !== \'active\') continue;
            
            $targets_data = $cb[\'targets\'] ?? [];
            $targets_urls = [];
            if (is_array($targets_data)) {
                foreach($targets_data as $t) if(!empty($t[\'url\'])) $targets_urls[] = $t[\'url\'];
            } else {
                $targets_urls = array_filter(array_map(\'trim\', explode("\\n", (string)$targets_data)));
            }

            if (empty($targets_urls)) continue;

            $allow_multiple = !empty($cb[\'allow_multiple\']);
            
            $html .= \'<li style="background:#fff; border-left:4px solid \'.($allow_multiple ? \'#2271b1\' : \'#d63638\').\'; padding:15px; margin-bottom:10px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">\';
            $html .= \'<strong>Banner de Override: \' . esc_html($cb[\'name\']) . \'</strong><br>\';
            if (!$allow_multiple) {
                $html .= \'⚠️ Este banner está configurado como <strong>EXCLUSIVO</strong>. Ele está <strong>bloqueando / engolindo TODOS os outros banners</strong> (Global e Estágios) em <strong>\' . count($targets_urls) . \' links</strong>. \';
            } else {
                $html .= \'ℹ️ Este banner permite múltiplos simultâneos, porém <strong>irá bloquear e sobrescrever qualquer outro banner que tente usar a posição (\' . esc_html($cb[\'position\'] ?? \'middle\') . \')</strong> nos seus <strong>\' . count($targets_urls) . \' links</strong> afetados. \';
            }
            $html .= \'<br><a href="#" onclick="jQuery(this).next(\\\'div\\\').slideToggle(); return false;">Exibir/Ocultar links afetados</a>\';
            $html .= \' | <a href="#" class="btn-edit-custom-banner" data-banner=\\\'\'.esc_attr(json_encode($cb)).\'\\\'>Configurar este Banner</a>\';
            
            $html .= \'<div style="display:none; margin-top:10px; background:#f9f9f9; padding:10px; border:1px solid #eee;">\';
            foreach ($targets_urls as $t) {
                $html .= \'<code style="display:block; margin-bottom:3px;">\' . esc_html($t) . \'</code>\';
            }
            $html .= \'</div>\';
            $html .= \'</li>\';
        }

        $html .= \'</ul>\';
        echo $html;
        wp_die();
    }"""

# 2. Fix is_banner_active to correctly handle new data structures
old_active = """    private function is_banner_active($options, $stage) {
        if (empty($options[$stage])) return false;
        if (!empty($options[$stage . '_schedule'])) {
            $now = time();
            $start = !empty($options[$stage . '_start']) ? $this->get_utc_timestamp($options[$stage . '_start']) : 0;
            $end = !empty($options[$stage . '_end']) ? $this->get_utc_timestamp($options[$stage . '_end']) : 0;
            if ($start && $now < $start) return false;
            if ($end && $now > $end) return false;
        }
        return true;
    }"""

new_active = """    private function is_banner_active($options, $stage) {
        $has_data = !empty($options[$stage . \'_desktop_data\']) || !empty($options[$stage . \'_mobile_data\']) || !empty($options[$stage]);
        if (!$has_data) return false;
        
        if (!empty($options[$stage . \'_schedule\'])) {
            $now = time();
            $start = !empty($options[$stage . \'_start\']) ? $this->get_utc_timestamp($options[$stage . \'_start\']) : 0;
            $end = !empty($options[$stage . \'_end\']) ? $this->get_utc_timestamp($options[$stage . \'_end\']) : 0;
            if ($start && $now < $start) return false;
            if ($end && $now > $end) return false;
        }
        return true;
    }"""

# Use substring search for safety
if old_conflict in content:
    content = content.replace(old_conflict, new_conflict)
if old_active in content:
    content = content.replace(old_active, new_active)

with open('juniors-banner-manager.php', 'w') as f:
    f.write(content)
