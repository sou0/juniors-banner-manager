import re

with open('plugin classificação.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace Emojis
content = content.replace('🖥️ Desktop', '<span class="dashicons dashicons-desktop"></span> Desktop')
content = content.replace('📱 Mobile', '<span class="dashicons dashicons-smartphone"></span> Mobile')

# 1. Update Rendering Logic (General Settings)
content = content.replace(
    """                $url = isset($options[$prefix . '_url']) ? $options[$prefix . '_url'] : '#';
                $mobile_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($mobile_url), esc_attr($alt_text));""",
    """                $url_desktop = isset($options[$prefix . '_url']) ? $options[$prefix . '_url'] : '#';
                $url = !empty($options[$prefix . '_url_mobile']) ? $options[$prefix . '_url_mobile'] : $url_desktop;
                $mobile_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($mobile_url), esc_attr($alt_text));"""
)

# 2. Update Rendering Logic (Custom Banners)
content = content.replace(
    """                $url = isset($cb['url']) ? $cb['url'] : '#';
                $mobile_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($mobile_url), esc_attr($alt_text));""",
    """                $url_desktop = isset($cb['url']) ? $cb['url'] : '#';
                $url = !empty($cb['url_mobile']) ? $cb['url_mobile'] : $url_desktop;
                $mobile_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($mobile_url), esc_attr($alt_text));"""
)

# 3. Update Save Handlers for Custom Banners
content = content.replace(
    "'url' => sanitize_text_field($_POST['cb_url']),",
    "'url' => sanitize_text_field($_POST['cb_url']),\n                'url_mobile' => isset($_POST['cb_url_mobile']) ? sanitize_text_field($_POST['cb_url_mobile']) : '',"
)

content = content.replace(
    "'url' => sanitize_text_field($_POST['scb_url']),",
    "'url' => sanitize_text_field($_POST['scb_url']),\n                'url_mobile' => isset($_POST['scb_url_mobile']) ? sanitize_text_field($_POST['scb_url_mobile']) : '',"
)

# 4. JS Reset and Populate Logic
content = content.replace(
    "$('#cb_url').val('');",
    "$('#cb_url').val('');\n                $('#cb_url_mobile').val('');"
)
content = content.replace(
    "$('#cb_url').val(data.url);",
    "$('#cb_url').val(data.url);\n                $('#cb_url_mobile').val(data.url_mobile || '');"
)

content = content.replace(
    "$('#scb_url').val('');",
    "$('#scb_url').val('');\n                $('#scb_url_mobile').val('');"
)
content = content.replace(
    "$('#scb_url').val(data.url);",
    "$('#scb_url').val(data.url);\n                $('#scb_url_mobile').val(data.url_mobile || '');"
)

# 5. UI Layout - General Settings
content = re.sub(
    r'<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>\s*</div>\s*<div class="fcm-html-wrapper-desktop-<\?php echo esc_attr\(\$key\); \?>"',
    r"""<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                                    <input type="url" name="fcm_settings[<?php echo esc_attr($key . '_url'); ?>]" value="<?php echo esc_url($link_url); ?>" class="regular-text" style="width:100%;" placeholder="https://exemplo.com/pagina">
                                                </div>
                                            </div>

                                            <div class="fcm-html-wrapper-desktop-<?php echo esc_attr($key); ?>\"""",
    content
)

content = re.sub(
    r'<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>\s*</div>\s*<div class="fcm-html-wrapper-mobile-<\?php echo esc_attr\(\$key\); \?>"',
    r"""<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link Mobile (Deixe vazio para usar o do Desktop):</label>
                                                    <?php $link_url_mobile = isset($options[$key . '_url_mobile']) ? $options[$key . '_url_mobile'] : ''; ?>
                                                    <input type="url" name="fcm_settings[<?php echo esc_attr($key . '_url_mobile'); ?>]" value="<?php echo esc_url($link_url_mobile); ?>" class="regular-text" style="width:100%;" placeholder="Ex: https://exemplo.com/mobile">
                                                </div>
                                            </div>

                                            <div class="fcm-html-wrapper-mobile-<?php echo esc_attr($key); ?>\"""",
    content
)

content = re.sub(
    r'<div style="margin-top: 15px; background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">\s*<label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino \(URL\) - Aplicado para banners do tipo Imagem:</label>\s*<input type="url" name="fcm_settings\[<\?php echo esc_attr\(\$key \. \'_url\'\); \?>\]" value="<\?php echo esc_url\(\$link_url\); \?>" class="regular-text" style="width:100%; max-width:600px;" placeholder="https://exemplo\.com/pagina">\s*</div>',
    '',
    content
)

# 6. UI Layout - Custom Banners
content = re.sub(
    r'<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>\s*</div>\s*<div id="cb_html_desktop_wrapper"',
    r"""<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                                    <input type="url" name="cb_url" id="cb_url" class="regular-text" style="width:100%;" placeholder="https://exemplo.com/pagina">
                                                </div>
                                            </div>

                                            <div id="cb_html_desktop_wrapper\"""",
    content
)

content = re.sub(
    r'<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>\s*</div>\s*<div id="cb_html_mobile_wrapper"',
    r"""<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link Mobile (Deixe vazio para usar o do Desktop):</label>
                                                    <input type="url" name="cb_url_mobile" id="cb_url_mobile" class="regular-text" style="width:100%;" placeholder="Ex: https://exemplo.com/mobile">
                                                </div>
                                            </div>

                                            <div id="cb_html_mobile_wrapper\"""",
    content
)

content = re.sub(
    r'<div style="margin-top: 15px; background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">\s*<label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino \(URL\) - Aplicado para banners do tipo Imagem:</label>\s*<input type="url" name="cb_url" id="cb_url" class="regular-text" style="width:100%; max-width:600px;" placeholder="https://exemplo\.com/pagina">\s*</div>',
    '',
    content
)

# 7. UI Layout - Shortcode Banners
content = re.sub(
    r'<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>\s*</div>\s*<div id="scb_html_desktop_wrapper"',
    r"""<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                                    <input type="url" name="scb_url" id="scb_url" class="regular-text" style="width:100%;" placeholder="https://exemplo.com/pagina">
                                                </div>
                                            </div>

                                            <div id="scb_html_desktop_wrapper\"""",
    content
)

content = re.sub(
    r'<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>\s*</div>\s*<div id="scb_html_mobile_wrapper"',
    r"""<button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link Mobile (Deixe vazio para usar o do Desktop):</label>
                                                    <input type="url" name="scb_url_mobile" id="scb_url_mobile" class="regular-text" style="width:100%;" placeholder="Ex: https://exemplo.com/mobile">
                                                </div>
                                            </div>

                                            <div id="scb_html_mobile_wrapper\"""",
    content
)

content = re.sub(
    r'<div style="margin-top: 15px; background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">\s*<label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino \(URL\) - Aplicado para banners do tipo Imagem:</label>\s*<input type="url" name="scb_url" id="scb_url" class="regular-text" style="width:100%; max-width:600px;" placeholder="https://exemplo\.com/pagina">\s*</div>',
    '',
    content
)

# Apply nice header styles
content = content.replace(
    '<h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;"><span class="dashicons dashicons-desktop"></span> Desktop</h4>',
    '<h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-desktop"></span> Desktop</h4>'
)
content = content.replace(
    '<h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;"><span class="dashicons dashicons-smartphone"></span> Mobile</h4>',
    '<h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-smartphone"></span> Mobile</h4>'
)

with open('plugin classificação.php', 'w', encoding='utf-8') as f:
    f.write(content)
