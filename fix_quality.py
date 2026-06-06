import sys

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'r') as f:
    content = f.read()

# 1. generate_banner_html_from_options
old_global = """        if ($type === 'image') {
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
            }"""

new_global = """        if ($type === 'image') {
            $img_id = isset($options[$prefix]) ? $options[$prefix] : '';
            if (!$img_id) return '';
            
            $desktop_url = wp_get_attachment_url($img_id);
            $alt_text = get_post_meta($img_id, '_wp_attachment_image_alt', true);
            $desktop_img = '<img src="' . esc_url($desktop_url) . '" alt="' . esc_attr($alt_text) . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">';
            
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
            }"""

content = content.replace(old_global, new_global)


# 2. generate_custom_banner_html
old_custom = """    public function generate_custom_banner_html($cb) {
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
            }"""

new_custom = """    public function generate_custom_banner_html($cb) {
        if ($cb['type'] === 'image') {
            if (empty($cb['image'])) return '';
            
            $desktop_url = wp_get_attachment_url($cb['image']);
            $alt_text = get_post_meta($cb['image'], '_wp_attachment_image_alt', true);
            $desktop_img = '<img src="' . esc_url($desktop_url) . '" alt="' . esc_attr($alt_text) . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">';
            
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
            }"""

content = content.replace(old_custom, new_custom)


# 3. render_fcm_banner_shortcode
old_shortcode = """        if ($scb['type'] === 'image') {
            $desktop_img = wp_get_attachment_image($scb['image'], 'full', false, ['style' => 'max-width: 100%; height: auto; display: block; margin: 0 auto;']);
            if (!$desktop_img) return ''; 
            
            $mobile_img_id = isset($scb['image_mobile']) ? $scb['image_mobile'] : '';
            $img_html = '';
            if ($mobile_img_id) {
                $mobile_url = wp_get_attachment_url($mobile_img_id);
                $img_html .= '<picture>';
                $img_html .= '<source media="(max-width: 768px)" srcset="' . esc_url($mobile_url) . '">';
                $img_html .= $desktop_img;
                $img_html .= '</picture>';
            } else {
                $img_html = $desktop_img;
            }"""

new_shortcode = """        if ($scb['type'] === 'image') {
            if (empty($scb['image'])) return '';
            
            $desktop_url = wp_get_attachment_url($scb['image']);
            if (!$desktop_url) return '';
            
            $alt_text = get_post_meta($scb['image'], '_wp_attachment_image_alt', true);
            $desktop_img = '<img src="' . esc_url($desktop_url) . '" alt="' . esc_attr($alt_text) . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">';
            
            $mobile_img_id = isset($scb['image_mobile']) ? $scb['image_mobile'] : '';
            $img_html = '';
            if ($mobile_img_id) {
                $mobile_url = wp_get_attachment_url($mobile_img_id);
                $img_html .= '<picture>';
                $img_html .= '<source media="(max-width: 768px)" srcset="' . esc_url($mobile_url) . '">';
                $img_html .= $desktop_img;
                $img_html .= '</picture>';
            } else {
                $img_html = $desktop_img;
            }"""

content = content.replace(old_shortcode, new_shortcode)

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'w') as f:
    f.write(content)
