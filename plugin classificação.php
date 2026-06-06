<?php
/**
 * Plugin Name: Funnel CTA Manager Pro
 * Description: Gerencia CTAs dinâmicos, banners de funil, banners personalizados e banners via shortcode com cronômetros e controle de posição.
 * Version: 2.6
 * Author: junior
 * Text Domain: funnel-cta
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/SEU-USUARIO/banner-manager/',
	__FILE__,
	'banner-manager'
);
$myUpdateChecker->setBranch('main');

class FunnelCTAManager {

    private $option_name = 'fcm_settings';
    private $custom_banners_option = 'fcm_custom_banners';
    private $shortcode_banners_option = 'fcm_shortcode_banners';

    public function __construct() {
        add_action('admin_menu', [$this, 'create_menu']);
        add_action('admin_init', [$this, 'handle_custom_banner_save']);
        add_action('admin_init', [$this, 'handle_shortcode_banner_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_footer', [$this, 'admin_footer_scripts']);
        
        add_action('add_meta_boxes', [$this, 'add_funnel_meta_box']);
        add_action('save_post', [$this, 'save_funnel_meta_box']);

        add_action('wp_footer', [$this, 'inject_cta_via_js']);

        add_action('wp_ajax_fcm_import_csv', [$this, 'handle_csv_import']);
        add_action('wp_ajax_fcm_search_posts', [$this, 'handle_post_search']);
        add_action('wp_ajax_fcm_analyze_conflicts', [$this, 'handle_conflict_analysis']);

        // Registrar Shortcodes do Cronômetro
        add_shortcode('fcm_ano', [$this, 'render_countdown_ano']);
        add_shortcode('fcm_mes', [$this, 'render_countdown_mes']);
        add_shortcode('fcm_dia', [$this, 'render_countdown_dia']);
        add_shortcode('fcm_hora', [$this, 'render_countdown_hora']);
        add_shortcode('fcm_minuto', [$this, 'render_countdown_minuto']);
        add_shortcode('fcm_segundo', [$this, 'render_countdown_segundo']);

        // Registrar Shortcode do Banner Avulso
        add_shortcode('fcm_banner', [$this, 'render_fcm_banner_shortcode']);

        // Injetar JS do Cronômetro no Footer
        add_action('wp_footer', [$this, 'render_countdown_js']);
    }

    private function get_utc_timestamp($local_time_string) {
        if (empty($local_time_string)) return 0;
        try {
            $tz = wp_timezone();
            $date = new DateTime($local_time_string, $tz);
            return $date->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    }


    public function handle_conflict_analysis() {
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
                $html .= 'Modo simultâneo <strong>ATIVADO</strong>. O banner Global aparecerá junto com banners de Estágios, a não ser que tentem ocupar a mesma posição (' . esc_html($options['global_position'] ?? 'middle') . '). ';
            } else {
                $html .= 'Modo simultâneo <strong>DESATIVADO</strong>. Se o post tiver uma classificação de Funil com imagem, o Banner Global será "engolido" e não aparecerá. ';
            }
            $html .= '<br><a href="#" onclick="jQuery(\'.fcm-go-to-tab[data-target=\\\'#tab-global\\\']\').click(); return false;">Configurar Banner Global</a>';
            $html .= '</li>';
        } else {
            $html .= '<li style="background:#fff; border-left:4px solid #ccc; padding:15px; margin-bottom:10px; border-radius:4px;">Banner Global inativo.</li>';
        }

        foreach ($custom_banners as $cb) {
            if ($cb['status'] !== 'active') continue;
            
            $targets = array_map('trim', explode("\n", $cb['targets']));
            $targets = array_filter($targets);
            if (empty($targets)) continue;

            $allow_multiple = !empty($cb['allow_multiple']);
            
            $html .= '<li style="background:#fff; border-left:4px solid '.($allow_multiple ? '#2271b1' : '#d63638').'; padding:15px; margin-bottom:10px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">';
            $html .= '<strong>Banner de Override: ' . esc_html($cb['name']) . '</strong><br>';
            if (!$allow_multiple) {
                $html .= '⚠️ Este banner está configurado como <strong>EXCLUSIVO</strong>. Ele está <strong>bloqueando / engolindo TODOS os outros banners</strong> (Global e Estágios) em <strong>' . count($targets) . ' links</strong>. ';
            } else {
                $html .= 'ℹ️ Este banner permite múltiplos simultâneos, porém <strong>irá bloquear e sobrescrever qualquer outro banner que tente usar a posição (' . esc_html($cb['position'] ?? 'middle') . ')</strong> nos seus <strong>' . count($targets) . ' links</strong> afetados. ';
            }
            $html .= '<br><a href="#" onclick="jQuery(this).next(\'div\').slideToggle(); return false;">Exibir/Ocultar links afetados</a>';
            $html .= ' | <a href="#" class="btn-edit-custom-banner" data-banner=\''.esc_attr(json_encode($cb)).'\'>Configurar este Banner</a>';
            
            $html .= '<div style="display:none; margin-top:10px; background:#f9f9f9; padding:10px; border:1px solid #eee;">';
            foreach ($targets as $t) {
                $html .= '<code style="display:block; margin-bottom:3px;">' . esc_html($t) . '</code>';
            }
            $html .= '</div>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        echo $html;
        wp_die();
    }

    /* -------------------------------------------------------------------------
       1. CORE ADMIN & MENUS
    ---------------------------------------------------------------------------- */
    public function create_menu() {
        add_menu_page('Funnel CTA', 'Funnel CTA', 'manage_options', 'funnel-cta', [$this, 'render_admin_page'], 'dashicons-filter', 30);
    }

    public function handle_custom_banner_save() {
        if (!current_user_can('manage_options')) return;

        // Salvar / Criar
        if (isset($_POST['fcm_save_custom']) && isset($_POST['fcm_custom_nonce']) && wp_verify_nonce($_POST['fcm_custom_nonce'], 'fcm_save_custom_action')) {
            $banners = get_option($this->custom_banners_option, []);
            
            $id = !empty($_POST['cb_id']) ? sanitize_text_field($_POST['cb_id']) : 'cb_' . time();
            
            $banners[$id] = [
                'id' => $id,
                'name' => sanitize_text_field($_POST['cb_name']),
                'type' => sanitize_text_field($_POST['cb_type']),
                'type_mobile' => isset($_POST['cb_type_mobile']) ? sanitize_text_field($_POST['cb_type_mobile']) : '',
                'image' => sanitize_text_field($_POST['cb_image']),
                'image_mobile' => isset($_POST['cb_image_mobile']) ? sanitize_text_field($_POST['cb_image_mobile']) : '',
                'url' => sanitize_text_field($_POST['cb_url']),
                'url_mobile' => isset($_POST['cb_url_mobile']) ? sanitize_text_field($_POST['cb_url_mobile']) : '',
                'html' => wp_unslash($_POST['cb_html']),
                'html_mobile' => isset($_POST['cb_html_mobile']) ? wp_unslash($_POST['cb_html_mobile']) : '', 
                'schedule' => isset($_POST['cb_schedule']) ? 1 : 0,
                'start' => sanitize_text_field($_POST['cb_start']),
                'end' => sanitize_text_field($_POST['cb_end']),
                'targets' => sanitize_textarea_field($_POST['cb_targets']),
                'status' => sanitize_text_field($_POST['cb_status']),
                'position' => isset($_POST['cb_position']) ? sanitize_text_field($_POST['cb_position']) : 'middle',
                'allow_multiple' => isset($_POST['cb_allow_multiple']) ? 1 : 0,
                'paragraph' => isset($_POST['cb_paragraph']) ? intval($_POST['cb_paragraph']) : 3
            ];
            
            update_option($this->custom_banners_option, $banners);
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=custom-list&msg=saved'));
            exit;
        }

        // Deletar
        if (isset($_GET['fcm_del_cb']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'del_cb_' . $_GET['fcm_del_cb'])) {
            $id = sanitize_text_field($_GET['fcm_del_cb']);
            $banners = get_option($this->custom_banners_option, []);
            if (isset($banners[$id])) {
                unset($banners[$id]);
                update_option($this->custom_banners_option, $banners);
            }
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=custom-list&msg=deleted'));
            exit;
        }
    }

    public function handle_shortcode_banner_save() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['fcm_save_shortcode']) && isset($_POST['fcm_shortcode_nonce']) && wp_verify_nonce($_POST['fcm_shortcode_nonce'], 'fcm_save_shortcode_action')) {
            $banners = get_option($this->shortcode_banners_option, []);
            $id = !empty($_POST['scb_id']) ? sanitize_text_field($_POST['scb_id']) : 'scb_' . time();
            $banners[$id] = [
                'id' => $id,
                'name' => sanitize_text_field($_POST['scb_name']),
                'type' => sanitize_text_field($_POST['scb_type']),
                'type_mobile' => isset($_POST['scb_type_mobile']) ? sanitize_text_field($_POST['scb_type_mobile']) : '',
                'image' => sanitize_text_field($_POST['scb_image']),
                'image_mobile' => isset($_POST['scb_image_mobile']) ? sanitize_text_field($_POST['scb_image_mobile']) : '',
                'url' => sanitize_text_field($_POST['scb_url']),
                'url_mobile' => isset($_POST['scb_url_mobile']) ? sanitize_text_field($_POST['scb_url_mobile']) : '',
                'html' => wp_unslash($_POST['scb_html']),
                'html_mobile' => isset($_POST['scb_html_mobile']) ? wp_unslash($_POST['scb_html_mobile']) : '', 
                'schedule' => isset($_POST['scb_schedule']) ? 1 : 0,
                'start' => sanitize_text_field($_POST['scb_start']),
                'end' => sanitize_text_field($_POST['scb_end']),
                'status' => sanitize_text_field($_POST['scb_status'])
            ];
            update_option($this->shortcode_banners_option, $banners);
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=shortcode-list&msg=saved'));
            exit;
        }

        if (isset($_GET['fcm_del_scb']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'del_scb_' . $_GET['fcm_del_scb'])) {
            $id = sanitize_text_field($_GET['fcm_del_scb']);
            $banners = get_option($this->shortcode_banners_option, []);
            if (isset($banners[$id])) {
                unset($banners[$id]);
                update_option($this->shortcode_banners_option, $banners);
            }
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=shortcode-list&msg=deleted'));
            exit;
        }
    }

    private function get_banner_status_html($options, $key, $is_custom = false) {
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
    }

    public function render_admin_page() {
        $options = get_option($this->option_name);
        $custom_banners = get_option($this->custom_banners_option, []);
        $shortcode_banners = get_option($this->shortcode_banners_option, []);
        $default_status = isset($options['default_status']) ? $options['default_status'] : 'active';
        $excluded_posts = isset($options['excluded_posts']) ? (array)$options['excluded_posts'] : [];
        
        $label_topo = isset($options['label_topo']) && !empty($options['label_topo']) ? $options['label_topo'] : 'Topo de Funil';
        $label_meio = isset($options['label_meio']) && !empty($options['label_meio']) ? $options['label_meio'] : 'Meio de Funil';
        $label_fundo = isset($options['label_fundo']) && !empty($options['label_fundo']) ? $options['label_fundo'] : 'Fundo de Funil';

        $stages = [
            'global' => 'Banner Global (Todos os Posts)',
            'topo' => $label_topo, 
            'meio' => $label_meio, 
            'fundo' => $label_fundo,
            'padrao' => 'Imagem Padrão'
        ];

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        ?>
        <div class="wrap" style="max-width: 1200px;">
            <h1 style="margin-bottom: 20px;">Gerenciador de CTAs de Funil <span style="font-size:12px; background:#0073aa; color:#fff; padding:3px 8px; border-radius:10px; vertical-align:middle;">Pro v2.6</span></h1>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Banner salvo com sucesso!</p></div>'; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') echo '<div class="notice notice-success is-dismissible"><p>Banner excluído com sucesso!</p></div>'; ?>

            <h2 class="nav-tab-wrapper fcm-tabs" style="margin-bottom: 0;">
                <a href="#tab-dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Visão Geral</a>
                <?php foreach ($stages as $k => $l): ?>
                    <a href="#tab-<?php echo esc_attr($k); ?>" class="nav-tab <?php echo $active_tab === $k ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($l); ?></a>
                <?php endforeach; ?>
                <a href="#tab-custom-list" class="nav-tab <?php echo $active_tab === 'custom-list' ? 'nav-tab-active' : ''; ?>" style="background: #e3f2fd; color: #0c5460;">Banners de Override</a>
                <a href="#tab-custom-edit" class="nav-tab fcm-hidden-tab <?php echo $active_tab === 'custom-edit' ? 'nav-tab-active' : ''; ?>" style="display: <?php echo $active_tab === 'custom-edit' ? 'inline-block' : 'none'; ?>;">Editor de Override</a>
                
                <a href="#tab-shortcode-list" class="nav-tab <?php echo $active_tab === 'shortcode-list' ? 'nav-tab-active' : ''; ?>" style="background: #e8f5e9; color: #004d40;">Banners Shortcode</a>
                <a href="#tab-shortcode-edit" class="nav-tab fcm-hidden-tab <?php echo $active_tab === 'shortcode-edit' ? 'nav-tab-active' : ''; ?>" style="display: <?php echo $active_tab === 'shortcode-edit' ? 'inline-block' : 'none'; ?>;">Editor de Shortcode</a>
                
                <a href="#tab-import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">Importação CSV</a>
                <a href="#tab-list" class="nav-tab <?php echo $active_tab === 'list' ? 'nav-tab-active' : ''; ?>">Posts Classificados</a>
                <a href="#tab-logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>"><span class="dashicons dashicons-search" style="margin-top:4px;"></span> Monitor de Conflitos</a>
                <a href="#tab-advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>" style="color: #666;"><span class="dashicons dashicons-admin-generic" style="margin-top:4px;"></span> Avançado</a>
            </h2>

            <div class="fcm-content-wrapper" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-top: none; min-height: 500px;">
                
                <!-- START: MAIN OPTIONS FORM -->
                <form method="post" action="options.php" id="fcm-main-form">
                    <?php settings_fields('fcm_settings_group'); ?>
                    
                    <!-- TAB: Dashboard Central -->
                    <div id="tab-dashboard" class="tab-content" style="display: <?php echo $active_tab === 'dashboard' ? 'block' : 'none'; ?>;">
                        <h2 style="font-size: 1.3em; margin-top:0;">Central de Banners</h2>
                        <p class="description">Visão geral do status de todos os seus CTAs Padrões.</p>
                        <hr style="margin: 20px 0;">
                        
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <?php foreach ($stages as $key => $label): ?>
                                <div style="flex: 1; min-width: 200px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9; text-align: center;">
                                    <h3 style="margin-top: 0; font-size: 1.2em; color: #2271b1;"><?php echo esc_html($label); ?></h3>
                                    <div style="margin: 15px 0; font-size: 14px;">
                                        <?php echo $this->get_banner_status_html($options, $key); ?>
                                    </div>
                                    <a href="#" class="button button-secondary fcm-go-to-tab" data-target="#tab-<?php echo esc_attr($key); ?>">Configurar</a>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display: flex; gap: 20px; margin-top: 40px;">
                            <div style="flex: 1;">
                                <h3 style="margin-top: 0; font-size: 1.3em;">Banners Especiais (Override)</h3>
                                <p class="description">Substituem os CTAs de funil em URLs específicas automaticamente.</p>
                                <hr style="margin: 10px 0 20px 0;">
                                <a href="#" class="button button-primary fcm-go-to-tab" data-target="#tab-custom-list">Gerenciar Banners de Override (<?php echo count($custom_banners); ?>)</a>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="margin-top: 0; font-size: 1.3em;">Banners Especiais (Shortcodes)</h3>
                                <p class="description">Banners independentes que podem ser colados em qualquer lugar pelo shortcode.</p>
                                <hr style="margin: 10px 0 20px 0;">
                                <a href="#" class="button button-primary fcm-go-to-tab" data-target="#tab-shortcode-list">Gerenciar Banners Shortcode (<?php echo count($shortcode_banners); ?>)</a>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($stages as $key => $label): 
                        $type = isset($options[$key . '_type']) ? $options[$key . '_type'] : 'image';
                        $html_content = isset($options[$key . '_html']) ? $options[$key . '_html'] : '';
                        $img_id = isset($options[$key]) ? $options[$key] : '';
                        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
                        $img_mobile_id = isset($options[$key . '_mobile']) ? $options[$key . '_mobile'] : '';
                        $img_mobile_url = $img_mobile_id ? wp_get_attachment_url($img_mobile_id) : '';
                        $link_url = isset($options[$key . '_url']) ? $options[$key . '_url'] : '';
                        $schedule = !empty($options[$key . '_schedule']);
                        $start = isset($options[$key . '_start']) ? $options[$key . '_start'] : '';
                        $end = isset($options[$key . '_end']) ? $options[$key . '_end'] : '';
                        
                        $pos = isset($options[$key . '_position']) ? $options[$key . '_position'] : 'middle';
                        $p_count = isset($options[$key . '_paragraph']) ? $options[$key . '_paragraph'] : 3;
                    ?>
                    <!-- TAB: <?php echo esc_html($label); ?> -->
                    <div id="tab-<?php echo esc_attr($key); ?>" class="tab-content" style="display: <?php echo $active_tab === $key ? 'block' : 'none'; ?>;">
                        <h2 style="font-size: 1.3em; margin-top:0;">Configurar: <?php echo esc_html($label); ?></h2>
                        <hr style="margin: 20px 0;">
                        
                        <?php if ($key === 'global'): ?>
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
                        <?php endif; ?>

                        <?php if ($key === 'padrao'): ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="fcm_default_status"><strong>Status Global do Padrão</strong></label></th>
                                    <td>
                                        <select name="fcm_settings[default_status]" id="fcm_default_status" style="min-width: 250px;">
                                            <option value="active" <?php selected($default_status, 'active'); ?>>Ativo (Mostrar em todos sem estágio)</option>
                                            <option value="inactive" <?php selected($default_status, 'inactive'); ?>>Inativo (Ocultar)</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <hr style="margin: 20px 0;">
                        <?php endif; ?>

                        <table class="form-table">
                                                                                    <tr>
                                <th scope="row"><label><strong>Conteúdo do Banner</strong></label></th>
                                <td>
                                    <?php $type_mobile = isset($options[$key . '_type_mobile']) ? $options[$key . '_type_mobile'] : $type; ?>
                                    <?php $html_mobile_content = isset($options[$key . '_html_mobile']) ? $options[$key . '_html_mobile'] : ''; ?>
                                    <div style="display:flex; gap: 20px; align-items: stretch;">
                                        <!-- Desktop Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-desktop"></span> Desktop</h4>
                                            
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
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                                    <input type="url" name="fcm_settings[<?php echo esc_attr($key . '_url'); ?>]" value="<?php echo esc_url($link_url); ?>" class="regular-text" style="width:100%;" placeholder="https://exemplo.com/pagina">
                                                </div>
                                            </div>

                                            <div class="fcm-html-wrapper-desktop-<?php echo esc_attr($key); ?>" style="display: <?php echo $type === 'html' ? 'block' : 'none'; ?>;">
                                                <textarea name="fcm_settings[<?php echo esc_attr($key . '_html'); ?>]" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."><?php echo esc_textarea($html_content); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-smartphone"></span> Mobile</h4>
                                            
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
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link Mobile (Deixe vazio para usar o do Desktop):</label>
                                                    <?php $link_url_mobile = isset($options[$key . '_url_mobile']) ? $options[$key . '_url_mobile'] : ''; ?>
                                                    <input type="url" name="fcm_settings[<?php echo esc_attr($key . '_url_mobile'); ?>]" value="<?php echo esc_url($link_url_mobile); ?>" class="regular-text" style="width:100%;" placeholder="Ex: https://exemplo.com/mobile">
                                                </div>
                                            </div>

                                            <div class="fcm-html-wrapper-mobile-<?php echo esc_attr($key); ?>" style="display: <?php echo $type_mobile === 'html' ? 'block' : 'none'; ?>;">
                                                <textarea name="fcm_settings[<?php echo esc_attr($key . '_html_mobile'); ?>]" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."><?php echo esc_textarea($html_mobile_content); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><strong>Exibição Simultânea</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
                                        <input type="checkbox" name="cb_allow_multiple" id="cb_allow_multiple" value="1">
                                        Permitir múltiplos banners nesta página (desde que posições diferentes). Se desmarcado, ele bloqueia todos os outros níveis (Funil, Padrão, Global).
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><strong>Posição de Injeção no Texto</strong></label></th>
                                <td>
                                    <select name="fcm_settings[<?php echo esc_attr($key . '_position'); ?>]" class="fcm-position-select" style="min-width: 250px;">
                                        <option value="middle" <?php selected($pos, 'middle'); ?>>No Meio (Posicionamento Inteligente Seguro)</option>
                                        <option value="top" <?php selected($pos, 'top'); ?>>No Início (Antes do conteúdo)</option>
                                        <option value="bottom" <?php selected($pos, 'bottom'); ?>>No Fim (Depois do conteúdo)</option>
                                        <option value="after_p" <?php selected($pos, 'after_p'); ?>>Após "X" parágrafos exatos</option>
                                    </select>
                                    <div class="fcm-paragraph-count-wrapper" style="margin-top: 10px; display: <?php echo $pos === 'after_p' ? 'block' : 'none'; ?>;">
                                        <label>Inserir exatamente após o parágrafo nº:</label>
                                        <input type="number" name="fcm_settings[<?php echo esc_attr($key . '_paragraph'); ?>]" value="<?php echo esc_attr($p_count); ?>" min="1" style="width: 80px; margin-left: 10px;">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><strong>Agendamento (Temporizador)</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
                                        <input type="checkbox" name="fcm_settings[<?php echo esc_attr($key . '_schedule'); ?>]" class="fcm-schedule-toggle" value="1" <?php checked($schedule, true); ?>>
                                        Ativar temporizador para este banner
                                    </label>
                                    <div class="fcm-schedule-fields" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; display: <?php echo $schedule ? 'block' : 'none'; ?>;">
                                        <div style="margin-bottom: 15px;">
                                            <label style="display:inline-block; width: 150px; font-weight:600;">Data/Hora de Entrada:</label>
                                            <input type="datetime-local" name="fcm_settings[<?php echo esc_attr($key . '_start'); ?>]" value="<?php echo esc_attr($start); ?>">
                                        </div>
                                        <div>
                                            <label style="display:inline-block; width: 150px; font-weight:600;">Data/Hora de Saída:</label>
                                            <input type="datetime-local" name="fcm_settings[<?php echo esc_attr($key . '_end'); ?>]" value="<?php echo esc_attr($end); ?>">
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php if ($key === 'padrao'): ?>
                            <tr>
                                <th scope="row"><label><strong>Regras de Ocultação (Exceções)</strong></label></th>
                                <td>
                                    <p style="margin-bottom:10px;">A imagem padrão <strong>NÃO</strong> aparecerá nos posts listados abaixo:</p>
                                    <div style="position: relative; max-width: 400px;">
                                        <input type="text" id="fcm-post-search" class="regular-text" placeholder="Pesquisar título do post..." style="width:100%;">
                                        <span class="spinner" id="fcm-search-spinner" style="float:none; position:absolute; right:10px; top:4px;"></span>
                                        <ul id="fcm-search-results" style="display:none; position:absolute; top:30px; left:0; right:0; background:#fff; border:1px solid #ccc; max-height:200px; overflow-y:auto; z-index:9999; margin:0; padding:0; box-shadow: 0 3px 5px rgba(0,0,0,0.1);"></ul>
                                    </div>
                                    
                                    <div id="fcm-excluded-posts-container" style="margin-top: 15px; min-height: 50px; padding: 10px; background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 4px;">
                                        <?php if (empty($excluded_posts) || count(array_filter($excluded_posts)) == 0): ?>
                                            <span id="fcm-no-exclusions" class="description">Nenhum post excluído no momento.</span>
                                        <?php else: ?>
                                            <span id="fcm-no-exclusions" class="description" style="display:none;">Nenhum post excluído no momento.</span>
                                        <?php endif; ?>
                                        
                                        <?php foreach ($excluded_posts as $pid): if(!$pid) continue; ?>
                                            <span class="fcm-excluded-tag" style="display:inline-block; background:#fff; border:1px solid #ccc; border-radius:3px; padding:4px 8px; margin: 0 5px 5px 0; font-size:12px;">
                                                <?php echo esc_html(get_the_title($pid)); ?>
                                                <a href="#" class="fcm-remove-exclusion" style="color:#b32d2e; text-decoration:none; margin-left:5px; font-weight:bold;">&times;</a>
                                                <input type="hidden" name="fcm_settings[excluded_posts][]" value="<?php echo esc_attr($pid); ?>">
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <?php endforeach; ?>
                    
                <!-- TAB: Avançado -->
                <div id="tab-advanced" class="tab-content" style="display: <?php echo $active_tab === 'advanced' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Configurações Avançadas</h2>
                    <p class="description">Opções avançadas para desenvolvedores e ajustes finos do motor de injeção.</p>
                    <hr style="margin: 20px 0;">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label>Nomes dos Estágios no Editor</label></th>
                            <td>
                                <div style="margin-bottom: 5px;">
                                    <label style="display:inline-block; width: 60px;">Topo:</label>
                                    <input type="text" name="fcm_settings[label_topo]" value="<?php echo esc_attr(isset($options['label_topo']) ? $options['label_topo'] : ''); ?>" class="regular-text" placeholder="Ex: Topo de Funil">
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <label style="display:inline-block; width: 60px;">Meio:</label>
                                    <input type="text" name="fcm_settings[label_meio]" value="<?php echo esc_attr(isset($options['label_meio']) ? $options['label_meio'] : ''); ?>" class="regular-text" placeholder="Ex: Meio de Funil">
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <label style="display:inline-block; width: 60px;">Fundo:</label>
                                    <input type="text" name="fcm_settings[label_fundo]" value="<?php echo esc_attr(isset($options['label_fundo']) ? $options['label_fundo'] : ''); ?>" class="regular-text" placeholder="Ex: Fundo de Funil">
                                </div>
                                <p class="description">Altere os nomes que aparecem na tela de edição do post.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Seletores CSS Customizados (Opcional)</label></th>
                            <td>
                                <textarea name="fcm_settings[custom_selectors]" rows="5" class="regular-text code" placeholder=".minha-classe-personalizada&#10;#meu-id-de-post"><?php echo esc_textarea(isset($options['custom_selectors']) ? $options['custom_selectors'] : ''); ?></textarea>
                                <p class="description">Se o plugin não conseguir encontrar automaticamente o texto do seu post, adicione aqui a Classe CSS ou ID da caixa de texto principal.<br><strong>Um seletor por linha.</strong> Ex: <code>.conteudo-do-meu-tema</code> ou <code>#box-artigo</code></p>
                            </td>
                        </tr>
                    </table>
                </div>

                    <div id="fcm-main-submit-btn" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; display: <?php echo in_array($active_tab, array_merge(array_keys($stages), ['advanced', 'dashboard'])) ? 'block' : 'none'; ?>;">
                        <?php submit_button('Salvar Configurações Globais', 'primary', 'submit', false); ?>
                    </div>
                </form>
                <!-- END: MAIN OPTIONS FORM -->


                <!-- TAB: Banners Especiais OVERRIDE (List) -->
                <div id="tab-custom-list" class="tab-content" style="display: <?php echo $active_tab === 'custom-list' ? 'block' : 'none'; ?>;">
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <h2 style="font-size: 1.3em; margin:0;">Banners de Override (Substituição)</h2>
                        <a href="#" class="button button-primary" id="btn-create-custom-banner">Criar Novo Override</a>
                    </div>
                    <p class="description">Banners personalizados que substituem automaticamente os CTAs do funil em URLs específicas.</p>
                    <hr style="margin: 20px 0;">
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nome do Banner</th>
                                <th>Tipo</th>
                                <th>Status / Cronograma</th>
                                <th>Posição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($custom_banners)): ?>
                                <tr><td colspan="5">Nenhum banner especial criado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($custom_banners as $cb): 
                                    $type_label = $cb['type'] === 'image' ? 'Imagem' : 'HTML/Elementor';
                                    $pos_label = 'Meio';
                                    if(isset($cb['position'])) {
                                        if($cb['position'] === 'top') $pos_label = 'Início';
                                        elseif($cb['position'] === 'bottom') $pos_label = 'Fim';
                                        elseif($cb['position'] === 'after_p') $pos_label = 'Após P('.$cb['paragraph'].')';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($cb['name']); ?></strong><br><small>ID: <?php echo esc_html($cb['id']); ?></small></td>
                                    <td><?php echo esc_html($type_label); ?></td>
                                    <td><?php echo $this->get_banner_status_html($cb, '', true); ?></td>
                                    <td><?php echo esc_html($pos_label); ?></td>
                                    <td>
                                        <a href="#" class="button button-small btn-edit-custom-banner" 
                                           data-banner='<?php echo esc_attr(json_encode($cb)); ?>'>Editar</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=funnel-cta&tab=custom-list&fcm_del_cb=' . $cb['id']), 'del_cb_' . $cb['id']); ?>" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm('Tem certeza?');">Excluir</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TAB: Monitor de Conflitos -->
                <div id="tab-logs" class="tab-content" style="display: <?php echo $active_tab === 'logs' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Monitor de Conflitos e Substituições</h2>
                    <p class="description">Esta ferramenta analisa as configurações ativas e identifica como os banners estão agindo (bloqueando ou sobrescrevendo outros).</p>
                    <hr style="margin: 20px 0;">
                    <button type="button" id="btn-run-conflict-analysis" class="button button-primary"><span class="dashicons dashicons-chart-pie" style="margin-top:4px;"></span> Analisar Cenário Atual</button>
                    <span class="spinner" id="fcm-analysis-spinner" style="float:none; margin-top:3px;"></span>
                    <div id="fcm-conflict-results" style="margin-top: 20px;"></div>
                </div>

                <!-- TAB: Criar/Editar Banner Especial OVERRIDE (Form) -->
                <div id="tab-custom-edit" class="tab-content" style="display: <?php echo $active_tab === 'custom-edit' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;" id="custom-edit-title">Criar Banner de Override</h2>
                    <a href="#" class="fcm-go-to-tab" data-target="#tab-custom-list" style="text-decoration:none;">&larr; Voltar para a lista</a>
                    <hr style="margin: 20px 0;">

                    <form method="post" action="<?php echo admin_url('admin.php?page=funnel-cta&tab=custom-list'); ?>">
                        <?php wp_nonce_field('fcm_save_custom_action', 'fcm_custom_nonce'); ?>
                        <input type="hidden" name="fcm_save_custom" value="1">
                        <input type="hidden" name="cb_id" id="cb_id" value="">

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label>Nome do Banner</label></th>
                                <td><input type="text" name="cb_name" id="cb_name" class="regular-text" required placeholder="Ex: Black Friday 2026 Override"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Status</label></th>
                                <td>
                                    <select name="cb_status" id="cb_status">
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                    </select>
                                </td>
                            </tr>
                                                        <tr>
                                <th scope="row"><label>Conteúdo do Banner</label></th>
                                <td>
                                    <div style="display:flex; gap: 20px; align-items: stretch;">
                                        <!-- Desktop Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-desktop"></span> Desktop</h4>
                                            
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
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                                    <input type="url" name="cb_url" id="cb_url" class="regular-text" style="width:100%;" placeholder="https://exemplo.com/pagina">
                                                </div>
                                            </div>

                                            <div id="cb_html_desktop_wrapper" style="display:none;">
                                                <textarea name="cb_html" id="cb_html" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-smartphone"></span> Mobile</h4>
                                            
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
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link Mobile (Deixe vazio para usar o do Desktop):</label>
                                                    <input type="url" name="cb_url_mobile" id="cb_url_mobile" class="regular-text" style="width:100%;" placeholder="Ex: https://exemplo.com/mobile">
                                                </div>
                                            </div>

                                            <div id="cb_html_mobile_wrapper" style="display:none;">
                                                <textarea name="cb_html_mobile" id="cb_html_mobile" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label><strong>Exibição Simultânea</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
                                        <input type="checkbox" name="cb_allow_multiple" id="cb_allow_multiple" value="1">
                                        Permitir múltiplos banners nesta página (desde que posições diferentes). Se desmarcado, ele bloqueia todos os outros níveis (Funil, Padrão, Global).
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label><strong>Posição de Injeção no Texto</strong></label></th>
                                <td>
                                    <select name="cb_position" id="cb_position" class="fcm-position-select" style="min-width: 250px;">
                                        <option value="middle">No Meio (Posicionamento Inteligente Seguro)</option>
                                        <option value="top">No Início (Antes do conteúdo)</option>
                                        <option value="bottom">No Fim (Depois do conteúdo)</option>
                                        <option value="after_p">Após "X" parágrafos exatos</option>
                                    </select>
                                    <div class="fcm-paragraph-count-wrapper" style="margin-top: 10px; display: none;">
                                        <label>Inserir exatamente após o parágrafo nº:</label>
                                        <input type="number" name="cb_paragraph" id="cb_paragraph" value="3" min="1" style="width: 80px; margin-left: 10px;">
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label><strong>Agendamento (Temporizador)</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
                                        <input type="checkbox" name="cb_schedule" id="cb_schedule" class="fcm-schedule-toggle" value="1">
                                        Ativar cronograma para este banner
                                    </label>
                                    <div class="fcm-schedule-fields cb-schedule-fields" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; display: none;">
                                        <div style="margin-bottom: 15px;">
                                            <label style="display:inline-block; width: 150px; font-weight:600;">Data/Hora de Entrada:</label>
                                            <input type="datetime-local" name="cb_start" id="cb_start">
                                        </div>
                                        <div style="margin-bottom: 15px;">
                                            <label style="display:inline-block; width: 150px; font-weight:600;">Data/Hora de Saída:</label>
                                            <input type="datetime-local" name="cb_end" id="cb_end">
                                        </div>
                                        
                                        <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-left: 4px solid #dba617;">
                                            <h4 style="margin-top:0;">Shortcodes do Cronômetro Regressivo</h4>
                                            <code style="display:block; margin:5px 0;">[fcm_ano id="<span class="cb_dynamic_id">ID_DO_BANNER</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_mes id="<span class="cb_dynamic_id">ID_DO_BANNER</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_dia id="<span class="cb_dynamic_id">ID_DO_BANNER</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_hora id="<span class="cb_dynamic_id">ID_DO_BANNER</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_minuto id="<span class="cb_dynamic_id">ID_DO_BANNER</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_segundo id="<span class="cb_dynamic_id">ID_DO_BANNER</span>"]</code>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label>Links Alvo (Onde exibir?)</label></th>
                                <td>
                                    <textarea name="cb_targets" id="cb_targets" rows="5" class="large-text" placeholder="/url-do-post-1/&#10;/url-do-post-2/"></textarea>
                                    <p class="description">Cole as URLs dos posts onde este banner deve aparecer. Ele <strong>sobrescreverá</strong> o funil nessas URLs.</p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit_custom" class="button button-primary" value="Salvar Banner Especial">
                        </p>
                    </form>
                </div>

                <!-- TAB: Banners SHORTCODE (List) -->
                <div id="tab-shortcode-list" class="tab-content" style="display: <?php echo $active_tab === 'shortcode-list' ? 'block' : 'none'; ?>;">
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <h2 style="font-size: 1.3em; margin:0;">Banners via Shortcode</h2>
                        <a href="#" class="button button-primary" id="btn-create-shortcode-banner">Criar Novo Banner Shortcode</a>
                    </div>
                    <p class="description">Banners independentes que não são injetados automaticamente. Você mesmo cola o shortcode onde quiser.</p>
                    <hr style="margin: 20px 0;">
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nome do Banner</th>
                                <th>Shortcode de Injeção</th>
                                <th>Status / Cronograma</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shortcode_banners)): ?>
                                <tr><td colspan="4">Nenhum banner shortcode criado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($shortcode_banners as $scb): 
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($scb['name']); ?></strong><br><small>Tipo: <?php echo $scb['type'] === 'image' ? 'Imagem' : 'HTML/Elementor'; ?></small></td>
                                    <td><code style="font-size:14px;">[fcm_banner id="<?php echo esc_html($scb['id']); ?>"]</code></td>
                                    <td><?php echo $this->get_banner_status_html($scb, '', true); ?></td>
                                    <td>
                                        <a href="#" class="button button-small btn-edit-shortcode-banner" 
                                           data-banner='<?php echo esc_attr(json_encode($scb)); ?>'>Editar</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=funnel-cta&tab=shortcode-list&fcm_del_scb=' . $scb['id']), 'del_scb_' . $scb['id']); ?>" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm('Tem certeza?');">Excluir</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TAB: Monitor de Conflitos -->
                <div id="tab-logs" class="tab-content" style="display: <?php echo $active_tab === 'logs' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Monitor de Conflitos e Substituições</h2>
                    <p class="description">Esta ferramenta analisa as configurações ativas e identifica como os banners estão agindo (bloqueando ou sobrescrevendo outros).</p>
                    <hr style="margin: 20px 0;">
                    <button type="button" id="btn-run-conflict-analysis" class="button button-primary"><span class="dashicons dashicons-chart-pie" style="margin-top:4px;"></span> Analisar Cenário Atual</button>
                    <span class="spinner" id="fcm-analysis-spinner" style="float:none; margin-top:3px;"></span>
                    <div id="fcm-conflict-results" style="margin-top: 20px;"></div>
                </div>

                <!-- TAB: Criar/Editar Banner SHORTCODE (Form) -->
                <div id="tab-shortcode-edit" class="tab-content" style="display: <?php echo $active_tab === 'shortcode-edit' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;" id="shortcode-edit-title">Criar Banner Shortcode</h2>
                    <a href="#" class="fcm-go-to-tab" data-target="#tab-shortcode-list" style="text-decoration:none;">&larr; Voltar para a lista</a>
                    <hr style="margin: 20px 0;">

                    <form method="post" action="<?php echo admin_url('admin.php?page=funnel-cta&tab=shortcode-list'); ?>">
                        <?php wp_nonce_field('fcm_save_shortcode_action', 'fcm_shortcode_nonce'); ?>
                        <input type="hidden" name="fcm_save_shortcode" value="1">
                        <input type="hidden" name="scb_id" id="scb_id" value="">

                        <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin-bottom: 20px;">
                            <strong>Como usar:</strong> Para exibir este banner em qualquer lugar do seu site (inclusive páginas do Elementor), use o shortcode:<br>
                            <code style="font-size: 16px; margin-top:5px; display:inline-block;">[fcm_banner id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label>Nome do Banner</label></th>
                                <td><input type="text" name="scb_name" id="scb_name" class="regular-text" required placeholder="Ex: Banner Avulso Lateral"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Status</label></th>
                                <td>
                                    <select name="scb_status" id="scb_status">
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                    </select>
                                </td>
                            </tr>
                                                        <tr>
                                <th scope="row"><label>Conteúdo do Banner</label></th>
                                <td>
                                    <div style="display:flex; gap: 20px; align-items: stretch;">
                                        <!-- Desktop Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-desktop"></span> Desktop</h4>
                                            
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
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link de Destino (URL):</label>
                                                    <input type="url" name="scb_url" id="scb_url" class="regular-text" style="width:100%;" placeholder="https://exemplo.com/pagina">
                                                </div>
                                            </div>

                                            <div id="scb_html_desktop_wrapper" style="display:none;">
                                                <textarea name="scb_html" id="scb_html" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div style="background:#f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; flex:1;">
                                            <h4 style="margin-top:0; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:16px; display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-smartphone"></span> Mobile</h4>
                                            
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
                                                <div style="margin-top: 15px;">
                                                    <label style="display:block; margin-bottom: 5px; font-weight: 600;">Link Mobile (Deixe vazio para usar o do Desktop):</label>
                                                    <input type="url" name="scb_url_mobile" id="scb_url_mobile" class="regular-text" style="width:100%;" placeholder="Ex: https://exemplo.com/mobile">
                                                </div>
                                            </div>

                                            <div id="scb_html_mobile_wrapper" style="display:none;">
                                                <textarea name="scb_html_mobile" id="scb_html_mobile" rows="6" class="large-text code" style="width: 100%;" placeholder="Cole o Shortcode ou HTML..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label><strong>Agendamento (Temporizador)</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
                                        <input type="checkbox" name="scb_schedule" id="scb_schedule" class="fcm-schedule-toggle" value="1">
                                        Ativar cronograma para este banner
                                    </label>
                                    <div class="fcm-schedule-fields scb-schedule-fields" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; display: none;">
                                        <div style="margin-bottom: 15px;">
                                            <label style="display:inline-block; width: 150px; font-weight:600;">Data/Hora de Entrada:</label>
                                            <input type="datetime-local" name="scb_start" id="scb_start">
                                        </div>
                                        <div style="margin-bottom: 15px;">
                                            <label style="display:inline-block; width: 150px; font-weight:600;">Data/Hora de Saída:</label>
                                            <input type="datetime-local" name="scb_end" id="scb_end">
                                        </div>
                                        
                                        <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-left: 4px solid #dba617;">
                                            <h4 style="margin-top:0;">Shortcodes do Cronômetro Regressivo</h4>
                                            <code style="display:block; margin:5px 0;">[fcm_ano id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_mes id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_dia id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_hora id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_minuto id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                                            <code style="display:block; margin:5px 0;">[fcm_segundo id="<span class="scb_dynamic_id">NOVO</span>"]</code>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit_shortcode" class="button button-primary" value="Salvar Banner Shortcode">
                        </p>
                    </form>
                </div>


                <!-- TAB: Importação CSV -->
                <div id="tab-import" class="tab-content" style="display: <?php echo $active_tab === 'import' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Importação em Massa via CSV</h2>
                    <hr style="margin: 20px 0;">
                    <div style="background: #f8f9fa; border-left: 4px solid #00a0d2; padding: 15px; margin-bottom: 20px;">
                        <strong>Estrutura da Planilha:</strong> O CSV não deve ter cabeçalho. As colunas devem ser:<br>
                        <code>Coluna A:</code> URL Completa ou Slug do Post<br>
                        <code>Coluna B:</code> Estágio (qualquer texto que você mapear abaixo)
                    </div>

                    <div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 4px; max-width: 600px;">
                        <h3 style="margin-top: 0;">Mapeamento de Nomes na Planilha</h3>
                        <p class="description">Se a sua planilha não usa exatamente "topo", "meio" e "fundo", digite abaixo quais nomes ela usa para que o importador os reconheça (ignorando maiúsculas/minúsculas).</p>
                        <div style="margin-bottom: 10px;">
                            <label style="display:inline-block; width: 100px; font-weight: bold;">Topo =</label>
                            <input type="text" id="fcm-map-topo" class="regular-text" placeholder="Ex: topo" value="topo">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label style="display:inline-block; width: 100px; font-weight: bold;">Meio =</label>
                            <input type="text" id="fcm-map-meio" class="regular-text" placeholder="Ex: meio" value="meio">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label style="display:inline-block; width: 100px; font-weight: bold;">Fundo =</label>
                            <input type="text" id="fcm-map-fundo" class="regular-text" placeholder="Ex: fundo" value="fundo">
                        </div>
                    </div>

                    <input type="file" id="fcm-csv-file" accept=".csv" style="margin-bottom: 15px;"><br>
                    <button type="button" id="fcm-run-import" class="button button-primary"><span class="dashicons dashicons-upload" style="margin-top:4px;"></span> Processar Planilha</button>
                    <div id="fcm-import-log" style="margin-top:20px; font-size:14px; max-width: 600px;"></div>
                </div>

                <!-- TAB: Posts Classificados -->
                <div id="tab-list" class="tab-content" style="display: <?php echo $active_tab === 'list' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Lista de Posts com CTA</h2>
                    <hr style="margin: 20px 0;">
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 60%;">Título do Post / URL</th>
                                <th>Estágio do Funil</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $classified_posts = new WP_Query([
                                'post_type'      => 'post',
                                'posts_per_page' => 100,
                                'post_status'    => 'any',
                                'meta_query'     => [['key' => '_fcm_stage', 'compare' => 'EXISTS']]
                            ]);

                            if ($classified_posts->have_posts()) {
                                while ($classified_posts->have_posts()) {
                                    $classified_posts->the_post();
                                    $stage = get_post_meta(get_the_ID(), '_fcm_stage', true);
                                    if(empty($stage)) continue;
                                    
                                    $colors = ['topo' => '#d1ecf1', 'meio' => '#fff3cd', 'fundo' => '#f8d7da'];
                                    $bg = isset($colors[$stage]) ? $colors[$stage] : '#eee';
                                    $stage_label = $stage;
                                    if ($stage === 'topo') $stage_label = $label_topo;
                                    if ($stage === 'meio') $stage_label = $label_meio;
                                    if ($stage === 'fundo') $stage_label = $label_fundo;

                                    echo '<tr>';
                                    echo '<td><strong>' . get_the_title() . '</strong><br><small><a href="' . get_permalink() . '" target="_blank">' . get_permalink() . '</a></small></td>';
                                    echo '<td><span style="background:'. $bg .'; padding: 5px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 10px;">' . esc_html($stage_label) . '</span></td>';
                                    echo '<td><a href="' . get_edit_post_link() . '" class="button button-small" target="_blank">Editar</a></td>';
                                    echo '</tr>';
                                }
                                wp_reset_postdata();
                            } else {
                                echo '<tr><td colspan="3">Nenhum post classificado até o momento.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- TAB: Monitor de Conflitos -->
                <div id="tab-logs" class="tab-content" style="display: <?php echo $active_tab === 'logs' ? 'block' : 'none'; ?>;">
                    <h2 style="font-size: 1.3em; margin-top:0;">Monitor de Conflitos e Substituições</h2>
                    <p class="description">Esta ferramenta analisa as configurações ativas e identifica como os banners estão agindo (bloqueando ou sobrescrevendo outros).</p>
                    <hr style="margin: 20px 0;">
                    <button type="button" id="btn-run-conflict-analysis" class="button button-primary"><span class="dashicons dashicons-chart-pie" style="margin-top:4px;"></span> Analisar Cenário Atual</button>
                    <span class="spinner" id="fcm-analysis-spinner" style="float:none; margin-top:3px;"></span>
                    <div id="fcm-conflict-results" style="margin-top: 20px;"></div>
                </div>
            </div>
        </div>
        
        <style>
            #fcm-search-results li { padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #eee; }
            #fcm-search-results li:hover { background: #f0f0f1; }
        </style>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_funnel-cta') return;
        wp_enqueue_media();
    }

    public function admin_footer_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_funnel-cta') return;
        ?>
        <script>
        jQuery(document).ready(function($){
            var mainFormTabs = ['#tab-dashboard', '#tab-topo', '#tab-meio', '#tab-fundo', '#tab-padrao', '#tab-advanced'];

            function switchTab(href) {
                $('.nav-tab').removeClass('nav-tab-active');
                
                // Tratar tabs ocultas
                $('a[href="#tab-custom-edit"], a[href="#tab-shortcode-edit"]').hide();
                
                if(href === '#tab-custom-edit') {
                    $('a[href="#tab-custom-edit"]').show().addClass('nav-tab-active');
                } else if(href === '#tab-shortcode-edit') {
                    $('a[href="#tab-shortcode-edit"]').show().addClass('nav-tab-active');
                } else {
                    $('.nav-tab[href="'+href+'"]').addClass('nav-tab-active');
                }

                $('.tab-content').hide();
                $(href).fadeIn('fast');
                
                if(mainFormTabs.includes(href)) {
                    $('#fcm-main-submit-btn').show();
                } else {
                    $('#fcm-main-submit-btn').hide();
                }

                var tabName = href.replace('#tab-', '');
                var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=funnel-cta&tab=' + tabName;
                window.history.pushState({path:newurl},'',newurl);
            }

            $('.nav-tab').click(function(e){
                e.preventDefault();
                switchTab($(this).attr('href'));
            });

            $('.fcm-go-to-tab').click(function(e){
                e.preventDefault();
                switchTab($(this).data('target'));
            });

            $('.fcm-schedule-toggle').change(function() {
                var fields = $(this).closest('td').find('.fcm-schedule-fields');
                if ($(this).is(':checked')) {
                    fields.slideDown('fast');
                } else {
                    fields.slideUp('fast');
                }
            });

            // Position toggle (Geral)
            $('.fcm-position-select').change(function(){
                if($(this).val() === 'after_p') {
                    $(this).siblings('.fcm-paragraph-count-wrapper').slideDown('fast');
                } else {
                    $(this).siblings('.fcm-paragraph-count-wrapper').slideUp('fast');
                }
            });

            // Main Type toggle
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
            });

            $('.fcm-upload-btn').click(function(e){
                var btn = $(this);
                var uploader = wp.media({title: 'Escolher Imagem', button: {text: 'Usar Imagem'}, multiple: false}).on('select', function() {
                    var attachment = uploader.state().get('selection').first().toJSON();
                    btn.siblings('.fcm-preview').attr('src', attachment.url).show();
                    btn.siblings('.fcm-img-id').val(attachment.id);
                }).open();
            });

            $('.fcm-remove-btn').click(function(){
                $(this).siblings('.fcm-preview').hide();
                $(this).siblings('.fcm-img-id').val('');
            });

                        // ----- BANNERS DE OVERRIDE LOGIC ----- //
            $('#cb_type, #cb_type_mobile').change(function(){
                var dType = $('#cb_type').val();
                var mType = $('#cb_type_mobile').val();
                
                if (dType === 'image') {
                    $('#cb_upload_desktop').show();
                    $('#cb_html_desktop_wrapper').hide();
                } else {
                    $('#cb_upload_desktop').hide();
                    $('#cb_html_desktop_wrapper').show();
                }

                if (mType === 'image') {
                    $('#cb_upload_mobile').show();
                    $('#cb_html_mobile_wrapper').hide();
                } else {
                    $('#cb_upload_mobile').hide();
                    $('#cb_html_mobile_wrapper').show();
                }
                
                if (dType === 'html' || mType === 'html') {
                    $('.cb-type-html').show();
                } else {
                    $('.cb-type-html').hide();
                }
            });

            $('#btn-create-custom-banner').click(function(e){
                e.preventDefault();
                $('#custom-edit-title').text('Criar Banner de Override');
                $('#cb_id').val('cb_' + Date.now());
                $('.cb_dynamic_id').text($('#cb_id').val());
                
                $('#cb_name').val('');
                $('#cb_status').val('active');
                $('#cb_type').val('image').trigger('change');
                $('#cb_type_mobile').val('image').trigger('change');
                $('#cb_image').val('');
                $('#cb_image_preview').hide().attr('src', '');
                $('#cb_image_mobile').val('');
                $('#cb_image_mobile_preview').hide().attr('src', '');
                $('#cb_url').val('');
                $('#cb_url_mobile').val('');
                $('#cb_html').val('');
                $('#cb_html_mobile').val('');
                $('#cb_schedule').prop('checked', false).trigger('change');
                $('#cb_allow_multiple').prop('checked', false);
                $('#cb_start').val('');
                $('#cb_end').val('');
                $('#cb_targets').val('');
                $('#cb_position').val('middle').trigger('change');
                $('#cb_paragraph').val('3');

                switchTab('#tab-custom-edit');
            });

            $('.btn-edit-custom-banner').click(function(e){
                e.preventDefault();
                $('#custom-edit-title').text('Editar Banner de Override');
                var data = $(this).data('banner');
                
                $('#cb_id').val(data.id);
                $('.cb_dynamic_id').text(data.id);
                $('#cb_name').val(data.name);
                $('#cb_status').val(data.status);
                $('#cb_type').val(data.type).trigger('change');
                $('#cb_type_mobile').val(data.type_mobile || data.type).trigger('change');
                
                $('#cb_image').val(data.image);
                if(data.image) { 
                    $('#cb_image_preview').attr('src', '<?php echo admin_url('images/media-button.png'); ?>').show();
                } else {
                    $('#cb_image_preview').hide();
                }
                $('#cb_image_mobile').val(data.image_mobile);
                if(data.image_mobile) { 
                    $('#cb_image_mobile_preview').attr('src', '<?php echo admin_url('images/media-button.png'); ?>').show();
                } else {
                    $('#cb_image_mobile_preview').hide();
                }
                
                $('#cb_url').val(data.url);
                $('#cb_url_mobile').val(data.url_mobile || '');
                $('#cb_html').val(data.html);
                $('#cb_html_mobile').val(data.html_mobile || data.html);
                
                $('#cb_schedule').prop('checked', data.schedule == 1).trigger('change');
                $('#cb_allow_multiple').prop('checked', data.allow_multiple == 1);
                $('#cb_start').val(data.start);
                $('#cb_end').val(data.end);
                $('#cb_targets').val(data.targets);

                $('#cb_position').val(data.position || 'middle').trigger('change');
                $('#cb_paragraph').val(data.paragraph || 3);

                switchTab('#tab-custom-edit');
            });

            // ----- BANNERS SHORTCODE LOGIC ----- //
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
            });

            $('#btn-create-shortcode-banner').click(function(e){
                e.preventDefault();
                $('#shortcode-edit-title').text('Criar Novo Banner Shortcode');
                $('#scb_id').val('scb_' + Date.now());
                $('.scb_dynamic_id').text($('#scb_id').val());
                
                $('#scb_name').val('');
                $('#scb_status').val('active');
                $('#scb_type').val('image').trigger('change');
                $('#scb_type_mobile').val('image').trigger('change');
                $('#scb_image').val('');
                $('#scb_image_preview').hide().attr('src', '');
                $('#scb_image_mobile').val('');
                $('#scb_image_mobile_preview').hide().attr('src', '');
                $('#scb_url').val('');
                $('#scb_url_mobile').val('');
                $('#scb_html').val('');
                $('#scb_html_mobile').val('');
                $('#scb_schedule').prop('checked', false).trigger('change');
                $('#scb_start').val('');
                $('#scb_end').val('');

                switchTab('#tab-shortcode-edit');
            });

            $('.btn-edit-shortcode-banner').click(function(e){
                e.preventDefault();
                $('#shortcode-edit-title').text('Editar Banner Shortcode');
                var data = $(this).data('banner');
                
                $('#scb_id').val(data.id);
                $('.scb_dynamic_id').text(data.id);
                $('#scb_name').val(data.name);
                $('#scb_status').val(data.status);
                $('#scb_type').val(data.type).trigger('change');
                $('#scb_type_mobile').val(data.type_mobile || data.type).trigger('change');
                
                $('#scb_image').val(data.image);
                if(data.image) { 
                    $('#scb_image_preview').attr('src', '<?php echo admin_url('images/media-button.png'); ?>').show();
                } else {
                    $('#scb_image_preview').hide();
                }
                $('#scb_image_mobile').val(data.image_mobile);
                if(data.image_mobile) { 
                    $('#scb_image_mobile_preview').attr('src', '<?php echo admin_url('images/media-button.png'); ?>').show();
                } else {
                    $('#scb_image_mobile_preview').hide();
                }
                
                $('#scb_url').val(data.url);
                $('#scb_url_mobile').val(data.url_mobile || '');
                $('#scb_html').val(data.html);
                $('#scb_html_mobile').val(data.html_mobile || data.html);
                
                $('#scb_schedule').prop('checked', data.schedule == 1).trigger('change');
                $('#scb_start').val(data.start);
                $('#scb_end').val(data.end);

                switchTab('#tab-shortcode-edit');
            });


            // ----- AJAX ----- //
            $('#btn-run-conflict-analysis').click(function(){
                $('#fcm-analysis-spinner').addClass('is-active');
                $.post(ajaxurl, {action: 'fcm_analyze_conflicts'}, function(res){
                    $('#fcm-analysis-spinner').removeClass('is-active');
                    $('#fcm-conflict-results').html(res);
                });
            });

            $('#fcm-run-import').click(function(){
                var file = $('#fcm-csv-file').prop('files')[0];
                if(!file) return alert('Selecione um arquivo CSV.');
                
                var mapTopo = $('#fcm-map-topo').val() || 'topo';
                var mapMeio = $('#fcm-map-meio').val() || 'meio';
                var mapFundo = $('#fcm-map-fundo').val() || 'fundo';

                $('#fcm-import-log').html('<span class="spinner is-active" style="float:none;"></span> Processando...');
                var reader = new FileReader();
                reader.onload = function(e){
                    $.post(ajaxurl, {
                        action: 'fcm_import_csv', 
                        data: e.target.result,
                        map_topo: mapTopo,
                        map_meio: mapMeio,
                        map_fundo: mapFundo
                    }, function(res){
                        $('#fcm-import-log').html(res);
                    });
                };
                reader.readAsText(file);
            });

            var searchTimeout;
            $('#fcm-post-search').on('keyup', function(){
                clearTimeout(searchTimeout);
                var term = $(this).val();
                if(term.length < 3) {
                    $('#fcm-search-results').hide(); return;
                }
                $('#fcm-search-spinner').addClass('is-active');
                searchTimeout = setTimeout(function(){
                    $.post(ajaxurl, {action: 'fcm_search_posts', term: term}, function(res){
                        $('#fcm-search-spinner').removeClass('is-active');
                        var html = '';
                        if(res && res.length > 0) {
                            $.each(res, function(i, item){
                                html += '<li data-id="'+item.id+'" data-title="'+item.title+'">'+item.title+'</li>';
                            });
                        } else {
                            html += '<li style="color:#999; cursor:default;">Nenhum post encontrado.</li>';
                        }
                        $('#fcm-search-results').html(html).show();
                    });
                }, 500);
            });

            $(document).on('click', '#fcm-search-results li[data-id]', function(){
                var id = $(this).data('id'); var title = $(this).data('title');
                if($('#fcm-excluded-posts-container input[value="'+id+'"]').length > 0) {
                    $('#fcm-post-search').val(''); $('#fcm-search-results').hide(); return;
                }
                var tag = '<span class="fcm-excluded-tag" style="display:inline-block; background:#fff; border:1px solid #ccc; border-radius:3px; padding:4px 8px; margin: 0 5px 5px 0; font-size:12px;">' + title + '<a href="#" class="fcm-remove-exclusion" style="color:#b32d2e; text-decoration:none; margin-left:5px; font-weight:bold;">&times;</a><input type="hidden" name="fcm_settings[excluded_posts][]" value="'+id+'"></span>';
                $('#fcm-no-exclusions').hide();
                $('#fcm-excluded-posts-container').append(tag);
                $('#fcm-post-search').val(''); $('#fcm-search-results').hide();
            });

            $(document).on('click', '.fcm-remove-exclusion', function(e){
                e.preventDefault(); $(this).parent('.fcm-excluded-tag').remove();
                if($('.fcm-excluded-tag').length === 0) $('#fcm-no-exclusions').show();
            });
            
            $(document).click(function(event) { 
                var $target = $(event.target);
                if(!$target.closest('#fcm-post-search').length && !$target.closest('#fcm-search-results').length) {
                    $('#fcm-search-results').hide();
                }        
            });
        });
        </script>
        <?php
    }

    public function handle_post_search() {
        if (!current_user_can('manage_options')) wp_die();
        $term = sanitize_text_field($_POST['term']);
        $posts = get_posts(['s' => $term, 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 10]);
        $results = [];
        foreach ($posts as $p) {
            $results[] = ['id' => $p->ID, 'title' => esc_html($p->post_title)];
        }
        wp_send_json($results);
    }

    /* -------------------------------------------------------------------------
       2. META BOX & FUNIL PADRÃO
    ---------------------------------------------------------------------------- */
    public function add_funnel_meta_box() {
        add_meta_box('fcm_meta_box', 'Estágio do Funil (CTA)', [$this, 'render_meta_box'], 'post', 'side', 'high');
    }

    public function render_meta_box($post) {
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
    }

    public function save_funnel_meta_box($post_id) {
        if (!isset($_POST['fcm_nonce']) || !wp_verify_nonce($_POST['fcm_nonce'], 'fcm_save_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['fcm_stage'])) update_post_meta($post_id, '_fcm_stage', sanitize_text_field($_POST['fcm_stage']));
    }

    /* -------------------------------------------------------------------------
       3. LÓGICA DE INJEÇÃO (OVERRIDE CUSTOM BANNERS -> FUNIL -> POSICIONAMENTO)
    ---------------------------------------------------------------------------- */
    private function is_banner_active($options, $stage) {
        if (empty($options[$stage])) return false;
        if (!empty($options[$stage . '_schedule'])) {
            $now = time();
            $start = !empty($options[$stage . '_start']) ? $this->get_utc_timestamp($options[$stage . '_start']) : 0;
            $end = !empty($options[$stage . '_end']) ? $this->get_utc_timestamp($options[$stage . '_end']) : 0;
            if ($start && $now < $start) return false;
            if ($end && $now > $end) return false;
        }
        return true;
    }

    public function generate_banner_html_from_options($options, $prefix) {
        $type_desktop = isset($options[$prefix . '_type']) ? $options[$prefix . '_type'] : 'image';
        $type_mobile = isset($options[$prefix . '_type_mobile']) ? $options[$prefix . '_type_mobile'] : $type_desktop;

        $desktop_output = '';
        if ($type_desktop === 'image') {
            $img_id = isset($options[$prefix]) ? $options[$prefix] : '';
            if ($img_id) {
                $desktop_url = wp_get_attachment_url($img_id);
                $alt_text = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                $url = isset($options[$prefix . '_url']) ? $options[$prefix . '_url'] : '#';
                $desktop_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($desktop_url), esc_attr($alt_text));
            }
        } else {
            $html = isset($options[$prefix . '_html']) ? $options[$prefix . '_html'] : '';
            if (!empty(trim($html))) {
                $desktop_output = do_shortcode($html);
            }
        }

        $mobile_output = '';
        if ($type_mobile === 'image') {
            $mobile_img_id = isset($options[$prefix . '_mobile']) ? $options[$prefix . '_mobile'] : '';
            if ($mobile_img_id) {
                $mobile_url = wp_get_attachment_url($mobile_img_id);
                $alt_text = get_post_meta($mobile_img_id, '_wp_attachment_image_alt', true);
                $url_desktop = isset($options[$prefix . '_url']) ? $options[$prefix . '_url'] : '#';
                $url = !empty($options[$prefix . '_url_mobile']) ? $options[$prefix . '_url_mobile'] : $url_desktop;
                $mobile_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($mobile_url), esc_attr($alt_text));
            } else {
                $mobile_output = $desktop_output; // fallback
            }
        } else {
            $html_mobile = isset($options[$prefix . '_html_mobile']) ? $options[$prefix . '_html_mobile'] : (isset($options[$prefix . '_html']) ? $options[$prefix . '_html'] : '');
            if (!empty(trim($html_mobile))) {
                $mobile_output = do_shortcode($html_mobile);
            }
        }

        if (empty($desktop_output) && empty($mobile_output)) return '';

        if ($desktop_output === $mobile_output) {
            return '<div class="fcm-cta-container" style="margin: 40px 0; text-align: center;">' . $desktop_output . '</div>';
        }

        $id = uniqid('fcm_');
        $final_html = '<style>
            .desktop-' . $id . ' { display: block; }
            .mobile-' . $id . ' { display: none; }
            @media (max-width: 768px) {
                .desktop-' . $id . ' { display: none !important; }
                .mobile-' . $id . ' { display: block !important; }
            }
        </style>';
        $final_html .= '<div class="fcm-cta-container" style="margin: 40px 0; text-align: center;">';
        if ($desktop_output) $final_html .= '<div class="desktop-' . $id . '">' . $desktop_output . '</div>';
        if ($mobile_output) $final_html .= '<div class="mobile-' . $id . '">' . $mobile_output . '</div>';
        $final_html .= '</div>';
        return $final_html;
    }

    public function generate_custom_banner_html($cb) {
        $type_desktop = isset($cb['type']) ? $cb['type'] : 'image';
        $type_mobile = isset($cb['type_mobile']) ? $cb['type_mobile'] : $type_desktop;

        $desktop_output = '';
        if ($type_desktop === 'image') {
            if (!empty($cb['image'])) {
                $desktop_url = wp_get_attachment_url($cb['image']);
                $alt_text = get_post_meta($cb['image'], '_wp_attachment_image_alt', true);
                $url = isset($cb['url']) ? $cb['url'] : '#';
                $desktop_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($desktop_url), esc_attr($alt_text));
            }
        } else {
            if (!empty(trim($cb['html']))) {
                $desktop_output = do_shortcode($cb['html']);
            }
        }

        $mobile_output = '';
        if ($type_mobile === 'image') {
            $mobile_img_id = isset($cb['image_mobile']) ? $cb['image_mobile'] : '';
            if ($mobile_img_id) {
                $mobile_url = wp_get_attachment_url($mobile_img_id);
                $alt_text = get_post_meta($mobile_img_id, '_wp_attachment_image_alt', true);
                $url_desktop = isset($cb['url']) ? $cb['url'] : '#';
                $url = !empty($cb['url_mobile']) ? $cb['url_mobile'] : $url_desktop;
                $mobile_output = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 100%%;"><img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block; margin: 0 auto;"></a>', esc_url($url), esc_url($mobile_url), esc_attr($alt_text));
            } else {
                $mobile_output = $desktop_output; // fallback
            }
        } else {
            $html_mobile = isset($cb['html_mobile']) ? $cb['html_mobile'] : (isset($cb['html']) ? $cb['html'] : '');
            if (!empty(trim($html_mobile))) {
                $mobile_output = do_shortcode($html_mobile);
            }
        }

        if (empty($desktop_output) && empty($mobile_output)) return '';

        if ($desktop_output === $mobile_output) {
            return '<div class="fcm-cta-container fcm-custom-cta" style="margin: 40px 0; text-align: center;">' . $desktop_output . '</div>';
        }

        $id = uniqid('fcm_cb_');
        $final_html = '<style>
            .desktop-' . $id . ' { display: block; }
            .mobile-' . $id . ' { display: none; }
            @media (max-width: 768px) {
                .desktop-' . $id . ' { display: none !important; }
                .mobile-' . $id . ' { display: block !important; }
            }
        </style>';
        $final_html .= '<div class="fcm-cta-container fcm-custom-cta" style="margin: 40px 0; text-align: center;">';
        if ($desktop_output) $final_html .= '<div class="desktop-' . $id . '">' . $desktop_output . '</div>';
        if ($mobile_output) $final_html .= '<div class="mobile-' . $id . '">' . $mobile_output . '</div>';
        $final_html .= '</div>';
        return $final_html;
    }

    public function inject_cta_via_js() {
        if (!is_single()) return;

        $post_id = get_the_ID();
        $current_url = get_permalink($post_id);
        $options = get_option($this->option_name);
        
        $final_banners = [];
        $override_blocks_others = false;

        $custom_banners = get_option($this->custom_banners_option, []);
        foreach ($custom_banners as $cb) {
            if ($cb['status'] !== 'active') continue;
            
            if (!empty($cb['schedule'])) {
                $now = time();
                $start = !empty($cb['start']) ? $this->get_utc_timestamp($cb['start']) : 0;
                $end = !empty($cb['end']) ? $this->get_utc_timestamp($cb['end']) : 0;
                if ($start && $now < $start) continue;
                if ($end && $now > $end) continue;
            }

            $targets = array_map('trim', explode("\n", $cb['targets']));
            $matched = false;
            foreach ($targets as $t) {
                if (empty($t)) continue;
                if (strpos($current_url, $t) !== false || $t == $post_id) {
                    $matched = true; break;
                }
            }
            
            if ($matched) {
                $html = $this->generate_custom_banner_html($cb);
                if ($html) {
                    $pos = isset($cb['position']) && $cb['position'] ? $cb['position'] : 'middle';
                    $pCount = isset($cb['paragraph']) ? (int)$cb['paragraph'] : 3;
                    $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'override'];
                    
                    if (empty($cb['allow_multiple'])) {
                        $override_blocks_others = true;
                    }
                }
                break;
            }
        }

        $has_stage_banner = false;

        if (!$override_blocks_others) {
            $stage = get_post_meta($post_id, '_fcm_stage', true);
            $stage_to_use = null;

            if ($stage && in_array($stage, ['topo', 'meio', 'fundo'])) {
                if ($this->is_banner_active($options, $stage)) {
                    $stage_to_use = $stage;
                } else {
                    $default_status = isset($options['default_status']) ? $options['default_status'] : 'active';
                    $excluded_posts = isset($options['excluded_posts']) ? (array)$options['excluded_posts'] : [];
                    
                    if ($default_status === 'active' && !in_array($post_id, $excluded_posts) && $this->is_banner_active($options, 'padrao')) {
                        $stage_to_use = 'padrao';
                    }
                }
            }

            if ($stage_to_use) {
                $html = $this->generate_banner_html_from_options($options, $stage_to_use);
                if ($html) {
                    $pos = isset($options[$stage_to_use . '_position']) && $options[$stage_to_use . '_position'] ? $options[$stage_to_use . '_position'] : 'middle';
                    $pCount = isset($options[$stage_to_use . '_paragraph']) ? (int)$options[$stage_to_use . '_paragraph'] : 3;
                    $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'stage'];
                    $has_stage_banner = true;
                }
            }
        }

        if (!$override_blocks_others) {
            $allow_global = !empty($options['global_allow_multiple']);
            if (!$has_stage_banner || $allow_global) {
                if ($this->is_banner_active($options, 'global')) {
                    $global_excluded = false;
                    $global_excluded_targets_str = isset($options['global_excluded_targets']) ? $options['global_excluded_targets'] : '';
                    if (!empty(trim($global_excluded_targets_str))) {
                        $ex_targets = array_filter(array_map('trim', explode("\n", $global_excluded_targets_str)));
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
        }

        if (empty($final_banners)) return;

        $custom_selectors_str = isset($options['custom_selectors']) ? $options['custom_selectors'] : '';
        $custom_selectors = [];
        if (!empty(trim($custom_selectors_str))) {
            $lines = explode("\n", $custom_selectors_str);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) $custom_selectors[] = $line;
            }
        }

        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var banners = <?php echo wp_json_encode($final_banners); ?>;
            var customSelectors = <?php echo wp_json_encode($custom_selectors); ?>;
            var selectors = [
                '#conteudo-postagem',
                '.elementor-widget-theme-post-content',
                '.elementor-widget-text-editor',
                '.blog-post',
                '.post',
                '.entry-content',
                '.post-content',
                'article'
            ].concat(customSelectors);

            var mainContainer = null;
            var maxP = 0;

            for (var i = 0; i < selectors.length; i++) {
                var els = document.querySelectorAll(selectors[i]);
                els.forEach(function(el) {
                    var pList = el.querySelectorAll('p');
                    if (pList.length > maxP) {
                        maxP = pList.length;
                        mainContainer = el;
                    }
                });
            }

            if (!mainContainer) return;

            var usedPositions = {};

            banners.forEach(function(b) {
                var posKey = b.pos === 'after_p' ? 'after_p_' + b.pCount : b.pos;
                if (usedPositions[posKey]) {
                    console.log('FCM: Banner (' + b.source + ') ocultado por conflito de posição: ' + posKey);
                    return;
                }
                usedPositions[posKey] = true;

                var div = document.createElement('div');
                div.innerHTML = b.html;
                var bannerEl = div.firstElementChild || div;

                if (b.pos === 'top') {
                    mainContainer.insertBefore(bannerEl, mainContainer.firstChild);
                    return;
                }
                if (b.pos === 'bottom') {
                    mainContainer.appendChild(bannerEl);
                    return;
                }

                var paragraphs = Array.from(mainContainer.querySelectorAll('p'));
                var total = paragraphs.length;
                
                if (total === 0) {
                    mainContainer.appendChild(bannerEl);
                    return;
                }

                var insertIndex = -1;

                if (b.pos === 'after_p') {
                    insertIndex = b.pCount - 1;
                    if (insertIndex < 0) insertIndex = 0;
                    if (insertIndex >= total) insertIndex = total - 1;
                } else { 
                    var middle = Math.floor(total / 2);
                    
                    for (var i = middle; i < total - 1; i++) {
                        var nextEl = paragraphs[i].nextElementSibling;
                        var isBadNext = nextEl && nextEl.matches && nextEl.matches('h1, h2, h3, h4, h5, h6, ul, ol, img, figure, iframe, video, audio, table, blockquote');
                        var isBadCurr = paragraphs[i].matches('h1, h2, h3, h4, h5, h6');
                        
                        if (!isBadNext && !isBadCurr) {
                            insertIndex = i;
                            break;
                        }
                    }
                    
                    if (insertIndex === -1) {
                        for (var i = middle - 1; i >= 0; i--) {
                            var nextEl = paragraphs[i].nextElementSibling;
                            var isBadNext = nextEl && nextEl.matches && nextEl.matches('h1, h2, h3, h4, h5, h6, ul, ol, img, figure, iframe, video, audio, table, blockquote');
                            var isBadCurr = paragraphs[i].matches('h1, h2, h3, h4, h5, h6');
                            
                            if (!isBadNext && !isBadCurr) {
                                insertIndex = i;
                                break;
                            }
                        }
                    }
                    if (insertIndex === -1) insertIndex = 0;
                }

                if (paragraphs[insertIndex]) {
                    paragraphs[insertIndex].parentNode.insertBefore(bannerEl, paragraphs[insertIndex].nextSibling);
                }
            });
        });
        </script>
        <?php
    }

    public function render_fcm_banner_shortcode($atts) {
        $atts = shortcode_atts(['id' => ''], $atts, 'fcm_banner');
        if (empty($atts['id'])) return '';

        $banners = get_option($this->shortcode_banners_option, []);
        if (!isset($banners[$atts['id']])) return '';

        $scb = $banners[$atts['id']];
        if ($scb['status'] !== 'active') return '';

        if (!empty($scb['schedule'])) {
            $now = time();
            $start = !empty($scb['start']) ? $this->get_utc_timestamp($scb['start']) : 0;
            $end = !empty($scb['end']) ? $this->get_utc_timestamp($scb['end']) : 0;
            if ($start && $now < $start) return '';
            if ($end && $now > $end) return '';
        }

        if ($scb['type'] === 'image') {
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
            }

            return sprintf(
                '<div class="fcm-cta-container fcm-shortcode-cta" style="margin: 20px 0; text-align: center;">
                    <a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block;">%s</a>
                </div>',
                esc_url($scb['url']),
                $img_html
            );
        } else {
            return '<div class="fcm-cta-container fcm-shortcode-cta" style="margin: 20px 0;">' . do_shortcode($scb['html']) . '</div>';
        }
    }


    private function get_banner_end_timestamp($id) {
        $custom = get_option($this->custom_banners_option, []);
        if (isset($custom[$id]) && !empty($custom[$id]['schedule']) && !empty($custom[$id]['end'])) {
            return $this->get_utc_timestamp($custom[$id]['end']);
        }
        $shortcode_banners = get_option($this->shortcode_banners_option, []);
        if (isset($shortcode_banners[$id]) && !empty($shortcode_banners[$id]['schedule']) && !empty($shortcode_banners[$id]['end'])) {
            return $this->get_utc_timestamp($shortcode_banners[$id]['end']);
        }
        return 0;
    }

    private function render_countdown_unit($atts, $unit) {
        $id = isset($atts['id']) ? sanitize_text_field($atts['id']) : '';
        if (!$id) return '00';
        $end = $this->get_banner_end_timestamp($id);
        if (!$end) return '00';
        return sprintf('<span class="fcm-countdown-el fcm-countdown-%s" data-end="%d">00</span>', esc_attr($unit), esc_attr($end));
    }

    public function render_countdown_ano($atts) { return $this->render_countdown_unit($atts, 'ano'); }
    public function render_countdown_mes($atts) { return $this->render_countdown_unit($atts, 'mes'); }
    public function render_countdown_dia($atts) { return $this->render_countdown_unit($atts, 'dia'); }
    public function render_countdown_hora($atts) { return $this->render_countdown_unit($atts, 'hora'); }
    public function render_countdown_minuto($atts) { return $this->render_countdown_unit($atts, 'minuto'); }
    public function render_countdown_segundo($atts) { return $this->render_countdown_unit($atts, 'segundo'); }

    public function render_countdown_js() {
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var els = document.querySelectorAll('.fcm-countdown-el');
            if (els.length === 0) return;

            setInterval(function() {
                var now = new Date().getTime();
                
                els.forEach(function(el) {
                    var end = parseInt(el.getAttribute('data-end')) * 1000;
                    var distance = end - now;

                    if (distance < 0) {
                        el.innerText = "00";
                        return;
                    }

                    var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    var months = Math.floor(days / 30);
                    var years = Math.floor(months / 12);
                    
                    var displayDays = days % 30;
                    var displayMonths = months % 12;

                    if (el.classList.contains('fcm-countdown-ano')) el.innerText = years.toString().padStart(2, '0');
                    if (el.classList.contains('fcm-countdown-mes')) el.innerText = displayMonths.toString().padStart(2, '0');
                    if (el.classList.contains('fcm-countdown-dia')) el.innerText = displayDays.toString().padStart(2, '0');
                    if (el.classList.contains('fcm-countdown-hora')) el.innerText = hours.toString().padStart(2, '0');
                    if (el.classList.contains('fcm-countdown-minuto')) el.innerText = minutes.toString().padStart(2, '0');
                    if (el.classList.contains('fcm-countdown-segundo')) el.innerText = seconds.toString().padStart(2, '0');
                });
            }, 1000);
        });
        </script>
        <?php
    }

    public function handle_csv_import() {
        if (!current_user_can('manage_options')) wp_die();
        $lines = explode("\n", stripslashes($_POST['data']));
        
        $map_topo = mb_strtolower(sanitize_text_field(isset($_POST['map_topo']) ? $_POST['map_topo'] : 'topo'), 'UTF-8');
        $map_meio = mb_strtolower(sanitize_text_field(isset($_POST['map_meio']) ? $_POST['map_meio'] : 'meio'), 'UTF-8');
        $map_fundo = mb_strtolower(sanitize_text_field(isset($_POST['map_fundo']) ? $_POST['map_fundo'] : 'fundo'), 'UTF-8');

        if(empty($map_topo)) $map_topo = 'topo';
        if(empty($map_meio)) $map_meio = 'meio';
        if(empty($map_fundo)) $map_fundo = 'fundo';

        $count = 0; $errors = 0;
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line);
            if (count($data) < 2) continue;
            $slug = basename(rtrim(parse_url(trim($data[0]), PHP_URL_PATH), '/'));
            $stage_raw = mb_strtolower(trim($data[1]), 'UTF-8');
            
            $stage = '';
            if ($stage_raw === $map_topo || $stage_raw === 'topo') $stage = 'topo';
            elseif ($stage_raw === $map_meio || $stage_raw === 'meio') $stage = 'meio';
            elseif ($stage_raw === $map_fundo || $stage_raw === 'fundo') $stage = 'fundo';

            $posts = get_posts(['name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'posts_per_page' => 1]);
            if ($posts && in_array($stage, ['topo', 'meio', 'fundo'])) {
                update_post_meta($posts[0]->ID, '_fcm_stage', $stage);
                $count++;
            } else {
                $errors++;
            }
        }
        printf("<div style='padding:15px; border-radius:5px; background:#d4edda; color:#155724; margin-top:10px;'><strong>Sucesso:</strong> %d posts atualizados.</div>", $count);
        if ($errors) echo "<div style='padding:15px; border-radius:5px; background:#f8d7da; color:#721c24; margin-top:10px;'><strong>Erros:</strong> $errors links não encontrados ou estágio não reconhecido.</div>";
        wp_die();
    }
}

new FunnelCTAManager();
add_action('admin_init', function(){ register_setting('fcm_settings_group', 'fcm_settings'); });