<?php
/**
 * Plugin Name: Funnel CTA Manager Pro
 * Description: Gerencia CTAs dinâmicos, banners de funil, banners personalizados e banners via shortcode com cronômetros e controle de posição.
 * Version: 3.1
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
        add_action('admin_init', [$this, 'handle_bulk_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_footer', [$this, 'admin_footer_scripts']);
        
        add_action('add_meta_boxes', [$this, 'add_funnel_meta_box']);
        add_action('save_post', [$this, 'save_funnel_meta_box']);

        add_action('wp_footer', [$this, 'inject_cta_via_js']);

        add_action('wp_ajax_fcm_import_classified', [$this, 'handle_classified_import']);
        add_action('wp_ajax_fcm_search_posts', [$this, 'handle_post_search']);
        add_action('wp_ajax_fcm_analyze_conflicts', [$this, 'handle_conflict_analysis']);
        add_action('wp_ajax_fcm_save_post_stage', [$this, 'handle_save_post_stage']);
        add_action('wp_ajax_fcm_save_post_pos', [$this, 'handle_save_post_pos']);

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

    private function render_banner_config_box($args) {
        $type = $args['type'] ?? 'image';
        $img_id = $args['img_id'] ?? '';
        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
        $url = $args['url'] ?? '';
        $html = $args['html'] ?? '';
        
        $type_name = $args['type_name'];
        $image_name = $args['image_name'];
        $url_name = $args['url_name'];
        $html_name = $args['html_name'];

        $title = $args['title'];
        $is_static = $args['is_static'] ?? false;
        $target_class = $args['target_class'] ?? ('fcm-fields-' . uniqid());
        $id_prefix = $args['id_prefix'] ?? ''; // Novo parâmetro para IDs

        ob_start();
        ?>
        <div class="fcm-banner-box <?php echo $is_static ? 'fcm-static-box' : 'fcm-random-box'; ?>" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; position: relative; display: flex; flex-direction: column; min-height: 420px; justify-content: flex-start;">
            <?php if (!$is_static): ?>
                <button type="button" class="fcm-remove-box-btn" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: #b32d2e; cursor: pointer;" title="Remover este banner">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            <?php endif; ?>
            
            <h3 style="margin-top: 0; border-bottom: 2px solid #2271b1; padding-bottom: 10px; min-height: 45px; display: flex; align-items: center;"><?php echo esc_html($title); ?></h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Tipo de Banner:</label>
                <select name="<?php echo esc_attr($type_name); ?>" id="<?php echo $id_prefix ? esc_attr($id_prefix . '_type') : ''; ?>" class="fcm-type-selector" data-target=".<?php echo esc_attr($target_class); ?>" style="width:100%;">
                    <option value="image" <?php selected($type, 'image'); ?>>Imagem</option>
                    <option value="html" <?php selected($type, 'html'); ?>>HTML / Shortcode</option>
                </select>
            </div>

            <div class="<?php echo esc_attr($target_class); ?> fcm-type-field-image" style="display: <?php echo $type === 'image' ? 'block' : 'none'; ?>; flex-grow: 1;">
                <div class="fcm-upload-wrapper" style="display: flex; flex-direction: column;">
                    <img src="<?php echo esc_url($img_url); ?>" id="<?php echo $id_prefix ? esc_attr($id_prefix . '_preview') : ''; ?>" style="width:100%; height:180px; object-fit:contain; background:#f0f0f0; display:<?php echo $img_url ? 'block' : 'none'; ?>; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                    <input type="hidden" name="<?php echo esc_attr($image_name); ?>" id="<?php echo $id_prefix ? esc_attr($id_prefix) : ''; ?>" value="<?php echo esc_attr($img_id); ?>" class="fcm-img-id">
                    <div>
                        <button type="button" class="button button-secondary fcm-upload-btn">Escolher Imagem</button>
                        <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;">Remover</button>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <label style="display:block; font-weight:600;">Link de Destino:</label>
                    <input type="url" name="<?php echo esc_attr($url_name); ?>" id="<?php echo $id_prefix ? esc_attr($id_prefix . '_url') : ''; ?>" value="<?php echo esc_url($url); ?>" style="width:100%;" placeholder="<?php echo $is_static ? 'https://exemplo.com' : 'Vazio = Usar link do banner estático'; ?>">
                </div>
            </div>

            <div class="<?php echo esc_attr($target_class); ?> fcm-type-field-html" style="display: <?php echo $type === 'html' ? 'block' : 'none'; ?>; flex-grow: 1;">
                <label style="display:block; font-weight:600;">Conteúdo HTML / Shortcode:</label>
                <textarea name="<?php echo esc_attr($html_name); ?>" id="<?php echo $id_prefix ? esc_attr($id_prefix . '_html') : ''; ?>" rows="8" style="width:100%; font-family:monospace; height: 250px;"><?php echo esc_textarea($html); ?></textarea>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
            
            $random_desktop = [];
            if (isset($_POST['cb_random_desktop']) && is_array($_POST['cb_random_desktop'])) {
                foreach ($_POST['cb_random_desktop'] as $rb) {
                    $random_desktop[] = [
                        'type' => sanitize_text_field($rb['type'] ?? 'image'),
                        'image' => sanitize_text_field($rb['image'] ?? ''),
                        'url' => sanitize_text_field($rb['url'] ?? ''),
                        'html' => wp_unslash($rb['html'] ?? '')
                    ];
                }
            }

            $random_mobile = [];
            if (isset($_POST['cb_random_mobile']) && is_array($_POST['cb_random_mobile'])) {
                foreach ($_POST['cb_random_mobile'] as $rb) {
                    $random_mobile[] = [
                        'type' => sanitize_text_field($rb['type'] ?? 'image'),
                        'image' => sanitize_text_field($rb['image'] ?? ''),
                        'url' => sanitize_text_field($rb['url'] ?? ''),
                        'html' => wp_unslash($rb['html'] ?? '')
                    ];
                }
            }

            $banners[$id] = [
                'id' => $id,
                'name' => sanitize_text_field($_POST['cb_name']),
                'type' => sanitize_text_field($_POST['cb_type']),
                'type_mobile' => isset($_POST['cb_type_mobile']) ? sanitize_text_field($_POST['cb_type_mobile']) : 'image',
                'image' => sanitize_text_field($_POST['cb_image']),
                'image_mobile' => isset($_POST['cb_image_mobile']) ? sanitize_text_field($_POST['cb_image_mobile']) : '',
                'url' => sanitize_text_field($_POST['cb_url']),
                'url_mobile' => isset($_POST['cb_url_mobile']) ? sanitize_text_field($_POST['cb_url_mobile']) : '',
                'html' => wp_unslash($_POST['cb_html']), 
                'html_mobile' => isset($_POST['cb_html_mobile']) ? wp_unslash($_POST['cb_html_mobile']) : '', 
                'random_desktop' => $random_desktop,
                'random_mobile' => $random_mobile,
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
            
            $random_desktop = [];
            if (isset($_POST['scb_random_desktop']) && is_array($_POST['scb_random_desktop'])) {
                foreach ($_POST['scb_random_desktop'] as $rb) {
                    $random_desktop[] = [
                        'type' => sanitize_text_field($rb['type'] ?? 'image'),
                        'image' => sanitize_text_field($rb['image'] ?? ''),
                        'url' => sanitize_text_field($rb['url'] ?? ''),
                        'html' => wp_unslash($rb['html'] ?? '')
                    ];
                }
            }

            $random_mobile = [];
            if (isset($_POST['scb_random_mobile']) && is_array($_POST['scb_random_mobile'])) {
                foreach ($_POST['scb_random_mobile'] as $rb) {
                    $random_mobile[] = [
                        'type' => sanitize_text_field($rb['type'] ?? 'image'),
                        'image' => sanitize_text_field($rb['image'] ?? ''),
                        'url' => sanitize_text_field($rb['url'] ?? ''),
                        'html' => wp_unslash($rb['html'] ?? '')
                    ];
                }
            }

            $banners[$id] = [
                'id' => $id,
                'name' => sanitize_text_field($_POST['scb_name']),
                'type' => sanitize_text_field($_POST['scb_type']),
                'type_mobile' => isset($_POST['scb_type_mobile']) ? sanitize_text_field($_POST['scb_type_mobile']) : 'image',
                'image' => sanitize_text_field($_POST['scb_image']),
                'image_mobile' => isset($_POST['scb_image_mobile']) ? sanitize_text_field($_POST['scb_image_mobile']) : '',
                'url' => sanitize_text_field($_POST['scb_url']),
                'url_mobile' => isset($_POST['scb_url_mobile']) ? sanitize_text_field($_POST['scb_url_mobile']) : '',
                'html' => wp_unslash($_POST['scb_html']), 
                'html_mobile' => isset($_POST['scb_html_mobile']) ? wp_unslash($_POST['scb_html_mobile']) : '', 
                'random_desktop' => $random_desktop,
                'random_mobile' => $random_mobile,
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
            <h1 style="margin-bottom: 20px;">Gerenciador de CTAs de Funil <span style="font-size:12px; background:#0073aa; color:#fff; padding:3px 8px; border-radius:10px; vertical-align:middle;">Pro v3.1</span></h1>
            
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
                        $type_mobile = isset($options[$key . '_type_mobile']) ? $options[$key . '_type_mobile'] : 'image';
                        $html_content = isset($options[$key . '_html']) ? $options[$key . '_html'] : '';
                        $html_mobile_content = isset($options[$key . '_html_mobile']) ? $options[$key . '_html_mobile'] : '';
                        $img_id = isset($options[$key]) ? $options[$key] : '';
                        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
                        $img_mobile_id = isset($options[$key . '_mobile']) ? $options[$key . '_mobile'] : '';
                        $img_mobile_url = $img_mobile_id ? wp_get_attachment_url($img_mobile_id) : '';
                        $link_url = isset($options[$key . '_url']) ? $options[$key . '_url'] : '';
                        $link_mobile_url = isset($options[$key . '_url_mobile']) ? $options[$key . '_url_mobile'] : '';
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

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                            <!-- Desktop Column -->
                            <div class="fcm-banner-column" data-column="desktop" data-key="<?php echo esc_attr($key); ?>">
                                <?php 
                                // Static Box
                                echo $this->render_banner_config_box([
                                    'title' => 'Configurações Desktop (Estático)',
                                    'is_static' => true,
                                    'type' => $type,
                                    'img_id' => $img_id,
                                    'url' => $link_url,
                                    'html' => $html_content,
                                    'type_name' => "fcm_settings[{$key}_type]",
                                    'image_name' => "fcm_settings[{$key}]",
                                    'url_name' => "fcm_settings[{$key}_url]",
                                    'html_name' => "fcm_settings[{$key}_html]",
                                    'target_class' => "fcm-desktop-fields-{$key}-static"
                                ]); 
                                ?>

                                <!-- Randomized Container -->
                                <div class="fcm-random-container">
                                    <?php 
                                    $random_desktop = isset($options[$key . '_random_desktop']) ? $options[$key . '_random_desktop'] : [];
                                    foreach ($random_desktop as $idx => $rb):
                                        echo $this->render_banner_config_box([
                                            'title' => 'Desktop Randomizado',
                                            'is_static' => false,
                                            'type' => $rb['type'] ?? 'image',
                                            'img_id' => $rb['image'] ?? '',
                                            'url' => $rb['url'] ?? '',
                                            'html' => $rb['html'] ?? '',
                                            'type_name' => "fcm_settings[{$key}_random_desktop][{$idx}][type]",
                                            'image_name' => "fcm_settings[{$key}_random_desktop][{$idx}][image]",
                                            'url_name' => "fcm_settings[{$key}_random_desktop][{$idx}][url]",
                                            'html_name' => "fcm_settings[{$key}_random_desktop][{$idx}][html]",
                                            'target_class' => "fcm-desktop-fields-{$key}-random-{$idx}"
                                        ]);
                                    endforeach;
                                    ?>
                                </div>

                                <button type="button" class="button button-secondary fcm-add-random-btn" style="width:100%; margin-top:10px;">
                                    <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> Adicionar Randomizado para Desktop
                                </button>
                            </div>

                            <!-- Mobile Column -->
                            <div class="fcm-banner-column" data-column="mobile" data-key="<?php echo esc_attr($key); ?>">
                                <?php 
                                // Static Box
                                echo $this->render_banner_config_box([
                                    'title' => 'Configurações Mobile (Estático)',
                                    'is_static' => true,
                                    'type' => $type_mobile,
                                    'img_id' => $img_mobile_id,
                                    'url' => $link_mobile_url,
                                    'html' => $html_mobile_content,
                                    'type_name' => "fcm_settings[{$key}_type_mobile]",
                                    'image_name' => "fcm_settings[{$key}_mobile]",
                                    'url_name' => "fcm_settings[{$key}_url_mobile]",
                                    'html_name' => "fcm_settings[{$key}_html_mobile]",
                                    'target_class' => "fcm-mobile-fields-{$key}-static"
                                ]); 
                                ?>

                                <!-- Randomized Container -->
                                <div class="fcm-random-container">
                                    <?php 
                                    $random_mobile = isset($options[$key . '_random_mobile']) ? $options[$key . '_random_mobile'] : [];
                                    foreach ($random_mobile as $idx => $rb):
                                        echo $this->render_banner_config_box([
                                            'title' => 'Mobile Randomizado',
                                            'is_static' => false,
                                            'type' => $rb['type'] ?? 'image',
                                            'img_id' => $rb['image'] ?? '',
                                            'url' => $rb['url'] ?? '',
                                            'html' => $rb['html'] ?? '',
                                            'type_name' => "fcm_settings[{$key}_random_mobile][{$idx}][type]",
                                            'image_name' => "fcm_settings[{$key}_random_mobile][{$idx}][image]",
                                            'url_name' => "fcm_settings[{$key}_random_mobile][{$idx}][url]",
                                            'html_name' => "fcm_settings[{$key}_random_mobile][{$idx}][html]",
                                            'target_class' => "fcm-mobile-fields-{$key}-random-{$idx}"
                                        ]);
                                    endforeach;
                                    ?>
                                </div>

                                <button type="button" class="button button-secondary fcm-add-random-btn" style="width:100%; margin-top:10px;">
                                    <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> Adicionar Randomizado para Mobile
                                </button>
                            </div>
                        </div>

                        <table class="form-table">
                            <?php if ($key === 'global'): ?>
                            <tr>
                                <th scope="row"><label for="fcm_global_allow_multiple"><strong>Exibição Simultânea</strong></label></th>
                                <td>
                                    <label style="font-weight: 600;">
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
                            <?php endif; ?>
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
                        <div>
                            <button type="button" id="btn-export-overrides" class="button"><span class="dashicons dashicons-download" style="margin-top:4px;"></span> Exportar Todos os Vínculos (CSV)</button>
                            <a href="#" class="button button-primary" id="btn-create-custom-banner">Criar Novo Override</a>
                        </div>
                    </div>
                    <p class="description">Esta lista exibe os banners de override criados. Clique em "Editar" para gerenciar os links onde o banner será exibido.</p>
                    <hr style="margin: 20px 0;">
                    
                    <form method="post" action="<?php echo admin_url('admin.php?page=funnel-cta&tab=custom-list'); ?>">
                        <?php wp_nonce_field('fcm_bulk_override_banners_nonce', 'fcm_bulk_override_banners_nonce'); ?>
                        <div class="tablenav top">
                            <div class="alignleft actions">
                                <select name="fcm_bulk_action_override_banners">
                                    <option value="-1">Ações em massa</option>
                                    <option value="delete">Excluir Banners Selecionados</option>
                                </select>
                                <input type="submit" class="button action" value="Aplicar">
                            </div>
                        </div>
                        <table class="wp-list-table widefat fixed striped" id="table-overrides-banners">
                            <thead>
                                <tr>
                                    <td class="manage-column column-cb check-column"><input type="checkbox" class="fcm-select-all" data-target=".fcm-override-banner-cb"></td>
                                    <th>Nome do Banner</th>
                                    <th>Status / Cronograma</th>
                                    <th>Total de Links</th>
                                    <th>Posição Padrão</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($custom_banners)): ?>
                                    <tr><td colspan="6">Nenhum banner de override criado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($custom_banners as $cb): 
                                        $targets = array_filter(array_map('trim', explode("\n", $cb['targets'])));
                                        $count_links = count($targets);
                                    ?>
                                        <tr>
                                            <th scope="row" class="check-column">
                                                <input type="checkbox" name="fcm_override_banner_ids[]" class="fcm-override-banner-cb" value="<?php echo esc_attr($cb['id']); ?>">
                                            </th>
                                            <td><strong><?php echo esc_html($cb['name']); ?></strong><br><small>ID: <?php echo esc_html($cb['id']); ?></small></td>
                                            <td><?php echo $this->get_banner_status_html($cb, '', true); ?></td>
                                            <td><?php echo $count_links; ?> links vinculados</td>
                                            <td><?php echo esc_html($cb['position'] ?? 'middle'); ?></td>
                                            <td>
                                                <a href="#" class="button button-small btn-edit-custom-banner" data-banner='<?php echo esc_attr(json_encode($this->enrich_banner_data($cb))); ?>'>Editar</a>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=funnel-cta&tab=custom-list&fcm_del_cb=' . $cb['id']), 'del_cb_' . $cb['id']); ?>" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm('Excluir este banner inteiro?');">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
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
                                <th scope="row"><label>Configurações de Banner</label></th>
                                <td>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <!-- Desktop Column -->
                                        <div class="fcm-banner-column" data-column="desktop" data-key="cb">
                                            <div class="fcm-static-container">
                                                <?php 
                                                echo $this->render_banner_config_box([
                                                    'title' => 'Desktop (Estático)',
                                                    'is_static' => true,
                                                    'type_name' => 'cb_type',
                                                    'image_name' => 'cb_image',
                                                    'url_name' => 'cb_url',
                                                    'html_name' => 'cb_html',
                                                    'target_class' => 'cb-desktop-fields',
                                                    'id_prefix' => 'cb_image'
                                                ]); 
                                                ?>
                                            </div>
                                            <div class="fcm-random-container">
                                                <!-- Populated via JS -->
                                            </div>
                                            <button type="button" class="button button-secondary fcm-add-random-btn" style="width:100%; margin-top:10px;">
                                                <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> Adicionar Randomizado para Desktop
                                            </button>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div class="fcm-banner-column" data-column="mobile" data-key="cb">
                                            <div class="fcm-static-container">
                                                <?php 
                                                echo $this->render_banner_config_box([
                                                    'title' => 'Mobile (Estático)',
                                                    'is_static' => true,
                                                    'type_name' => 'cb_type_mobile',
                                                    'image_name' => 'cb_image_mobile',
                                                    'url_name' => 'cb_url_mobile',
                                                    'html_name' => 'cb_html_mobile',
                                                    'target_class' => 'cb-mobile-fields',
                                                    'id_prefix' => 'cb_image_mobile'
                                                ]); 
                                                ?>
                                            </div>
                                            <div class="fcm-random-container">
                                                <!-- Populated via JS -->
                                            </div>
                                            <button type="button" class="button button-secondary fcm-add-random-btn" style="width:100%; margin-top:10px;">
                                                <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> Adicionar Randomizado para Mobile
                                            </button>
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
                                    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px;">
                                        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                            <div style="position:relative;">
                                                <input type="text" id="fcm-target-search" class="regular-text" placeholder="Pesquisar post por título ou URL...">
                                                <span class="spinner" id="fcm-target-spinner" style="position:absolute; right:-30px; top:5px;"></span>
                                                <ul id="fcm-target-results" style="display:none; position:absolute; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:100; max-height:200px; overflow-y:auto; margin:0; padding:0; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></ul>
                                            </div>
                                            <div>
                                                <button type="button" id="btn-export-targets" class="button"><span class="dashicons dashicons-download" style="margin-top:4px;"></span> Exportar CSV</button>
                                                <button type="button" id="btn-import-targets-trigger" class="button"><span class="dashicons dashicons-upload" style="margin-top:4px;"></span> Importar CSV</button>
                                                <input type="file" id="fcm-csv-targets" accept=".csv" style="display:none;">
                                            </div>
                                        </div>

                                        <table class="wp-list-table widefat fixed striped" id="table-target-links">
                                            <thead>
                                                <tr>
                                                    <td class="manage-column column-cb check-column"><input type="checkbox" class="fcm-select-all" data-target=".fcm-target-cb"></td>
                                                    <th style="width: 40%;">Título / URL</th>
                                                    <th>Posição Override</th>
                                                    <th>Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody id="fcm-target-list-body">
                                                <!-- Populated via JS -->
                                            </tbody>
                                        </table>
                                        
                                        <div class="tablenav bottom" style="margin-top: 10px;">
                                            <div class="alignleft actions">
                                                <button type="button" id="btn-bulk-remove-targets" class="button">Remover Selecionados</button>
                                            </div>
                                        </div>
                                        
                                        <input type="hidden" name="cb_targets" id="cb_targets_hidden">
                                        <p class="description">Cole as URLs ou pesquise posts. Os posts nesta lista receberão este banner, sobrescrevendo o funil.</p>
                                    </div>
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
                                           data-banner='<?php echo esc_attr(json_encode($this->enrich_banner_data($scb))); ?>'>Editar</a>
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
                                <th scope="row"><label>Configurações de Banner</label></th>
                                <td>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <!-- Desktop Column -->
                                        <div class="fcm-banner-column" data-column="desktop" data-key="scb">
                                            <div class="fcm-static-container">
                                                <?php 
                                                echo $this->render_banner_config_box([
                                                    'title' => 'Desktop (Estático)',
                                                    'is_static' => true,
                                                    'type_name' => 'scb_type',
                                                    'image_name' => 'scb_image',
                                                    'url_name' => 'scb_url',
                                                    'html_name' => 'scb_html',
                                                    'target_class' => 'scb-desktop-fields',
                                                    'id_prefix' => 'scb_image'
                                                ]); 
                                                ?>
                                            </div>
                                            <div class="fcm-random-container">
                                                <!-- Populated via JS -->
                                            </div>
                                            <button type="button" class="button button-secondary fcm-add-random-btn" style="width:100%; margin-top:10px;">
                                                <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> Adicionar Randomizado para Desktop
                                            </button>
                                        </div>

                                        <!-- Mobile Column -->
                                        <div class="fcm-banner-column" data-column="mobile" data-key="scb">
                                            <div class="fcm-static-container">
                                                <?php 
                                                echo $this->render_banner_config_box([
                                                    'title' => 'Mobile (Estático)',
                                                    'is_static' => true,
                                                    'type_name' => 'scb_type_mobile',
                                                    'image_name' => 'scb_image_mobile',
                                                    'url_name' => 'scb_url_mobile',
                                                    'html_name' => 'scb_html_mobile',
                                                    'target_class' => 'scb-mobile-fields',
                                                    'id_prefix' => 'scb_image_mobile'
                                                ]); 
                                                ?>
                                            </div>
                                            <div class="fcm-random-container">
                                                <!-- Populated via JS -->
                                            </div>
                                            <button type="button" class="button button-secondary fcm-add-random-btn" style="width:100%; margin-top:10px;">
                                                <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> Adicionar Randomizado para Mobile
                                            </button>
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


                <!-- TAB: Posts Classificados -->
                <div id="tab-list" class="tab-content" style="display: <?php echo $active_tab === 'list' ? 'block' : 'none'; ?>;">
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <h2 style="font-size: 1.3em; margin:0;">Lista de Posts com CTA</h2>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <div style="position:relative;">
                                <input type="text" id="fcm-quick-classify-search" class="regular-text" placeholder="Adicionar post à lista..." style="width:250px;">
                                <ul id="fcm-quick-results" style="display:none; position:absolute; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:100; max-height:200px; overflow-y:auto; margin:0; padding:0; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></ul>
                            </div>
                            <button type="button" id="btn-export-classified" class="button"><span class="dashicons dashicons-download" style="margin-top:4px;"></span> Exportar CSV</button>
                            <button type="button" id="btn-import-classified-trigger" class="button"><span class="dashicons dashicons-upload" style="margin-top:4px;"></span> Importar CSV</button>
                        </div>
                    </div>
                    <p class="description">Gerencie todos os posts que possuem um estágio do funil definido. Você pode remover posts em massa ou alterar suas posições individualmente.</p>
                    
                    <!-- Painel Expansível de Importação -->
                    <div id="fcm-import-classified-panel" style="display:none; margin-top: 15px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                        <h3 style="margin-top:0; font-size: 1.1em;">Importação em Massa via CSV</h3>
                        <div style="background: #f8f9fa; border-left: 4px solid #00a0d2; padding: 15px; margin-bottom: 20px;">
                            <strong>Estrutura da Planilha:</strong> O CSV não deve ter cabeçalho. As colunas devem ser:<br>
                            <code>Coluna A:</code> URL Completa ou Slug do Post<br>
                            <code>Coluna B:</code> Estágio (qualquer texto que você mapear abaixo)
                        </div>

                        <div style="display: flex; gap: 20px;">
                            <div style="flex: 1; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                                <h4 style="margin-top: 0; margin-bottom: 10px;">Mapeamento de Nomes na Planilha</h4>
                                <p class="description" style="margin-bottom: 15px;">Se a sua planilha não usa exatamente "topo", "meio" e "fundo", digite abaixo quais nomes ela usa para que o importador os reconheça.</p>
                                <div style="margin-bottom: 10px; display:flex; align-items:center;">
                                    <label style="width: 80px; font-weight: bold;">Topo =</label>
                                    <input type="text" id="fcm-map-topo" class="regular-text" placeholder="Ex: topo" value="topo">
                                </div>
                                <div style="margin-bottom: 10px; display:flex; align-items:center;">
                                    <label style="width: 80px; font-weight: bold;">Meio =</label>
                                    <input type="text" id="fcm-map-meio" class="regular-text" placeholder="Ex: meio" value="meio">
                                </div>
                                <div style="display:flex; align-items:center;">
                                    <label style="width: 80px; font-weight: bold;">Fundo =</label>
                                    <input type="text" id="fcm-map-fundo" class="regular-text" placeholder="Ex: fundo" value="fundo">
                                </div>
                            </div>
                            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                                <label style="display:block; font-weight:bold; margin-bottom:10px;">Selecione o Arquivo CSV:</label>
                                <input type="file" id="fcm-csv-classified-file" accept=".csv" style="margin-bottom: 15px;">
                                <div>
                                    <button type="button" class="button button-primary" id="btn-run-classified-import">Processar Planilha</button>
                                    <button type="button" class="button" id="btn-cancel-import-panel">Cancelar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr style="margin: 20px 0;">

                    <form method="post" action="<?php echo admin_url('admin.php?page=funnel-cta&tab=list'); ?>" id="form-bulk-classified">
                        <?php wp_nonce_field('fcm_bulk_classified_nonce', 'fcm_bulk_classified_nonce'); ?>
                        <div class="tablenav top">
                            <div class="alignleft actions">
                                <select name="fcm_bulk_action_classified">
                                    <option value="-1">Ações em massa</option>
                                    <option value="delete">Remover Estágio dos Selecionados</option>
                                </select>
                                <input type="submit" name="bulk_submit" class="button action" value="Aplicar">
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <td class="manage-column column-cb check-column"><input type="checkbox" class="fcm-select-all" data-target=".fcm-classified-cb"></td>
                                    <th style="width: 40%;">Título do Post / URL</th>
                                    <th>Estágio</th>
                                    <th>Posição Override</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $classified_posts = new WP_Query([
                                    'post_type'      => 'post',
                                    'posts_per_page' => -1,
                                    'post_status'    => 'any',
                                    'meta_query'     => [['key' => '_fcm_stage', 'compare' => 'EXISTS']]
                                ]);

                                if ($classified_posts->have_posts()) {
                                    while ($classified_posts->have_posts()) {
                                        $classified_posts->the_post();
                                        $post_id = get_the_ID();
                                        $stage = get_post_meta($post_id, '_fcm_stage', true);
                                        if(empty($stage)) continue;
                                        
                                        $pos_override = get_post_meta($post_id, '_fcm_position_override', true);
                                        $p_override = get_post_meta($post_id, '_fcm_paragraph_override', true);
                                        
                                        $colors = ['topo' => '#d1ecf1', 'meio' => '#fff3cd', 'fundo' => '#f8d7da'];
                                        $bg = isset($colors[$stage]) ? $colors[$stage] : '#eee';
                                        $stage_label = $stage;
                                        if ($stage === 'topo') $stage_label = $label_topo;
                                        if ($stage === 'meio') $stage_label = $label_meio;
                                        if ($stage === 'fundo') $stage_label = $label_fundo;

                                        $pos_text = 'Padrão';
                                        if ($pos_override) {
                                            $pos_labels = ['top' => 'Início', 'middle' => 'Meio', 'bottom' => 'Fim', 'after_p' => 'Após Pág.'];
                                            $pos_text = (isset($pos_labels[$pos_override]) ? $pos_labels[$pos_override] : $pos_override);
                                            if ($pos_override === 'after_p') $pos_text .= ' (' . ($p_override ?: 3) . ')';
                                        }

                                        echo '<tr>';
                                        echo '<th scope="row" class="check-column"><input type="checkbox" name="fcm_classified_ids[]" class="fcm-classified-cb" value="' . $post_id . '"></th>';
                                        echo '<td><strong>' . get_the_title() . '</strong><br><small><a href="' . get_permalink() . '" target="_blank">' . get_permalink() . '</a></small></td>';
                                        echo '<td><span style="background:'. $bg .'; padding: 5px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 10px;">' . esc_html($stage_label) . '</span></td>';
                                        echo '<td>' . esc_html($pos_text) . '</td>';
                                        echo '<td>';
                                        echo '<a href="#" class="button button-small btn-edit-post-pos" data-id="' . $post_id . '" data-pos="' . esc_attr($pos_override) . '" data-p="' . esc_attr($p_override) . '">Posição</a> ';
                                        echo '<a href="' . get_edit_post_link() . '" class="button button-small" target="_blank">Editar Post</a> ';
                                        echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=funnel-cta&tab=list&fcm_del_post_stage=' . $post_id), 'del_post_stage_' . $post_id) . '" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm(\'Remover este post da lista?\');">Remover</a>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    wp_reset_postdata();
                                } else {
                                    echo '<tr><td colspan="5">Nenhum post classificado até o momento.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </form>
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
            .fcm-banner-column { display: flex; flex-direction: column; }
            .fcm-banner-column .fcm-random-container { display: flex; flex-direction: column; flex-grow: 1; }
            /* Garantir que as caixas estáticas fiquem alinhadas no topo se houver diferença de conteúdo */
            .fcm-static-box { flex-shrink: 0; }
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
        <!-- Modal Posição Override -->
        <div id="fcm-pos-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:30px; border-radius:8px; width:400px; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
                <h3 style="margin-top:0;">Configurar Posição Específica</h3>
                <input type="hidden" id="fcm-pos-post-id">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Posição do Banner:</label>
                    <select id="fcm-pos-select" style="width:100%;">
                        <option value="">Padrão do Banner</option>
                        <option value="top">No Início</option>
                        <option value="middle">No Meio</option>
                        <option value="bottom">No Fim</option>
                        <option value="after_p">Após Parágrafo</option>
                    </select>
                </div>
                
                <div id="fcm-pos-p-wrapper" style="margin-bottom:15px; display:none;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Nº do Parágrafo:</label>
                    <input type="number" id="fcm-pos-p" value="3" min="1" style="width:100%;">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="button" id="btn-close-pos-modal">Cancelar</button>
                    <button type="button" class="button button-primary" id="btn-save-pos-modal">Salvar Posição</button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var mainFormTabs = ['#tab-dashboard', '#tab-topo', '#tab-meio', '#tab-fundo', '#tab-padrao', '#tab-advanced', '#tab-logs', '#tab-shortcode-list', '#tab-custom-list', '#tab-list'];

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

            // Select All logic
            $(document).on('change', '.fcm-select-all', function(){
                var target = $(this).data('target');
                $(target).prop('checked', $(this).prop('checked'));
            });

            // Position Modal
            $(document).on('click', '.btn-edit-post-pos', function(e){
                e.preventDefault();
                var id = $(this).data('id');
                var pos = $(this).data('pos') || '';
                var p = $(this).data('p') || '3';
                
                $('#fcm-pos-post-id').val(id);
                $('#fcm-pos-select').val(pos).trigger('change');
                $('#fcm-pos-p').val(p);
                $('#fcm-pos-modal').css('display', 'flex');
            });

            $('#fcm-pos-select').change(function(){
                if ($(this).val() === 'after_p') $('#fcm-pos-p-wrapper').show();
                else $('#fcm-pos-p-wrapper').hide();
            });

            $('#btn-close-pos-modal').click(function(){ $('#fcm-pos-modal').hide(); });

            $('#btn-save-pos-modal').click(function(){
                var id = $('#fcm-pos-post-id').val();
                var pos = $('#fcm-pos-select').val();
                var p = $('#fcm-pos-p').val();
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Salvando...');
                $.post(ajaxurl, {
                    action: 'fcm_save_post_pos',
                    post_id: id,
                    pos: pos,
                    p: p
                }, function(res){
                    $btn.prop('disabled', false).text('Salvar Posição');
                    $('#fcm-pos-modal').hide();
                    
                    // Se estiver no editor de override, atualiza a lista interna sem recarregar
                    if ($('#tab-custom-edit').is(':visible')) {
                        var link = targetLinks.find(function(l){ return l.post_id == id; });
                        if (link) {
                            link.pos_override = pos;
                            link.p_override = p;
                            renderTargetList();
                        }
                    } else {
                        // Se estiver nas listas principais (PHP), recarrega para mostrar a nova posição
                        location.reload();
                    }
                });
            });

            // CSV Export
            function exportToCSV(filename, tableSelector) {
                var csv = [];
                var rows = $(tableSelector + " tbody tr");
                
                rows.each(function() {
                    var row = [];
                    var url = $(this).find('.fcm-row-url a').attr('href') || $(this).find('small a').attr('href');
                    if (url) {
                        row.push('"' + url + '"');
                        
                        // Pegar estágio se existir (Posts Classificados)
                        var stage = $(this).find('td:nth-child(3) span').text().trim() || $(this).find('td:nth-child(3)').text().trim();
                        if (stage && stage !== 'Editar' && stage !== 'Remover' && stage !== 'Banner Padrão') {
                            row.push('"' + stage + '"');
                        }
                        
                        csv.push(row.join(","));
                    }
                });

                var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
                var downloadLink = document.createElement("a");
                downloadLink.download = filename;
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = "none";
                document.body.appendChild(downloadLink);
                downloadLink.click();
            }

            $('#btn-export-classified').click(function(){ exportToCSV('posts_classificados.csv', '#tab-list table'); });
            $('#btn-export-targets').click(function(){ exportToCSV('override_links.csv', '#table-target-links'); });
            $('#btn-export-overrides').click(function(){ exportToCSV('lista_completa_overrides.csv', '#table-overrides-banners'); });

            // CSV Import
            $('#btn-import-classified-trigger').click(function(){
                $('#fcm-import-classified-panel').slideToggle('fast');
            });
            $('#btn-cancel-import-panel').click(function(){
                $('#fcm-import-classified-panel').slideUp('fast');
            });

            $('#btn-run-classified-import').click(function(){
                var fileInput = $('#fcm-csv-classified-file')[0];
                var file = fileInput.files[0];
                if (!file) {
                    alert('Por favor, selecione um arquivo CSV.');
                    return;
                }
                
                var mapTopo = $('#fcm-map-topo').val() || 'topo';
                var mapMeio = $('#fcm-map-meio').val() || 'meio';
                var mapFundo = $('#fcm-map-fundo').val() || 'fundo';
                var $btn = $(this);

                $btn.prop('disabled', true).text('Processando...');
                
                var reader = new FileReader();
                reader.onload = function(e){
                    $.post(ajaxurl, {
                        action: 'fcm_import_classified',
                        data: e.target.result,
                        map_topo: mapTopo,
                        map_meio: mapMeio,
                        map_fundo: mapFundo
                    }, function(res){
                        $btn.prop('disabled', false).text('Processar Planilha');
                        if(res.success) {
                            alert(res.data + ' posts atualizados com sucesso!');
                            window.location.search = '?page=funnel-cta&tab=list';
                        } else {
                            alert('Erro na importação.');
                        }
                    });
                };
                reader.readAsText(file);
            });

            $('#btn-import-targets-trigger').click(function(){ $('#fcm-csv-targets').click(); });
            $('#btn-import-overrides-trigger').click(function(){ $('#fcm-csv-overrides').click(); });

            // Import para Targets de Override (Feito puramente em JS para não perder o estado do editor)
            $('#fcm-csv-targets').change(function(){
                var file = this.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(e){
                    var lines = e.target.result.split("\n");
                    var added = 0;
                    
                    lines.forEach(function(line){
                        if(!line.trim()) return;
                        var parts = line.split(",");
                        var url = parts[0].replace(/"/g, '').trim();
                        if (url && !targetLinks.find(function(l){ return l.url === url; })) {
                            targetLinks.push({title: 'Post Importado', url: url, post_id: null});
                            added++;
                        }
                    });
                    
                    renderTargetList();
                    alert(added + ' links adicionados à lista temporária. Salve o banner para confirmar.');
                };
                reader.readAsText(file);
                $(this).val('');
            });

            // Target Links Management (Override Form)
            var targetLinks = [];

            function renderTargetList() {
                var html = '';
                targetLinks.forEach(function(link, index) {
                    var posText = 'Banner Padrão';
                    if (link.pos_override) {
                        var posLabels = {'top': 'Início', 'middle': 'Meio', 'bottom': 'Fim', 'after_p': 'Após Pág.'};
                        posText = (posLabels[link.pos_override] || link.pos_override);
                        if (link.pos_override === 'after_p') posText += ' (' + (link.p_override || 3) + ')';
                    }

                    html += `<tr>
                        <th scope="row" class="check-column"><input type="checkbox" class="fcm-target-cb" value="${index}"></th>
                        <td><strong>${link.title}</strong><br><small class="fcm-row-url"><a href="${link.url}" target="_blank">${link.url}</a></small></td>
                        <td>${posText}</td>
                        <td>
                            <button type="button" class="button button-small btn-edit-post-pos" data-id="${link.post_id}" data-pos="${link.pos_override || ''}" data-p="${link.p_override || '3'}" ${link.post_id ? '' : 'disabled'}>Posição</button>
                            <button type="button" class="button button-small btn-remove-target" data-index="${index}">Remover</button>
                        </td>
                    </tr>`;
                });
                if (targetLinks.length === 0) {
                    html = '<tr><td colspan="4">Nenhum link adicionado.</td></tr>';
                }
                $('#fcm-target-list-body').html(html);
                $('#cb_targets_hidden').val(targetLinks.map(l => l.url).join("\n"));
            }

            $('#fcm-target-search').on('keyup', function(){
                clearTimeout(searchTimeout);
                var term = $(this).val();
                if(term.length < 3) { $('#fcm-target-results').hide(); return; }
                $('#fcm-target-spinner').addClass('is-active');
                searchTimeout = setTimeout(function(){
                    $.post(ajaxurl, {action: 'fcm_search_posts', term: term}, function(res){
                        $('#fcm-target-spinner').removeClass('is-active');
                        var html = '';
                        if(res && res.length > 0) {
                            $.each(res, function(i, item){
                                html += `<li data-id="${item.id}" data-title="${item.title}" data-url="${item.url}" data-pos="${item.pos_override || ''}" data-p="${item.p_override || ''}">${item.title}</li>`;
                            });
                        } else {
                            html += '<li style="color:#999; cursor:default;">Nenhum post encontrado.</li>';
                        }
                        $('#fcm-target-results').html(html).show();
                    });
                }, 500);
            });

            $(document).on('click', '#fcm-target-results li[data-url]', function(){
                var url = $(this).data('url');
                var title = $(this).data('title');
                var id = $(this).data('id');
                var pos = $(this).data('pos');
                var p = $(this).data('p');

                if (targetLinks.find(l => l.url === url)) {
                    $('#fcm-target-search').val(''); $('#fcm-target-results').hide(); return;
                }
                targetLinks.push({title: title, url: url, post_id: id, pos_override: pos, p_override: p});
                renderTargetList();
                $('#fcm-target-search').val(''); $('#fcm-target-results').hide();
            });

            $(document).on('click', '.btn-remove-target', function(){
                var idx = $(this).data('index');
                targetLinks.splice(idx, 1);
                renderTargetList();
            });

            $('#btn-bulk-remove-targets').click(function(){
                var selected = [];
                $('.fcm-target-cb:checked').each(function(){ selected.push(parseInt($(this).val())); });
                selected.sort((a,b) => b-a).forEach(idx => targetLinks.splice(idx, 1));
                renderTargetList();
                $('.fcm-select-all').prop('checked', false);
            });

            $(document).on('change', '.fcm-schedule-toggle', function(){
                var fields = $(this).closest('td').find('.fcm-schedule-fields');
                if ($(this).is(':checked')) {
                    fields.slideDown('fast');
                } else {
                    fields.slideUp('fast');
                }
            });

            // Position toggle (Geral)
            $(document).on('change', '.fcm-position-select', function(){
                if($(this).val() === 'after_p') {
                    $(this).siblings('.fcm-paragraph-count-wrapper').slideDown('fast');
                } else {
                    $(this).siblings('.fcm-paragraph-count-wrapper').slideUp('fast');
                }
            });

            // Universal Type Toggle
            $(document).on('change', '.fcm-type-selector', function(){
                var target = $(this).data('target');
                var type = $(this).val();
                $(target).hide();
                $(target + '.fcm-type-field-' + type).show();
            });

            // Media Uploader Delegation
            $(document).on('click', '.fcm-upload-btn', function(e){
                var btn = $(this);
                var uploader = wp.media({title: 'Escolher Imagem', button: {text: 'Usar Imagem'}, multiple: false}).on('select', function() {
                    var attachment = uploader.state().get('selection').first().toJSON();
                    var wrapper = btn.closest('.fcm-upload-wrapper');
                    wrapper.find('.fcm-preview').attr('src', attachment.url).show();
                    wrapper.find('.fcm-img-id').val(attachment.id);
                }).open();
            });

            $(document).on('click', '.fcm-remove-btn', function(){
                var wrapper = $(this).closest('.fcm-upload-wrapper');
                wrapper.find('.fcm-preview').hide();
                wrapper.find('.fcm-img-id').val('');
            });

            // Add Randomized Banner
            $('.fcm-add-random-btn').click(function(){
                var column = $(this).closest('.fcm-banner-column');
                var colType = column.data('column');
                var key = column.data('key');
                var container = column.find('.fcm-random-container');
                var uniqueIdx = Date.now(); // Unique index to avoid collisions
                
                var targetClass = 'fcm-' + key + '-' + colType + '-random-' + uniqueIdx;
                var namePrefix = '';
                
                if(key === 'cb' || key === 'scb') {
                    namePrefix = key + '_random_' + colType + '[' + uniqueIdx + ']';
                } else {
                    namePrefix = 'fcm_settings[' + key + '_random_' + colType + '][' + uniqueIdx + ']';
                }

                var html = `
                <div class="fcm-banner-box fcm-random-box" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; position: relative; display: flex; flex-direction: column; min-height: 420px; justify-content: flex-start;">
                    <button type="button" class="fcm-remove-box-btn" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: #b32d2e; cursor: pointer;" title="Remover este banner">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    <h3 style="margin-top: 0; border-bottom: 2px solid #2271b1; padding-bottom: 10px; min-height: 45px; display: flex; align-items: center;">${colType.charAt(0).toUpperCase() + colType.slice(1)} Randomizado</h3>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Tipo de Banner:</label>
                        <select name="${namePrefix}[type]" class="fcm-type-selector" data-target=".${targetClass}" style="width:100%;">
                            <option value="image" selected>Imagem</option>
                            <option value="html">HTML / Shortcode</option>
                        </select>
                    </div>
                    <div class="${targetClass} fcm-type-field-image" style="display: block; flex-grow: 1;">
                        <div class="fcm-upload-wrapper" style="display: flex; flex-direction: column;">
                            <img src="" style="width:100%; height:180px; object-fit:contain; background:#f0f0f0; display:none; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                            <input type="hidden" name="${namePrefix}[image]" value="" class="fcm-img-id">
                            <div>
                                <button type="button" class="button button-secondary fcm-upload-btn">Escolher Imagem</button>
                                <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;">Remover</button>
                            </div>
                        </div>
                        <div style="margin-top: 15px;">
                            <label style="display:block; font-weight:600;">Link de Destino:</label>
                            <input type="url" name="${namePrefix}[url]" value="" style="width:100%;" placeholder="Vazio = Usar link do banner estático">
                        </div>
                    </div>
                    <div class="${targetClass} fcm-type-field-html" style="display: none; flex-grow: 1;">
                        <label style="display:block; font-weight:600;">Conteúdo HTML / Shortcode:</label>
                        <textarea name="${namePrefix}[html]" rows="8" style="width:100%; font-family:monospace; height: 250px;"></textarea>
                    </div>
                </div>`;
                
                container.append(html);
            });

            $(document).on('click', '.fcm-remove-box-btn', function(){
                if(confirm('Remover este banner randomizado?')){
                    $(this).closest('.fcm-banner-box').remove();
                }
            });

            function populateRandomBanners(container, banners, key, colType) {
                container.empty();
                if(!banners || !banners.length) return;
                
                banners.forEach(function(rb, index){
                    var targetClass = 'fcm-' + key + '-' + colType + '-random-' + index + '-' + Date.now();
                    var namePrefix = key + '_random_' + colType + '[' + index + ']';
                    
                    var html = `
                    <div class="fcm-banner-box fcm-random-box" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; position: relative; display: flex; flex-direction: column; min-height: 420px; justify-content: flex-start;">
                        <button type="button" class="fcm-remove-box-btn" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: #b32d2e; cursor: pointer;" title="Remover">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                        <h3 style="margin-top: 0; border-bottom: 2px solid #2271b1; padding-bottom: 10px; min-height: 45px; display: flex; align-items: center;">${colType.charAt(0).toUpperCase() + colType.slice(1)} Randomizado</h3>
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px;">Tipo de Banner:</label>
                            <select name="${namePrefix}[type]" class="fcm-type-selector" data-target=".${targetClass}" style="width:100%;">
                                <option value="image" ${rb.type === 'image' ? 'selected' : ''}>Imagem</option>
                                <option value="html" ${rb.type === 'html' ? 'selected' : ''}>HTML / Shortcode</option>
                            </select>
                        </div>
                    <div class="${targetClass} fcm-type-field-image" style="display: ${rb.type === 'image' ? 'block' : 'none'}; flex-grow: 1;">
                            <div class="fcm-upload-wrapper" style="display: flex; flex-direction: column;">
                                <img src="${rb.image_url || ''}" style="width:100%; height:180px; object-fit:contain; background:#f0f0f0; display:${rb.image_url ? 'block' : 'none'}; margin-bottom:15px; border: 1px dashed #ccc; border-radius: 4px;" class="fcm-preview">
                                <input type="hidden" name="${namePrefix}[image]" value="${rb.image || ''}" class="fcm-img-id">
                                <div>
                                    <button type="button" class="button button-secondary fcm-upload-btn">Escolher Imagem</button>
                                    <button type="button" class="button fcm-remove-btn" style="color:#b32d2e;">Remover</button>
                                </div>
                            </div>
                            <div style="margin-top: 15px;">
                                <label style="display:block; font-weight:600;">Link de Destino:</label>
                                <input type="url" name="${namePrefix}[url]" value="${rb.url || ''}" style="width:100%;" placeholder="Vazio = Usar link do banner estático">
                            </div>
                        </div>
                        <div class="${targetClass} fcm-type-field-html" style="display: ${rb.type === 'html' ? 'block' : 'none'}; flex-grow: 1;">
                            <label style="display:block; font-weight:600;">Conteúdo HTML / Shortcode:</label>
                            <textarea name="${namePrefix}[html]" rows="8" style="width:100%; font-family:monospace; height: 250px;">${rb.html || ''}</textarea>
                        </div>
                    </div>`;
                    container.append(html);
                });
            }

            // ----- BANNERS DE OVERRIDE LOGIC ----- //
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

                $('.fcm-banner-column[data-key="cb"] .fcm-random-container').empty();
                
                $('#cb_schedule').prop('checked', false).trigger('change');
                $('#cb_allow_multiple').prop('checked', false);
                $('#cb_start').val('');
                $('#cb_end').val('');
                $('#cb_targets').val('');
                $('#cb_position').val('middle').trigger('change');
                $('#cb_paragraph').val('3');

                switchTab('#tab-custom-edit');
            });

            $(document).on('click', '.btn-edit-custom-banner', function(e){
                e.preventDefault();
                $('#custom-edit-title').text('Editar Banner de Override');
                var data = $(this).data('banner');
                
                $('#cb_id').val(data.id);
                $('.cb_dynamic_id').text(data.id);
                $('#cb_name').val(data.name);
                $('#cb_status').val(data.status);
                
                $('#cb_type').val(data.type || 'image').trigger('change');
                $('#cb_type_mobile').val(data.type_mobile || 'image').trigger('change');
                
                $('#cb_image').val(data.image);
                if(data.image_url) { 
                    $('#cb_image_preview').attr('src', data.image_url).show();
                } else {
                    $('#cb_image_preview').hide();
                }
                $('#cb_image_mobile').val(data.image_mobile);
                if(data.image_mobile_url) { 
                    $('#cb_image_mobile_preview').attr('src', data.image_mobile_url).show();
                } else {
                    $('#cb_image_mobile_preview').hide();
                }
                
                $('#cb_url').val(data.url);
                $('#cb_url_mobile').val(data.url_mobile);
                $('#cb_html').val(data.html);
                $('#cb_html_mobile').val(data.html_mobile);

                populateRandomBanners($('.fcm-banner-column[data-key="cb"][data-column="desktop"] .fcm-random-container'), data.random_desktop, 'cb', 'desktop');
                populateRandomBanners($('.fcm-banner-column[data-key="cb"][data-column="mobile"] .fcm-random-container'), data.random_mobile, 'cb', 'mobile');
                
                $('#cb_schedule').prop('checked', data.schedule == 1).trigger('change');
                $('#cb_allow_multiple').prop('checked', data.allow_multiple == 1);
                $('#cb_start').val(data.start);
                $('#cb_end').val(data.end);
                
                targetLinks = [];
                if (data.targets_data) {
                    targetLinks = data.targets_data;
                }
                renderTargetList();

                $('#cb_position').val(data.position || 'middle').trigger('change');
                $('#cb_paragraph').val(data.paragraph || 3);

                switchTab('#tab-custom-edit');
            });

            // ----- BANNERS SHORTCODE LOGIC ----- //
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

                $('.fcm-banner-column[data-key="scb"] .fcm-random-container').empty();
                
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
                
                $('#scb_type').val(data.type || 'image').trigger('change');
                $('#scb_type_mobile').val(data.type_mobile || 'image').trigger('change');
                
                $('#scb_image').val(data.image);
                if(data.image_url) { 
                    $('#scb_image_preview').attr('src', data.image_url).show();
                } else {
                    $('#scb_image_preview').hide();
                }
                $('#scb_image_mobile').val(data.image_mobile);
                if(data.image_mobile_url) { 
                    $('#scb_image_mobile_preview').attr('src', data.image_mobile_url).show();
                } else {
                    $('#scb_image_mobile_preview').hide();
                }
                
                $('#scb_url').val(data.url);
                $('#scb_url_mobile').val(data.url_mobile);
                $('#scb_html').val(data.html);
                $('#scb_html_mobile').val(data.html_mobile);

                populateRandomBanners($('.fcm-banner-column[data-key="scb"][data-column="desktop"] .fcm-random-container'), data.random_desktop, 'scb', 'desktop');
                populateRandomBanners($('.fcm-banner-column[data-key="scb"][data-column="mobile"] .fcm-random-container'), data.random_mobile, 'scb', 'mobile');
                
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
            
            $('#fcm-quick-classify-search').on('keyup', function(){
                clearTimeout(searchTimeout);
                var term = $(this).val();
                if(term.length < 3) { $('#fcm-quick-results').hide(); return; }
                searchTimeout = setTimeout(function(){
                    $.post(ajaxurl, {action: 'fcm_search_posts', term: term}, function(res){
                        var html = '';
                        if(res && res.length > 0) {
                            $.each(res, function(i, item){
                                html += `<li data-id="${item.id}" data-title="${item.title}">${item.title}</li>`;
                            });
                        } else {
                            html += '<li style="color:#999; cursor:default;">Nenhum post encontrado.</li>';
                        }
                        $('#fcm-quick-results').html(html).show();
                    });
                }, 500);
            });

            $(document).on('click', '#fcm-quick-results li[data-id]', function(){
                var id = $(this).data('id');
                var stage = prompt('Digite o estágio (topo, meio, fundo):', 'topo');
                if (stage && ['topo', 'meio', 'fundo'].indexOf(stage.toLowerCase()) !== -1) {
                    $.post(ajaxurl, {action: 'fcm_save_post_stage', post_id: id, stage: stage.toLowerCase()}, function(){
                        location.reload();
                    });
                } else if (stage) {
                    alert('Estágio inválido. Use topo, meio ou fundo.');
                }
                $('#fcm-quick-classify-search').val(''); $('#fcm-quick-results').hide();
            });

            $(document).click(function(event) { 
                var $target = $(event.target);
                if(!$target.closest('#fcm-target-search').length && !$target.closest('#fcm-target-results').length) {
                    $('#fcm-target-results').hide();
                }        
                if(!$target.closest('#fcm-quick-classify-search').length && !$target.closest('#fcm-quick-results').length) {
                    $('#fcm-quick-results').hide();
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
            $results[] = [
                'id' => $p->ID, 
                'title' => esc_html($p->post_title),
                'url' => get_permalink($p->ID),
                'pos_override' => get_post_meta($p->ID, '_fcm_position_override', true),
                'p_override' => get_post_meta($p->ID, '_fcm_paragraph_override', true)
            ];
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

    private function is_banner_valid($b) {
        if (!isset($b['type'])) return false;
        if ($b['type'] === 'image' && !empty($b['image'])) return true;
        if ($b['type'] === 'html' && !empty(trim($b['html']))) return true;
        return false;
    }

    private function enrich_banner_data($b) {
        if (!empty($b['image'])) {
            $b['image_url'] = wp_get_attachment_url($b['image']);
        }
        if (!empty($b['image_mobile'])) {
            $b['image_mobile_url'] = wp_get_attachment_url($b['image_mobile']);
        }
        
        if (isset($b['random_desktop']) && is_array($b['random_desktop'])) {
            foreach ($b['random_desktop'] as &$rb) {
                if (!empty($rb['image'])) {
                    $rb['image_url'] = wp_get_attachment_url($rb['image']);
                }
            }
        }
        if (isset($b['random_mobile']) && is_array($b['random_mobile'])) {
            foreach ($b['random_mobile'] as &$rb) {
                if (!empty($rb['image'])) {
                    $rb['image_url'] = wp_get_attachment_url($rb['image']);
                }
            }
        }
        if (isset($b['targets']) && !empty($b['targets'])) {
            $urls = array_filter(array_map('trim', explode("\n", $b['targets'])));
            $b['targets_data'] = [];
            foreach ($urls as $u) {
                $pid = url_to_postid($u);
                $b['targets_data'][] = [
                    'url' => $u,
                    'title' => $pid ? get_the_title($pid) : 'Post / URL',
                    'post_id' => $pid,
                    'pos_override' => $pid ? get_post_meta($pid, '_fcm_position_override', true) : '',
                    'p_override' => $pid ? get_post_meta($pid, '_fcm_paragraph_override', true) : ''
                ];
            }
        }

        return $b;
    }

    private function get_random_banner($random_banners, $static_banner) {
        $pool = [];
        
        if ($this->is_banner_valid($static_banner)) {
            $pool[] = $static_banner;
        }
        
        if (is_array($random_banners) && !empty($random_banners)) {
            foreach ($random_banners as $rb) {
                if ($this->is_banner_valid($rb)) {
                    // Fallback de link para o estático se o randomizado estiver vazio
                    if (empty($rb['url']) && !empty($static_banner['url'])) {
                        $rb['url'] = $static_banner['url'];
                    }
                    $pool[] = $rb;
                }
            }
        }
        
        if (empty($pool)) return null;
        
        // Se houver apenas um, retorna ele
        if (count($pool) === 1) return $pool[0];

        // Embaralhar o pool para garantir aleatoriedade real
        shuffle($pool);
        
        // Retornar um item aleatório
        return $pool[array_rand($pool)];
    }

    public function generate_banner_html_from_options($options, $prefix) {
        // Preparar Banners Estáticos
        $static_desktop = [
            'type' => $options[$prefix . '_type'] ?? 'image',
            'image' => $options[$prefix] ?? '',
            'url' => $options[$prefix . '_url'] ?? '',
            'html' => $options[$prefix . '_html'] ?? ''
        ];
        $static_mobile = [
            'type' => $options[$prefix . '_type_mobile'] ?? 'image',
            'image' => $options[$prefix . '_mobile'] ?? '',
            'url' => $options[$prefix . '_url_mobile'] ?? '',
            'html' => $options[$prefix . '_html_mobile'] ?? ''
        ];

        // Randomizados
        $random_desktop = $options[$prefix . '_random_desktop'] ?? [];
        $random_mobile = $options[$prefix . '_random_mobile'] ?? [];

        // Escolher
        $picked_desktop = $this->get_random_banner($random_desktop, $static_desktop);
        $picked_mobile = $this->get_random_banner($random_mobile, $static_mobile);

        // Fallback: Se mobile estiver vazio, tenta o desktop estático
        if (!$picked_mobile && $this->is_banner_valid($static_desktop)) {
            $picked_mobile = $static_desktop;
        }

        if (!$picked_desktop && !$picked_mobile) return '';

        $html_desktop = '';
        $html_mobile = '';

        if ($picked_desktop) {
            if ($picked_desktop['type'] === 'image') {
                $img_url = wp_get_attachment_url($picked_desktop['image']);
                $alt = get_post_meta($picked_desktop['image'], '_wp_attachment_image_alt', true);
                $url = !empty($picked_desktop['url']) ? $picked_desktop['url'] : '#';
                $html_desktop = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" style="max-width:100%%; height:auto; display:block; margin:0 auto;"></a>', esc_url($url), esc_url($img_url), esc_attr($alt));
            } else {
                $html_desktop = do_shortcode($picked_desktop['html'] ?? '');
            }
        }

        if ($picked_mobile) {
            if ($picked_mobile['type'] === 'image') {
                $img_url = wp_get_attachment_url($picked_mobile['image']);
                $alt = get_post_meta($picked_mobile['image'], '_wp_attachment_image_alt', true);
                $url = !empty($picked_mobile['url']) ? $picked_mobile['url'] : '#';
                $html_mobile = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" style="max-width:100%%; height:auto; display:block; margin:0 auto;"></a>', esc_url($url), esc_url($img_url), esc_attr($alt));
            } else {
                $html_mobile = do_shortcode($picked_mobile['html'] ?? '');
            }
        }

        $out = '<div class="fcm-cta-container" style="margin: 40px 0; text-align: center;">';
        if ($html_desktop === $html_mobile) {
            $out .= $html_desktop;
        } else {
            if (!empty($html_desktop)) $out .= '<div class="fcm-desktop-only">' . $html_desktop . '</div>';
            if (!empty($html_mobile)) $out .= '<div class="fcm-mobile-only">' . $html_mobile . '</div>';
            $out .= '<style>
                @media (max-width: 768px) { .fcm-desktop-only { display: none !important; } .fcm-mobile-only { display: block !important; } }
                @media (min-width: 769px) { .fcm-desktop-only { display: block !important; } .fcm-mobile-only { display: none !important; } }
            </style>';
        }
        $out .= '</div>';
        return $out;
    }

    public function generate_custom_banner_html($cb) {
        // Preparar Estáticos
        $static_desktop = [
            'type' => $cb['type'] ?? 'image',
            'image' => $cb['image'] ?? '',
            'url' => $cb['url'] ?? '',
            'html' => $cb['html'] ?? ''
        ];
        $static_mobile = [
            'type' => $cb['type_mobile'] ?? 'image',
            'image' => $cb['image_mobile'] ?? '',
            'url' => $cb['url_mobile'] ?? '',
            'html' => $cb['html_mobile'] ?? ''
        ];

        // Randomizados
        $random_desktop = $cb['random_desktop'] ?? [];
        $random_mobile = $cb['random_mobile'] ?? [];

        // Escolher
        $picked_desktop = $this->get_random_banner($random_desktop, $static_desktop);
        $picked_mobile = $this->get_random_banner($random_mobile, $static_mobile);

        // Fallback Mobile
        if (!$picked_mobile && $this->is_banner_valid($static_desktop)) {
            $picked_mobile = $static_desktop;
        }

        if (!$picked_desktop && !$picked_mobile) return '';

        $html_desktop = '';
        $html_mobile = '';

        if ($picked_desktop) {
            if ($picked_desktop['type'] === 'image') {
                $img_url = wp_get_attachment_url($picked_desktop['image']);
                $alt = get_post_meta($picked_desktop['image'], '_wp_attachment_image_alt', true);
                $url = !empty($picked_desktop['url']) ? $picked_desktop['url'] : '#';
                $html_desktop = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" style="max-width:100%%; height:auto; display:block; margin:0 auto;"></a>', esc_url($url), esc_url($img_url), esc_attr($alt));
            } else {
                $html_desktop = do_shortcode($picked_desktop['html'] ?? '');
            }
        }

        if ($picked_mobile) {
            if ($picked_mobile['type'] === 'image') {
                $img_url = wp_get_attachment_url($picked_mobile['image']);
                $alt = get_post_meta($picked_mobile['image'], '_wp_attachment_image_alt', true);
                $url = !empty($picked_mobile['url']) ? $picked_mobile['url'] : '#';
                $html_mobile = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" style="max-width:100%%; height:auto; display:block; margin:0 auto;"></a>', esc_url($url), esc_url($img_url), esc_attr($alt));
            } else {
                $html_mobile = do_shortcode($picked_mobile['html'] ?? '');
            }
        }

        $out = '<div class="fcm-cta-container fcm-custom-cta" style="margin: 40px 0; text-align: center;">';
        if ($html_desktop === $html_mobile) {
            $out .= $html_desktop;
        } else {
            if (!empty($html_desktop)) $out .= '<div class="fcm-desktop-only">' . $html_desktop . '</div>';
            if (!empty($html_mobile)) $out .= '<div class="fcm-mobile-only">' . $html_mobile . '</div>';
            $out .= '<style>
                @media (max-width: 768px) { .fcm-desktop-only { display: none !important; } .fcm-mobile-only { display: block !important; } }
                @media (min-width: 769px) { .fcm-desktop-only { display: block !important; } .fcm-mobile-only { display: none !important; } }
            </style>';
        }
        $out .= '</div>';
        return $out;
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
                    $pos = get_post_meta($post_id, '_fcm_position_override', true);
                    if (!$pos) $pos = isset($cb['position']) && $cb['position'] ? $cb['position'] : 'middle';
                    
                    $pCount = get_post_meta($post_id, '_fcm_paragraph_override', true);
                    if (!$pCount) $pCount = isset($cb['paragraph']) ? (int)$cb['paragraph'] : 3;
                    
                    $final_banners[] = ['html' => $html, 'pos' => $pos, 'pCount' => $pCount, 'source' => 'override'];
                    
                    if (empty($cb['allow_multiple'])) {
                        $override_blocks_others = true;
                        break; // Só para se o banner explicitamente bloquear outros
                    }
                }
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
                    $pos = get_post_meta($post_id, '_fcm_position_override', true);
                    if (!$pos) $pos = isset($options[$stage_to_use . '_position']) && $options[$stage_to_use . '_position'] ? $options[$stage_to_use . '_position'] : 'middle';
                    
                    $pCount = get_post_meta($post_id, '_fcm_paragraph_override', true);
                    if (!$pCount) $pCount = isset($options[$stage_to_use . '_paragraph']) ? (int)$options[$stage_to_use . '_paragraph'] : 3;
                    
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
                            $pos = get_post_meta($post_id, '_fcm_position_override', true);
                            if (!$pos) $pos = isset($options['global_position']) && $options['global_position'] ? $options['global_position'] : 'middle';
                            
                            $pCount = get_post_meta($post_id, '_fcm_paragraph_override', true);
                            if (!$pCount) $pCount = isset($options['global_paragraph']) ? (int)$options['global_paragraph'] : 3;
                            
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

            // 1. Prioridade absoluta para Selectores Customizados
            if (customSelectors.length > 0) {
                for (var i = 0; i < customSelectors.length; i++) {
                    var el = document.querySelector(customSelectors[i]);
                    if (el) {
                        mainContainer = el;
                        break; // Se achou um customizado, usa ele imediatamente
                    }
                }
            }

            // 2. Fallback para Selectores Padrão se não achou customizado
            if (!mainContainer) {
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
                    // Se houver um parágrafo, insere antes do primeiro parágrafo
                    // Se não houver, insere no início do container
                    var firstP = mainContainer.querySelector('p');
                    if (firstP) {
                        firstP.parentNode.insertBefore(bannerEl, firstP);
                    } else {
                        mainContainer.insertBefore(bannerEl, mainContainer.firstChild);
                    }
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

        // Preparar Estáticos
        $static_desktop = [
            'type' => $scb['type'] ?? 'image',
            'image' => $scb['image'] ?? '',
            'url' => $scb['url'] ?? '',
            'html' => $scb['html'] ?? ''
        ];
        $static_mobile = [
            'type' => $scb['type_mobile'] ?? 'image',
            'image' => $scb['image_mobile'] ?? '',
            'url' => $scb['url_mobile'] ?? '',
            'html' => $scb['html_mobile'] ?? ''
        ];

        // Randomizados
        $random_desktop = $scb['random_desktop'] ?? [];
        $random_mobile = $scb['random_mobile'] ?? [];

        // Escolher
        $picked_desktop = $this->get_random_banner($random_desktop, $static_desktop);
        $picked_mobile = $this->get_random_banner($random_mobile, $static_mobile);

        // Fallback Mobile
        if (!$picked_mobile && $this->is_banner_valid($static_desktop)) {
            $picked_mobile = $static_desktop;
        }

        if (!$picked_desktop && !$picked_mobile) return '';

        $html_desktop = '';
        $html_mobile = '';

        if ($picked_desktop) {
            if ($picked_desktop['type'] === 'image') {
                $img_url = wp_get_attachment_url($picked_desktop['image']);
                $alt = get_post_meta($picked_desktop['image'], '_wp_attachment_image_alt', true);
                $url = !empty($picked_desktop['url']) ? $picked_desktop['url'] : '#';
                $html_desktop = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" style="max-width:100%%; height:auto; display:block; margin:0 auto;"></a>', esc_url($url), esc_url($img_url), esc_attr($alt));
            } else {
                $html_desktop = do_shortcode($picked_desktop['html'] ?? '');
            }
        }

        if ($picked_mobile) {
            if ($picked_mobile['type'] === 'image') {
                $img_url = wp_get_attachment_url($picked_mobile['image']);
                $alt = get_post_meta($picked_mobile['image'], '_wp_attachment_image_alt', true);
                $url = !empty($picked_mobile['url']) ? $picked_mobile['url'] : '#';
                $html_mobile = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" style="max-width:100%%; height:auto; display:block; margin:0 auto;"></a>', esc_url($url), esc_url($img_url), esc_attr($alt));
            } else {
                $html_mobile = do_shortcode($picked_mobile['html'] ?? '');
            }
        }

        $out = '<div class="fcm-cta-container fcm-shortcode-cta" style="margin: 20px 0; text-align: center;">';
        if ($html_desktop === $html_mobile) {
            $out .= $html_desktop;
        } else {
            if (!empty($html_desktop)) $out .= '<div class="fcm-desktop-only">' . $html_desktop . '</div>';
            if (!empty($html_mobile)) $out .= '<div class="fcm-mobile-only">' . $html_mobile . '</div>';
            $out .= '<style>
                @media (max-width: 768px) { .fcm-desktop-only { display: none !important; } .fcm-mobile-only { display: block !important; } }
                @media (min-width: 769px) { .fcm-desktop-only { display: block !important; } .fcm-mobile-only { display: none !important; } }
            </style>';
        }
        $out .= '</div>';
        return $out;
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

    public function handle_bulk_actions() {
        if (!current_user_can('manage_options')) return;

        // Bulk Classified Posts
        if (isset($_POST['fcm_bulk_action_classified']) && $_POST['fcm_bulk_action_classified'] === 'delete' && isset($_POST['fcm_classified_ids'])) {
            check_admin_referer('fcm_bulk_classified_nonce', 'fcm_bulk_classified_nonce');
            foreach ($_POST['fcm_classified_ids'] as $post_id) {
                delete_post_meta((int)$post_id, '_fcm_stage');
            }
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=list&msg=bulk_deleted'));
            exit;
        }

        // Single delete classified
        if (isset($_GET['fcm_del_post_stage']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'del_post_stage_' . $_GET['fcm_del_post_stage'])) {
            delete_post_meta((int)$_GET['fcm_del_post_stage'], '_fcm_stage');
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=list&msg=deleted'));
            exit;
        }

        // Bulk Override Banners (Delete Banners)
        if (isset($_POST['fcm_bulk_action_override_banners']) && $_POST['fcm_bulk_action_override_banners'] === 'delete' && isset($_POST['fcm_override_banner_ids'])) {
            check_admin_referer('fcm_bulk_override_banners_nonce', 'fcm_bulk_override_banners_nonce');
            $banners = get_option($this->custom_banners_option, []);
            foreach ($_POST['fcm_override_banner_ids'] as $b_id) {
                if (isset($banners[$b_id])) unset($banners[$b_id]);
            }
            update_option($this->custom_banners_option, $banners);
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=custom-list&msg=bulk_deleted'));
            exit;
        }

        // Bulk Override Links (This is now less common from the main list but kept for compatibility if needed)
        if (isset($_POST['fcm_bulk_action_override']) && $_POST['fcm_bulk_action_override'] === 'delete_links' && isset($_POST['fcm_override_links'])) {
            check_admin_referer('fcm_bulk_override_nonce', 'fcm_bulk_override_nonce');
            $banners = get_option($this->custom_banners_option, []);
            
            $to_remove = [];
            foreach ($_POST['fcm_override_links'] as $item) {
                $parts = explode('|', $item);
                if (count($parts) < 2) continue;
                list($b_id, $idx) = $parts;
                $to_remove[$b_id][] = (int)$idx;
            }

            foreach ($to_remove as $b_id => $indices) {
                if (isset($banners[$b_id])) {
                    $targets = array_filter(array_map('trim', explode("\n", $banners[$b_id]['targets'])));
                    rsort($indices); // Remove from end to keep indices valid
                    foreach ($indices as $idx) {
                        if (isset($targets[$idx])) unset($targets[$idx]);
                    }
                    $banners[$b_id]['targets'] = implode("\n", $targets);
                }
            }
            update_option($this->custom_banners_option, $banners);
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=custom-list&msg=links_removed'));
            exit;
        }

        // Single delete override link
        if (isset($_GET['fcm_del_link']) && isset($_GET['idx']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'del_link_' . $_GET['fcm_del_link'])) {
            $banners = get_option($this->custom_banners_option, []);
            $b_id = sanitize_text_field($_GET['fcm_del_link']);
            $idx = (int)$_GET['idx'];
            if (isset($banners[$b_id])) {
                $targets = array_filter(array_map('trim', explode("\n", $banners[$b_id]['targets'])));
                if (isset($targets[$idx])) {
                    $targets = array_values($targets); // Re-index
                    unset($targets[$idx]);
                    $banners[$b_id]['targets'] = implode("\n", $targets);
                    update_option($this->custom_banners_option, $banners);
                }
            }
            wp_redirect(admin_url('admin.php?page=funnel-cta&tab=custom-list&msg=link_removed'));
            exit;
        }
    }

    public function handle_save_post_stage() {
        if (!current_user_can('manage_options')) wp_die();
        $post_id = (int)$_POST['post_id'];
        $stage = sanitize_text_field($_POST['stage']);
        if ($stage) update_post_meta($post_id, '_fcm_stage', $stage);
        else delete_post_meta($post_id, '_fcm_stage');
        wp_send_json_success();
    }

    public function handle_save_post_pos() {
        if (!current_user_can('manage_options')) wp_die();
        $post_id = (int)$_POST['post_id'];
        $pos = sanitize_text_field($_POST['pos']);
        $p = (int)$_POST['p'];
        
        if ($pos) {
            update_post_meta($post_id, '_fcm_position_override', $pos);
            update_post_meta($post_id, '_fcm_paragraph_override', $p);
        } else {
            delete_post_meta($post_id, '_fcm_position_override');
            delete_post_meta($post_id, '_fcm_paragraph_override');
        }
        wp_send_json_success();
    }

    public function handle_classified_import() {
        if (!current_user_can('manage_options')) wp_die();
        $lines = explode("\n", stripslashes($_POST['data']));
        
        $map_topo = mb_strtolower(sanitize_text_field(isset($_POST['map_topo']) ? $_POST['map_topo'] : 'topo'), 'UTF-8');
        $map_meio = mb_strtolower(sanitize_text_field(isset($_POST['map_meio']) ? $_POST['map_meio'] : 'meio'), 'UTF-8');
        $map_fundo = mb_strtolower(sanitize_text_field(isset($_POST['map_fundo']) ? $_POST['map_fundo'] : 'fundo'), 'UTF-8');

        $count = 0;
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) < 2) continue;
            $slug = basename(rtrim(parse_url(trim($data[0]), PHP_URL_PATH), '/'));
            $csv_stage = mb_strtolower(trim($data[1]), 'UTF-8');
            
            $stage = '';
            if ($csv_stage === $map_topo || $csv_stage === 'topo') $stage = 'topo';
            elseif ($csv_stage === $map_meio || $csv_stage === 'meio') $stage = 'meio';
            elseif ($csv_stage === $map_fundo || $csv_stage === 'fundo') $stage = 'fundo';

            if (!$stage) continue;

            $posts = get_posts(['name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'posts_per_page' => 1]);
            if ($posts) {
                update_post_meta($posts[0]->ID, '_fcm_stage', $stage);
                $count++;
            }
        }
        wp_send_json_success($count);
    }
}

new FunnelCTAManager();
add_action('admin_init', function(){ register_setting('fcm_settings_group', 'fcm_settings'); });