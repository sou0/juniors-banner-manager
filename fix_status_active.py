import sys

with open('juniors-banner-manager.php', 'r') as f:
    content = f.read()

# 1. Update get_banner_status_html to handle new data structures
old_status_html = """    private function get_banner_status_html($options, $key, $is_custom = false) {
        if ($is_custom) {
            $cb = $options;
            if ($cb['status'] === 'inactive') return '<span style="color:#b32d2e; font-weight:bold;">Inativo</span>';
            $scheduled = !empty($cb['schedule']);
            $start = !empty($cb['start']) ? $this->get_utc_timestamp($cb['start']) : 0;
            $end = !empty($cb['end']) ? $this->get_utc_timestamp($cb['end']) : 0;
        } else {
            $img_id = isset($options[$key]) ? $options[$key] : '';
            if (!$img_id) return '<span style="color:#b32d2e; font-weight:bold;"><span class="dashicons dashicons-no-alt"></span> Não configurado</span>';
            $scheduled = !empty($options[$key . '_schedule']);
            $start = !empty($options[$key . '_start']) ? $this->get_utc_timestamp($options[$key . '_start']) : 0;
            $end = !empty($options[$key . '_end']) ? $this->get_utc_timestamp($options[$key . '_end']) : 0;
        }

        if ($scheduled) {
            $now = time();
            if ($start && $now < $start) {
                return '<span style="color:#dba617; font-weight:bold;"><span class="dashicons dashicons-clock"></span> Agendado (Aguardando)</span>';
            } elseif ($end && $now > $end) {
                return '<span style="color:#b32d2e; font-weight:bold;"><span class="dashicons dashicons-clock"></span> Expirado</span>';
            }
            return '<span style="color:#00a32a; font-weight:bold;"><span class="dashicons dashicons-clock"></span> Ativo (Temporizado)</span>';
        }

        return '<span style="color:#00a32a; font-weight:bold;"><span class="dashicons dashicons-yes"></span> Ativo</span>';
    }"""

new_status_html = """    private function get_banner_status_html($options, $key, $is_custom = false) {
        if ($is_custom) {
            $cb = $options;
            if (($cb['status'] ?? 'active') === 'inactive') return '<span style="color:#b32d2e; font-weight:bold;">Inativo</span>';
            $scheduled = !empty($cb['schedule']);
            $start = !empty($cb['start']) ? $this->get_utc_timestamp($cb['start']) : 0;
            $end = !empty($cb['end']) ? $this->get_utc_timestamp($cb['end']) : 0;
        } else {
            $has_data = !empty($options[$key . '_desktop_data']) || !empty($options[$key . '_mobile_data']) || !empty($options[$key]);
            if (!$has_data) return '<span style="color:#b32d2e; font-weight:bold;"><span class="dashicons dashicons-no-alt"></span> Não configurado</span>';
            
            $scheduled = !empty($options[$key . '_schedule']);
            $start = !empty($options[$key . '_start']) ? $this->get_utc_timestamp($options[$key . '_start']) : 0;
            $end = !empty($options[$key . '_end']) ? $this->get_utc_timestamp($options[$key . '_end']) : 0;
        }

        if ($scheduled) {
            $now = time();
            if ($start && $now < $start) {
                return '<span style="color:#dba617; font-weight:bold;"><span class="dashicons dashicons-clock"></span> Agendado (Aguardando)</span>';
            } elseif ($end && $now > $end) {
                return '<span style="color:#b32d2e; font-weight:bold;"><span class="dashicons dashicons-clock"></span> Expirado</span>';
            }
            return '<span style="color:#00a32a; font-weight:bold;"><span class="dashicons dashicons-clock"></span> Ativo (Temporizado)</span>';
        }

        return '<span style="color:#00a32a; font-weight:bold;"><span class="dashicons dashicons-yes"></span> Ativo</span>';
    }"""

if old_status_html in content:
    content = content.replace(old_status_html, new_status_html)

# Increment version to 3.3
content = content.replace("* Version: 3.2", "* Version: 3.3")
content = content.replace("v3.2", "v3.3")

with open('juniors-banner-manager.php', 'w') as f:
    f.write(content)
