<?php
    private function sanitize_random_entries($data) {
        if (!is_array($data)) return [];
        $sanitized = [];
        foreach ($data as $entry) {
            $sanitized[] = [
                'type' => sanitize_text_field($entry['type'] ?? 'image'),
                'image' => sanitize_text_field($entry['image'] ?? ''),
                'url' => sanitize_text_field($entry['url'] ?? ''),
                'css' => sanitize_text_field($entry['css'] ?? ''),
                'html' => wp_unslash($entry['html'] ?? '')
            ];
        }
        return $sanitized;
    }

    private function render_random_editor_html($key, $device, $data) {
        $items = $data ?? [];
        $html = '<div class="fcm-random-container" data-key="' . $key . '" data-device="' . $device . '">';
        $html .= '<div class="fcm-random-entries-list">';
        foreach ($items as $index => $item) {
            $html .= $this->render_random_entry_row($key, $device, $index, $item);
        }
        $html .= '</div>';
        $html .= '<button type="button" class="button button-secondary fcm-add-random-entry" style="margin-top:10px;">+ Adicionar Entrada (' . ucfirst($device) . ')</button>';
        $html .= '</div>';
        return $html;
    }

    private function render_random_entry_row($key, $device, $index, $data = []) {
        $type = $data['type'] ?? 'image';
        $img_id = $data['image'] ?? '';
        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
        $url = $data['url'] ?? '';
        $css = $data['css'] ?? '';
        $html_content = $data['html'] ?? '';

        $is_complex = in_array($key, ['cb', 'scb']);
        $name_prefix = $is_complex ? "{$key}_random[{$device}][{$index}]" : "fcm_settings[{$key}_{$device}_data][{$index}]";
        
        ob_start();
        ?>
        <div class="fcm-random-entry-row" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px; position: relative;">
            <button type="button" class="fcm-remove-random-entry" style="position: absolute; top: 5px; right: 5px; background: none; border: none; color: #b32d2e; cursor: pointer; font-size: 20px;">&times;</button>
            <div style="margin-bottom: 10px;">
                <label><strong>Tipo:</strong></label>
                <select name="<?php echo $name_prefix; ?>[type]" class="fcm-random-type-select">
                    <option value="image" <?php selected($type, 'image'); ?>>Imagem</option>
                    <option value="html" <?php selected($type, 'html'); ?>>HTML / Shortcode</option>
                </select>
            </div>
            
            <div class="fcm-random-type-area fcm-random-type-image" style="display: <?php echo $type === 'image' ? 'block' : 'none'; ?>;">
                <div class="fcm-upload-wrapper">
                    <img src="<?php echo $img_url; ?>" style="max-width:150px; display:<?php echo $img_url ? 'block' : 'none'; ?>; margin-bottom:10px; border: 1px dashed #ccc;" class="fcm-preview">
                    <input type="hidden" name="<?php echo $name_prefix; ?>[image]" value="<?php echo $img_id; ?>" class="fcm-img-id">
                    <button type="button" class="button button-secondary fcm-upload-btn">Escolher Imagem</button>
                    <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;">Remover</button>
                </div>
                <div style="margin-top: 10px;">
                    <label>URL de Destino:</label>
                    <input type="url" name="<?php echo $name_prefix; ?>[url]" value="<?php echo esc_url($url); ?>" class="regular-text" style="width: 100%;">
                </div>
                <div style="margin-top: 10px;">
                    <label>CSS Personalizado:</label>
                    <textarea name="<?php echo $name_prefix; ?>[css]" rows="1" class="regular-text code" style="width: 100%;"><?php echo esc_textarea($css); ?></textarea>
                </div>
            </div>
            
            <div class="fcm-random-type-area fcm-random-type-html" style="display: <?php echo $type === 'html' ? 'block' : 'none'; ?>;">
                <label>Conteúdo HTML / Shortcode:</label>
                <textarea name="<?php echo $name_prefix; ?>[html]" rows="3" class="large-text code" style="width: 100%;"><?php echo esc_textarea($html_content); ?></textarea>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
