import re

with open('plugin classificação.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. General Settings
def replace_general_settings(match):
    key = match.group(1)
    return f"""                            <tr>
                                <th scope="row"><label><strong>Conteúdo do Banner</strong></label></th>
                                <td>
                                    <?php $type_mobile = isset($options[$key . '_type_mobile']) ? $options[$key . '_type_mobile'] : $type; ?>
                                    <?php $html_mobile_content = isset($options[$key . '_html_mobile']) ? $options[$key . '_html_mobile'] : ''; ?>
                                    <div style="display:flex; gap: 20px; align-items: stretch;">
                                        <!-- Desktop Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;">🖥️ Desktop</h4>
                                            
                                            <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo de Banner:</label>
                                            <select name="fcm_settings[<?php echo esc_attr($key . '_type'); ?>]" class="fcm-main-type-select-desktop" data-key="<?php echo esc_attr($key); ?>" style="width: 100%; margin-bottom:15px;">
                                                <option value="image" <?php selected($type, 'image'); ?>>Apenas Imagem (Padrão)</option>
                                                <option value="html" <?php selected($type, 'html'); ?>>Shortcode Elementor / HTML</option>
                                            </select>

                                            <div class="fcm-upload-wrapper-desktop-<?php echo esc_attr($key); ?>" style="display: <?php echo $type === 'image' ? 'block' : 'none'; ?>;">
                                                <img src="<?php echo esc_url($img_url); ?>" style="max-width:100%; display:<?php echo $img_url ? 'block' : 'none'; ?>; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                                                <input type="hidden" name="fcm_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($img_id); ?>" class="fcm-img-id">
                                                <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                            </div>

                                            <div class="fcm-html-wrapper-desktop-<?php echo esc_attr($key); ?>" style="display: <?php echo $type === 'html' ? 'block' : 'none'; ?>;">
                                                <textarea name="fcm_settings[<?php echo esc_attr($key . '_html'); ?>]" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."><?php echo esc_textarea($html_content); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;">📱 Mobile</h4>
                                            
                                            <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo de Banner:</label>
                                            <select name="fcm_settings[<?php echo esc_attr($key . '_type_mobile'); ?>]" class="fcm-main-type-select-mobile" data-key="<?php echo esc_attr($key); ?>" style="width: 100%; margin-bottom:15px;">
                                                <option value="image" <?php selected($type_mobile, 'image'); ?>>Apenas Imagem (Padrão)</option>
                                                <option value="html" <?php selected($type_mobile, 'html'); ?>>Shortcode Elementor / HTML</option>
                                            </select>

                                            <div class="fcm-upload-wrapper-mobile-<?php echo esc_attr($key); ?>" style="display: <?php echo $type_mobile === 'image' ? 'block' : 'none'; ?>;">
                                                <img src="<?php echo esc_url($img_mobile_url); ?>" style="max-width:100%; display:<?php echo $img_mobile_url ? 'block' : 'none'; ?>; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                                                <input type="hidden" name="fcm_settings[<?php echo esc_attr($key . '_mobile'); ?>]" value="<?php echo esc_attr($img_mobile_id); ?>" class="fcm-img-id">
                                                <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                            </div>

                                            <div class="fcm-html-wrapper-mobile-<?php echo esc_attr($key); ?>" style="display: <?php echo $type_mobile === 'html' ? 'block' : 'none'; ?>;">
                                                <textarea name="fcm_settings[<?php echo esc_attr($key . '_html_mobile'); ?>]" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."><?php echo esc_textarea($html_mobile_content); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 15px; background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                                        <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL) - Aplicado para banners do tipo Imagem:</label>
                                        <input type="url" name="fcm_settings[<?php echo esc_attr($key . '_url'); ?>]" value="<?php echo esc_url($link_url); ?>" class="regular-text" style="width:100%; max-width:600px;" placeholder="https://exemplo.com/pagina">
                                    </div>
                                </td>
                            </tr>"""

content = re.sub(
    r'<tr>\s*<th scope="row"><label><strong>Tipo de Banner</strong></label></th>.*?<tr class="fcm-main-type-area fcm-main-type-html-<\?php echo esc_attr\((.*?)\); \?>".*?</tr>',
    replace_general_settings,
    content,
    flags=re.DOTALL
)

# 2. Custom Banners Settings
replacement_cb = """                            <tr>
                                <th scope="row"><label>Conteúdo do Banner</label></th>
                                <td>
                                    <div style="display:flex; gap: 20px; align-items: stretch;">
                                        <!-- Desktop Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;">🖥️ Desktop</h4>
                                            
                                            <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo de Banner:</label>
                                            <select name="cb_type" id="cb_type" style="width: 100%; margin-bottom:15px;">
                                                <option value="image">Apenas Imagem (Padrão)</option>
                                                <option value="html">Shortcode Elementor / HTML</option>
                                            </select>

                                            <div id="cb_upload_desktop" style="display:none;">
                                                <img src="" style="max-width:100%; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview" id="cb_image_preview">
                                                <input type="hidden" name="cb_image" id="cb_image" value="" class="fcm-img-id">
                                                <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                            </div>

                                            <div id="cb_html_desktop_wrapper" style="display:none;">
                                                <textarea name="cb_html" id="cb_html" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;">📱 Mobile</h4>
                                            
                                            <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo de Banner:</label>
                                            <select name="cb_type_mobile" id="cb_type_mobile" style="width: 100%; margin-bottom:15px;">
                                                <option value="image">Apenas Imagem (Padrão)</option>
                                                <option value="html">Shortcode Elementor / HTML</option>
                                            </select>

                                            <div id="cb_upload_mobile" style="display:none;">
                                                <img src="" style="max-width:100%; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview" id="cb_image_mobile_preview">
                                                <input type="hidden" name="cb_image_mobile" id="cb_image_mobile" value="" class="fcm-img-id">
                                                <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                            </div>

                                            <div id="cb_html_mobile_wrapper" style="display:none;">
                                                <textarea name="cb_html_mobile" id="cb_html_mobile" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 15px; background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                                        <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL) - Aplicado para banners do tipo Imagem:</label>
                                        <input type="url" name="cb_url" id="cb_url" class="regular-text" style="width:100%; max-width:600px;" placeholder="https://exemplo.com/pagina">
                                    </div>
                                </td>
                            </tr>"""

content = re.sub(
    r'<tr>\s*<th scope="row"><label>Tipo de Banner</label></th>.*?<tr class="cb-type-area cb-type-html" style="display:none;">.*?</tr>',
    replacement_cb,
    content,
    flags=re.DOTALL
)

# 3. Shortcode Banners Settings
replacement_scb = """                            <tr>
                                <th scope="row"><label>Conteúdo do Banner</label></th>
                                <td>
                                    <div style="display:flex; gap: 20px; align-items: stretch;">
                                        <!-- Desktop Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;">🖥️ Desktop</h4>
                                            
                                            <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo de Banner:</label>
                                            <select name="scb_type" id="scb_type" style="width: 100%; margin-bottom:15px;">
                                                <option value="image">Apenas Imagem (Padrão)</option>
                                                <option value="html">Shortcode Elementor / HTML</option>
                                            </select>

                                            <div id="scb_upload_desktop" style="display:none;">
                                                <img src="" style="max-width:100%; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview" id="scb_image_preview">
                                                <input type="hidden" name="scb_image" id="scb_image" value="" class="fcm-img-id">
                                                <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                            </div>

                                            <div id="scb_html_desktop_wrapper" style="display:none;">
                                                <textarea name="scb_html" id="scb_html" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px;">📱 Mobile</h4>
                                            
                                            <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo de Banner:</label>
                                            <select name="scb_type_mobile" id="scb_type_mobile" style="width: 100%; margin-bottom:15px;">
                                                <option value="image">Apenas Imagem (Padrão)</option>
                                                <option value="html">Shortcode Elementor / HTML</option>
                                            </select>

                                            <div id="scb_upload_mobile" style="display:none;">
                                                <img src="" style="max-width:100%; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview" id="scb_image_mobile_preview">
                                                <input type="hidden" name="scb_image_mobile" id="scb_image_mobile" value="" class="fcm-img-id">
                                                <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                            </div>

                                            <div id="scb_html_mobile_wrapper" style="display:none;">
                                                <textarea name="scb_html_mobile" id="scb_html_mobile" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 15px; background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                                        <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL) - Aplicado para banners do tipo Imagem:</label>
                                        <input type="url" name="scb_url" id="scb_url" class="regular-text" style="width:100%; max-width:600px;" placeholder="https://exemplo.com/pagina">
                                    </div>
                                </td>
                            </tr>"""

content = re.sub(
    r'<tr>\s*<th scope="row"><label>Tipo de Banner</label></th>.*?<tr class="scb-type-area scb-type-html" style="display:none;">.*?</tr>',
    replacement_scb,
    content,
    flags=re.DOTALL
)


with open('plugin classificação.php', 'w', encoding='utf-8') as f:
    f.write(content)
