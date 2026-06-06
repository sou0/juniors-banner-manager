import re

with open('plugin classificação.php', 'r', encoding='utf-8') as f:
    content = f.read()

replacement = """                            <tr>
                                <th scope="row"><label>Tipo de Banner (Desktop)</label></th>
                                <td>
                                    <select name="scb_type" id="scb_type">
                                        <option value="image">Apenas Imagem (Padrão)</option>
                                        <option value="html">Shortcode Elementor / HTML Personalizado</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Tipo de Banner (Mobile)</label></th>
                                <td>
                                    <select name="scb_type_mobile" id="scb_type_mobile">
                                        <option value="image">Apenas Imagem (Padrão)</option>
                                        <option value="html">Shortcode Elementor / HTML Personalizado</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr class="scb-type-area scb-type-image">
                                <th scope="row"><label>Imagens do Banner</label></th>
                                <td>
                                    <div style="display:flex; gap: 20px;">
                                        <div class="fcm-upload-wrapper" id="scb_upload_desktop" style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:10px;">Desktop</h4>
                                            <img src="" style="max-width:100%; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview" id="scb_image_preview">
                                            <input type="hidden" name="scb_image" id="scb_image" value="" class="fcm-img-id">
                                            <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                            <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                        </div>
                                        <div class="fcm-upload-wrapper" id="scb_upload_mobile" style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:10px;">Mobile (Opcional)</h4>
                                            <img src="" style="max-width:100%; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview" id="scb_image_mobile_preview">
                                            <input type="hidden" name="scb_image_mobile" id="scb_image_mobile" value="" class="fcm-img-id">
                                            <button type="button" class="button button-secondary fcm-upload-btn"><span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> Escolher Imagem</button>
                                            <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Remover</button>
                                        </div>
                                    </div>
                                    <div style="margin-top: 15px;">
                                        <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                        <input type="url" name="scb_url" id="scb_url" class="regular-text" placeholder="https://exemplo.com/pagina">
                                    </div>
                                </td>
                            </tr>

                            <tr class="scb-type-area scb-type-html" style="display:none;">
                                <th scope="row"><label>Conteúdo HTML / Shortcode</label></th>
                                <td>
                                    <div id="scb_html_desktop_wrapper" style="margin-bottom:10px;">
                                        <h4 style="margin-top:0; margin-bottom:5px;">Desktop</h4>
                                        <textarea name="scb_html" id="scb_html" rows="4" class="large-text code" placeholder="Cole aqui seu [elementor-template id='xx'] ou código HTML..."></textarea>
                                    </div>
                                    <div id="scb_html_mobile_wrapper">
                                        <h4 style="margin-top:0; margin-bottom:5px;">Mobile</h4>
                                        <textarea name="scb_html_mobile" id="scb_html_mobile" rows="4" class="large-text code" placeholder="Cole aqui seu [elementor-template id='xx'] ou código HTML para mobile..."></textarea>
                                    </div>
                                </td>
                            </tr>"""

content = re.sub(
    r'<tr>\s*<th scope="row"><label>Tipo de Banner</label></th>\s*<td>\s*<select name="scb_type" id="scb_type">.*?</select>\s*</td>\s*</tr>\s*<tr class="scb-type-area scb-type-image">\s*<th scope="row"><label>Imagem do Banner</label></th>.*?</tr>\s*<tr class="scb-type-area scb-type-html" style="display:none;">\s*<th scope="row"><label>Conteúdo HTML / Shortcode</label></th>.*?</tr>',
    replacement,
    content,
    flags=re.DOTALL
)

js_replacement = """            // ----- BANNERS VIA SHORTCODE LOGIC ----- //
            $('#scb_type, #scb_type_mobile').change(function(){
                var dType = $('#scb_type').val();
                var mType = $('#scb_type_mobile').val();
                
                if (dType === 'image') {
                    $('#scb_upload_desktop').show();
                    $('#scb_html_desktop_wrapper').hide();
                } else {
                    $('#scb_upload_desktop').hide();
                    $('#scb_html_desktop_wrapper').show();
                }

                if (mType === 'image') {
                    $('#scb_upload_mobile').show();
                    $('#scb_html_mobile_wrapper').hide();
                } else {
                    $('#scb_upload_mobile').hide();
                    $('#scb_html_mobile_wrapper').show();
                }
                
                if (dType === 'html' || mType === 'html') {
                    $('.scb-type-html').show();
                } else {
                    $('.scb-type-html').hide();
                }
            });"""

content = re.sub(
    r'// ----- BANNERS VIA SHORTCODE LOGIC ----- //\s*\$\(\'#scb_type\'\)\.change\(function\(\)\{\s*\$\(\'\.scb-type-area\'\)\.hide\(\);\s*\$\(\'\.scb-type-\' \+ \$\(this\)\.val\(\)\)\.show\(\);\s*\}\);',
    js_replacement,
    content,
    flags=re.DOTALL
)

with open('plugin classificação.php', 'w', encoding='utf-8') as f:
    f.write(content)
