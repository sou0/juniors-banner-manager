import sys

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'r') as f:
    content = f.read()

# --- FIX 1: Move Advanced Tab into Main Form ---
start_str = "                <!-- TAB: Avançado -->"
end_str = "                <!-- TAB: Importação CSV -->"

start_idx = content.find(start_str)
end_idx = content.find(end_str)

adv_block = content[start_idx:end_idx]

# Remove adv_block
content = content[:start_idx] + content[end_idx:]

# Find form submit button
btn_str = '                    <div id="fcm-main-submit-btn"'
btn_idx = content.find(btn_str)

# Insert adv_block before submit button
content = content[:btn_idx] + adv_block + content[btn_idx:]

# Update submit button display condition
content = content.replace(
    "display: <?php echo in_array($active_tab, array_keys($stages)) ? 'block' : 'none'; ?>",
    "display: <?php echo in_array($active_tab, array_merge(array_keys($stages), ['advanced', 'dashboard'])) ? 'block' : 'none'; ?>"
)

# --- FIX 2: Update render_meta_box ---
old_meta_box = """    public function render_meta_box($post) {
        $value = get_post_meta($post->ID, '_fcm_stage', true);
        wp_nonce_field('fcm_save_nonce', 'fcm_nonce');
        ?>
        <select name="fcm_stage" style="width:100%">
            <option value="">Padrão (Fallback Automático)</option>
            <option value="topo" <?php selected($value, 'topo'); ?>>Topo de Funil</option>
            <option value="meio" <?php selected($value, 'meio'); ?>>Meio de Funil</option>
            <option value="fundo" <?php selected($value, 'fundo'); ?>>Fundo de Funil</option>
        </select>
        <p class="description" style="margin-top: 10px;">Atenção: Se este post estiver nas regras de um "Banner de Override", as configurações de funil acima serão ignoradas.</p>
        <?php
    }"""

new_meta_box = """    public function render_meta_box($post) {
        $value = get_post_meta($post->ID, '_fcm_stage', true);
        wp_nonce_field('fcm_save_nonce', 'fcm_nonce');
        
        $options = get_option($this->option_name);
        $label_topo = isset($options['label_topo']) && !empty($options['label_topo']) ? $options['label_topo'] : 'Topo de Funil';
        $label_meio = isset($options['label_meio']) && !empty($options['label_meio']) ? $options['label_meio'] : 'Meio de Funil';
        $label_fundo = isset($options['label_fundo']) && !empty($options['label_fundo']) ? $options['label_fundo'] : 'Fundo de Funil';
        ?>
        <select name="fcm_stage" style="width:100%">
            <option value="">Padrão (Fallback Automático)</option>
            <option value="topo" <?php selected($value, 'topo'); ?>><?php echo esc_html($label_topo); ?></option>
            <option value="meio" <?php selected($value, 'meio'); ?>><?php echo esc_html($label_meio); ?></option>
            <option value="fundo" <?php selected($value, 'fundo'); ?>><?php echo esc_html($label_fundo); ?></option>
        </select>
        <p class="description" style="margin-top: 10px;">Atenção: Se este post estiver nas regras de um "Banner de Override", as configurações de funil acima serão ignoradas.</p>
        <?php
    }"""

content = content.replace(old_meta_box, new_meta_box)

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'w') as f:
    f.write(content)
