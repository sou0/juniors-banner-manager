import re

with open('plugin classificação.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Save handlers
content = content.replace(
    "'type' => sanitize_text_field($_POST['cb_type']),",
    "'type' => sanitize_text_field($_POST['cb_type']),\n                'type_mobile' => isset($_POST['cb_type_mobile']) ? sanitize_text_field($_POST['cb_type_mobile']) : '',"
)
content = content.replace(
    "'html' => wp_unslash($_POST['cb_html']),",
    "'html' => wp_unslash($_POST['cb_html']),\n                'html_mobile' => isset($_POST['cb_html_mobile']) ? wp_unslash($_POST['cb_html_mobile']) : '',"
)

content = content.replace(
    "'type' => sanitize_text_field($_POST['scb_type']),",
    "'type' => sanitize_text_field($_POST['scb_type']),\n                'type_mobile' => isset($_POST['scb_type_mobile']) ? sanitize_text_field($_POST['scb_type_mobile']) : '',"
)
content = content.replace(
    "'html' => wp_unslash($_POST['scb_html']),",
    "'html' => wp_unslash($_POST['scb_html']),\n                'html_mobile' => isset($_POST['scb_html_mobile']) ? wp_unslash($_POST['scb_html_mobile']) : '',"
)

# 2. General Settings UI
replacement = """                            <tr>
                                <th scope="row"><label><strong>Tipo de Banner (Desktop)</strong></label></th>
                                <td>
                                    <select name="fcm_settings[<?php echo esc_attr($key . '_type'); ?>]" class="fcm-main-type-select-desktop" data-key="<?php echo esc_attr($key); ?>" style="min-width: 250px;">
                                        <option value="image" <?php selected($type, 'image'); ?>>Apenas Imagem (Padrão)</option>
                                        <option value="html" <?php selected($type, 'html'); ?>>Shortcode Elementor / HTML Personalizado</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><strong>Tipo de Banner (Mobile)</strong></label></th>
                                <td>
                                    <?php $type_mobile = isset($options[$key . '_type_mobile']) ? $options[$key . '_type_mobile'] : $type; ?>
                                    <select name="fcm_settings[<?php echo esc_attr($key . '_type_mobile'); ?>]" class="fcm-main-type-select-mobile" data-key="<?php echo esc_attr($key); ?>" style="min-width: 250px;">
                                        <option value="image" <?php selected($type_mobile, 'image'); ?>>Apenas Imagem (Padrão)</option>
                                        <option value="html" <?php selected($type_mobile, 'html'); ?>>Shortcode Elementor / HTML Personalizado</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="fcm-main-type-area fcm-main-type-image-<?php echo esc_attr($key); ?>" style="display: table-row;">
                                <th scope="row" style="width: 250px;"><label><strong>Imagens do Banner</strong></label></th>
                                <td>
                                    <div style="display:flex; gap: 20px;">
                                        <div class="fcm-upload-wrapper fcm-upload-wrapper-desktop-<?php echo esc_attr($key); ?>" style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1; display: <?php echo $type === 'image' ? 'block' : 'none'; ?>;">
                                            <h4 style="margin-top:0; margin-bottom:10px;">Desktop</h4>
                                            <img src="<?php echo esc_url($img_url); ?>" style="max-width:100%; display:<?php echo $img_url ? 'block' : 'none'; ?>; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                                            <input type="hidden" name="fcm_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($img_id); ?>" class="fcm-img-id">
                                            <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                            <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                        </div>
                                        <div class="fcm-upload-wrapper fcm-upload-wrapper-mobile-<?php echo esc_attr($key); ?>" style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1; display: <?php echo $type_mobile === 'image' ? 'block' : 'none'; ?>;">
                                            <h4 style="margin-top:0; margin-bottom:10px;">Mobile (Opcional)</h4>
                                            <img src="<?php echo esc_url($img_mobile_url); ?>" style="max-width:100%; display:<?php echo $img_mobile_url ? 'block' : 'none'; ?>; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                                            <input type="hidden" name="fcm_settings[<?php echo esc_attr($key . '_mobile'); ?>]" value="<?php echo esc_attr($img_mobile_id); ?>" class="fcm-img-id">
                                            <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                            <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                        </div>
                                    </div>
                                    <div style="margin-top: 15px;">
                                        <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                        <input type="url" name="fcm_settings[<?php echo esc_attr($key . '_url'); ?>]" value="<?php echo esc_url($link_url); ?>" class="regular-text" placeholder="https://exemplo.com/pagina">
                                    </div>
                                </td>
                            </tr>
                            <tr class="fcm-main-type-area fcm-main-type-html-<?php echo esc_attr($key); ?>" style="display: <?php echo ($type === 'html' || $type_mobile === 'html') ? 'table-row' : 'none'; ?>;">
                                <th scope="row"><label><strong>Conteúdo HTML / Shortcode</strong></label></th>
                                <td>
                                    <?php $html_mobile_content = isset($options[$key . '_html_mobile']) ? $options[$key . '_html_mobile'] : ''; ?>
                                    <div class="fcm-html-wrapper-desktop-<?php echo esc_attr($key); ?>" style="display: <?php echo $type === 'html' ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                                        <h4 style="margin-top:0; margin-bottom:5px;">Desktop</h4>
                                        <textarea name="fcm_settings[<?php echo esc_attr($key . '_html'); ?>]" rows="4" class="large-text code" placeholder="Cole aqui seu [elementor-template id='xx'] ou código HTML..."><?php echo esc_textarea($html_content); ?></textarea>
                                    </div>
                                    <div class="fcm-html-wrapper-mobile-<?php echo esc_attr($key); ?>" style="display: <?php echo $type_mobile === 'html' ? 'block' : 'none'; ?>;">
                                        <h4 style="margin-top:0; margin-bottom:5px;">Mobile</h4>
                                        <textarea name="fcm_settings[<?php echo esc_attr($key . '_html_mobile'); ?>]" rows="4" class="large-text code" placeholder="Cole aqui seu [elementor-template id='xx'] ou código HTML para mobile..."><?php echo esc_textarea($html_mobile_content); ?></textarea>
                                    </div>
                                </td>
                            </tr>"""

content = re.sub(
    r'<tr>\s*<th scope="row"><label><strong>Tipo de Banner</strong></label></th>\s*<td>\s*<select name="fcm_settings\[<\?php echo esc_attr\(\$key \. \'_type\'\); \?>\]" class="fcm-main-type-select" data-key="<\?php echo esc_attr\(\$key\); \?>".*?</select>\s*</td>\s*</tr>\s*<tr class="fcm-main-type-area fcm-main-type-image-<\?php echo esc_attr\(\$key\); \?>".*?<th scope="row" style="width: 250px;"><label><strong>Imagem do Banner</strong></label></th>.*?</tr>\s*<tr class="fcm-main-type-area fcm-main-type-html-<\?php echo esc_attr\(\$key\); \?>".*?<th scope="row"><label><strong>Conteúdo HTML / Shortcode</strong></label></th>.*?</tr>',
    replacement,
    content,
    flags=re.DOTALL
)

# 3. JS Replacements
content = content.replace(
    """            // Main Type toggle
            $('.fcm-main-type-select').change(function(){
                var key = $(this).data('key');
                $('.fcm-main-type-image-' + key).hide();
                $('.fcm-main-type-html-' + key).hide();
                $('.fcm-main-type-' + $(this).val() + '-' + key).show();
            });""",
    """            // Main Type toggle
            $('.fcm-main-type-select-desktop, .fcm-main-type-select-mobile').change(function(){
                var key = $(this).data('key');
                var dType = $('.fcm-main-type-select-desktop[data-key="'+key+'"]').val();
                var mType = $('.fcm-main-type-select-mobile[data-key="'+key+'"]').val();
                
                if (dType === 'image') {
                    $('.fcm-upload-wrapper-desktop-' + key).show();
                    $('.fcm-html-wrapper-desktop-' + key).hide();
                } else {
                    $('.fcm-upload-wrapper-desktop-' + key).hide();
                    $('.fcm-html-wrapper-desktop-' + key).show();
                }

                if (mType === 'image') {
                    $('.fcm-upload-wrapper-mobile-' + key).show();
                    $('.fcm-html-wrapper-mobile-' + key).hide();
                } else {
                    $('.fcm-upload-wrapper-mobile-' + key).hide();
                    $('.fcm-html-wrapper-mobile-' + key).show();
                }
                
                if (dType === 'html' || mType === 'html') {
                    $('.fcm-main-type-html-' + key).show();
                } else {
                    $('.fcm-main-type-html-' + key).hide();
                }
            });"""
)

# Let's save just the general settings first, then do Custom and Shortcode separately.
with open('plugin classificação.php', 'w', encoding='utf-8') as f:
    f.write(content)
