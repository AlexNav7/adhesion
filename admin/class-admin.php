<?php
/**
 * Clase principal del administrador
 * 
 * Esta clase maneja todo el backend del plugin:
 * - Menús de administración
 * - Páginas de configuración
 * - Dashboard del plugin
 * - Integración con WordPress admin
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Admin {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Slug del menú principal
     */
    private $menu_slug = 'adhesion';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
        $this->load_admin_classes();
    }
    
    /**
     * Inicializar hooks del admin
     */
    private function init_hooks() {
        // Menús de administración
        add_action('admin_menu', array($this, 'add_admin_menus'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Columnas personalizadas en listados de usuarios
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_action('manage_users_custom_column', array($this, 'show_user_column_content'), 10, 3);
        
        // Notices del admin
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Enlaces de acción en la página de plugins
        add_filter('plugin_action_links_' . ADHESION_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // Meta links en la página de plugins
        add_filter('plugin_row_meta', array($this, 'add_plugin_meta_links'), 10, 2);
        
        // Inicialización del admin
        add_action('admin_init', array($this, 'admin_init'));

        // Campos personalizados en perfiles de usuario
        add_action('show_user_profile', array($this, 'show_extra_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'show_extra_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_extra_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_extra_user_profile_fields'));

    }
    
    /**
     * Cargar clases específicas del admin
     */
    private function load_admin_classes() {
        // Cargar dependencias de WP_List_Table
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        if (!function_exists('convert_to_screen')) {
            require_once(ABSPATH . 'wp-admin/includes/screen.php');
        }


        require_once ADHESION_PLUGIN_PATH . 'admin/class-settings.php';
        require_once ADHESION_PLUGIN_PATH . 'admin/class-documents.php';
        require_once ADHESION_PLUGIN_PATH . 'admin/class-users-list.php';
        require_once ADHESION_PLUGIN_PATH . 'admin/class-contracts-list.php';
        require_once ADHESION_PLUGIN_PATH . 'admin/class-calculations-list.php';
        
        // // Inicializar solo cuando sea necesario
        // if (isset($_GET['page']) && strpos($_GET['page'], 'adhesion') === 0) {
        //     new Adhesion_Settings();
        //     new Adhesion_Documents();
        //     new Adhesion_Users_List();
        //     new Adhesion_Contracts_List();
        //     new Adhesion_Calculations_List();
        // }
    }
    
    /**
     * Agregar menús de administración
     */
    public function add_admin_menus() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Menú principal
        add_menu_page(
            __('Adhesión', 'adhesion'),                    // Título de la página
            __('Adhesión', 'adhesion'),                    // Título del menú
            'manage_options',                              // Capacidad requerida
            $this->menu_slug,                              // Slug del menú
            array($this, 'display_dashboard'),             // Función callback
            'dashicons-clipboard',                         // Icono
            30                                             // Posición
        );
        
        // Submenú: Dashboard
        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'adhesion'),
            __('Dashboard', 'adhesion'),
            'manage_options',
            $this->menu_slug,
            array($this, 'display_dashboard')
        );
        
        // Submenú: Cálculos
        add_submenu_page(
            $this->menu_slug,
            __('Cálculos de Presupuestos', 'adhesion'),
            __('Cálculos', 'adhesion'),
            'manage_options',
            'adhesion-calculations',
            array($this, 'display_calculations')
        );
        
        // Submenú: Contratos
        add_submenu_page(
            $this->menu_slug,
            __('Contratos de Adhesión', 'adhesion'),
            __('Contratos', 'adhesion'),
            'manage_options',
            'adhesion-contracts',
            array($this, 'display_contracts')
        );
        
        // Submenú: Usuarios
        add_submenu_page(
            $this->menu_slug,
            __('Usuarios Adheridos', 'adhesion'),
            __('Usuarios', 'adhesion'),
            'manage_options',
            'adhesion-users',
            array($this, 'display_users')
        );
        
        // Submenú: Documentos
        add_submenu_page(
            $this->menu_slug,
            __('Gestión de Documentos', 'adhesion'),
            __('Documentos', 'adhesion'),
            'manage_options',
            'adhesion-documents',
            array($this, 'display_documents')
        );
        
        // Submenú: Configuración
        add_submenu_page(
            $this->menu_slug,
            __('Configuración de Adhesión', 'adhesion'),
            __('Configuración', 'adhesion'),
            'manage_options',
            'adhesion-settings',
            array($this, 'display_settings')
        );
    }
    
    /**
     * Mostrar dashboard principal
     */
    public function display_dashboard() {
        // Obtener estadísticas
        $stats = $this->db->get_basic_stats();
        $recent_stats = $this->db->get_period_stats(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );
        
        // Obtener últimos cálculos y contratos
        $recent_calculations = $this->db->get_all_calculations(5, 0);
        $recent_contracts = $this->db->get_all_calculations(5, 0, array()); // Obtener contratos recientes
        
        // Verificar configuración
        $config_status = array(
            'redsys' => adhesion_is_redsys_configured(),
            'docusign' => adhesion_is_docusign_configured()
        );
        
        include ADHESION_PLUGIN_PATH . 'admin/partials/dashboard-display.php';
    }
    
    /**
     * Mostrar página de cálculos
     */
    public function display_calculations() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/calculations-display.php';
    }
    
    /**
     * Mostrar página de contratos
     */
    public function display_contracts() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/contracts-display.php';
    }
    
    /**
     * Mostrar página de usuarios
     */
    public function display_users() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/users-display.php';
    }
    
    /**
     * Mostrar página de documentos
     */
    public function display_documents() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/documents-display.php';
    }
    
    /**
     * Mostrar página de configuración
     */
    public function display_settings() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/settings-display.php';
    }
    
    /**
     * Agregar widgets al dashboard de WordPress
     */
    public function add_dashboard_widgets() {
        // Solo para administradores
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'adhesion_stats_widget',
            __('Estadísticas de Adhesión', 'adhesion'),
            array($this, 'dashboard_stats_widget')
        );
        
        wp_add_dashboard_widget(
            'adhesion_recent_activity',
            __('Actividad Reciente - Adhesión', 'adhesion'),
            array($this, 'dashboard_recent_activity_widget')
        );
    }
    
    /**
     * Widget de estadísticas en el dashboard
     */
    public function dashboard_stats_widget() {
        $stats = $this->db->get_basic_stats();
        ?>
        <div class="adhesion-dashboard-widget">
            <div class="adhesion-stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html($stats['total_calculations']); ?></span>
                    <span class="stat-label"><?php _e('Cálculos Totales', 'adhesion'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html($stats['total_contracts']); ?></span>
                    <span class="stat-label"><?php _e('Contratos Totales', 'adhesion'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html($stats['signed_contracts']); ?></span>
                    <span class="stat-label"><?php _e('Contratos Firmados', 'adhesion'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo adhesion_format_price($stats['total_revenue']); ?></span>
                    <span class="stat-label"><?php _e('Ingresos Totales', 'adhesion'); ?></span>
                </div>
            </div>
            
            <div class="adhesion-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="button button-primary">
                    <?php _e('Ver Dashboard Completo', 'adhesion'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Widget de actividad reciente en el dashboard
     */
    public function dashboard_recent_activity_widget() {
        $recent_calculations = $this->db->get_all_calculations(3, 0);
        ?>
        <div class="adhesion-dashboard-widget">
            <h4><?php _e('Últimos Cálculos', 'adhesion'); ?></h4>
            <?php if (!empty($recent_calculations)): ?>
                <ul class="adhesion-recent-list">
                    <?php foreach ($recent_calculations as $calc): ?>
                        <li>
                            <strong><?php echo esc_html($calc['user_name']); ?></strong>
                            <span class="calculation-price"><?php echo adhesion_format_price($calc['total_price']); ?></span>
                            <span class="calculation-date"><?php echo adhesion_format_date($calc['created_at']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                    <?php _e('Ver Todos los Cálculos', 'adhesion'); ?>
                </a>
            <?php else: ?>
                <p><?php _e('No hay cálculos recientes.', 'adhesion'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Agregar columnas personalizadas en la lista de usuarios
     */
    public function add_user_columns($columns) {
        $columns['adhesion_calculations'] = __('Cálculos', 'adhesion');
        $columns['adhesion_contracts'] = __('Contratos', 'adhesion');
        return $columns;
    }
    
    /**
     * Mostrar contenido de columnas personalizadas de usuarios
     */
    public function show_user_column_content($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'adhesion_calculations':
                $calculations = $this->db->get_user_calculations($user_id, 1);
                return count($calculations);
                
            case 'adhesion_contracts':
                $contracts = $this->db->get_user_contracts($user_id, 1);
                return count($contracts);
        }
        
        return $value;
    }
    
    /**
     * Mostrar notices del admin
     */
    public function show_admin_notices() {
        // Verificar configuración básica
        if (!adhesion_is_redsys_configured() || !adhesion_is_docusign_configured()) {
            $this->show_configuration_notice();
        }
        
        // Verificar permisos de archivos
        $this->check_file_permissions();
        
        // Mostrar notices del sistema
        $this->show_system_notices();
    }
    
    /**
     * Notice de configuración pendiente
     */
    private function show_configuration_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'adhesion') === false) {
            return; // Solo mostrar en páginas del plugin
        }
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('Adhesión:', 'adhesion'); ?></strong>
                <?php _e('Algunas integraciones no están configuradas.', 'adhesion'); ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>">
                    <?php _e('Configurar ahora', 'adhesion'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Verificar permisos de archivos
     */
    private function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        $adhesion_dir = $upload_dir['basedir'] . '/adhesion/';
        
        if (!is_dir($adhesion_dir)) {
            if (!wp_mkdir_p($adhesion_dir)) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e('Adhesión:', 'adhesion'); ?></strong>
                        <?php _e('No se puede crear el directorio de uploads. Verifica los permisos.', 'adhesion'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Mostrar notices del sistema
     */
    private function show_system_notices() {
        // Verificar actualizaciones necesarias
        $current_version = get_option('adhesion_version', '0.0.0');
        if (version_compare($current_version, ADHESION_PLUGIN_VERSION, '<')) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Adhesión:', 'adhesion'); ?></strong>
                    <?php _e('Base de datos actualizada automáticamente.', 'adhesion'); ?>
                </p>
            </div>
            <?php
            update_option('adhesion_version', ADHESION_PLUGIN_VERSION);
        }
    }
    
    /**
     * Agregar enlaces de acción en la página de plugins
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=adhesion-settings') . '">' . __('Configuración', 'adhesion') . '</a>';
        $dashboard_link = '<a href="' . admin_url('admin.php?page=adhesion') . '">' . __('Dashboard', 'adhesion') . '</a>';
        
        array_unshift($links, $settings_link, $dashboard_link);
        
        return $links;
    }
    
    /**
     * Agregar meta links en la página de plugins
     */
    public function add_plugin_meta_links($links, $file) {
        if ($file === ADHESION_PLUGIN_BASENAME) {
            $links[] = '<a href="' . admin_url('admin.php?page=adhesion') . '" target="_blank">' . __('Dashboard', 'adhesion') . '</a>';
            $links[] = '<a href="#" target="_blank">' . __('Documentación', 'adhesion') . '</a>';
            $links[] = '<a href="#" target="_blank">' . __('Soporte', 'adhesion') . '</a>';
        }
        
        return $links;
    }
    
    /**
     * Inicialización del admin
     */
    public function admin_init() {
        // Procesar acciones del admin
        $this->process_admin_actions();
        
        // Registrar configuraciones
        $this->register_settings();
    }
    
    /**
     * Procesar acciones del admin
     */
    private function process_admin_actions() {
        if (!isset($_POST['adhesion_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['adhesion_nonce'], 'adhesion_admin_action')) {
            wp_die(__('Error de seguridad. Inténtalo de nuevo.', 'adhesion'));
        }
        
        $action = sanitize_text_field($_POST['adhesion_action']);
        
        switch ($action) {
            case 'clear_cache':
                $this->clear_plugin_cache();
                break;
                
            case 'export_data':
                $this->export_plugin_data();
                break;
                
            case 'reset_settings':
                $this->reset_plugin_settings();
                break;
        }
    }
    
    /**
     * Limpiar cache del plugin
     */
    private function clear_plugin_cache() {
        // Limpiar transients
        delete_transient('adhesion_calculator_prices');
        delete_transient('adhesion_active_documents');
        
        adhesion_add_notice(__('Cache del plugin limpiado correctamente.', 'adhesion'), 'success');
        
        wp_redirect(admin_url('admin.php?page=adhesion-settings'));
        exit;
    }
    
    /**
     * Exportar datos del plugin
     */
    private function export_plugin_data() {
        // TODO: Implementar exportación de datos
        adhesion_add_notice(__('Funcionalidad de exportación en desarrollo.', 'adhesion'), 'info');
    }
    
    /**
     * Resetear configuraciones del plugin
     */
    private function reset_plugin_settings() {
        delete_option('adhesion_settings');
        
        // Restaurar configuraciones por defecto
        Adhesion_Activator::set_default_options();
        
        adhesion_add_notice(__('Configuraciones restablecidas a valores por defecto.', 'adhesion'), 'success');
        
        wp_redirect(admin_url('admin.php?page=adhesion-settings'));
        exit;
    }
    
    /**
     * Registrar configuraciones del plugin
     */
    private function register_settings() {
        register_setting('adhesion_settings', 'adhesion_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }
    
    /**
     * Sanitizar configuraciones
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        // Sanitizar configuraciones de Redsys
        $sanitized['redsys_merchant_code'] = sanitize_text_field($settings['redsys_merchant_code'] ?? '');
        $sanitized['redsys_terminal'] = sanitize_text_field($settings['redsys_terminal'] ?? '001');
        $sanitized['redsys_secret_key'] = sanitize_text_field($settings['redsys_secret_key'] ?? '');
        $sanitized['redsys_environment'] = in_array($settings['redsys_environment'] ?? 'test', array('test', 'production')) ? $settings['redsys_environment'] : 'test';
        
        // Sanitizar configuraciones de DocuSign
        $sanitized['docusign_integration_key'] = sanitize_text_field($settings['docusign_integration_key'] ?? '');
        $sanitized['docusign_secret_key'] = sanitize_text_field($settings['docusign_secret_key'] ?? '');
        $sanitized['docusign_account_id'] = sanitize_text_field($settings['docusign_account_id'] ?? '');
        $sanitized['docusign_environment'] = in_array($settings['docusign_environment'] ?? 'demo', array('demo', 'production')) ? $settings['docusign_environment'] : 'demo';
        
        // Sanitizar configuraciones generales
        $sanitized['calculator_enabled'] = isset($settings['calculator_enabled']) ? '1' : '0';
        $sanitized['auto_create_users'] = isset($settings['auto_create_users']) ? '1' : '0';
        $sanitized['email_notifications'] = isset($settings['email_notifications']) ? '1' : '0';
        
        return $sanitized;
    }


    /**
     * Mostrar campos personalizados en el perfil de usuario
     */
    public function show_extra_user_profile_fields($user) {
        // Solo mostrar para usuarios con rol adhesion_client
        if (!in_array('adhesion_client', $user->roles)) {
            return;
        }
        
        // Obtener valores actuales
        $empresa = get_user_meta($user->ID, 'empresa', true);
        $cif = get_user_meta($user->ID, 'cif', true);
        $telefono = get_user_meta($user->ID, 'telefono', true);
        $registration_date = get_user_meta($user->ID, 'registration_date', true);
        ?>
        
        <h2><?php _e('Información de Adhesión', 'adhesion'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="empresa"><?php _e('Empresa', 'adhesion'); ?></label></th>
                <td>
                    <input type="text" name="empresa" id="empresa" value="<?php echo esc_attr($empresa); ?>" class="regular-text" />
                    <p class="description"><?php _e('Nombre de la empresa del cliente.', 'adhesion'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="cif"><?php _e('CIF', 'adhesion'); ?></label></th>
                <td>
                    <input type="text" name="cif" id="cif" value="<?php echo esc_attr($cif); ?>" class="regular-text" />
                    <p class="description"><?php _e('CIF de la empresa.', 'adhesion'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="telefono"><?php _e('Teléfono', 'adhesion'); ?></label></th>
                <td>
                    <input type="tel" name="telefono" id="telefono" value="<?php echo esc_attr($telefono); ?>" class="regular-text" />
                    <p class="description"><?php _e('Número de teléfono de contacto.', 'adhesion'); ?></p>
                </td>
            </tr>
            
            <?php if ($registration_date): ?>
            <tr>
                <th><?php _e('Fecha de registro', 'adhesion'); ?></th>
                <td>
                    <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration_date)); ?></span>
                    <p class="description"><?php _e('Fecha en que se registró en el sistema de adhesión.', 'adhesion'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Guardar campos personalizados del perfil de usuario
     */
    public function save_extra_user_profile_fields($user_id) {
        // Verificar permisos
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Verificar que es un usuario de adhesión
        $user = get_userdata($user_id);
        if (!in_array('adhesion_client', $user->roles)) {
            return false;
        }
        
        // Sanitizar y guardar empresa
        if (isset($_POST['empresa'])) {
            $empresa = sanitize_text_field($_POST['empresa']);
            update_user_meta($user_id, 'empresa', $empresa);
        }
        
        // Sanitizar y guardar CIF
        if (isset($_POST['cif'])) {
            $cif = sanitize_text_field($_POST['cif']);
            update_user_meta($user_id, 'cif', $cif);
        }
        
        // Sanitizar y guardar teléfono
        if (isset($_POST['telefono'])) {
            $telefono = sanitize_text_field($_POST['telefono']);
            update_user_meta($user_id, 'telefono', $telefono);
        }
    }


}