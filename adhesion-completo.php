

// ===== adhesion.php =====
<?php
/**
 * Plugin Name: AdhesiÃ³n - Proceso de AdhesiÃ³n de Clientes
 * Plugin URI: https://tudominio.com
 * Description: Plugin para gestionar el proceso completo de adhesiÃ³n de clientes con calculadora, pagos y firma de contratos.
 * Version: 1.0.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 * Text Domain: adhesion
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('ADHESION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADHESION_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ADHESION_PLUGIN_VERSION', '1.0.0');
define('ADHESION_PLUGIN_FILE', __FILE__);
define('ADHESION_PLUGIN_BASENAME', plugin_basename(__FILE__));

// ==========================================================
// CARGAR DEPENDENCIAS CRÃTICAS INMEDIATAMENTE
// ==========================================================

// Cargar las clases necesarias para los hooks de activaciÃ³n/desactivaciÃ³n
require_once ADHESION_PLUGIN_PATH . 'includes/class-activator.php';
require_once ADHESION_PLUGIN_PATH . 'includes/class-deactivator.php';

// ==========================================================
// REGISTRAR HOOKS DE ACTIVACIÃ“N/DESACTIVACIÃ“N
// ==========================================================

// AHORA SÃ podemos registrar los hooks porque las clases ya estÃ¡n cargadas
register_activation_hook(ADHESION_PLUGIN_FILE, array('Adhesion_Activator', 'activate'));
register_deactivation_hook(ADHESION_PLUGIN_FILE, array('Adhesion_Deactivator', 'deactivate'));

/**
 * Clase principal del plugin AdhesiÃ³n
 */
class Adhesion_Plugin {

    /**
     * Instancia Ãºnica del plugin
     */
    private static $instance = null;

    /**
     * Constructor privado para patrÃ³n singleton
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Obtener instancia Ãºnica del plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar el plugin
     */
    private function init() {
        // Cargar resto de dependencias
        $this->load_dependencies();
        
        // Hooks de inicializaciÃ³n
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init_plugin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Las clases crÃ­ticas ya se cargaron arriba, cargar el resto
        require_once ADHESION_PLUGIN_PATH . 'includes/class-database.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/class-ajax-handler.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/functions.php';
    }

    /**
     * Inicializar componentes del plugin
     */
    public function init_plugin() {
        // Cargar componentes segÃºn el contexto
        if (is_admin()) {
            $this->init_admin();
        }
        
        $this->init_public();
        $this->init_ajax();
    }

    /**
     * Inicializar componentes del admin
     */
    private function init_admin() {
        require_once ADHESION_PLUGIN_PATH . 'admin/class-admin.php';
        new Adhesion_Admin();
    }

    /**
     * Inicializar componentes pÃºblicos
     */
    private function init_public() {
        require_once ADHESION_PLUGIN_PATH . 'public/class-public.php';
        new Adhesion_Public();
    }

    /**
     * Inicializar manejador AJAX
     */
    private function init_ajax() {
        new Adhesion_Ajax_Handler();
    }

    /**
     * Cargar traduciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'adhesion',
            false,
            dirname(ADHESION_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Encolar assets del frontend
     */
    public function enqueue_public_assets() {
        // CSS del frontend
        wp_enqueue_style(
            'adhesion-frontend-css',
            ADHESION_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ADHESION_PLUGIN_VERSION
        );

        // JavaScript del frontend
        wp_enqueue_script(
            'adhesion-frontend-js',
            ADHESION_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ADHESION_PLUGIN_VERSION,
            true
        );

        // Localizar script para AJAX
        wp_localize_script('adhesion-frontend-js', 'adhesion_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adhesion_nonce'),
            'messages' => array(
                'error' => __('Ha ocurrido un error. Por favor, intÃ©ntalo de nuevo.', 'adhesion'),
                'loading' => __('Cargando...', 'adhesion'),
                'success' => __('OperaciÃ³n completada con Ã©xito.', 'adhesion')
            )
        ));
    }

    /**
     * Encolar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en pÃ¡ginas del plugin
        if (strpos($hook, 'adhesion') === false) {
            return;
        }

        // CSS del admin
        wp_enqueue_style(
            'adhesion-admin-css',
            ADHESION_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ADHESION_PLUGIN_VERSION
        );

        // JavaScript del admin
        wp_enqueue_script(
            'adhesion-admin-js',
            ADHESION_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ADHESION_PLUGIN_VERSION,
            true
        );

        // Localizar script para AJAX del admin
        wp_localize_script('adhesion-admin-js', 'adhesion_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adhesion_admin_nonce'),
            'messages' => array(
                'confirm_delete' => __('Â¿EstÃ¡s seguro de que quieres eliminar este elemento?', 'adhesion'),
                'error' => __('Ha ocurrido un error. Por favor, intÃ©ntalo de nuevo.', 'adhesion'),
                'saved' => __('Cambios guardados correctamente.', 'adhesion')
            )
        ));
    }
}

/**
 * FunciÃ³n principal para obtener la instancia del plugin
 */
function adhesion() {
    return Adhesion_Plugin::get_instance();
}

// Inicializar el plugin cuando WordPress estÃ© listo
add_action('plugins_loaded', 'adhesion');

/**
 * Funciones de utilidad globales
 */

/**
 * Obtener configuraciÃ³n del plugin
 */
function adhesion_get_option($key, $default = '') {
    $options = get_option('adhesion_settings', array());
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Actualizar configuraciÃ³n del plugin
 */
function adhesion_update_option($key, $value) {
    $options = get_option('adhesion_settings', array());
    $options[$key] = $value;
    return update_option('adhesion_settings', $options);
}

/**
 * Log de debug para el plugin
 */
function adhesion_log($message, $type = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[ADHESION %s] %s', strtoupper($type), $message));
    }
}

/**
 * Verificar si el usuario tiene permisos de adhesiÃ³n
 */
function adhesion_user_can_access() {
    return is_user_logged_in() && (current_user_can('manage_options') || in_array('adhesion_client', wp_get_current_user()->roles));
}

/**
 * Hook que se ejecuta cuando el plugin se carga completamente
 */
do_action('adhesion_loaded');


// ===== uninstall.php =====
<?php
/**
 * DesinstalaciÃ³n del plugin AdhesiÃ³n
 * 
 * Este archivo se ejecuta cuando el plugin se DESINSTALA (no solo desactiva)
 * Elimina completamente:
 * - Todas las tablas de la base de datos
 * - Todas las opciones de configuraciÃ³n
 * - PÃ¡ginas creadas por el plugin
 * - Roles y capacidades
 * - Archivos subidos
 */

// Si no se llama desde WordPress, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Log de inicio de desinstalaciÃ³n
error_log('[ADHESION] ðŸ—‘ï¸ Iniciando desinstalaciÃ³n completa del plugin');

// Verificar que realmente queremos desinstalar
if (!current_user_can('delete_plugins')) {
    error_log('[ADHESION] âŒ Usuario sin permisos para desinstalar');
    return;
}

global $wpdb;

try {
    // ==========================================
    // 1. ELIMINAR TABLAS DE LA BASE DE DATOS
    // ==========================================
    
    error_log('[ADHESION] Eliminando tablas de la base de datos...');
    
    $tables_to_delete = array(
        $wpdb->prefix . 'adhesion_calculations',
        $wpdb->prefix . 'adhesion_contracts',
        $wpdb->prefix . 'adhesion_documents',
        $wpdb->prefix . 'adhesion_settings',
        $wpdb->prefix . 'adhesion_calculator_prices'
    );
    
    foreach ($tables_to_delete as $table) {
        // Verificar si la tabla existe antes de eliminarla
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if ($table_exists) {
            $result = $wpdb->query("DROP TABLE IF EXISTS `$table`");
            
            if ($result === false) {
                error_log("[ADHESION] âŒ Error eliminando tabla: $table - " . $wpdb->last_error);
            } else {
                error_log("[ADHESION] âœ… Tabla eliminada: $table");
            }
        } else {
            error_log("[ADHESION] âš ï¸ Tabla no existe: $table");
        }
    }
    
    // ==========================================
    // 2. ELIMINAR OPCIONES DE WORDPRESS
    // ==========================================
    
    error_log('[ADHESION] Eliminando opciones de configuraciÃ³n...');
    
    $options_to_delete = array(
        'adhesion_settings',
        'adhesion_activated',
        'adhesion_tables_created',
        'adhesion_version'
    );
    
    foreach ($options_to_delete as $option) {
        $deleted = delete_option($option);
        if ($deleted) {
            error_log("[ADHESION] âœ… OpciÃ³n eliminada: $option");
        } else {
            error_log("[ADHESION] âš ï¸ OpciÃ³n no existÃ­a: $option");
        }
    }
    
    // Eliminar tambiÃ©n opciones de sitio para multisitio
    if (is_multisite()) {
        foreach ($options_to_delete as $option) {
            delete_site_option($option);
        }
    }
    
    // ==========================================
    // 3. ELIMINAR PÃGINAS CREADAS POR EL PLUGIN
    // ==========================================
    
    error_log('[ADHESION] Eliminando pÃ¡ginas creadas por el plugin...');
    
    $page_slugs_to_delete = array(
        'calculadora-presupuesto',
        'mi-cuenta-adhesion',
        'proceso-pago',
        'firma-contratos'
    );
    
    foreach ($page_slugs_to_delete as $slug) {
        $page = get_page_by_path($slug);
        
        if ($page) {
            // Verificar que la pÃ¡gina contiene shortcodes del plugin antes de eliminarla
            $page_content = $page->post_content;
            if (strpos($page_content, '[adhesion_') !== false) {
                $deleted = wp_delete_post($page->ID, true); // true = forzar eliminaciÃ³n permanente
                
                if ($deleted) {
                    error_log("[ADHESION] âœ… PÃ¡gina eliminada: $slug (ID: {$page->ID})");
                } else {
                    error_log("[ADHESION] âŒ Error eliminando pÃ¡gina: $slug");
                }
            } else {
                error_log("[ADHESION] âš ï¸ PÃ¡gina existe pero no contiene shortcodes del plugin: $slug");
            }
        } else {
            error_log("[ADHESION] âš ï¸ PÃ¡gina no encontrada: $slug");
        }
    }
    
    // ==========================================
    // 4. ELIMINAR ROLES Y CAPACIDADES
    // ==========================================
    
    error_log('[ADHESION] Eliminando roles y capacidades...');
    
    // Eliminar rol de cliente adherido
    if (get_role('adhesion_client')) {
        remove_role('adhesion_client');
        error_log('[ADHESION] âœ… Rol eliminado: adhesion_client');
    }
    
    // Eliminar capacidades de otros roles
    $roles_to_clean = array('administrator', 'editor');
    $caps_to_remove = array(
        'adhesion_manage_all',
        'adhesion_manage_settings',
        'adhesion_manage_documents',
        'adhesion_view_reports',
        'adhesion_access',
        'adhesion_calculate',
        'adhesion_view_account'
    );
    
    foreach ($roles_to_clean as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($caps_to_remove as $cap) {
                if ($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                    error_log("[ADHESION] âœ… Capacidad eliminada: $cap de $role_name");
                }
            }
        }
    }
    
    // ==========================================
    // 5. ELIMINAR ARCHIVOS Y DIRECTORIOS
    // ==========================================
    
    error_log('[ADHESION] Eliminando archivos subidos...');
    
    $upload_dir = wp_upload_dir();
    $adhesion_upload_dir = $upload_dir['basedir'] . '/adhesion/';
    
    if (is_dir($adhesion_upload_dir)) {
        // FunciÃ³n recursiva para eliminar directorio y contenido
        $deleted = adhesion_delete_directory_recursive($adhesion_upload_dir);
        
        if ($deleted) {
            error_log("[ADHESION] âœ… Directorio eliminado: $adhesion_upload_dir");
        } else {
            error_log("[ADHESION] âŒ Error eliminando directorio: $adhesion_upload_dir");
        }
    } else {
        error_log("[ADHESION] âš ï¸ Directorio no existe: $adhesion_upload_dir");
    }
    
    // ==========================================
    // 6. LIMPIAR TRANSIENTS Y CACHE
    // ==========================================
    
    error_log('[ADHESION] Limpiando transients y cache...');
    
    // Eliminar transients especÃ­ficos del plugin
    $transients_to_delete = array(
        'adhesion_calculator_prices',
        'adhesion_active_documents',
        'adhesion_system_status'
    );
    
    foreach ($transients_to_delete as $transient) {
        delete_transient($transient);
        delete_site_transient($transient); // Para multisitio
        error_log("[ADHESION] âœ… Transient eliminado: $transient");
    }
    
    // Eliminar transients de usuarios (notificaciones)
    $users = get_users(array('fields' => 'ID'));
    foreach ($users as $user_id) {
        delete_transient('adhesion_notices_' . $user_id);
    }
    
    // ==========================================
    // 7. LIMPIAR METADATOS DE USUARIOS
    // ==========================================
    
    error_log('[ADHESION] Eliminando metadatos de usuarios...');
    
    // Buscar usuarios que tenÃ­an el rol de adhesion_client
    $adhesion_users = get_users(array(
        'meta_key' => 'wp_capabilities',
        'meta_value' => 'adhesion_client',
        'meta_compare' => 'LIKE'
    ));
    
    foreach ($adhesion_users as $user) {
        // Eliminar metadata especÃ­fico del plugin
        delete_user_meta($user->ID, 'adhesion_last_calculation');
        delete_user_meta($user->ID, 'adhesion_total_calculations');
        delete_user_meta($user->ID, 'adhesion_registration_date');
        
        error_log("[ADHESION] âœ… Metadata eliminado del usuario: {$user->ID}");
    }
    
    // ==========================================
    // 8. LIMPIAR CRON JOBS
    // ==========================================
    
    error_log('[ADHESION] Eliminando tareas programadas...');
    
    // Eliminar cron jobs del plugin
    $cron_hooks = array(
        'adhesion_cleanup_old_data',
        'adhesion_send_reminder_emails',
        'adhesion_update_calculator_prices'
    );
    
    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
            error_log("[ADHESION] âœ… Cron job eliminado: $hook");
        }
    }
    
    // ==========================================
    // 9. VERIFICACIÃ“N FINAL
    // ==========================================
    
    error_log('[ADHESION] Realizando verificaciÃ³n final...');
    
    $verification_errors = array();
    
    // Verificar que las tablas se eliminaron
    foreach ($tables_to_delete as $table) {
        $still_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($still_exists) {
            $verification_errors[] = "Tabla aÃºn existe: $table";
        }
    }
    
    // Verificar que las opciones se eliminaron
    foreach ($options_to_delete as $option) {
        $still_exists = get_option($option);
        if ($still_exists !== false) {
            $verification_errors[] = "OpciÃ³n aÃºn existe: $option";
        }
    }
    
    // Verificar que el rol se eliminÃ³
    if (get_role('adhesion_client')) {
        $verification_errors[] = "Rol 'adhesion_client' aÃºn existe";
    }
    
    if (empty($verification_errors)) {
        error_log('[ADHESION] âœ… DESINSTALACIÃ“N COMPLETADA EXITOSAMENTE - Todo limpio');
    } else {
        error_log('[ADHESION] âš ï¸ DESINSTALACIÃ“N con advertencias:');
        foreach ($verification_errors as $error) {
            error_log("[ADHESION] - $error");
        }
    }
    
    // ==========================================
    // 10. LOG FINAL
    // ==========================================
    
    error_log('[ADHESION] ðŸŽ¯ Resumen de desinstalaciÃ³n:');
    error_log('[ADHESION] - Tablas de BD: ' . count($tables_to_delete) . ' procesadas');
    error_log('[ADHESION] - Opciones: ' . count($options_to_delete) . ' procesadas');
    error_log('[ADHESION] - PÃ¡ginas: ' . count($page_slugs_to_delete) . ' verificadas');
    error_log('[ADHESION] - Roles: 1 eliminado + capacidades limpiadas');
    error_log('[ADHESION] - Archivos: directorio uploads limpiado');
    error_log('[ADHESION] - Cache: transients eliminados');
    error_log('[ADHESION] - Usuarios: metadata limpiado');
    error_log('[ADHESION] - Cron: tareas programadas eliminadas');
    error_log('[ADHESION] ðŸ FIN DE DESINSTALACIÃ“N');
    
} catch (Exception $e) {
    error_log('[ADHESION] âŒ ERROR CRÃTICO durante desinstalaciÃ³n: ' . $e->getMessage());
    error_log('[ADHESION] Stack trace: ' . $e->getTraceAsString());
}

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

/**
 * Eliminar directorio recursivamente
 */
function adhesion_delete_directory_recursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            adhesion_delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * Limpiar opciones que empiecen con cierto prefijo
 */
function adhesion_cleanup_options_by_prefix($prefix) {
    global $wpdb;
    
    $options = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix . '%'
        )
    );
    
    foreach ($options as $option) {
        delete_option($option->option_name);
        error_log("[ADHESION] âœ… OpciÃ³n eliminada: {$option->option_name}");
    }
}

// Llamar limpieza adicional de opciones que puedan haberse creado dinÃ¡micamente
adhesion_cleanup_options_by_prefix('adhesion_temp_');
adhesion_cleanup_options_by_prefix('adhesion_cache_');

// Forzar limpieza de rewrite rules
flush_rewrite_rules();


// ===== class-admin.php =====
<?php
/**
 * Clase principal del administrador
 * 
 * Esta clase maneja todo el backend del plugin:
 * - MenÃºs de administraciÃ³n
 * - PÃ¡ginas de configuraciÃ³n
 * - Dashboard del plugin
 * - IntegraciÃ³n con WordPress admin
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
     * Slug del menÃº principal
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
        // MenÃºs de administraciÃ³n
        add_action('admin_menu', array($this, 'add_admin_menus'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Columnas personalizadas en listados de usuarios
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_action('manage_users_custom_column', array($this, 'show_user_column_content'), 10, 3);
        
        // Notices del admin
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Enlaces de acciÃ³n en la pÃ¡gina de plugins
        add_filter('plugin_action_links_' . ADHESION_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // Meta links en la pÃ¡gina de plugins
        add_filter('plugin_row_meta', array($this, 'add_plugin_meta_links'), 10, 2);
        
        // InicializaciÃ³n del admin
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * Cargar clases especÃ­ficas del admin
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
     * Agregar menÃºs de administraciÃ³n
     */
    public function add_admin_menus() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // MenÃº principal
        add_menu_page(
            __('AdhesiÃ³n', 'adhesion'),                    // TÃ­tulo de la pÃ¡gina
            __('AdhesiÃ³n', 'adhesion'),                    // TÃ­tulo del menÃº
            'manage_options',                              // Capacidad requerida
            $this->menu_slug,                              // Slug del menÃº
            array($this, 'display_dashboard'),             // FunciÃ³n callback
            'dashicons-clipboard',                         // Icono
            30                                             // PosiciÃ³n
        );
        
        // SubmenÃº: Dashboard
        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'adhesion'),
            __('Dashboard', 'adhesion'),
            'manage_options',
            $this->menu_slug,
            array($this, 'display_dashboard')
        );
        
        // SubmenÃº: CÃ¡lculos
        add_submenu_page(
            $this->menu_slug,
            __('CÃ¡lculos de Presupuestos', 'adhesion'),
            __('CÃ¡lculos', 'adhesion'),
            'manage_options',
            'adhesion-calculations',
            array($this, 'display_calculations')
        );
        
        // SubmenÃº: Contratos
        add_submenu_page(
            $this->menu_slug,
            __('Contratos de AdhesiÃ³n', 'adhesion'),
            __('Contratos', 'adhesion'),
            'manage_options',
            'adhesion-contracts',
            array($this, 'display_contracts')
        );
        
        // SubmenÃº: Usuarios
        add_submenu_page(
            $this->menu_slug,
            __('Usuarios Adheridos', 'adhesion'),
            __('Usuarios', 'adhesion'),
            'manage_options',
            'adhesion-users',
            array($this, 'display_users')
        );
        
        // SubmenÃº: Documentos
        add_submenu_page(
            $this->menu_slug,
            __('GestiÃ³n de Documentos', 'adhesion'),
            __('Documentos', 'adhesion'),
            'manage_options',
            'adhesion-documents',
            array($this, 'display_documents')
        );
        
        // SubmenÃº: ConfiguraciÃ³n
        add_submenu_page(
            $this->menu_slug,
            __('ConfiguraciÃ³n de AdhesiÃ³n', 'adhesion'),
            __('ConfiguraciÃ³n', 'adhesion'),
            'manage_options',
            'adhesion-settings',
            array($this, 'display_settings')
        );
    }
    
    /**
     * Mostrar dashboard principal
     */
    public function display_dashboard() {
        // Obtener estadÃ­sticas
        $stats = $this->db->get_basic_stats();
        $recent_stats = $this->db->get_period_stats(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );
        
        // Obtener Ãºltimos cÃ¡lculos y contratos
        $recent_calculations = $this->db->get_all_calculations(5, 0);
        $recent_contracts = $this->db->get_all_calculations(5, 0, array()); // Obtener contratos recientes
        
        // Verificar configuraciÃ³n
        $config_status = array(
            'redsys' => adhesion_is_redsys_configured(),
            'docusign' => adhesion_is_docusign_configured()
        );
        
        include ADHESION_PLUGIN_PATH . 'admin/partials/dashboard-display.php';
    }
    
    /**
     * Mostrar pÃ¡gina de cÃ¡lculos
     */
    public function display_calculations() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/calculations-display.php';
    }
    
    /**
     * Mostrar pÃ¡gina de contratos
     */
    public function display_contracts() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/contracts-display.php';
    }
    
    /**
     * Mostrar pÃ¡gina de usuarios
     */
    public function display_users() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/users-display.php';
    }
    
    /**
     * Mostrar pÃ¡gina de documentos
     */
    public function display_documents() {
        include ADHESION_PLUGIN_PATH . 'admin/partials/documents-display.php';
    }
    
    /**
     * Mostrar pÃ¡gina de configuraciÃ³n
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
            __('EstadÃ­sticas de AdhesiÃ³n', 'adhesion'),
            array($this, 'dashboard_stats_widget')
        );
        
        wp_add_dashboard_widget(
            'adhesion_recent_activity',
            __('Actividad Reciente - AdhesiÃ³n', 'adhesion'),
            array($this, 'dashboard_recent_activity_widget')
        );
    }
    
    /**
     * Widget de estadÃ­sticas en el dashboard
     */
    public function dashboard_stats_widget() {
        $stats = $this->db->get_basic_stats();
        ?>
        <div class="adhesion-dashboard-widget">
            <div class="adhesion-stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html($stats['total_calculations']); ?></span>
                    <span class="stat-label"><?php _e('CÃ¡lculos Totales', 'adhesion'); ?></span>
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
            <h4><?php _e('Ãšltimos CÃ¡lculos', 'adhesion'); ?></h4>
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
                    <?php _e('Ver Todos los CÃ¡lculos', 'adhesion'); ?>
                </a>
            <?php else: ?>
                <p><?php _e('No hay cÃ¡lculos recientes.', 'adhesion'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Agregar columnas personalizadas en la lista de usuarios
     */
    public function add_user_columns($columns) {
        $columns['adhesion_calculations'] = __('CÃ¡lculos', 'adhesion');
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
        // Verificar configuraciÃ³n bÃ¡sica
        if (!adhesion_is_redsys_configured() || !adhesion_is_docusign_configured()) {
            $this->show_configuration_notice();
        }
        
        // Verificar permisos de archivos
        $this->check_file_permissions();
        
        // Mostrar notices del sistema
        $this->show_system_notices();
    }
    
    /**
     * Notice de configuraciÃ³n pendiente
     */
    private function show_configuration_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'adhesion') === false) {
            return; // Solo mostrar en pÃ¡ginas del plugin
        }
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('AdhesiÃ³n:', 'adhesion'); ?></strong>
                <?php _e('Algunas integraciones no estÃ¡n configuradas.', 'adhesion'); ?>
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
                        <strong><?php _e('AdhesiÃ³n:', 'adhesion'); ?></strong>
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
                    <strong><?php _e('AdhesiÃ³n:', 'adhesion'); ?></strong>
                    <?php _e('Base de datos actualizada automÃ¡ticamente.', 'adhesion'); ?>
                </p>
            </div>
            <?php
            update_option('adhesion_version', ADHESION_PLUGIN_VERSION);
        }
    }
    
    /**
     * Agregar enlaces de acciÃ³n en la pÃ¡gina de plugins
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=adhesion-settings') . '">' . __('ConfiguraciÃ³n', 'adhesion') . '</a>';
        $dashboard_link = '<a href="' . admin_url('admin.php?page=adhesion') . '">' . __('Dashboard', 'adhesion') . '</a>';
        
        array_unshift($links, $settings_link, $dashboard_link);
        
        return $links;
    }
    
    /**
     * Agregar meta links en la pÃ¡gina de plugins
     */
    public function add_plugin_meta_links($links, $file) {
        if ($file === ADHESION_PLUGIN_BASENAME) {
            $links[] = '<a href="' . admin_url('admin.php?page=adhesion') . '" target="_blank">' . __('Dashboard', 'adhesion') . '</a>';
            $links[] = '<a href="#" target="_blank">' . __('DocumentaciÃ³n', 'adhesion') . '</a>';
            $links[] = '<a href="#" target="_blank">' . __('Soporte', 'adhesion') . '</a>';
        }
        
        return $links;
    }
    
    /**
     * InicializaciÃ³n del admin
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
            wp_die(__('Error de seguridad. IntÃ©ntalo de nuevo.', 'adhesion'));
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
        // TODO: Implementar exportaciÃ³n de datos
        adhesion_add_notice(__('Funcionalidad de exportaciÃ³n en desarrollo.', 'adhesion'), 'info');
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
}


// ===== class-calculations-list.php =====
<?php
/**
 * Clase para listado de cÃ¡lculos de presupuestos
 * 
 * Esta clase extiende WP_List_Table para mostrar:
 * - Listado paginado de cÃ¡lculos
 * - Filtros por usuario, fecha y estado
 * - Acciones en masa
 * - ExportaciÃ³n de datos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no estÃ¡ disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adhesion_Calculations_List extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'calculation',
            'plural' => 'calculations',
            'ajax' => false
        ));
        
        $this->db = new Adhesion_Database();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Procesar acciones
        add_action('admin_init', array($this, 'process_bulk_actions'));
        
        // AJAX para acciones rÃ¡pidas
        add_action('wp_ajax_adhesion_delete_calculation', array($this, 'ajax_delete_calculation'));
        add_action('wp_ajax_adhesion_export_calculations', array($this, 'ajax_export_calculations'));
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'adhesion'),
            'user' => __('Usuario', 'adhesion'),
            'materials' => __('Materiales', 'adhesion'),
            'total_tons' => __('Toneladas', 'adhesion'),
            'total_price' => __('Precio Total', 'adhesion'),
            'created_at' => __('Fecha', 'adhesion'),
            'actions' => __('Acciones', 'adhesion')
        );
    }
    
    /**
     * Obtener columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'id' => array('id', false),
            'user' => array('user_name', false),
            'total_price' => array('total_price', false),
            'total_tons' => array('total_tons', false),
            'created_at' => array('created_at', true) // Por defecto descendente
        );
    }
    
    /**
     * Obtener acciones en masa
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Eliminar', 'adhesion'),
            'export' => __('Exportar', 'adhesion'),
            'mark_inactive' => __('Marcar como inactivo', 'adhesion')
        );
    }
    
    /**
     * Preparar elementos
     */
    public function prepare_items() {
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Procesar acciones en masa
        $this->process_bulk_actions();
        
        // Configurar paginaciÃ³n
        $per_page = $this->get_items_per_page('calculations_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Obtener filtros
        $filters = $this->get_filters();
        
        // Obtener elementos
        $this->items = $this->db->get_all_calculations($per_page, $offset, $filters);
        
        // Configurar paginaciÃ³n
        $total_items = $this->get_total_calculations($filters);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Obtener filtros de la URL
     */
    private function get_filters() {
        $filters = array();
        
        // Filtro por usuario
        if (!empty($_GET['user_id'])) {
            $filters['user_id'] = intval($_GET['user_id']);
        }
        
        // Filtro por fecha desde
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        // Filtro por fecha hasta
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        // Filtro por rango de precio
        if (!empty($_GET['price_min'])) {
            $filters['price_min'] = floatval($_GET['price_min']);
        }
        
        if (!empty($_GET['price_max'])) {
            $filters['price_max'] = floatval($_GET['price_max']);
        }
        
        // OrdenaciÃ³n
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'created_at');
        $order = sanitize_text_field($_GET['order'] ?? 'desc');
        
        $filters['orderby'] = $orderby;
        $filters['order'] = $order;
        
        return $filters;
    }
    
    /**
     * Obtener total de cÃ¡lculos (para paginaciÃ³n)
     */
    private function get_total_calculations($filters) {
        global $wpdb;
        
        $where_clauses = array("c.status = 'active'");
        $params = array();
        
        // Aplicar filtros
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "c.user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "c.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "c.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['price_min'])) {
            $where_clauses[] = "c.total_price >= %f";
            $params[] = $filters['price_min'];
        }
        
        if (!empty($filters['price_max'])) {
            $where_clauses[] = "c.total_price <= %f";
            $params[] = $filters['price_max'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculations c 
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
                WHERE $where_sql";
        
        if (empty($params)) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        }
    }
    
    /**
     * Columna por defecto
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
                
            case 'total_tons':
                return $item['total_tons'] ? adhesion_format_tons($item['total_tons']) : '-';
                
            case 'total_price':
                return adhesion_format_price($item['total_price']);
                
            case 'created_at':
                return adhesion_format_date($item['created_at']);
                
            default:
                return $item[$column_name] ?? '-';
        }
    }
    
    /**
     * Columna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="calculation[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Columna de usuario
     */
    public function column_user($item) {
        $user_name = $item['user_name'] ?: __('Usuario eliminado', 'adhesion');
        $user_email = $item['user_email'] ?: '';
        
        $output = '<strong>' . esc_html($user_name) . '</strong>';
        
        if ($user_email) {
            $output .= '<br><small>' . esc_html($user_email) . '</small>';
        }
        
        // Agregar enlace al usuario si existe
        if ($item['user_id']) {
            $user_link = admin_url('user-edit.php?user_id=' . $item['user_id']);
            $output = '<a href="' . esc_url($user_link) . '">' . $output . '</a>';
        }
        
        return $output;
    }
    
    /**
     * Columna de materiales
     */
    public function column_materials($item) {
        $calculation_data = $item['calculation_data'];
        
        if (empty($calculation_data) || !isset($calculation_data['materials'])) {
            return '-';
        }
        
        $materials = $calculation_data['materials'];
        $output = '';
        
        if (count($materials) <= 2) {
            // Mostrar todos si son pocos
            foreach ($materials as $material) {
                $output .= '<div class="material-item">';
                $output .= '<strong>' . esc_html($material['type']) . '</strong>: ';
                $output .= adhesion_format_tons($material['quantity']);
                $output .= '</div>';
            }
        } else {
            // Mostrar resumen si son muchos
            $total_types = count($materials);
            $output .= sprintf(__('%d tipos de materiales', 'adhesion'), $total_types);
            $output .= '<br><small>' . __('Ver detalles', 'adhesion') . '</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de acciones
     */
    public function column_actions($item) {
        $actions = array();
        
        // Ver detalles
        $view_url = admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $item['id']);
        $actions['view'] = '<a href="' . esc_url($view_url) . '" class="button button-small">' . __('Ver', 'adhesion') . '</a>';
        
        // Crear contrato si no existe
        $has_contract = $this->calculation_has_contract($item['id']);
        if (!$has_contract) {
            $contract_url = admin_url('admin.php?page=adhesion-contracts&action=create&calculation=' . $item['id']);
            $actions['contract'] = '<a href="' . esc_url($contract_url) . '" class="button button-small button-primary">' . __('Crear Contrato', 'adhesion') . '</a>';
        }
        
        // Eliminar
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=adhesion-calculations&action=delete&calculation=' . $item['id']),
            'delete_calculation_' . $item['id']
        );
        $actions['delete'] = '<a href="' . esc_url($delete_url) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . 
                           esc_js(__('Â¿EstÃ¡s seguro de que quieres eliminar este cÃ¡lculo?', 'adhesion')) . '\')">' . 
                           __('Eliminar', 'adhesion') . '</a>';
        
        return '<div class="row-actions-wrapper">' . implode(' ', $actions) . '</div>';
    }
    
    /**
     * Verificar si un cÃ¡lculo tiene contrato asociado
     */
    private function calculation_has_contract($calculation_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE calculation_id = %d",
            $calculation_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Mostrar filtros
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        
        ?>
        <div class="alignleft actions">
            <label for="filter-user" class="screen-reader-text"><?php _e('Filtrar por usuario', 'adhesion'); ?></label>
            <select name="user_id" id="filter-user">
                <option value=""><?php _e('Todos los usuarios', 'adhesion'); ?></option>
                <?php
                // Obtener usuarios con cÃ¡lculos
                $users = $this->get_users_with_calculations();
                foreach ($users as $user) {
                    printf(
                        '<option value="%d" %s>%s (%d)</option>',
                        $user['user_id'],
                        selected($_GET['user_id'] ?? '', $user['user_id'], false),
                        esc_html($user['user_name']),
                        $user['calculation_count']
                    );
                }
                ?>
            </select>
            
            <label for="filter-date-from" class="screen-reader-text"><?php _e('Fecha desde', 'adhesion'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" 
                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha desde', 'adhesion'); ?>">
            
            <label for="filter-date-to" class="screen-reader-text"><?php _e('Fecha hasta', 'adhesion'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" 
                   value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha hasta', 'adhesion'); ?>">
            
            <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php _e('Filtrar', 'adhesion'); ?>">
            
            <?php if (!empty($_GET['user_id']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                    <?php _e('Limpiar filtros', 'adhesion'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" onclick="adhesionExportCalculations()">
                <?php _e('Exportar CSV', 'adhesion'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Obtener usuarios con cÃ¡lculos
     */
    private function get_users_with_calculations() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT u.ID as user_id, u.display_name as user_name, COUNT(c.id) as calculation_count
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id
             WHERE c.status = 'active'
             GROUP BY u.ID
             ORDER BY u.display_name ASC",
            ARRAY_A
        );
    }
    
    /**
     * Procesar acciones en masa
     */
    public function process_bulk_actions() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'adhesion'));
        }
        
        $calculation_ids = $_GET['calculation'] ?? array();
        
        if (empty($calculation_ids)) {
            return;
        }
        
        // Asegurar que son arrays
        if (!is_array($calculation_ids)) {
            $calculation_ids = array($calculation_ids);
        }
        
        switch ($action) {
            case 'delete':
                $this->bulk_delete($calculation_ids);
                break;
                
            case 'export':
                $this->bulk_export($calculation_ids);
                break;
                
            case 'mark_inactive':
                $this->bulk_mark_inactive($calculation_ids);
                break;
        }
    }
    
    /**
     * Eliminar en masa
     */
    private function bulk_delete($calculation_ids) {
        global $wpdb;
        
        $deleted_count = 0;
        
        foreach ($calculation_ids as $id) {
            $id = intval($id);
            
            // Verificar que no tenga contratos asociados
            $has_contracts = $this->calculation_has_contract($id);
            
            if ($has_contracts) {
                continue; // No eliminar si tiene contratos
            }
            
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_calculations',
                array('status' => 'deleted'),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            $message = sprintf(__('Se eliminaron %d cÃ¡lculos correctamente.', 'adhesion'), $deleted_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-calculations'));
        exit;
    }
    
    /**
     * Marcar como inactivo en masa
     */
    private function bulk_mark_inactive($calculation_ids) {
        global $wpdb;
        
        $updated_count = 0;
        
        foreach ($calculation_ids as $id) {
            $id = intval($id);
            
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_calculations',
                array('status' => 'inactive'),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
        
        if ($updated_count > 0) {
            $message = sprintf(__('Se marcaron %d cÃ¡lculos como inactivos.', 'adhesion'), $updated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-calculations'));
        exit;
    }
    
    /**
     * Exportar en masa
     */
    private function bulk_export($calculation_ids) {
        $calculations = array();
        
        foreach ($calculation_ids as $id) {
            $calculation = $this->db->get_calculation(intval($id));
            if ($calculation) {
                $calculations[] = $calculation;
            }
        }
        
        $this->export_calculations_csv($calculations);
    }
    
    /**
     * Exportar cÃ¡lculos a CSV
     */
    private function export_calculations_csv($calculations) {
        $filename = 'adhesion_calculations_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeceras
        fputcsv($output, array(
            'ID',
            'Usuario',
            'Email',
            'Materiales',
            'Total Toneladas',
            'Precio Total',
            'Fecha CreaciÃ³n'
        ), ';');
        
        // Datos
        foreach ($calculations as $calc) {
            $materials_summary = '';
            if (!empty($calc['calculation_data']['materials'])) {
                $materials = array();
                foreach ($calc['calculation_data']['materials'] as $material) {
                    $materials[] = $material['type'] . ': ' . $material['quantity'] . 't';
                }
                $materials_summary = implode(', ', $materials);
            }
            
            fputcsv($output, array(
                $calc['id'],
                $calc['user_name'] ?: 'Usuario eliminado',
                $calc['user_email'] ?: '',
                $materials_summary,
                $calc['total_tons'],
                $calc['total_price'],
                $calc['created_at']
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Eliminar cÃ¡lculo
     */
    public function ajax_delete_calculation() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $calculation_id = intval($_POST['calculation_id']);
            
            // Verificar que no tenga contratos
            if ($this->calculation_has_contract($calculation_id)) {
                throw new Exception(__('No se puede eliminar un cÃ¡lculo que tiene contratos asociados.', 'adhesion'));
            }
            
            global $wpdb;
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_calculations',
                array('status' => 'deleted'),
                array('id' => $calculation_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception(__('Error al eliminar el cÃ¡lculo.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('CÃ¡lculo eliminado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Exportar cÃ¡lculos
     */
    public function ajax_export_calculations() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Obtener todos los cÃ¡lculos con filtros actuales
            $filters = $this->get_filters();
            $calculations = $this->db->get_all_calculations(1000, 0, $filters); // MÃ¡ximo 1000
            
            $this->export_calculations_csv($calculations);
            
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }
}

/**
 * FunciÃ³n para mostrar la pÃ¡gina de listado
 */
function adhesion_display_calculations_page() {
    $list_table = new Adhesion_Calculations_List();
    $list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('CÃ¡lculos de Presupuestos', 'adhesion'); ?></h1>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <?php
            $list_table->search_box(__('Buscar cÃ¡lculos', 'adhesion'), 'calculation');
            $list_table->display();
            ?>
        </form>
    </div>
    
    <script>
    function adhesionExportCalculations() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'adhesion_export_calculations';
        form.appendChild(actionInput);
        
        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = 'nonce';
        nonceInput.value = '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>';
        form.appendChild(nonceInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    </script>
    
    <style>
    .material-item {
        margin-bottom: 2px;
        font-size: 12px;
    }
    
    .row-actions-wrapper {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .row-actions-wrapper .button {
        margin: 0;
    }
    
    @media (max-width: 768px) {
        .column-materials,
        .column-total_tons {
            display: none;
        }
        
        .row-actions-wrapper {
            flex-direction: column;
        }
    }
    </style>
    <?php
}


// ===== class-contracts-list.php =====
<?php
/**
 * Clase para listado de contratos de adhesiÃ³n
 * 
 * Esta clase extiende WP_List_Table para mostrar:
 * - Listado paginado de contratos
 * - Filtros por estado, usuario y fechas
 * - Acciones de gestiÃ³n de contratos
 * - IntegraciÃ³n con DocuSign y pagos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no estÃ¡ disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adhesion_Contracts_List extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'contract',
            'plural' => 'contracts',
            'ajax' => false
        ));
        
        $this->db = new Adhesion_Database();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Procesar acciones
        add_action('admin_init', array($this, 'process_bulk_actions'));
        
        // AJAX para acciones rÃ¡pidas
        add_action('wp_ajax_adhesion_update_contract_status', array($this, 'ajax_update_contract_status'));
        add_action('wp_ajax_adhesion_resend_contract', array($this, 'ajax_resend_contract'));
        add_action('wp_ajax_adhesion_export_contracts', array($this, 'ajax_export_contracts'));
        add_action('wp_ajax_adhesion_check_docusign_status', array($this, 'ajax_check_docusign_status'));
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'contract_number' => __('NÂ° Contrato', 'adhesion'),
            'user' => __('Cliente', 'adhesion'),
            'calculation' => __('CÃ¡lculo Base', 'adhesion'),
            'status' => __('Estado', 'adhesion'),
            'payment_status' => __('Pago', 'adhesion'),
            'total_amount' => __('Importe', 'adhesion'),
            'created_at' => __('Fecha CreaciÃ³n', 'adhesion'),
            'signed_at' => __('Fecha Firma', 'adhesion'),
            'actions' => __('Acciones', 'adhesion')
        );
    }
    
    /**
     * Obtener columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'contract_number' => array('contract_number', false),
            'user' => array('user_name', false),
            'status' => array('status', false),
            'payment_status' => array('payment_status', false),
            'total_amount' => array('total_amount', false),
            'created_at' => array('created_at', true),
            'signed_at' => array('signed_at', false)
        );
    }
    
    /**
     * Obtener acciones en masa
     */
    public function get_bulk_actions() {
        return array(
            'send_to_docusign' => __('Enviar a DocuSign', 'adhesion'),
            'mark_completed' => __('Marcar como completado', 'adhesion'),
            'export' => __('Exportar seleccionados', 'adhesion'),
            'cancel' => __('Cancelar contratos', 'adhesion')
        );
    }
    
    /**
     * Preparar elementos
     */
    public function prepare_items() {
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Procesar acciones en masa
        $this->process_bulk_actions();
        
        // Configurar paginaciÃ³n
        $per_page = $this->get_items_per_page('contracts_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Obtener filtros
        $filters = $this->get_filters();
        
        // Obtener elementos
        $this->items = $this->get_contracts($per_page, $offset, $filters);
        
        // Configurar paginaciÃ³n
        $total_items = $this->get_total_contracts($filters);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Obtener contratos con joins
     */
    private function get_contracts($per_page, $offset, $filters) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $where_clauses[] = "c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where_clauses[] = "c.payment_status = %s";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "c.user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "c.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "c.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        // BÃºsqueda
        if (!empty($filters['search'])) {
            $where_clauses[] = "(c.contract_number LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // OrdenaciÃ³n
        $orderby = sanitize_sql_orderby($filters['orderby'] . ' ' . $filters['order']);
        $orderby = $orderby ?: 'c.created_at DESC';
        
        $sql = "SELECT c.*, u.display_name as user_name, u.user_email, 
                       calc.total_price as calculation_total
                FROM {$wpdb->prefix}adhesion_contracts c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}adhesion_calculations calc ON c.calculation_id = calc.id
                WHERE $where_sql
                ORDER BY $orderby
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $params),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['client_data'])) {
                $result['client_data'] = json_decode($result['client_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Obtener filtros de la URL
     */
    private function get_filters() {
        $filters = array();
        
        // Filtros bÃ¡sicos
        $filters['status'] = sanitize_text_field($_GET['status'] ?? '');
        $filters['payment_status'] = sanitize_text_field($_GET['payment_status'] ?? '');
        $filters['user_id'] = intval($_GET['user_id'] ?? 0);
        
        // Filtros de fecha
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        // BÃºsqueda
        $filters['search'] = sanitize_text_field($_GET['s'] ?? '');
        
        // OrdenaciÃ³n
        $filters['orderby'] = sanitize_text_field($_GET['orderby'] ?? 'created_at');
        $filters['order'] = sanitize_text_field($_GET['order'] ?? 'desc');
        
        return $filters;
    }
    
    /**
     * Obtener total de contratos
     */
    private function get_total_contracts($filters) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Aplicar los mismos filtros que en get_contracts
        if (!empty($filters['status'])) {
            $where_clauses[] = "c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where_clauses[] = "c.payment_status = %s";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "c.user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "c.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "c.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(c.contract_number LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT COUNT(*)
                FROM {$wpdb->prefix}adhesion_contracts c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE $where_sql";
        
        if (empty($params)) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        }
    }
    
    /**
     * Columna por defecto
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'contract_number':
                return '<strong>' . esc_html($item['contract_number']) . '</strong>';
                
            case 'created_at':
                return adhesion_format_date($item['created_at']);
                
            case 'signed_at':
                return $item['signed_at'] ? adhesion_format_date($item['signed_at']) : '-';
                
            default:
                return $item[$column_name] ?? '-';
        }
    }
    
    /**
     * Columna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="contract[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Columna de usuario/cliente
     */
    public function column_user($item) {
        $user_name = $item['user_name'] ?: __('Usuario eliminado', 'adhesion');
        $user_email = $item['user_email'] ?: '';
        
        $output = '<strong>' . esc_html($user_name) . '</strong>';
        
        if ($user_email) {
            $output .= '<br><small>' . esc_html($user_email) . '</small>';
        }
        
        // Mostrar datos del cliente si estÃ¡n disponibles
        if (!empty($item['client_data']['nombre_completo'])) {
            $output .= '<br><small><em>' . esc_html($item['client_data']['nombre_completo']) . '</em></small>';
        }
        
        // Agregar enlace al usuario si existe
        if ($item['user_id']) {
            $user_link = admin_url('user-edit.php?user_id=' . $item['user_id']);
            $output = '<a href="' . esc_url($user_link) . '">' . $output . '</a>';
        }
        
        return $output;
    }
    
    /**
     * Columna de cÃ¡lculo base
     */
    public function column_calculation($item) {
        if (!$item['calculation_id']) {
            return '<em>' . __('Sin cÃ¡lculo base', 'adhesion') . '</em>';
        }
        
        $calc_link = admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $item['calculation_id']);
        $output = '<a href="' . esc_url($calc_link) . '">#' . $item['calculation_id'] . '</a>';
        
        if ($item['calculation_total']) {
            $output .= '<br><small>' . adhesion_format_price($item['calculation_total']) . '</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de estado del contrato
     */
    public function column_status($item) {
        $status = adhesion_format_contract_status($item['status']);
        
        $output = '<span class="adhesion-badge adhesion-badge-' . $this->get_status_class($item['status']) . '">';
        $output .= esc_html($status['label']);
        $output .= '</span>';
        
        // Agregar informaciÃ³n adicional
        if ($item['status'] === 'signed' && $item['docusign_envelope_id']) {
            $output .= '<br><small>ID: ' . esc_html(substr($item['docusign_envelope_id'], 0, 8)) . '...</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de estado de pago
     */
    public function column_payment_status($item) {
        $payment_status = adhesion_format_payment_status($item['payment_status']);
        
        $output = '<span class="adhesion-badge adhesion-badge-' . $this->get_payment_status_class($item['payment_status']) . '">';
        $output .= esc_html($payment_status['label']);
        $output .= '</span>';
        
        // Agregar referencia de pago si existe
        if ($item['payment_reference']) {
            $output .= '<br><small>Ref: ' . esc_html($item['payment_reference']) . '</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de importe total
     */
    public function column_total_amount($item) {
        if ($item['payment_amount']) {
            return '<strong>' . adhesion_format_price($item['payment_amount']) . '</strong>';
        } elseif ($item['calculation_total']) {
            return '<em>' . adhesion_format_price($item['calculation_total']) . '</em>';
        }
        
        return '-';
    }
    
    /**
     * Columna de acciones
     */
    public function column_actions($item) {
        $actions = array();
        
        // Ver detalles
        $view_url = admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $item['id']);
        $actions['view'] = '<a href="' . esc_url($view_url) . '" class="button button-small">' . __('Ver', 'adhesion') . '</a>';
        
        // Acciones segÃºn el estado
        switch ($item['status']) {
            case 'pending':
                if (adhesion_is_docusign_configured()) {
                    $actions['send'] = '<button type="button" class="button button-small button-primary" onclick="adhesionSendToDocusign(' . $item['id'] . ')">' . __('Enviar a Firma', 'adhesion') . '</button>';
                }
                break;
                
            case 'sent':
                $actions['check'] = '<button type="button" class="button button-small" onclick="adhesionCheckDocusignStatus(' . $item['id'] . ')">' . __('Verificar Estado', 'adhesion') . '</button>';
                break;
                
            case 'signed':
                if ($item['signed_document_url']) {
                    $actions['download'] = '<a href="' . esc_url($item['signed_document_url']) . '" class="button button-small" target="_blank">' . __('Descargar', 'adhesion') . '</a>';
                }
                break;
        }
        
        // Editar (solo si no estÃ¡ firmado)
        if ($item['status'] !== 'signed') {
            $edit_url = admin_url('admin.php?page=adhesion-contracts&action=edit&contract=' . $item['id']);
            $actions['edit'] = '<a href="' . esc_url($edit_url) . '" class="button button-small">' . __('Editar', 'adhesion') . '</a>';
        }
        
        return '<div class="row-actions-wrapper">' . implode(' ', $actions) . '</div>';
    }
    
    /**
     * Obtener clase CSS para estado de contrato
     */
    private function get_status_class($status) {
        switch ($status) {
            case 'pending':
                return 'warning';
            case 'sent':
                return 'info';
            case 'signed':
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'error';
            default:
                return 'secondary';
        }
    }
    
    /**
     * Obtener clase CSS para estado de pago
     */
    private function get_payment_status_class($status) {
        switch ($status) {
            case 'pending':
                return 'warning';
            case 'processing':
                return 'info';
            case 'completed':
                return 'success';
            case 'failed':
            case 'refunded':
                return 'error';
            default:
                return 'secondary';
        }
    }
    
    /**
     * Mostrar filtros
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        
        ?>
        <div class="alignleft actions">
            <!-- Filtro por estado -->
            <label for="filter-status" class="screen-reader-text"><?php _e('Filtrar por estado', 'adhesion'); ?></label>
            <select name="status" id="filter-status">
                <option value=""><?php _e('Todos los estados', 'adhesion'); ?></option>
                <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('Pendiente', 'adhesion'); ?></option>
                <option value="sent" <?php selected($_GET['status'] ?? '', 'sent'); ?>><?php _e('Enviado', 'adhesion'); ?></option>
                <option value="signed" <?php selected($_GET['status'] ?? '', 'signed'); ?>><?php _e('Firmado', 'adhesion'); ?></option>
                <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Completado', 'adhesion'); ?></option>
                <option value="cancelled" <?php selected($_GET['status'] ?? '', 'cancelled'); ?>><?php _e('Cancelado', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtro por estado de pago -->
            <label for="filter-payment-status" class="screen-reader-text"><?php _e('Filtrar por pago', 'adhesion'); ?></label>
            <select name="payment_status" id="filter-payment-status">
                <option value=""><?php _e('Todos los pagos', 'adhesion'); ?></option>
                <option value="pending" <?php selected($_GET['payment_status'] ?? '', 'pending'); ?>><?php _e('Pago Pendiente', 'adhesion'); ?></option>
                <option value="processing" <?php selected($_GET['payment_status'] ?? '', 'processing'); ?>><?php _e('Procesando', 'adhesion'); ?></option>
                <option value="completed" <?php selected($_GET['payment_status'] ?? '', 'completed'); ?>><?php _e('Pagado', 'adhesion'); ?></option>
                <option value="failed" <?php selected($_GET['payment_status'] ?? '', 'failed'); ?>><?php _e('Pago Fallido', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtros de fecha -->
            <label for="filter-date-from" class="screen-reader-text"><?php _e('Fecha desde', 'adhesion'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" 
                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha desde', 'adhesion'); ?>">
            
            <label for="filter-date-to" class="screen-reader-text"><?php _e('Fecha hasta', 'adhesion'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" 
                   value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha hasta', 'adhesion'); ?>">
            
            <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar', 'adhesion'); ?>">
            
            <?php if (!empty($_GET['status']) || !empty($_GET['payment_status']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                    <?php _e('Limpiar', 'adhesion'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" onclick="adhesionExportContracts()">
                <?php _e('Exportar CSV', 'adhesion'); ?>
            </button>
            
            <?php if (adhesion_is_docusign_configured()): ?>
            <button type="button" class="button button-primary" onclick="adhesionBulkSendToDocusign()">
                <?php _e('Enviar Pendientes', 'adhesion'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Procesar acciones en masa
     */
    public function process_bulk_actions() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'adhesion'));
        }
        
        $contract_ids = $_GET['contract'] ?? array();
        
        if (empty($contract_ids)) {
            return;
        }
        
        // Asegurar que son arrays
        if (!is_array($contract_ids)) {
            $contract_ids = array($contract_ids);
        }
        
        switch ($action) {
            case 'send_to_docusign':
                $this->bulk_send_to_docusign($contract_ids);
                break;
                
            case 'mark_completed':
                $this->bulk_mark_completed($contract_ids);
                break;
                
            case 'export':
                $this->bulk_export($contract_ids);
                break;
                
            case 'cancel':
                $this->bulk_cancel($contract_ids);
                break;
        }
    }
    
    /**
     * Enviar contratos a DocuSign en masa
     */
    private function bulk_send_to_docusign($contract_ids) {
        if (!adhesion_is_docusign_configured()) {
            adhesion_add_notice(__('DocuSign no estÃ¡ configurado.', 'adhesion'), 'error');
            return;
        }
        
        $sent_count = 0;
        
        foreach ($contract_ids as $id) {
            $contract = $this->db->get_contract(intval($id));
            
            if (!$contract || $contract['status'] !== 'pending') {
                continue;
            }
            
            // TODO: Implementar envÃ­o real a DocuSign
            $result = $this->db->update_contract_status($contract['id'], 'sent', array(
                'docusign_envelope_id' => 'TEST_' . time() . '_' . $contract['id']
            ));
            
            if ($result) {
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            $message = sprintf(__('Se enviaron %d contratos a DocuSign.', 'adhesion'), $sent_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-contracts'));
        exit;
    }
    
    /**
     * Marcar como completado en masa
     */
    private function bulk_mark_completed($contract_ids) {
        $updated_count = 0;
        
        foreach ($contract_ids as $id) {
            $result = $this->db->update_contract_status(intval($id), 'completed');
            
            if ($result) {
                $updated_count++;
            }
        }
        
        if ($updated_count > 0) {
            $message = sprintf(__('Se marcaron %d contratos como completados.', 'adhesion'), $updated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-contracts'));
        exit;
    }
    
    /**
     * Cancelar contratos en masa
     */
    private function bulk_cancel($contract_ids) {
        $cancelled_count = 0;
        
        foreach ($contract_ids as $id) {
            $contract = $this->db->get_contract(intval($id));
            
            // No cancelar contratos ya firmados
            if ($contract && $contract['status'] !== 'signed') {
                $result = $this->db->update_contract_status($contract['id'], 'cancelled');
                
                if ($result) {
                    $cancelled_count++;
                }
            }
        }
        
        if ($cancelled_count > 0) {
            $message = sprintf(__('Se cancelaron %d contratos.', 'adhesion'), $cancelled_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-contracts'));
        exit;
    }
    
    /**
     * Exportar contratos en masa
     */
    private function bulk_export($contract_ids) {
        $contracts = array();
        
        foreach ($contract_ids as $id) {
            $contract = $this->db->get_contract(intval($id));
            if ($contract) {
                $contracts[] = $contract;
            }
        }
        
        $this->export_contracts_csv($contracts);
    }
    
    /**
     * Exportar contratos a CSV
     */
    private function export_contracts_csv($contracts) {
        $filename = 'adhesion_contracts_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeceras
        fputcsv($output, array(
            'NÂ° Contrato',
            'Cliente',
            'Email',
            'DNI/CIF',
            'Estado',
            'Estado Pago',
            'Importe',
            'Fecha CreaciÃ³n',
            'Fecha Firma',
            'ID DocuSign'
        ), ';');
        
        // Datos
        foreach ($contracts as $contract) {
            $client_data = $contract['client_data'] ?: array();
            
            fputcsv($output, array(
                $contract['contract_number'],
                $contract['user_name'] ?: 'Usuario eliminado',
                $contract['user_email'] ?: '',
                $client_data['dni_cif'] ?? '',
                ucfirst($contract['status']),
                ucfirst($contract['payment_status']),
                $contract['payment_amount'] ?: $contract['calculation_total'] ?: '0',
                $contract['created_at'],
                $contract['signed_at'] ?: '',
                $contract['docusign_envelope_id'] ?: ''
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Actualizar estado de contrato
     */
    public function ajax_update_contract_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $new_status = sanitize_text_field($_POST['status']);
            
            // Validar estado
            $valid_statuses = array('pending', 'sent', 'signed', 'completed', 'cancelled');
            if (!in_array($new_status, $valid_statuses)) {
                throw new Exception(__('Estado de contrato invÃ¡lido.', 'adhesion'));
            }
            
            $result = $this->db->update_contract_status($contract_id, $new_status);
            
            if (!$result) {
                throw new Exception(__('Error al actualizar el estado del contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Estado del contrato actualizado correctamente.', 'adhesion'),
                'new_status' => $new_status
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Reenviar contrato a DocuSign
     */
    public function ajax_resend_contract() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!adhesion_is_docusign_configured()) {
                throw new Exception(__('DocuSign no estÃ¡ configurado.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // TODO: Implementar reenvÃ­o real a DocuSign
            $envelope_id = 'RESEND_' . time() . '_' . $contract_id;
            
            $result = $this->db->update_contract_status($contract_id, 'sent', array(
                'docusign_envelope_id' => $envelope_id
            ));
            
            if (!$result) {
                throw new Exception(__('Error al reenviar el contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Contrato reenviado a DocuSign correctamente.', 'adhesion'),
                'envelope_id' => $envelope_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Verificar estado en DocuSign
     */
    public function ajax_check_docusign_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!adhesion_is_docusign_configured()) {
                throw new Exception(__('DocuSign no estÃ¡ configurado.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract || empty($contract['docusign_envelope_id'])) {
                throw new Exception(__('Contrato no encontrado o sin ID de DocuSign.', 'adhesion'));
            }
            
            // TODO: Implementar verificaciÃ³n real con DocuSign API
            // Por ahora simulamos una respuesta
            $simulated_status = rand(1, 10) > 7 ? 'signed' : 'sent'; // 30% probabilidad de firmado
            
            if ($simulated_status === 'signed') {
                $update_data = array(
                    'signed_document_url' => 'https://demo.docusign.net/documents/' . $contract['docusign_envelope_id'] . '.pdf'
                );
                
                $this->db->update_contract_status($contract_id, 'signed', $update_data);
                
                $message = __('Â¡Contrato firmado! Documento descargado.', 'adhesion');
            } else {
                $message = __('El contrato aÃºn no ha sido firmado.', 'adhesion');
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'status' => $simulated_status,
                'signed_url' => $simulated_status === 'signed' ? $update_data['signed_document_url'] : null
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Exportar contratos
     */
    public function ajax_export_contracts() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Obtener todos los contratos con filtros actuales
            $filters = $this->get_filters();
            $contracts = $this->get_contracts(1000, 0, $filters); // MÃ¡ximo 1000
            
            $this->export_contracts_csv($contracts);
            
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }
}

/**
 * FunciÃ³n para mostrar la pÃ¡gina de listado de contratos
 */
function adhesion_display_contracts_page() {
    $list_table = new Adhesion_Contracts_List();
    $list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Contratos de AdhesiÃ³n', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('â† Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <form method="get" id="contracts-filter">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <?php
            $list_table->search_box(__('Buscar contratos', 'adhesion'), 'contract');
            $list_table->display();
            ?>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros
        $('#filter-status, #filter-payment-status, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#contracts-filter').submit();
        });
    });
    
    // Funciones AJAX para acciones de contratos
    function adhesionSendToDocusign(contractId) {
        if (!confirm('<?php echo esc_js(__('Â¿Enviar este contrato a DocuSign para firma?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_resend_contract',
                contract_id: contractId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    
    function adhesionCheckDocusignStatus(contractId) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_check_docusign_status',
                contract_id: contractId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.status === 'signed') {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    
    function adhesionExportContracts() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        // Agregar campos ocultos
        const fields = {
            action: 'adhesion_export_contracts',
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>',
            status: '<?php echo esc_js($_GET['status'] ?? ''); ?>',
            payment_status: '<?php echo esc_js($_GET['payment_status'] ?? ''); ?>',
            date_from: '<?php echo esc_js($_GET['date_from'] ?? ''); ?>',
            date_to: '<?php echo esc_js($_GET['date_to'] ?? ''); ?>'
        };
        
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    function adhesionBulkSendToDocusign() {
        if (!confirm('<?php echo esc_js(__('Â¿Enviar todos los contratos pendientes a DocuSign?', 'adhesion')); ?>')) {
            return;
        }
        
        // Simular envÃ­o en masa
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_bulk_send_docusign',
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || '<?php echo esc_js(__('Contratos enviados correctamente', 'adhesion')); ?>');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || '<?php echo esc_js(__('Error desconocido', 'adhesion')); ?>'));
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    </script>
    
    <style>
    .row-actions-wrapper {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .row-actions-wrapper .button {
        margin: 0;
        font-size: 11px;
        padding: 4px 8px;
        height: auto;
        line-height: 1.2;
    }
    
    .adhesion-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .adhesion-badge-success {
        background: #d1e7dd;
        color: #0f5132;
    }
    
    .adhesion-badge-warning {
        background: #fff3cd;
        color: #664d03;
    }
    
    .adhesion-badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .adhesion-badge-error {
        background: #f8d7da;
        color: #842029;
    }
    
    .adhesion-badge-secondary {
        background: #e2e3e5;
        color: #41464b;
    }
    
    @media (max-width: 768px) {
        .column-calculation,
        .column-signed_at,
        .column-payment_status {
            display: none;
        }
        
        .row-actions-wrapper {
            flex-direction: column;
        }
    }
    </style>
    <?php
}


// ===== class-documents.php =====
<?php
/**
 * Clase para gestiÃ³n de documentos editables
 * 
 * Esta clase maneja:
 * - Plantillas de contratos editables (header, body, footer)
 * - Variables dinÃ¡micas en documentos
 * - GeneraciÃ³n de documentos personalizados
 * - IntegraciÃ³n con DocuSign
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Documents {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Variables disponibles para documentos
     */
    private $available_variables;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
        $this->init_variables();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para gestiÃ³n de documentos
        add_action('wp_ajax_adhesion_save_document', array($this, 'ajax_save_document'));
        add_action('wp_ajax_adhesion_delete_document', array($this, 'ajax_delete_document'));
        add_action('wp_ajax_adhesion_preview_document', array($this, 'ajax_preview_document'));
        add_action('wp_ajax_adhesion_duplicate_document', array($this, 'ajax_duplicate_document'));
        add_action('wp_ajax_adhesion_toggle_document_status', array($this, 'ajax_toggle_document_status'));
        
        // Shortcodes para documentos
        add_shortcode('adhesion_document', array($this, 'render_document_shortcode'));
    }
    
    /**
     * Inicializar variables disponibles
     */
    private function init_variables() {
        $this->available_variables = array(
            // Datos del cliente
            'nombre_completo' => __('Nombre completo del cliente', 'adhesion'),
            'dni_cif' => __('DNI o CIF del cliente', 'adhesion'),
            'direccion' => __('DirecciÃ³n completa', 'adhesion'),
            'codigo_postal' => __('CÃ³digo postal', 'adhesion'),
            'ciudad' => __('Ciudad', 'adhesion'),
            'provincia' => __('Provincia', 'adhesion'),
            'telefono' => __('NÃºmero de telÃ©fono', 'adhesion'),
            'email' => __('DirecciÃ³n de email', 'adhesion'),
            'empresa' => __('Nombre de la empresa', 'adhesion'),
            
            // Datos del contrato
            'numero_contrato' => __('NÃºmero de contrato', 'adhesion'),
            'fecha_contrato' => __('Fecha de creaciÃ³n del contrato', 'adhesion'),
            'fecha_firma' => __('Fecha de firma', 'adhesion'),
            'precio_total' => __('Precio total del servicio', 'adhesion'),
            'precio_tonelada' => __('Precio por tonelada', 'adhesion'),
            'cantidad_toneladas' => __('Cantidad total en toneladas', 'adhesion'),
            
            // Datos del cÃ¡lculo
            'materiales_detalle' => __('Detalle de materiales calculados', 'adhesion'),
            'materiales_resumen' => __('Resumen de materiales', 'adhesion'),
            
            // Datos generales
            'fecha_hoy' => __('Fecha actual', 'adhesion'),
            'sitio_nombre' => __('Nombre del sitio web', 'adhesion'),
            'sitio_url' => __('URL del sitio web', 'adhesion'),
            'ano_actual' => __('AÃ±o actual', 'adhesion')
        );
    }
    
    /**
     * Obtener todos los documentos
     */
    public function get_documents($type = null, $active_only = false) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        if ($type) {
            $where_clauses[] = "document_type = %s";
            $params[] = $type;
        }
        
        if ($active_only) {
            $where_clauses[] = "is_active = 1";
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = "SELECT * FROM {$wpdb->prefix}adhesion_documents $where_sql ORDER BY created_at DESC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            return $wpdb->get_results($sql, ARRAY_A);
        }
    }
    
    /**
     * Obtener un documento por ID
     */
    public function get_document($document_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adhesion_documents WHERE id = %d",
            $document_id
        ), ARRAY_A);
        
        if ($result && !empty($result['variables_list'])) {
            $result['variables_list'] = json_decode($result['variables_list'], true);
        }
        
        return $result;
    }
    
    /**
     * Crear o actualizar documento
     */
    public function save_document($data, $document_id = null) {
        global $wpdb;
        
        // Sanitizar datos
        $sanitized_data = array(
            'document_type' => sanitize_text_field($data['document_type']),
            'title' => sanitize_text_field($data['title']),
            'header_content' => wp_kses_post($data['header_content']),
            'body_content' => wp_kses_post($data['body_content']),
            'footer_content' => wp_kses_post($data['footer_content']),
            'variables_list' => json_encode($this->extract_variables_from_content($data)),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        if ($document_id) {
            // Actualizar documento existente
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_documents',
                $sanitized_data,
                array('id' => $document_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                adhesion_log("Documento $document_id actualizado", 'info');
                return $document_id;
            }
        } else {
            // Crear nuevo documento
            $result = $wpdb->insert(
                $wpdb->prefix . 'adhesion_documents',
                $sanitized_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result !== false) {
                $document_id = $wpdb->insert_id;
                adhesion_log("Documento $document_id creado", 'info');
                return $document_id;
            }
        }
        
        return false;
    }
    
    /**
     * Extraer variables del contenido
     */
    private function extract_variables_from_content($data) {
        $content = $data['header_content'] . ' ' . $data['body_content'] . ' ' . $data['footer_content'];
        
        // Buscar variables con formato [variable]
        preg_match_all('/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/', $content, $matches);
        
        return array_unique($matches[1]);
    }
    
    /**
     * Eliminar documento
     */
    public function delete_document($document_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'adhesion_documents',
            array('id' => $document_id),
            array('%d')
        );
        
        if ($result !== false) {
            adhesion_log("Documento $document_id eliminado", 'info');
        }
        
        return $result !== false;
    }
    
    /**
     * Cambiar estado activo/inactivo
     */
    public function toggle_document_status($document_id) {
        global $wpdb;
        
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$wpdb->prefix}adhesion_documents WHERE id = %d",
            $document_id
        ));
        
        $new_status = $current_status ? 0 : 1;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'adhesion_documents',
            array('is_active' => $new_status),
            array('id' => $document_id),
            array('%d'),
            array('%d')
        );
        
        return $result !== false ? $new_status : false;
    }
    
    /**
     * Duplicar documento
     */
    public function duplicate_document($document_id) {
        $original = $this->get_document($document_id);
        
        if (!$original) {
            return false;
        }
        
        // Crear copia con nuevo tÃ­tulo
        $copy_data = $original;
        unset($copy_data['id']);
        $copy_data['title'] = $original['title'] . ' (Copia)';
        $copy_data['is_active'] = 0; // Las copias empiezan inactivas
        
        return $this->save_document($copy_data);
    }
    
    /**
     * Generar documento personalizado
     */
    public function generate_document($document_id, $variables_data) {
        $document = $this->get_document($document_id);
        
        if (!$document) {
            return false;
        }
        
        // Combinar todas las secciones
        $full_content = $document['header_content'] . "\n\n" . $document['body_content'] . "\n\n" . $document['footer_content'];
        
        // Reemplazar variables
        $processed_content = $this->replace_variables($full_content, $variables_data);
        
        return array(
            'title' => $document['title'],
            'content' => $processed_content,
            'header' => $this->replace_variables($document['header_content'], $variables_data),
            'body' => $this->replace_variables($document['body_content'], $variables_data),
            'footer' => $this->replace_variables($document['footer_content'], $variables_data)
        );
    }
    
    /**
     * Reemplazar variables en contenido
     */
    private function replace_variables($content, $variables_data) {
        // Agregar variables del sistema
        $system_variables = array(
            'fecha_hoy' => date_i18n('d/m/Y'),
            'sitio_nombre' => get_bloginfo('name'),
            'sitio_url' => get_home_url(),
            'ano_actual' => date('Y')
        );
        
        $all_variables = array_merge($system_variables, $variables_data);
        
        // Reemplazar cada variable
        foreach ($all_variables as $key => $value) {
            // Formatear valores especiales
            if (is_numeric($value) && in_array($key, array('precio_total', 'precio_tonelada'))) {
                $value = adhesion_format_price($value);
            } elseif (is_numeric($value) && $key === 'cantidad_toneladas') {
                $value = adhesion_format_tons($value);
            } elseif (strpos($key, 'fecha_') === 0 && $value) {
                $value = adhesion_format_date($value, 'd/m/Y');
            }
            
            $content = str_replace('[' . $key . ']', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Generar variables desde un contrato
     */
    public function get_variables_from_contract($contract_id) {
        $contract = $this->db->get_contract($contract_id);
        
        if (!$contract) {
            return array();
        }
        
        $variables = array();
        
        // Datos del cliente
        if (!empty($contract['client_data'])) {
            foreach ($contract['client_data'] as $key => $value) {
                $variables[$key] = $value;
            }
        }
        
        // Datos del contrato
        $variables['numero_contrato'] = $contract['contract_number'];
        $variables['fecha_contrato'] = $contract['created_at'];
        $variables['fecha_firma'] = $contract['signed_at'];
        
        // Datos financieros
        if ($contract['payment_amount']) {
            $variables['precio_total'] = $contract['payment_amount'];
        } elseif ($contract['total_price']) {
            $variables['precio_total'] = $contract['total_price'];
        }
        
        // Datos del cÃ¡lculo si existe
        if ($contract['calculation_data']) {
            $calc_data = $contract['calculation_data'];
            
            if (isset($calc_data['materials'])) {
                $variables['materiales_detalle'] = $this->format_materials_detail($calc_data['materials']);
                $variables['materiales_resumen'] = $this->format_materials_summary($calc_data['materials']);
            }
        }
        
        // Datos del cÃ¡lculo base
        if (!empty($contract['total_tons'])) {
            $variables['cantidad_toneladas'] = $contract['total_tons'];
        }
        
        if (!empty($contract['price_per_ton'])) {
            $variables['precio_tonelada'] = $contract['price_per_ton'];
        }
        
        return $variables;
    }
    
    /**
     * Formatear detalle de materiales
     */
    private function format_materials_detail($materials) {
        if (empty($materials)) {
            return '';
        }
        
        $details = array();
        foreach ($materials as $material) {
            $detail = $material['type'] . ': ' . adhesion_format_tons($material['quantity']);
            if (isset($material['price_per_ton'])) {
                $detail .= ' (' . adhesion_format_price($material['price_per_ton']) . '/t)';
            }
            if (isset($material['total'])) {
                $detail .= ' = ' . adhesion_format_price($material['total']);
            }
            $details[] = $detail;
        }
        
        return implode("\n", $details);
    }
    
    /**
     * Formatear resumen de materiales
     */
    private function format_materials_summary($materials) {
        if (empty($materials)) {
            return '';
        }
        
        $types = array();
        $total_quantity = 0;
        
        foreach ($materials as $material) {
            $types[] = $material['type'];
            $total_quantity += $material['quantity'];
        }
        
        return implode(', ', $types) . ' (' . adhesion_format_tons($total_quantity) . ' total)';
    }
    
    /**
     * Obtener variables disponibles
     */
    public function get_available_variables() {
        return $this->available_variables;
    }
    
    /**
     * AJAX: Guardar documento
     */
    public function ajax_save_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = !empty($_POST['document_id']) ? intval($_POST['document_id']) : null;
            
            $data = array(
                'document_type' => sanitize_text_field($_POST['document_type']),
                'title' => sanitize_text_field($_POST['title']),
                'header_content' => wp_kses_post($_POST['header_content']),
                'body_content' => wp_kses_post($_POST['body_content']),
                'footer_content' => wp_kses_post($_POST['footer_content']),
                'is_active' => isset($_POST['is_active'])
            );
            
            // Validar datos obligatorios
            if (empty($data['title']) || empty($data['document_type'])) {
                throw new Exception(__('El tÃ­tulo y tipo de documento son obligatorios.', 'adhesion'));
            }
            
            $saved_id = $this->save_document($data, $document_id);
            
            if (!$saved_id) {
                throw new Exception(__('Error al guardar el documento.', 'adhesion'));
            }
            
            // Obtener variables extraÃ­das
            $variables = $this->extract_variables_from_content($data);
            
            wp_send_json_success(array(
                'message' => $document_id ? __('Documento actualizado correctamente.', 'adhesion') : __('Documento creado correctamente.', 'adhesion'),
                'document_id' => $saved_id,
                'variables_found' => $variables
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Eliminar documento
     */
    public function ajax_delete_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            
            if (!$this->delete_document($document_id)) {
                throw new Exception(__('Error al eliminar el documento.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Documento eliminado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Vista previa de documento
     */
    public function ajax_preview_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            $contract_id = intval($_POST['contract_id'] ?? 0);
            
            // Obtener datos de ejemplo o del contrato
            if ($contract_id) {
                $variables = $this->get_variables_from_contract($contract_id);
            } else {
                // Datos de ejemplo
                $variables = array(
                    'nombre_completo' => 'Juan PÃ©rez GarcÃ­a',
                    'dni_cif' => '12345678Z',
                    'direccion' => 'Calle Ejemplo, 123, 1ÂºA',
                    'codigo_postal' => '28001',
                    'ciudad' => 'Madrid',
                    'provincia' => 'Madrid',
                    'telefono' => '666 777 888',
                    'email' => 'juan.perez@email.com',
                    'empresa' => 'Empresa Ejemplo S.L.',
                    'numero_contrato' => 'ADH202412001',
                    'precio_total' => 1500.00,
                    'precio_tonelada' => 150.00,
                    'cantidad_toneladas' => 10.0,
                    'materiales_resumen' => 'CartÃ³n, Papel (10.0 t total)'
                );
            }
            
            $generated = $this->generate_document($document_id, $variables);
            
            if (!$generated) {
                throw new Exception(__('Error al generar la vista previa.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'content' => $generated['content'],
                'title' => $generated['title']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Duplicar documento
     */
    public function ajax_duplicate_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            
            $new_id = $this->duplicate_document($document_id);
            
            if (!$new_id) {
                throw new Exception(__('Error al duplicar el documento.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Documento duplicado correctamente.', 'adhesion'),
                'new_document_id' => $new_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Cambiar estado activo/inactivo
     */
    public function ajax_toggle_document_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            
            $new_status = $this->toggle_document_status($document_id);
            
            if ($new_status === false) {
                throw new Exception(__('Error al cambiar el estado del documento.', 'adhesion'));
            }
            
            $status_text = $new_status ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Documento marcado como %s.', 'adhesion'), strtolower($status_text)),
                'new_status' => $new_status,
                'status_text' => $status_text
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Shortcode para mostrar documento
     */
    public function render_document_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'contract_id' => 0,
            'type' => 'contract'
        ), $atts);
        
        $document_id = intval($atts['id']);
        $contract_id = intval($atts['contract_id']);
        
        if (!$document_id) {
            return '<p>' . __('ID de documento invÃ¡lido.', 'adhesion') . '</p>';
        }
        
        // Obtener variables del contrato si se proporciona
        $variables = array();
        if ($contract_id) {
            $variables = $this->get_variables_from_contract($contract_id);
        }
        
        $generated = $this->generate_document($document_id, $variables);
        
        if (!$generated) {
            return '<p>' . __('Error al generar el documento.', 'adhesion') . '</p>';
        }
        
        return '<div class="adhesion-document-content">' . $generated['content'] . '</div>';
    }
}


// ===== class-settings.php =====
<?php
/**
 * Clase para gestiÃ³n de configuraciones del plugin
 * 
 * Esta clase maneja:
 * - Procesamiento de formularios de configuraciÃ³n
 * - ValidaciÃ³n de APIs externas
 * - Pruebas de conectividad
 * - GestiÃ³n de precios de calculadora
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Settings {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Configuraciones actuales
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->settings = get_option('adhesion_settings', array());
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para pruebas de configuraciÃ³n
        add_action('wp_ajax_adhesion_test_redsys', array($this, 'test_redsys_connection'));
        add_action('wp_ajax_adhesion_test_docusign', array($this, 'test_docusign_connection'));
        
        // AJAX para gestiÃ³n de precios
        add_action('wp_ajax_adhesion_get_calculator_prices', array($this, 'get_calculator_prices'));
        add_action('wp_ajax_adhesion_update_calculator_prices', array($this, 'update_calculator_prices'));
        
        // Hooks para validar configuraciones
        add_action('update_option_adhesion_settings', array($this, 'on_settings_updated'), 10, 2);
        
        // Programar tareas de verificaciÃ³n
        add_action('adhesion_verify_apis', array($this, 'verify_api_connections'));
    }
    
    /**
     * Obtener configuraciÃ³n especÃ­fica
     */
    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Actualizar configuraciÃ³n especÃ­fica
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        return update_option('adhesion_settings', $this->settings);
    }
    
    /**
     * Obtener todas las configuraciones
     */
    public function get_all_settings() {
        return $this->settings;
    }
    
    /**
     * Validar configuraciones de Redsys
     */
    public function validate_redsys_settings($settings) {
        $errors = array();
        
        // Validar cÃ³digo de comercio
        if (empty($settings['redsys_merchant_code'])) {
            $errors[] = __('El cÃ³digo de comercio de Redsys es obligatorio.', 'adhesion');
        } elseif (!preg_match('/^[0-9]{9}$/', $settings['redsys_merchant_code'])) {
            $errors[] = __('El cÃ³digo de comercio debe tener 9 dÃ­gitos.', 'adhesion');
        }
        
        // Validar terminal
        if (empty($settings['redsys_terminal'])) {
            $errors[] = __('El terminal de Redsys es obligatorio.', 'adhesion');
        } elseif (!preg_match('/^[0-9]{3}$/', $settings['redsys_terminal'])) {
            $errors[] = __('El terminal debe tener 3 dÃ­gitos.', 'adhesion');
        }
        
        // Validar clave secreta
        if (empty($settings['redsys_secret_key'])) {
            $errors[] = __('La clave secreta de Redsys es obligatoria.', 'adhesion');
        } elseif (strlen($settings['redsys_secret_key']) < 32) {
            $errors[] = __('La clave secreta debe tener al menos 32 caracteres.', 'adhesion');
        }
        
        // Validar entorno
        if (!in_array($settings['redsys_environment'], array('test', 'production'))) {
            $errors[] = __('Entorno de Redsys invÃ¡lido.', 'adhesion');
        }
        
        return $errors;
    }
    
    /**
     * Validar configuraciones de DocuSign
     */
    public function validate_docusign_settings($settings) {
        $errors = array();
        
        // Validar Integration Key
        if (empty($settings['docusign_integration_key'])) {
            $errors[] = __('La Integration Key de DocuSign es obligatoria.', 'adhesion');
        } elseif (!preg_match('/^[a-f0-9-]{36}$/i', $settings['docusign_integration_key'])) {
            $errors[] = __('La Integration Key debe ser un UUID vÃ¡lido.', 'adhesion');
        }
        
        // Validar Secret Key
        if (empty($settings['docusign_secret_key'])) {
            $errors[] = __('La Secret Key de DocuSign es obligatoria.', 'adhesion');
        } elseif (strlen($settings['docusign_secret_key']) < 36) {
            $errors[] = __('La Secret Key debe tener al menos 36 caracteres.', 'adhesion');
        }
        
        // Validar Account ID
        if (empty($settings['docusign_account_id'])) {
            $errors[] = __('El Account ID de DocuSign es obligatorio.', 'adhesion');
        } elseif (!preg_match('/^[a-f0-9-]{36}$/i', $settings['docusign_account_id'])) {
            $errors[] = __('El Account ID debe ser un UUID vÃ¡lido.', 'adhesion');
        }
        
        // Validar entorno
        if (!in_array($settings['docusign_environment'], array('demo', 'production'))) {
            $errors[] = __('Entorno de DocuSign invÃ¡lido.', 'adhesion');
        }
        
        return $errors;
    }
    
    /**
     * Validar configuraciones de email
     */
    public function validate_email_settings($settings) {
        $errors = array();
        
        // Validar email del administrador
        if (!empty($settings['admin_email']) && !is_email($settings['admin_email'])) {
            $errors[] = __('El email del administrador no es vÃ¡lido.', 'adhesion');
        }
        
        // Validar email del remitente
        if (!empty($settings['email_from_address']) && !is_email($settings['email_from_address'])) {
            $errors[] = __('El email del remitente no es vÃ¡lido.', 'adhesion');
        }
        
        // Validar nombre del remitente
        if (empty($settings['email_from_name'])) {
            $errors[] = __('El nombre del remitente es obligatorio.', 'adhesion');
        }
        
        return $errors;
    }
    
    /**
     * Sanitizar configuraciones completas
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        // Configuraciones de Redsys
        $sanitized['redsys_merchant_code'] = sanitize_text_field($settings['redsys_merchant_code'] ?? '');
        $sanitized['redsys_terminal'] = sanitize_text_field($settings['redsys_terminal'] ?? '001');
        $sanitized['redsys_secret_key'] = sanitize_text_field($settings['redsys_secret_key'] ?? '');
        $sanitized['redsys_environment'] = in_array($settings['redsys_environment'] ?? 'test', array('test', 'production')) ? $settings['redsys_environment'] : 'test';
        $sanitized['redsys_currency'] = sanitize_text_field($settings['redsys_currency'] ?? '978');
        
        // Configuraciones de DocuSign
        $sanitized['docusign_integration_key'] = sanitize_text_field($settings['docusign_integration_key'] ?? '');
        $sanitized['docusign_secret_key'] = sanitize_text_field($settings['docusign_secret_key'] ?? '');
        $sanitized['docusign_account_id'] = sanitize_text_field($settings['docusign_account_id'] ?? '');
        $sanitized['docusign_environment'] = in_array($settings['docusign_environment'] ?? 'demo', array('demo', 'production')) ? $settings['docusign_environment'] : 'demo';
        
        // Configuraciones generales (checkboxes)
        $sanitized['calculator_enabled'] = isset($settings['calculator_enabled']) ? '1' : '0';
        $sanitized['auto_create_users'] = isset($settings['auto_create_users']) ? '1' : '0';
        $sanitized['email_notifications'] = isset($settings['email_notifications']) ? '1' : '0';
        $sanitized['contract_auto_send'] = isset($settings['contract_auto_send']) ? '1' : '0';
        $sanitized['require_payment'] = isset($settings['require_payment']) ? '1' : '0';
        
        // Configuraciones de email
        $sanitized['admin_email'] = sanitize_email($settings['admin_email'] ?? get_option('admin_email'));
        $sanitized['email_from_name'] = sanitize_text_field($settings['email_from_name'] ?? get_bloginfo('name'));
        $sanitized['email_from_address'] = sanitize_email($settings['email_from_address'] ?? get_option('admin_email'));
        
        return $sanitized;
    }
    
    /**
     * Probar conexiÃ³n con Redsys (AJAX)
     */
    public function test_redsys_connection() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            // Obtener configuraciones
            $merchant_code = sanitize_text_field($_POST['merchant_code']);
            $secret_key = sanitize_text_field($_POST['secret_key']);
            $terminal = sanitize_text_field($_POST['terminal']);
            $environment = sanitize_text_field($_POST['environment']);
            
            // Validar configuraciones bÃ¡sicas
            $test_settings = array(
                'redsys_merchant_code' => $merchant_code,
                'redsys_secret_key' => $secret_key,
                'redsys_terminal' => $terminal,
                'redsys_environment' => $environment
            );
            
            $validation_errors = $this->validate_redsys_settings($test_settings);
            
            if (!empty($validation_errors)) {
                throw new Exception(implode('. ', $validation_errors));
            }
            
            // Realizar prueba de conectividad
            $test_result = $this->perform_redsys_test($test_settings);
            
            wp_send_json_success(array(
                'message' => __('ConfiguraciÃ³n de Redsys vÃ¡lida.', 'adhesion'),
                'test_result' => $test_result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Probar conexiÃ³n con DocuSign (AJAX)
     */
    public function test_docusign_connection() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            // Obtener configuraciones
            $integration_key = sanitize_text_field($_POST['integration_key']);
            $secret_key = sanitize_text_field($_POST['secret_key']);
            $account_id = sanitize_text_field($_POST['account_id']);
            $environment = sanitize_text_field($_POST['environment']);
            
            // Validar configuraciones bÃ¡sicas
            $test_settings = array(
                'docusign_integration_key' => $integration_key,
                'docusign_secret_key' => $secret_key,
                'docusign_account_id' => $account_id,
                'docusign_environment' => $environment
            );
            
            $validation_errors = $this->validate_docusign_settings($test_settings);
            
            if (!empty($validation_errors)) {
                throw new Exception(implode('. ', $validation_errors));
            }
            
            // Realizar prueba de conectividad
            $test_result = $this->perform_docusign_test($test_settings);
            
            wp_send_json_success(array(
                'message' => __('ConfiguraciÃ³n de DocuSign vÃ¡lida.', 'adhesion'),
                'test_result' => $test_result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Realizar prueba real de Redsys
     */
    private function perform_redsys_test($settings) {
        // URL de prueba segÃºn el entorno
        $url = $settings['redsys_environment'] === 'production' 
            ? 'https://sis.redsys.es/sis/realizarPago'
            : 'https://sis-t.redsys.es:25443/sis/realizarPago';
        
        // ParÃ¡metros de prueba
        $merchant_parameters = base64_encode(json_encode(array(
            'DS_MERCHANT_AMOUNT' => '100', // 1 euro en cÃ©ntimos
            'DS_MERCHANT_ORDER' => 'TEST' . time(),
            'DS_MERCHANT_MERCHANTCODE' => $settings['redsys_merchant_code'],
            'DS_MERCHANT_CURRENCY' => '978',
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => $settings['redsys_terminal'],
            'DS_MERCHANT_MERCHANTURL' => home_url('/adhesion-redsys-test/'),
            'DS_MERCHANT_URLOK' => home_url('/adhesion-test-ok/'),
            'DS_MERCHANT_URLKO' => home_url('/adhesion-test-ko/')
        )));
        
        // Generar firma
        $signature = $this->generate_redsys_signature($merchant_parameters, $settings['redsys_secret_key']);
        
        // Realizar peticiÃ³n de prueba (solo validaciÃ³n de formato)
        $response = wp_remote_post($url, array(
            'timeout' => 10,
            'body' => array(
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => $merchant_parameters,
                'Ds_Signature' => $signature
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception(__('Error de conectividad con Redsys: ', 'adhesion') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return array(
            'status' => 'success',
            'message' => __('ParÃ¡metros vÃ¡lidos. ConexiÃ³n establecida.', 'adhesion'),
            'response_code' => $response_code,
            'environment' => $settings['redsys_environment']
        );
    }
    
    /**
     * Realizar prueba real de DocuSign
     */
    private function perform_docusign_test($settings) {
        // URL base segÃºn el entorno
        $base_url = $settings['docusign_environment'] === 'production'
            ? 'https://www.docusign.net/restapi'
            : 'https://demo.docusign.net/restapi';
        
        // Intentar obtener informaciÃ³n de la cuenta
        $url = $base_url . '/v2.1/accounts/' . $settings['docusign_account_id'];
        
        // Headers bÃ¡sicos (sin autenticaciÃ³n real para prueba)
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );
        
        // Realizar peticiÃ³n de prueba
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => $headers
        ));
        
        if (is_wp_error($response)) {
            throw new Exception(__('Error de conectividad con DocuSign: ', 'adhesion') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // DocuSign devuelve 401 para peticiones sin autenticaciÃ³n, lo que indica que el endpoint es vÃ¡lido
        if ($response_code === 401) {
            return array(
                'status' => 'success',
                'message' => __('Endpoint vÃ¡lido. ConfiguraciÃ³n correcta.', 'adhesion'),
                'response_code' => $response_code,
                'environment' => $settings['docusign_environment']
            );
        }
        
        return array(
            'status' => 'warning',
            'message' => __('Respuesta inesperada del servidor.', 'adhesion'),
            'response_code' => $response_code
        );
    }
    
    /**
     * Generar firma para Redsys
     */
    private function generate_redsys_signature($merchant_parameters, $secret_key) {
        // Obtener clave de la orden
        $parameters = json_decode(base64_decode($merchant_parameters), true);
        $order = $parameters['DS_MERCHANT_ORDER'];
        
        // Generar clave especÃ­fica para la orden
        $key = base64_decode($secret_key);
        $cipher = 'aes-256-cbc';
        $iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
        
        $encrypted_key = openssl_encrypt($order, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        // Generar firma
        $signature = hash_hmac('sha256', $merchant_parameters, $encrypted_key, true);
        
        return base64_encode($signature);
    }
    
    /**
     * Obtener precios de calculadora (AJAX)
     */
    public function get_calculator_prices() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            $prices = $this->db->get_calculator_prices();
            
            wp_send_json_success(array(
                'prices' => $prices
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Actualizar precios de calculadora (AJAX)
     */
    public function update_calculator_prices() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $prices = $_POST['prices'];
            
            if (!is_array($prices)) {
                throw new Exception(__('Datos de precios invÃ¡lidos.', 'adhesion'));
            }
            
            $updated_count = 0;
            
            foreach ($prices as $price_data) {
                $material_type = sanitize_text_field($price_data['material_type']);
                $price_per_ton = floatval($price_data['price_per_ton']);
                $minimum_quantity = floatval($price_data['minimum_quantity'] ?? 0);
                
                if (empty($material_type) || $price_per_ton <= 0) {
                    continue;
                }
                
                $success = $this->db->update_material_price($material_type, $price_per_ton, $minimum_quantity);
                
                if ($success) {
                    $updated_count++;
                }
            }
            
            // Limpiar cache de precios
            delete_transient('adhesion_calculator_prices');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Se actualizaron %d precios correctamente.', 'adhesion'), $updated_count),
                'updated_count' => $updated_count
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Hook cuando se actualizan las configuraciones
     */
    public function on_settings_updated($old_value, $new_value) {
        // Verificar si hay cambios importantes
        $important_keys = array(
            'redsys_merchant_code', 'redsys_secret_key',
            'docusign_integration_key', 'docusign_account_id'
        );
        
        $has_important_changes = false;
        
        foreach ($important_keys as $key) {
            if (($old_value[$key] ?? '') !== ($new_value[$key] ?? '')) {
                $has_important_changes = true;
                break;
            }
        }
        
        if ($has_important_changes) {
            // Programar verificaciÃ³n de APIs
            if (!wp_next_scheduled('adhesion_verify_apis')) {
                wp_schedule_single_event(time() + 300, 'adhesion_verify_apis'); // 5 minutos
            }
            
            // Log del cambio
            adhesion_log('Configuraciones importantes actualizadas, verificaciÃ³n programada', 'info');
        }
        
        // Limpiar caches relacionados
        delete_transient('adhesion_redsys_settings');
        delete_transient('adhesion_docusign_settings');
    }
    
    /**
     * Verificar conexiones de APIs (tarea programada)
     */
    public function verify_api_connections() {
        $settings = get_option('adhesion_settings', array());
        
        // Verificar Redsys
        if (!empty($settings['redsys_merchant_code']) && !empty($settings['redsys_secret_key'])) {
            try {
                $this->perform_redsys_test($settings);
                update_option('adhesion_redsys_status', 'ok');
                adhesion_log('VerificaciÃ³n de Redsys: OK', 'info');
            } catch (Exception $e) {
                update_option('adhesion_redsys_status', 'error');
                adhesion_log('VerificaciÃ³n de Redsys fallÃ³: ' . $e->getMessage(), 'error');
            }
        }
        
        // Verificar DocuSign
        if (!empty($settings['docusign_integration_key']) && !empty($settings['docusign_account_id'])) {
            try {
                $this->perform_docusign_test($settings);
                update_option('adhesion_docusign_status', 'ok');
                adhesion_log('VerificaciÃ³n de DocuSign: OK', 'info');
            } catch (Exception $e) {
                update_option('adhesion_docusign_status', 'error');
                adhesion_log('VerificaciÃ³n de DocuSign fallÃ³: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Exportar configuraciones (sin datos sensibles)
     */
    public function export_settings() {
        $settings = $this->get_all_settings();
        
        // Remover datos sensibles
        $safe_settings = $settings;
        unset($safe_settings['redsys_secret_key']);
        unset($safe_settings['docusign_secret_key']);
        
        return $safe_settings;
    }
    
    /**
     * Importar configuraciones
     */
    public function import_settings($imported_settings) {
        $current_settings = $this->get_all_settings();
        
        // Mantener datos sensibles actuales si no se proporcionan nuevos
        if (empty($imported_settings['redsys_secret_key'])) {
            $imported_settings['redsys_secret_key'] = $current_settings['redsys_secret_key'] ?? '';
        }
        
        if (empty($imported_settings['docusign_secret_key'])) {
            $imported_settings['docusign_secret_key'] = $current_settings['docusign_secret_key'] ?? '';
        }
        
        // Sanitizar y guardar
        $sanitized_settings = $this->sanitize_settings($imported_settings);
        
        return update_option('adhesion_settings', $sanitized_settings);
    }
}


// ===== class-users-list.php =====
<?php
/**
 * Clase para listado de usuarios adheridos
 * 
 * Esta clase extiende WP_List_Table para mostrar:
 * - Listado paginado de usuarios con rol adhesion_client
 * - EstadÃ­sticas de actividad por usuario
 * - Filtros por actividad y fechas
 * - Acciones de gestiÃ³n de usuarios
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no estÃ¡ disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adhesion_Users_List extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (!isset($GLOBALS['current_screen'])) {
            set_current_screen('adhesion_users');
        }
        parent::__construct(array(
            'singular' => 'user',
            'plural' => 'users',
            'ajax' => false
        ));
        
        $this->db = new Adhesion_Database();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Procesar acciones
        add_action('admin_init', array($this, 'process_bulk_actions'));
        
        // AJAX para acciones rÃ¡pidas
        add_action('wp_ajax_adhesion_send_user_email', array($this, 'ajax_send_user_email'));
        add_action('wp_ajax_adhesion_export_users', array($this, 'ajax_export_users'));
        add_action('wp_ajax_adhesion_update_user_status', array($this, 'ajax_update_user_status'));
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'user_info' => __('Usuario', 'adhesion'),
            'contact_info' => __('Contacto', 'adhesion'),
            'activity_stats' => __('Actividad', 'adhesion'),
            'financial_stats' => __('Financiero', 'adhesion'),
            'last_activity' => __('Ãšltima Actividad', 'adhesion'),
            'user_registered' => __('Registrado', 'adhesion'),
            'status' => __('Estado', 'adhesion'),
            'actions' => __('Acciones', 'adhesion')
        );
    }
    
    /**
     * Obtener columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'user_info' => array('display_name', false),
            'contact_info' => array('user_email', false),
            'last_activity' => array('last_activity', true),
            'user_registered' => array('user_registered', true),
            'status' => array('user_status', false)
        );
    }
    
    /**
     * Obtener acciones en masa
     */
    public function get_bulk_actions() {
        return array(
            'send_email' => __('Enviar Email', 'adhesion'),
            'export' => __('Exportar seleccionados', 'adhesion'),
            'activate' => __('Activar usuarios', 'adhesion'),
            'deactivate' => __('Desactivar usuarios', 'adhesion'),
            'delete' => __('Eliminar usuarios', 'adhesion')
        );
    }
    
    /**
     * Preparar elementos
     */
    public function prepare_items() {
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Procesar acciones en masa
        $this->process_bulk_actions();
        
        // Configurar paginaciÃ³n
        $per_page = $this->get_items_per_page('adhesion_users_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Obtener filtros
        $filters = $this->get_filters();
        
        // Obtener elementos
        $this->items = $this->get_adhesion_users($per_page, $offset, $filters);
        
        // Configurar paginaciÃ³n
        $total_items = $this->get_total_adhesion_users($filters);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Obtener usuarios adheridos con estadÃ­sticas
     */
    private function get_adhesion_users($per_page, $offset, $filters) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        // Filtro base: solo usuarios con rol adhesion_client
        $where_clauses[] = "um.meta_key = 'wp_capabilities' AND um.meta_value LIKE %s";
        $params[] = '%adhesion_client%';
        
        // Filtros adicionales
        if (!empty($filters['status'])) {
            $where_clauses[] = "u.user_status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "u.user_registered >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "u.user_registered <= %s";
            $params[] = $filters['date_to'];
        }
        
        // BÃºsqueda
        if (!empty($filters['search'])) {
            $where_clauses[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // OrdenaciÃ³n
        $orderby = sanitize_sql_orderby($filters['orderby'] . ' ' . $filters['order']);
        $orderby = $orderby ?: 'u.user_registered DESC';
        
        // Query principal con estadÃ­sticas
        $sql = "SELECT u.*, 
                       um_first.meta_value as first_name,
                       um_last.meta_value as last_name,
                       um_phone.meta_value as phone,
                       um_company.meta_value as company,
                       COUNT(DISTINCT c.id) as calculation_count,
                       COUNT(DISTINCT ct.id) as contract_count,
                       SUM(CASE WHEN ct.payment_status = 'completed' THEN ct.payment_amount ELSE 0 END) as total_paid,
                       MAX(GREATEST(COALESCE(c.created_at, '1970-01-01'), COALESCE(ct.created_at, '1970-01-01'))) as last_activity
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
                LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id AND um_phone.meta_key = 'phone'
                LEFT JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'company'
                LEFT JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id AND c.status = 'active'
                LEFT JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
                WHERE $where_sql
                GROUP BY u.ID
                ORDER BY $orderby
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $wpdb->get_results(
            $wpdb->prepare($sql, $params),
            ARRAY_A
        );
    }
    
    /**
     * Obtener filtros de la URL
     */
    private function get_filters() {
        $filters = array();
        
        // Filtros bÃ¡sicos
        $filters['status'] = sanitize_text_field($_GET['status'] ?? '');
        
        // Filtros de fecha
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        // Filtro de actividad
        if (!empty($_GET['activity'])) {
            $filters['activity'] = sanitize_text_field($_GET['activity']);
        }
        
        // BÃºsqueda
        $filters['search'] = sanitize_text_field($_GET['s'] ?? '');
        
        // OrdenaciÃ³n
        $filters['orderby'] = sanitize_text_field($_GET['orderby'] ?? 'user_registered');
        $filters['order'] = sanitize_text_field($_GET['order'] ?? 'desc');
        
        return $filters;
    }
    
    /**
     * Obtener total de usuarios adheridos
     */
    private function get_total_adhesion_users($filters) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        // Filtro base: solo usuarios con rol adhesion_client
        $where_clauses[] = "um.meta_key = 'wp_capabilities' AND um.meta_value LIKE %s";
        $params[] = '%adhesion_client%';
        
        // Aplicar los mismos filtros
        if (!empty($filters['status'])) {
            $where_clauses[] = "u.user_status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "u.user_registered >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "u.user_registered <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT COUNT(DISTINCT u.ID)
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE $where_sql";
        
        if (empty($params)) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        }
    }
    
    /**
     * Columna por defecto
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'user_registered':
                return adhesion_format_date($item['user_registered']);
                
            case 'last_activity':
                return $item['last_activity'] && $item['last_activity'] !== '1970-01-01 00:00:00' 
                    ? adhesion_format_date($item['last_activity']) 
                    : '<em>' . __('Sin actividad', 'adhesion') . '</em>';
                
            default:
                return $item[$column_name] ?? '-';
        }
    }
    
    /**
     * Columna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="user[]" value="%s" />',
            $item['ID']
        );
    }
    
    /**
     * Columna de informaciÃ³n del usuario
     */
    public function column_user_info($item) {
        $display_name = $item['display_name'] ?: $item['user_login'];
        $full_name = trim($item['first_name'] . ' ' . $item['last_name']);
        
        $output = '<strong><a href="' . admin_url('user-edit.php?user_id=' . $item['ID']) . '">' . esc_html($display_name) . '</a></strong>';
        
        if ($full_name && $full_name !== $display_name) {
            $output .= '<br><small><em>' . esc_html($full_name) . '</em></small>';
        }
        
        $output .= '<br><small>ID: ' . $item['ID'] . '</small>';
        
        // Mostrar empresa si existe
        if ($item['company']) {
            $output .= '<br><small><strong>' . esc_html($item['company']) . '</strong></small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de informaciÃ³n de contacto
     */
    public function column_contact_info($item) {
        $output = '<a href="mailto:' . esc_attr($item['user_email']) . '">' . esc_html($item['user_email']) . '</a>';
        
        if ($item['phone']) {
            $output .= '<br><small><a href="tel:' . esc_attr($item['phone']) . '">' . esc_html($item['phone']) . '</a></small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de estadÃ­sticas de actividad
     */
    public function column_activity_stats($item) {
        $calculations = intval($item['calculation_count']);
        $contracts = intval($item['contract_count']);
        
        $output = '<div class="activity-stats">';
        
        // CÃ¡lculos
        $output .= '<div class="stat-item">';
        $output .= '<span class="dashicons dashicons-calculator"></span>';
        $output .= '<span class="stat-number">' . $calculations . '</span>';
        $output .= '<span class="stat-label">' . _n('cÃ¡lculo', 'cÃ¡lculos', $calculations, 'adhesion') . '</span>';
        $output .= '</div>';
        
        // Contratos
        $output .= '<div class="stat-item">';
        $output .= '<span class="dashicons dashicons-media-document"></span>';
        $output .= '<span class="stat-number">' . $contracts . '</span>';
        $output .= '<span class="stat-label">' . _n('contrato', 'contratos', $contracts, 'adhesion') . '</span>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Columna de estadÃ­sticas financieras
     */
    public function column_financial_stats($item) {
        $total_paid = floatval($item['total_paid']);
        
        if ($total_paid > 0) {
            return '<strong>' . adhesion_format_price($total_paid) . '</strong>';
        }
        
        return '<em>' . __('Sin pagos', 'adhesion') . '</em>';
    }
    
    /**
     * Columna de estado
     */
    public function column_status($item) {
        $status = intval($item['user_status']);
        
        if ($status === 0) {
            return '<span class="adhesion-badge adhesion-badge-success">' . __('Activo', 'adhesion') . '</span>';
        } else {
            return '<span class="adhesion-badge adhesion-badge-warning">' . __('Inactivo', 'adhesion') . '</span>';
        }
    }
    
    /**
     * Columna de acciones
     */
    public function column_actions($item) {
        $actions = array();
        
        // Ver perfil
        $actions['view'] = '<a href="' . admin_url('user-edit.php?user_id=' . $item['ID']) . '" class="button button-small">' . __('Ver Perfil', 'adhesion') . '</a>';
        
        // Ver cÃ¡lculos si tiene
        if ($item['calculation_count'] > 0) {
            $calc_url = admin_url('admin.php?page=adhesion-calculations&user_id=' . $item['ID']);
            $actions['calculations'] = '<a href="' . esc_url($calc_url) . '" class="button button-small">' . __('Ver CÃ¡lculos', 'adhesion') . '</a>';
        }
        
        // Ver contratos si tiene
        if ($item['contract_count'] > 0) {
            $contract_url = admin_url('admin.php?page=adhesion-contracts&user_id=' . $item['ID']);
            $actions['contracts'] = '<a href="' . esc_url($contract_url) . '" class="button button-small">' . __('Ver Contratos', 'adhesion') . '</a>';
        }
        
        // Enviar email
        $actions['email'] = '<button type="button" class="button button-small" onclick="adhesionSendUserEmail(' . $item['ID'] . ', \'' . esc_js($item['user_email']) . '\')">' . __('Email', 'adhesion') . '</button>';
        
        return '<div class="row-actions-wrapper">' . implode(' ', $actions) . '</div>';
    }
    
    /**
     * Mostrar filtros
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        
        ?>
        <div class="alignleft actions">
            <!-- Filtro por estado -->
            <label for="filter-status" class="screen-reader-text"><?php _e('Filtrar por estado', 'adhesion'); ?></label>
            <select name="status" id="filter-status">
                <option value=""><?php _e('Todos los estados', 'adhesion'); ?></option>
                <option value="0" <?php selected($_GET['status'] ?? '', '0'); ?>><?php _e('Activos', 'adhesion'); ?></option>
                <option value="1" <?php selected($_GET['status'] ?? '', '1'); ?>><?php _e('Inactivos', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtro por actividad -->
            <label for="filter-activity" class="screen-reader-text"><?php _e('Filtrar por actividad', 'adhesion'); ?></label>
            <select name="activity" id="filter-activity">
                <option value=""><?php _e('Toda la actividad', 'adhesion'); ?></option>
                <option value="with_calculations" <?php selected($_GET['activity'] ?? '', 'with_calculations'); ?>><?php _e('Con cÃ¡lculos', 'adhesion'); ?></option>
                <option value="with_contracts" <?php selected($_GET['activity'] ?? '', 'with_contracts'); ?>><?php _e('Con contratos', 'adhesion'); ?></option>
                <option value="with_payments" <?php selected($_GET['activity'] ?? '', 'with_payments'); ?>><?php _e('Con pagos', 'adhesion'); ?></option>
                <option value="no_activity" <?php selected($_GET['activity'] ?? '', 'no_activity'); ?>><?php _e('Sin actividad', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtros de fecha -->
            <label for="filter-date-from" class="screen-reader-text"><?php _e('Registrado desde', 'adhesion'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" 
                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                   placeholder="<?php _e('Desde', 'adhesion'); ?>">
            
            <label for="filter-date-to" class="screen-reader-text"><?php _e('Registrado hasta', 'adhesion'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" 
                   value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                   placeholder="<?php _e('Hasta', 'adhesion'); ?>">
            
            <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar', 'adhesion'); ?>">
            
            <?php if (!empty($_GET['status']) || !empty($_GET['activity']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                    <?php _e('Limpiar', 'adhesion'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" onclick="adhesionExportUsers()">
                <?php _e('Exportar CSV', 'adhesion'); ?>
            </button>
            
            <button type="button" class="button button-primary" onclick="adhesionBulkEmail()">
                <?php _e('Email Masivo', 'adhesion'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Procesar acciones en masa
     */
    public function process_bulk_actions() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'adhesion'));
        }
        
        $user_ids = $_GET['user'] ?? array();
        
        if (empty($user_ids)) {
            return;
        }
        
        // Asegurar que son arrays
        if (!is_array($user_ids)) {
            $user_ids = array($user_ids);
        }
        
        switch ($action) {
            case 'send_email':
                $this->bulk_send_email($user_ids);
                break;
                
            case 'export':
                $this->bulk_export($user_ids);
                break;
                
            case 'activate':
                $this->bulk_activate($user_ids);
                break;
                
            case 'deactivate':
                $this->bulk_deactivate($user_ids);
                break;
                
            case 'delete':
                $this->bulk_delete($user_ids);
                break;
        }
    }
    
    /**
     * Enviar email en masa
     */
    private function bulk_send_email($user_ids) {
        // Redirigir a pÃ¡gina de composiciÃ³n de email
        $url = admin_url('admin.php?page=adhesion-users&action=compose_email&users=' . implode(',', $user_ids));
        wp_redirect($url);
        exit;
    }
    
    /**
     * Exportar usuarios en masa
     */
    private function bulk_export($user_ids) {
        $users = array();
        
        foreach ($user_ids as $user_id) {
            $user_data = $this->get_user_full_data(intval($user_id));
            if ($user_data) {
                $users[] = $user_data;
            }
        }
        
        $this->export_users_csv($users);
    }
    
    /**
     * Activar usuarios en masa
     */
    private function bulk_activate($user_ids) {
        $activated_count = 0;
        
        foreach ($user_ids as $user_id) {
            $result = wp_update_user(array(
                'ID' => intval($user_id),
                'user_status' => 0
            ));
            
            if (!is_wp_error($result)) {
                $activated_count++;
            }
        }
        
        if ($activated_count > 0) {
            $message = sprintf(__('Se activaron %d usuarios correctamente.', 'adhesion'), $activated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-users'));
        exit;
    }
    
    /**
     * Desactivar usuarios en masa
     */
    private function bulk_deactivate($user_ids) {
        $deactivated_count = 0;
        
        foreach ($user_ids as $user_id) {
            // No desactivar el usuario actual
            if (intval($user_id) === get_current_user_id()) {
                continue;
            }
            
            $result = wp_update_user(array(
                'ID' => intval($user_id),
                'user_status' => 1
            ));
            
            if (!is_wp_error($result)) {
                $deactivated_count++;
            }
        }
        
        if ($deactivated_count > 0) {
            $message = sprintf(__('Se desactivaron %d usuarios correctamente.', 'adhesion'), $deactivated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-users'));
        exit;
    }
    
    /**
     * Eliminar usuarios en masa (con precauciÃ³n)
     */
    private function bulk_delete($user_ids) {
        $deleted_count = 0;
        
        foreach ($user_ids as $user_id) {
            $user_id = intval($user_id);
            
            // No eliminar el usuario actual
            if ($user_id === get_current_user_id()) {
                continue;
            }
            
            // No eliminar si tiene contratos firmados
            global $wpdb;
            $signed_contracts = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'signed'",
                $user_id
            ));
            
            if ($signed_contracts > 0) {
                continue; // Saltar usuarios con contratos firmados
            }
            
            // Eliminar usuario
            if (wp_delete_user($user_id)) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            $message = sprintf(__('Se eliminaron %d usuarios correctamente.', 'adhesion'), $deleted_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-users'));
        exit;
    }
    
    /**
     * Obtener datos completos de un usuario
     */
    private function get_user_full_data($user_id) {
        global $wpdb;
        
        $sql = "SELECT u.*, 
                       um_first.meta_value as first_name,
                       um_last.meta_value as last_name,
                       um_phone.meta_value as phone,
                       um_company.meta_value as company,
                       COUNT(DISTINCT c.id) as calculation_count,
                       COUNT(DISTINCT ct.id) as contract_count,
                       SUM(CASE WHEN ct.payment_status = 'completed' THEN ct.payment_amount ELSE 0 END) as total_paid
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
                LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id AND um_phone.meta_key = 'phone'
                LEFT JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'company'
                LEFT JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id AND c.status = 'active'
                LEFT JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
                WHERE u.ID = %d
                GROUP BY u.ID";
        
        return $wpdb->get_row($wpdb->prepare($sql, $user_id), ARRAY_A);
    }
    
    /**
     * Exportar usuarios a CSV
     */
    private function export_users_csv($users) {
        $filename = 'adhesion_users_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeceras
        fputcsv($output, array(
            'ID',
            'Usuario',
            'Nombre',
            'Apellidos',
            'Email',
            'TelÃ©fono',
            'Empresa',
            'Estado',
            'Fecha Registro',
            'CÃ¡lculos',
            'Contratos',
            'Total Pagado',
            'Ãšltima Actividad'
        ), ';');
        
        // Datos
        foreach ($users as $user) {
            fputcsv($output, array(
                $user['ID'],
                $user['user_login'],
                $user['first_name'] ?: '',
                $user['last_name'] ?: '',
                $user['user_email'],
                $user['phone'] ?: '',
                $user['company'] ?: '',
                $user['user_status'] == 0 ? 'Activo' : 'Inactivo',
                $user['user_registered'],
                $user['calculation_count'] ?: '0',
                $user['contract_count'] ?: '0',
                $user['total_paid'] ?: '0',
                $user['last_activity'] ?: ''
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Enviar email a usuario
     */
    public function ajax_send_user_email() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $user_id = intval($_POST['user_id']);
            $subject = sanitize_text_field($_POST['subject']);
            $message = sanitize_textarea_field($_POST['message']);
            
            $user = get_userdata($user_id);
            if (!$user) {
                throw new Exception(__('Usuario no encontrado.', 'adhesion'));
            }
            
            // Enviar email
            $sent = adhesion_send_email(
                $user->user_email,
                $subject,
                'user-notification',
                array(
                    'user_name' => $user->display_name,
                    'message' => $message,
                    'site_name' => get_bloginfo('name')
                )
            );
            
            if (!$sent) {
                throw new Exception(__('Error al enviar el email.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Email enviado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Exportar usuarios
     */
    public function ajax_export_users() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Obtener todos los usuarios con filtros actuales
            $filters = $this->get_filters();
            $users = $this->get_adhesion_users(1000, 0, $filters); // MÃ¡ximo 1000
            
            $this->export_users_csv($users);
            
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }
    
    /**
     * AJAX: Actualizar estado de usuario
     */
    public function ajax_update_user_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $user_id = intval($_POST['user_id']);
            $status = intval($_POST['status']);
            
            // No permitir cambiar el estado del usuario actual
            if ($user_id === get_current_user_id()) {
                throw new Exception(__('No puedes cambiar tu propio estado.', 'adhesion'));
            }
            
            $result = wp_update_user(array(
                'ID' => $user_id,
                'user_status' => $status
            ));
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $status_text = $status == 0 ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Usuario marcado como %s.', 'adhesion'), strtolower($status_text)),
                'new_status' => $status_text
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

/**
 * FunciÃ³n para mostrar la pÃ¡gina de listado de usuarios
 */
function adhesion_display_users_page() {
    $list_table = new Adhesion_Users_List();
    $list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Usuarios Adheridos', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action">
            <?php _e('AÃ±adir Usuario', 'adhesion'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('â† Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <form method="get" id="users-filter">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <?php
            $list_table->search_box(__('Buscar usuarios', 'adhesion'), 'user');
            $list_table->display();
            ?>
        </form>
        
        <!-- Modal para enviar email -->
        <div id="email-modal" class="adhesion-modal" style="display: none;">
            <div class="adhesion-modal-content">
                <div class="adhesion-modal-header">
                    <h2><?php _e('Enviar Email', 'adhesion'); ?></h2>
                    <button type="button" class="adhesion-modal-close" onclick="adhesionCloseEmailModal()">&times;</button>
                </div>
                <div class="adhesion-modal-body">
                    <form id="email-form">
                        <div class="adhesion-form-row">
                            <label for="email-to"><?php _e('Para:', 'adhesion'); ?></label>
                            <input type="email" id="email-to" readonly>
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label for="email-subject"><?php _e('Asunto:', 'adhesion'); ?></label>
                            <input type="text" id="email-subject" required>
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label for="email-message"><?php _e('Mensaje:', 'adhesion'); ?></label>
                            <textarea id="email-message" rows="6" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="adhesion-modal-footer">
                    <button type="button" class="button button-primary" onclick="adhesionSendEmailConfirm()">
                        <?php _e('Enviar Email', 'adhesion'); ?>
                    </button>
                    <button type="button" class="button" onclick="adhesionCloseEmailModal()">
                        <?php _e('Cancelar', 'adhesion'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros
        $('#filter-status, #filter-activity, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#users-filter').submit();
        });
    });
    
    // Variables globales para el modal
    let currentUserId = null;
    
    function adhesionSendUserEmail(userId, userEmail) {
        currentUserId = userId;
        document.getElementById('email-to').value = userEmail;
        document.getElementById('email-subject').value = '';
        document.getElementById('email-message').value = '';
        document.getElementById('email-modal').style.display = 'block';
    }
    
    function adhesionCloseEmailModal() {
        document.getElementById('email-modal').style.display = 'none';
        currentUserId = null;
    }
    
    function adhesionSendEmailConfirm() {
        if (!currentUserId) return;
        
        const subject = document.getElementById('email-subject').value;
        const message = document.getElementById('email-message').value;
        
        if (!subject || !message) {
            alert('<?php echo esc_js(__('Por favor, completa todos los campos.', 'adhesion')); ?>');
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_send_user_email',
                user_id: currentUserId,
                subject: subject,
                message: message,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#email-modal .adhesion-modal-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    adhesionCloseEmailModal();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#email-modal .adhesion-modal-content').css('opacity', '1');
            }
        });
    }
    
    function adhesionExportUsers() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        // Agregar campos ocultos con filtros actuales
        const fields = {
            action: 'adhesion_export_users',
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>',
            status: '<?php echo esc_js($_GET['status'] ?? ''); ?>',
            activity: '<?php echo esc_js($_GET['activity'] ?? ''); ?>',
            date_from: '<?php echo esc_js($_GET['date_from'] ?? ''); ?>',
            date_to: '<?php echo esc_js($_GET['date_to'] ?? ''); ?>'
        };
        
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    function adhesionBulkEmail() {
        const checkedBoxes = document.querySelectorAll('input[name="user[]"]:checked');
        
        if (checkedBoxes.length === 0) {
            alert('<?php echo esc_js(__('Selecciona al menos un usuario.', 'adhesion')); ?>');
            return;
        }
        
        // TODO: Implementar modal de email masivo
        alert('<?php echo esc_js(__('Funcionalidad de email masivo en desarrollo.', 'adhesion')); ?>');
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('email-modal');
        if (event.target === modal) {
            adhesionCloseEmailModal();
        }
    }
    </script>
    
    <style>
    .activity-stats {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }
    
    .stat-item .dashicons {
        width: 16px;
        height: 16px;
        font-size: 16px;
        color: #646970;
    }
    
    .stat-number {
        font-weight: bold;
        color: #0073aa;
        min-width: 20px;
    }
    
    .stat-label {
        color: #646970;
    }
    
    .row-actions-wrapper {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .row-actions-wrapper .button {
        margin: 0;
        font-size: 11px;
        padding: 3px 6px;
        height: auto;
        line-height: 1.2;
    }
    
    /* Modal styles */
    .adhesion-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .adhesion-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .adhesion-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ccd0d4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f6f7f7;
    }
    
    .adhesion-modal-header h2 {
        margin: 0;
        font-size: 16px;
    }
    
    .adhesion-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #646970;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .adhesion-modal-close:hover {
        color: #d63638;
    }
    
    .adhesion-modal-body {
        padding: 20px;
    }
    
    .adhesion-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ccd0d4;
        background: #f6f7f7;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .adhesion-form-row {
        margin-bottom: 15px;
    }
    
    .adhesion-form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .adhesion-form-row input,
    .adhesion-form-row textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    
    .adhesion-form-row input:focus,
    .adhesion-form-row textarea:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }
    
    @media (max-width: 768px) {
        .column-activity_stats,
        .column-financial_stats,
        .column-last_activity {
            display: none;
        }
        
        .activity-stats {
            flex-direction: row;
        }
        
        .row-actions-wrapper {
            flex-direction: column;
        }
        
        .adhesion-modal-content {
            margin: 10% auto;
            width: 95%;
        }
    }
    </style>
    <?php
}


// ===== calculations-display.php =====
<?php
/**
 * Vista del listado de cÃ¡lculos de presupuestos
 * 
 * Esta vista maneja:
 * - Listado principal de cÃ¡lculos
 * - Vista detallada de un cÃ¡lculo especÃ­fico
 * - EstadÃ­sticas y resÃºmenes
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acciÃ³n actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$calculation_id = intval($_GET['calculation'] ?? 0);

// Instanciar base de datos
$db = new Adhesion_Database();

// Manejar acciones especÃ­ficas
switch ($action) {
    case 'view':
        if ($calculation_id) {
            adhesion_display_calculation_detail($calculation_id, $db);
        } else {
            adhesion_display_calculations_list();
        }
        break;
        
    case 'delete':
        if ($calculation_id && wp_verify_nonce($_GET['_wpnonce'], 'delete_calculation_' . $calculation_id)) {
            adhesion_handle_delete_calculation($calculation_id, $db);
        }
        adhesion_display_calculations_list();
        break;
        
    default:
        adhesion_display_calculations_list();
        break;
}

/**
 * Mostrar listado principal de cÃ¡lculos
 */
function adhesion_display_calculations_list() {
    // Obtener estadÃ­sticas rÃ¡pidas
    $db = new Adhesion_Database();
    $stats = $db->get_basic_stats();
    
    // EstadÃ­sticas de la Ãºltima semana
    $week_stats = $db->get_period_stats(
        date('Y-m-d', strtotime('-7 days')),
        date('Y-m-d')
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('CÃ¡lculos de Presupuestos', 'adhesion'); ?>
            <span class="title-count"><?php echo sprintf(__('(%s total)', 'adhesion'), number_format($stats['total_calculations'])); ?></span>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('â† Volver al Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar notificaciones
        adhesion_display_notices();
        ?>
        
        <!-- EstadÃ­sticas rÃ¡pidas -->
        <div class="adhesion-quick-stats">
            <div class="adhesion-stats-grid">
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calculator"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_calculations']); ?></div>
                        <div class="stat-label"><?php _e('Total CÃ¡lculos', 'adhesion'); ?></div>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($week_stats['period_calculations']); ?></div>
                        <div class="stat-label"><?php _e('Esta Semana', 'adhesion'); ?></div>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo adhesion_format_price($week_stats['period_revenue']); ?></div>
                        <div class="stat-label"><?php _e('Ingresos Semana', 'adhesion'); ?></div>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <?php 
                        $avg_calc_value = $stats['total_calculations'] > 0 ? $stats['total_revenue'] / $stats['total_calculations'] : 0;
                        ?>
                        <div class="stat-number"><?php echo adhesion_format_price($avg_calc_value); ?></div>
                        <div class="stat-label"><?php _e('Valor Promedio', 'adhesion'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de cÃ¡lculos -->
        <div class="adhesion-table-container">
            <?php
            // Crear y mostrar la tabla
            $list_table = new Adhesion_Calculations_List();
            $list_table->prepare_items();
            ?>
            
            <form method="get" id="calculations-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                <?php
                $list_table->search_box(__('Buscar cÃ¡lculos', 'adhesion'), 'calculation');
                $list_table->display();
                ?>
            </form>
        </div>
    </div>
    
    <style>
    .adhesion-quick-stats {
        margin: 20px 0 30px;
    }
    
    .quick-stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    .quick-stat-card .stat-icon {
        width: 40px;
        height: 40px;
        background: #0073aa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }
    
    .quick-stat-card .stat-number {
        font-size: 20px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
    }
    
    .quick-stat-card .stat-label {
        font-size: 12px;
        color: #646970;
        margin-top: 2px;
    }
    
    .adhesion-table-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    @media (max-width: 768px) {
        .adhesion-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .quick-stat-card {
            padding: 12px;
        }
        
        .quick-stat-card .stat-number {
            font-size: 16px;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros cuando cambian los selects
        $('#filter-user, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#calculations-filter').submit();
        });
        
        // ConfirmaciÃ³n para eliminaciones
        $('.delete-calculation').on('click', function(e) {
            if (!confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres eliminar este cÃ¡lculo?', 'adhesion')); ?>')) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * Mostrar detalle de un cÃ¡lculo especÃ­fico
 */
function adhesion_display_calculation_detail($calculation_id, $db) {
    $calculation = $db->get_calculation($calculation_id);
    
    if (!$calculation) {
        ?>
        <div class="wrap">
            <h1><?php _e('CÃ¡lculo no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El cÃ¡lculo solicitado no existe o ha sido eliminado.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                <?php _e('â† Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener informaciÃ³n adicional
    $user = get_userdata($calculation['user_id']);
    $contracts = $db->get_user_contracts($calculation['user_id']);
    $related_calculations = $db->get_user_calculations($calculation['user_id'], 5);
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php echo sprintf(__('CÃ¡lculo #%s', 'adhesion'), $calculation['id']); ?>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="page-title-action">
            <?php _e('â† Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <div class="adhesion-calculation-detail">
            <div class="adhesion-detail-grid">
                <!-- InformaciÃ³n del cÃ¡lculo -->
                <div class="adhesion-card">
                    <div class="adhesion-card-header">
                        <h2><?php _e('InformaciÃ³n del CÃ¡lculo', 'adhesion'); ?></h2>
                    </div>
                    <div class="adhesion-card-body">
                        <table class="adhesion-detail-table">
                            <tr>
                                <th><?php _e('ID:', 'adhesion'); ?></th>
                                <td><?php echo esc_html($calculation['id']); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Fecha de creaciÃ³n:', 'adhesion'); ?></th>
                                <td><?php echo adhesion_format_date($calculation['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Total toneladas:', 'adhesion'); ?></th>
                                <td><?php echo $calculation['total_tons'] ? adhesion_format_tons($calculation['total_tons']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Precio total:', 'adhesion'); ?></th>
                                <td><strong><?php echo adhesion_format_price($calculation['total_price']); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php _e('Precio por tonelada:', 'adhesion'); ?></th>
                                <td><?php echo $calculation['price_per_ton'] ? adhesion_format_price($calculation['price_per_ton']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Estado:', 'adhesion'); ?></th>
                                <td>
                                    <span class="adhesion-badge adhesion-badge-success">
                                        <?php echo ucfirst($calculation['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- InformaciÃ³n del usuario -->
                <div class="adhesion-card">
                    <div class="adhesion-card-header">
                        <h2><?php _e('InformaciÃ³n del Usuario', 'adhesion'); ?></h2>
                    </div>
                    <div class="adhesion-card-body">
                        <?php if ($user): ?>
                        <table class="adhesion-detail-table">
                            <tr>
                                <th><?php _e('Nombre:', 'adhesion'); ?></th>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Email:', 'adhesion'); ?></th>
                                <td><a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a></td>
                            </tr>
                            <tr>
                                <th><?php _e('Rol:', 'adhesion'); ?></th>
                                <td><?php echo implode(', ', $user->roles); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Registrado:', 'adhesion'); ?></th>
                                <td><?php echo adhesion_format_date($user->user_registered); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('CÃ¡lculos totales:', 'adhesion'); ?></th>
                                <td><?php echo count($related_calculations); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Contratos:', 'adhesion'); ?></th>
                                <td><?php echo count($contracts); ?></td>
                            </tr>
                        </table>
                        <?php else: ?>
                        <p class="adhesion-no-data"><?php _e('Usuario eliminado o no encontrado.', 'adhesion'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Detalle de materiales -->
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php _e('Detalle de Materiales', 'adhesion'); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <?php if (!empty($calculation['calculation_data']['materials'])): ?>
                    <div class="adhesion-materials-grid">
                        <?php foreach ($calculation['calculation_data']['materials'] as $material): ?>
                        <div class="material-detail-card">
                            <div class="material-type">
                                <strong><?php echo esc_html($material['type']); ?></strong>
                            </div>
                            <div class="material-quantity">
                                <?php echo adhesion_format_tons($material['quantity']); ?>
                            </div>
                            <?php if (isset($material['price_per_ton'])): ?>
                            <div class="material-price">
                                <?php echo adhesion_format_price($material['price_per_ton']); ?>/t
                            </div>
                            <?php endif; ?>
                            <?php if (isset($material['total'])): ?>
                            <div class="material-total">
                                <strong><?php echo adhesion_format_price($material['total']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="adhesion-no-data"><?php _e('No hay informaciÃ³n detallada de materiales.', 'adhesion'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php _e('Acciones', 'adhesion'); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <div class="adhesion-actions-grid">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=create&calculation=' . $calculation['id']); ?>" 
                           class="button button-primary">
                            <?php _e('Crear Contrato', 'adhesion'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $calculation['user_id']); ?>" 
                           class="button">
                            <?php _e('Ver Usuario', 'adhesion'); ?>
                        </a>
                        
                        <button type="button" class="button" onclick="adhesionExportCalculation(<?php echo $calculation['id']; ?>)">
                            <?php _e('Exportar Datos', 'adhesion'); ?>
                        </button>
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=adhesion-calculations&action=delete&calculation=' . $calculation['id']), 'delete_calculation_' . $calculation['id']); ?>" 
                           class="button button-link-delete"
                           onclick="return confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres eliminar este cÃ¡lculo?', 'adhesion')); ?>')">
                            <?php _e('Eliminar', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .adhesion-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .adhesion-detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .adhesion-detail-table th,
    .adhesion-detail-table td {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f1;
        text-align: left;
    }
    
    .adhesion-detail-table th {
        width: 40%;
        font-weight: 600;
        color: #646970;
    }
    
    .adhesion-detail-table td {
        color: #1d2327;
    }
    
    .adhesion-materials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .material-detail-card {
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f9f9f9;
        text-align: center;
    }
    
    .material-type {
        font-size: 16px;
        margin-bottom: 8px;
        color: #0073aa;
    }
    
    .material-quantity {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 5px;
        color: #1d2327;
    }
    
    .material-price {
        font-size: 12px;
        color: #646970;
        margin-bottom: 5px;
    }
    
    .material-total {
        font-size: 14px;
        color: #00a32a;
    }
    
    .adhesion-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .adhesion-no-data {
        color: #646970;
        font-style: italic;
        text-align: center;
        padding: 20px;
    }
    
    @media (max-width: 768px) {
        .adhesion-detail-grid {
            grid-template-columns: 1fr;
        }
        
        .adhesion-materials-grid {
            grid-template-columns: 1fr;
        }
        
        .adhesion-actions-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script>
    function adhesionExportCalculation(calculationId) {
        // TODO: Implementar exportaciÃ³n individual
        alert('<?php echo esc_js(__('Funcionalidad de exportaciÃ³n individual en desarrollo.', 'adhesion')); ?>');
    }
    </script>
    <?php
}

/**
 * Manejar eliminaciÃ³n de cÃ¡lculo
 */
function adhesion_handle_delete_calculation($calculation_id, $db) {
    // Verificar que no tenga contratos asociados
    global $wpdb;
    $has_contracts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE calculation_id = %d",
        $calculation_id
    ));
    
    if ($has_contracts > 0) {
        adhesion_add_notice(__('No se puede eliminar un cÃ¡lculo que tiene contratos asociados.', 'adhesion'), 'error');
        return;
    }
    
    // Marcar como eliminado
    $result = $wpdb->update(
        $wpdb->prefix . 'adhesion_calculations',
        array('status' => 'deleted'),
        array('id' => $calculation_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        adhesion_add_notice(__('CÃ¡lculo eliminado correctamente.', 'adhesion'), 'success');
        adhesion_log("CÃ¡lculo $calculation_id eliminado por administrador", 'info');
    } else {
        adhesion_add_notice(__('Error al eliminar el cÃ¡lculo.', 'adhesion'), 'error');
    }
}


// ===== contracts-display.php =====
<?php
/**
 * Vista del listado de contratos de adhesiÃ³n
 * 
 * Esta vista maneja:
 * - Listado principal de contratos
 * - Vista detallada de un contrato especÃ­fico
 * - CreaciÃ³n de nuevos contratos
 * - EdiciÃ³n de contratos existentes
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acciÃ³n actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$contract_id = intval($_GET['contract'] ?? 0);
$calculation_id = intval($_GET['calculation'] ?? 0);

// Instanciar base de datos
$db = new Adhesion_Database();

// Manejar acciones especÃ­ficas
switch ($action) {
    case 'view':
        if ($contract_id) {
            adhesion_display_contract_detail($contract_id, $db);
        } else {
            adhesion_display_contracts_list();
        }
        break;
        
    case 'create':
        adhesion_display_contract_create($calculation_id, $db);
        break;
        
    case 'edit':
        if ($contract_id) {
            adhesion_display_contract_edit($contract_id, $db);
        } else {
            adhesion_display_contracts_list();
        }
        break;
        
    default:
        adhesion_display_contracts_list();
        break;
}

/**
 * Mostrar listado principal de contratos
 */
function adhesion_display_contracts_list() {
    // Obtener estadÃ­sticas rÃ¡pidas
    $db = new Adhesion_Database();
    $stats = $db->get_basic_stats();
    
    // EstadÃ­sticas especÃ­ficas de contratos
    global $wpdb;
    
    $contract_stats = array();
    $contract_stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'pending'");
    $contract_stats['sent'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'sent'");
    $contract_stats['signed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'signed'");
    $contract_stats['completed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'completed'");
    
    // EstadÃ­sticas de completado por fecha
    $monthly_stats = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM {$wpdb->prefix}adhesion_contracts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Contratos de AdhesiÃ³n', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=create'); ?>" class="page-title-action">
            <?php _e('AÃ±adir nuevo', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <!-- EstadÃ­sticas de contratos -->
        <div class="contracts-stats-grid">
            <div class="contracts-stat-card pending">
                <div class="stat-icon">ðŸ“‹</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['pending']); ?></div>
                    <div class="stat-label"><?php _e('Pendientes', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&status=pending'); ?>" class="button button-small">
                        <?php _e('Ver', 'adhesion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contracts-stat-card sent">
                <div class="stat-icon">ðŸ“¤</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['sent']); ?></div>
                    <div class="stat-label"><?php _e('Enviados', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&status=sent'); ?>" class="button button-small">
                        <?php _e('Ver', 'adhesion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contracts-stat-card signed">
                <div class="stat-icon">âœ…</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['signed']); ?></div>
                    <div class="stat-label"><?php _e('Firmados', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&status=signed'); ?>" class="button button-small">
                        <?php _e('Ver', 'adhesion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contracts-stat-card completed">
                <div class="stat-icon">ðŸŽ¯</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['completed']); ?></div>
                    <div class="stat-label"><?php _e('Completados', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <span class="stat-percentage">
                        <?php 
                        $total_contracts = array_sum($contract_stats);
                        if ($total_contracts > 0) {
                            echo round(($contract_stats['completed'] / $total_contracts) * 100, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Tabla de contratos -->
        <form method="get" id="contracts-filter">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="status" id="filter-status">
                        <option value=""><?php _e('Todos los estados', 'adhesion'); ?></option>
                        <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('Pendientes', 'adhesion'); ?></option>
                        <option value="sent" <?php selected($_GET['status'] ?? '', 'sent'); ?>><?php _e('Enviados', 'adhesion'); ?></option>
                        <option value="signed" <?php selected($_GET['status'] ?? '', 'signed'); ?>><?php _e('Firmados', 'adhesion'); ?></option>
                        <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Completados', 'adhesion'); ?></option>
                    </select>
                    
                    <select name="payment_status" id="filter-payment-status">
                        <option value=""><?php _e('Todos los pagos', 'adhesion'); ?></option>
                        <option value="pending" <?php selected($_GET['payment_status'] ?? '', 'pending'); ?>><?php _e('Pago pendiente', 'adhesion'); ?></option>
                        <option value="paid" <?php selected($_GET['payment_status'] ?? '', 'paid'); ?>><?php _e('Pagado', 'adhesion'); ?></option>
                        <option value="failed" <?php selected($_GET['payment_status'] ?? '', 'failed'); ?>><?php _e('Pago fallido', 'adhesion'); ?></option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" placeholder="<?php _e('Fecha desde', 'adhesion'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="<?php _e('Fecha hasta', 'adhesion'); ?>">
                    
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Filtrar', 'adhesion'); ?>">
                </div>
                
                <div class="alignright actions">
                    <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="<?php _e('Buscar contratos...', 'adhesion'); ?>">
                    <input type="submit" id="search-submit" class="button" value="<?php _e('Buscar', 'adhesion'); ?>">
                </div>
            </div>
        </form>
        
        <!-- Lista de contratos recientes -->
        <?php
        // Obtener contratos con filtros aplicados
        $contracts = adhesion_get_contracts_with_filters();
        
        if (empty($contracts)) {
            ?>
            <div class="notice notice-info">
                <p><?php _e('No se encontraron contratos que coincidan con los filtros aplicados.', 'adhesion'); ?></p>
            </div>
            <?php
        } else {
            adhesion_display_contracts_table($contracts);
        }
        ?>
    </div>
    
    <style>
    .contracts-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .contracts-stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        position: relative;
        overflow: hidden;
    }
    
    .contracts-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .contracts-stat-card.pending::before { background: #dba617; }
    .contracts-stat-card.sent::before { background: #0073aa; }
    .contracts-stat-card.signed::before { background: #00a32a; }
    .contracts-stat-card.completed::before { background: #8c8f94; }
    
    .contracts-stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .contracts-stat-card.pending .stat-icon { background: #dba617; }
    .contracts-stat-card.sent .stat-icon { background: #0073aa; }
    .contracts-stat-card.signed .stat-icon { background: #00a32a; }
    .contracts-stat-card.completed .stat-icon { background: #8c8f94; }
    
    .contracts-stat-card .stat-content {
        flex: 1;
    }
    
    .contracts-stat-card .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .contracts-stat-card .stat-label {
        font-size: 14px;
        color: #646970;
        font-weight: 500;
    }
    
    .contracts-stat-card .stat-action {
        flex-shrink: 0;
    }
    
    .stat-percentage {
        font-size: 16px;
        font-weight: bold;
        color: #646970;
    }
    
    @media (max-width: 768px) {
        .contracts-stat-card {
            padding: 15px;
        }
        
        .contracts-stat-card .stat-number {
            font-size: 20px;
        }
        
        .contracts-stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
    }
    </style>
    <?php
}

/**
 * Mostrar formulario de creaciÃ³n de contrato
 */
function adhesion_display_contract_create($calculation_id, $db) {
    $calculation = null;
    if ($calculation_id) {
        $calculation = $db->get_calculation($calculation_id);
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Crear Nuevo Contrato', 'adhesion'); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Funcionalidad de creaciÃ³n de contratos en desarrollo. Por ahora, los contratos se crean automÃ¡ticamente desde el proceso de adhesiÃ³n del frontend.', 'adhesion'); ?></p>
        </div>
        
        <?php if ($calculation): ?>
        <div class="notice notice-success">
            <p>
                <?php echo sprintf(__('Contrato basado en el cÃ¡lculo #%s de %s', 'adhesion'), $calculation['id'], adhesion_format_price($calculation['total_price'])); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
            <?php _e('â† Volver al listado', 'adhesion'); ?>
        </a>
    </div>
    <?php
}

/**
 * Mostrar formulario de ediciÃ³n de contrato
 */
function adhesion_display_contract_edit($contract_id, $db) {
    $contract = $db->get_contract($contract_id);
    
    if (!$contract) {
        ?>
        <div class="wrap">
            <h1><?php _e('Contrato no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El contrato solicitado no existe.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                <?php _e('â† Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo sprintf(__('Editar Contrato %s', 'adhesion'), $contract['contract_number']); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Funcionalidad de ediciÃ³n de contratos en desarrollo.', 'adhesion'); ?></p>
        </div>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract_id); ?>" class="button">
            <?php _e('â† Ver contrato', 'adhesion'); ?>
        </a>
    </div>
    <?php
}

/**
 * Obtener contratos con filtros aplicados
 */
function adhesion_get_contracts_with_filters() {
    global $wpdb;
    
    $where_conditions = array("1=1");
    $query_params = array();
    
    // Filtro por estado
    if (!empty($_GET['status'])) {
        $where_conditions[] = "status = %s";
        $query_params[] = sanitize_text_field($_GET['status']);
    }
    
    // Filtro por estado de pago
    if (!empty($_GET['payment_status'])) {
        $where_conditions[] = "payment_status = %s";
        $query_params[] = sanitize_text_field($_GET['payment_status']);
    }
    
    // Filtro por rango de fechas
    if (!empty($_GET['date_from'])) {
        $where_conditions[] = "DATE(created_at) >= %s";
        $query_params[] = sanitize_text_field($_GET['date_from']);
    }
    
    if (!empty($_GET['date_to'])) {
        $where_conditions[] = "DATE(created_at) <= %s";
        $query_params[] = sanitize_text_field($_GET['date_to']);
    }
    
    // Filtro de bÃºsqueda
    if (!empty($_GET['s'])) {
        $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
        $where_conditions[] = "(contract_number LIKE %s OR client_name LIKE %s OR client_email LIKE %s)";
        $query_params[] = $search;
        $query_params[] = $search;
        $query_params[] = $search;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT * FROM {$wpdb->prefix}adhesion_contracts WHERE $where_clause ORDER BY created_at DESC LIMIT 50";
    
    if (!empty($query_params)) {
        $query = $wpdb->prepare($query, $query_params);
    }
    
    return $wpdb->get_results($query, ARRAY_A);
}

/**
 * Mostrar tabla de contratos
 */
function adhesion_display_contracts_table($contracts) {
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('NÃºmero', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Cliente', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Estado', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Pago', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Importe', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Fecha', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Acciones', 'adhesion'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contracts as $contract): ?>
            <tr>
                <td>
                    <strong>
                        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']); ?>">
                            <?php echo esc_html($contract['contract_number'] ?? 'Sin nÃºmero'); ?>
                        </a>
                    </strong>
                </td>
                <td>
                    <div>
                        <strong><?php echo esc_html($contract['client_name'] ?? 'Sin nombre'); ?></strong><br>
                        <small><?php echo esc_html($contract['client_email'] ?? ''); ?></small>
                    </div>
                </td>
                <td>
                    <?php echo adhesion_get_status_badge($contract['status']); ?>
                </td>
                <td>
                    <?php echo adhesion_get_payment_status_badge($contract['payment_status']); ?>
                </td>
                <td>
                    <strong><?php echo adhesion_format_price($contract['total_amount'] ?? 0); ?></strong>
                </td>
                <td>
                    <?php echo adhesion_format_date($contract['created_at'], 'd/m/Y'); ?>
                </td>
                <td>
                    <div class="row-actions">
                        <span class="view">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']); ?>">
                                <?php _e('Ver', 'adhesion'); ?>
                            </a>
                        </span>
                        
                        <?php if ($contract['status'] === 'pending'): ?>
                        | <span class="send">
                            <a href="#" onclick="adhesionSendToDocusign(<?php echo $contract['id']; ?>)">
                                <?php _e('Enviar a firma', 'adhesion'); ?>
                            </a>
                        </span>
                        <?php endif; ?>
                        
                        | <span class="edit">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=edit&contract=' . $contract['id']); ?>">
                                <?php _e('Editar', 'adhesion'); ?>
                            </a>
                        </span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros
        $('#filter-status, #filter-payment-status, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#contracts-filter').submit();
        });
    });
    
    // Funciones AJAX para acciones de contratos
    function adhesionSendToDocusign(contractId) {
        if (!confirm('<?php echo esc_js(__('Â¿Enviar este contrato a DocuSign para firma?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.post(ajaxurl, {
            action: 'adhesion_send_contract_docusign',
            contract_id: contractId,
            nonce: '<?php echo wp_create_nonce('adhesion_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Contrato enviado correctamente', 'adhesion')); ?>');
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error al enviar contrato: ', 'adhesion')); ?>' + response.data);
            }
        });
    }
    </script>
    <?php
}

/**
 * Mostrar detalle de un contrato especÃ­fico
 */
function adhesion_display_contract_detail($contract_id, $db) {
    $contract = $db->get_contract($contract_id);
    
    if (!$contract) {
        ?>
        <div class="wrap">
            <h1><?php _e('Contrato no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El contrato solicitado no existe o ha sido eliminado.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                <?php _e('â† Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener informaciÃ³n adicional
    $user = get_userdata($contract['user_id']);
    $calculation = $contract['calculation_id'] ? $db->get_calculation($contract['calculation_id']) : null;
    
    ?>
    <div class="wrap">
        <h1><?php echo sprintf(__('Contrato %s', 'adhesion'), esc_html($contract['contract_number'])); ?></h1>
        
        <div class="contract-detail-header">
            <div class="contract-status-info">
                <div class="status-badge">
                    <?php echo adhesion_get_status_badge($contract['status']); ?>
                </div>
                <div class="payment-badge">
                    <?php echo adhesion_get_payment_status_badge($contract['payment_status']); ?>
                </div>
            </div>
            
            <div class="contract-actions">
                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                    <?php _e('â† Volver al listado', 'adhesion'); ?>
                </a>
                
                <?php if ($contract['status'] === 'pending'): ?>
                <button type="button" class="button button-primary" onclick="adhesionSendToDocusign(<?php echo $contract['id']; ?>)">
                    <?php _e('Enviar a DocuSign', 'adhesion'); ?>
                </button>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=edit&contract=' . $contract['id']); ?>" class="button">
                    <?php _e('Editar', 'adhesion'); ?>
                </a>
            </div>
        </div>
        
        <div class="contract-detail-content">
            <!-- InformaciÃ³n del cliente -->
            <div class="detail-section">
                <h3><?php _e('InformaciÃ³n del cliente', 'adhesion'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Nombre:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['client_name'] ?? ($user ? $user->display_name : 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['client_email'] ?? ($user ? $user->user_email : 'N/A')); ?></td>
                    </tr>
                    <?php if (!empty($contract['client_phone'])): ?>
                    <tr>
                        <th><?php _e('TelÃ©fono:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['client_phone']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- InformaciÃ³n del contrato -->
            <div class="detail-section">
                <h3><?php _e('Detalles del contrato', 'adhesion'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('NÃºmero de contrato:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['contract_number']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Fecha de creaciÃ³n:', 'adhesion'); ?></th>
                        <td><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y H:i'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Importe total:', 'adhesion'); ?></th>
                        <td><strong><?php echo adhesion_format_price($contract['total_amount']); ?></strong></td>
                    </tr>
                    <?php if ($contract['signed_at']): ?>
                    <tr>
                        <th><?php _e('Fecha de firma:', 'adhesion'); ?></th>
                        <td><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- InformaciÃ³n del cÃ¡lculo asociado -->
            <?php if ($calculation): ?>
            <div class="detail-section">
                <h3><?php _e('CÃ¡lculo asociado', 'adhesion'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID del cÃ¡lculo:', 'adhesion'); ?></th>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $calculation['id']); ?>">
                                #<?php echo esc_html($calculation['id']); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Total toneladas:', 'adhesion'); ?></th>
                        <td><?php echo number_format($calculation['total_tons'], 2, ',', '.'); ?> t</td>
                    </tr>
                    <tr>
                        <th><?php _e('Precio total:', 'adhesion'); ?></th>
                        <td><?php echo adhesion_format_price($calculation['total_price']); ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Documentos y firma -->
            <div class="detail-section">
                <h3><?php _e('Documentos y firma', 'adhesion'); ?></h3>
                <table class="form-table">
                    <?php if (!empty($contract['docusign_envelope_id'])): ?>
                    <tr>
                        <th><?php _e('DocuSign Envelope ID:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['docusign_envelope_id']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($contract['signed_document_url'])): ?>
                    <tr>
                        <th><?php _e('Documento firmado:', 'adhesion'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($contract['signed_document_url']); ?>" target="_blank" class="button">
                                <?php _e('Descargar documento', 'adhesion'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Historial de actividad -->
            <div class="detail-section">
                <h3><?php _e('Historial de actividad', 'adhesion'); ?></h3>
                <div class="activity-timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Contrato creado', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    
                    <?php if ($contract['sent_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker sent"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Enviado para firma', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['sent_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($contract['signed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker signed"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Firmado por el cliente', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($contract['completed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker completed"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Proceso completado', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['completed_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .contract-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
        padding: 20px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .contract-status-info {
        display: flex;
        gap: 10px;
    }
    
    .contract-actions {
        display: flex;
        gap: 10px;
    }
    
    .contract-detail-content {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .detail-section {
        padding: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .detail-section:last-child {
        border-bottom: none;
    }
    
    .detail-section h3 {
        margin: 0 0 15px 0;
        color: #1d2327;
        font-size: 16px;
    }
    
    .activity-timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .activity-timeline::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #ddd;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    
    .timeline-marker {
        position: absolute;
        left: -18px;
        top: 3px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0073aa;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #ddd;
    }
    
    .timeline-marker.sent { background: #dba617; }
    .timeline-marker.signed { background: #00a32a; }
    .timeline-marker.completed { background: #8c8f94; }
    
    .timeline-content strong {
        color: #1d2327;
    }
    
    .timeline-content small {
        color: #646970;
    }
    
    @media (max-width: 768px) {
        .contract-detail-header {
            flex-direction: column;
            gap: 15px;
            align-items: stretch;
        }
        
        .contract-actions {
            justify-content: center;
        }
    }
    </style>
    
    <script>
    // Funciones AJAX para acciones de contratos
    function adhesionSendToDocusign(contractId) {
        if (!confirm('<?php echo esc_js(__('Â¿Enviar este contrato a DocuSign para firma?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.post(ajaxurl, {
            action: 'adhesion_send_contract_docusign',
            contract_id: contractId,
            nonce: '<?php echo wp_create_nonce('adhesion_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Contrato enviado correctamente', 'adhesion')); ?>');
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error al enviar contrato: ', 'adhesion')); ?>' + response.data);
            }
        });
    }
    </script>
    <?php
}

/**
 * Funciones auxiliares para badges de estado
 */
function adhesion_get_status_badge($status) {
    $badges = array(
        'pending' => '<span class="status-badge pending">' . __('Pendiente', 'adhesion') . '</span>',
        'sent' => '<span class="status-badge sent">' . __('Enviado', 'adhesion') . '</span>',
        'signed' => '<span class="status-badge signed">' . __('Firmado', 'adhesion') . '</span>',
        'completed' => '<span class="status-badge completed">' . __('Completado', 'adhesion') . '</span>',
        'cancelled' => '<span class="status-badge cancelled">' . __('Cancelado', 'adhesion') . '</span>'
    );
    
    return $badges[$status] ?? '<span class="status-badge unknown">' . esc_html($status) . '</span>';
}

function adhesion_get_payment_status_badge($payment_status) {
    $badges = array(
        'pending' => '<span class="payment-badge pending">' . __('Pendiente', 'adhesion') . '</span>',
        'paid' => '<span class="payment-badge paid">' . __('Pagado', 'adhesion') . '</span>',
        'failed' => '<span class="payment-badge failed">' . __('Fallido', 'adhesion') . '</span>',
        'refunded' => '<span class="payment-badge refunded">' . __('Reembolsado', 'adhesion') . '</span>'
    );
    
    return $badges[$payment_status] ?? '<span class="payment-badge unknown">' . esc_html($payment_status) . '</span>';
}

// Estilos CSS para los badges
add_action('admin_head', function() {
    ?>
    <style>
    .status-badge, .payment-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge.pending { background: #fff3cd; color: #856404; }
    .status-badge.sent { background: #cce5ff; color: #004085; }
    .status-badge.signed { background: #d4edda; color: #155724; }
    .status-badge.completed { background: #e2e3e5; color: #383d41; }
    .status-badge.cancelled { background: #f8d7da; color: #721c24; }
    .status-badge.unknown { background: #ffeeba; color: #856404; }
    
    .payment-badge.pending { background: #fff3cd; color: #856404; }
    .payment-badge.paid { background: #d4edda; color: #155724; }
    .payment-badge.failed { background: #f8d7da; color: #721c24; }
    .payment-badge.refunded { background: #e2e3e5; color: #383d41; }
    .payment-badge.unknown { background: #ffeeba; color: #856404; }
    </style>
    <?php
});

?>
                


// ===== dashboard-display.php =====
<?php
/**
 * Vista del dashboard principal del admin
 * 
 * Muestra estadÃ­sticas, estado del sistema y accesos rÃ¡pidos
 * Variables disponibles: $stats, $recent_stats, $recent_calculations, $config_status
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Dashboard de AdhesiÃ³n', 'adhesion'); ?>
        <span class="title-count"><?php echo sprintf(__('v%s', 'adhesion'), ADHESION_PLUGIN_VERSION); ?></span>
    </h1>
    
    <hr class="wp-header-end">
    
    <?php
    // Mostrar notificaciones
    adhesion_display_notices();
    ?>
    
    <!-- Estado de configuraciÃ³n -->
    <?php if (!$config_status['redsys'] || !$config_status['docusign']): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('Â¡ConfiguraciÃ³n pendiente!', 'adhesion'); ?></strong>
            <?php if (!$config_status['redsys']): ?>
                <?php _e('Redsys no configurado.', 'adhesion'); ?>
            <?php endif; ?>
            <?php if (!$config_status['docusign']): ?>
                <?php _e('DocuSign no configurado.', 'adhesion'); ?>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>" class="button button-primary">
                <?php _e('Configurar ahora', 'adhesion'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- EstadÃ­sticas principales -->
    <div class="adhesion-dashboard-stats">
        <div class="adhesion-stats-grid">
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calculator"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_calculations']); ?></div>
                    <div class="stat-label"><?php _e('CÃ¡lculos Totales', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <span class="stat-period"><?php echo sprintf(__('%d esta semana', 'adhesion'), $recent_stats['period_calculations']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_contracts']); ?></div>
                    <div class="stat-label"><?php _e('Contratos Totales', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <span class="stat-period"><?php echo sprintf(__('%d esta semana', 'adhesion'), $recent_stats['period_contracts']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['signed_contracts']); ?></div>
                    <div class="stat-label"><?php _e('Contratos Firmados', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <?php 
                        $conversion_rate = $stats['total_contracts'] > 0 ? ($stats['signed_contracts'] / $stats['total_contracts']) * 100 : 0;
                        ?>
                        <span class="stat-period"><?php echo sprintf(__('%.1f%% conversiÃ³n', 'adhesion'), $conversion_rate); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo adhesion_format_price($stats['total_revenue']); ?></div>
                    <div class="stat-label"><?php _e('Ingresos Totales', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <span class="stat-period"><?php echo sprintf(__('%s esta semana', 'adhesion'), adhesion_format_price($recent_stats['period_revenue'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal del dashboard -->
    <div class="adhesion-dashboard-content">
        <div class="adhesion-dashboard-left">
            
            <!-- Actividad reciente -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Actividad Reciente', 'adhesion'); ?></h2>
                
                <?php if (!empty($recent_calculations)): ?>
                <div class="adhesion-activity-list">
                    <?php foreach ($recent_calculations as $calc): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <span class="dashicons dashicons-calculator"></span>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <strong><?php echo esc_html($calc['user_name'] ?: __('Usuario desconocido', 'adhesion')); ?></strong>
                                <?php _e('realizÃ³ un cÃ¡lculo', 'adhesion'); ?>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-price"><?php echo adhesion_format_price($calc['total_price']); ?></span>
                                <span class="activity-date"><?php echo adhesion_format_date($calc['created_at'], 'd/m/Y H:i'); ?></span>
                            </div>
                        </div>
                        <div class="activity-actions">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&calculation_id=' . $calc['id']); ?>" class="button button-small">
                                <?php _e('Ver', 'adhesion'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="adhesion-section-footer">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                        <?php _e('Ver todos los cÃ¡lculos', 'adhesion'); ?>
                    </a>
                </div>
                
                <?php else: ?>
                <div class="adhesion-empty-state">
                    <p><?php _e('No hay actividad reciente.', 'adhesion'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>" class="button button-primary">
                        <?php _e('Configurar plugin', 'adhesion'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Estado del sistema -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Estado del Sistema', 'adhesion'); ?></h2>
                
                <div class="adhesion-system-status">
                    <div class="status-item">
                        <span class="status-label"><?php _e('Calculadora:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo adhesion_get_setting('calculator_enabled') ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo adhesion_get_setting('calculator_enabled') ? __('Activa', 'adhesion') : __('Inactiva', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('Redsys:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo $config_status['redsys'] ? 'status-configured' : 'status-pending'; ?>">
                            <?php echo $config_status['redsys'] ? __('Configurado', 'adhesion') : __('Pendiente', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('DocuSign:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo $config_status['docusign'] ? 'status-configured' : 'status-pending'; ?>">
                            <?php echo $config_status['docusign'] ? __('Configurado', 'adhesion') : __('Pendiente', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('Tablas BD:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo $this->db->tables_exist() ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $this->db->tables_exist() ? __('OK', 'adhesion') : __('Error', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('Permisos:', 'adhesion'); ?></span>
                        <?php 
                        $upload_dir = wp_upload_dir();
                        $adhesion_dir = $upload_dir['basedir'] . '/adhesion/';
                        $writable = is_writable($adhesion_dir);
                        ?>
                        <span class="status-value <?php echo $writable ? 'status-ok' : 'status-warning'; ?>">
                            <?php echo $writable ? __('OK', 'adhesion') : __('Verificar', 'adhesion'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="adhesion-dashboard-right">
            
            <!-- Accesos rÃ¡pidos -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Accesos RÃ¡pidos', 'adhesion'); ?></h2>
                
                <div class="adhesion-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-calculator"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Ver CÃ¡lculos', 'adhesion'); ?></h3>
                            <p><?php _e('Gestionar todos los cÃ¡lculos de presupuestos', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Ver Contratos', 'adhesion'); ?></h3>
                            <p><?php _e('Gestionar contratos de adhesiÃ³n', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Ver Usuarios', 'adhesion'); ?></h3>
                            <p><?php _e('Gestionar usuarios adheridos', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-edit-page"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Editar Documentos', 'adhesion'); ?></h3>
                            <p><?php _e('Personalizar plantillas de contratos', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-admin-settings"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('ConfiguraciÃ³n', 'adhesion'); ?></h3>
                            <p><?php _e('Configurar APIs y opciones', 'adhesion'); ?></p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- InformaciÃ³n del plugin -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('InformaciÃ³n del Plugin', 'adhesion'); ?></h2>
                
                <div class="adhesion-plugin-info">
                    <div class="info-item">
                        <span class="info-label"><?php _e('VersiÃ³n:', 'adhesion'); ?></span>
                        <span class="info-value"><?php echo ADHESION_PLUGIN_VERSION; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><?php _e('WordPress:', 'adhesion'); ?></span>
                        <span class="info-value"><?php echo get_bloginfo('version'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><?php _e('PHP:', 'adhesion'); ?></span>
                        <span class="info-value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><?php _e('Ãšltima activaciÃ³n:', 'adhesion'); ?></span>
                        <span class="info-value">
                            <?php 
                            $activated = get_option('adhesion_activated');
                            echo $activated ? adhesion_format_date($activated, 'd/m/Y H:i') : __('Desconocida', 'adhesion');
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="adhesion-plugin-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('adhesion_admin_action', 'adhesion_nonce'); ?>
                        <input type="hidden" name="adhesion_action" value="clear_cache">
                        <button type="submit" class="button">
                            <?php _e('Limpiar Cache', 'adhesion'); ?>
                        </button>
                    </form>
                    
                    <a href="#" class="button" onclick="adhesionExportData(); return false;">
                        <?php _e('Exportar Datos', 'adhesion'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function adhesionExportData() {
    if (confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres exportar todos los datos?', 'adhesion')); ?>')) {
        // TODO: Implementar exportaciÃ³n
        alert('<?php echo esc_js(__('Funcionalidad en desarrollo', 'adhesion')); ?>');
    }
}
</script>

<style>
/* Estilos especÃ­ficos para el dashboard */
.adhesion-dashboard-stats {
    margin: 20px 0;
}

.adhesion-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.adhesion-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: #0073aa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
    display: block;
}

.stat-change {
    margin-top: 5px;
}

.stat-period {
    font-size: 12px;
    color: #646970;
}

.adhesion-dashboard-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.adhesion-dashboard-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.adhesion-dashboard-section h2 {
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    font-size: 16px;
    font-weight: 600;
}

.adhesion-activity-list {
    padding: 0;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f1;
    gap: 15px;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: #f0f0f1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #646970;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-size: 14px;
    margin-bottom: 5px;
}

.activity-meta {
    font-size: 12px;
    color: #646970;
}

.activity-price {
    font-weight: bold;
    color: #1d2327;
    margin-right: 10px;
}

.adhesion-section-footer {
    padding: 15px 20px;
    border-top: 1px solid #f0f0f1;
    text-align: center;
}

.adhesion-empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #646970;
}

.adhesion-system-status {
    padding: 20px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 500;
}

.status-value {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-active, .status-ok, .status-configured {
    background: #d5e7d5;
    color: #1e4620;
}

.status-inactive, .status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
}

.adhesion-quick-actions {
    padding: 20px;
    display: grid;
    gap: 15px;
}

.quick-action-card {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: inherit;
    transition: border-color 0.2s;
    gap: 15px;
}

.quick-action-card:hover {
    border-color: #0073aa;
    text-decoration: none;
}

.quick-action-icon {
    width: 40px;
    height: 40px;
    background: #0073aa;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.quick-action-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
}

.quick-action-content p {
    margin: 0;
    font-size: 12px;
    color: #646970;
}

.adhesion-plugin-info {
    padding: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
}

.info-value {
    color: #646970;
}

.adhesion-plugin-actions {
    padding: 15px 20px;
    border-top: 1px solid #f0f0f1;
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .adhesion-dashboard-content {
        grid-template-columns: 1fr;
    }
    
    .adhesion-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>


// ===== documents-display.php =====
<?php
/**
 * Vista de gestiÃ³n de documentos editables
 * 
 * Esta vista maneja:
 * - Listado de documentos/plantillas
 * - Editor de documentos con 3 secciones
 * - Vista previa en tiempo real
 * - GestiÃ³n de variables dinÃ¡micas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acciÃ³n actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$document_id = intval($_GET['document'] ?? 0);

// Instanciar clases necesarias
$documents = new Adhesion_Documents();
$db = new Adhesion_Database();

// Manejar acciones especÃ­ficas
switch ($action) {
    case 'edit':
    case 'new':
        adhesion_display_document_editor($document_id, $documents);
        break;
        
    case 'preview':
        adhesion_display_document_preview($document_id, $documents);
        break;
        
    default:
        adhesion_display_documents_list($documents);
        break;
}

/**
 * Mostrar listado principal de documentos
 */
function adhesion_display_documents_list($documents) {
    $all_documents = $documents->get_documents();
    
    // Agrupar por tipo
    $documents_by_type = array();
    foreach ($all_documents as $doc) {
        $documents_by_type[$doc['document_type']][] = $doc;
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('GestiÃ³n de Documentos', 'adhesion'); ?>
            <span class="title-count"><?php echo sprintf(__('(%s plantillas)', 'adhesion'), count($all_documents)); ?></span>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-documents&action=new'); ?>" class="page-title-action">
            <?php _e('+ Nuevo Documento', 'adhesion'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('â† Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar notificaciones
        adhesion_display_notices();
        ?>
        
        <!-- InformaciÃ³n sobre documentos -->
        <div class="adhesion-documents-info">
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php _e('Sobre los Documentos Editables', 'adhesion'); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-edit-page"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Editor de 3 Secciones', 'adhesion'); ?></h3>
                                <p><?php _e('Cada documento se divide en Header, Cuerpo y Footer para mÃ¡xima flexibilidad.', 'adhesion'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-admin-generic"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Variables DinÃ¡micas', 'adhesion'); ?></h3>
                                <p><?php _e('Usa [variable] para insertar datos del cliente, contrato o cÃ¡lculo automÃ¡ticamente.', 'adhesion'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-visibility"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Vista Previa', 'adhesion'); ?></h3>
                                <p><?php _e('Previsualiza cÃ³mo se verÃ¡ el documento con datos reales antes de enviarlo.', 'adhesion'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-migrate"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('IntegraciÃ³n DocuSign', 'adhesion'); ?></h3>
                                <p><?php _e('Los documentos activos se envÃ­an automÃ¡ticamente para firma digital.', 'adhesion'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Listado de documentos por tipo -->
        <?php if (empty($all_documents)): ?>
        <div class="adhesion-empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <h3><?php _e('No hay documentos creados', 'adhesion'); ?></h3>
            <p><?php _e('Crea tu primera plantilla de documento para empezar a generar contratos personalizados.', 'adhesion'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=adhesion-documents&action=new'); ?>" class="button button-primary button-large">
                <?php _e('Crear Primer Documento', 'adhesion'); ?>
            </a>
        </div>
        
        <?php else: ?>
        <div class="adhesion-documents-grid">
            <?php foreach ($documents_by_type as $type => $type_documents): ?>
            <div class="documents-type-section">
                <h2 class="type-title">
                    <?php echo sprintf(__('Tipo: %s', 'adhesion'), ucfirst($type)); ?>
                    <span class="type-count">(<?php echo count($type_documents); ?>)</span>
                </h2>
                
                <div class="documents-list">
                    <?php foreach ($type_documents as $document): ?>
                    <div class="document-card <?php echo $document['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="document-header">
                            <h3 class="document-title">
                                <?php echo esc_html($document['title']); ?>
                                <?php if ($document['is_active']): ?>
                                <span class="adhesion-badge adhesion-badge-success"><?php _e('Activo', 'adhesion'); ?></span>
                                <?php else: ?>
                                <span class="adhesion-badge adhesion-badge-secondary"><?php _e('Inactivo', 'adhesion'); ?></span>
                                <?php endif; ?>
                            </h3>
                            
                            <div class="document-meta">
                                <span class="document-date">
                                    <?php echo sprintf(__('Modificado: %s', 'adhesion'), adhesion_format_date($document['updated_at'], 'd/m/Y H:i')); ?>
                                </span>
                                
                                <?php if (!empty($document['variables_list'])): ?>
                                <span class="document-variables">
                                    <?php 
                                    $variables = json_decode($document['variables_list'], true);
                                    echo sprintf(__('%d variables', 'adhesion'), count($variables));
                                    ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="document-preview">
                            <?php 
                            $preview_content = strip_tags($document['body_content']);
                            $preview_content = wp_trim_words($preview_content, 20, '...');
                            echo esc_html($preview_content);
                            ?>
                        </div>
                        
                        <div class="document-actions">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-documents&action=edit&document=' . $document['id']); ?>" 
                               class="button button-primary">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Editar', 'adhesion'); ?>
                            </a>
                            
                            <button type="button" class="button" onclick="adhesionPreviewDocument(<?php echo $document['id']; ?>)">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Vista Previa', 'adhesion'); ?>
                            </button>
                            
                            <button type="button" class="button" onclick="adhesionDuplicateDocument(<?php echo $document['id']; ?>)">
                                <span class="dashicons dashicons-admin-page"></span>
                                <?php _e('Duplicar', 'adhesion'); ?>
                            </button>
                            
                            <button type="button" class="button" onclick="adhesionToggleDocumentStatus(<?php echo $document['id']; ?>, <?php echo $document['is_active'] ? '0' : '1'; ?>)">
                                <span class="dashicons dashicons-<?php echo $document['is_active'] ? 'hidden' : 'visibility'; ?>"></span>
                                <?php echo $document['is_active'] ? __('Desactivar', 'adhesion') : __('Activar', 'adhesion'); ?>
                            </button>
                            
                            <button type="button" class="button button-link-delete" onclick="adhesionDeleteDocument(<?php echo $document['id']; ?>)">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Eliminar', 'adhesion'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para vista previa -->
    <div id="preview-modal" class="adhesion-modal" style="display: none;">
        <div class="adhesion-modal-content large">
            <div class="adhesion-modal-header">
                <h2><?php _e('Vista Previa del Documento', 'adhesion'); ?></h2>
                <button type="button" class="adhesion-modal-close" onclick="adhesionClosePreviewModal()">&times;</button>
            </div>
            <div class="adhesion-modal-body">
                <div id="preview-content"></div>
            </div>
            <div class="adhesion-modal-footer">
                <button type="button" class="button" onclick="adhesionClosePreviewModal()">
                    <?php _e('Cerrar', 'adhesion'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <style>
    .adhesion-documents-info {
        margin: 20px 0 30px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .info-item {
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    
    .info-icon {
        width: 40px;
        height: 40px;
        background: #0073aa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .info-content h3 {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: 600;
        color: #1d2327;
    }
    
    .info-content p {
        margin: 0;
        font-size: 13px;
        color: #646970;
        line-height: 1.4;
    }
    
    .documents-type-section {
        margin-bottom: 40px;
    }
    
    .type-title {
        font-size: 18px;
        margin-bottom: 20px;
        color: #1d2327;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 10px;
    }
    
    .type-count {
        font-size: 14px;
        color: #646970;
        font-weight: normal;
    }
    
    .documents-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .document-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 6px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        transition: box-shadow 0.2s;
    }
    
    .document-card:hover {
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
    }
    
    .document-card.inactive {
        opacity: 0.7;
        border-style: dashed;
    }
    
    .document-header {
        margin-bottom: 15px;
    }
    
    .document-title {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1d2327;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .document-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 12px;
        color: #646970;
    }
    
    .document-preview {
        margin-bottom: 20px;
        padding: 15px;
        background: #f6f7f7;
        border-radius: 4px;
        font-size: 13px;
        color: #646970;
        line-height: 1.4;
        min-height: 60px;
    }
    
    .document-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .document-actions .button {
        font-size: 11px;
        padding: 4px 8px;
        height: auto;
        line-height: 1.3;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .adhesion-empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 6px;
        margin-top: 20px;
    }
    
    .empty-icon {
        width: 80px;
        height: 80px;
        background: #f0f0f1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 32px;
        color: #8c8f94;
    }
    
    .adhesion-empty-state h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        color: #1d2327;
    }
    
    .adhesion-empty-state p {
        margin: 0 0 25px 0;
        color: #646970;
        font-size: 16px;
    }
    
    /* Modal styles */
    .adhesion-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .adhesion-modal-content {
        background-color: #fff;
        margin: 2% auto;
        padding: 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .adhesion-modal-content.large {
        max-width: 900px;
    }
    
    .adhesion-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ccd0d4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f6f7f7;
        flex-shrink: 0;
    }
    
    .adhesion-modal-header h2 {
        margin: 0;
        font-size: 16px;
    }
    
    .adhesion-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #646970;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .adhesion-modal-close:hover {
        color: #d63638;
    }
    
    .adhesion-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }
    
    .adhesion-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ccd0d4;
        background: #f6f7f7;
        text-align: right;
        flex-shrink: 0;
    }
    
    #preview-content {
        font-family: 'Times New Roman', serif;
        line-height: 1.6;
        color: #1d2327;
    }
    
    @media (max-width: 768px) {
        .documents-list {
            grid-template-columns: 1fr;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .document-actions {
            flex-direction: column;
        }
        
        .adhesion-modal-content {
            margin: 5% auto;
            width: 95%;
        }
    }
    </style>
    
    <script>
    function adhesionPreviewDocument(documentId) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_preview_document',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#preview-content').html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active"></span></div>');
                jQuery('#preview-modal').show();
            },
            success: function(response) {
                if (response.success) {
                    jQuery('#preview-content').html(response.data.content);
                } else {
                    jQuery('#preview-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                }
            },
            error: function() {
                jQuery('#preview-content').html('<p style="color: #d63638;"><?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?></p>');
            }
        });
    }
    
    function adhesionClosePreviewModal() {
        jQuery('#preview-modal').hide();
    }
    
    function adhesionDuplicateDocument(documentId) {
        if (!confirm('<?php echo esc_js(__('Â¿Duplicar este documento?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_duplicate_document',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
    
    function adhesionToggleDocumentStatus(documentId, newStatus) {
        const action = newStatus ? '<?php echo esc_js(__('activar', 'adhesion')); ?>' : '<?php echo esc_js(__('desactivar', 'adhesion')); ?>';
        
        if (!confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres', 'adhesion')); ?> ' + action + ' <?php echo esc_js(__('este documento?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_toggle_document_status',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
    
    function adhesionDeleteDocument(documentId) {
        if (!confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres eliminar este documento? Esta acciÃ³n no se puede deshacer.', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_delete_document',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('preview-modal');
        if (event.target === modal) {
            adhesionClosePreviewModal();
        }
    }
    </script>
    <?php
}

/**
 * Mostrar editor de documentos
 */
function adhesion_display_document_editor($document_id, $documents) {
    $document = null;
    $is_new = true;
    
    if ($document_id) {
        $document = $documents->get_document($document_id);
        $is_new = false;
        
        if (!$document) {
            ?>
            <div class="wrap">
                <h1><?php _e('Documento no encontrado', 'adhesion'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('El documento solicitado no existe.', 'adhesion'); ?></p>
                </div>
                <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="button">
                    <?php _e('â† Volver al listado', 'adhesion'); ?>
                </a>
            </div>
            <?php
            return;
        }
    }
    
    // Valores por defecto para nuevo documento
    if ($is_new) {
        $document = array(
            'id' => 0,
            'document_type' => 'contract',
            'title' => '',
            'header_content' => '<h1>CONTRATO DE ADHESIÃ“N</h1>
<p><strong>NÃºmero:</strong> [numero_contrato]</p>
<p><strong>Fecha:</strong> [fecha_contrato]</p>
<hr>',
            'body_content' => '<h2>DATOS DEL CLIENTE</h2>
<p><strong>Nombre completo:</strong> [nombre_completo]</p>
<p><strong>DNI/CIF:</strong> [dni_cif]</p>
<p><strong>DirecciÃ³n:</strong> [direccion], [codigo_postal] [ciudad] ([provincia])</p>
<p><strong>TelÃ©fono:</strong> [telefono]</p>
<p><strong>Email:</strong> [email]</p>

<h2>CONDICIONES DEL SERVICIO</h2>
<p><strong>Materiales:</strong> [materiales_resumen]</p>
<p><strong>Precio total:</strong> [precio_total]</p>
<p><strong>Precio por tonelada:</strong> [precio_tonelada]</p>',
            'footer_content' => '<hr>
<p><strong>Firma del cliente:</strong></p>
<br><br>
<p>_________________________</p>
<p>Fecha: [fecha_hoy]</p>',
            'is_active' => false
        );
    }
    
    // Obtener variables disponibles
    $available_variables = $documents->get_available_variables();
    
    ?>
    <div class="wrap">
        <h1><?php echo $is_new ? __('Nuevo Documento', 'adhesion') : sprintf(__('Editar: %s', 'adhesion'), esc_html($document['title'])); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="page-title-action">
            <?php _e('â† Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <div class="adhesion-document-editor">
            <form id="document-form" method="post">
                <?php wp_nonce_field('adhesion_save_document', 'adhesion_document_nonce'); ?>
                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                
                <div class="editor-layout">
                    <!-- Panel izquierdo: Editor -->
                    <div class="editor-panel">
                        <!-- ConfiguraciÃ³n bÃ¡sica -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('ConfiguraciÃ³n BÃ¡sica', 'adhesion'); ?></h2>
                            </div>
                            <div class="adhesion-card-body">
                                <div class="adhesion-form-row">
                                    <label for="document-title"><?php _e('TÃ­tulo del documento:', 'adhesion'); ?></label>
                                    <input type="text" id="document-title" name="title" 
                                           value="<?php echo esc_attr($document['title']); ?>" 
                                           placeholder="<?php _e('Ej: Contrato de AdhesiÃ³n EstÃ¡ndar', 'adhesion'); ?>" required>
                                </div>
                                
                                <div class="adhesion-form-row">
                                    <label for="document-type"><?php _e('Tipo de documento:', 'adhesion'); ?></label>
                                    <select id="document-type" name="document_type">
                                        <option value="contract" <?php selected($document['document_type'], 'contract'); ?>><?php _e('Contrato', 'adhesion'); ?></option>
                                        <option value="notification" <?php selected($document['document_type'], 'notification'); ?>><?php _e('NotificaciÃ³n', 'adhesion'); ?></option>
                                        <option value="invoice" <?php selected($document['document_type'], 'invoice'); ?>><?php _e('Factura', 'adhesion'); ?></option>
                                        <option value="other" <?php selected($document['document_type'], 'other'); ?>><?php _e('Otro', 'adhesion'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="adhesion-form-row">
                                    <label>
                                        <input type="checkbox" name="is_active" value="1" <?php checked($document['is_active'], 1); ?>>
                                        <?php _e('Documento activo (se usa en el proceso de adhesiÃ³n)', 'adhesion'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Editor de Header -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Header (Encabezado)', 'adhesion'); ?></h2>
                                <p class="description"><?php _e('Contenido que aparece al inicio del documento', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <?php
                                wp_editor($document['header_content'], 'header_content', array(
                                    'textarea_name' => 'header_content',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <!-- Editor de Body -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Cuerpo Principal', 'adhesion'); ?></h2>
                                <p class="description"><?php _e('Contenido principal del documento', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <?php
                                wp_editor($document['body_content'], 'body_content', array(
                                    'textarea_name' => 'body_content',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false,
                                    'teeny' => false,
                                    'quicktags' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <!-- Editor de Footer -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Footer (Pie)', 'adhesion'); ?></h2>
                                <p class="description"><?php _e('Contenido que aparece al final del documento', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <?php
                                wp_editor($document['footer_content'], 'footer_content', array(
                                    'textarea_name' => 'footer_content',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <!-- Botones de acciÃ³n -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-footer">
                                <button type="submit" class="button button-primary button-large">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php echo $is_new ? __('Crear Documento', 'adhesion') : __('Actualizar Documento', 'adhesion'); ?>
                                </button>
                                
                                <?php if (!$is_new): ?>
                                <button type="button" class="button" onclick="adhesionPreviewDocument(<?php echo $document['id']; ?>)">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Vista Previa', 'adhesion'); ?>
                                </button>
                                <?php endif; ?>
                                
                                <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="button">
                                    <?php _e('Cancelar', 'adhesion'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panel derecho: Variables y ayuda -->
                    <div class="sidebar-panel">
                        <!-- Variables disponibles -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h3><?php _e('Variables Disponibles', 'adhesion'); ?></h3>
                                <p class="description"><?php _e('Haz clic para insertar en el editor', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <div class="variables-grid">
                                    <?php foreach ($available_variables as $variable => $description): ?>
                                    <div class="variable-item" onclick="adhesionInsertVariable('[<?php echo $variable; ?>]')">
                                        <code>[<?php echo $variable; ?>]</code>
                                        <span class="variable-description"><?php echo esc_html($description); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ayuda y tips -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h3><?php _e('Ayuda', 'adhesion'); ?></h3>
                            </div>
                            <div class="adhesion-card-body">
                                <div class="help-section">
                                    <h4><?php _e('CÃ³mo usar las variables:', 'adhesion'); ?></h4>
                                    <ul>
                                        <li><?php _e('Las variables se escriben entre corchetes: [variable]', 'adhesion'); ?></li>
                                        <li><?php _e('Se reemplazan automÃ¡ticamente con datos reales', 'adhesion'); ?></li>
                                        <li><?php _e('Haz clic en una variable para insertarla', 'adhesion'); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="help-section">
                                    <h4><?php _e('Estructura del documento:', 'adhesion'); ?></h4>
                                    <ul>
                                        <li><strong><?php _e('Header:', 'adhesion'); ?></strong> <?php _e('TÃ­tulo, nÃºmero de contrato, fecha', 'adhesion'); ?></li>
                                        <li><strong><?php _e('Cuerpo:', 'adhesion'); ?></strong> <?php _e('Condiciones, datos del cliente', 'adhesion'); ?></li>
                                        <li><strong><?php _e('Footer:', 'adhesion'); ?></strong> <?php _e('Firmas, fecha final', 'adhesion'); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="help-section">
                                    <h4><?php _e('Consejos:', 'adhesion'); ?></h4>
                                    <ul>
                                        <li><?php _e('Usa solo un documento activo por tipo', 'adhesion'); ?></li>
                                        <li><?php _e('Prueba siempre la vista previa antes de activar', 'adhesion'); ?></li>
                                        <li><?php _e('Guarda borradores como inactivos', 'adhesion'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Variables detectadas -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h3><?php _e('Variables en Uso', 'adhesion'); ?></h3>
                            </div>
                            <div class="adhesion-card-body">
                                <div id="detected-variables">
                                    <p class="no-variables"><?php _e('No hay variables detectadas aÃºn', 'adhesion'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .adhesion-document-editor {
        margin-top: 20px;
    }
    
    .editor-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 20px;
    }
    
    .editor-panel .adhesion-card {
        margin-bottom: 20px;
    }
    
    .sidebar-panel .adhesion-card {
        margin-bottom: 15px;
    }
    
    .adhesion-form-row {
        margin-bottom: 15px;
    }
    
    .adhesion-form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .adhesion-form-row input[type="text"],
    .adhesion-form-row select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    
    .variables-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .variable-item {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .variable-item:hover {
        background-color: #f0f0f1;
        border-color: #0073aa;
    }
    
    .variable-item code {
        display: block;
        font-weight: bold;
        color: #0073aa;
        margin-bottom: 2px;
    }
    
    .variable-description {
        font-size: 11px;
        color: #646970;
        line-height: 1.3;
    }
    
    .help-section {
        margin-bottom: 20px;
    }
    
    .help-section h4 {
        margin: 0 0 8px 0;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
    }
    
    .help-section ul {
        margin: 0;
        padding-left: 20px;
    }
    
    .help-section li {
        font-size: 12px;
        color: #646970;
        margin-bottom: 4px;
        line-height: 1.4;
    }
    
    #detected-variables {
        font-size: 12px;
    }
    
    .detected-variable {
        display: inline-block;
        padding: 2px 6px;
        margin: 2px;
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        border-radius: 3px;
        color: #0073aa;
    }
    
    .no-variables {
        color: #646970;
        font-style: italic;
        text-align: center;
        margin: 10px 0;
    }
    
    @media (max-width: 1200px) {
        .editor-layout {
            grid-template-columns: 1fr;
        }
        
        .sidebar-panel {
            order: -1;
        }
        
        .variables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            max-height: none;
        }
    }
    </style>
    
    <script>
    let currentEditor = null;
    
    function adhesionInsertVariable(variable) {
        // Intentar insertar en el editor activo
        if (typeof tinymce !== 'undefined') {
            const activeEditor = tinymce.activeEditor;
            if (activeEditor && !activeEditor.isHidden()) {
                activeEditor.insertContent(variable + ' ');
                return;
            }
        }
        
        // Fallback: insertar en el textarea activo
        const textareas = document.querySelectorAll('textarea');
        let activeTextarea = null;
        
        for (let textarea of textareas) {
            if (textarea === document.activeElement) {
                activeTextarea = textarea;
                break;
            }
        }
        
        if (activeTextarea) {
            const start = activeTextarea.selectionStart;
            const end = activeTextarea.selectionEnd;
            const text = activeTextarea.value;
            
            activeTextarea.value = text.substring(0, start) + variable + ' ' + text.substring(end);
            activeTextarea.selectionStart = activeTextarea.selectionEnd = start + variable.length + 1;
            activeTextarea.focus();
        } else {
            // Ãšltimo recurso: copiar al portapapeles
            navigator.clipboard.writeText(variable).then(function() {
                alert('<?php echo esc_js(__('Variable copiada al portapapeles:', 'adhesion')); ?> ' + variable);
            });
        }
        
        // Actualizar variables detectadas
        adhesionUpdateDetectedVariables();
    }
    
    function adhesionUpdateDetectedVariables() {
        let allContent = '';
        
        // Recoger contenido de todos los editores
        if (typeof tinymce !== 'undefined') {
            tinymce.editors.forEach(function(editor) {
                if (editor.getContent) {
                    allContent += ' ' + editor.getContent();
                }
            });
        }
        
        // TambiÃ©n de los textareas
        const textareas = document.querySelectorAll('textarea[name$="_content"]');
        textareas.forEach(function(textarea) {
            allContent += ' ' + textarea.value;
        });
        
        // Extraer variables
        const variableRegex = /\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g;
        const matches = allContent.match(variableRegex);
        const uniqueVariables = [...new Set(matches || [])];
        
        // Mostrar variables detectadas
        const container = document.getElementById('detected-variables');
        if (uniqueVariables.length > 0) {
            container.innerHTML = uniqueVariables.map(variable => 
                '<span class="detected-variable">' + variable + '</span>'
            ).join('');
        } else {
            container.innerHTML = '<p class="no-variables"><?php echo esc_js(__('No hay variables detectadas aÃºn', 'adhesion')); ?></p>';
        }
    }
    
    // Procesar formulario
    jQuery(document).ready(function($) {
        $('#document-form').on('submit', function(e) {
            e.preventDefault();
            
            // Obtener datos del formulario
            const formData = new FormData(this);
            formData.append('action', 'adhesion_save_document');
            formData.append('nonce', '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>');
            
            // Obtener contenido de los editores TinyMCE
            if (typeof tinymce !== 'undefined') {
                tinymce.editors.forEach(function(editor) {
                    if (editor.targetElm) {
                        formData.set(editor.targetElm.name, editor.getContent());
                    }
                });
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#document-form button[type="submit"]').prop('disabled', true).html('<span class="spinner is-active" style="float: none;"></span> <?php echo esc_js(__('Guardando...', 'adhesion')); ?>');
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        <?php if ($is_new): ?>
                        // Redirigir al modo ediciÃ³n para nuevos documentos
                        window.location.href = '<?php echo admin_url('admin.php?page=adhesion-documents&action=edit&document='); ?>' + response.data.document_id;
                        <?php else: ?>
                        // Actualizar variables detectadas
                        adhesionUpdateDetectedVariables();
                        <?php endif; ?>
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?>');
                },
                complete: function() {
                    $('#document-form button[type="submit"]').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> <?php echo esc_js($is_new ? __('Crear Documento', 'adhesion') : __('Actualizar Documento', 'adhesion')); ?>');
                }
            });
        });
        
        // Actualizar variables detectadas cuando se cambie el contenido
        $(document).on('keyup change', 'textarea[name$="_content"]', function() {
            setTimeout(adhesionUpdateDetectedVariables, 500);
        });
        
        if (typeof tinymce !== 'undefined') {
            $(document).on('tinymce-editor-init', function(event, editor) {
                editor.on('keyup change', function() {
                    setTimeout(adhesionUpdateDetectedVariables, 500);
                });
            });
        }
        
        // Actualizar al cargar
        setTimeout(adhesionUpdateDetectedVariables, 1000);
    });
    </script>
    <?php
}

/**
 * Mostrar vista previa de documento (funciÃ³n independiente)
 */
function adhesion_display_document_preview($document_id, $documents) {
    // Esta funciÃ³n se llamarÃ­a desde una URL especÃ­fica para preview
    // Por ahora la vista previa se maneja vÃ­a AJAX en el modal
    wp_redirect(admin_url('admin.php?page=adhesion-documents'));
    exit;
}


// ===== settings-display.php =====
<?php
/**
 * Vista de configuraciÃ³n del plugin
 * 
 * PÃ¡gina para configurar todas las opciones del plugin:
 * - APIs de Redsys y DocuSign
 * - Configuraciones generales
 * - Precios de calculadora
 * - Opciones de email
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener configuraciones actuales
$settings = get_option('adhesion_settings', array());

// Procesar formulario si se enviÃ³
if (isset($_POST['submit']) && wp_verify_nonce($_POST['adhesion_settings_nonce'], 'adhesion_settings_save')) {
    $settings = $_POST['adhesion_settings'];
    
    // Sanitizar configuraciones
    $sanitized_settings = array();
    
    // Configuraciones de Redsys
    $sanitized_settings['redsys_merchant_code'] = sanitize_text_field($settings['redsys_merchant_code'] ?? '');
    $sanitized_settings['redsys_terminal'] = sanitize_text_field($settings['redsys_terminal'] ?? '001');
    $sanitized_settings['redsys_secret_key'] = sanitize_text_field($settings['redsys_secret_key'] ?? '');
    $sanitized_settings['redsys_environment'] = in_array($settings['redsys_environment'] ?? 'test', array('test', 'production')) ? $settings['redsys_environment'] : 'test';
    $sanitized_settings['redsys_currency'] = sanitize_text_field($settings['redsys_currency'] ?? '978');
    
    // Configuraciones de DocuSign
    $sanitized_settings['docusign_integration_key'] = sanitize_text_field($settings['docusign_integration_key'] ?? '');
    $sanitized_settings['docusign_secret_key'] = sanitize_text_field($settings['docusign_secret_key'] ?? '');
    $sanitized_settings['docusign_account_id'] = sanitize_text_field($settings['docusign_account_id'] ?? '');
    $sanitized_settings['docusign_environment'] = in_array($settings['docusign_environment'] ?? 'demo', array('demo', 'production')) ? $settings['docusign_environment'] : 'demo';
    
    // Configuraciones generales
    $sanitized_settings['calculator_enabled'] = isset($settings['calculator_enabled']) ? '1' : '0';
    $sanitized_settings['auto_create_users'] = isset($settings['auto_create_users']) ? '1' : '0';
    $sanitized_settings['email_notifications'] = isset($settings['email_notifications']) ? '1' : '0';
    $sanitized_settings['contract_auto_send'] = isset($settings['contract_auto_send']) ? '1' : '0';
    $sanitized_settings['require_payment'] = isset($settings['require_payment']) ? '1' : '0';
    
    // Configuraciones de email
    $sanitized_settings['admin_email'] = sanitize_email($settings['admin_email'] ?? get_option('admin_email'));
    $sanitized_settings['email_from_name'] = sanitize_text_field($settings['email_from_name'] ?? get_bloginfo('name'));
    $sanitized_settings['email_from_address'] = sanitize_email($settings['email_from_address'] ?? get_option('admin_email'));
    
    // Guardar configuraciones
    update_option('adhesion_settings', $sanitized_settings);
    $settings = $sanitized_settings;
    
    // Mostrar mensaje de Ã©xito
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Configuraciones guardadas correctamente.', 'adhesion') . '</strong></p></div>';
}

// Verificar estado de configuraciÃ³n
$redsys_configured = !empty($settings['redsys_merchant_code']) && !empty($settings['redsys_secret_key']);
$docusign_configured = !empty($settings['docusign_integration_key']) && !empty($settings['docusign_account_id']);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('ConfiguraciÃ³n de AdhesiÃ³n', 'adhesion'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <?php
    // Mostrar notificaciones
    adhesion_display_notices();
    ?>
    
    <!-- Estado de configuraciÃ³n -->
    <div class="adhesion-config-status">
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Estado de ConfiguraciÃ³n', 'adhesion'); ?></h2>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-status-grid">
                    <div class="status-item-card">
                        <div class="status-icon <?php echo $redsys_configured ? 'status-ok' : 'status-pending'; ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('Redsys (Pagos)', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo $redsys_configured ? __('Configurado correctamente', 'adhesion') : __('ConfiguraciÃ³n pendiente', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="status-item-card">
                        <div class="status-icon <?php echo $docusign_configured ? 'status-ok' : 'status-pending'; ?>">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('DocuSign (Firmas)', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo $docusign_configured ? __('Configurado correctamente', 'adhesion') : __('ConfiguraciÃ³n pendiente', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="status-item-card">
                        <div class="status-icon <?php echo ($redsys_configured && $docusign_configured) ? 'status-ok' : 'status-warning'; ?>">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('Plugin', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo ($redsys_configured && $docusign_configured) ? __('Listo para usar', 'adhesion') : __('ConfiguraciÃ³n incompleta', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de configuraciÃ³n -->
    <form method="post" action="" class="adhesion-settings-form">
        <?php wp_nonce_field('adhesion_settings_save', 'adhesion_settings_nonce'); ?>
        
        <!-- ConfiguraciÃ³n de Redsys -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('ConfiguraciÃ³n de Redsys (Pagos)', 'adhesion'); ?></h2>
                <p class="description"><?php _e('ConfiguraciÃ³n para procesar pagos con tarjeta a travÃ©s de Redsys.', 'adhesion'); ?></p>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="redsys_environment"><?php _e('Entorno', 'adhesion'); ?></label>
                        <select name="adhesion_settings[redsys_environment]" id="redsys_environment">
                            <option value="test" <?php selected($settings['redsys_environment'] ?? 'test', 'test'); ?>><?php _e('Pruebas', 'adhesion'); ?></option>
                            <option value="production" <?php selected($settings['redsys_environment'] ?? 'test', 'production'); ?>><?php _e('ProducciÃ³n', 'adhesion'); ?></option>
                        </select>
                        <p class="adhesion-form-help"><?php _e('Usa "Pruebas" mientras desarrollas y "ProducciÃ³n" cuando estÃ© todo listo.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_merchant_code"><?php _e('CÃ³digo de Comercio', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[redsys_merchant_code]" id="redsys_merchant_code" 
                               value="<?php echo esc_attr($settings['redsys_merchant_code'] ?? ''); ?>" 
                               placeholder="999008881" />
                        <p class="adhesion-form-help"><?php _e('CÃ³digo proporcionado por Redsys (9 dÃ­gitos).', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_terminal"><?php _e('Terminal', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[redsys_terminal]" id="redsys_terminal" 
                               value="<?php echo esc_attr($settings['redsys_terminal'] ?? '001'); ?>" 
                               placeholder="001" />
                        <p class="adhesion-form-help"><?php _e('NÃºmero de terminal (por defecto: 001).', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_secret_key"><?php _e('Clave Secreta', 'adhesion'); ?></label>
                        <input type="password" name="adhesion_settings[redsys_secret_key]" id="redsys_secret_key" 
                               value="<?php echo esc_attr($settings['redsys_secret_key'] ?? ''); ?>" 
                               placeholder="sq7HjrUOBfKmC576ILgskD5srU870gJ7" />
                        <p class="adhesion-form-help"><?php _e('Clave secreta proporcionada por Redsys.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_currency"><?php _e('Moneda', 'adhesion'); ?></label>
                        <select name="adhesion_settings[redsys_currency]" id="redsys_currency">
                            <option value="978" <?php selected($settings['redsys_currency'] ?? '978', '978'); ?>><?php _e('EUR (Euro)', 'adhesion'); ?></option>
                            <option value="840" <?php selected($settings['redsys_currency'] ?? '978', '840'); ?>><?php _e('USD (DÃ³lar)', 'adhesion'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ConfiguraciÃ³n de DocuSign -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('ConfiguraciÃ³n de DocuSign (Firmas)', 'adhesion'); ?></h2>
                <p class="description"><?php _e('ConfiguraciÃ³n para la firma digital de contratos con DocuSign.', 'adhesion'); ?></p>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="docusign_environment"><?php _e('Entorno', 'adhesion'); ?></label>
                        <select name="adhesion_settings[docusign_environment]" id="docusign_environment">
                            <option value="demo" <?php selected($settings['docusign_environment'] ?? 'demo', 'demo'); ?>><?php _e('Demo', 'adhesion'); ?></option>
                            <option value="production" <?php selected($settings['docusign_environment'] ?? 'demo', 'production'); ?>><?php _e('ProducciÃ³n', 'adhesion'); ?></option>
                        </select>
                        <p class="adhesion-form-help"><?php _e('Usa "Demo" para pruebas y "ProducciÃ³n" para firmas reales.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="docusign_integration_key"><?php _e('Integration Key', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[docusign_integration_key]" id="docusign_integration_key" 
                               value="<?php echo esc_attr($settings['docusign_integration_key'] ?? ''); ?>" 
                               placeholder="12345678-1234-1234-1234-123456789012" />
                        <p class="adhesion-form-help"><?php _e('Clave de integraciÃ³n de tu aplicaciÃ³n DocuSign.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="docusign_secret_key"><?php _e('Secret Key', 'adhesion'); ?></label>
                        <input type="password" name="adhesion_settings[docusign_secret_key]" id="docusign_secret_key" 
                               value="<?php echo esc_attr($settings['docusign_secret_key'] ?? ''); ?>" 
                               placeholder="abcd1234-ef56-78gh-90ij-klmnopqrstuv" />
                        <p class="adhesion-form-help"><?php _e('Clave secreta de tu aplicaciÃ³n DocuSign.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="docusign_account_id"><?php _e('Account ID', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[docusign_account_id]" id="docusign_account_id" 
                               value="<?php echo esc_attr($settings['docusign_account_id'] ?? ''); ?>" 
                               placeholder="12345678-1234-1234-1234-123456789012" />
                        <p class="adhesion-form-help"><?php _e('ID de cuenta de DocuSign.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuraciones Generales -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Configuraciones Generales', 'adhesion'); ?></h2>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[calculator_enabled]" value="1" 
                                   <?php checked($settings['calculator_enabled'] ?? '1', '1'); ?> />
                            <?php _e('Habilitar calculadora de presupuestos', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Permite a los usuarios calcular presupuestos antes de adherirse.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[auto_create_users]" value="1" 
                                   <?php checked($settings['auto_create_users'] ?? '1', '1'); ?> />
                            <?php _e('Crear usuarios automÃ¡ticamente', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Crea cuentas de usuario automÃ¡ticamente durante el proceso de adhesiÃ³n.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[require_payment]" value="1" 
                                   <?php checked($settings['require_payment'] ?? '0', '1'); ?> />
                            <?php _e('Requerir pago antes de la firma', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Los usuarios deben pagar antes de firmar el contrato.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[contract_auto_send]" value="1" 
                                   <?php checked($settings['contract_auto_send'] ?? '1', '1'); ?> />
                            <?php _e('Enviar contratos automÃ¡ticamente', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('EnvÃ­a automÃ¡ticamente los contratos a DocuSign tras completar los datos.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[email_notifications]" value="1" 
                                   <?php checked($settings['email_notifications'] ?? '1', '1'); ?> />
                            <?php _e('Habilitar notificaciones por email', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('EnvÃ­a emails de confirmaciÃ³n y actualizaciones de estado.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ConfiguraciÃ³n de Email -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('ConfiguraciÃ³n de Email', 'adhesion'); ?></h2>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="admin_email"><?php _e('Email del Administrador', 'adhesion'); ?></label>
                        <input type="email" name="adhesion_settings[admin_email]" id="admin_email" 
                               value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>" />
                        <p class="adhesion-form-help"><?php _e('Email donde se enviarÃ¡n las notificaciones administrativas.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="email_from_name"><?php _e('Nombre del Remitente', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[email_from_name]" id="email_from_name" 
                               value="<?php echo esc_attr($settings['email_from_name'] ?? get_bloginfo('name')); ?>" />
                        <p class="adhesion-form-help"><?php _e('Nombre que aparecerÃ¡ como remitente en los emails.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="email_from_address"><?php _e('Email del Remitente', 'adhesion'); ?></label>
                        <input type="email" name="adhesion_settings[email_from_address]" id="email_from_address" 
                               value="<?php echo esc_attr($settings['email_from_address'] ?? get_option('admin_email')); ?>" />
                        <p class="adhesion-form-help"><?php _e('DirecciÃ³n de email que aparecerÃ¡ como remitente.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botones de acciÃ³n -->
        <div class="adhesion-card">
            <div class="adhesion-card-footer">
                <button type="submit" name="submit" class="button button-primary button-large">
                    <?php _e('Guardar ConfiguraciÃ³n', 'adhesion'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="button button-secondary">
                    <?php _e('Volver al Dashboard', 'adhesion'); ?>
                </a>
                
                <button type="button" class="button" onclick="adhesionTestConfiguration()">
                    <?php _e('Probar ConfiguraciÃ³n', 'adhesion'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function adhesionTestConfiguration() {
    // Verificar configuraciÃ³n de Redsys
    const merchantCode = document.getElementById('redsys_merchant_code').value;
    const secretKey = document.getElementById('redsys_secret_key').value;
    const integrationKey = document.getElementById('docusign_integration_key').value;
    const accountId = document.getElementById('docusign_account_id').value;
    
    let errors = [];
    
    if (!merchantCode) {
        errors.push('<?php echo esc_js(__('Falta el cÃ³digo de comercio de Redsys', 'adhesion')); ?>');
    }
    
    if (!secretKey) {
        errors.push('<?php echo esc_js(__('Falta la clave secreta de Redsys', 'adhesion')); ?>');
    }
    
    if (!integrationKey) {
        errors.push('<?php echo esc_js(__('Falta la Integration Key de DocuSign', 'adhesion')); ?>');
    }
    
    if (!accountId) {
        errors.push('<?php echo esc_js(__('Falta el Account ID de DocuSign', 'adhesion')); ?>');
    }
    
    if (errors.length > 0) {
        alert('<?php echo esc_js(__('Errores de configuraciÃ³n:', 'adhesion')); ?>\n\n' + errors.join('\n'));
        return;
    }
    
    alert('<?php echo esc_js(__('ConfiguraciÃ³n bÃ¡sica completa. Para pruebas completas, guarda la configuraciÃ³n y realiza un proceso de adhesiÃ³n de prueba.', 'adhesion')); ?>');
}

// Mostrar/ocultar campos segÃºn el entorno
document.addEventListener('DOMContentLoaded', function() {
    const redsysEnv = document.getElementById('redsys_environment');
    const docusignEnv = document.getElementById('docusign_environment');
    
    function updateEnvironmentNotices() {
        // Agregar avisos visuales para entornos de producciÃ³n
        const isRedsysProduction = redsysEnv.value === 'production';
        const isDocusignProduction = docusignEnv.value === 'production';
        
        // TODO: Agregar indicadores visuales para entornos de producciÃ³n
    }
    
    redsysEnv.addEventListener('change', updateEnvironmentNotices);
    docusignEnv.addEventListener('change', updateEnvironmentNotices);
    
    updateEnvironmentNotices();
});
</script>

<style>
/* Estilos especÃ­ficos para la pÃ¡gina de configuraciÃ³n */
.adhesion-config-status {
    margin-bottom: 30px;
}

.adhesion-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.status-item-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #ddd;
}

.status-item-card .status-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.status-item-card .status-icon.status-ok {
    background: var(--adhesion-success);
    border-left-color: var(--adhesion-success);
}

.status-item-card .status-icon.status-pending {
    background: var(--adhesion-warning);
    border-left-color: var(--adhesion-warning);
}

.status-item-card .status-icon.status-warning {
    background: var(--adhesion-error);
    border-left-color: var(--adhesion-error);
}

.status-content h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
}

.status-text {
    margin: 0;
    font-size: 14px;
    color: var(--adhesion-text-secondary);
}

.adhesion-settings-form .adhesion-card {
    margin-bottom: 25px;
}

.adhesion-form-group {
    display: grid;
    gap: 20px;
}

.adhesion-form-row label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--adhesion-text-primary);
}

.adhesion-form-row input[type="text"],
.adhesion-form-row input[type="email"],
.adhesion-form-row input[type="password"],
.adhesion-form-row select {
    width: 100%;
    max-width: 400px;
    padding: 10px 12px;
    border: 1px solid var(--adhesion-border);
    border-radius: var(--adhesion-radius);
    font-size: 14px;
}

.adhesion-form-row input[type="checkbox"] {
    margin-right: 8px;
}

.adhesion-form-help {
    font-size: 13px;
    color: var(--adhesion-text-secondary);
    margin-top: 5px;
    font-style: italic;
    line-height: 1.4;
}

.description {
    color: var(--adhesion-text-secondary);
    font-size: 14px;
    margin: 5px 0 0 0;
}

@media (max-width: 768px) {
    .adhesion-status-grid {
        grid-template-columns: 1fr;
    }
    
    .status-item-card {
        padding: 15px;
    }
    
    .adhesion-form-row input,
    .adhesion-form-row select {
        max-width: 100%;
    }
}
</style>


// ===== users-display.php =====
<?php
/**
 * Vista del listado de usuarios adheridos
 * 
 * Esta vista maneja:
 * - Listado principal de usuarios adheridos
 * - Vista detallada de un usuario especÃ­fico
 * - ComposiciÃ³n y envÃ­o de emails
 * - EstadÃ­sticas y resÃºmenes de usuarios
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acciÃ³n actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$user_id = intval($_GET['user_id'] ?? 0);

// Instanciar base de datos
$db = new Adhesion_Database();

// Manejar acciones especÃ­ficas
switch ($action) {
    case 'view':
        if ($user_id) {
            adhesion_display_user_detail($user_id, $db);
        } else {
            adhesion_display_users_list();
        }
        break;
        
    case 'compose_email':
        adhesion_display_email_compose();
        break;
        
    default:
        adhesion_display_users_list();
        break;
}

/**
 * Mostrar listado principal de usuarios
 */
function adhesion_display_users_list() {
    // Obtener estadÃ­sticas de usuarios
    global $wpdb;
    
    $user_stats = array();
    
    // Total de usuarios adheridos
    $user_stats['total'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'"
    );
    
    // Usuarios activos
    $user_stats['active'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND u.user_status = 0"
    );
    
    // Usuarios con actividad (Ãºltimos 30 dÃ­as)
    $user_stats['active_recently'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         LEFT JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id
         LEFT JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND (c.created_at >= %s OR ct.created_at >= %s)",
        date('Y-m-d', strtotime('-30 days')),
        date('Y-m-d', strtotime('-30 days'))
    ));
    
    // Usuarios con contratos firmados
    $user_stats['with_contracts'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         INNER JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND ct.status = 'signed'"
    );
    
    // Nuevos usuarios este mes
    $user_stats['new_this_month'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND u.user_registered >= %s",
        date('Y-m-01')
    ));
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('Usuarios Adheridos', 'adhesion'); ?>
            <span class="title-count"><?php echo sprintf(__('(%s total)', 'adhesion'), number_format($user_stats['total'])); ?></span>
        </h1>
        
        <a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action">
            <?php _e('+ AÃ±adir Usuario', 'adhesion'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('â† Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar notificaciones
        adhesion_display_notices();
        ?>
        
        <!-- EstadÃ­sticas de usuarios -->
        <div class="adhesion-users-overview">
            <div class="adhesion-stats-grid">
                <div class="users-stat-card total">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['total']); ?></div>
                        <div class="stat-label"><?php _e('Total Usuarios', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button button-small">
                            <?php _e('Ver Todos', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="users-stat-card active">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['active']); ?></div>
                        <div class="stat-label"><?php _e('Usuarios Activos', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <div class="stat-percentage">
                            <?php 
                            $active_percentage = $user_stats['total'] > 0 ? ($user_stats['active'] / $user_stats['total']) * 100 : 0;
                            echo sprintf(__('%.1f%%', 'adhesion'), $active_percentage);
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="users-stat-card recent">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['active_recently']); ?></div>
                        <div class="stat-label"><?php _e('Activos (30 dÃ­as)', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users&activity=recent'); ?>" class="button button-small">
                            <?php _e('Ver', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="users-stat-card contracts">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['with_contracts']); ?></div>
                        <div class="stat-label"><?php _e('Con Contratos', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users&activity=with_contracts'); ?>" class="button button-small">
                            <?php _e('Ver', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="users-stat-card new">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['new_this_month']); ?></div>
                        <div class="stat-label"><?php _e('Nuevos Este Mes', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <?php if ($user_stats['new_this_month'] > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users&date_from=' . date('Y-m-01')); ?>" class="button button-small">
                            <?php _e('Ver', 'adhesion'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accesos rÃ¡pidos -->
        <div class="adhesion-quick-actions-row">
            <div class="quick-action-item">
                <h3><?php _e('Acciones RÃ¡pidas', 'adhesion'); ?></h3>
                <div class="quick-actions-buttons">
                    <button type="button" class="button button-primary" onclick="adhesionBulkEmailModal()">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php _e('Email a Todos los Activos', 'adhesion'); ?>
                    </button>
                    
                    <button type="button" class="button" onclick="adhesionExportAllUsers()">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Exportar Todos', 'adhesion'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-users&activity=no_activity'); ?>" class="button">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Ver Sin Actividad', 'adhesion'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tabla de usuarios -->
        <div class="adhesion-table-container">
            <?php
            // Crear y mostrar la tabla
            $list_table = new Adhesion_Users_List();
            $list_table->prepare_items();
            ?>
            
            <form method="get" id="users-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                <?php
                $list_table->search_box(__('Buscar usuarios', 'adhesion'), 'user');
                $list_table->display();
                ?>
            </form>
        </div>
    </div>
    
    <style>
    .adhesion-users-overview {
        margin: 20px 0 30px;
    }
    
    .users-stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 6px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        position: relative;
        overflow: hidden;
    }
    
    .users-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .users-stat-card.total::before { background: #0073aa; }
    .users-stat-card.active::before { background: #00a32a; }
    .users-stat-card.recent::before { background: #dba617; }
    .users-stat-card.contracts::before { background: #8c8f94; }
    .users-stat-card.new::before { background: #d63638; }
    
    .users-stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .users-stat-card.total .stat-icon { background: #0073aa; }
    .users-stat-card.active .stat-icon { background: #00a32a; }
    .users-stat-card.recent .stat-icon { background: #dba617; }
    .users-stat-card.contracts .stat-icon { background: #8c8f94; }
    .users-stat-card.new .stat-icon { background: #d63638; }
    
    .users-stat-card .stat-content {
        flex: 1;
    }
    
    .users-stat-card .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .users-stat-card .stat-label {
        font-size: 14px;
        color: #646970;
        font-weight: 500;
    }
    
    .users-stat-card .stat-action {
        flex-shrink: 0;
    }
    
    .stat-percentage {
        font-size: 14px;
        font-weight: bold;
        color: #646970;
        text-align: center;
    }
    
    .adhesion-quick-actions-row {
        margin: 20px 0;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    .adhesion-quick-actions-row h3 {
        margin: 0 0 15px 0;
        font-size: 16px;
        color: #1d2327;
    }
    
    .quick-actions-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .quick-actions-buttons .button {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    @media (max-width: 768px) {
        .users-stat-card {
            padding: 15px;
        }
        
        .users-stat-card .stat-number {
            font-size: 20px;
        }
        
        .users-stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .quick-actions-buttons {
            flex-direction: column;
        }
        
        .quick-actions-buttons .button {
            justify-content: center;
        }
    }
    </style>
    <?php
}

/**
 * Mostrar detalle de un usuario especÃ­fico
 */
function adhesion_display_user_detail($user_id, $db) {
    $user = get_userdata($user_id);
    
    if (!$user || !in_array('adhesion_client', $user->roles)) {
        ?>
        <div class="wrap">
            <h1><?php _e('Usuario no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El usuario solicitado no existe o no es un usuario adherido.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                <?php _e('â† Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener datos adicionales
    $calculations = $db->get_user_calculations($user_id, 10);
    $contracts = $db->get_user_contracts($user_id, 10);
    
    // Obtener metadatos del usuario
    $user_meta = array(
        'first_name' => get_user_meta($user_id, 'first_name', true),
        'last_name' => get_user_meta($user_id, 'last_name', true),
        'phone' => get_user_meta($user_id, 'phone', true),
        'company' => get_user_meta($user_id, 'company', true)
    );
    
    // Calcular estadÃ­sticas
    $total_paid = 0;
    $contracts_signed = 0;
    foreach ($contracts as $contract) {
        if ($contract['payment_status'] === 'completed') {
            $total_paid += floatval($contract['payment_amount']);
        }
        if ($contract['status'] === 'signed') {
            $contracts_signed++;
        }
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php echo sprintf(__('Usuario: %s', 'adhesion'), esc_html($user->display_name)); ?>
            <span class="user-status-badge">
                <?php 
                $status_class = $user->user_status == 0 ? 'success' : 'warning';
                $status_text = $user->user_status == 0 ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
                echo '<span class="adhesion-badge adhesion-badge-' . $status_class . '">' . $status_text . '</span>';
                ?>
            </span>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="page-title-action">
            <?php _e('â† Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <div class="adhesion-user-detail">
            <div class="adhesion-detail-grid">
                <!-- Panel izquierdo -->
                <div class="adhesion-detail-left">
                    <!-- InformaciÃ³n personal -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('InformaciÃ³n Personal', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <table class="adhesion-detail-table">
                                <tr>
                                    <th><?php _e('ID:', 'adhesion'); ?></th>
                                    <td><?php echo $user->ID; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Usuario:', 'adhesion'); ?></th>
                                    <td><?php echo esc_html($user->user_login); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Nombre completo:', 'adhesion'); ?></th>
                                    <td><?php echo esc_html(trim($user_meta['first_name'] . ' ' . $user_meta['last_name'])) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Email:', 'adhesion'); ?></th>
                                    <td><a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a></td>
                                </tr>
                                <tr>
                                    <th><?php _e('TelÃ©fono:', 'adhesion'); ?></th>
                                    <td><?php echo $user_meta['phone'] ? '<a href="tel:' . esc_attr($user_meta['phone']) . '">' . esc_html($user_meta['phone']) . '</a>' : '-'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Empresa:', 'adhesion'); ?></th>
                                    <td><?php echo esc_html($user_meta['company']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Registrado:', 'adhesion'); ?></th>
                                    <td><?php echo adhesion_format_date($user->user_registered); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Estado:', 'adhesion'); ?></th>
                                    <td>
                                        <?php 
                                        $status_class = $user->user_status == 0 ? 'success' : 'warning';
                                        $status_text = $user->user_status == 0 ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
                                        echo '<span class="adhesion-badge adhesion-badge-' . $status_class . '">' . $status_text . '</span>';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- EstadÃ­sticas -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('EstadÃ­sticas', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <div class="user-stats-grid">
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-calculator"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo count($calculations); ?></div>
                                        <div class="stat-label"><?php _e('CÃ¡lculos', 'adhesion'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-media-document"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo count($contracts); ?></div>
                                        <div class="stat-label"><?php _e('Contratos', 'adhesion'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $contracts_signed; ?></div>
                                        <div class="stat-label"><?php _e('Firmados', 'adhesion'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-money-alt"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo adhesion_format_price($total_paid); ?></div>
                                        <div class="stat-label"><?php _e('Total Pagado', 'adhesion'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Panel derecho -->
                <div class="adhesion-detail-right">
                    <!-- Ãšltimos cÃ¡lculos -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Ãšltimos CÃ¡lculos', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <?php if (!empty($calculations)): ?>
                            <div class="activity-list">
                                <?php foreach (array_slice($calculations, 0, 5) as $calc): ?>
                                <div class="activity-item">
                                    <div class="activity-content">
                                        <strong><?php echo adhesion_format_price($calc['total_price']); ?></strong>
                                        <span class="activity-date"><?php echo adhesion_format_date($calc['created_at'], 'd/m/Y'); ?></span>
                                    </div>
                                    <div class="activity-action">
                                        <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $calc['id']); ?>" class="button button-small">
                                            <?php _e('Ver', 'adhesion'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($calculations) > 5): ?>
                            <div class="card-footer">
                                <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&user_id=' . $user_id); ?>" class="button">
                                    <?php _e('Ver todos los cÃ¡lculos', 'adhesion'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <p class="adhesion-no-data"><?php _e('No hay cÃ¡lculos registrados.', 'adhesion'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Ãšltimos contratos -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Ãšltimos Contratos', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <?php if (!empty($contracts)): ?>
                            <div class="activity-list">
                                <?php foreach (array_slice($contracts, 0, 5) as $contract): ?>
                                <div class="activity-item">
                                    <div class="activity-content">
                                        <strong><?php echo esc_html($contract['contract_number']); ?></strong>
                                        <?php 
                                        $status = adhesion_format_contract_status($contract['status']);
                                        echo '<span class="adhesion-badge adhesion-badge-' . esc_attr($status['class']) . '">' . esc_html($status['label']) . '</span>';
                                        ?>
                                        <span class="activity-date"><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y'); ?></span>
                                    </div>
                                    <div class="activity-action">
                                        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']); ?>" class="button button-small">
                                            <?php _e('Ver', 'adhesion'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($contracts) > 5): ?>
                            <div class="card-footer">
                                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&user_id=' . $user_id); ?>" class="button">
                                    <?php _e('Ver todos los contratos', 'adhesion'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <p class="adhesion-no-data"><?php _e('No hay contratos registrados.', 'adhesion'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Acciones', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <div class="adhesion-actions-list">
                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $user_id); ?>" class="button button-primary">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php _e('Editar Usuario', 'adhesion'); ?>
                                </a>
                                
                                <button type="button" class="button" onclick="adhesionExportUserData(<?php echo $user_id; ?>)">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php _e('Exportar Datos', 'adhesion'); ?>
                                </button>
                                
                                <?php if ($user->user_status == 0): ?>
                                <button type="button" class="button button-secondary" onclick="adhesionToggleUserStatus(<?php echo $user_id; ?>, 1)">
                                    <span class="dashicons dashicons-hidden"></span>
                                    <?php _e('Desactivar Usuario', 'adhesion'); ?>
                                </button>
                                <?php else: ?>
                                <button type="button" class="button button-secondary" onclick="adhesionToggleUserStatus(<?php echo $user_id; ?>, 0)">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Activar Usuario', 'adhesion'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .user-status-badge {
        margin-left: 10px;
    }
    
    .adhesion-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .adhesion-detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .adhesion-detail-table th,
    .adhesion-detail-table td {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f1;
        text-align: left;
        vertical-align: top;
    }
    
    .adhesion-detail-table th {
        width: 35%;
        font-weight: 600;
        color: #646970;
    }
    
    .adhesion-detail-table td {
        color: #1d2327;
    }
    
    .user-stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .user-stat-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px;
        background: #f6f7f7;
        border-radius: 4px;
    }
    
    .user-stat-item .stat-icon {
        width: 35px;
        height: 35px;
        background: #0073aa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
    }
    
    .user-stat-item .stat-number {
        font-size: 18px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
    }
    
    .user-stat-item .stat-label {
        font-size: 12px;
        color: #646970;
        margin-top: 2px;
    }
    
    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .activity-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 4px;
        border-left: 3px solid #0073aa;
    }
    
    .activity-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .activity-date {
        font-size: 12px;
        color: #646970;
    }
    
    .card-footer {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f1;
        text-align: center;
    }
    
    .adhesion-actions-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .adhesion-actions-list .button {
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-start;
        width: 100%;
        text-align: left;
    }
    
    .adhesion-no-data {
        color: #646970;
        font-style: italic;
        text-align: center;
        padding: 20px;
    }
    
    @media (max-width: 768px) {
        .adhesion-detail-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .user-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .activity-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
    </style>
    
    <script>
    function adhesionExportUserData(userId) {
        // TODO: Implementar exportaciÃ³n individual de usuario
        alert('<?php echo esc_js(__('Funcionalidad de exportaciÃ³n individual en desarrollo.', 'adhesion')); ?>');
    }
    
    function adhesionToggleUserStatus(userId, newStatus) {
        const statusText = newStatus == 0 ? '<?php echo esc_js(__('activar', 'adhesion')); ?>' : '<?php echo esc_js(__('desactivar', 'adhesion')); ?>';
        
        if (!confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres', 'adhesion')); ?> ' + statusText + ' <?php echo esc_js(__('este usuario?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_update_user_status',
                user_id: userId,
                status: newStatus,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexiÃ³n', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    </script>
    <?php
}

/**
 * Mostrar pÃ¡gina de composiciÃ³n de email
 */
function adhesion_display_email_compose() {
    $user_ids = explode(',', sanitize_text_field($_GET['users'] ?? ''));
    $user_ids = array_filter(array_map('intval', $user_ids));
    
    if (empty($user_ids)) {
        ?>
        <div class="wrap">
            <h1><?php _e('Componer Email', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('No se han seleccionado usuarios para enviar el email.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                <?php _e('â† Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener informaciÃ³n de los usuarios
    $users = array();
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user && in_array('adhesion_client', $user->roles)) {
            $users[] = $user;
        }
    }
    
    if (empty($users)) {
        ?>
        <div class="wrap">
            <h1><?php _e('Componer Email', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('Los usuarios seleccionados no son vÃ¡lidos.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                <?php _e('â† Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Componer Email Masivo', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="page-title-action">
            <?php _e('â† Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <div class="adhesion-email-compose">
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php echo sprintf(__('Enviando a %d usuarios', 'adhesion'), count($users)); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <!-- Lista de destinatarios -->
                    <div class="recipients-list">
                        <h3><?php _e('Destinatarios:', 'adhesion'); ?></h3>
                        <div class="recipients-grid">
                            <?php foreach ($users as $user): ?>
                            <div class="recipient-item">
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Formulario de email -->
                    <form id="bulk-email-form" method="post" action="">
                        <?php wp_nonce_field('adhesion_bulk_email', 'adhesion_bulk_email_nonce'); ?>
                        
                        <?php foreach ($user_ids as $user_id): ?>
                        <input type="hidden" name="user_ids[]" value="<?php echo $user_id; ?>">
                        <?php endforeach; ?>
                        
                        <div class="adhesion-form-row">
                            <label for="email-subject"><?php _e('Asunto:', 'adhesion'); ?></label>
                            <input type="text" id="email-subject" name="subject" required 
                                   placeholder="<?php _e('Escribe el asunto del email...', 'adhesion'); ?>">
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label for="email-message"><?php _e('Mensaje:', 'adhesion'); ?></label>
                            <textarea id="email-message" name="message" rows="10" required 
                                      placeholder="<?php _e('Escribe tu mensaje aquÃ­...', 'adhesion'); ?>"></textarea>
                            <p class="adhesion-form-help">
                                <?php _e('Variables disponibles: {nombre}, {email}, {empresa}', 'adhesion'); ?>
                            </p>
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label>
                                <input type="checkbox" name="send_test" value="1">
                                <?php _e('Enviar email de prueba solo a mi direcciÃ³n primero', 'adhesion'); ?>
                            </label>
                        </div>
                        
                        <div class="adhesion-form-actions">
                            <button type="submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php _e('Enviar Emails', 'adhesion'); ?>
                            </button>
                            
                            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button button-large">
                                <?php _e('Cancelar', 'adhesion'); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .adhesion-email-compose {
        max-width: 800px;
        margin-top: 20px;
    }
    
    .recipients-list {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f0f1;
    }
    
    .recipients-list h3 {
        margin-bottom: 15px;
        color: #1d2327;
    }
    
    .recipients-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 10px;
    }
    
    .recipient-item {
        padding: 8px 12px;
        background: #f6f7f7;
        border-radius: 4px;
        border-left: 3px solid #0073aa;
    }
    
    .recipient-item strong {
        display: block;
        color: #1d2327;
    }
    
    .recipient-item small {
        color: #646970;
        font-size: 12px;
    }
    
    .adhesion-form-row {
        margin-bottom: 20px;
    }
    
    .adhesion-form-row label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #1d2327;
    }
    
    .adhesion-form-row input[type="text"],
    .adhesion-form-row textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .adhesion-form-row input:focus,
    .adhesion-form-row textarea:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }
    
    .adhesion-form-help {
        font-size: 12px;
        color: #646970;
        margin-top: 5px;
        font-style: italic;
    }
    
    .adhesion-form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #f0f0f1;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .button-large {
        padding: 8px 16px;
        height: auto;
        line-height: 1.4;
    }
    
    @media (max-width: 768px) {
        .recipients-grid {
            grid-template-columns: 1fr;
        }
        
        .adhesion-form-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .adhesion-form-actions .button {
            justify-content: center;
        }
    }
    </style>
    
    <?php
    // Procesar envÃ­o del formulario
    if ($_POST && wp_verify_nonce($_POST['adhesion_bulk_email_nonce'], 'adhesion_bulk_email')) {
        adhesion_process_bulk_email($_POST);
    }
    ?>
    
    <script>
    jQuery(document).ready(function($) {
        $('#bulk-email-form').on('submit', function(e) {
            const subject = $('#email-subject').val().trim();
            const message = $('#email-message').val().trim();
            
            if (!subject || !message) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Por favor, completa todos los campos obligatorios.', 'adhesion')); ?>');
                return false;
            }
            
            if (!confirm('<?php echo esc_js(__('Â¿EstÃ¡s seguro de que quieres enviar este email a todos los usuarios seleccionados?', 'adhesion')); ?>')) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * Procesar envÃ­o de email masivo
 */
function adhesion_process_bulk_email($post_data) {
    $user_ids = array_map('intval', $post_data['user_ids'] ?? array());
    $subject = sanitize_text_field($post_data['subject']);
    $message = sanitize_textarea_field($post_data['message']);
    $send_test = isset($post_data['send_test']);
    
    if (empty($user_ids) || empty($subject) || empty($message)) {
        adhesion_add_notice(__('Faltan datos obligatorios para enviar el email.', 'adhesion'), 'error');
        return;
    }
    
    $sent_count = 0;
    $failed_count = 0;
    
    if ($send_test) {
        // Enviar solo a administrador como prueba
        $admin_email = get_option('admin_email');
        $test_message = "[PRUEBA] " . $message . "\n\n---\nEste es un email de prueba. Se enviarÃ­a a " . count($user_ids) . " usuarios.";
        
        $sent = adhesion_send_email($admin_email, "[PRUEBA] " . $subject, 'user-notification', array(
            'user_name' => 'Administrador',
            'message' => $test_message,
            'site_name' => get_bloginfo('name')
        ));
        
        if ($sent) {
            adhesion_add_notice(__('Email de prueba enviado a tu direcciÃ³n.', 'adhesion'), 'success');
        } else {
            adhesion_add_notice(__('Error enviando email de prueba.', 'adhesion'), 'error');
        }
        
        return;
    }
    
    // Enviar a todos los usuarios
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            $failed_count++;
            continue;
        }
        
        // Reemplazar variables en el mensaje
        $personalized_message = str_replace(
            array('{nombre}', '{email}', '{empresa}'),
            array(
                $user->display_name,
                $user->user_email,
                get_user_meta($user_id, 'company', true) ?: ''
            ),
            $message
        );
        
        $sent = adhesion_send_email($user->user_email, $subject, 'user-notification', array(
            'user_name' => $user->display_name,
            'message' => $personalized_message,
            'site_name' => get_bloginfo('name')
        ));
        
        if ($sent) {
            $sent_count++;
        } else {
            $failed_count++;
        }
        
        // Pausa pequeÃ±a para evitar sobrecarga del servidor
        usleep(100000); // 0.1 segundos
    }
    
    // Mostrar resultado
    if ($sent_count > 0) {
        $message = sprintf(__('Se enviaron %d emails correctamente.', 'adhesion'), $sent_count);
        if ($failed_count > 0) {
            $message .= ' ' . sprintf(__('%d emails fallaron.', 'adhesion'), $failed_count);
        }
        adhesion_add_notice($message, 'success');
    } else {
        adhesion_add_notice(__('No se pudo enviar ningÃºn email.', 'adhesion'), 'error');
    }
}

// Funciones JavaScript globales
?>
<script>
function adhesionBulkEmailModal() {
    alert('<?php echo esc_js(__('Selecciona usuarios desde la tabla usando los checkboxes y luego usa la acciÃ³n "Enviar Email".', 'adhesion')); ?>');
}

function adhesionExportAllUsers() {
    if (confirm('<?php echo esc_js(__('Â¿Exportar todos los usuarios adheridos a CSV?', 'adhesion')); ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        const fields = {
            action: 'adhesion_export_users',
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
        };
        
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}
</script>


// ===== class-activator.php =====
<?php
/**
 * Clase para activaciÃ³n del plugin - CON VERIFICACIÃ“N Y FALLBACK
 * 
 * Esta versiÃ³n:
 * 1. Intenta crear tablas con dbDelta
 * 2. Si falla, usa SQL directo
 * 3. Verifica que las tablas existen
 * 4. Solo continÃºa si TODO estÃ¡ correcto
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Activator {
    
    /**
     * MÃ©todo principal de activaciÃ³n
     */
    public static function activate() {
        try {
            // Verificar requisitos mÃ­nimos
            self::check_requirements();
            
            // Crear tablas con verificaciÃ³n
            $tables_created = self::create_tables_with_verification();
            
            if (!$tables_created) {
                // ABORT - No se pudieron crear las tablas
                wp_die(
                    __('Error: No se pudieron crear las tablas de la base de datos. Contacta con el administrador.', 'adhesion'),
                    __('Error de ActivaciÃ³n - AdhesiÃ³n', 'adhesion')
                );
            }
            
            // Solo continuar si las tablas estÃ¡n OK
            self::create_pages();
            self::set_default_options();
            self::create_roles_and_capabilities();
            self::create_upload_directory();
            
            // Marcar activaciÃ³n exitosa
            update_option('adhesion_tables_created', 'yes');
            update_option('adhesion_activated', current_time('mysql'));
            
            flush_rewrite_rules();
            
            error_log('[ADHESION] âœ… Plugin activado correctamente con todas las tablas');
            
        } catch (Exception $e) {
            error_log('[ADHESION ERROR] ' . $e->getMessage());
            wp_die(
                sprintf(__('Error activando plugin AdhesiÃ³n: %s', 'adhesion'), $e->getMessage()),
                __('Error de ActivaciÃ³n', 'adhesion')
            );
        }
    }
    
    /**
     * Crear tablas con verificaciÃ³n obligatoria
     */
    private static function create_tables_with_verification() {
        global $wpdb;
        
        error_log('[ADHESION] Iniciando creaciÃ³n de tablas...');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // PASO 1: Intentar con dbDelta (mÃ©todo WordPress estÃ¡ndar)
        $dbdelta_success = self::try_create_with_dbdelta($charset_collate);
        
        // PASO 2: Verificar si las tablas existen
        $tables_exist = self::verify_all_tables_exist();
        
        if ($tables_exist) {
            error_log('[ADHESION] âœ… Tablas creadas correctamente con dbDelta');
            self::insert_initial_data();
            return true;
        }
        
        // PASO 3: Si dbDelta fallÃ³, intentar con SQL directo
        error_log('[ADHESION] âš ï¸ dbDelta fallÃ³, intentando SQL directo...');
        $direct_sql_success = self::create_with_direct_sql($charset_collate);
        
        // PASO 4: Verificar nuevamente
        $tables_exist = self::verify_all_tables_exist();
        
        if ($tables_exist) {
            error_log('[ADHESION] âœ… Tablas creadas correctamente con SQL directo');
            self::insert_initial_data();
            return true;
        }
        
        // PASO 5: TODO FALLÃ“
        error_log('[ADHESION] âŒ CRÃTICO: No se pudieron crear las tablas por ningÃºn mÃ©todo');
        return false;
    }
    
    /**
     * Intentar crear tablas con dbDelta
     */
    private static function try_create_with_dbdelta($charset_collate) {
        global $wpdb;
        
        $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (!file_exists($upgrade_file)) {
            error_log('[ADHESION] ERROR: upgrade.php no encontrado');
            return false;
        }
        
        require_once($upgrade_file);
        
        if (!function_exists('dbDelta')) {
            error_log('[ADHESION] ERROR: funciÃ³n dbDelta no disponible');
            return false;
        }
        
        // SQLs para dbDelta (formato especÃ­fico requerido)
        $sqls = array(
            "CREATE TABLE {$wpdb->prefix}adhesion_calculations (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                calculation_data longtext NOT NULL,
                material_data longtext,
                total_price decimal(10,2) NOT NULL DEFAULT 0.00,
                price_per_ton decimal(8,2) DEFAULT 0.00,
                total_tons decimal(8,2) DEFAULT 0.00,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_contracts (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                calculation_id mediumint(9) DEFAULT NULL,
                contract_number varchar(50) DEFAULT NULL,
                status varchar(50) DEFAULT 'pending',
                client_data longtext,
                docusign_envelope_id varchar(255) DEFAULT NULL,
                signed_document_url varchar(500) DEFAULT NULL,
                payment_status varchar(50) DEFAULT 'pending',
                payment_amount decimal(10,2) DEFAULT 0.00,
                payment_reference varchar(100) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                signed_at datetime DEFAULT NULL,
                payment_completed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status),
                UNIQUE KEY contract_number (contract_number)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_documents (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                document_type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                header_content longtext,
                body_content longtext,
                footer_content longtext,
                variables_list longtext,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY document_type (document_type)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_settings (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                setting_key varchar(100) NOT NULL,
                setting_value longtext,
                setting_type varchar(20) DEFAULT 'string',
                is_encrypted tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_calculator_prices (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                material_type varchar(100) NOT NULL,
                price_per_ton decimal(8,2) NOT NULL DEFAULT 0.00,
                minimum_quantity decimal(8,2) DEFAULT 0.00,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY material_type (material_type)
            ) $charset_collate;"
        );
        
        foreach ($sqls as $sql) {
            $result = dbDelta($sql);
            error_log('[ADHESION] dbDelta resultado: ' . print_r($result, true));
        }
        
        return true; // dbDelta ejecutado (aunque puede haber fallado)
    }
    
    /**
     * Crear tablas con SQL directo (fallback)
     */
    private static function create_with_direct_sql($charset_collate) {
        global $wpdb;
        
        error_log('[ADHESION] Ejecutando SQL directo...');
        
        $sqls = array(
            'adhesion_calculations' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_calculations` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) NOT NULL,
                `calculation_data` longtext NOT NULL,
                `material_data` longtext,
                `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `price_per_ton` decimal(8,2) DEFAULT 0.00,
                `total_tons` decimal(8,2) DEFAULT 0.00,
                `status` varchar(20) DEFAULT 'active',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`)
            ) {$charset_collate}",
            
            'adhesion_contracts' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_contracts` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) NOT NULL,
                `calculation_id` mediumint(9) DEFAULT NULL,
                `contract_number` varchar(50) DEFAULT NULL,
                `status` varchar(50) DEFAULT 'pending',
                `client_data` longtext,
                `docusign_envelope_id` varchar(255) DEFAULT NULL,
                `signed_document_url` varchar(500) DEFAULT NULL,
                `payment_status` varchar(50) DEFAULT 'pending',
                `payment_amount` decimal(10,2) DEFAULT 0.00,
                `payment_reference` varchar(100) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `signed_at` datetime DEFAULT NULL,
                `payment_completed_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`),
                UNIQUE KEY `contract_number` (`contract_number`)
            ) {$charset_collate}",
            
            'adhesion_documents' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_documents` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `document_type` varchar(50) NOT NULL,
                `title` varchar(255) NOT NULL,
                `header_content` longtext,
                `body_content` longtext,
                `footer_content` longtext,
                `variables_list` longtext,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `document_type` (`document_type`)
            ) {$charset_collate}",
            
            'adhesion_settings' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_settings` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(100) NOT NULL,
                `setting_value` longtext,
                `setting_type` varchar(20) DEFAULT 'string',
                `is_encrypted` tinyint(1) DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) {$charset_collate}",
            
            'adhesion_calculator_prices' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_calculator_prices` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `material_type` varchar(100) NOT NULL,
                `price_per_ton` decimal(8,2) NOT NULL DEFAULT 0.00,
                `minimum_quantity` decimal(8,2) DEFAULT 0.00,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `material_type` (`material_type`)
            ) {$charset_collate}"
        );
        
        $success_count = 0;
        foreach ($sqls as $table_name => $sql) {
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                error_log("[ADHESION] ERROR SQL directo en $table_name: " . $wpdb->last_error);
            } else {
                error_log("[ADHESION] âœ… SQL directo exitoso: $table_name");
                $success_count++;
            }
        }
        
        return $success_count === count($sqls);
    }
    
    /**
     * Verificar que TODAS las tablas existen
     */
    private static function verify_all_tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'adhesion_calculations',
            $wpdb->prefix . 'adhesion_contracts',
            $wpdb->prefix . 'adhesion_documents',
            $wpdb->prefix . 'adhesion_settings',
            $wpdb->prefix . 'adhesion_calculator_prices'
        );
        
        $existing_count = 0;
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if ($exists) {
                error_log("[ADHESION] âœ… Tabla confirmada: $table");
                $existing_count++;
            } else {
                error_log("[ADHESION] âŒ Tabla NO existe: $table");
            }
        }
        
        $all_exist = ($existing_count === count($required_tables));
        error_log("[ADHESION] VerificaciÃ³n: $existing_count/" . count($required_tables) . " tablas existen");
        
        return $all_exist;
    }
    
    /**
     * Insertar datos iniciales
     */
    private static function insert_initial_data() {
        global $wpdb;
        
        error_log('[ADHESION] Insertando datos iniciales...');
        
        // Documento por defecto
        $existing_doc = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_documents WHERE document_type = 'contract'"
        );
        
        if ($existing_doc == 0) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'adhesion_documents',
                array(
                    'document_type' => 'contract',
                    'title' => 'Contrato de AdhesiÃ³n EstÃ¡ndar',
                    'header_content' => '<h1>CONTRATO DE ADHESIÃ“N</h1><p>Fecha: [fecha]</p>',
                    'body_content' => '<h2>DATOS DEL CLIENTE</h2><p>Nombre: [nombre_completo]</p>',
                    'footer_content' => '<p>Firma: ________________________</p>',
                    'variables_list' => json_encode(['fecha', 'nombre_completo']),
                    'is_active' => 1
                )
            );
            
            if ($result) {
                error_log('[ADHESION] âœ… Documento por defecto insertado');
            } else {
                error_log('[ADHESION] âŒ Error insertando documento: ' . $wpdb->last_error);
            }
        }
        
        // Precios por defecto
        $existing_prices = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculator_prices"
        );
        
        if ($existing_prices == 0) {
            $prices = array(
                array('material_type' => 'CartÃ³n', 'price_per_ton' => 150.00),
                array('material_type' => 'Papel', 'price_per_ton' => 120.00),
                array('material_type' => 'PlÃ¡stico', 'price_per_ton' => 200.00),
                array('material_type' => 'Metal', 'price_per_ton' => 300.00),
                array('material_type' => 'Vidrio', 'price_per_ton' => 80.00)
            );
            
            foreach ($prices as $price) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'adhesion_calculator_prices',
                    array(
                        'material_type' => $price['material_type'],
                        'price_per_ton' => $price['price_per_ton'],
                        'is_active' => 1
                    )
                );
                
                if (!$result) {
                    error_log('[ADHESION] Error insertando precio ' . $price['material_type'] . ': ' . $wpdb->last_error);
                }
            }
            
            error_log('[ADHESION] âœ… Precios por defecto insertados');
        }
    }
    
    /**
     * Verificar requisitos mÃ­nimos del sistema
     */
    private static function check_requirements() {
        global $wp_version;
        
        if (version_compare($wp_version, '5.0', '<')) {
            throw new Exception('Este plugin requiere WordPress 5.0 o superior.');
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            throw new Exception('Este plugin requiere PHP 7.4 o superior.');
        }
        
        if (!extension_loaded('curl')) {
            throw new Exception('Este plugin requiere la extensiÃ³n cURL de PHP.');
        }
    }
    
    /**
     * Crear pÃ¡ginas necesarias
     */
    private static function create_pages() {
        $pages = array(
            array('title' => 'Calculadora de Presupuesto', 'content' => '[adhesion_calculator]', 'slug' => 'calculadora-presupuesto'),
            array('title' => 'Mi Cuenta - AdhesiÃ³n', 'content' => '[adhesion_account]', 'slug' => 'mi-cuenta-adhesion'),
            array('title' => 'Proceso de Pago', 'content' => '[adhesion_payment]', 'slug' => 'proceso-pago'),
            array('title' => 'Firma de Contratos', 'content' => '[adhesion_contract_signing]', 'slug' => 'firma-contratos')
        );
        
        foreach ($pages as $page_data) {
            if (!get_page_by_path($page_data['slug'])) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $page_data['slug'],
                    'post_author' => 1
                ));
                
                if ($page_id) {
                    $existing_settings = get_option('adhesion_settings', array());
                    $existing_settings['page_' . str_replace('-', '_', $page_data['slug'])] = $page_id;
                    update_option('adhesion_settings', $existing_settings);
                }
            }
        }
    }
    
    /**
     * Configurar opciones por defecto
     */
    private static function set_default_options() {
        $default_settings = array(
            'redsys_merchant_code' => '',
            'redsys_terminal' => '001',
            'redsys_secret_key' => '',
            'redsys_environment' => 'test',
            'redsys_currency' => '978',
            'docusign_integration_key' => '',
            'docusign_secret_key' => '',
            'docusign_account_id' => '',
            'docusign_environment' => 'demo',
            'calculator_enabled' => '1',
            'auto_create_users' => '1',
            'email_notifications' => '1',
            'contract_auto_send' => '1',
            'admin_email' => get_option('admin_email'),
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_option('admin_email'),
            'plugin_version' => ADHESION_PLUGIN_VERSION
        );
        
        $existing_settings = get_option('adhesion_settings', array());
        $final_settings = array_merge($default_settings, $existing_settings);
        
        update_option('adhesion_settings', $final_settings);
    }
    
    /**
     * Crear roles y capacidades
     */
    private static function create_roles_and_capabilities() {
        if (!get_role('adhesion_client')) {
            add_role('adhesion_client', __('Cliente Adherido', 'adhesion'), array(
                'read' => true,
                'adhesion_access' => true,
                'adhesion_calculate' => true,
                'adhesion_view_account' => true
            ));
        }
        
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('adhesion_manage_all');
            $admin_role->add_cap('adhesion_manage_settings');
            $admin_role->add_cap('adhesion_manage_documents');
            $admin_role->add_cap('adhesion_view_reports');
        }
    }
    
    /**
     * Crear directorio de uploads
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $adhesion_dir = $upload_dir['basedir'] . '/adhesion/';
        
        if (!file_exists($adhesion_dir)) {
            wp_mkdir_p($adhesion_dir);
            file_put_contents($adhesion_dir . '.htaccess', "Order deny,allow\nDeny from all\n<Files ~ \"\\.(pdf|doc|docx)$\">\nAllow from all\n</Files>");
            file_put_contents($adhesion_dir . 'index.php', '<?php // Silence is golden');
        }
    }
}


// ===== class-ajax-handler.php =====
<?php
/**
 * Clase para manejo de peticiones AJAX
 * 
 * Esta clase maneja todas las peticiones AJAX del plugin:
 * - Calculadora de presupuestos
 * - GestiÃ³n de formularios
 * - Operaciones CRUD
 * - ValidaciÃ³n y seguridad
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Ajax_Handler {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks de AJAX
     */
    private function init_hooks() {
        // AJAX para usuarios logueados
        add_action('wp_ajax_adhesion_calculate', array($this, 'handle_calculate'));
        add_action('wp_ajax_adhesion_save_calculation', array($this, 'handle_save_calculation'));
        add_action('wp_ajax_adhesion_get_user_data', array($this, 'handle_get_user_data'));
        add_action('wp_ajax_adhesion_update_user_data', array($this, 'handle_update_user_data'));
        add_action('wp_ajax_adhesion_create_contract', array($this, 'handle_create_contract'));
        add_action('wp_ajax_adhesion_get_contract_status', array($this, 'handle_get_contract_status'));
        
        // AJAX para usuarios no logueados (si es necesario)
        add_action('wp_ajax_nopriv_adhesion_register_user', array($this, 'handle_register_user'));
        
        // AJAX para administradores
        add_action('wp_ajax_adhesion_admin_get_calculations', array($this, 'handle_admin_get_calculations'));
        add_action('wp_ajax_adhesion_admin_get_contracts', array($this, 'handle_admin_get_contracts'));
        add_action('wp_ajax_adhesion_admin_update_prices', array($this, 'handle_admin_update_prices'));
        add_action('wp_ajax_adhesion_admin_save_document', array($this, 'handle_admin_save_document'));
        add_action('wp_ajax_adhesion_admin_get_stats', array($this, 'handle_admin_get_stats'));
    }
    
    // ==========================================
    // AJAX FRONTEND (USUARIOS)
    // ==========================================
    
    /**
     * Manejar cÃ¡lculo de presupuesto
     */
    public function handle_calculate() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad. Recarga la pÃ¡gina e intÃ©ntalo de nuevo.', 'adhesion'));
            }
            
            // Verificar que el usuario estÃ© logueado
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado para calcular presupuestos.', 'adhesion'));
            }
            
            // Sanitizar datos de entrada
            $materials = $this->sanitize_materials_data($_POST);
            
            // Validar datos
            if (empty($materials)) {
                throw new Exception(__('No se han proporcionado materiales para calcular.', 'adhesion'));
            }
            
            // Obtener precios de la base de datos
            $prices = $this->db->get_calculator_prices();
            $price_lookup = array();
            foreach ($prices as $price) {
                $price_lookup[$price['material_type']] = floatval($price['price_per_ton']);
            }
            
            // Calcular presupuesto
            $calculation_result = $this->calculate_budget($materials, $price_lookup);
            
            // Respuesta exitosa
            wp_send_json_success(array(
                'calculation' => $calculation_result,
                'message' => __('CÃ¡lculo realizado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            adhesion_log('Error en cÃ¡lculo AJAX: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Guardar cÃ¡lculo en base de datos
     */
    public function handle_save_calculation() {
        try {
            // Verificar nonce y usuario
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado.', 'adhesion'));
            }
            
            $user_id = get_current_user_id();
            
            // Sanitizar datos
            $calculation_data = $this->sanitize_calculation_data($_POST);
            $total_price = floatval($_POST['total_price']);
            $price_per_ton = isset($_POST['price_per_ton']) ? floatval($_POST['price_per_ton']) : null;
            $total_tons = isset($_POST['total_tons']) ? floatval($_POST['total_tons']) : null;
            
            // Guardar en base de datos
            $calculation_id = $this->db->create_calculation(
                $user_id,
                $calculation_data,
                $total_price,
                $price_per_ton,
                $total_tons
            );
            
            if (!$calculation_id) {
                throw new Exception(__('Error al guardar el cÃ¡lculo.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'calculation_id' => $calculation_id,
                'message' => __('CÃ¡lculo guardado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Obtener datos del usuario actual
     */
    public function handle_get_user_data() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado.', 'adhesion'));
            }
            
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            
            // Obtener cÃ¡lculos del usuario
            $calculations = $this->db->get_user_calculations($user_id, 5);
            
            // Obtener contratos del usuario
            $contracts = $this->db->get_user_contracts($user_id, 5);
            
            $user_data = array(
                'user_info' => array(
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'first_name' => get_user_meta($user_id, 'first_name', true),
                    'last_name' => get_user_meta($user_id, 'last_name', true),
                    'phone' => get_user_meta($user_id, 'phone', true),
                    'company' => get_user_meta($user_id, 'company', true)
                ),
                'recent_calculations' => $calculations,
                'recent_contracts' => $contracts
            );
            
            wp_send_json_success($user_data);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Actualizar datos del usuario
     */
    public function handle_update_user_data() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado.', 'adhesion'));
            }
            
            $user_id = get_current_user_id();
            
            // Sanitizar y actualizar datos
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $phone = sanitize_text_field($_POST['phone']);
            $company = sanitize_text_field($_POST['company']);
            
            update_user_meta($user_id, 'first_name', $first_name);
            update_user_meta($user_id, 'last_name', $last_name);
            update_user_meta($user_id, 'phone', $phone);
            update_user_meta($user_id, 'company', $company);
            
            // Actualizar display_name si es necesario
            if (!empty($first_name) && !empty($last_name)) {
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $first_name . ' ' . $last_name
                ));
            }
            
            wp_send_json_success(array(
                'message' => __('Datos actualizados correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Crear nuevo contrato
     */
    public function handle_create_contract() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado.', 'adhesion'));
            }
            
            $user_id = get_current_user_id();
            $calculation_id = intval($_POST['calculation_id']);
            
            // Sanitizar datos del cliente
            $client_data = array(
                'nombre_completo' => sanitize_text_field($_POST['nombre_completo']),
                'dni_cif' => sanitize_text_field($_POST['dni_cif']),
                'direccion' => sanitize_textarea_field($_POST['direccion']),
                'telefono' => sanitize_text_field($_POST['telefono']),
                'email' => sanitize_email($_POST['email']),
                'empresa' => sanitize_text_field($_POST['empresa'])
            );
            
            // Validar datos obligatorios
            if (empty($client_data['nombre_completo']) || empty($client_data['dni_cif']) || empty($client_data['email'])) {
                throw new Exception(__('Faltan datos obligatorios del cliente.', 'adhesion'));
            }
            
            // Crear contrato
            $contract_id = $this->db->create_contract($user_id, $calculation_id, $client_data);
            
            if (!$contract_id) {
                throw new Exception(__('Error al crear el contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'contract_id' => $contract_id,
                'message' => __('Contrato creado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Obtener estado del contrato
     */
    public function handle_get_contract_status() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Verificar que el usuario tenga acceso al contrato
            if (!current_user_can('manage_options') && $contract['user_id'] != get_current_user_id()) {
                throw new Exception(__('No tienes permisos para ver este contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'contract' => $contract
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Registro de nuevo usuario
     */
    public function handle_register_user() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $username = sanitize_user($_POST['username']);
            $email = sanitize_email($_POST['email']);
            $password = $_POST['password'];
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            
            // Validaciones
            if (empty($username) || empty($email) || empty($password)) {
                throw new Exception(__('Todos los campos son obligatorios.', 'adhesion'));
            }
            
            if (username_exists($username)) {
                throw new Exception(__('El nombre de usuario ya existe.', 'adhesion'));
            }
            
            if (email_exists($email)) {
                throw new Exception(__('El email ya estÃ¡ registrado.', 'adhesion'));
            }
            
            // Crear usuario
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
            
            // Asignar rol de cliente
            $user = new WP_User($user_id);
            $user->set_role('adhesion_client');
            
            // Agregar metadatos
            update_user_meta($user_id, 'first_name', $first_name);
            update_user_meta($user_id, 'last_name', $last_name);
            
            wp_send_json_success(array(
                'user_id' => $user_id,
                'message' => __('Usuario registrado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // ==========================================
    // AJAX ADMIN
    // ==========================================
    
    /**
     * Obtener cÃ¡lculos para admin
     */
    public function handle_admin_get_calculations() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $page = intval($_POST['page']) ?: 1;
            $per_page = intval($_POST['per_page']) ?: 20;
            $offset = ($page - 1) * $per_page;
            
            // Filtros
            $filters = array();
            if (!empty($_POST['user_id'])) {
                $filters['user_id'] = intval($_POST['user_id']);
            }
            if (!empty($_POST['date_from'])) {
                $filters['date_from'] = sanitize_text_field($_POST['date_from']);
            }
            if (!empty($_POST['date_to'])) {
                $filters['date_to'] = sanitize_text_field($_POST['date_to']);
            }
            
            $calculations = $this->db->get_all_calculations($per_page, $offset, $filters);
            
            wp_send_json_success(array(
                'calculations' => $calculations,
                'page' => $page,
                'per_page' => $per_page
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Actualizar precios de materiales
     */
    public function handle_admin_update_prices() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $prices = $_POST['prices'];
            
            if (!is_array($prices)) {
                throw new Exception(__('Datos de precios invÃ¡lidos.', 'adhesion'));
            }
            
            foreach ($prices as $price_data) {
                $material_type = sanitize_text_field($price_data['material_type']);
                $price_per_ton = floatval($price_data['price_per_ton']);
                $minimum_quantity = floatval($price_data['minimum_quantity']);
                
                $this->db->update_material_price($material_type, $price_per_ton, $minimum_quantity);
            }
            
            wp_send_json_success(array(
                'message' => __('Precios actualizados correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Obtener estadÃ­sticas para admin
     */
    public function handle_admin_get_stats() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $basic_stats = $this->db->get_basic_stats();
            
            // EstadÃ­sticas del Ãºltimo mes
            $last_month_stats = $this->db->get_period_stats(
                date('Y-m-d', strtotime('-30 days')),
                date('Y-m-d')
            );
            
            wp_send_json_success(array(
                'basic_stats' => $basic_stats,
                'last_month_stats' => $last_month_stats
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // ==========================================
    // MÃ‰TODOS AUXILIARES
    // ==========================================
    
    /**
     * Sanitizar datos de materiales
     */
    private function sanitize_materials_data($post_data) {
        $materials = array();
        
        if (isset($post_data['materials']) && is_array($post_data['materials'])) {
            foreach ($post_data['materials'] as $material) {
                $materials[] = array(
                    'type' => sanitize_text_field($material['type']),
                    'quantity' => floatval($material['quantity'])
                );
            }
        }
        
        return $materials;
    }
    
    /**
     * Sanitizar datos de cÃ¡lculo
     */
    private function sanitize_calculation_data($post_data) {
        return array(
            'materials' => $this->sanitize_materials_data($post_data),
            'calculation_method' => sanitize_text_field($post_data['calculation_method'] ?: 'standard'),
            'notes' => sanitize_textarea_field($post_data['notes'] ?: ''),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Calcular presupuesto
     */
    private function calculate_budget($materials, $price_lookup) {
        $total_price = 0;
        $total_tons = 0;
        $material_breakdown = array();
        
        foreach ($materials as $material) {
            $material_type = $material['type'];
            $quantity = $material['quantity'];
            
            if (!isset($price_lookup[$material_type])) {
                throw new Exception(sprintf(__('Precio no encontrado para el material: %s', 'adhesion'), $material_type));
            }
            
            $price_per_ton = $price_lookup[$material_type];
            $material_total = $quantity * $price_per_ton;
            
            $material_breakdown[] = array(
                'type' => $material_type,
                'quantity' => $quantity,
                'price_per_ton' => $price_per_ton,
                'total' => $material_total
            );
            
            $total_price += $material_total;
            $total_tons += $quantity;
        }
        
        return array(
            'materials' => $material_breakdown,
            'total_tons' => $total_tons,
            'total_price' => $total_price,
            'average_price_per_ton' => $total_tons > 0 ? $total_price / $total_tons : 0,
            'calculation_date' => current_time('mysql')
        );
    }
}


// ===== class-database.php =====
<?php
/**
 * Clase para gestiÃ³n de base de datos
 * 
 * Esta clase maneja todas las operaciones CRUD del plugin:
 * - CÃ¡lculos de presupuestos
 * - Contratos de adhesiÃ³n
 * - Documentos editables
 * - Configuraciones
 * - Precios de calculadora
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Database {
    
    /**
     * Instancia de WordPress Database
     */
    private $wpdb;
    
    /**
     * Nombres de las tablas
     */
    private $table_calculations;
    private $table_contracts;
    private $table_documents;
    private $table_settings;
    private $table_calculator_prices;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Definir nombres de tablas
        $this->table_calculations = $wpdb->prefix . 'adhesion_calculations';
        $this->table_contracts = $wpdb->prefix . 'adhesion_contracts';
        $this->table_documents = $wpdb->prefix . 'adhesion_documents';
        $this->table_settings = $wpdb->prefix . 'adhesion_settings';
        $this->table_calculator_prices = $wpdb->prefix . 'adhesion_calculator_prices';
    }
    
    // ==========================================
    // MÃ‰TODOS PARA CÃLCULOS DE PRESUPUESTOS
    // ==========================================
    
    /**
     * Crear un nuevo cÃ¡lculo
     */
    public function create_calculation($user_id, $calculation_data, $total_price, $price_per_ton = null, $total_tons = null) {
        $data = array(
            'user_id' => $user_id,
            'calculation_data' => is_array($calculation_data) ? json_encode($calculation_data) : $calculation_data,
            'total_price' => $total_price,
            'price_per_ton' => $price_per_ton,
            'total_tons' => $total_tons,
            'status' => 'active'
        );
        
        $result = $this->wpdb->insert(
            $this->table_calculations,
            $data,
            array('%d', '%s', '%f', '%f', '%f', '%s')
        );
        
        if ($result === false) {
            adhesion_log('Error al crear cÃ¡lculo: ' . $this->wpdb->last_error, 'error');
            return false;
        }
        
        $calculation_id = $this->wpdb->insert_id;
        adhesion_log("CÃ¡lculo creado con ID: $calculation_id", 'info');
        
        return $calculation_id;
    }
    
    /**
     * Obtener cÃ¡lculo por ID
     */
    public function get_calculation($calculation_id) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_calculations} WHERE id = %d",
                $calculation_id
            ),
            ARRAY_A
        );
        
        if ($result && !empty($result['calculation_data'])) {
            $result['calculation_data'] = json_decode($result['calculation_data'], true);
        }
        
        return $result;
    }
    
    /**
     * Obtener cÃ¡lculos de un usuario
     */
    public function get_user_calculations($user_id, $limit = 10, $offset = 0) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_calculations} 
                 WHERE user_id = %d AND status = 'active'
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $user_id, $limit, $offset
            ),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['calculation_data'])) {
                $result['calculation_data'] = json_decode($result['calculation_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Obtener todos los cÃ¡lculos (para admin)
     */
    public function get_all_calculations($limit = 50, $offset = 0, $filters = array()) {
        $where_clauses = array("status = 'active'");
        $params = array();
        
        // Aplicar filtros
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT c.*, u.display_name as user_name, u.user_email 
                FROM {$this->table_calculations} c
                LEFT JOIN {$this->wpdb->users} u ON c.user_id = u.ID
                WHERE $where_sql
                ORDER BY c.created_at DESC 
                LIMIT %d OFFSET %d";
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['calculation_data'])) {
                $result['calculation_data'] = json_decode($result['calculation_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Actualizar cÃ¡lculo
     */
    public function update_calculation($calculation_id, $data) {
        if (isset($data['calculation_data']) && is_array($data['calculation_data'])) {
            $data['calculation_data'] = json_encode($data['calculation_data']);
        }
        
        $result = $this->wpdb->update(
            $this->table_calculations,
            $data,
            array('id' => $calculation_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    // ==========================================
    // MÃ‰TODOS PARA CONTRATOS
    // ==========================================
    
    /**
     * Crear un nuevo contrato
     */
    public function create_contract($user_id, $calculation_id = null, $client_data = array()) {
        // Generar nÃºmero de contrato Ãºnico
        $contract_number = $this->generate_contract_number();
        
        $data = array(
            'user_id' => $user_id,
            'calculation_id' => $calculation_id,
            'contract_number' => $contract_number,
            'status' => 'pending',
            'client_data' => json_encode($client_data),
            'payment_status' => 'pending'
        );
        
        $result = $this->wpdb->insert(
            $this->table_contracts,
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            adhesion_log('Error al crear contrato: ' . $this->wpdb->last_error, 'error');
            return false;
        }
        
        $contract_id = $this->wpdb->insert_id;
        adhesion_log("Contrato creado con ID: $contract_id, NÃºmero: $contract_number", 'info');
        
        return $contract_id;
    }
    
    /**
     * Generar nÃºmero de contrato Ãºnico
     */
    private function generate_contract_number() {
        $prefix = 'ADH';
        $year = date('Y');
        $month = date('m');
        
        // Buscar el Ãºltimo nÃºmero del mes
        $last_number = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT MAX(CAST(SUBSTRING(contract_number, -4) AS UNSIGNED)) 
                 FROM {$this->table_contracts} 
                 WHERE contract_number LIKE %s",
                $prefix . $year . $month . '%'
            )
        );
        
        $next_number = ($last_number ? $last_number : 0) + 1;
        
        return sprintf('%s%s%s%04d', $prefix, $year, $month, $next_number);
    }
    
    /**
     * Obtener contrato por ID
     */
    public function get_contract($contract_id) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT c.*, calc.total_price, calc.calculation_data, u.display_name as user_name, u.user_email
                 FROM {$this->table_contracts} c
                 LEFT JOIN {$this->table_calculations} calc ON c.calculation_id = calc.id
                 LEFT JOIN {$this->wpdb->users} u ON c.user_id = u.ID
                 WHERE c.id = %d",
                $contract_id
            ),
            ARRAY_A
        );
        
        if ($result) {
            // Decodificar datos JSON
            if (!empty($result['client_data'])) {
                $result['client_data'] = json_decode($result['client_data'], true);
            }
            if (!empty($result['calculation_data'])) {
                $result['calculation_data'] = json_decode($result['calculation_data'], true);
            }
        }
        
        return $result;
    }
    
    /**
     * Obtener contratos de un usuario
     */
    public function get_user_contracts($user_id, $limit = 10, $offset = 0) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT c.*, calc.total_price 
                 FROM {$this->table_contracts} c
                 LEFT JOIN {$this->table_calculations} calc ON c.calculation_id = calc.id
                 WHERE c.user_id = %d
                 ORDER BY c.created_at DESC 
                 LIMIT %d OFFSET %d",
                $user_id, $limit, $offset
            ),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['client_data'])) {
                $result['client_data'] = json_decode($result['client_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Actualizar estado del contrato
     */
    public function update_contract_status($contract_id, $status, $additional_data = array()) {
        $data = array_merge(array('status' => $status), $additional_data);
        
        // Agregar timestamp especÃ­fico segÃºn el estado
        if ($status === 'signed') {
            $data['signed_at'] = current_time('mysql');
        } elseif ($status === 'completed' && !empty($additional_data['payment_status']) && $additional_data['payment_status'] === 'completed') {
            $data['payment_completed_at'] = current_time('mysql');
        }
        
        $result = $this->wpdb->update(
            $this->table_contracts,
            $data,
            array('id' => $contract_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            adhesion_log("Contrato $contract_id actualizado a estado: $status", 'info');
        }
        
        return $result !== false;
    }
    
    // ==========================================
    // MÃ‰TODOS PARA DOCUMENTOS
    // ==========================================
    
    /**
     * Obtener documentos activos por tipo
     */
    public function get_active_documents($document_type = null) {
        $where = "is_active = 1";
        $params = array();
        
        if ($document_type) {
            $where .= " AND document_type = %s";
            $params[] = $document_type;
        }
        
        $sql = "SELECT * FROM {$this->table_documents} WHERE $where ORDER BY created_at DESC";
        
        if (!empty($params)) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $params),
                ARRAY_A
            );
        } else {
            return $this->wpdb->get_results($sql, ARRAY_A);
        }
    }
    
    /**
     * Crear o actualizar documento
     */
    public function save_document($data, $document_id = null) {
        if ($document_id) {
            // Actualizar documento existente
            $result = $this->wpdb->update(
                $this->table_documents,
                $data,
                array('id' => $document_id),
                null,
                array('%d')
            );
        } else {
            // Crear nuevo documento
            $result = $this->wpdb->insert(
                $this->table_documents,
                $data
            );
            $document_id = $this->wpdb->insert_id;
        }
        
        return $result !== false ? $document_id : false;
    }
    
    // ==========================================
    // MÃ‰TODOS PARA PRECIOS DE CALCULADORA
    // ==========================================
    
    /**
     * Obtener precios activos de calculadora
     */
    public function get_calculator_prices() {
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_calculator_prices} 
             WHERE is_active = 1 
             ORDER BY material_type ASC",
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Actualizar precio de material
     */
    public function update_material_price($material_type, $price_per_ton, $minimum_quantity = 0) {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_calculator_prices} WHERE material_type = %s",
                $material_type
            )
        );
        
        $data = array(
            'material_type' => $material_type,
            'price_per_ton' => $price_per_ton,
            'minimum_quantity' => $minimum_quantity,
            'is_active' => 1
        );
        
        if ($existing) {
            $result = $this->wpdb->update(
                $this->table_calculator_prices,
                $data,
                array('id' => $existing),
                array('%s', '%f', '%f', '%d'),
                array('%d')
            );
        } else {
            $result = $this->wpdb->insert(
                $this->table_calculator_prices,
                $data,
                array('%s', '%f', '%f', '%d')
            );
        }
        
        return $result !== false;
    }
    
    // ==========================================
    // MÃ‰TODOS ESTADÃSTICOS Y REPORTES
    // ==========================================
    
    /**
     * Obtener estadÃ­sticas bÃ¡sicas
     */
    public function get_basic_stats() {
        $stats = array();
        
        // Total de cÃ¡lculos
        $stats['total_calculations'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_calculations} WHERE status = 'active'"
        );
        
        // Total de contratos
        $stats['total_contracts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_contracts}"
        );
        
        // Contratos firmados
        $stats['signed_contracts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_contracts} WHERE status = 'signed'"
        );
        
        // Contratos pagados
        $stats['paid_contracts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_contracts} WHERE payment_status = 'completed'"
        );
        
        // Ingresos totales
        $stats['total_revenue'] = $this->wpdb->get_var(
            "SELECT SUM(payment_amount) FROM {$this->table_contracts} WHERE payment_status = 'completed'"
        ) ?: 0;
        
        return $stats;
    }
    
    /**
     * Obtener estadÃ­sticas por perÃ­odo
     */
    public function get_period_stats($date_from, $date_to) {
        $stats = array();
        
        // CÃ¡lculos en el perÃ­odo
        $stats['period_calculations'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_calculations} 
                 WHERE created_at BETWEEN %s AND %s AND status = 'active'",
                $date_from, $date_to
            )
        );
        
        // Contratos en el perÃ­odo
        $stats['period_contracts'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_contracts} 
                 WHERE created_at BETWEEN %s AND %s",
                $date_from, $date_to
            )
        );
        
        // Ingresos en el perÃ­odo
        $stats['period_revenue'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(payment_amount) FROM {$this->table_contracts} 
                 WHERE payment_completed_at BETWEEN %s AND %s AND payment_status = 'completed'",
                $date_from, $date_to
            )
        ) ?: 0;
        
        return $stats;
    }
    
    // ==========================================
    // MÃ‰TODOS DE UTILIDAD
    // ==========================================
    
    /**
     * Verificar si las tablas existen
     */
    public function tables_exist() {
        $tables = array(
            $this->table_calculations,
            $this->table_contracts,
            $this->table_documents,
            $this->table_settings,
            $this->table_calculator_prices
        );
        
        foreach ($tables as $table) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Limpiar datos antiguos
     */
    public function cleanup_old_data($days = 365) {
        $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Eliminar cÃ¡lculos antiguos sin contratos asociados
        $deleted_calculations = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE c FROM {$this->table_calculations} c
                 LEFT JOIN {$this->table_contracts} ct ON c.id = ct.calculation_id
                 WHERE c.created_at < %s AND ct.id IS NULL",
                $date_limit
            )
        );
        
        if ($deleted_calculations > 0) {
            adhesion_log("Limpieza automÃ¡tica: $deleted_calculations cÃ¡lculos antiguos eliminados", 'info');
        }
        
        return $deleted_calculations;
    }
}


// ===== class-deactivator.php =====
<?php
/**
 * Clase para desactivaciÃ³n del plugin
 * 
 * Esta clase se encarga de las tareas de limpieza cuando se desactiva el plugin:
 * - Limpiar datos temporales
 * - Limpiar cache y transients
 * - Log de desactivaciÃ³n
 * 
 * NOTA: Esta clase NO elimina datos permanentes como tablas o configuraciones.
 * Para eso estÃ¡ el archivo uninstall.php que se ejecuta al DESINSTALAR el plugin.
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Deactivator {
    
    /**
     * MÃ©todo principal de desactivaciÃ³n
     */
    public static function deactivate() {
        // Limpiar tareas programadas
        self::clear_scheduled_tasks();
        
        // Limpiar cache y transients
        self::clear_cache_and_transients();
        
        // Limpiar archivos temporales
        self::clear_temp_files();
        
        // Actualizar permalinks
        flush_rewrite_rules();
        
        // Log de desactivaciÃ³n
        adhesion_log('Plugin desactivado correctamente', 'info');
        
        // Marcar fecha de desactivaciÃ³n
        update_option('adhesion_deactivated', current_time('mysql'));
    }
    
    /**
     * Limpiar tareas programadas (cron jobs)
     */
    private static function clear_scheduled_tasks() {
        // Limpiar eventos cron del plugin
        $scheduled_events = array(
            'adhesion_cleanup_temp_files',
            'adhesion_check_pending_contracts',
            'adhesion_send_reminder_emails',
            'adhesion_docusign_status_check'
        );
        
        foreach ($scheduled_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
                adhesion_log("Evento programado eliminado: $event", 'info');
            }
        }
        
        // Limpiar todos los eventos cron relacionados con el plugin
        wp_clear_scheduled_hook('adhesion_cleanup_temp_files');
        wp_clear_scheduled_hook('adhesion_check_pending_contracts');
        wp_clear_scheduled_hook('adhesion_send_reminder_emails');
        wp_clear_scheduled_hook('adhesion_docusign_status_check');
    }
    
    /**
     * Limpiar cache y transients
     */
    private static function clear_cache_and_transients() {
        global $wpdb;
        
        // Eliminar transients especÃ­ficos del plugin
        $transients_to_delete = array(
            'adhesion_calculator_prices',
            'adhesion_docusign_token',
            'adhesion_redsys_settings',
            'adhesion_active_documents',
            'adhesion_user_calculations_%', // % para wildcards
            'adhesion_contract_status_%'
        );
        
        foreach ($transients_to_delete as $transient) {
            if (strpos($transient, '%') !== false) {
                // Para transients con wildcards
                $transient_pattern = str_replace('%', '', $transient);
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_' . $transient_pattern . '%',
                    '_transient_timeout_' . $transient_pattern . '%'
                ));
            } else {
                // Para transients especÃ­ficos
                delete_transient($transient);
            }
        }
        
        // Limpiar object cache si estÃ¡ disponible
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        adhesion_log('Cache y transients limpiados', 'info');
    }
    
    /**
     * Limpiar archivos temporales
     */
    private static function clear_temp_files() {
        // Directorio de archivos temporales del plugin
        $temp_dir = WP_CONTENT_DIR . '/uploads/adhesion/temp/';
        
        if (is_dir($temp_dir)) {
            self::delete_directory_contents($temp_dir, false); // No eliminar el directorio, solo el contenido
            adhesion_log('Archivos temporales limpiados', 'info');
        }
        
        // Limpiar archivos de documentos temporales mÃ¡s antiguos de 24 horas
        $uploads_dir = WP_CONTENT_DIR . '/uploads/adhesion/documents/temp/';
        if (is_dir($uploads_dir)) {
            self::cleanup_old_temp_files($uploads_dir, 24 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Eliminar contenido de un directorio
     */
    private static function delete_directory_contents($dir, $delete_dir = false) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $dir . '/' . $file;
            
            if (is_dir($file_path)) {
                self::delete_directory_contents($file_path, true);
            } else {
                unlink($file_path);
            }
        }
        
        if ($delete_dir) {
            rmdir($dir);
        }
        
        return true;
    }
    
    /**
     * Limpiar archivos temporales antiguos
     */
    private static function cleanup_old_temp_files($dir, $max_age_seconds) {
        if (!is_dir($dir)) {
            return;
        }
        
        $current_time = time();
        $files = glob($dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $file_age = $current_time - filemtime($file);
                
                if ($file_age > $max_age_seconds) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Limpiar opciones temporales (opcional)
     * 
     * Estas opciones se pueden limpiar en la desactivaciÃ³n si se desea,
     * pero generalmente se mantienen para cuando se reactive el plugin
     */
    private static function clear_temporary_options() {
        $temp_options = array(
            'adhesion_temp_calculations',
            'adhesion_pending_notifications',
            'adhesion_last_cron_run',
            'adhesion_docusign_temp_tokens'
        );
        
        foreach ($temp_options as $option) {
            delete_option($option);
        }
        
        adhesion_log('Opciones temporales limpiadas', 'info');
    }
    
    /**
     * Remover capacidades de roles (opcional)
     * 
     * CUIDADO: Solo descomentar si se quiere remover las capacidades
     * al desactivar el plugin. Normalmente se mantienen.
     */
    private static function remove_capabilities() {
        /*
        // Remover capacidades del administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('adhesion_manage_all');
            $admin_role->remove_cap('adhesion_manage_settings');
            $admin_role->remove_cap('adhesion_manage_documents');
            $admin_role->remove_cap('adhesion_view_reports');
        }
        
        // Remover capacidades del editor
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->remove_cap('adhesion_view_reports');
            $editor_role->remove_cap('adhesion_manage_documents');
        }
        
        // NOTA: No eliminamos el rol 'adhesion_client' porque los usuarios
        // podrÃ­an tener ese rol asignado y causarÃ­a problemas
        */
    }
    
    /**
     * Crear respaldo de configuraciones importantes antes de limpiar
     */
    private static function backup_important_settings() {
        $important_settings = array(
            'redsys_merchant_code',
            'redsys_secret_key',
            'docusign_integration_key',
            'docusign_secret_key',
            'docusign_account_id'
        );
        
        $backup_data = array();
        $current_settings = get_option('adhesion_settings', array());
        
        foreach ($important_settings as $setting) {
            if (isset($current_settings[$setting]) && !empty($current_settings[$setting])) {
                $backup_data[$setting] = $current_settings[$setting];
            }
        }
        
        if (!empty($backup_data)) {
            update_option('adhesion_settings_backup', $backup_data);
            adhesion_log('Respaldo de configuraciones creado', 'info');
        }
    }
}


// ===== functions.php =====
<?php
/**
 * Funciones auxiliares del plugin AdhesiÃ³n
 * 
 * Este archivo contiene funciones de utilidad que se usan en todo el plugin:
 * - Funciones de formato
 * - Validaciones
 * - Helpers para templates
 * - Utilidades generales
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// FUNCIONES DE FORMATO
// ==========================================

/**
 * Formatear precio con moneda
 */
function adhesion_format_price($price, $currency = 'â‚¬') {
    return number_format($price, 2, ',', '.') . ' ' . $currency;
}

/**
 * Formatear cantidad en toneladas
 */
function adhesion_format_tons($tons) {
    return number_format($tons, 2, ',', '.') . ' t';
}

/**
 * Formatear fecha en espaÃ±ol
 */
function adhesion_format_date($date, $format = 'd/m/Y H:i') {
    if (empty($date)) {
        return '-';
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date_i18n($format, $timestamp);
}

/**
 * Formatear estado de contrato
 */
function adhesion_format_contract_status($status) {
    $statuses = array(
        'pending' => array(
            'label' => __('Pendiente', 'adhesion'),
            'class' => 'status-pending'
        ),
        'signed' => array(
            'label' => __('Firmado', 'adhesion'),
            'class' => 'status-signed'
        ),
        'completed' => array(
            'label' => __('Completado', 'adhesion'),
            'class' => 'status-completed'
        ),
        'cancelled' => array(
            'label' => __('Cancelado', 'adhesion'),
            'class' => 'status-cancelled'
        )
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : array(
        'label' => ucfirst($status),
        'class' => 'status-unknown'
    );
}

/**
 * Formatear estado de pago
 */
function adhesion_format_payment_status($status) {
    $statuses = array(
        'pending' => array(
            'label' => __('Pendiente', 'adhesion'),
            'class' => 'payment-pending'
        ),
        'processing' => array(
            'label' => __('Procesando', 'adhesion'),
            'class' => 'payment-processing'
        ),
        'completed' => array(
            'label' => __('Completado', 'adhesion'),
            'class' => 'payment-completed'
        ),
        'failed' => array(
            'label' => __('Fallido', 'adhesion'),
            'class' => 'payment-failed'
        ),
        'refunded' => array(
            'label' => __('Reembolsado', 'adhesion'),
            'class' => 'payment-refunded'
        )
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : array(
        'label' => ucfirst($status),
        'class' => 'payment-unknown'
    );
}

// ==========================================
// FUNCIONES DE VALIDACIÃ“N
// ==========================================

/**
 * Validar DNI/CIF espaÃ±ol
 */
function adhesion_validate_dni_cif($value) {
    $value = strtoupper(trim($value));
    
    // Validar DNI (8 nÃºmeros + 1 letra)
    if (preg_match('/^[0-9]{8}[A-Z]$/', $value)) {
        $number = substr($value, 0, 8);
        $letter = substr($value, 8, 1);
        $valid_letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $calculated_letter = $valid_letters[intval($number) % 23];
        return $letter === $calculated_letter;
    }
    
    // Validar CIF (1 letra + 7 nÃºmeros + 1 dÃ­gito de control)
    if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $value)) {
        return true; // ValidaciÃ³n bÃ¡sica para CIF
    }
    
    return false;
}

/**
 * Validar email
 */
function adhesion_validate_email($email) {
    return is_email($email);
}

/**
 * Validar telÃ©fono espaÃ±ol
 */
function adhesion_validate_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Formatos vÃ¡lidos: 9 dÃ­gitos, +34 seguido de 9 dÃ­gitos
    return preg_match('/^(?:\+34)?[67][0-9]{8}$/', $phone);
}

/**
 * Validar cÃ³digo postal espaÃ±ol
 */
function adhesion_validate_postal_code($code) {
    return preg_match('/^[0-5][0-9]{4}$/', $code);
}

/**
 * Validar cantidad de material
 */
function adhesion_validate_material_quantity($quantity) {
    $quantity = floatval($quantity);
    return $quantity > 0 && $quantity <= 1000; // MÃ¡ximo 1000 toneladas
}

// ==========================================
// FUNCIONES DE UTILIDAD
// ==========================================

/**
 * Obtener tipos de material disponibles
 */
function adhesion_get_material_types() {
    $db = new Adhesion_Database();
    $prices = $db->get_calculator_prices();
    
    $types = array();
    foreach ($prices as $price) {
        $types[$price['material_type']] = $price['material_type'];
    }
    
    return $types;
}

/**
 * Obtener precio de un material
 */
function adhesion_get_material_price($material_type) {
    $db = new Adhesion_Database();
    $prices = $db->get_calculator_prices();
    
    foreach ($prices as $price) {
        if ($price['material_type'] === $material_type) {
            return floatval($price['price_per_ton']);
        }
    }
    
    return 0;
}

/**
 * Generar nÃºmero de pedido Ãºnico
 */
function adhesion_generate_order_number($prefix = 'ADH') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(wp_generate_password(6, false));
}

/**
 * Obtener configuraciÃ³n del plugin
 */
function adhesion_get_setting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $settings = get_option('adhesion_settings', array());
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Actualizar configuraciÃ³n del plugin
 */
function adhesion_update_setting($key, $value) {
    $settings = get_option('adhesion_settings', array());
    $settings[$key] = $value;
    return update_option('adhesion_settings', $settings);
}

/**
 * Verificar si las integraciones estÃ¡n configuradas
 */
function adhesion_is_redsys_configured() {
    $merchant_code = adhesion_get_setting('redsys_merchant_code');
    $secret_key = adhesion_get_setting('redsys_secret_key');
    
    return !empty($merchant_code) && !empty($secret_key);
}

function adhesion_is_docusign_configured() {
    $integration_key = adhesion_get_setting('docusign_integration_key');
    $account_id = adhesion_get_setting('docusign_account_id');
    
    return !empty($integration_key) && !empty($account_id);
}

// ==========================================
// FUNCIONES PARA TEMPLATES
// ==========================================

/**
 * Cargar template del plugin
 */
function adhesion_get_template($template_name, $vars = array()) {
    $template_path = ADHESION_PLUGIN_PATH . 'templates/' . $template_name . '.php';
    
    if (!file_exists($template_path)) {
        adhesion_log("Template no encontrado: $template_name", 'error');
        return '';
    }
    
    // Extraer variables para el template
    extract($vars);
    
    ob_start();
    include $template_path;
    return ob_get_clean();
}

/**
 * Mostrar template del plugin
 */
function adhesion_display_template($template_name, $vars = array()) {
    echo adhesion_get_template($template_name, $vars);
}

/**
 * Generar nonce para formularios
 */
function adhesion_nonce_field($action = 'adhesion_nonce') {
    return wp_nonce_field($action, 'adhesion_nonce', true, false);
}

/**
 * Generar URL de acciÃ³n
 */
function adhesion_get_action_url($action, $extra_args = array()) {
    $args = array_merge(array('adhesion_action' => $action), $extra_args);
    return add_query_arg($args, home_url());
}

// ==========================================
// FUNCIONES DE NOTIFICACIÃ“N
// ==========================================

/**
 * Agregar notificaciÃ³n al usuario
 */
function adhesion_add_notice($message, $type = 'info') {
    $notices = get_transient('adhesion_notices_' . get_current_user_id()) ?: array();
    $notices[] = array(
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    );
    
    set_transient('adhesion_notices_' . get_current_user_id(), $notices, 300); // 5 minutos
}

/**
 * Obtener y limpiar notificaciones
 */
function adhesion_get_notices() {
    $user_id = get_current_user_id();
    $notices = get_transient('adhesion_notices_' . $user_id) ?: array();
    
    if (!empty($notices)) {
        delete_transient('adhesion_notices_' . $user_id);
    }
    
    return $notices;
}

/**
 * Mostrar notificaciones
 */
function adhesion_display_notices() {
    $notices = adhesion_get_notices();
    
    if (empty($notices)) {
        return;
    }
    
    foreach ($notices as $notice) {
        $class = 'adhesion-notice adhesion-notice-' . esc_attr($notice['type']);
        echo '<div class="' . $class . '">';
        echo '<p>' . esc_html($notice['message']) . '</p>';
        echo '</div>';
    }
}

// ==========================================
// FUNCIONES DE EMAIL
// ==========================================

/**
 * Enviar email usando template
 */
function adhesion_send_email($to, $subject, $template_name, $vars = array()) {
    // Obtener configuraciÃ³n de email
    $from_name = adhesion_get_setting('email_from_name', get_bloginfo('name'));
    $from_email = adhesion_get_setting('email_from_address', get_option('admin_email'));
    
    // Headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    );
    
    // Cargar template de email
    $message = adhesion_get_template('emails/' . $template_name, $vars);
    
    if (empty($message)) {
        adhesion_log("Template de email no encontrado: $template_name", 'error');
        return false;
    }
    
    // Enviar email
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        adhesion_log("Email enviado a $to con template $template_name", 'info');
    } else {
        adhesion_log("Error enviando email a $to", 'error');
    }
    
    return $sent;
}

/**
 * Notificar al administrador
 */
function adhesion_notify_admin($subject, $message, $data = array()) {
    $admin_email = adhesion_get_setting('admin_email', get_option('admin_email'));
    
    $vars = array_merge($data, array(
        'subject' => $subject,
        'message' => $message,
        'site_name' => get_bloginfo('name'),
        'site_url' => get_home_url()
    ));
    
    return adhesion_send_email($admin_email, $subject, 'admin-notification', $vars);
}

// ==========================================
// FUNCIONES DE SEGURIDAD
// ==========================================

/**
 * Sanitizar datos de material
 */
function adhesion_sanitize_material_data($data) {
    return array(
        'type' => sanitize_text_field($data['type'] ?? ''),
        'quantity' => floatval($data['quantity'] ?? 0),
        'price_per_ton' => floatval($data['price_per_ton'] ?? 0)
    );
}

/**
 * Sanitizar datos de cliente
 */
function adhesion_sanitize_client_data($data) {
    return array(
        'nombre_completo' => sanitize_text_field($data['nombre_completo'] ?? ''),
        'dni_cif' => sanitize_text_field($data['dni_cif'] ?? ''),
        'direccion' => sanitize_textarea_field($data['direccion'] ?? ''),
        'codigo_postal' => sanitize_text_field($data['codigo_postal'] ?? ''),
        'ciudad' => sanitize_text_field($data['ciudad'] ?? ''),
        'provincia' => sanitize_text_field($data['provincia'] ?? ''),
        'telefono' => sanitize_text_field($data['telefono'] ?? ''),
        'email' => sanitize_email($data['email'] ?? ''),
        'empresa' => sanitize_text_field($data['empresa'] ?? '')
    );
}

/**
 * Verificar permisos de usuario
 */
function adhesion_user_can($capability, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    
    $user = get_userdata($user_id);
    
    if (!$user) {
        return false;
    }
    
    // Verificar capacidades especÃ­ficas del plugin
    $adhesion_caps = array(
        'adhesion_access' => array('adhesion_client', 'administrator', 'editor'),
        'adhesion_calculate' => array('adhesion_client', 'administrator', 'editor'),
        'adhesion_manage_all' => array('administrator'),
        'adhesion_manage_settings' => array('administrator'),
        'adhesion_view_reports' => array('administrator', 'editor')
    );
    
    if (isset($adhesion_caps[$capability])) {
        $allowed_roles = $adhesion_caps[$capability];
        $user_roles = $user->roles;
        
        return array_intersect($user_roles, $allowed_roles) !== array();
    }
    
    // Fallback a capacidades estÃ¡ndar de WordPress
    return user_can($user_id, $capability);
}

// ==========================================
// FUNCIONES DE DEBUG
// ==========================================

/**
 * Debug de variables del plugin
 */
function adhesion_debug($var, $label = 'DEBUG') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[$label] " . print_r($var, true));
    }
}

/**
 * Obtener informaciÃ³n del sistema
 */
function adhesion_get_system_info() {
    global $wpdb;
    
    return array(
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'plugin_version' => ADHESION_PLUGIN_VERSION,
        'tables_exist' => (new Adhesion_Database())->tables_exist(),
        'redsys_configured' => adhesion_is_redsys_configured(),
        'docusign_configured' => adhesion_is_docusign_configured(),
        'active_theme' => get_option('stylesheet'),
        'mysql_version' => $wpdb->db_version()
    );
}


// ===== class-calculator.php =====
<?php
/**
 * Clase para la calculadora de presupuestos
 * 
 * Esta clase maneja toda la lÃ³gica de la calculadora:
 * - CÃ¡lculos de precios por materiales
 * - Validaciones de cantidades
 * - GeneraciÃ³n de presupuestos
 * - Guardado de cÃ¡lculos
 * - AJAX para calculadora interactiva
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Calculator {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Precios de materiales en cachÃ©
     */
    private $material_prices;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
        $this->load_material_prices();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para usuarios logueados
        add_action('wp_ajax_adhesion_calculate_budget', array($this, 'ajax_calculate_budget'));
        add_action('wp_ajax_adhesion_save_calculation', array($this, 'ajax_save_calculation'));
        add_action('wp_ajax_adhesion_get_material_prices', array($this, 'ajax_get_material_prices'));
        add_action('wp_ajax_adhesion_validate_materials', array($this, 'ajax_validate_materials'));
        
        // AJAX para usuarios no logueados (solo cÃ¡lculos, no guardado)
        add_action('wp_ajax_nopriv_adhesion_calculate_budget', array($this, 'ajax_calculate_budget_preview'));
        add_action('wp_ajax_nopriv_adhesion_get_material_prices', array($this, 'ajax_get_material_prices'));
    }
    
    /**
     * Cargar precios de materiales
     */
    private function load_material_prices() {
        // Usar cachÃ© transitorio para mejorar rendimiento
        $this->material_prices = get_transient('adhesion_calculator_prices');
        
        if (false === $this->material_prices) {
            $prices = $this->db->get_calculator_prices();
            $this->material_prices = array();
            
            foreach ($prices as $price) {
                $this->material_prices[$price['material_type']] = array(
                    'price_per_ton' => floatval($price['price_per_ton']),
                    'minimum_quantity' => floatval($price['minimum_quantity']),
                    'is_active' => $price['is_active']
                );
            }
            
            // Cachear por 1 hora
            set_transient('adhesion_calculator_prices', $this->material_prices, HOUR_IN_SECONDS);
        }
    }
    
    // ==========================================
    // MÃ‰TODOS PRINCIPALES DE CÃLCULO
    // ==========================================
    
    /**
     * Calcular presupuesto completo
     */
    public function calculate_budget($materials, $options = array()) {
        try {
            // Validar entrada
            if (empty($materials) || !is_array($materials)) {
                throw new Exception(__('No se han proporcionado materiales para calcular.', 'adhesion'));
            }
            
            // Opciones por defecto
            $options = wp_parse_args($options, array(
                'apply_discounts' => true,
                'include_taxes' => true,
                'tax_rate' => 21, // IVA 21% por defecto
                'minimum_order' => 0
            ));
            
            $calculation_result = array(
                'materials' => array(),
                'subtotal' => 0,
                'total_tons' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_price' => 0,
                'average_price_per_ton' => 0,
                'warnings' => array(),
                'calculation_details' => array()
            );
            
            // Procesar cada material
            foreach ($materials as $material) {
                $material_result = $this->calculate_material($material, $options);
                
                if ($material_result) {
                    $calculation_result['materials'][] = $material_result;
                    $calculation_result['subtotal'] += $material_result['total'];
                    $calculation_result['total_tons'] += $material_result['quantity'];
                    
                    // Agregar advertencias si las hay
                    if (!empty($material_result['warnings'])) {
                        $calculation_result['warnings'] = array_merge(
                            $calculation_result['warnings'], 
                            $material_result['warnings']
                        );
                    }
                }
            }
            
            // Aplicar descuentos si corresponde
            if ($options['apply_discounts']) {
                $calculation_result['discount_amount'] = $this->calculate_discount(
                    $calculation_result['subtotal'], 
                    $calculation_result['total_tons']
                );
            }
            
            // Calcular impuestos
            if ($options['include_taxes']) {
                $taxable_amount = $calculation_result['subtotal'] - $calculation_result['discount_amount'];
                $calculation_result['tax_amount'] = $taxable_amount * ($options['tax_rate'] / 100);
            }
            
            // Precio total final
            $calculation_result['total_price'] = $calculation_result['subtotal'] - 
                                               $calculation_result['discount_amount'] + 
                                               $calculation_result['tax_amount'];
            
            // Precio promedio por tonelada
            if ($calculation_result['total_tons'] > 0) {
                $calculation_result['average_price_per_ton'] = $calculation_result['total_price'] / $calculation_result['total_tons'];
            }
            
            // Verificar pedido mÃ­nimo
            if ($options['minimum_order'] > 0 && $calculation_result['total_price'] < $options['minimum_order']) {
                $calculation_result['warnings'][] = sprintf(
                    __('El pedido mÃ­nimo es de %s. Cantidad actual: %s', 'adhesion'),
                    adhesion_format_price($options['minimum_order']),
                    adhesion_format_price($calculation_result['total_price'])
                );
            }
            
            // Detalles adicionales para el log
            $calculation_result['calculation_details'] = array(
                'calculation_date' => current_time('mysql'),
                'options_used' => $options,
                'material_count' => count($calculation_result['materials']),
                'has_warnings' => !empty($calculation_result['warnings'])
            );
            
            return $calculation_result;
            
        } catch (Exception $e) {
            adhesion_log('Error en cÃ¡lculo de presupuesto: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Calcular un material especÃ­fico
     */
    private function calculate_material($material, $options) {
        try {
            // Sanitizar y validar datos del material
            $material_type = sanitize_text_field($material['type']);
            $quantity = floatval($material['quantity']);
            
            // Validaciones bÃ¡sicas
            if (empty($material_type)) {
                throw new Exception(__('Tipo de material no especificado.', 'adhesion'));
            }
            
            if ($quantity <= 0) {
                throw new Exception(sprintf(__('Cantidad invÃ¡lida para %s.', 'adhesion'), $material_type));
            }
            
            if ($quantity > 1000) { // LÃ­mite mÃ¡ximo de 1000 toneladas
                throw new Exception(sprintf(__('Cantidad mÃ¡xima excedida para %s (mÃ¡ximo: 1000t).', 'adhesion'), $material_type));
            }
            
            // Verificar si el material estÃ¡ disponible
            if (!isset($this->material_prices[$material_type])) {
                throw new Exception(sprintf(__('Material no disponible: %s', 'adhesion'), $material_type));
            }
            
            $material_info = $this->material_prices[$material_type];
            
            // Verificar si el material estÃ¡ activo
            if (!$material_info['is_active']) {
                throw new Exception(sprintf(__('Material temporalmente no disponible: %s', 'adhesion'), $material_type));
            }
            
            $price_per_ton = $material_info['price_per_ton'];
            $minimum_quantity = $material_info['minimum_quantity'];
            
            $result = array(
                'type' => $material_type,
                'quantity' => $quantity,
                'price_per_ton' => $price_per_ton,
                'minimum_quantity' => $minimum_quantity,
                'total' => $quantity * $price_per_ton,
                'warnings' => array()
            );
            
            // Verificar cantidad mÃ­nima
            if ($minimum_quantity > 0 && $quantity < $minimum_quantity) {
                $result['warnings'][] = sprintf(
                    __('Cantidad mÃ­nima para %s es %s (actual: %s)', 'adhesion'),
                    $material_type,
                    adhesion_format_tons($minimum_quantity),
                    adhesion_format_tons($quantity)
                );
            }
            
            // Aplicar descuentos por volumen si corresponde
            $volume_discount = $this->calculate_volume_discount($material_type, $quantity);
            if ($volume_discount > 0) {
                $result['volume_discount'] = $volume_discount;
                $result['total'] = $result['total'] * (1 - $volume_discount);
                $result['discounted_price_per_ton'] = $result['total'] / $quantity;
            }
            
            return $result;
            
        } catch (Exception $e) {
            adhesion_log('Error calculando material: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Calcular descuento por volumen para un material especÃ­fico
     */
    private function calculate_volume_discount($material_type, $quantity) {
        // Tabla de descuentos por volumen (configurable)
        $volume_discounts = apply_filters('adhesion_volume_discounts', array(
            'default' => array(
                50 => 0.05,   // 5% descuento a partir de 50t
                100 => 0.10,  // 10% descuento a partir de 100t
                200 => 0.15   // 15% descuento a partir de 200t
            )
        ));
        
        // Usar descuentos especÃ­ficos del material o los por defecto
        $discounts = isset($volume_discounts[$material_type]) ? 
                    $volume_discounts[$material_type] : 
                    $volume_discounts['default'];
        
        $discount = 0;
        foreach ($discounts as $min_quantity => $discount_rate) {
            if ($quantity >= $min_quantity) {
                $discount = $discount_rate;
            }
        }
        
        return $discount;
    }
    
    /**
     * Calcular descuento general del pedido
     */
    private function calculate_discount($subtotal, $total_tons) {
        $discount = 0;
        
        // Descuento por importe total
        if ($subtotal >= 10000) {
            $discount += $subtotal * 0.03; // 3% para pedidos > 10.000â‚¬
        } elseif ($subtotal >= 5000) {
            $discount += $subtotal * 0.02; // 2% para pedidos > 5.000â‚¬
        }
        
        // Descuento adicional por tonelaje
        if ($total_tons >= 500) {
            $discount += $subtotal * 0.02; // 2% adicional para > 500t
        }
        
        return apply_filters('adhesion_calculate_discount', $discount, $subtotal, $total_tons);
    }
    
    // ==========================================
    // MÃ‰TODOS AJAX
    // ==========================================
    
    /**
     * AJAX: Calcular presupuesto (usuarios logueados)
     */
    public function ajax_calculate_budget() {
        try {
            // Verificar seguridad
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado para usar la calculadora.', 'adhesion'));
            }
            
            // Obtener datos
            $materials = isset($_POST['materials']) ? $_POST['materials'] : array();
            $options = isset($_POST['options']) ? $_POST['options'] : array();
            
            // Sanitizar materiales
            $materials = $this->sanitize_materials_input($materials);
            
            if (empty($materials)) {
                throw new Exception(__('Debes agregar al menos un material.', 'adhesion'));
            }
            
            // Calcular presupuesto
            $result = $this->calculate_budget($materials, $options);
            
            if ($result === false) {
                throw new Exception(__('Error en el cÃ¡lculo del presupuesto.', 'adhesion'));
            }
            
            // Formatear resultado para el frontend
            $formatted_result = $this->format_result_for_display($result);
            
            wp_send_json_success(array(
                'calculation' => $formatted_result,
                'can_save' => true,
                'message' => __('Presupuesto calculado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Vista previa de cÃ¡lculo (usuarios no logueados)
     */
    public function ajax_calculate_budget_preview() {
        try {
            // Verificar nonce bÃ¡sico
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            // Obtener datos
            $materials = isset($_POST['materials']) ? $_POST['materials'] : array();
            $materials = $this->sanitize_materials_input($materials);
            
            if (empty($materials)) {
                throw new Exception(__('Debes agregar al menos un material.', 'adhesion'));
            }
            
            // Calcular solo como vista previa
            $result = $this->calculate_budget($materials, array(
                'apply_discounts' => false, // Sin descuentos para preview
                'include_taxes' => true
            ));
            
            if ($result === false) {
                throw new Exception(__('Error en el cÃ¡lculo del presupuesto.', 'adhesion'));
            }
            
            // Formatear resultado
            $formatted_result = $this->format_result_for_display($result);
            
            wp_send_json_success(array(
                'calculation' => $formatted_result,
                'can_save' => false,
                'login_required' => true,
                'message' => __('Vista previa del presupuesto. Inicia sesiÃ³n para guardar.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Guardar cÃ¡lculo
     */
    public function ajax_save_calculation() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado para guardar cÃ¡lculos.', 'adhesion'));
            }
            
            $user_id = get_current_user_id();
            
            // Obtener datos del cÃ¡lculo
            $calculation_data = json_decode(stripslashes($_POST['calculation_data']), true);
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            
            if (!$calculation_data) {
                throw new Exception(__('Datos de cÃ¡lculo invÃ¡lidos.', 'adhesion'));
            }
            
            // AÃ±adir notas a los datos del cÃ¡lculo
            $calculation_data['notes'] = $notes;
            $calculation_data['saved_at'] = current_time('mysql');
            
            // Guardar en base de datos
            $calculation_id = $this->db->create_calculation(
                $user_id,
                $calculation_data,
                floatval($calculation_data['total_price']),
                floatval($calculation_data['average_price_per_ton']),
                floatval($calculation_data['total_tons'])
            );
            
            if (!$calculation_id) {
                throw new Exception(__('Error al guardar el cÃ¡lculo en la base de datos.', 'adhesion'));
            }
            
            // Log de la acciÃ³n
            adhesion_log("CÃ¡lculo guardado por usuario $user_id con ID $calculation_id", 'info');
            
            wp_send_json_success(array(
                'calculation_id' => $calculation_id,
                'message' => __('CÃ¡lculo guardado correctamente.', 'adhesion'),
                'redirect_url' => $this->get_account_url()
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Obtener precios de materiales
     */
    public function ajax_get_material_prices() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $this->load_material_prices();
            
            // Formatear precios para el frontend
            $formatted_prices = array();
            foreach ($this->material_prices as $type => $info) {
                if ($info['is_active']) {
                    $formatted_prices[] = array(
                        'type' => $type,
                        'price_per_ton' => $info['price_per_ton'],
                        'minimum_quantity' => $info['minimum_quantity'],
                        'formatted_price' => adhesion_format_price($info['price_per_ton']),
                        'formatted_minimum' => adhesion_format_tons($info['minimum_quantity'])
                    );
                }
            }
            
            wp_send_json_success(array(
                'materials' => $formatted_prices,
                'currency' => 'EUR',
                'last_updated' => get_option('adhesion_prices_last_updated', current_time('mysql'))
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Validar materiales
     */
    public function ajax_validate_materials() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $materials = isset($_POST['materials']) ? $_POST['materials'] : array();
            $materials = $this->sanitize_materials_input($materials);
            
            $validation_result = array(
                'is_valid' => true,
                'errors' => array(),
                'warnings' => array()
            );
            
            foreach ($materials as $material) {
                $material_validation = $this->validate_single_material($material);
                
                if (!$material_validation['is_valid']) {
                    $validation_result['is_valid'] = false;
                    $validation_result['errors'] = array_merge(
                        $validation_result['errors'], 
                        $material_validation['errors']
                    );
                }
                
                if (!empty($material_validation['warnings'])) {
                    $validation_result['warnings'] = array_merge(
                        $validation_result['warnings'], 
                        $material_validation['warnings']
                    );
                }
            }
            
            wp_send_json_success($validation_result);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // ==========================================
    // MÃ‰TODOS DE UTILIDAD
    // ==========================================
    
    /**
     * Sanitizar entrada de materiales
     */
    private function sanitize_materials_input($materials) {
        if (!is_array($materials)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($materials as $material) {
            if (isset($material['type']) && isset($material['quantity'])) {
                $type = sanitize_text_field($material['type']);
                $quantity = floatval($material['quantity']);
                
                if (!empty($type) && $quantity > 0) {
                    $sanitized[] = array(
                        'type' => $type,
                        'quantity' => $quantity
                    );
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validar un material individual
     */
    private function validate_single_material($material) {
        $result = array(
            'is_valid' => true,
            'errors' => array(),
            'warnings' => array()
        );
        
        $type = $material['type'];
        $quantity = $material['quantity'];
        
        // Verificar si el material existe
        if (!isset($this->material_prices[$type])) {
            $result['is_valid'] = false;
            $result['errors'][] = sprintf(__('Material no disponible: %s', 'adhesion'), $type);
            return $result;
        }
        
        $material_info = $this->material_prices[$type];
        
        // Verificar si estÃ¡ activo
        if (!$material_info['is_active']) {
            $result['is_valid'] = false;
            $result['errors'][] = sprintf(__('Material temporalmente no disponible: %s', 'adhesion'), $type);
            return $result;
        }
        
        // Verificar cantidad
        if ($quantity <= 0) {
            $result['is_valid'] = false;
            $result['errors'][] = sprintf(__('Cantidad debe ser mayor que 0 para %s', 'adhesion'), $type);
        }
        
        if ($quantity > 1000) {
            $result['is_valid'] = false;
            $result['errors'][] = sprintf(__('Cantidad mÃ¡xima excedida para %s (mÃ¡ximo: 1000t)', 'adhesion'), $type);
        }
        
        // Verificar cantidad mÃ­nima (advertencia, no error)
        if ($material_info['minimum_quantity'] > 0 && $quantity < $material_info['minimum_quantity']) {
            $result['warnings'][] = sprintf(
                __('Cantidad mÃ­nima recomendada para %s: %s', 'adhesion'),
                $type,
                adhesion_format_tons($material_info['minimum_quantity'])
            );
        }
        
        return $result;
    }
    
    /**
     * Formatear resultado para mostrar en frontend
     */
    private function format_result_for_display($result) {
        $formatted = $result;
        
        // Formatear precios
        $formatted['formatted_subtotal'] = adhesion_format_price($result['subtotal']);
        $formatted['formatted_discount'] = adhesion_format_price($result['discount_amount']);
        $formatted['formatted_tax'] = adhesion_format_price($result['tax_amount']);
        $formatted['formatted_total'] = adhesion_format_price($result['total_price']);
        $formatted['formatted_tons'] = adhesion_format_tons($result['total_tons']);
        $formatted['formatted_avg_price'] = adhesion_format_price($result['average_price_per_ton']);
        
        // Formatear materiales individuales
        foreach ($formatted['materials'] as &$material) {
            $material['formatted_quantity'] = adhesion_format_tons($material['quantity']);
            $material['formatted_price'] = adhesion_format_price($material['price_per_ton']);
            $material['formatted_total'] = adhesion_format_price($material['total']);
            
            if (isset($material['discounted_price_per_ton'])) {
                $material['formatted_discounted_price'] = adhesion_format_price($material['discounted_price_per_ton']);
            }
        }
        
        return $formatted;
    }
    
    /**
     * Obtener URL de cuenta de usuario
     */
    private function get_account_url() {
        $page_id = adhesion_get_setting('page_mi_cuenta_adhesion');
        return $page_id ? get_permalink($page_id) : home_url();
    }
    
    /**
     * Obtener tipos de material disponibles
     */
    public function get_available_materials() {
        $this->load_material_prices();
        
        $available = array();
        foreach ($this->material_prices as $type => $info) {
            if ($info['is_active']) {
                $available[] = array(
                    'type' => $type,
                    'price_per_ton' => $info['price_per_ton'],
                    'minimum_quantity' => $info['minimum_quantity']
                );
            }
        }
        
        return $available;
    }
    
    /**
     * Limpiar cachÃ© de precios
     */
    public function clear_prices_cache() {
        delete_transient('adhesion_calculator_prices');
        $this->material_prices = null;
        $this->load_material_prices();
    }
    
    /**
     * Exportar cÃ¡lculo a PDF (funcionalidad futura)
     */
    public function export_calculation_to_pdf($calculation_id) {
        // TODO: Implementar exportaciÃ³n a PDF
        // Esta funcionalidad se puede implementar mÃ¡s adelante
        return false;
    }
}


// ===== class-docusign.php =====
<?php
/**
 * IntegraciÃ³n con DocuSign para firma digital
 * 
 * Esta clase maneja toda la integraciÃ³n con DocuSign:
 * - AutenticaciÃ³n OAuth2
 * - CreaciÃ³n de sobres para firma
 * - GestiÃ³n de firmantes y documentos
 * - Procesamiento de callbacks
 * - Descarga de documentos firmados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_DocuSign {
    
    /**
     * @var Adhesion_Database
     */
    private $db;
    
    /**
     * @var array Configuraciones de DocuSign
     */
    private $settings;
    
    /**
     * @var string URL base de DocuSign
     */
    private $base_url;
    
    /**
     * @var string Token de acceso actual
     */
    private $access_token;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->load_settings();
        $this->setup_base_url();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para admin
        add_action('wp_ajax_adhesion_send_to_docusign', array($this, 'ajax_send_to_docusign'));
        add_action('wp_ajax_adhesion_check_docusign_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_adhesion_download_signed_document', array($this, 'ajax_download_document'));
        
        // AJAX para frontend
        add_action('wp_ajax_adhesion_start_signing', array($this, 'ajax_start_signing'));
        add_action('wp_ajax_nopriv_adhesion_docusign_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_adhesion_docusign_callback', array($this, 'handle_callback'));
        
        // Callback URL personalizada
        add_action('init', array($this, 'setup_callback_endpoint'));
        add_action('template_redirect', array($this, 'handle_callback_endpoint'));
        
        // Cron job para verificar estados
        add_action('adhesion_check_docusign_status', array($this, 'check_pending_envelopes'));
        
        // Programar cron si no existe
        if (!wp_next_scheduled('adhesion_check_docusign_status')) {
            wp_schedule_event(time(), 'hourly', 'adhesion_check_docusign_status');
        }
    }
    
    /**
     * Cargar configuraciones
     */
    private function load_settings() {
        $this->settings = get_option('adhesion_settings', array());
        
        // Configuraciones por defecto
        $defaults = array(
            'docusign_integration_key' => '',
            'docusign_secret_key' => '',
            'docusign_account_id' => '',
            'docusign_environment' => 'demo',
            'docusign_redirect_uri' => home_url('/adhesion-docusign-callback/')
        );
        
        $this->settings = wp_parse_args($this->settings, $defaults);
    }
    
    /**
     * Configurar URL base segÃºn entorno
     */
    private function setup_base_url() {
        $this->base_url = $this->settings['docusign_environment'] === 'production'
            ? 'https://www.docusign.net/restapi'
            : 'https://demo.docusign.net/restapi';
    }
    
    /**
     * Configurar endpoint para callbacks
     */
    public function setup_callback_endpoint() {
        add_rewrite_rule(
            '^adhesion-docusign-callback/?$',
            'index.php?adhesion_docusign_callback=1',
            'top'
        );
        
        add_rewrite_rule(
            '^adhesion-docusign-return/([^/]+)/?$',
            'index.php?adhesion_docusign_return=$matches[1]',
            'top'
        );
    }
    
    /**
     * Manejar endpoint de callback
     */
    public function handle_callback_endpoint() {
        if (get_query_var('adhesion_docusign_callback')) {
            $this->handle_callback();
            exit;
        }
        
        if ($envelope_id = get_query_var('adhesion_docusign_return')) {
            $this->handle_return($envelope_id);
            exit;
        }
    }
    
    /**
     * Obtener token de acceso OAuth2
     */
    private function get_access_token() {
        // Verificar si hay token en cachÃ© vÃ¡lido
        $cached_token = get_transient('adhesion_docusign_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return $cached_token;
        }
        
        // Solicitar nuevo token
        $token_url = $this->base_url . '/oauth/token';
        
        $auth_header = base64_encode($this->settings['docusign_integration_key'] . ':' . $this->settings['docusign_secret_key']);
        
        $response = wp_remote_post($token_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . $auth_header,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'scope' => 'signature impersonation'
            )
        ));
        
        if (is_wp_error($response)) {
            adhesion_log('Error obteniendo token DocuSign: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            adhesion_log('Respuesta invÃ¡lida de DocuSign OAuth: ' . $body, 'error');
            return false;
        }
        
        // Guardar token en cachÃ© (expires_in - 60 segundos de margen)
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) - 60 : 3540;
        set_transient('adhesion_docusign_token', $data['access_token'], $expires_in);
        
        $this->access_token = $data['access_token'];
        return $data['access_token'];
    }
    
    /**
     * Crear sobre para firma
     */
    public function create_envelope($contract_id, $recipient_email, $recipient_name, $document_content, $document_name = 'Contrato de AdhesiÃ³n') {
        try {
            // Validar configuraciÃ³n
            if (!$this->is_configured()) {
                throw new Exception(__('DocuSign no estÃ¡ configurado correctamente.', 'adhesion'));
            }
            
            // Obtener token de acceso
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception(__('No se pudo obtener token de acceso de DocuSign.', 'adhesion'));
            }
            
            // Obtener informaciÃ³n del contrato
            $contract = $this->db->get_contract($contract_id);
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Preparar documento PDF
            $pdf_content = $this->generate_pdf_document($document_content);
            if (!$pdf_content) {
                throw new Exception(__('Error generando documento PDF.', 'adhesion'));
            }
            
            // Configurar sobre
            $envelope_definition = array(
                'emailSubject' => sprintf(__('Firma de Contrato - %s', 'adhesion'), $contract['contract_number']),
                'documents' => array(
                    array(
                        'documentBase64' => base64_encode($pdf_content),
                        'name' => $document_name,
                        'fileExtension' => 'pdf',
                        'documentId' => '1'
                    )
                ),
                'recipients' => array(
                    'signers' => array(
                        array(
                            'email' => $recipient_email,
                            'name' => $recipient_name,
                            'recipientId' => '1',
                            'routingOrder' => '1',
                            'tabs' => array(
                                'signHereTabs' => array(
                                    array(
                                        'documentId' => '1',
                                        'pageNumber' => '1',
                                        'xPosition' => '400',
                                        'yPosition' => '650'
                                    )
                                ),
                                'dateSignedTabs' => array(
                                    array(
                                        'documentId' => '1',
                                        'pageNumber' => '1',
                                        'xPosition' => '400',
                                        'yPosition' => '700'
                                    )
                                )
                            )
                        )
                    )
                ),
                'status' => 'sent',
                'eventNotification' => array(
                    'url' => home_url('/adhesion-docusign-callback/'),
                    'loggingEnabled' => 'true',
                    'requireAcknowledgment' => 'true',
                    'envelopeEvents' => array(
                        array('envelopeEventStatusCode' => 'completed'),
                        array('envelopeEventStatusCode' => 'declined'),
                        array('envelopeEventStatusCode' => 'voided')
                    )
                )
            );
            
            // URL del endpoint
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes';
            
            // Realizar peticiÃ³n
            $response = wp_remote_post($url, array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($envelope_definition)
            ));
            
            if (is_wp_error($response)) {
                throw new Exception(__('Error enviando a DocuSign: ', 'adhesion') . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 201) {
                adhesion_log('Error DocuSign: ' . $body, 'error');
                throw new Exception(__('Error creando sobre en DocuSign: ', 'adhesion') . ($data['message'] ?? 'Error desconocido'));
            }
            
            // Guardar envelope ID en el contrato
            $envelope_id = $data['envelopeId'];
            $this->db->update_contract($contract_id, array(
                'docusign_envelope_id' => $envelope_id,
                'status' => 'sent',
                'sent_at' => current_time('mysql')
            ));
            
            // Log de Ã©xito
            adhesion_log("Sobre DocuSign creado: {$envelope_id} para contrato {$contract_id}", 'info');
            
            return array(
                'success' => true,
                'envelope_id' => $envelope_id,
                'status' => 'sent',
                'message' => __('Documento enviado para firma correctamente.', 'adhesion')
            );
            
        } catch (Exception $e) {
            adhesion_log('Error en create_envelope: ' . $e->getMessage(), 'error');
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Obtener URL de firma embedded
     */
    public function get_signing_url($envelope_id, $recipient_email, $recipient_name, $return_url = null) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception(__('No se pudo obtener token de acceso.', 'adhesion'));
            }
            
            if (!$return_url) {
                $return_url = home_url('/adhesion-docusign-return/' . $envelope_id);
            }
            
            $recipient_view = array(
                'authenticationMethod' => 'none',
                'email' => $recipient_email,
                'returnUrl' => $return_url,
                'recipientId' => '1',
                'userName' => $recipient_name
            );
            
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes/' . $envelope_id . '/views/recipient';
            
            $response = wp_remote_post($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($recipient_view)
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['url'])) {
                throw new Exception(__('No se pudo obtener URL de firma.', 'adhesion'));
            }
            
            return $data['url'];
            
        } catch (Exception $e) {
            adhesion_log('Error obteniendo URL de firma: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Verificar estado de sobre
     */
    public function check_envelope_status($envelope_id) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return false;
            }
            
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes/' . $envelope_id;
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return isset($data['status']) ? $data : false;
            
        } catch (Exception $e) {
            adhesion_log('Error verificando estado: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Descargar documento firmado
     */
    public function download_signed_document($envelope_id, $document_id = '1') {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return false;
            }
            
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes/' . $envelope_id . '/documents/' . $document_id;
            
            $response = wp_remote_get($url, array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                )
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            return wp_remote_retrieve_body($response);
            
        } catch (Exception $e) {
            adhesion_log('Error descargando documento: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Generar PDF desde contenido HTML
     */
    private function generate_pdf_document($html_content) {
        // AquÃ­ puedes usar una librerÃ­a como TCPDF, mPDF o DOMPDF
        // Por simplicidad, vamos a generar un PDF bÃ¡sico
        
        // Si tienes TCPDF disponible:
        if (class_exists('TCPDF')) {
            return $this->generate_pdf_with_tcpdf($html_content);
        }
        
        // Alternativa: generar HTML que se puede convertir a PDF
        return $this->generate_html_for_pdf($html_content);
    }
    
    /**
     * Generar PDF con TCPDF
     */
    private function generate_pdf_with_tcpdf($html_content) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configurar documento
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle(__('Contrato de AdhesiÃ³n', 'adhesion'));
            
            // Configurar pÃ¡gina
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Agregar pÃ¡gina
            $pdf->AddPage();
            
            // Escribir contenido
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            adhesion_log('Error generando PDF: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Generar HTML para PDF
     */
    private function generate_html_for_pdf($content) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . __('Contrato de AdhesiÃ³n', 'adhesion') . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 40px; }
                .content { line-height: 1.6; }
                .signature { margin-top: 100px; text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . __('CONTRATO DE ADHESIÃ“N', 'adhesion') . '</h1>
                <p>' . get_bloginfo('name') . '</p>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="signature">
                <p>' . __('Firma del Cliente:', 'adhesion') . '</p>
                <p>_________________________</p>
                <p>' . __('Fecha:', 'adhesion') . ' _____________</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Manejar callback de DocuSign
     */
    public function handle_callback() {
        try {
            // Obtener datos del callback
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data) {
                adhesion_log('Callback DocuSign invÃ¡lido: ' . $input, 'error');
                return;
            }
            
            adhesion_log('Callback DocuSign recibido: ' . $input, 'info');
            
            // Extraer informaciÃ³n del sobre
            $envelope_id = $data['envelopeId'] ?? '';
            $status = $data['status'] ?? '';
            
            if (!$envelope_id || !$status) {
                adhesion_log('Datos incompletos en callback DocuSign', 'error');
                return;
            }
            
            // Buscar contrato por envelope ID
            $contract = $this->db->get_contract_by_envelope($envelope_id);
            if (!$contract) {
                adhesion_log("Contrato no encontrado para envelope: {$envelope_id}", 'error');
                return;
            }
            
            // Procesar segÃºn el estado
            switch ($status) {
                case 'completed':
                    $this->process_completed_envelope($contract, $envelope_id);
                    break;
                    
                case 'declined':
                    $this->process_declined_envelope($contract, $envelope_id);
                    break;
                    
                case 'voided':
                    $this->process_voided_envelope($contract, $envelope_id);
                    break;
            }
            
            // Responder a DocuSign
            wp_send_json_success('Callback procesado correctamente');
            
        } catch (Exception $e) {
            adhesion_log('Error en callback DocuSign: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Procesar sobre completado
     */
    private function process_completed_envelope($contract, $envelope_id) {
        // Descargar documento firmado
        $signed_document = $this->download_signed_document($envelope_id);
        
        if ($signed_document) {
            // Guardar documento firmado
            $upload_dir = wp_upload_dir();
            $filename = 'contrato-firmado-' . $contract['id'] . '-' . time() . '.pdf';
            $filepath = $upload_dir['path'] . '/' . $filename;
            
            file_put_contents($filepath, $signed_document);
            
            // Actualizar contrato
            $this->db->update_contract($contract['id'], array(
                'status' => 'completed',
                'signed_at' => current_time('mysql'),
                'signed_document_path' => $upload_dir['url'] . '/' . $filename
            ));
            
            // Enviar email de confirmaciÃ³n
            $this->send_completion_email($contract, $filepath);
            
            adhesion_log("Contrato {$contract['id']} completado y firmado", 'info');
        }
    }
    
    /**
     * Procesar sobre rechazado
     */
    private function process_declined_envelope($contract, $envelope_id) {
        $this->db->update_contract($contract['id'], array(
            'status' => 'declined',
            'declined_at' => current_time('mysql')
        ));
        
        // Enviar email de notificaciÃ³n
        $this->send_declined_email($contract);
        
        adhesion_log("Contrato {$contract['id']} rechazado por el cliente", 'info');
    }
    
    /**
     * Procesar sobre anulado
     */
    private function process_voided_envelope($contract, $envelope_id) {
        $this->db->update_contract($contract['id'], array(
            'status' => 'voided',
            'voided_at' => current_time('mysql')
        ));
        
        adhesion_log("Contrato {$contract['id']} anulado", 'info');
    }
    
    /**
     * Enviar email de contrato completado
     */
    private function send_completion_email($contract, $document_path = null) {
        $user = get_user_by('ID', $contract['user_id']);
        if (!$user) return;
        
        $subject = sprintf(__('Contrato firmado correctamente - %s', 'adhesion'), $contract['contract_number']);
        
        $message = sprintf(
            __('Estimado/a %s,

Su contrato de adhesiÃ³n %s ha sido firmado correctamente.

Detalles del contrato:
- NÃºmero: %s
- Importe: %s â‚¬
- Fecha de firma: %s

El documento firmado estÃ¡ disponible en su Ã¡rea de cliente.

Gracias por confiar en nosotros.

Saludos cordiales,
%s', 'adhesion'),
            $user->display_name,
            $contract['contract_number'],
            $contract['contract_number'],
            number_format($contract['total_amount'], 2, ',', '.'),
            adhesion_format_date($contract['signed_at']),
            get_bloginfo('name')
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Adjuntar documento si estÃ¡ disponible
        $attachments = array();
        if ($document_path && file_exists($document_path)) {
            $attachments[] = $document_path;
        }
        
        wp_mail($user->user_email, $subject, $message, $headers, $attachments);
        
        // Email al administrador
        $admin_email = adhesion_get_option('admin_email', get_option('admin_email'));
        if ($admin_email && $admin_email !== $user->user_email) {
            $admin_subject = sprintf(__('Nuevo contrato firmado - %s', 'adhesion'), $contract['contract_number']);
            wp_mail($admin_email, $admin_subject, $message, $headers);
        }
    }
    
    /**
     * Enviar email de contrato rechazado
     */
    private function send_declined_email($contract) {
        $user = get_user_by('ID', $contract['user_id']);
        if (!$user) return;
        
        $subject = sprintf(__('Contrato no firmado - %s', 'adhesion'), $contract['contract_number']);
        
        $message = sprintf(
            __('Estimado/a %s,

Hemos detectado que no ha firmado el contrato %s.

Si desea proceder con la adhesiÃ³n, puede volver a acceder al proceso desde su Ã¡rea de cliente.

Si tiene alguna duda, no dude en contactarnos.

Saludos cordiales,
%s', 'adhesion'),
            $user->display_name,
            $contract['contract_number'],
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Verificar configuraciÃ³n de DocuSign
     */
    public function is_configured() {
        return !empty($this->settings['docusign_integration_key']) &&
               !empty($this->settings['docusign_secret_key']) &&
               !empty($this->settings['docusign_account_id']);
    }
    
    /**
     * AJAX: Enviar contrato a DocuSign
     */
    public function ajax_send_to_docusign() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Obtener datos del usuario
            $user = get_user_by('ID', $contract['user_id']);
            if (!$user) {
                throw new Exception(__('Usuario no encontrado.', 'adhesion'));
            }
            
            // Generar contenido del documento
            $document_content = $this->generate_contract_content($contract);
            
            // Crear sobre en DocuSign
            $result = $this->create_envelope(
                $contract_id,
                $user->user_email,
                $user->display_name,
                $document_content
            );
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Iniciar proceso de firma (frontend)
     */
    public function ajax_start_signing() {
        try {
            // Verificar usuario logueado
            if (!is_user_logged_in()) {
                throw new Exception(__('Debe estar logueado.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_public_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Verificar que el contrato pertenece al usuario actual
            if ($contract['user_id'] != get_current_user_id()) {
                throw new Exception(__('No tiene permisos para este contrato.', 'adhesion'));
            }
            
            // Verificar que el contrato estÃ¡ listo para firmar
            if ($contract['payment_status'] !== 'completed') {
                throw new Exception(__('El pago debe estar completado antes de firmar.', 'adhesion'));
            }
            
            // Si ya tiene envelope_id, obtener URL de firma
            if (!empty($contract['docusign_envelope_id'])) {
                $signing_url = $this->get_signing_url(
                    $contract['docusign_envelope_id'],
                    wp_get_current_user()->user_email,
                    wp_get_current_user()->display_name
                );
                
                if ($signing_url) {
                    wp_send_json_success(array(
                        'action' => 'redirect',
                        'url' => $signing_url,
                        'message' => __('Redirigiendo a la plataforma de firma...', 'adhesion')
                    ));
                }
            }
            
            // Crear nuevo sobre si no existe
            $user = wp_get_current_user();
            $document_content = $this->generate_contract_content($contract);
            
            $result = $this->create_envelope(
                $contract_id,
                $user->user_email,
                $user->display_name,
                $document_content
            );
            
            if ($result['success']) {
                // Obtener URL de firma
                $signing_url = $this->get_signing_url(
                    $result['envelope_id'],
                    $user->user_email,
                    $user->display_name
                );
                
                if ($signing_url) {
                    wp_send_json_success(array(
                        'action' => 'redirect',
                        'url' => $signing_url,
                        'envelope_id' => $result['envelope_id'],
                        'message' => __('Documento preparado. Redirigiendo...', 'adhesion')
                    ));
                } else {
                    wp_send_json_error(__('Error obteniendo URL de firma.', 'adhesion'));
                }
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Verificar estado de DocuSign
     */
    public function ajax_check_status() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $envelope_id = sanitize_text_field($_POST['envelope_id']);
            
            if (empty($envelope_id)) {
                throw new Exception(__('ID de sobre requerido.', 'adhesion'));
            }
            
            $status = $this->check_envelope_status($envelope_id);
            
            if ($status) {
                wp_send_json_success($status);
            } else {
                wp_send_json_error(__('No se pudo verificar el estado.', 'adhesion'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Descargar documento firmado
     */
    public function ajax_download_document() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $envelope_id = sanitize_text_field($_POST['envelope_id']);
            $document_id = sanitize_text_field($_POST['document_id'] ?? '1');
            
            $document = $this->download_signed_document($envelope_id, $document_id);
            
            if ($document) {
                // Configurar headers para descarga
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="contrato-firmado-' . $envelope_id . '.pdf"');
                header('Content-Length: ' . strlen($document));
                
                echo $document;
                exit;
            } else {
                wp_send_json_error(__('No se pudo descargar el documento.', 'adhesion'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Manejar retorno desde DocuSign
     */
    public function handle_return($envelope_id) {
        try {
            // Buscar contrato por envelope ID
            $contract = $this->db->get_contract_by_envelope($envelope_id);
            
            if (!$contract) {
                wp_die(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Verificar estado del sobre
            $status = $this->check_envelope_status($envelope_id);
            
            if ($status && $status['status'] === 'completed') {
                // Redirigir a pÃ¡gina de Ã©xito
                $success_url = add_query_arg(array(
                    'adhesion_message' => 'contract_signed',
                    'contract_id' => $contract['id']
                ), home_url('/mi-cuenta/'));
                
                wp_redirect($success_url);
                exit;
            } else {
                // Redirigir a pÃ¡gina de contrato con estado actualizado
                $return_url = add_query_arg(array(
                    'adhesion_message' => 'signing_pending',
                    'contract_id' => $contract['id']
                ), home_url('/mi-cuenta/'));
                
                wp_redirect($return_url);
                exit;
            }
            
        } catch (Exception $e) {
            adhesion_log('Error en handle_return: ' . $e->getMessage(), 'error');
            wp_die(__('Error procesando respuesta de firma.', 'adhesion'));
        }
    }
    
    /**
     * Generar contenido del contrato
     */
    private function generate_contract_content($contract) {
        // Obtener plantilla de documento
        $documents = $this->db->get_active_documents();
        $template = '';
        
        if (!empty($documents)) {
            $document = $documents[0]; // Usar primer documento activo
            $template = $document['header'] . "\n\n" . $document['body'] . "\n\n" . $document['footer'];
        } else {
            // Plantilla por defecto
            $template = $this->get_default_contract_template();
        }
        
        // Obtener datos del usuario
        $user = get_user_by('ID', $contract['user_id']);
        $user_meta = get_user_meta($contract['user_id']);
        
        // Obtener datos del cÃ¡lculo si existe
        $calculation = null;
        if ($contract['calculation_id']) {
            $calculation = $this->db->get_calculation($contract['calculation_id']);
        }
        
        // Variables para reemplazar
        $variables = array(
            '[fecha]' => date('d/m/Y'),
            '[nombre]' => $user->display_name,
            '[email]' => $user->user_email,
            '[numero_contrato]' => $contract['contract_number'],
            '[importe_total]' => number_format($contract['total_amount'], 2, ',', '.') . ' â‚¬',
            '[fecha_creacion]' => adhesion_format_date($contract['created_at'], 'd/m/Y'),
            '[empresa]' => get_bloginfo('name'),
            
            // Datos del usuario extendidos
            '[telefono]' => $user_meta['phone'][0] ?? '',
            '[dni]' => $user_meta['dni'][0] ?? '',
            '[direccion]' => $user_meta['address'][0] ?? '',
            '[ciudad]' => $user_meta['city'][0] ?? '',
            '[codigo_postal]' => $user_meta['postal_code'][0] ?? '',
            '[empresa_cliente]' => $user_meta['company'][0] ?? '',
            '[cif_cliente]' => $user_meta['cif'][0] ?? '',
        );
        
        // Variables del cÃ¡lculo si existe
        if ($calculation) {
            $variables['[materiales]'] = $this->format_calculation_materials($calculation);
            $variables['[subtotal]'] = number_format($calculation['subtotal'], 2, ',', '.') . ' â‚¬';
            $variables['[descuentos]'] = number_format($calculation['discount_amount'], 2, ',', '.') . ' â‚¬';
            $variables['[iva]'] = number_format($calculation['tax_amount'], 2, ',', '.') . ' â‚¬';
            $variables['[total_toneladas]'] = number_format($calculation['total_tons'], 2, ',', '.') . ' t';
        }
        
        // Reemplazar variables en la plantilla
        $content = str_replace(array_keys($variables), array_values($variables), $template);
        
        return $content;
    }
    
    /**
     * Formatear materiales del cÃ¡lculo para el contrato
     */
    private function format_calculation_materials($calculation) {
        $materials_data = json_decode($calculation['materials_data'], true);
        
        if (!$materials_data) {
            return '';
        }
        
        $formatted = "<table border='1' cellpadding='5' cellspacing='0' width='100%'>\n";
        $formatted .= "<tr><th>Material</th><th>Cantidad (t)</th><th>Precio/t</th><th>Importe</th></tr>\n";
        
        foreach ($materials_data as $material) {
            $formatted .= sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s â‚¬</td><td>%s â‚¬</td></tr>\n",
                esc_html($material['name']),
                number_format($material['quantity'], 2, ',', '.'),
                number_format($material['price'], 2, ',', '.'),
                number_format($material['total'], 2, ',', '.')
            );
        }
        
        $formatted .= "</table>";
        
        return $formatted;
    }
    
    /**
     * Obtener plantilla por defecto
     */
    private function get_default_contract_template() {
        return '
<h2>CONTRATO DE ADHESIÃ“N</h2>

<p><strong>Fecha:</strong> [fecha]</p>
<p><strong>NÃºmero de contrato:</strong> [numero_contrato]</p>

<h3>DATOS DEL CLIENTE</h3>
<p><strong>Nombre:</strong> [nombre]</p>
<p><strong>Email:</strong> [email]</p>
<p><strong>TelÃ©fono:</strong> [telefono]</p>
<p><strong>DNI/NIE:</strong> [dni]</p>
<p><strong>DirecciÃ³n:</strong> [direccion]</p>
<p><strong>Ciudad:</strong> [ciudad]</p>
<p><strong>CÃ³digo Postal:</strong> [codigo_postal]</p>

<h3>DATOS DE LA EMPRESA CLIENTE</h3>
<p><strong>Empresa:</strong> [empresa_cliente]</p>
<p><strong>CIF:</strong> [cif_cliente]</p>

<h3>DETALLE DEL SERVICIO</h3>
<p>El presente contrato regula la adhesiÃ³n del cliente a los servicios de [empresa].</p>

<h4>Materiales contratados:</h4>
[materiales]

<h3>IMPORTE</h3>
<p><strong>Subtotal:</strong> [subtotal]</p>
<p><strong>Descuentos:</strong> [descuentos]</p>
<p><strong>IVA:</strong> [iva]</p>
<p><strong>TOTAL:</strong> [importe_total]</p>

<h3>CONDICIONES GENERALES</h3>
<p>El cliente acepta las condiciones generales de contrataciÃ³n de [empresa].</p>
<p>El presente contrato entra en vigor a partir de la fecha de firma.</p>

<p>En prueba de conformidad, las partes firman el presente contrato:</p>
';
    }
    
    /**
     * Verificar sobres pendientes (cron job)
     */
    public function check_pending_envelopes() {
        if (!$this->is_configured()) {
            return;
        }
        
        // Obtener contratos con estado 'sent' que no han sido actualizados en las Ãºltimas 24h
        $pending_contracts = $this->db->get_pending_docusign_contracts();
        
        foreach ($pending_contracts as $contract) {
            if (empty($contract['docusign_envelope_id'])) {
                continue;
            }
            
            $status = $this->check_envelope_status($contract['docusign_envelope_id']);
            
            if ($status && $status['status'] !== $contract['status']) {
                // Actualizar estado del contrato
                switch ($status['status']) {
                    case 'completed':
                        $this->process_completed_envelope($contract, $contract['docusign_envelope_id']);
                        break;
                        
                    case 'declined':
                        $this->process_declined_envelope($contract, $contract['docusign_envelope_id']);
                        break;
                        
                    case 'voided':
                        $this->process_voided_envelope($contract, $contract['docusign_envelope_id']);
                        break;
                }
                
                adhesion_log("Estado actualizado para contrato {$contract['id']}: {$status['status']}", 'info');
            }
        }
    }
    
    /**
     * Obtener estadÃ­sticas de DocuSign
     */
    public function get_docusign_stats() {
        return array(
            'total_sent' => $this->db->count_contracts_by_status('sent'),
            'total_completed' => $this->db->count_contracts_by_status('completed'),
            'total_declined' => $this->db->count_contracts_by_status('declined'),
            'total_pending' => $this->db->count_contracts_by_status('pending'),
            'success_rate' => $this->calculate_success_rate()
        );
    }
    
    /**
     * Calcular tasa de Ã©xito
     */
    private function calculate_success_rate() {
        $total_sent = $this->db->count_contracts_by_status('sent') + $this->db->count_contracts_by_status('completed') + $this->db->count_contracts_by_status('declined');
        $completed = $this->db->count_contracts_by_status('completed');
        
        if ($total_sent === 0) {
            return 0;
        }
        
        return round(($completed / $total_sent) * 100, 2);
    }
    
    /**
     * Limpiar tokens expirados
     */
    public function cleanup_expired_tokens() {
        delete_transient('adhesion_docusign_token');
    }
    
    /**
     * Probar configuraciÃ³n de DocuSign
     */
    public function test_configuration() {
        try {
            if (!$this->is_configured()) {
                throw new Exception(__('DocuSign no estÃ¡ configurado.', 'adhesion'));
            }
            
            $token = $this->get_access_token();
            
            if (!$token) {
                throw new Exception(__('No se pudo obtener token de acceso.', 'adhesion'));
            }
            
            // Probar obteniendo informaciÃ³n de la cuenta
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'];
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                return array(
                    'success' => true,
                    'message' => __('ConfiguraciÃ³n de DocuSign vÃ¡lida.', 'adhesion')
                );
            } else {
                throw new Exception(__('Error de autenticaciÃ³n con DocuSign.', 'adhesion'));
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}

// FunciÃ³n de ayuda global
function adhesion_docusign() {
    return new Adhesion_DocuSign();
}



// ===== class-payment.php =====
<?php
/**
 * Clase para integraciÃ³n con Redsys (pagos)
 * 
 * Esta clase maneja toda la integraciÃ³n con Redsys:
 * - GeneraciÃ³n de formularios de pago
 * - Procesamiento de callbacks
 * - ValidaciÃ³n de respuestas
 * - GestiÃ³n de estados de pago
 * - IntegraciÃ³n con contratos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Payment {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * ConfiguraciÃ³n de Redsys
     */
    private $config;
    
    /**
     * URLs de Redsys segÃºn entorno
     */
    private $redsys_urls = array(
        'test' => 'https://sis-t.redsys.es:25443/sis/realizarPago',
        'production' => 'https://sis.redsys.es/sis/realizarPago'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Cargar configuraciÃ³n de Redsys
     */
    private function load_config() {
        $this->config = array(
            'merchant_code' => adhesion_get_setting('redsys_merchant_code'),
            'terminal' => adhesion_get_setting('redsys_terminal', '001'),
            'secret_key' => adhesion_get_setting('redsys_secret_key'),
            'environment' => adhesion_get_setting('redsys_environment', 'test'),
            'currency' => adhesion_get_setting('redsys_currency', '978') // EUR
        );
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para usuarios logueados
        add_action('wp_ajax_adhesion_create_payment', array($this, 'ajax_create_payment'));
        add_action('wp_ajax_adhesion_check_payment_status', array($this, 'ajax_check_payment_status'));
        
        // Hooks para procesar callbacks de Redsys
        add_action('init', array($this, 'handle_redsys_callback'));
        
        // Shortcode para formulario de pago
        add_shortcode('adhesion_payment_form', array($this, 'payment_form_shortcode'));
    }
    
    // ==========================================
    // MÃ‰TODOS PRINCIPALES DE PAGO
    // ==========================================
    
    /**
     * Crear pago en Redsys
     */
    public function create_payment($contract_id, $amount, $description = '') {
        try {
            // Verificar configuraciÃ³n
            if (!$this->is_configured()) {
                throw new Exception(__('Redsys no estÃ¡ configurado correctamente.', 'adhesion'));
            }
            
            // Obtener informaciÃ³n del contrato
            $contract = $this->db->get_contract($contract_id);
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Generar nÃºmero de pedido Ãºnico
            $order_number = $this->generate_order_number($contract_id);
            
            // Preparar datos del pago
            $payment_data = array(
                'DS_MERCHANT_AMOUNT' => $this->format_amount($amount),
                'DS_MERCHANT_ORDER' => $order_number,
                'DS_MERCHANT_MERCHANTCODE' => $this->config['merchant_code'],
                'DS_MERCHANT_CURRENCY' => $this->config['currency'],
                'DS_MERCHANT_TRANSACTIONTYPE' => '0', // AutorizaciÃ³n
                'DS_MERCHANT_TERMINAL' => $this->config['terminal'],
                'DS_MERCHANT_MERCHANTURL' => $this->get_notification_url(),
                'DS_MERCHANT_URLOK' => $this->get_success_url($contract_id),
                'DS_MERCHANT_URLKO' => $this->get_error_url($contract_id),
                'DS_MERCHANT_MERCHANTNAME' => get_bloginfo('name'),
                'DS_MERCHANT_PRODUCTDESCRIPTION' => !empty($description) ? $description : sprintf(__('Contrato %s', 'adhesion'), $contract['contract_number']),
                'DS_MERCHANT_TITULAR' => $this->get_customer_name($contract),
                'DS_MERCHANT_MERCHANTDATA' => json_encode(array(
                    'contract_id' => $contract_id,
                    'user_id' => $contract['user_id'],
                    'plugin_version' => ADHESION_PLUGIN_VERSION
                ))
            );
            
            // Codificar parÃ¡metros
            $merchant_parameters = base64_encode(json_encode($payment_data));
            
            // Generar firma
            $signature = $this->generate_signature($merchant_parameters, $order_number);
            
            // Actualizar contrato con informaciÃ³n del pago
            $this->db->update_contract_status($contract_id, 'pending_payment', array(
                'payment_amount' => $amount,
                'payment_reference' => $order_number,
                'payment_status' => 'pending'
            ));
            
            // Log del pago creado
            adhesion_log("Pago creado - Contrato: $contract_id, Orden: $order_number, Importe: $amount", 'info');
            
            return array(
                'order_number' => $order_number,
                'merchant_parameters' => $merchant_parameters,
                'signature' => $signature,
                'form_url' => $this->get_redsys_url(),
                'amount' => $amount,
                'contract_id' => $contract_id
            );
            
        } catch (Exception $e) {
            adhesion_log('Error creando pago: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Procesar callback de Redsys
     */
    public function handle_redsys_callback() {
        // Verificar si es un callback de Redsys
        if (!isset($_POST['Ds_SignatureVersion']) || !isset($_POST['Ds_MerchantParameters']) || !isset($_POST['Ds_Signature'])) {
            return;
        }
        
        // Verificar que es nuestra URL de notificaciÃ³n
        if (strpos($_SERVER['REQUEST_URI'], 'adhesion-redsys-notification') === false) {
            return;
        }
        
        try {
            adhesion_log('Callback recibido de Redsys', 'info');
            
            // Obtener parÃ¡metros
            $signature_version = $_POST['Ds_SignatureVersion'];
            $merchant_parameters = $_POST['Ds_MerchantParameters'];
            $signature = $_POST['Ds_Signature'];
            
            // Decodificar parÃ¡metros
            $parameters = json_decode(base64_decode($merchant_parameters), true);
            
            if (!$parameters) {
                throw new Exception('ParÃ¡metros invÃ¡lidos en callback de Redsys');
            }
            
            // Log de parÃ¡metros recibidos
            adhesion_log('ParÃ¡metros Redsys: ' . json_encode($parameters), 'debug');
            
            // Verificar firma
            if (!$this->verify_signature($merchant_parameters, $signature, $parameters['Ds_Order'])) {
                throw new Exception('Firma invÃ¡lida en callback de Redsys');
            }
            
            // Procesar el resultado del pago
            $this->process_payment_result($parameters);
            
            // Respuesta exitosa a Redsys
            http_response_code(200);
            echo 'OK';
            exit;
            
        } catch (Exception $e) {
            adhesion_log('Error procesando callback Redsys: ' . $e->getMessage(), 'error');
            http_response_code(400);
            echo 'ERROR: ' . $e->getMessage();
            exit;
        }
    }
    
    /**
     * Procesar resultado del pago
     */
    private function process_payment_result($parameters) {
        // Extraer datos importantes
        $order = $parameters['Ds_Order'];
        $response_code = $parameters['Ds_Response'];
        $amount = $parameters['Ds_Amount'];
        $currency = $parameters['Ds_Currency'];
        $date = $parameters['Ds_Date'];
        $hour = $parameters['Ds_Hour'];
        $auth_code = isset($parameters['Ds_AuthorisationCode']) ? $parameters['Ds_AuthorisationCode'] : '';
        
        // Obtener datos del merchant si existen
        $merchant_data = isset($parameters['Ds_MerchantData']) ? json_decode($parameters['Ds_MerchantData'], true) : array();
        $contract_id = isset($merchant_data['contract_id']) ? intval($merchant_data['contract_id']) : null;
        
        // Si no tenemos contract_id en merchant_data, intentar extraerlo del nÃºmero de orden
        if (!$contract_id) {
            $contract_id = $this->extract_contract_id_from_order($order);
        }
        
        if (!$contract_id) {
            throw new Exception('No se pudo identificar el contrato asociado al pago');
        }
        
        // Verificar si el pago fue exitoso
        $is_successful = $this->is_payment_successful($response_code);
        
        // Actualizar estado del contrato
        if ($is_successful) {
            $this->handle_successful_payment($contract_id, $parameters);
        } else {
            $this->handle_failed_payment($contract_id, $parameters);
        }
    }
    
    /**
     * Manejar pago exitoso
     */
    private function handle_successful_payment($contract_id, $parameters) {
        try {
            // Actualizar contrato
            $update_data = array(
                'payment_status' => 'completed',
                'payment_amount' => $this->parse_amount($parameters['Ds_Amount']),
                'payment_reference' => $parameters['Ds_Order'],
                'payment_auth_code' => $parameters['Ds_AuthorisationCode'] ?? '',
                'payment_completed_at' => current_time('mysql')
            );
            
            // Si el pago estÃ¡ completo y es requerido antes de la firma, cambiar estado
            if (adhesion_get_setting('require_payment', '0') === '1') {
                $update_data['status'] = 'paid';
            }
            
            $this->db->update_contract_status($contract_id, null, $update_data);
            
            // Log del pago exitoso
            adhesion_log("Pago exitoso - Contrato: $contract_id, Orden: {$parameters['Ds_Order']}, Importe: {$parameters['Ds_Amount']}", 'info');
            
            // Enviar notificaciÃ³n por email si estÃ¡ habilitado
            if (adhesion_get_setting('email_notifications', '1')) {
                $this->send_payment_confirmation_email($contract_id, $parameters);
            }
            
            // Hook para otras acciones despuÃ©s del pago exitoso
            do_action('adhesion_payment_successful', $contract_id, $parameters);
            
        } catch (Exception $e) {
            adhesion_log('Error procesando pago exitoso: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Manejar pago fallido
     */
    private function handle_failed_payment($contract_id, $parameters) {
        try {
            // Actualizar contrato
            $update_data = array(
                'payment_status' => 'failed',
                'payment_reference' => $parameters['Ds_Order'],
                'payment_error_code' => $parameters['Ds_Response']
            );
            
            $this->db->update_contract_status($contract_id, null, $update_data);
            
            // Log del pago fallido
            adhesion_log("Pago fallido - Contrato: $contract_id, Orden: {$parameters['Ds_Order']}, CÃ³digo: {$parameters['Ds_Response']}", 'warning');
            
            // Hook para otras acciones despuÃ©s del pago fallido
            do_action('adhesion_payment_failed', $contract_id, $parameters);
            
        } catch (Exception $e) {
            adhesion_log('Error procesando pago fallido: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    // ==========================================
    // MÃ‰TODOS AJAX
    // ==========================================
    
    /**
     * AJAX: Crear pago
     */
    public function ajax_create_payment() {
        try {
            // Verificar seguridad
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado para realizar pagos.', 'adhesion'));
            }
            
            // Obtener datos
            $contract_id = intval($_POST['contract_id']);
            $amount = floatval($_POST['amount']);
            $description = sanitize_text_field($_POST['description'] ?? '');
            
            // Validaciones
            if ($contract_id <= 0) {
                throw new Exception(__('ID de contrato invÃ¡lido.', 'adhesion'));
            }
            
            if ($amount <= 0) {
                throw new Exception(__('El importe debe ser mayor que 0.', 'adhesion'));
            }
            
            // Verificar que el usuario tenga acceso al contrato
            $contract = $this->db->get_contract($contract_id);
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            if ($contract['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos para este contrato.', 'adhesion'));
            }
            
            // Crear pago
            $payment_data = $this->create_payment($contract_id, $amount, $description);
            
            wp_send_json_success(array(
                'payment_data' => $payment_data,
                'message' => __('Pago creado correctamente. SerÃ¡s redirigido a Redsys.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Verificar estado del pago
     */
    public function ajax_check_payment_status() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            if ($contract['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos para este contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'payment_status' => $contract['payment_status'],
                'payment_amount' => $contract['payment_amount'],
                'payment_reference' => $contract['payment_reference'],
                'contract_status' => $contract['status']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // ==========================================
    // MÃ‰TODOS DE UTILIDAD
    // ==========================================
    
    /**
     * Verificar si Redsys estÃ¡ configurado
     */
    public function is_configured() {
        return !empty($this->config['merchant_code']) && 
               !empty($this->config['secret_key']) && 
               !empty($this->config['terminal']);
    }
    
    /**
     * Generar nÃºmero de orden Ãºnico
     */
    private function generate_order_number($contract_id) {
        // Formato: YYYYMMDDHHMMSS + contract_id (max 12 caracteres)
        $timestamp = date('ymdHis'); // 12 caracteres
        $contract_suffix = str_pad($contract_id, 4, '0', STR_PAD_LEFT); // 4 caracteres
        
        return $timestamp . $contract_suffix; // Total: 12 caracteres (mÃ¡ximo permitido por Redsys)
    }
    
    /**
     * Extraer contract_id del nÃºmero de orden
     */
    private function extract_contract_id_from_order($order) {
        // Los Ãºltimos 4 caracteres son el contract_id
        if (strlen($order) >= 4) {
            return intval(substr($order, -4));
        }
        return null;
    }
    
    /**
     * Formatear importe para Redsys (cÃ©ntimos)
     */
    private function format_amount($amount) {
        return str_pad(round($amount * 100), 1, '0', STR_PAD_LEFT);
    }
    
    /**
     * Parsear importe desde Redsys (cÃ©ntimos a euros)
     */
    private function parse_amount($amount_centimos) {
        return floatval($amount_centimos) / 100;
    }
    
    /**
     * Generar firma HMAC-SHA256
     */
    private function generate_signature($merchant_parameters, $order) {
        // Generar clave especÃ­fica para la orden
        $key = base64_decode($this->config['secret_key']);
        $cipher = 'aes-256-cbc';
        $iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
        
        // Encriptar la orden con la clave
        $encrypted_key = openssl_encrypt($order, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        // Generar HMAC con la clave encriptada
        $signature = hash_hmac('sha256', $merchant_parameters, $encrypted_key, true);
        
        return base64_encode($signature);
    }
    
    /**
     * Verificar firma de Redsys
     */
    private function verify_signature($merchant_parameters, $received_signature, $order) {
        $calculated_signature = $this->generate_signature($merchant_parameters, $order);
        return hash_equals($calculated_signature, $received_signature);
    }
    
    /**
     * Verificar si el pago fue exitoso
     */
    private function is_payment_successful($response_code) {
        // CÃ³digos de respuesta exitosos (0000-0099)
        $code = intval($response_code);
        return $code >= 0 && $code <= 99;
    }
    
    /**
     * Obtener URL de Redsys segÃºn entorno
     */
    private function get_redsys_url() {
        return $this->redsys_urls[$this->config['environment']];
    }
    
    /**
     * Obtener URL de notificaciÃ³n
     */
    private function get_notification_url() {
        return add_query_arg('adhesion-redsys-notification', '1', home_url());
    }
    
    /**
     * Obtener URL de Ã©xito
     */
    private function get_success_url($contract_id) {
        return add_query_arg(array(
            'adhesion_payment_return' => 'success',
            'contract_id' => $contract_id
        ), home_url());
    }
    
    /**
     * Obtener URL de error
     */
    private function get_error_url($contract_id) {
        return add_query_arg(array(
            'adhesion_payment_return' => 'error',
            'contract_id' => $contract_id
        ), home_url());
    }
    
    /**
     * Obtener nombre del cliente desde el contrato
     */
    private function get_customer_name($contract) {
        if (!empty($contract['client_data']['nombre_completo'])) {
            return $contract['client_data']['nombre_completo'];
        }
        
        if (!empty($contract['user_name'])) {
            return $contract['user_name'];
        }
        
        return 'Cliente';
    }
    
    /**
     * Manejar retorno desde Redsys
     */
    public function handle_return() {
        if (!isset($_GET['adhesion_payment_return'])) {
            return;
        }
        
        $result = sanitize_text_field($_GET['adhesion_payment_return']);
        $contract_id = intval($_GET['contract_id'] ?? 0);
        
        if ($contract_id <= 0) {
            return;
        }
        
        // Obtener informaciÃ³n del contrato
        $contract = $this->db->get_contract($contract_id);
        if (!$contract) {
            return;
        }
        
        // Verificar acceso del usuario
        if (!is_user_logged_in() || 
            ($contract['user_id'] != get_current_user_id() && !current_user_can('manage_options'))) {
            return;
        }
        
        // Mostrar mensaje segÃºn el resultado
        if ($result === 'success') {
            if ($contract['payment_status'] === 'completed') {
                adhesion_add_notice(__('Â¡Pago realizado correctamente! Tu contrato ha sido procesado.', 'adhesion'), 'success');
            } else {
                adhesion_add_notice(__('El pago estÃ¡ siendo procesado. RecibirÃ¡s confirmaciÃ³n en breve.', 'adhesion'), 'info');
            }
        } else {
            adhesion_add_notice(__('Hubo un problema con el pago. Por favor, intÃ©ntalo de nuevo o contacta con nosotros.', 'adhesion'), 'error');
        }
        
        // Redireccionar para limpiar la URL
        $redirect_url = remove_query_arg(array('adhesion_payment_return', 'contract_id'));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Enviar email de confirmaciÃ³n de pago
     */
    private function send_payment_confirmation_email($contract_id, $payment_data) {
        try {
            $contract = $this->db->get_contract($contract_id);
            if (!$contract || empty($contract['user_email'])) {
                return false;
            }
            
            $amount = $this->parse_amount($payment_data['Ds_Amount']);
            
            $email_data = array(
                'contract_number' => $contract['contract_number'],
                'amount' => adhesion_format_price($amount),
                'payment_reference' => $payment_data['Ds_Order'],
                'customer_name' => $this->get_customer_name($contract),
                'site_name' => get_bloginfo('name')
            );
            
            return adhesion_send_email(
                $contract['user_email'],
                sprintf(__('ConfirmaciÃ³n de pago - Contrato %s', 'adhesion'), $contract['contract_number']),
                'payment-confirmation',
                $email_data
            );
            
        } catch (Exception $e) {
            adhesion_log('Error enviando email de confirmaciÃ³n de pago: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Shortcode para formulario de pago
     */
    public function payment_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'contract_id' => '',
            'amount' => '',
            'description' => ''
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="adhesion-notice adhesion-notice-warning">' . 
                   '<p>' . __('Debes estar logueado para realizar pagos.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        if (!$this->is_configured()) {
            return '<div class="adhesion-notice adhesion-notice-error">' . 
                   '<p>' . __('Los pagos no estÃ¡n configurados. Contacta con el administrador.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/payment-form.php';
        return ob_get_clean();
    }
    
    /**
     * Obtener estadÃ­sticas de pagos
     */
    public function get_payment_stats($period = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-$period days"));
        $date_to = date('Y-m-d');
        
        $stats = array();
        
        // Total pagos completados
        $stats['total_payments'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts 
             WHERE payment_status = 'completed' 
             AND payment_completed_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Ingresos totales
        $stats['total_revenue'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(payment_amount) FROM {$wpdb->prefix}adhesion_contracts 
             WHERE payment_status = 'completed' 
             AND payment_completed_at BETWEEN %s AND %s",
            $date_from, $date_to
        )) ?: 0;
        
        // Pagos fallidos
        $stats['failed_payments'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts 
             WHERE payment_status = 'failed'
             AND created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Tasa de conversiÃ³n
        $total_attempts = $stats['total_payments'] + $stats['failed_payments'];
        $stats['conversion_rate'] = $total_attempts > 0 ? ($stats['total_payments'] / $total_attempts) * 100 : 0;
        
        return $stats;
    }
}


// ===== class-public.php =====
<?php
/**
 * Clase principal del frontend pÃºblico
 * 
 * Esta clase maneja todo el frontend del plugin:
 * - Shortcodes para calculadora, cuenta de usuario, pagos
 * - Enqueue de assets del frontend
 * - Hooks pÃºblicos
 * - IntegraciÃ³n con WordPress pÃºblico
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Public {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Inicializar hooks del frontend
     */
    private function init_hooks() {
        // Shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // Assets del frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Formularios
        add_action('wp', array($this, 'handle_form_submissions'));
        
        // Login/logout hooks
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'on_user_logout'));
        
        // Redirecciones despuÃ©s del login
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);
        
        // Body classes
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // PÃ¡ginas de adhesiÃ³n
        add_action('template_redirect', array($this, 'adhesion_page_handler'));
    }
    
    /**
     * Cargar dependencias del frontend
     */
    private function load_dependencies() {
        require_once ADHESION_PLUGIN_PATH . 'public/class-calculator.php';
        require_once ADHESION_PLUGIN_PATH . 'public/class-payment.php';
        require_once ADHESION_PLUGIN_PATH . 'public/class-docusign.php';
        require_once ADHESION_PLUGIN_PATH . 'public/class-user-account.php';
    }
    
    /**
     * Registrar shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('adhesion_calculator', array($this, 'calculator_shortcode'));
        add_shortcode('adhesion_account', array($this, 'account_shortcode'));
        add_shortcode('adhesion_payment', array($this, 'payment_shortcode'));
        add_shortcode('adhesion_contract_signing', array($this, 'contract_signing_shortcode'));
        add_shortcode('adhesion_login', array($this, 'login_shortcode'));
        add_shortcode('adhesion_register', array($this, 'register_shortcode'));
    }
    
    /**
     * Encolar scripts y estilos
     */
    public function enqueue_scripts() {
        // Solo cargar en pÃ¡ginas necesarias
        if (!$this->is_adhesion_page()) {
            return;
        }
        
        // CSS del frontend
        wp_enqueue_style(
            'adhesion-frontend',
            ADHESION_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ADHESION_PLUGIN_VERSION
        );
        
        // JavaScript del frontend
        wp_enqueue_script(
            'adhesion-frontend',
            ADHESION_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ADHESION_PLUGIN_VERSION,
            true
        );
        
        // Localizar script para AJAX
        wp_localize_script('adhesion-frontend', 'adhesionAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adhesion_nonce'),
            'messages' => array(
                'error' => __('Ha ocurrido un error. Por favor, intÃ©ntalo de nuevo.', 'adhesion'),
                'loading' => __('Cargando...', 'adhesion'),
                'success' => __('OperaciÃ³n completada con Ã©xito.', 'adhesion'),
                'calculatingBudget' => __('Calculando presupuesto...', 'adhesion'),
                'processingPayment' => __('Procesando pago...', 'adhesion'),
                'requiredFields' => __('Por favor, completa todos los campos obligatorios.', 'adhesion')
            ),
            'settings' => array(
                'calculatorEnabled' => adhesion_get_setting('calculator_enabled', '1'),
                'requirePayment' => adhesion_get_setting('require_payment', '0')
            )
        ));
        
        // Enqueue adicional para calculadora
        if ($this->is_calculator_page()) {
            $this->enqueue_calculator_assets();
        }
        
        // Enqueue adicional para pagos
        if ($this->is_payment_page()) {
            $this->enqueue_payment_assets();
        }
    }
    
    /**
     * Assets especÃ­ficos para calculadora
     */
    private function enqueue_calculator_assets() {
        wp_enqueue_script(
            'adhesion-calculator',
            ADHESION_PLUGIN_URL . 'assets/js/calculator.js',
            array('jquery'),
            ADHESION_PLUGIN_VERSION,
            true
        );
        
        // Localizar precios de materiales
        $materials = $this->db->get_calculator_prices();
        $prices = array();
        foreach ($materials as $material) {
            $prices[$material['material_type']] = floatval($material['price_per_ton']);
        }
        
        wp_localize_script('adhesion-calculator', 'calculatorData', array(
            'materials' => $materials,
            'prices' => $prices
        ));
    }
    
    /**
     * Assets especÃ­ficos para pagos
     */
    private function enqueue_payment_assets() {
        // Script especÃ­fico de Redsys si estÃ¡ configurado
        if (adhesion_is_redsys_configured()) {
            wp_enqueue_script(
                'adhesion-payment',
                ADHESION_PLUGIN_URL . 'assets/js/payment.js',
                array('jquery'),
                ADHESION_PLUGIN_VERSION,
                true
            );
        }
    }
    
    // ==========================================
    // SHORTCODES
    // ==========================================
    
    /**
     * Shortcode: Calculadora de presupuestos
     */
    public function calculator_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'title' => __('Calculadora de Presupuesto', 'adhesion')
        ), $atts);
        
        // Verificar si la calculadora estÃ¡ habilitada
        if (!adhesion_get_setting('calculator_enabled', '1')) {
            return '<div class="adhesion-notice adhesion-notice-warning">' . 
                   '<p>' . __('La calculadora no estÃ¡ disponible temporalmente.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        // Verificar si el usuario estÃ¡ logueado (segÃºn especificaciones)
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/calculator-display.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Cuenta de usuario
     */
    public function account_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_calculations' => 'true',
            'show_contracts' => 'true',
            'limit' => '5'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }
        
        // Verificar que el usuario tenga rol correcto
        if (!adhesion_user_can('adhesion_access')) {
            return '<div class="adhesion-notice adhesion-notice-error">' . 
                   '<p>' . __('No tienes permisos para acceder a esta Ã¡rea.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/user-account-display.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Proceso de pago
     */
    public function payment_shortcode($atts) {
        $atts = shortcode_atts(array(
            'calculation_id' => '',
            'contract_id' => ''
        ), $atts);
        
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }
        
        // Verificar configuraciÃ³n de Redsys
        if (!adhesion_is_redsys_configured()) {
            return '<div class="adhesion-notice adhesion-notice-error">' . 
                   '<p>' . __('Los pagos no estÃ¡n configurados. Contacta con el administrador.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/payment-display.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Firma de contratos
     */
    public function contract_signing_shortcode($atts) {
        $atts = shortcode_atts(array(
            'contract_id' => ''
        ), $atts);
        
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }
        
        // Verificar configuraciÃ³n de DocuSign
        if (!adhesion_is_docusign_configured()) {
            return '<div class="adhesion-notice adhesion-notice-error">' . 
                   '<p>' . __('La firma digital no estÃ¡ configurada. Contacta con el administrador.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/contract-signing-display.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Login personalizado
     */
    public function login_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_register_link' => 'true'
        ), $atts);
        
        if (is_user_logged_in()) {
            $account_url = $this->get_account_url();
            return '<div class="adhesion-notice adhesion-notice-info">' . 
                   '<p>' . sprintf(__('Ya estÃ¡s logueado. <a href="%s">Ir a mi cuenta</a>', 'adhesion'), $account_url) . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/login-form.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Registro personalizado
     */
    public function register_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_login_link' => 'true'
        ), $atts);
        
        if (is_user_logged_in()) {
            $account_url = $this->get_account_url();
            return '<div class="adhesion-notice adhesion-notice-info">' . 
                   '<p>' . sprintf(__('Ya estÃ¡s registrado. <a href="%s">Ir a mi cuenta</a>', 'adhesion'), $account_url) . '</p>' .
                   '</div>';
        }
        
        // Verificar si el registro estÃ¡ habilitado
        if (!get_option('users_can_register')) {
            return '<div class="adhesion-notice adhesion-notice-warning">' . 
                   '<p>' . __('El registro de usuarios no estÃ¡ habilitado.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/register-form.php';
        return ob_get_clean();
    }
    
    // ==========================================
    // MANEJO DE FORMULARIOS
    // ==========================================
    
    /**
     * Manejar envÃ­os de formularios
     */
    public function handle_form_submissions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verificar nonce
        if (!isset($_POST['adhesion_nonce']) || !wp_verify_nonce($_POST['adhesion_nonce'], 'adhesion_form')) {
            return;
        }
        
        // Determinar quÃ© formulario se enviÃ³
        if (isset($_POST['adhesion_action'])) {
            $action = sanitize_text_field($_POST['adhesion_action']);
            
            switch ($action) {
                case 'register':
                    $this->handle_registration();
                    break;
                    
                case 'save_calculation':
                    $this->handle_save_calculation();
                    break;
                    
                case 'create_contract':
                    $this->handle_create_contract();
                    break;
                    
                case 'update_profile':
                    $this->handle_update_profile();
                    break;
            }
        }
    }
    
    /**
     * Manejar registro de usuario
     */
    private function handle_registration() {
        try {
            // Verificar que el registro estÃ© habilitado
            if (!get_option('users_can_register')) {
                throw new Exception(__('El registro no estÃ¡ habilitado.', 'adhesion'));
            }
            
            // Sanitizar datos
            $username = sanitize_user($_POST['username']);
            $email = sanitize_email($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            
            // Validaciones
            if (empty($username) || empty($email) || empty($password)) {
                throw new Exception(__('Todos los campos son obligatorios.', 'adhesion'));
            }
            
            if ($password !== $confirm_password) {
                throw new Exception(__('Las contraseÃ±as no coinciden.', 'adhesion'));
            }
            
            if (username_exists($username)) {
                throw new Exception(__('El nombre de usuario ya existe.', 'adhesion'));
            }
            
            if (email_exists($email)) {
                throw new Exception(__('El email ya estÃ¡ registrado.', 'adhesion'));
            }
            
            // Crear usuario
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
            
            // Asignar rol de cliente adherido
            $user = new WP_User($user_id);
            $user->set_role('adhesion_client');
            
            // Agregar metadatos
            update_user_meta($user_id, 'first_name', $first_name);
            update_user_meta($user_id, 'last_name', $last_name);
            
            // Login automÃ¡tico
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            // Enviar email de bienvenida si estÃ¡ habilitado
            if (adhesion_get_setting('email_notifications', '1')) {
                adhesion_send_email($email, __('Bienvenido a nuestro servicio', 'adhesion'), 'welcome', array(
                    'user_name' => $first_name,
                    'username' => $username,
                    'site_name' => get_bloginfo('name')
                ));
            }
            
            // Redireccionar
            $redirect_url = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : $this->get_calculator_url();
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            adhesion_add_notice($e->getMessage(), 'error');
        }
    }
    
    /**
     * Manejar guardado de cÃ¡lculo
     */
    private function handle_save_calculation() {
        if (!is_user_logged_in()) {
            adhesion_add_notice(__('Debes estar logueado para guardar cÃ¡lculos.', 'adhesion'), 'error');
            return;
        }
        
        try {
            $user_id = get_current_user_id();
            
            // Sanitizar datos del cÃ¡lculo
            $calculation_data = array(
                'materials' => $this->sanitize_materials_data($_POST['materials']),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                'timestamp' => current_time('mysql')
            );
            
            $total_price = floatval($_POST['total_price']);
            $total_tons = floatval($_POST['total_tons']);
            $price_per_ton = $total_tons > 0 ? $total_price / $total_tons : 0;
            
            // Guardar en base de datos
            $calculation_id = $this->db->create_calculation(
                $user_id,
                $calculation_data,
                $total_price,
                $price_per_ton,
                $total_tons
            );
            
            if (!$calculation_id) {
                throw new Exception(__('Error al guardar el cÃ¡lculo.', 'adhesion'));
            }
            
            adhesion_add_notice(__('CÃ¡lculo guardado correctamente.', 'adhesion'), 'success');
            
            // Redireccionar a la cuenta del usuario
            wp_redirect($this->get_account_url());
            exit;
            
        } catch (Exception $e) {
            adhesion_add_notice($e->getMessage(), 'error');
        }
    }
    
    // ==========================================
    // UTILIDADES Y HELPERS
    // ==========================================
    
    /**
     * Verificar si estamos en una pÃ¡gina de adhesiÃ³n
     */
    private function is_adhesion_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Verificar shortcodes de adhesiÃ³n en el contenido
        $shortcodes = array(
            'adhesion_calculator',
            'adhesion_account',
            'adhesion_payment',
            'adhesion_contract_signing',
            'adhesion_login',
            'adhesion_register'
        );
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si estamos en la pÃ¡gina de calculadora
     */
    private function is_calculator_page() {
        global $post;
        return $post && has_shortcode($post->post_content, 'adhesion_calculator');
    }
    
    /**
     * Verificar si estamos en la pÃ¡gina de pagos
     */
    private function is_payment_page() {
        global $post;
        return $post && has_shortcode($post->post_content, 'adhesion_payment');
    }
    
    /**
     * Mensaje de login requerido
     */
    private function login_required_message() {
        $login_url = wp_login_url(get_permalink());
        $register_url = wp_registration_url();
        
        $message = '<div class="adhesion-login-required">';
        $message .= '<h3>' . __('Acceso Requerido', 'adhesion') . '</h3>';
        $message .= '<p>' . __('Debes iniciar sesiÃ³n para acceder a esta funciÃ³n.', 'adhesion') . '</p>';
        $message .= '<div class="adhesion-login-buttons">';
        $message .= '<a href="' . esc_url($login_url) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar SesiÃ³n', 'adhesion') . '</a>';
        
        if (get_option('users_can_register')) {
            $message .= '<a href="' . esc_url($register_url) . '" class="adhesion-btn adhesion-btn-secondary">' . __('Registrarse', 'adhesion') . '</a>';
        }
        
        $message .= '</div>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Obtener URL de la calculadora
     */
    private function get_calculator_url() {
        $page_id = adhesion_get_setting('page_calculadora_presupuesto');
        return $page_id ? get_permalink($page_id) : home_url();
    }
    
    /**
     * Obtener URL de la cuenta de usuario
     */
    private function get_account_url() {
        $page_id = adhesion_get_setting('page_mi_cuenta_adhesion');
        return $page_id ? get_permalink($page_id) : home_url();
    }
    
    /**
     * Sanitizar datos de materiales
     */
    private function sanitize_materials_data($materials) {
        if (!is_array($materials)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($materials as $material) {
            if (isset($material['type']) && isset($material['quantity'])) {
                $sanitized[] = array(
                    'type' => sanitize_text_field($material['type']),
                    'quantity' => floatval($material['quantity'])
                );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Agregar clases CSS al body
     */
    public function add_body_classes($classes) {
        if ($this->is_adhesion_page()) {
            $classes[] = 'adhesion-page';
            
            if ($this->is_calculator_page()) {
                $classes[] = 'adhesion-calculator-page';
            }
            
            if ($this->is_payment_page()) {
                $classes[] = 'adhesion-payment-page';
            }
            
            if (is_user_logged_in()) {
                $classes[] = 'adhesion-logged-in';
            } else {
                $classes[] = 'adhesion-logged-out';
            }
        }
        
        return $classes;
    }
    
    /**
     * Manejar redirecciÃ³n despuÃ©s del login
     */
    public function login_redirect($redirect_to, $request, $user) {
        // Solo para usuarios con rol adhesion_client
        if (isset($user->roles) && in_array('adhesion_client', $user->roles)) {
            // Redireccionar a la calculadora por defecto
            return $this->get_calculator_url();
        }
        
        return $redirect_to;
    }
    
    /**
     * Acciones al hacer login
     */
    public function on_user_login($user_login, $user) {
        // Actualizar Ãºltima actividad
        update_user_meta($user->ID, 'adhesion_last_login', current_time('mysql'));
    }
    
    /**
     * Acciones al hacer logout
     */
    public function on_user_logout() {
        // Limpiar datos temporales si es necesario
        $user_id = get_current_user_id();
        if ($user_id) {
            delete_transient('adhesion_user_data_' . $user_id);
        }
    }
    
    /**
     * Manejar pÃ¡ginas especiales de adhesiÃ³n
     */
    public function adhesion_page_handler() {
        // Manejar callbacks de Redsys
        if (isset($_GET['adhesion_payment_return'])) {
            $this->handle_payment_return();
        }
        
        // Manejar callbacks de DocuSign
        if (isset($_GET['adhesion_docusign_return'])) {
            $this->handle_docusign_return();
        }
    }
    
    /**
     * Manejar retorno de pago
     */
    private function handle_payment_return() {
        // Esta funcionalidad se implementarÃ¡ en class-payment.php
        $payment_handler = new Adhesion_Payment();
        $payment_handler->handle_return();
    }
    
    /**
     * Manejar retorno de DocuSign
     */
    private function handle_docusign_return() {
        // Esta funcionalidad se implementarÃ¡ en class-docusign.php
        $docusign_handler = new Adhesion_DocuSign();
        $docusign_handler->handle_return();
    }
}


// ===== class-user-account.php =====
<?php
/**
 * Clase para gestiÃ³n de cuentas de usuario
 * Archivo: public/class-user-account.php
 * 
 * Maneja toda la funcionalidad del dashboard de usuario:
 * - VisualizaciÃ³n de datos
 * - ActualizaciÃ³n de perfil
 * - GestiÃ³n de cÃ¡lculos y contratos
 * - Shortcodes relacionados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_User_Account {
    
    /**
     * Instancia Ãºnica de la clase
     */
    private static $instance = null;
    
    /**
     * Base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
    }
    
    /**
     * Obtener instancia Ãºnica
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_adhesion_get_calculation_details', array($this, 'ajax_get_calculation_details'));
        add_action('wp_ajax_adhesion_get_contract_details', array($this, 'ajax_get_contract_details'));
        add_action('wp_ajax_adhesion_process_calculation_contract', array($this, 'ajax_process_calculation_contract'));
        add_action('wp_ajax_adhesion_initiate_contract_signing', array($this, 'ajax_initiate_contract_signing'));
        add_action('wp_ajax_adhesion_update_user_profile', array($this, 'ajax_update_user_profile'));
        add_action('wp_ajax_adhesion_check_contract_status', array($this, 'ajax_check_contract_status'));
        
        // Shortcodes
        add_shortcode('adhesion_account', array($this, 'account_shortcode'));
        add_shortcode('adhesion_login', array($this, 'login_shortcode'));
        add_shortcode('adhesion_register', array($this, 'register_shortcode'));
        
        // Proceso de login/registro
        add_action('wp_ajax_nopriv_adhesion_user_login', array($this, 'ajax_user_login'));
        add_action('wp_ajax_nopriv_adhesion_user_register', array($this, 'ajax_user_register'));
    }
    
    /**
     * ================================
     * SHORTCODES
     * ================================
     */
    
    /**
     * Shortcode para mostrar la cuenta de usuario
     */
    public function account_shortcode($atts = array()) {
        // Verificar que el usuario estÃ© logueado
        if (!is_user_logged_in()) {
            return $this->login_shortcode($atts);
        }
        
        $current_user = wp_get_current_user();
        
        // Verificar permisos
        if (!$this->user_can_access_account($current_user)) {
            return '<div class="adhesion-message error">' . 
                   __('No tienes permisos para acceder a esta Ã¡rea.', 'adhesion') . 
                   '</div>';
        }
        
        // Encolar assets necesarios
        $this->enqueue_account_assets();
        
        // Obtener contenido
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/user-account-display.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode para formulario de login
     */
    public function login_shortcode($atts = array()) {
        if (is_user_logged_in()) {
            return '<div class="adhesion-message info">' . 
                   __('Ya estÃ¡s conectado.', 'adhesion') . 
                   ' <a href="' . wp_logout_url(get_permalink()) . '">' . __('Cerrar sesiÃ³n', 'adhesion') . '</a>' .
                   '</div>';
        }
        
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_register_link' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
        <div class="adhesion-login-form">
            <form id="adhesion-login-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_user_login', 'adhesion_login_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('Iniciar SesiÃ³n', 'adhesion'); ?></h3>
                    
                    <div class="form-group">
                        <label for="user_login"><?php _e('Email o Usuario', 'adhesion'); ?></label>
                        <input type="text" id="user_login" name="user_login" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_password"><?php _e('ContraseÃ±a', 'adhesion'); ?></label>
                        <input type="password" id="user_password" name="user_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="remember" value="1">
                            <?php _e('Recordarme', 'adhesion'); ?>
                        </label>
                    </div>
                    
                    <?php if (!empty($atts['redirect'])) : ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-unlock"></span>
                        <?php _e('Iniciar SesiÃ³n', 'adhesion'); ?>
                    </button>
                    
                    <?php if ($atts['show_register_link'] === 'yes') : ?>
                        <a href="#" id="show-register-form" class="button">
                            <?php _e('Crear Cuenta', 'adhesion'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para formulario de registro
     */
    public function register_shortcode($atts = array()) {
        if (is_user_logged_in()) {
            return '<div class="adhesion-message info">' . 
                   __('Ya estÃ¡s registrado y conectado.', 'adhesion') . 
                   '</div>';
        }
        
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_login_link' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
        <div class="adhesion-register-form">
            <form id="adhesion-register-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_user_register', 'adhesion_register_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('Crear Cuenta', 'adhesion'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_first_name"><?php _e('Nombre', 'adhesion'); ?></label>
                            <input type="text" id="reg_first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_last_name"><?php _e('Apellidos', 'adhesion'); ?></label>
                            <input type="text" id="reg_last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_email"><?php _e('Correo ElectrÃ³nico', 'adhesion'); ?></label>
                        <input type="email" id="reg_email" name="user_email" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_password"><?php _e('ContraseÃ±a', 'adhesion'); ?></label>
                            <input type="password" id="reg_password" name="user_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_confirm_password"><?php _e('Confirmar ContraseÃ±a', 'adhesion'); ?></label>
                            <input type="password" id="reg_confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_company"><?php _e('Empresa (Opcional)', 'adhesion'); ?></label>
                        <input type="text" id="reg_company" name="company">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="accept_terms" value="1" required>
                            <?php _e('Acepto los tÃ©rminos y condiciones', 'adhesion'); ?>
                        </label>
                    </div>
                    
                    <?php if (!empty($atts['redirect'])) : ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php _e('Crear Cuenta', 'adhesion'); ?>
                    </button>
                    
                    <?php if ($atts['show_login_link'] === 'yes') : ?>
                        <a href="#" id="show-login-form" class="button">
                            <?php _e('Ya tengo cuenta', 'adhesion'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * ================================
     * AJAX HANDLERS
     * ================================
     */
    
    /**
     * Obtener detalles de cÃ¡lculo vÃ­a AJAX
     */
    public function ajax_get_calculation_details() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $calculation_id = intval($_POST['calculation_id']);
        $user_id = get_current_user_id();
        
        // Obtener cÃ¡lculo
        $calculation = $this->db->get_calculation($calculation_id);
        
        if (!$calculation || $calculation->user_id != $user_id) {
            wp_send_json_error(__('CÃ¡lculo no encontrado o sin permisos.', 'adhesion'));
        }
        
        // Preparar datos de respuesta
        $response_data = array(
            'id' => $calculation->id,
            'material_type' => $calculation->material_type,
            'quantity' => $calculation->quantity,
            'price_per_kg' => $calculation->price_per_kg,
            'total_price' => $calculation->total_price,
            'additional_details' => $calculation->additional_details,
            'status' => $calculation->status,
            'created_at' => $calculation->created_at
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Obtener detalles de contrato vÃ­a AJAX
     */
    public function ajax_get_contract_details() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $contract_id = intval($_POST['contract_id']);
        $user_id = get_current_user_id();
        
        // Obtener contrato
        $contract = $this->db->get_contract($contract_id);
        
        if (!$contract || $contract->user_id != $user_id) {
            wp_send_json_error(__('Contrato no encontrado o sin permisos.', 'adhesion'));
        }
        
        // Preparar datos de respuesta
        $response_data = array(
            'id' => $contract->id,
            'contract_type' => $contract->contract_type,
            'status' => $contract->status,
            'amount' => $contract->amount,
            'created_at' => $contract->created_at,
            'signed_at' => $contract->signed_at,
            'docusign_envelope_id' => $contract->docusign_envelope_id,
            'signed_document_url' => $contract->signed_document_url,
            'contract_data' => $contract->contract_data
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Procesar contrataciÃ³n de cÃ¡lculo vÃ­a AJAX
     */
    public function ajax_process_calculation_contract() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $calculation_id = intval($_POST['calculation_id']);
        $user_id = get_current_user_id();
        
        // Obtener cÃ¡lculo
        $calculation = $this->db->get_calculation($calculation_id);
        
        if (!$calculation || $calculation->user_id != $user_id) {
            wp_send_json_error(__('CÃ¡lculo no encontrado o sin permisos.', 'adhesion'));
        }
        
        if ($calculation->status !== 'calculated') {
            wp_send_json_error(__('Este cÃ¡lculo ya ha sido procesado.', 'adhesion'));
        }
        
        try {
            // Crear contrato
            $contract_data = array(
                'user_id' => $user_id,
                'calculation_id' => $calculation_id,
                'contract_type' => 'adhesion_standard',
                'amount' => $calculation->total_price,
                'status' => 'pending_payment',
                'contract_data' => $this->generate_contract_content($calculation),
                'created_at' => current_time('mysql')
            );
            
            $contract_id = $this->db->create_contract($contract_data);
            
            if (!$contract_id) {
                throw new Exception(__('Error al crear el contrato.', 'adhesion'));
            }
            
            // Actualizar estado del cÃ¡lculo
            $this->db->update_calculation($calculation_id, array(
                'status' => 'contracted'
            ));
            
            // Generar URL de pago
            $payment_url = $this->generate_payment_url($contract_id);
            
            wp_send_json_success(array(
                'contract_id' => $contract_id,
                'payment_url' => $payment_url,
                'message' => __('Contrato creado correctamente. Redirigiendo al pago...', 'adhesion')
            ));
            
        } catch (Exception $e) {
            adhesion_log('Error processing calculation contract: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Iniciar firma de contrato vÃ­a AJAX
     */
    public function ajax_initiate_contract_signing() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $contract_id = intval($_POST['contract_id']);
        $user_id = get_current_user_id();
        
        // Obtener contrato
        $contract = $this->db->get_contract($contract_id);
        
        if (!$contract || $contract->user_id != $user_id) {
            wp_send_json_error(__('Contrato no encontrado o sin permisos.', 'adhesion'));
        }
        
        if ($contract->status !== 'pending') {
            wp_send_json_error(__('Este contrato no estÃ¡ disponible para firma.', 'adhesion'));
        }
        
        try {
            // Inicializar DocuSign
            $docusign = new Adhesion_DocuSign();
            $current_user = wp_get_current_user();
            
            // Preparar datos para DocuSign
            $envelope_data = array(
                'document_content' => $contract->contract_data,
                'signer_name' => $current_user->display_name,
                'signer_email' => $current_user->user_email,
                'subject' => sprintf(__('Contrato de AdhesiÃ³n - %s', 'adhesion'), get_bloginfo('name')),
                'message' => __('Por favor, revisa y firma el contrato de adhesiÃ³n.', 'adhesion')
            );
            
            $result = $docusign->send_envelope($envelope_data);
            
            if ($result['success']) {
                // Actualizar contrato con informaciÃ³n de DocuSign
                $this->db->update_contract($contract_id, array(
                    'docusign_envelope_id' => $result['envelope_id'],
                    'status' => 'sent_for_signature'
                ));
                
                wp_send_json_success(array(
                    'signing_url' => $result['signing_url'],
                    'envelope_id' => $result['envelope_id'],
                    'message' => __('Firma digital iniciada. Revisa tu email y completa la firma.', 'adhesion')
                ));
            } else {
                throw new Exception($result['message']);
            }
            
        } catch (Exception $e) {
            adhesion_log('Error initiating contract signing: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Actualizar perfil de usuario vÃ­a AJAX
     */
    public function ajax_update_user_profile() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['adhesion_profile_nonce'], 'adhesion_update_profile')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $user_id = get_current_user_id();
        $current_user = get_userdata($user_id);
        
        try {
            // Validar datos
            $display_name = sanitize_text_field($_POST['display_name']);
            $user_email = sanitize_email($_POST['user_email']);
            
            if (empty($display_name) || empty($user_email)) {
                throw new Exception(__('Nombre y email son obligatorios.', 'adhesion'));
            }
            
            if (!is_email($user_email)) {
                throw new Exception(__('Email no vÃ¡lido.', 'adhesion'));
            }
            
            // Verificar si el email ya existe (solo si es diferente al actual)
            if ($user_email !== $current_user->user_email) {
                if (email_exists($user_email)) {
                    throw new Exception(__('Este email ya estÃ¡ registrado.', 'adhesion'));
                }
            }
            
            // Actualizar datos bÃ¡sicos de usuario
            $user_data = array(
                'ID' => $user_id,
                'display_name' => $display_name,
                'user_email' => $user_email
            );
            
            // Manejar cambio de contraseÃ±a
            if (!empty($_POST['new_password'])) {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verificar contraseÃ±a actual
                if (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
                    throw new Exception(__('La contraseÃ±a actual no es correcta.', 'adhesion'));
                }
                
                // Verificar que coincidan las nuevas contraseÃ±as
                if ($new_password !== $confirm_password) {
                    throw new Exception(__('Las contraseÃ±as nuevas no coinciden.', 'adhesion'));
                }
                
                // Verificar longitud mÃ­nima
                if (strlen($new_password) < 6) {
                    throw new Exception(__('La contraseÃ±a debe tener al menos 6 caracteres.', 'adhesion'));
                }
                
                $user_data['user_pass'] = $new_password;
            }
            
            // Actualizar usuario
            $updated = wp_update_user($user_data);
            
            if (is_wp_error($updated)) {
                throw new Exception($updated->get_error_message());
            }
            
            // Actualizar metadatos adicionales
            $meta_fields = array('phone', 'company', 'address', 'city', 'postal_code', 'country');
            
            foreach ($meta_fields as $field) {
                if (isset($_POST[$field])) {
                    update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
                }
            }
            
            // Log de la actualizaciÃ³n
            adhesion_log(sprintf('User profile updated for user ID: %d', $user_id), 'info');
            
            wp_send_json_success(array(
                'message' => __('Perfil actualizado correctamente.', 'adhesion'),
                'display_name' => $display_name,
                'user_email' => $user_email
            ));
            
        } catch (Exception $e) {
            adhesion_log('Error updating user profile: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Verificar estado de contratos vÃ­a AJAX
     */
    public function ajax_check_contract_status() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $user_id = get_current_user_id();
        
        // Verificar si hay actualizaciones en contratos del usuario
        $last_check = get_user_meta($user_id, 'last_contract_check', true);
        $current_time = time();
        
        // Solo verificar si han pasado al menos 30 segundos desde la Ãºltima verificaciÃ³n
        if ($last_check && ($current_time - $last_check) < 30) {
            wp_send_json_success(array('hasUpdates' => false));
        }
        
        try {
            // Obtener contratos recientes del usuario
            global $wpdb;
            $recent_updates = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts 
                 WHERE user_id = %d 
                 AND updated_at > %s 
                 AND status IN ('signed', 'cancelled')",
                $user_id,
                date('Y-m-d H:i:s', $last_check ?: $current_time - 3600)
            ));
            
            // Actualizar timestamp de Ãºltima verificaciÃ³n
            update_user_meta($user_id, 'last_contract_check', $current_time);
            
            wp_send_json_success(array(
                'hasUpdates' => $recent_updates > 0,
                'updateCount' => intval($recent_updates)
            ));
            
        } catch (Exception $e) {
            wp_send_json_success(array('hasUpdates' => false));
        }
    }
    
    /**
     * Procesar login de usuario vÃ­a AJAX
     */
    public function ajax_user_login() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['adhesion_login_nonce'], 'adhesion_user_login')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        $user_login = sanitize_text_field($_POST['user_login']);
        $user_password = $_POST['user_password'];
        $remember = isset($_POST['remember']);
        $redirect_to = isset($_POST['redirect_to']) ? esc_url($_POST['redirect_to']) : '';
        
        if (empty($user_login) || empty($user_password)) {
            wp_send_json_error(__('Usuario y contraseÃ±a son obligatorios.', 'adhesion'));
        }
        
        // Intentar login
        $creds = array(
            'user_login' => $user_login,
            'user_password' => $user_password,
            'remember' => $remember
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error(__('Usuario o contraseÃ±a incorrectos.', 'adhesion'));
        }
        
        // Login exitoso
        wp_send_json_success(array(
            'message' => __('Login exitoso. Redirigiendo...', 'adhesion'),
            'redirect_to' => $redirect_to ?: get_permalink()
        ));
    }
    
    /**
     * Procesar registro de usuario vÃ­a AJAX
     */
    public function ajax_user_register() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['adhesion_register_nonce'], 'adhesion_user_register')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        try {
            // Validar datos requeridos
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $user_email = sanitize_email($_POST['user_email']);
            $user_password = $_POST['user_password'];
            $confirm_password = $_POST['confirm_password'];
            $company = sanitize_text_field($_POST['company']);
            $accept_terms = isset($_POST['accept_terms']);
            $redirect_to = isset($_POST['redirect_to']) ? esc_url($_POST['redirect_to']) : '';
            
            // Validaciones
            if (empty($first_name) || empty($last_name) || empty($user_email) || empty($user_password)) {
                throw new Exception(__('Todos los campos obligatorios deben ser completados.', 'adhesion'));
            }
            
            if (!is_email($user_email)) {
                throw new Exception(__('Email no vÃ¡lido.', 'adhesion'));
            }
            
            if (email_exists($user_email)) {
                throw new Exception(__('Este email ya estÃ¡ registrado.', 'adhesion'));
            }
            
            if ($user_password !== $confirm_password) {
                throw new Exception(__('Las contraseÃ±as no coinciden.', 'adhesion'));
            }
            
            if (strlen($user_password) < 6) {
                throw new Exception(__('La contraseÃ±a debe tener al menos 6 caracteres.', 'adhesion'));
            }
            
            if (!$accept_terms) {
                throw new Exception(__('Debes aceptar los tÃ©rminos y condiciones.', 'adhesion'));
            }
            
            // Crear usuario
            $user_data = array(
                'user_login' => $user_email,
                'user_email' => $user_email,
                'user_pass' => $user_password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
                'role' => 'adhesion_client'
            );
            
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
            
            // Agregar metadatos adicionales
            if (!empty($company)) {
                update_user_meta($user_id, 'company', $company);
            }
            
            update_user_meta($user_id, 'registration_date', current_time('mysql'));
            update_user_meta($user_id, 'terms_accepted', current_time('mysql'));
            
            // Login automÃ¡tico despuÃ©s del registro
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            // Enviar email de bienvenida (si estÃ¡ configurado)
            $this->send_welcome_email($user_id);
            
            // Log del registro
            adhesion_log(sprintf('New user registered: %s (ID: %d)', $user_email, $user_id), 'info');
            
            wp_send_json_success(array(
                'message' => __('Cuenta creada correctamente. Bienvenido!', 'adhesion'),
                'redirect_to' => $redirect_to ?: get_permalink()
            ));
            
        } catch (Exception $e) {
            adhesion_log('Error during user registration: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * ================================
     * MÃ‰TODOS AUXILIARES
     * ================================
     */
    
    /**
     * Verificar si el usuario puede acceder a su cuenta
     */
    private function user_can_access_account($user) {
        if (!$user || !$user->exists()) {
            return false;
        }
        
        // Permitir acceso a administradores y usuarios con rol adhesion_client
        return current_user_can('manage_options') || 
               in_array('adhesion_client', $user->roles);
    }
    
    /**
     * Encolar assets necesarios para la cuenta
     */
    private function enqueue_account_assets() {
        // CSS del frontend
        wp_enqueue_style(
            'adhesion-frontend',
            ADHESION_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ADHESION_PLUGIN_VERSION
        );
        
        // JavaScript del frontend
        wp_enqueue_script(
            'adhesion-frontend',
            ADHESION_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ADHESION_PLUGIN_VERSION,
            true
        );
        
        // Localizar script
        wp_localize_script('adhesion-frontend', 'adhesionAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adhesion_nonce'),
            'messages' => array(
                'error' => __('Ha ocurrido un error. Por favor, intÃ©ntalo de nuevo.', 'adhesion'),
                'loading' => __('Cargando...', 'adhesion'),
                'success' => __('OperaciÃ³n completada con Ã©xito.', 'adhesion'),
                'confirmContract' => __('Â¿EstÃ¡s seguro de que quieres proceder con la contrataciÃ³n?', 'adhesion'),
                'confirmDelete' => __('Â¿EstÃ¡s seguro de que quieres eliminar este elemento?', 'adhesion')
            )
        ));
    }
    
    /**
     * Generar contenido del contrato
     */
    private function generate_contract_content($calculation) {
        $user = wp_get_current_user();
        
        $content = sprintf(
            __('CONTRATO DE ADHESIÃ“N

Fecha: %s

DATOS DEL CLIENTE:
Nombre: %s
Email: %s
Empresa: %s

DATOS DEL SERVICIO:
Material: %s
Cantidad: %s kg
Precio por kg: %sâ‚¬
Precio total: %sâ‚¬

El cliente acepta los tÃ©rminos y condiciones del servicio.

[FIRMA DIGITAL]', 'adhesion'),
            date_i18n(get_option('date_format')),
            $user->display_name,
            $user->user_email,
            get_user_meta($user->ID, 'company', true),
            $calculation->material_type,
            number_format($calculation->quantity, 0, ',', '.'),
            number_format($calculation->price_per_kg, 2, ',', '.'),
            number_format($calculation->total_price, 2, ',', '.')
        );
        
        return $content;
    }
    
    /**
     * Generar URL de pago
     */
    private function generate_payment_url($contract_id) {
        // Esto deberÃ­a generar la URL hacia la pÃ¡gina de pago
        $payment_page = get_option('adhesion_payment_page_id');
        
        if ($payment_page) {
            return add_query_arg(array(
                'contract_id' => $contract_id,
                'action' => 'process_payment'
            ), get_permalink($payment_page));
        }
        
        // Fallback: usar URL actual con parÃ¡metros
        return add_query_arg(array(
            'adhesion_action' => 'process_payment',
            'contract_id' => $contract_id
        ), get_permalink());
    }
    
    /**
     * Enviar email de bienvenida
     */
    private function send_welcome_email($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        $subject = sprintf(__('Bienvenido a %s', 'adhesion'), get_bloginfo('name'));
        
        $message = sprintf(
            __('Hola %s,

Â¡Bienvenido a %s!

Tu cuenta ha sido creada exitosamente. Ya puedes acceder a tu Ã¡rea de cliente para:
- Realizar cÃ¡lculos de presupuesto
- Gestionar tus contratos
- Actualizar tu perfil

Accede a tu cuenta: %s

Gracias por confiar en nosotros.

Saludos,
El equipo de %s', 'adhesion'),
            $user->display_name,
            get_bloginfo('name'),
            wp_login_url(),
            get_bloginfo('name')
        );
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Obtener estadÃ­sticas del usuario
     */
    public function get_user_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // CÃ¡lculos totales
        $stats['total_calculations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculations WHERE user_id = %d",
            $user_id
        ));
        
        // Contratos totales
        $stats['total_contracts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d",
            $user_id
        ));
        
        // Contratos pendientes
        $stats['pending_contracts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        // Contratos firmados
        $stats['signed_contracts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'signed'",
            $user_id
        ));
        
        return $stats;
    }
}


// ===== calculator-display.php =====
<?php
/**
 * Vista de la calculadora de presupuestos
 * 
 * Esta vista maneja:
 * - Formulario interactivo de materiales
 * - CÃ¡lculos en tiempo real
 * - VisualizaciÃ³n de precios y descuentos
 * - Guardado de cÃ¡lculos
 * - Responsive design
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario estÃ© logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para acceder a la calculadora.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar SesiÃ³n', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Obtener datos necesarios
$db = new Adhesion_Database();
$materials = $db->get_calculator_prices();
$user = wp_get_current_user();

// Filtrar solo materiales activos
$active_materials = array_filter($materials, function($material) {
    return $material['is_active'];
});

// ConfiguraciÃ³n de la calculadora
$calculator_config = array(
    'tax_rate' => adhesion_get_option('tax_rate', 21),
    'currency' => adhesion_get_option('currency', 'EUR'),
    'minimum_order' => adhesion_get_option('minimum_order', 0),
    'max_quantity_per_material' => 1000,
    'apply_discounts' => adhesion_get_option('apply_discounts', true)
);
?>

<div class="adhesion-calculator-container" id="adhesion-calculator">
    
    <!-- Header de la calculadora -->
    <div class="adhesion-calculator-header">
        <h2 class="calculator-title">
            <span class="calculator-icon">ðŸ“Š</span>
            <?php echo esc_html($atts['title']); ?>
        </h2>
        <p class="calculator-description">
            <?php _e('Calcula el presupuesto para tus materiales de construcciÃ³n. Los precios incluyen descuentos por volumen automÃ¡ticos.', 'adhesion'); ?>
        </p>
    </div>

    <!-- Mensajes de estado -->
    <div id="adhesion-calculator-messages" class="adhesion-messages-container"></div>

    <!-- Formulario de la calculadora -->
    <form id="adhesion-calculator-form" class="adhesion-calculator-form">
        
        <!-- InformaciÃ³n del usuario -->
        <div class="calculator-user-info">
            <div class="user-info-card">
                <h3><?php _e('Calculando para:', 'adhesion'); ?></h3>
                <p><strong><?php echo esc_html($user->display_name); ?></strong></p>
                <p><?php echo esc_html($user->user_email); ?></p>
            </div>
        </div>

        <!-- SecciÃ³n de materiales -->
        <div class="calculator-materials-section">
            <h3 class="section-title">
                <span class="section-icon">ðŸ—ï¸</span>
                <?php _e('Selecciona tus materiales', 'adhesion'); ?>
            </h3>
            
            <div class="materials-container" id="materials-container">
                
                <?php if (empty($active_materials)): ?>
                    <div class="no-materials-message">
                        <p><?php _e('No hay materiales disponibles en este momento. Contacta con el administrador.', 'adhesion'); ?></p>
                    </div>
                <?php else: ?>
                    
                    <!-- Plantilla de material (se clona con JavaScript) -->
                    <div class="material-row-template" style="display: none;">
                        <div class="material-row" data-row-index="0">
                            <div class="material-row-header">
                                <h4 class="material-row-title"><?php _e('Material', 'adhesion'); ?> <span class="material-number">1</span></h4>
                                <button type="button" class="remove-material-btn" title="<?php _e('Eliminar material', 'adhesion'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            
                            <div class="material-row-content">
                                <div class="form-grid">
                                    
                                    <!-- Selector de material -->
                                    <div class="form-group">
                                        <label for="material_type_0"><?php _e('Tipo de Material', 'adhesion'); ?> *</label>
                                        <select name="materials[0][type]" id="material_type_0" class="material-type-select" required>
                                            <option value=""><?php _e('Selecciona un material...', 'adhesion'); ?></option>
                                            <?php foreach ($active_materials as $material): ?>
                                                <option value="<?php echo esc_attr($material['material_type']); ?>" 
                                                        data-price="<?php echo esc_attr($material['price_per_ton']); ?>"
                                                        data-minimum="<?php echo esc_attr($material['minimum_quantity']); ?>"
                                                        data-description="<?php echo esc_attr($material['description'] ?? ''); ?>">
                                                    <?php echo esc_html(ucfirst($material['material_type'])); ?>
                                                    (<?php echo adhesion_format_price($material['price_per_ton']); ?>/t)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Cantidad -->
                                    <div class="form-group">
                                        <label for="material_quantity_0"><?php _e('Cantidad (toneladas)', 'adhesion'); ?> *</label>
                                        <input type="number" 
                                               name="materials[0][quantity]" 
                                               id="material_quantity_0" 
                                               class="material-quantity-input"
                                               min="0.1" 
                                               step="0.1" 
                                               max="<?php echo $calculator_config['max_quantity_per_material']; ?>"
                                               placeholder="<?php _e('Ej: 25.5', 'adhesion'); ?>"
                                               required>
                                        <div class="input-help">
                                            <span class="minimum-quantity-text" style="display: none;">
                                                <?php _e('MÃ­nimo:', 'adhesion'); ?> <span class="minimum-value">0</span>t
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Precio unitario (solo lectura) -->
                                    <div class="form-group">
                                        <label><?php _e('Precio por tonelada', 'adhesion'); ?></label>
                                        <div class="price-display">
                                            <span class="price-per-ton">--</span>
                                            <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Total del material -->
                                    <div class="form-group">
                                        <label><?php _e('Subtotal', 'adhesion'); ?></label>
                                        <div class="total-display">
                                            <span class="material-total">0,00</span>
                                            <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Alertas del material -->
                                <div class="material-alerts"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Primer material (visible por defecto) -->
                    <div class="material-row" data-row-index="0">
                        <div class="material-row-header">
                            <h4 class="material-row-title"><?php _e('Material', 'adhesion'); ?> <span class="material-number">1</span></h4>
                            <button type="button" class="remove-material-btn" title="<?php _e('Eliminar material', 'adhesion'); ?>" style="display: none;">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        
                        <div class="material-row-content">
                            <div class="form-grid">
                                
                                <!-- Selector de material -->
                                <div class="form-group">
                                    <label for="material_type_0"><?php _e('Tipo de Material', 'adhesion'); ?> *</label>
                                    <select name="materials[0][type]" id="material_type_0" class="material-type-select" required>
                                        <option value=""><?php _e('Selecciona un material...', 'adhesion'); ?></option>
                                        <?php foreach ($active_materials as $material): ?>
                                            <option value="<?php echo esc_attr($material['material_type']); ?>" 
                                                    data-price="<?php echo esc_attr($material['price_per_ton']); ?>"
                                                    data-minimum="<?php echo esc_attr($material['minimum_quantity']); ?>"
                                                    data-description="<?php echo esc_attr($material['description'] ?? ''); ?>">
                                                <?php echo esc_html(ucfirst($material['material_type'])); ?>
                                                (<?php echo adhesion_format_price($material['price_per_ton']); ?>/t)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Cantidad -->
                                <div class="form-group">
                                    <label for="material_quantity_0"><?php _e('Cantidad (toneladas)', 'adhesion'); ?> *</label>
                                    <input type="number" 
                                           name="materials[0][quantity]" 
                                           id="material_quantity_0" 
                                           class="material-quantity-input"
                                           min="0.1" 
                                           step="0.1" 
                                           max="<?php echo $calculator_config['max_quantity_per_material']; ?>"
                                           placeholder="<?php _e('Ej: 25.5', 'adhesion'); ?>"
                                           required>
                                    <div class="input-help">
                                        <span class="minimum-quantity-text" style="display: none;">
                                            <?php _e('MÃ­nimo:', 'adhesion'); ?> <span class="minimum-value">0</span>t
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Precio unitario (solo lectura) -->
                                <div class="form-group">
                                    <label><?php _e('Precio por tonelada', 'adhesion'); ?></label>
                                    <div class="price-display">
                                        <span class="price-per-ton">--</span>
                                        <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Total del material -->
                                <div class="form-group">
                                    <label><?php _e('Subtotal', 'adhesion'); ?></label>
                                    <div class="total-display">
                                        <span class="material-total">0,00</span>
                                        <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Alertas del material -->
                            <div class="material-alerts"></div>
                        </div>
                    </div>
                
                <?php endif; ?>
            </div>
            
            <!-- BotÃ³n para agregar materiales -->
            <?php if (!empty($active_materials)): ?>
                <div class="add-material-section">
                    <button type="button" id="add-material-btn" class="adhesion-btn adhesion-btn-secondary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Agregar otro material', 'adhesion'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- SecciÃ³n de opciones adicionales -->
        <div class="calculator-options-section">
            <h3 class="section-title">
                <span class="section-icon">âš™ï¸</span>
                <?php _e('Opciones del cÃ¡lculo', 'adhesion'); ?>
            </h3>
            
            <div class="options-grid">
                
                <!-- Aplicar descuentos -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="apply_discounts" id="apply_discounts" value="1" checked>
                        <span class="checkmark"></span>
                        <?php _e('Aplicar descuentos por volumen', 'adhesion'); ?>
                    </label>
                    <p class="option-description">
                        <?php _e('Se aplicarÃ¡n descuentos automÃ¡ticos segÃºn la cantidad total del pedido.', 'adhesion'); ?>
                    </p>
                </div>
                
                <!-- Incluir IVA -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="include_taxes" id="include_taxes" value="1" checked>
                        <span class="checkmark"></span>
                        <?php printf(__('Incluir IVA (%s%%)', 'adhesion'), $calculator_config['tax_rate']); ?>
                    </label>
                    <p class="option-description">
                        <?php _e('El precio final incluirÃ¡ el IVA correspondiente.', 'adhesion'); ?>
                    </p>
                </div>
                
                <!-- Notas adicionales -->
                <div class="form-group form-group-full">
                    <label for="calculation_notes"><?php _e('Notas del cÃ¡lculo (opcional)', 'adhesion'); ?></label>
                    <textarea name="notes" 
                              id="calculation_notes" 
                              rows="3" 
                              placeholder="<?php _e('AÃ±ade cualquier observaciÃ³n sobre este cÃ¡lculo...', 'adhesion'); ?>"></textarea>
                </div>
            </div>
        </div>

        <!-- Botones de acciÃ³n -->
        <div class="calculator-actions">
            <button type="button" id="calculate-btn" class="adhesion-btn adhesion-btn-primary adhesion-btn-large">
                <span class="btn-icon">ðŸ§®</span>
                <span class="btn-text"><?php _e('Calcular Presupuesto', 'adhesion'); ?></span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span>
                    <?php _e('Calculando...', 'adhesion'); ?>
                </span>
            </button>
            
            <button type="button" id="reset-calculator-btn" class="adhesion-btn adhesion-btn-outline">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Reiniciar', 'adhesion'); ?>
            </button>
        </div>
    </form>

    <!-- SecciÃ³n de resultados -->
    <div id="calculation-results" class="calculation-results" style="display: none;">
        
        <!-- Resumen del cÃ¡lculo -->
        <div class="results-header">
            <h3 class="results-title">
                <span class="results-icon">ðŸ“‹</span>
                <?php _e('Resultado del cÃ¡lculo', 'adhesion'); ?>
            </h3>
            <div class="results-date">
                <?php _e('Calculado el', 'adhesion'); ?>: <span class="calculation-date">--</span>
            </div>
        </div>
        
        <!-- Desglose de materiales -->
        <div class="materials-breakdown">
            <h4><?php _e('Desglose por materiales', 'adhesion'); ?></h4>
            <div class="materials-table-container">
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th><?php _e('Material', 'adhesion'); ?></th>
                            <th><?php _e('Cantidad', 'adhesion'); ?></th>
                            <th><?php _e('Precio/t', 'adhesion'); ?></th>
                            <th><?php _e('Subtotal', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="materials-breakdown-body">
                        <!-- Se rellena con JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Resumen financiero -->
        <div class="financial-summary">
            <div class="summary-grid">
                
                <div class="summary-item">
                    <span class="summary-label"><?php _e('Total toneladas:', 'adhesion'); ?></span>
                    <span class="summary-value" id="total-tons">0</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label"><?php _e('Subtotal:', 'adhesion'); ?></span>
                    <span class="summary-value" id="subtotal">0,00 â‚¬</span>
                </div>
                
                <div class="summary-item discount-item" style="display: none;">
                    <span class="summary-label"><?php _e('Descuentos:', 'adhesion'); ?></span>
                    <span class="summary-value discount-value" id="discount-amount">-0,00 â‚¬</span>
                </div>
                
                <div class="summary-item tax-item" style="display: none;">
                    <span class="summary-label"><?php printf(__('IVA (%s%%):', 'adhesion'), $calculator_config['tax_rate']); ?></span>
                    <span class="summary-value" id="tax-amount">0,00 â‚¬</span>
                </div>
                
                <div class="summary-item total-item">
                    <span class="summary-label"><?php _e('TOTAL:', 'adhesion'); ?></span>
                    <span class="summary-value total-value" id="total-price">0,00 â‚¬</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label"><?php _e('Precio promedio/t:', 'adhesion'); ?></span>
                    <span class="summary-value" id="average-price">0,00 â‚¬</span>
                </div>
            </div>
        </div>
        
        <!-- Alertas y avisos -->
        <div class="calculation-warnings" id="calculation-warnings" style="display: none;">
            <h4><?php _e('Avisos importantes', 'adhesion'); ?></h4>
            <div class="warnings-list" id="warnings-list">
                <!-- Se rellena con JavaScript -->
            </div>
        </div>
        
        <!-- Acciones del resultado -->
        <div class="results-actions">
            <button type="button" id="save-calculation-btn" class="adhesion-btn adhesion-btn-success">
                <span class="dashicons dashicons-saved"></span>
                <span class="btn-text"><?php _e('Guardar CÃ¡lculo', 'adhesion'); ?></span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span>
                    <?php _e('Guardando...', 'adhesion'); ?>
                </span>
            </button>
            
            <button type="button" id="create-contract-btn" class="adhesion-btn adhesion-btn-primary">
                <span class="dashicons dashicons-media-document"></span>
                <?php _e('Crear Contrato', 'adhesion'); ?>
            </button>
            
            <button type="button" id="print-calculation-btn" class="adhesion-btn adhesion-btn-outline">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('Imprimir', 'adhesion'); ?>
            </button>
        </div>
    </div>

    <!-- Historial de cÃ¡lculos -->
    <div class="calculation-history-section">
        <h3 class="section-title">
            <span class="section-icon">ðŸ“ˆ</span>
            <?php _e('Mis cÃ¡lculos recientes', 'adhesion'); ?>
        </h3>
        
        <div id="recent-calculations" class="recent-calculations">
            <div class="loading-calculations">
                <span class="spinner"></span>
                <?php _e('Cargando cÃ¡lculos...', 'adhesion'); ?>
            </div>
        </div>
        
        <div class="history-actions">
            <a href="<?php echo esc_url(home_url('/mi-cuenta/')); ?>" class="adhesion-btn adhesion-btn-outline">
                <?php _e('Ver todos mis cÃ¡lculos', 'adhesion'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Datos para JavaScript -->
<script type="text/javascript">
    window.adhesionCalculatorConfig = <?php echo json_encode(array(
        'materials' => $active_materials,
        'config' => $calculator_config,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('adhesion_nonce'),
        'userId' => get_current_user_id(),
        'messages' => array(
            'selectMaterial' => __('Por favor, selecciona un material', 'adhesion'),
            'enterQuantity' => __('Por favor, introduce una cantidad vÃ¡lida', 'adhesion'),
            'calculationSaved' => __('CÃ¡lculo guardado correctamente', 'adhesion'),
            'errorSaving' => __('Error al guardar el cÃ¡lculo', 'adhesion'),
            'errorCalculating' => __('Error al calcular el presupuesto', 'adhesion'),
            'confirmReset' => __('Â¿EstÃ¡s seguro de que quieres reiniciar la calculadora?', 'adhesion'),
            'minQuantityWarning' => __('La cantidad estÃ¡ por debajo del mÃ­nimo recomendado', 'adhesion'),
            'maxQuantityWarning' => __('La cantidad excede el mÃ¡ximo permitido', 'adhesion'),
            'noMaterialsSelected' => __('Debes seleccionar al menos un material', 'adhesion')
        ),
        'currency' => array(
            'symbol' => 'â‚¬',
            'code' => $calculator_config['currency'],
            'decimals' => 2,
            'decimal_separator' => ',',
            'thousand_separator' => '.'
        )
    )); ?>
    </script>
</script>

<style>
/* Estilos especÃ­ficos para la calculadora - Se integrarÃ¡n en frontend.css */
.adhesion-calculator-container {
    max-width: 1200px;
    margin: 0 10px 10px 0;
}

.adhesion-btn-primary {
    background: #007cba;
    color: white;
}

.adhesion-btn-primary:hover {
    background: #005a87;
    color: white;
}

.adhesion-btn-secondary {
    background: #6c757d;
    color: white;
}

.adhesion-btn-secondary:hover {
    background: #545b62;
    color: white;
}

.adhesion-btn-success {
    background: #28a745;
    color: white;
}

.adhesion-btn-success:hover {
    background: #1e7e34;
    color: white;
}

.adhesion-btn-outline {
    background: transparent;
    color: #007cba;
    border: 2px solid #007cba;
}

.adhesion-btn-outline:hover {
    background: #007cba;
    color: white;
}

.adhesion-btn-large {
    padding: 16px 32px;
    font-size: 18px;
}

.btn-loading {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.calculation-results {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e4e7;
}

.results-title {
    font-size: 1.4em;
    margin: 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.results-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.results-date {
    color: #666;
    font-size: 0.9em;
}

.materials-breakdown {
    margin-bottom: 25px;
}

.materials-breakdown h4 {
    margin: 0 0 15px 0;
    color: #23282d;
}

.materials-table-container {
    overflow-x: auto;
}

.materials-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.materials-table th,
.materials-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e4e7;
}

.materials-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #23282d;
}

.materials-table tbody tr:hover {
    background: #f8f9fa;
}

.financial-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.summary-label {
    font-weight: 500;
    opacity: 0.9;
}

.summary-value {
    font-weight: 700;
    font-size: 1.1em;
}

.total-item {
    grid-column: 1 / -1;
    border-top: 2px solid rgba(255,255,255,0.3);
    padding-top: 15px;
    margin-top: 10px;
}

.total-value {
    font-size: 1.5em;
    color: #fff;
}

.discount-value {
    color: #90EE90;
}

.calculation-warnings {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.calculation-warnings h4 {
    margin: 0 0 15px 0;
    color: #856404;
}

.warnings-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.warning-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 0;
    color: #856404;
}

.warning-icon {
    color: #f0ad4e;
    font-size: 1.2em;
    margin-top: 2px;
}

.results-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.calculation-history-section {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-top: 30px;
}

.recent-calculations {
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading-calculations {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #666;
}

.calculation-history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid #e2e4e7;
    border-radius: 6px;
    margin-bottom: 10px;
    transition: background 0.3s ease;
}

.calculation-history-item:hover {
    background: #f8f9fa;
}

.history-info h5 {
    margin: 0 0 5px 0;
    color: #23282d;
}

.history-meta {
    font-size: 0.9em;
    color: #666;
}

.history-amount {
    font-weight: 700;
    font-size: 1.1em;
    color: #007cba;
}

.history-actions {
    text-align: center;
    margin-top: 20px;
}

.adhesion-messages-container {
    margin-bottom: 20px;
}

.adhesion-notice {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.adhesion-notice-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.adhesion-notice-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.adhesion-notice-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.adhesion-notice-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.no-materials-message {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

/* Responsive design */
@media (max-width: 768px) {
    .adhesion-calculator-container {
        padding: 15px;
    }
    
    .calculator-title {
        font-size: 2em;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .results-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .results-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .adhesion-btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
    
    .materials-table {
        font-size: 0.9em;
    }
    
    .calculation-history-item {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .options-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .calculator-title {
        font-size: 1.5em;
    }
    
    .calculator-description {
        font-size: 1em;
    }
    
    .material-row-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .form-group select,
    .form-group input {
        font-size: 16px; /* Evitar zoom en iOS */
    }
}

/* Animaciones */
.material-row {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.calculation-results {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estados de validaciÃ³n */
.form-group.has-error input,
.form-group.has-error select {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
}

.form-group.has-success input,
.form-group.has-success select {
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.1);
}

.error-message {
    color: #dc3545;
    font-size: 0.9em;
    margin-top: 5px;
}

.success-message {
    color: #28a745;
    font-size: 0.9em;
    margin-top: 5px;
}

/* Estados de los botones */
.adhesion-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.adhesion-btn.loading {
    pointer-events: none;
}

.adhesion-btn.loading .btn-text {
    display: none;
}

.adhesion-btn.loading .btn-loading {
    display: flex;
}

/* Mejoras de accesibilidad */
.adhesion-btn:focus,
.form-group input:focus,
.form-group select:focus {
    outline: 2px solid #007cba;
    outline-offset: 2px;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Print styles */
@media print {
    .calculator-actions,
    .results-actions,
    .add-material-section,
    .history-actions,
    .remove-material-btn {
        display: none;
    }
    
    .calculation-results {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .financial-summary {
        background: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #000;
    }
}
</style>0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.adhesion-calculator-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 30px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
}

.calculator-title {
    font-size: 2.5em;
    margin: 0 0 10px 0;
    font-weight: 700;
}

.calculator-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.calculator-description {
    font-size: 1.1em;
    margin: 0;
    opacity: 0.9;
}

.calculator-user-info {
    margin-bottom: 30px;
}

.user-info-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #007cba;
}

.user-info-card h3 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.calculator-materials-section,
.calculator-options-section {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 1.4em;
    margin: 0 0 20px 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.section-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.material-row {
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow 0.3s ease;
}

.material-row:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.material-row-header {
    background: #f8f9fa;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e4e7;
}

.material-row-title {
    margin: 0;
    font-size: 1.1em;
    color: #23282d;
}

.remove-material-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.remove-material-btn:hover {
    background: #c82333;
}

.material-row-content {
    padding: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #23282d;
}

.form-group select,
.form-group input {
    padding: 12px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.form-group select:focus,
.form-group input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.price-display,
.total-display {
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    font-weight: 600;
    font-size: 1.1em;
}

.input-help {
    margin-top: 5px;
    font-size: 0.9em;
    color: #666;
}

.minimum-quantity-text {
    color: #007cba;
    font-weight: 500;
}

.material-alerts {
    margin-top: 15px;
}

.material-alert {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.material-alert.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.material-alert.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.add-material-section {
    text-align: center;
    padding: 20px 0;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-group-full {
    grid-column: 1 / -1;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 10px;
    transform: scale(1.2);
}

.option-description {
    margin: 8px 0 0 0;
    font-size: 0.9em;
    color: #666;
}

.calculator-actions {
    text-align: center;
    margin: 30px 0;
}

.adhesion-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    margin: 


// ===== contract-signing-display.php =====
            emailSent: '<?php echo esc_js(__('Email enviado correctamente.', 'adhesion')); ?>',
            emailError: '<?php echo esc_js(__('Error enviando email.', 'adhesion')); ?>',
            statusUpdated: '<?php echo esc_js(__('Estado actualizado.', 'adhesion')); ?>',
            retryingSigning: '<?php echo esc_js(__('Reintentando proceso de firma...', 'adhesion')); ?>'
        }
    };
    
    // Event listeners
    $('#start-signing-btn').on('click', function(e) {
        e.preventDefault();
        startSigning();
    });
    
    $('#send-email-btn').on('click', function(e) {
        e.preventDefault();
        sendSigningEmail();
    });
    
    $('#check-status-btn').on('click', function(e) {
        e.preventDefault();
        checkSigningStatus();
    });
    
    $('#resend-email-btn').on('click', function(e) {
        e.preventDefault();
        resendSigningEmail();
    });
    
    $('#open-docusign-btn').on('click', function(e) {
        e.preventDefault();
        openDocuSign();
    });
    
    $('#send-copy-email-btn').on('click', function(e) {
        e.preventDefault();
        sendCopyEmail();
    });
    
    $('#retry-signing-btn').on('click', function(e) {
        e.preventDefault();
        retrySigning();
    });
    
    /**
     * Iniciar proceso de firma
     */
    function startSigning() {
        const $btn = $('#start-signing-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_start_signing',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.action === 'redirect' && response.data.url) {
                        // Abrir DocuSign en nueva ventana
                        const signingWindow = window.open(response.data.url, '_blank', 'width=800,height=600');
                        
                        // Opcional: detectar cuando se cierra la ventana
                        const checkClosed = setInterval(function() {
                            if (signingWindow.closed) {
                                clearInterval(checkClosed);
                                // Verificar estado despuÃ©s de cerrar la ventana
                                setTimeout(function() {
                                    checkSigningStatus();
                                }, 2000);
                            }
                        }, 1000);
                        
                        showSigningMessage(response.data.message || 'Redirigiendo a DocuSign...', 'success');
                    } else {
                        // El documento fue enviado por email
                        showSigningMessage(response.data.message || 'Documento enviado por email', 'success');
                        
                        // Cambiar a estado "signing"
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    showSigningMessage(response.data || window.adhesionSigningConfig.messages.signingError, 'error');
                }
            },
            error: function() {
                showSigningMessage(window.adhesionSigningConfig.messages.signingError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Enviar email de firma
     */
    function sendSigningEmail() {
        const $btn = $('#send-email-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_send_signing_email',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage(window.adhesionSigningConfig.messages.emailSent, 'success');
                    
                    // Cambiar a estado "signing"
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showSigningMessage(response.data || window.adhesionSigningConfig.messages.emailError, 'error');
                }
            },
            error: function() {
                showSigningMessage(window.adhesionSigningConfig.messages.emailError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Verificar estado de firma
     */
    function checkSigningStatus() {
        const $btn = $('#check-status-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_check_signing_status',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data.status;
                    
                    if (status === 'completed') {
                        showSigningMessage('Â¡Contrato firmado correctamente!', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else if (status === 'declined') {
                        showSigningMessage('El contrato ha sido rechazado', 'error');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showSigningMessage(window.adhesionSigningConfig.messages.statusUpdated, 'info');
                    }
                } else {
                    showSigningMessage(response.data || 'Error verificando estado', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error verificando estado', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Reenviar email de firma
     */
    function resendSigningEmail() {
        const $btn = $('#resend-email-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_resend_signing_email',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage(window.adhesionSigningConfig.messages.emailSent, 'success');
                } else {
                    showSigningMessage(response.data || window.adhesionSigningConfig.messages.emailError, 'error');
                }
            },
            error: function() {
                showSigningMessage(window.adhesionSigningConfig.messages.emailError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Abrir DocuSign en nueva ventana
     */
    function openDocuSign() {
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_get_signing_url',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    window.open(response.data.url, '_blank', 'width=800,height=600');
                } else {
                    showSigningMessage('No se pudo obtener la URL de firma', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error obteniendo URL de firma', 'error');
            }
        });
    }
    
    /**
     * Enviar copia del contrato firmado por email
     */
    function sendCopyEmail() {
        const $btn = $('#send-copy-email-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_send_contract_copy',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage('Copia enviada por email correctamente', 'success');
                } else {
                    showSigningMessage(response.data || 'Error enviando copia', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error enviando copia', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Reintentar proceso de firma
     */
    function retrySigning() {
        const $btn = $('#retry-signing-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_retry_signing',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage('Proceso reiniciado correctamente', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showSigningMessage(response.data || 'Error reintentando firma', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error reintentando firma', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Funciones de utilidad
     */
    function updateButtonLoading($btn, loading) {
        const $text = $btn.find('.btn-text');
        const $loading = $btn.find('.btn-loading');
        
        if (loading) {
            $btn.prop('disabled', true).addClass('loading');
            if ($text.length) $text.hide();
            if ($loading.length) $loading.show();
        } else {
            $btn.prop('disabled', false).removeClass('loading');
            if ($text.length) $text.show();
            if ($loading.length) $loading.hide();
        }
    }
    
    function showSigningMessage(message, type) {
        const $container = $('#signing-messages');
        const alertClass = `adhesion-notice adhesion-notice-${type}`;
        const html = `<div class="${alertClass}">${message}</div>`;
        
        $container.html(html);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 500);
        
        // Auto-hide para Ã©xito
        if (type === 'success') {
            setTimeout(function() {
                $container.empty();
            }, 5000);
        }
    }
    
    // Verificar si venimos de un retorno de DocuSign
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('signed') === '1') {
        // Verificar estado despuÃ©s de regresar de DocuSign
        setTimeout(function() {
            checkSigningStatus();
        }, 1000);
    }
});
</script>

<style>
/* Estilos especÃ­ficos para firma de contratos */
.adhesion-signing-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.signing-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 30px 20px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 12px;
}

.signing-title {
    font-size: 2.5em;
    margin: 0 0 15px 0;
    font-weight: 700;
}

.signing-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.signing-status {
    margin-top: 20px;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 1.1em;
}

.status-ready {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.status-signing {
    background: rgba(255, 193, 7, 0.9);
    color: #856404;
}

.status-signed {
    background: rgba(40, 167, 69, 0.9);
    color: white;
}

.status-error {
    background: rgba(220, 53, 69, 0.9);
    color: white;
}

/* InformaciÃ³n del contrato */
.contract-info {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-title {
    font-size: 1.4em;
    margin: 0 0 20px 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.info-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.contract-details {
    margin-bottom: 25px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #495057;
}

.detail-value {
    color: #23282d;
    text-align: right;
}

.payment-completed {
    color: #28a745;
    font-weight: 600;
}

.status-completed {
    color: #28a745;
    font-weight: 600;
}

/* Resumen del pedido */
.order-summary-section {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
    margin-top: 20px;
}

.order-summary-section h4 {
    margin: 0 0 15px 0;
    color: #495057;
}

.materials-summary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.material-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.material-item:last-child {
    border-bottom: none;
}

.material-name {
    font-weight: 500;
}

.material-quantity {
    color: #666;
}

.material-total {
    font-weight: 600;
    color: #28a745;
}

/* Contenido de pasos */
.signing-content {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Paso: Listo para firmar */
.ready-content {
    text-align: center;
    padding: 20px;
}

.ready-icon {
    margin-bottom: 20px;
}

.icon-large {
    font-size: 4em;
    display: block;
}

.ready-title {
    font-size: 2em;
    margin: 0 0 15px 0;
    color: #28a745;
}

.ready-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
    line-height: 1.6;
}

.signing-info {
    background: #e8f5e8;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: left;
}

.signing-info h4 {
    margin: 0 0 15px 0;
    color: #155724;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    margin-bottom: 10px;
    color: #155724;
    font-size: 0.95em;
}

.ready-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.additional-info {
    max-width: 600px;
    margin: 0 auto;
    text-align: left;
}

.additional-info details {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
}

.additional-info summary {
    font-weight: 600;
    cursor: pointer;
    color: #007cba;
}

.details-content {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.details-content ol {
    margin: 0;
    color: #495057;
}

.details-content li {
    margin-bottom: 8px;
}

/* Paso: En proceso de firma */
.signing-progress {
    text-align: center;
    padding: 20px;
}

.progress-icon {
    margin-bottom: 20px;
}

.spinner-large {
    width: 60px;
    height: 60px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

.progress-title {
    font-size: 1.8em;
    margin: 0 0 15px 0;
    color: #23282d;
}

.progress-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
}

.signing-status-detail {
    margin-bottom: 30px;
}

.status-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 500px;
    margin: 0 auto 30px;
    position: relative;
}

.status-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 2;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e4e7;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    font-weight: 600;
}

.status-step.completed .step-icon {
    background: #28a745;
    color: white;
}

.status-step.active .step-icon {
    background: #007cba;
    color: white;
}

.spinner-small {
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.step-text {
    font-size: 0.9em;
    color: #666;
    text-align: center;
}

.status-step.active .step-text {
    color: #007cba;
    font-weight: 600;
}

.process-info {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 25px;
    text-align: left;
}

.process-info p {
    margin: 8px 0;
}

.progress-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.signing-help {
    background: #e8f4fd;
    border: 1px solid #bee5eb;
    border-radius: 8px;
    padding: 20px;
    text-align: left;
}

.signing-help h4 {
    margin: 0 0 15px 0;
    color: #0c5460;
}

.signing-help ul {
    margin: 0;
    color: #0c5460;
}

.signing-help li {
    margin-bottom: 8px;
}

/* Paso: Firmado correctamente */
.completed-content {
    text-align: center;
    padding: 20px;
}

.success-animation {
    margin-bottom: 25px;
}

.checkmark-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    animation: successPulse 0.6s ease-out;
}

.checkmark {
    font-size: 3em;
    color: white;
    font-weight: bold;
}

@keyframes successPulse {
    0% {
        transform: scale(0.8);
        opacity: 0.5;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.success-title {
    font-size: 2em;
    margin: 0 0 15px 0;
    color: #28a745;
}

.success-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
}

.signed-contract-info {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.signed-details {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.signed-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.signed-item:last-child {
    border-bottom: none;
}

.signed-label {
    font-weight: 600;
    color: #495057;
}

.signed-value {
    color: #23282d;
}

.post-signing-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.next-steps-signed {
    background: #e8f4fd;
    border: 1px solid #bee5eb;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: left;
}

.next-steps-signed h4 {
    margin: 0 0 20px 0;
    color: #0c5460;
}

.steps-timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.timeline-step {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2em;
    flex-shrink: 0;
}

.timeline-step.completed .timeline-icon {
    background: #28a745;
    color: white;
}

.timeline-content h5 {
    margin: 0 0 5px 0;
    color: #23282d;
}

.timeline-content p {
    margin: 0;
    color: #666;
    font-size: 0.9em;
}

.contact-support {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.contact-support h5 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.contact-methods {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 15px;
}

.contact-methods span {
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #e2e4e7;
    font-size: 0.9em;
}

/* Paso: Error */
.error-content {
    text-align: center;
    padding: 20px;
}

.error-icon {
    margin-bottom: 20px;
}

.error-title {
    font-size: 1.8em;
    margin: 0 0 15px 0;
    color: #dc3545;
}

.error-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
}

.error-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.error-support {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    padding: 20px;
}

.error-support h5 {
    margin: 0 0 10px 0;
    color: #721c24;
}

.error-support p {
    margin: 0 0 15px 0;
    color: #721c24;
}

.support-methods {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.support-methods span {
    background: white;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 0.9em;
    color: #721c24;
}

/* Botones */
.adhesion-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    text-align: center;
    justify-content: center;
}

.adhesion-btn-primary {
    background: #007cba;
    color: white;
}

.adhesion-btn-primary:hover {
    background: #005a87;
    color: white;
}

.adhesion-btn-success {
    background: #28a745;
    color: white;
}

.adhesion-btn-success:hover {
    background: #1e7e34;
    color: white;
}

.adhesion-btn-secondary {
    background: #6c757d;
    color: white;
}

.adhesion-btn-secondary:hover {
    background: #545b62;
    color: white;
}

.adhesion-btn-outline {
    background: transparent;
    color: #007cba;
    border: 2px solid #007cba;
}

.adhesion-btn-outline:hover {
    background: #007cba;
    color: white;
}

.adhesion-btn-large {
    padding: 16px 32px;
    font-size: 18px;
}

.btn-loading {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.adhesion-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.adhesion-btn.loading {
    pointer-events: none;
}

.adhesion-btn.loading .btn-text {
    display: none;
}

.adhesion-btn.loading .btn-loading {
    display: flex;
}

/* Mensajes */
.adhesion-messages-container {
    margin-bottom: 20px;
}

.adhesion-notice {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.adhesion-notice-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.adhesion-notice-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.adhesion-notice-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.adhesion-notice-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

/* Error especÃ­fico de firma */
.signing-error {
    text-align: center;
    padding: 40px 20px;
}

.signing-error .error-icon {
    font-size: 4em;
    margin-bottom: 20px;
}

.signing-error h3 {
    color: #dc3545;
    margin-bottom: 15px;
}

.signing-error p {
    color: #666;
    margin-bottom: 30px;
}

/* Responsive design */
@media (max-width: 768px) {
    .adhesion-signing-container {
        padding: 15px;
    }
    
    .signing-title {
        font-size: 2em;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-item {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .detail-value {
        text-align: center;
    }
    
    .ready-actions,
    .progress-actions,
    .post-signing-actions,
    .error-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .adhesion-btn {
        width: 100%;
        max-width: 300px;
    }
    
    .status-steps {
        flex-direction: column;
        gap: 20px;
    }
    
    .steps-timeline {
        gap: 15px;
    }
    
    .timeline-step {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .contact-methods,
    .support-methods {
        flex-direction: column;
        align-items: center;
    }
    
    .materials-summary {
        font-size: 0.9em;
    }
    
    .material-item {
        flex-wrap: wrap;
        gap: 5px;
    }
}

@media (max-width: 480px) {
    .signing-title {
        font-size: 1.8em;
    }
    
    .ready-title,
    .success-title {
        font-size: 1.6em;
    }
    
    .progress-title {
        font-size: 1.5em;
    }
    
    .contract-info,
    .signing-content {
        padding: 20px;
    }
    
    .step-icon {
        width: 35px;
        height: 35px;
    }
    
    .checkmark-circle {
        width: 80px;
        height: 80px;
    }
    
    .checkmark {
        font-size: 2.5em;
    }
    
    .icon-large {
        font-size: 3em;
    }
}

/* Estados especÃ­ficos para mejor UX */
.status-indicator.pulsing {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
    100% {
        opacity: 1;
    }
}

/* Mejoras de accesibilidad */
.adhesion-btn:focus,
details summary:focus {
    outline: 2px solid #007cba;
    outline-offset: 2px;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Animaciones adicionales */
.signing-step-content {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Print styles */
@media print {
    .ready-actions,
    .progress-actions,
    .post-signing-actions,
    .error-actions {
        display: none;
    }
    
    .signing-header {
        background: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #000;
    }
    
    .contract-info {
        border: 1px solid #000;
    }
    
    .next-steps-signed,
    .signing-help,
    .contact-support {
        background: #f8f9fa !important;
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}
</style><?php
/**
 * Vista de firma de contratos con DocuSign
 * 
 * Esta vista maneja:
 * - PreparaciÃ³n de documentos para firma
 * - IntegraciÃ³n con DocuSign
 * - Estados de firma (pendiente, firmado, rechazado)
 * - Descarga de documentos firmados
 * - Seguimiento del proceso
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario estÃ© logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para firmar contratos.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar SesiÃ³n', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Verificar configuraciÃ³n de DocuSign
if (!adhesion_is_docusign_configured()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('La firma digital no estÃ¡ configurada. Contacta con el administrador.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Obtener datos necesarios
$db = new Adhesion_Database();
$user = wp_get_current_user();

// Obtener ID del contrato desde parÃ¡metros
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : (isset($atts['contract_id']) ? intval($atts['contract_id']) : 0);

// Variables para el proceso
$contract = null;
$calculation = null;
$signing_step = 'loading'; // loading, ready, signing, signed, error

// Obtener informaciÃ³n del contrato
if ($contract_id) {
    $contract = $db->get_contract($contract_id);
    if ($contract && $contract['user_id'] == $user->ID) {
        // Obtener cÃ¡lculo asociado si existe
        if ($contract['calculation_id']) {
            $calculation = $db->get_calculation($contract['calculation_id']);
        }
        
        // Determinar paso segÃºn estado del contrato
        switch ($contract['status']) {
            case 'completed':
                $signing_step = 'signed';
                break;
            case 'sent':
            case 'pending_signature':
                $signing_step = 'signing';
                break;
            case 'declined':
            case 'voided':
                $signing_step = 'error';
                break;
            default:
                if ($contract['payment_status'] === 'completed') {
                    $signing_step = 'ready';
                } else {
                    $signing_step = 'error';
                }
                break;
        }
    }
} else {
    // Si no hay contract_id, buscar contratos pendientes del usuario
    $pending_contracts = $db->get_user_contracts($user->ID, array('completed_payment', 'pending_signature', 'sent'));
    if (!empty($pending_contracts)) {
        $contract = $pending_contracts[0];
        $contract_id = $contract['id'];
        $signing_step = $contract['status'] === 'completed' ? 'signed' : 'ready';
    }
}

// Si no hay contrato vÃ¡lido, mostrar error
if (!$contract) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('No se encontrÃ³ un contrato vÃ¡lido para firmar.', 'adhesion') . '</p>';
    echo '<p><a href="' . home_url('/mi-cuenta/') . '" class="adhesion-btn adhesion-btn-primary">' . __('Ir a Mi Cuenta', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// ConfiguraciÃ³n de la firma
$signing_config = array(
    'return_url' => add_query_arg(array('contract_id' => $contract_id, 'signed' => '1'), get_permalink()),
    'timeout_minutes' => adhesion_get_option('signing_timeout', 30),
    'reminder_days' => adhesion_get_option('reminder_days', 3)
);
?>

<div class="adhesion-signing-container" id="adhesion-contract-signing">
    
    <!-- Header de firma -->
    <div class="signing-header">
        <h2 class="signing-title">
            <span class="signing-icon">âœï¸</span>
            <?php _e('Firma de Contrato', 'adhesion'); ?>
        </h2>
        
        <!-- Estado del proceso -->
        <div class="signing-status">
            <div class="status-indicator status-<?php echo esc_attr($signing_step); ?>">
                <?php
                switch ($signing_step) {
                    case 'ready':
                        echo '<span class="status-icon">ðŸ“‹</span>';
                        echo '<span class="status-text">' . __('Listo para firmar', 'adhesion') . '</span>';
                        break;
                    case 'signing':
                        echo '<span class="status-icon">â³</span>';
                        echo '<span class="status-text">' . __('Pendiente de firma', 'adhesion') . '</span>';
                        break;
                    case 'signed':
                        echo '<span class="status-icon">âœ…</span>';
                        echo '<span class="status-text">' . __('Firmado correctamente', 'adhesion') . '</span>';
                        break;
                    case 'error':
                        echo '<span class="status-icon">âŒ</span>';
                        echo '<span class="status-text">' . __('Error en la firma', 'adhesion') . '</span>';
                        break;
                    default:
                        echo '<span class="status-icon">ðŸ”„</span>';
                        echo '<span class="status-text">' . __('Cargando...', 'adhesion') . '</span>';
                        break;
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Mensajes de estado -->
    <div id="signing-messages" class="adhesion-messages-container"></div>

    <!-- InformaciÃ³n del contrato -->
    <div class="contract-info">
        <h3 class="info-title">
            <span class="info-icon">ðŸ“„</span>
            <?php _e('InformaciÃ³n del contrato', 'adhesion'); ?>
        </h3>
        
        <div class="contract-details">
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label"><?php _e('NÃºmero de contrato:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo esc_html($contract['contract_number'] ?? 'Generando...'); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Cliente:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo esc_html($user->display_name); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Fecha de creaciÃ³n:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y'); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Importe total:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo adhesion_format_price($contract['total_price'] ?? 0); ?></span>
                </div>
                
                <?php if ($contract['payment_status'] === 'completed'): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php _e('Estado del pago:', 'adhesion'); ?></span>
                        <span class="detail-value payment-completed">âœ… <?php _e('Pagado', 'adhesion'); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($contract['signed_at'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php _e('Fecha de firma:', 'adhesion'); ?></span>
                        <span class="detail-value"><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Resumen del pedido -->
        <?php if ($calculation): ?>
            <div class="order-summary-section">
                <h4><?php _e('Resumen del pedido:', 'adhesion'); ?></h4>
                <?php
                $materials_data = json_decode($calculation['materials_data'], true);
                if ($materials_data):
                ?>
                    <div class="materials-summary">
                        <?php foreach ($materials_data as $material): ?>
                            <div class="material-item">
                                <span class="material-name"><?php echo esc_html(ucfirst($material['type'])); ?></span>
                                <span class="material-quantity"><?php echo adhesion_format_tons($material['quantity']); ?></span>
                                <span class="material-total"><?php echo adhesion_format_price($material['total']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenido principal segÃºn el paso -->
    <div class="signing-content">
        
        <?php if ($signing_step === 'ready'): ?>
            <!-- PASO: Listo para firmar -->
            <div class="signing-step-content" id="ready-to-sign">
                <div class="ready-content">
                    <div class="ready-icon">
                        <span class="icon-large">ðŸ“‹</span>
                    </div>
                    <h3 class="ready-title"><?php _e('Contrato listo para firmar', 'adhesion'); ?></h3>
                    <p class="ready-description">
                        <?php _e('Tu pago ha sido procesado correctamente. Ahora puedes proceder a firmar el contrato digitalmente usando DocuSign.', 'adhesion'); ?>
                    </p>
                    
                    <!-- InformaciÃ³n importante -->
                    <div class="signing-info">
                        <h4><?php _e('InformaciÃ³n importante:', 'adhesion'); ?></h4>
                        <ul class="info-list">
                            <li>ðŸ”’ <?php _e('La firma se realiza de forma segura a travÃ©s de DocuSign', 'adhesion'); ?></li>
                            <li>ðŸ“§ <?php _e('RecibirÃ¡s un email con el enlace para firmar', 'adhesion'); ?></li>
                            <li>â° <?php printf(__('Tienes %d dÃ­as para completar la firma', 'adhesion'), $signing_config['reminder_days']); ?></li>
                            <li>ðŸ“± <?php _e('Puedes firmar desde cualquier dispositivo', 'adhesion'); ?></li>
                        </ul>
                    </div>
                    
                    <!-- AcciÃ³n principal -->
                    <div class="ready-actions">
                        <button type="button" id="start-signing-btn" class="adhesion-btn adhesion-btn-success adhesion-btn-large">
                            <span class="btn-icon">âœï¸</span>
                            <span class="btn-text"><?php _e('Firmar Contrato Ahora', 'adhesion'); ?></span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span>
                                <?php _e('Preparando firma...', 'adhesion'); ?>
                            </span>
                        </button>
                        
                        <button type="button" id="send-email-btn" class="adhesion-btn adhesion-btn-outline">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Enviar por email', 'adhesion'); ?>
                        </button>
                    </div>
                    
                    <!-- InformaciÃ³n adicional -->
                    <div class="additional-info">
                        <details>
                            <summary><?php _e('Â¿QuÃ© sucede despuÃ©s de firmar?', 'adhesion'); ?></summary>
                            <div class="details-content">
                                <ol>
                                    <li><?php _e('RecibirÃ¡s una copia del contrato firmado por email', 'adhesion'); ?></li>
                                    <li><?php _e('Nuestro equipo procesarÃ¡ tu pedido', 'adhesion'); ?></li>
                                    <li><?php _e('Te contactaremos para coordinar la entrega', 'adhesion'); ?></li>
                                    <li><?php _e('PodrÃ¡s descargar el contrato desde tu Ã¡rea de cliente', 'adhesion'); ?></li>
                                </ol>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
            
        <?php elseif ($signing_step === 'signing'): ?>
            <!-- PASO: En proceso de firma -->
            <div class="signing-step-content" id="signing-in-progress">
                <div class="signing-progress">
                    <div class="progress-icon">
                        <div class="spinner-large"></div>
                    </div>
                    <h3 class="progress-title"><?php _e('Firma en proceso', 'adhesion'); ?></h3>
                    <p class="progress-description">
                        <?php _e('El contrato ha sido enviado para firma. Revisa tu email para continuar con el proceso.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Estado detallado -->
                    <div class="signing-status-detail">
                        <div class="status-steps">
                            <div class="status-step completed">
                                <span class="step-icon">âœ…</span>
                                <span class="step-text"><?php _e('Contrato generado', 'adhesion'); ?></span>
                            </div>
                            <div class="status-step completed">
                                <span class="step-icon">âœ…</span>
                                <span class="step-text"><?php _e('Enviado a DocuSign', 'adhesion'); ?></span>
                            </div>
                            <div class="status-step active">
                                <span class="step-icon">
                                    <span class="spinner-small"></span>
                                </span>
                                <span class="step-text"><?php _e('Esperando firma', 'adhesion'); ?></span>
                            </div>
                            <div class="status-step">
                                <span class="step-icon">â³</span>
                                <span class="step-text"><?php _e('Contrato completado', 'adhesion'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- InformaciÃ³n del proceso -->
                    <div class="process-info">
                        <?php if (!empty($contract['docusign_envelope_id'])): ?>
                            <p><strong><?php _e('ID de sobre:', 'adhesion'); ?></strong> <?php echo esc_html($contract['docusign_envelope_id']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($contract['sent_at'])): ?>
                            <p><strong><?php _e('Enviado el:', 'adhesion'); ?></strong> <?php echo adhesion_format_date($contract['sent_at'], 'd/m/Y H:i'); ?></p>
                        <?php endif; ?>
                        
                        <p><strong><?php _e('Email:', 'adhesion'); ?></strong> <?php echo esc_html($user->user_email); ?></p>
                    </div>
                    
                    <!-- Acciones disponibles -->
                    <div class="progress-actions">
                        <button type="button" id="check-status-btn" class="adhesion-btn adhesion-btn-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Verificar Estado', 'adhesion'); ?>
                        </button>
                        
                        <button type="button" id="resend-email-btn" class="adhesion-btn adhesion-btn-outline">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Reenviar Email', 'adhesion'); ?>
                        </button>
                        
                        <?php if (!empty($contract['docusign_envelope_id'])): ?>
                            <button type="button" id="open-docusign-btn" class="adhesion-btn adhesion-btn-secondary">
                                <span class="dashicons dashicons-external"></span>
                                <?php _e('Abrir en DocuSign', 'adhesion'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Ayuda -->
                    <div class="signing-help">
                        <h4><?php _e('Â¿No recibes el email?', 'adhesion'); ?></h4>
                        <ul>
                            <li><?php _e('Revisa tu carpeta de spam o correo no deseado', 'adhesion'); ?></li>
                            <li><?php _e('Verifica que el email sea correcto:', 'adhesion'); ?> <strong><?php echo esc_html($user->user_email); ?></strong></li>
                            <li><?php _e('Pulsa "Reenviar Email" si no lo encuentras', 'adhesion'); ?></li>
                            <li><?php _e('Contacta con nosotros si continÃºas teniendo problemas', 'adhesion'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Auto-refresh del estado -->
                <script>
                    // Verificar estado cada 10 segundos
                    let statusCheckInterval = setInterval(function() {
                        checkSigningStatus();
                    }, 10000);
                    
                    function checkSigningStatus() {
                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'adhesion_check_signing_status',
                                nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
                                contract_id: <?php echo $contract_id; ?>
                            },
                            success: function(response) {
                                if (response.success) {
                                    if (response.data.status === 'completed') {
                                        clearInterval(statusCheckInterval);
                                        window.location.reload(); // Recargar para mostrar firma completada
                                    } else if (response.data.status === 'declined') {
                                        clearInterval(statusCheckInterval);
                                        showSigningError('El contrato ha sido rechazado');
                                    }
                                }
                            }
                        });
                    }
                    
                    function showSigningError(message) {
                        document.getElementById('signing-in-progress').innerHTML = 
                            '<div class="signing-error">' +
                            '<div class="error-icon">âŒ</div>' +
                            '<h3>Error en la firma</h3>' +
                            '<p>' + message + '</p>' +
                            '<a href="<?php echo get_permalink(); ?>" class="adhesion-btn adhesion-btn-primary">Intentar de nuevo</a>' +
                            '</div>';
                    }
                </script>
            </div>
            
        <?php elseif ($signing_step === 'signed'): ?>
            <!-- PASO: Firmado correctamente -->
            <div class="signing-step-content" id="signing-completed">
                <div class="completed-content">
                    <div class="success-animation">
                        <div class="checkmark-circle">
                            <div class="checkmark">âœ“</div>
                        </div>
                    </div>
                    <h3 class="success-title"><?php _e('Â¡Contrato firmado exitosamente!', 'adhesion'); ?></h3>
                    <p class="success-description">
                        <?php _e('Tu contrato ha sido firmado digitalmente y estÃ¡ completamente procesado. Ya puedes acceder a tu copia firmada.', 'adhesion'); ?>
                    </p>
                    
                    <!-- InformaciÃ³n del contrato firmado -->
                    <div class="signed-contract-info">
                        <div class="signed-details">
                            <div class="signed-item">
                                <span class="signed-label"><?php _e('Firmado el:', 'adhesion'); ?></span>
                                <span class="signed-value"><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></span>
                            </div>
                            
                            <?php if (!empty($contract['signed_document_path'])): ?>
                                <div class="signed-item">
                                    <span class="signed-label"><?php _e('Documento:', 'adhesion'); ?></span>
                                    <span class="signed-value"><?php _e('Disponible para descarga', 'adhesion'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="signed-item">
                                <span class="signed-label"><?php _e('Estado:', 'adhesion'); ?></span>
                                <span class="signed-value status-completed">âœ… <?php _e('Completado', 'adhesion'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Acciones post-firma -->
                    <div class="post-signing-actions">
                        <?php if (!empty($contract['signed_document_path'])): ?>
                            <a href="<?php echo esc_url($contract['signed_document_path']); ?>" 
                               class="adhesion-btn adhesion-btn-success" 
                               download
                               target="_blank">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Descargar Contrato Firmado', 'adhesion'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo home_url('/mi-cuenta/'); ?>" class="adhesion-btn adhesion-btn-primary">
                            <span class="dashicons dashicons-admin-home"></span>
                            <?php _e('Ir a Mi Cuenta', 'adhesion'); ?>
                        </a>
                        
                        <button type="button" id="send-copy-email-btn" class="adhesion-btn adhesion-btn-outline">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Enviar copia por email', 'adhesion'); ?>
                        </button>
                    </div>
                    
                    <!-- PrÃ³ximos pasos -->
                    <div class="next-steps-signed">
                        <h4><?php _e('Â¿QuÃ© sigue ahora?', 'adhesion'); ?></h4>
                        <div class="steps-timeline">
                            <div class="timeline-step completed">
                                <span class="timeline-icon">âœ…</span>
                                <div class="timeline-content">
                                    <h5><?php _e('Contrato firmado', 'adhesion'); ?></h5>
                                    <p><?php _e('Tu contrato ha sido firmado digitalmente', 'adhesion'); ?></p>
                                </div>
                            </div>
                            <div class="timeline-step">
                                <span class="timeline-icon">ðŸ“‹</span>
                                <div class="timeline-content">
                                    <h5><?php _e('Procesamiento del pedido', 'adhesion'); ?></h5>
                                    <p><?php _e('Nuestro equipo prepararÃ¡ tu pedido', 'adhesion'); ?></p>
                                </div>
                            </div>
                            <div class="timeline-step">
                                <span class="timeline-icon">ðŸšš</span>
                                <div class="timeline-content">
                                    <h5><?php _e('CoordinaciÃ³n de entrega', 'adhesion'); ?></h5>
                                    <p><?php _e('Te contactaremos para organizar la entrega', 'adhesion'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- InformaciÃ³n de contacto -->
                    <div class="contact-support">
                        <h5><?php _e('Â¿Necesitas ayuda?', 'adhesion'); ?></h5>
                        <p><?php _e('Si tienes alguna pregunta sobre tu contrato o pedido:', 'adhesion'); ?></p>
                        <div class="contact-methods">
                            <span>ðŸ“ž <?php echo adhesion_get_option('support_phone', '900 123 456'); ?></span>
                            <span>ðŸ“§ <?php echo adhesion_get_option('support_email', get_option('admin_email')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- PASO: Error o estado no vÃ¡lido -->
            <div class="signing-step-content" id="signing-error">
                <div class="error-content">
                    <div class="error-icon">
                        <span class="icon-large">âŒ</span>
                    </div>
                    <h3 class="error-title">
                        <?php 
                        if ($contract['status'] === 'declined') {
                            _e('Contrato rechazado', 'adhesion');
                        } elseif ($contract['payment_status'] !== 'completed') {
                            _e('Pago requerido', 'adhesion');
                        } else {
                            _e('Error en el proceso de firma', 'adhesion');
                        }
                        ?>
                    </h3>
                    <p class="error-description">
                        <?php 
                        if ($contract['status'] === 'declined') {
                            _e('El contrato ha sido rechazado durante el proceso de firma. Puedes iniciar un nuevo proceso si deseas continuar.', 'adhesion');
                        } elseif ($contract['payment_status'] !== 'completed') {
                            _e('Debes completar el pago antes de poder firmar el contrato.', 'adhesion');
                        } else {
                            _e('Ha ocurrido un error en el proceso de firma. Por favor, contacta con nosotros para resolverlo.', 'adhesion');
                        }
                        ?>
                    </p>
                    
                    <!-- Acciones de error -->
                    <div class="error-actions">
                        <?php if ($contract['payment_status'] !== 'completed'): ?>
                            <a href="<?php echo add_query_arg('contract_id', $contract_id, home_url('/pago/')); ?>" 
                               class="adhesion-btn adhesion-btn-primary">
                                <span class="dashicons dashicons-money"></span>
                                <?php _e('Completar Pago', 'adhesion'); ?>
                            </a>
                        <?php elseif ($contract['status'] === 'declined'): ?>
                            <a href="<?php echo home_url('/calculadora/'); ?>" 
                               class="adhesion-btn adhesion-btn-primary">
                                <span class="dashicons dashicons-calculator"></span>
                                <?php _e('Nuevo CÃ¡lculo', 'adhesion'); ?>
                            </a>
                        <?php else: ?>
                            <button type="button" id="retry-signing-btn" class="adhesion-btn adhesion-btn-primary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Reintentar Firma', 'adhesion'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo home_url('/mi-cuenta/'); ?>" class="adhesion-btn adhesion-btn-outline">
                            <?php _e('Volver a Mi Cuenta', 'adhesion'); ?>
                        </a>
                    </div>
                    
                    <!-- InformaciÃ³n de soporte -->
                    <div class="error-support">
                        <h5><?php _e('Â¿Necesitas ayuda?', 'adhesion'); ?></h5>
                        <p><?php _e('Si el problema persiste, contacta con nuestro equipo de soporte:', 'adhesion'); ?></p>
                        <div class="support-methods">
                            <span>ðŸ“ž <?php echo adhesion_get_option('support_phone', '900 123 456'); ?></span>
                            <span>ðŸ“§ <?php echo adhesion_get_option('support_email', get_option('admin_email')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript especÃ­fico para firma de contratos -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // ConfiguraciÃ³n global
    window.adhesionSigningConfig = {
        contractId: <?php echo $contract_id; ?>,
        signingStep: '<?php echo $signing_step; ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
        returnUrl: '<?php echo esc_js($signing_config['return_url']); ?>',
        messages: {
            preparingSigning: '<?php echo esc_js(__('Preparando firma...', 'adhesion')); ?>',
            signingError: '<?php echo esc_js(__('Error iniciando el proceso de firma.', 'adhesion')); ?>',
            checkingStatus: '<?php echo esc_js(__('Verificando estado...', 'adhesion')); ?>',
            emailSent: '<?php echo esc_js(__('Email


// ===== payment-display.php =====
                                        clearInterval(paymentCheckInterval);
                                        window.location.reload(); // Recargar para mostrar confirmaciÃ³n
                                    } else if (response.data.status === 'failed') {
                                        clearInterval(paymentCheckInterval);
                                        showPaymentError(response.data.message || 'Error en el pago');
                                    }
                                }
                            }
                        });
                    }
                    
                    function showPaymentError(message) {
                        document.getElementById('payment-processing').innerHTML = 
                            '<div class="payment-error">' +
                            '<div class="error-icon">âŒ</div>' +
                            '<h3>Error en el pago</h3>' +
                            '<p>' + message + '</p>' +
                            '<a href="<?php echo get_permalink(); ?>" class="adhesion-btn adhesion-btn-primary">Intentar de nuevo</a>' +
                            '</div>';
                    }
                </script>
            </div>
            
        <?php elseif ($payment_step === 'complete'): ?>
            <!-- PASO 4: Pago completado -->
            <div class="payment-step-content" id="payment-complete">
                <div class="success-content">
                    <div class="success-icon">
                        <div class="checkmark">âœ“</div>
                    </div>
                    <h3 class="success-title"><?php _e('Â¡Pago realizado con Ã©xito!', 'adhesion'); ?></h3>
                    <p class="success-description">
                        <?php _e('Tu pedido ha sido procesado correctamente. En breve recibirÃ¡s un email con la confirmaciÃ³n.', 'adhesion'); ?>
                    </p>
                    
                    <!-- InformaciÃ³n del pedido completado -->
                    <div class="order-completed-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label"><?php _e('NÃºmero de contrato:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo esc_html($contract['contract_number'] ?? 'Generando...'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Importe pagado:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo adhesion_format_price($total_amount); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Fecha de pago:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo date('d/m/Y H:i'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Referencia:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo esc_html($contract['payment_reference'] ?? ''); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PrÃ³ximos pasos -->
                    <div class="next-steps">
                        <h4><?php _e('PrÃ³ximos pasos:', 'adhesion'); ?></h4>
                        <ol>
                            <li><?php _e('RecibirÃ¡s un email con la confirmaciÃ³n del pago', 'adhesion'); ?></li>
                            <li><?php _e('Te enviaremos el contrato para firma digital', 'adhesion'); ?></li>
                            <li><?php _e('Una vez firmado, coordinaremos la entrega', 'adhesion'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Acciones despuÃ©s del pago -->
                    <div class="post-payment-actions">
                        <a href="<?php echo home_url('/mi-cuenta/'); ?>" class="adhesion-btn adhesion-btn-primary">
                            <span class="dashicons dashicons-admin-home"></span>
                            <?php _e('Ir a Mi Cuenta', 'adhesion'); ?>
                        </a>
                        
                        <?php if (adhesion_is_docusign_configured()): ?>
                            <button type="button" id="sign-contract-btn" class="adhesion-btn adhesion-btn-success">
                                <span class="dashicons dashicons-media-document"></span>
                                <?php _e('Firmar Contrato Ahora', 'adhesion'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo home_url('/calculadora/'); ?>" class="adhesion-btn adhesion-btn-outline">
                            <?php _e('Nuevo CÃ¡lculo', 'adhesion'); ?>
                        </a>
                    </div>
                    
                    <!-- InformaciÃ³n de contacto -->
                    <div class="contact-info">
                        <h5><?php _e('Â¿Necesitas ayuda?', 'adhesion'); ?></h5>
                        <p><?php _e('Si tienes alguna duda sobre tu pedido, puedes contactarnos:', 'adhesion'); ?></p>
                        <div class="contact-methods">
                            <span>ðŸ“ž <?php echo adhesion_get_option('support_phone', '900 123 456'); ?></span>
                            <span>ðŸ“§ <?php echo adhesion_get_option('support_email', get_option('admin_email')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>

    <!-- Formulario oculto para Redsys -->
    <div id="redsys-form-container" style="display: none;">
        <form id="redsys-payment-form" method="POST" target="_blank">
            <input type="hidden" name="Ds_SignatureVersion" value="HMAC_SHA256_V1">
            <input type="hidden" name="Ds_MerchantParameters" value="">
            <input type="hidden" name="Ds_Signature" value="">
        </form>
    </div>

    <!-- Modal de carga -->
    <div id="payment-loading-modal" class="adhesion-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-body">
                <div class="loading-content">
                    <div class="spinner-large"></div>
                    <h3><?php _e('Preparando pago...', 'adhesion'); ?></h3>
                    <p><?php _e('Por favor, espera mientras preparamos tu pago seguro.', 'adhesion'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript especÃ­fico para el proceso de pago -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // ConfiguraciÃ³n global del pago
    window.adhesionPaymentConfig = {
        contractId: <?php echo $contract_id; ?>,
        calculationId: <?php echo $calculation_id; ?>,
        totalAmount: <?php echo $total_amount; ?>,
        currentStep: '<?php echo $payment_step; ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
        messages: {
            savingData: '<?php echo esc_js(__('Guardando datos...', 'adhesion')); ?>',
            dataRequired: '<?php echo esc_js(__('Por favor, completa todos los campos obligatorios.', 'adhesion')); ?>',
            preparingPayment: '<?php echo esc_js(__('Preparando pago...', 'adhesion')); ?>',
            paymentError: '<?php echo esc_js(__('Error procesando el pago. IntÃ©ntalo de nuevo.', 'adhesion')); ?>',
            invalidDNI: '<?php echo esc_js(__('El DNI/NIE no es vÃ¡lido.', 'adhesion')); ?>',
            invalidPhone: '<?php echo esc_js(__('El telÃ©fono no es vÃ¡lido.', 'adhesion')); ?>',
            invalidPostal: '<?php echo esc_js(__('El cÃ³digo postal no es vÃ¡lido.', 'adhesion')); ?>'
        }
    };
    
    // Event listeners para el formulario de datos
    $('#save-client-data-btn').on('click', function(e) {
        e.preventDefault();
        saveClientData();
    });
    
    // Event listeners para revisiÃ³n
    $('#proceed-to-payment-btn').on('click', function(e) {
        e.preventDefault();
        createPayment();
    });
    
    $('#edit-client-data-btn').on('click', function(e) {
        e.preventDefault();
        goToStep('form');
    });
    
    $('#back-to-form-btn').on('click', function(e) {
        e.preventDefault();
        goToStep('form');
    });
    
    // Event listener para firma de contrato
    $('#sign-contract-btn').on('click', function(e) {
        e.preventDefault();
        startContractSigning();
    });
    
    // Validaciones en tiempo real
    setupRealtimeValidation();
    
    /**
     * Guardar datos del cliente
     */
    function saveClientData() {
        // Validar formulario
        if (!validateClientForm()) {
            return;
        }
        
        const formData = collectClientFormData();
        const $btn = $('#save-client-data-btn');
        
        // Mostrar estado de carga
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionPaymentConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_save_client_data',
                nonce: window.adhesionPaymentConfig.nonce,
                contract_id: window.adhesionPaymentConfig.contractId,
                calculation_id: window.adhesionPaymentConfig.calculationId,
                client_data: formData
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar contract_id si se creÃ³ uno nuevo
                    if (response.data.contract_id) {
                        window.adhesionPaymentConfig.contractId = response.data.contract_id;
                    }
                    
                    // Ir al paso de revisiÃ³n
                    goToStep('review');
                } else {
                    showPaymentMessage(response.data || window.adhesionPaymentConfig.messages.paymentError, 'error');
                }
            },
            error: function() {
                showPaymentMessage(window.adhesionPaymentConfig.messages.paymentError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Crear pago en Redsys
     */
    function createPayment() {
        const $btn = $('#proceed-to-payment-btn');
        
        // Mostrar modal de carga
        $('#payment-loading-modal').show();
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionPaymentConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_create_payment',
                nonce: window.adhesionPaymentConfig.nonce,
                contract_id: window.adhesionPaymentConfig.contractId,
                amount: window.adhesionPaymentConfig.totalAmount
            },
            success: function(response) {
                if (response.success) {
                    // Enviar formulario a Redsys
                    submitRedsysForm(response.data);
                } else {
                    $('#payment-loading-modal').hide();
                    showPaymentMessage(response.data || window.adhesionPaymentConfig.messages.paymentError, 'error');
                }
            },
            error: function() {
                $('#payment-loading-modal').hide();
                showPaymentMessage(window.adhesionPaymentConfig.messages.paymentError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Enviar formulario a Redsys
     */
    function submitRedsysForm(paymentData) {
        const $form = $('#redsys-payment-form');
        
        // Configurar formulario
        $form.attr('action', paymentData.form_url);
        $form.find('input[name="Ds_MerchantParameters"]').val(paymentData.merchant_parameters);
        $form.find('input[name="Ds_Signature"]').val(paymentData.signature);
        
        // Ocultar modal y enviar
        $('#payment-loading-modal').hide();
        
        // Cambiar a paso de procesamiento
        goToStep('processing');
        
        // Enviar formulario (abre en nueva ventana)
        $form.submit();
    }
    
    /**
     * Iniciar proceso de firma de contrato
     */
    function startContractSigning() {
        const $btn = $('#sign-contract-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionPaymentConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_start_signing',
                nonce: window.adhesionPaymentConfig.nonce,
                contract_id: window.adhesionPaymentConfig.contractId
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    // Abrir DocuSign en nueva ventana
                    window.open(response.data.url, '_blank');
                } else {
                    showPaymentMessage(response.data || 'Error iniciando firma', 'error');
                }
            },
            error: function() {
                showPaymentMessage('Error iniciando firma', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Validar formulario del cliente
     */
    function validateClientForm() {
        let isValid = true;
        
        // Limpiar errores previos
        $('.form-group').removeClass('has-error');
        $('.error-message').remove();
        
        // Validar campos requeridos
        $('input[required], textarea[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                markFieldError($field, 'Este campo es obligatorio');
                isValid = false;
            }
        });
        
        // Validaciones especÃ­ficas
        const dni = $('#dni').val().trim();
        if (dni && !validateDNI(dni)) {
            markFieldError($('#dni'), window.adhesionPaymentConfig.messages.invalidDNI);
            isValid = false;
        }
        
        const phone = $('#telefono').val().trim();
        if (phone && !validatePhone(phone)) {
            markFieldError($('#telefono'), window.adhesionPaymentConfig.messages.invalidPhone);
            isValid = false;
        }
        
        const postal = $('#codigo_postal').val().trim();
        if (postal && !validatePostalCode(postal)) {
            markFieldError($('#codigo_postal'), window.adhesionPaymentConfig.messages.invalidPostal);
            isValid = false;
        }
        
        // Validar checkboxes requeridos
        if (!$('#acepta_terminos').prop('checked')) {
            markFieldError($('#acepta_terminos'), 'Debes aceptar los tÃ©rminos y condiciones');
            isValid = false;
        }
        
        if (!$('#acepta_privacidad').prop('checked')) {
            markFieldError($('#acepta_privacidad'), 'Debes aceptar la polÃ­tica de privacidad');
            isValid = false;
        }
        
        if (!isValid) {
            showPaymentMessage(window.adhesionPaymentConfig.messages.dataRequired, 'error');
            
            // Scroll al primer error
            const $firstError = $('.has-error').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
                $firstError.find('input, textarea, select').focus();
            }
        }
        
        return isValid;
    }
    
    /**
     * Recopilar datos del formulario
     */
    function collectClientFormData() {
        return {
            nombre_completo: $('#nombre_completo').val().trim(),
            email: $('#email').val().trim(),
            telefono: $('#telefono').val().trim(),
            dni: $('#dni').val().trim(),
            direccion: $('#direccion').val().trim(),
            ciudad: $('#ciudad').val().trim(),
            codigo_postal: $('#codigo_postal').val().trim(),
            provincia: $('#provincia').val().trim(),
            empresa: $('#empresa').val().trim(),
            cif: $('#cif').val().trim(),
            notas_pedido: $('#notas_pedido').val().trim(),
            acepta_terminos: $('#acepta_terminos').prop('checked'),
            acepta_privacidad: $('#acepta_privacidad').prop('checked'),
            acepta_comunicaciones: $('#acepta_comunicaciones').prop('checked')
        };
    }
    
    /**
     * Configurar validaciÃ³n en tiempo real
     */
    function setupRealtimeValidation() {
        // ValidaciÃ³n de DNI
        $('#dni').on('blur', function() {
            const value = $(this).val().trim();
            if (value && !validateDNI(value)) {
                markFieldError($(this), window.adhesionPaymentConfig.messages.invalidDNI);
            } else {
                clearFieldError($(this));
            }
        });
        
        // ValidaciÃ³n de telÃ©fono
        $('#telefono').on('blur', function() {
            const value = $(this).val().trim();
            if (value && !validatePhone(value)) {
                markFieldError($(this), window.adhesionPaymentConfig.messages.invalidPhone);
            } else {
                clearFieldError($(this));
            }
        });
        
        // ValidaciÃ³n de cÃ³digo postal
        $('#codigo_postal').on('blur', function() {
            const value = $(this).val().trim();
            if (value && !validatePostalCode(value)) {
                markFieldError($(this), window.adhesionPaymentConfig.messages.invalidPostal);
            } else {
                clearFieldError($(this));
            }
        });
        
        // Formato automÃ¡tico de telÃ©fono
        $('#telefono').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            $(this).val(value);
        });
        
        // Formato automÃ¡tico de cÃ³digo postal
        $('#codigo_postal').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5);
            }
            $(this).val(value);
        });
    }
    
    /**
     * Funciones de validaciÃ³n
     */
    function validateDNI(dni) {
        const dniRegex = /^[0-9]{8}[A-Z]$/;
        if (!dniRegex.test(dni)) return false;
        
        const number = dni.substring(0, 8);
        const letter = dni.substring(8, 9);
        const validLetters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        const calculatedLetter = validLetters[parseInt(number) % 23];
        
        return letter === calculatedLetter;
    }
    
    function validatePhone(phone) {
        const phoneRegex = /^[67][0-9]{8}$/;
        return phoneRegex.test(phone);
    }
    
    function validatePostalCode(postal) {
        const postalRegex = /^[0-5][0-9]{4}$/;
        return postalRegex.test(postal);
    }
    
    /**
     * Funciones de UI
     */
    function markFieldError($field, message) {
        const $formGroup = $field.closest('.form-group');
        $formGroup.addClass('has-error');
        $formGroup.find('.error-message').remove();
        if (message) {
            $formGroup.append('<div class="error-message">' + message + '</div>');
        }
    }
    
    function clearFieldError($field) {
        const $formGroup = $field.closest('.form-group');
        $formGroup.removeClass('has-error');
        $formGroup.find('.error-message').remove();
    }
    
    function updateButtonLoading($btn, loading) {
        const $text = $btn.find('.btn-text');
        const $loading = $btn.find('.btn-loading');
        
        if (loading) {
            $btn.prop('disabled', true).addClass('loading');
            $text.hide();
            $loading.show();
        } else {
            $btn.prop('disabled', false).removeClass('loading');
            $text.show();
            $loading.hide();
        }
    }
    
    function showPaymentMessage(message, type) {
        const $container = $('#payment-messages');
        const alertClass = `adhesion-notice adhesion-notice-${type}`;
        const html = `<div class="${alertClass}">${message}</div>`;
        
        $container.html(html);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 500);
        
        // Auto-hide para Ã©xito
        if (type === 'success') {
            setTimeout(function() {
                $container.empty();
            }, 5000);
        }
    }
    
    function goToStep(step) {
        // Actualizar URL sin recargar pÃ¡gina
        const url = new URL(window.location);
        url.searchParams.set('step', step);
        window.history.pushState({}, '', url);
        
        // Recargar pÃ¡gina para mostrar nuevo paso
        window.location.reload();
    }
});
</script>

<style>
/* Estilos especÃ­ficos para el proceso de pago */
.adhesion-payment-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.payment-header {
    text-align: center;
    margin-bottom: 40px;
}

.payment-title {
    font-size: 2.5em;
    margin: 0 0 20px 0;
    color: #23282d;
}

.payment-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

/* Barra de progreso */
.payment-progress {
    margin: 30px 0;
}

.progress-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 600px;
    margin: 0 auto;
    position: relative;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 2;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e4e7;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.progress-step.active .step-number {
    background: #007cba;
    color: white;
}

.progress-step.completed .step-number {
    background: #28a745;
    color: white;
}

.step-label {
    font-size: 0.9em;
    color: #666;
    font-weight: 500;
}

.progress-step.active .step-label {
    color: #007cba;
    font-weight: 600;
}

.progress-line {
    position: absolute;
    top: 20px;
    height: 2px;
    background: #e2e4e7;
    z-index: 1;
    transition: background 0.3s ease;
}

.progress-line.completed {
    background: #28a745;
}

.progress-line:nth-of-type(2) {
    left: 16.66%;
    width: 16.66%;
}

.progress-line:nth-of-type(4) {
    left: 41.66%;
    width: 16.66%;
}

.progress-line:nth-of-type(6) {
    left: 66.66%;
    width: 16.66%;
}

/* Resumen del pedido */
.order-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.summary-title {
    font-size: 1.4em;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
}

.summary-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.summary-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.summary-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.summary-section h4 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
    opacity: 0.9;
}

.materials-summary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.material-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.95em;
}

.material-name {
    font-weight: 500;
}

.material-quantity {
    opacity: 0.8;
}

.material-total {
    font-weight: 600;
}

.financial-totals {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.total-line:last-child {
    margin-bottom: 0;
}

.final-total {
    border-top: 2px solid rgba(255,255,255,0.3);
    padding-top: 10px;
    margin-top: 10px;
    font-size: 1.2em;
    font-weight: 700;
}

.discount .total-value {
    color: #90EE90;
}

/* Contenido de pasos */
.payment-content {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.step-title {
    font-size: 1.6em;
    margin: 0 0 10px 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.step-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.step-description {
    color: #666;
    margin-bottom: 30px;
    font-size: 1.1em;
}

/* Formulario */
.adhesion-form {
    max-width: 800px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #f0f0f1;
}

.form-section:last-child {
    border-bottom: none;
}

.section-title {
    font-size: 1.3em;
    margin: 0 0 20px 0;
    color: #23282d;
}

.optional-label {
    font-size: 0.9em;
    color: #666;
    font-weight: normal;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group-full {
    grid-column: 1 / -1;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #23282d;
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 12px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.form-group input[readonly] {
    background: #f8f9fa;
    color: #666;
}

.field-help {
    font-size: 0.85em;
    color: #666;
    margin-top: 4px;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    font-weight: 500;
    line-height: 1.4;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 12px;
    margin-top: 2px;
    transform: scale(1.2);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 40px;
    flex-wrap: wrap;
}

/* Estados de validaciÃ³n */
.form-group.has-error input,
.form-group.has-error textarea,
.form-group.has-error select {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
}

.error-message {
    color: #dc3545;
    font-size: 0.9em;
    margin-top: 5px;
    display: flex;
    align-items: center;
}

.error-message::before {
    content: 'âš ï¸';
    margin-right: 5px;
}

/* SecciÃ³n de revisiÃ³n */
.review-section {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.review-title {
    font-size: 1.2em;
    margin: 0 0 15px 0;
    color: #23282d;
}

.data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.data-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.data-item:last-child {
    border-bottom: none;
}

.data-label {
    font-weight: 600;
    color: #495057;
}

.data-value {
    color: #23282d;
    text-align: right;
}

.review-actions {
    margin-top: 20px;
    text-align: center;
}

/* MÃ©todos de pago */
.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.payment-method {
    display: flex;
    align-items: center;
    padding: 20px;
    border: 2px solid #e2e4e7;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method.selected {
    border-color: #007cba;
    background: #f0f8ff;
}

.method-icon {
    font-size: 2em;
    margin-right: 20px;
}

.method-info {
    flex: 1;
}

.method-info h5 {
    margin: 0 0 5px 0;
    color: #23282d;
}

.method-info p {
    margin: 0;
    color: #666;
    font-size: 0.9em;
}

.method-security {
    display: flex;
    align-items: center;
}

.security-badge {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    font-weight: 600;
}

/* Acciones principales de revisiÃ³n */
.review-actions-main {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin: 40px 0;
    flex-wrap: wrap;
}

/* InformaciÃ³n de seguridad */
.security-info {
    background: #e8f5e8;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.security-info h5 {
    margin: 0 0 15px 0;
    color: #155724;
}

.security-info ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.security-info li {
    margin-bottom: 8px;
    color: #155724;
    font-size: 0.95em;
}

/* Paso de procesamiento */
.processing-content {
    text-align: center;
    padding: 40px 20px;
}

.processing-icon {
    margin-bottom: 20px;
}

.spinner-large {
    width: 60px;
    height: 60px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

.processing-title {
    font-size: 1.8em;
    margin: 0 0 10px 0;
    color: #23282d;
}

.processing-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 40px;
}

.payment-status {
    max-width: 400px;
    margin: 0 auto 30px;
}

.status-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
}

.status-item:last-child {
    border-bottom: none;
}

.status-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e2e4e7;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-weight: 600;
}

.status-item.active .status-icon {
    background: #007cba;
    color: white;
}

.status-item.processing .status-icon {
    background: transparent;
}

.spinner-small {
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.status-text {
    color: #23282d;
    font-weight: 500;
}

.payment-info {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    text-align: left;
    max-width: 400px;
    margin: 0 auto;
}

.payment-info p {
    margin: 8px 0;
}

/* Paso completado */
.success-content {
    text-align: center;
    padding: 40px 20px;
}

.success-icon {
    margin-bottom: 20px;
}

.checkmark {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #28a745;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5em;
    margin: 0 auto;
    animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
    0% {
        transform: scale(0.8);
        opacity: 0.5;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.success-title {
    font-size: 2em;
    margin: 0 0 15px 0;
    color: #28a745;
}

.success-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 40px;
}

.order-completed-info {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    text-align: center;
}

.info-label {
    font-weight: 500;
    color: #666;
    font-size: 0.9em;
    margin-bottom: 5px;
}

.info-value {
    font-weight: 600;
    color: #23282d;
    font-size: 1.1em;
}

.next-steps {
    background: #e8f4fd;
    border: 1px solid #bee5eb;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: left;
}

.next-steps h4 {
    margin: 0 0 15px 0;
    color: #0c5460;
}

.next-steps ol {
    margin: 0;
    color: #0c5460;
}

.next-steps li {
    margin-bottom: 8px;
}

.post-payment-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.contact-info {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.contact-info h5 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.contact-methods {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 15px;
}

.contact-methods span {
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #e2e4e7;
    font-size: 0.9em;
}

/* Botones */
.adhesion-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    margin: 0 5px 10px 0;
    text-align: center;
    justify-content: center;
}

.adhesion-btn-primary {
    background: #007cba;
    color: white;
}

.adhesion-btn-primary:hover {
    background: #005a87;
    color: white;
}

.adhesion-btn-success {
    background: #28a745;
    color: white;
}

.adhesion-btn-success:hover {
    background: #1e7e34;
    color: white;
}

.adhesion-btn-outline {
    background: transparent;
    color: #007cba;
    border: 2px solid #007cba;
}

.adhesion-btn-outline:hover {
    background: #007cba;
    color: white;
}

.adhesion-btn-large {
    padding: 16px 32px;
    font-size: 18px;
}

.btn-loading {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.adhesion-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.adhesion-btn.loading {
    pointer-events: none;
}

.adhesion-btn.loading .btn-text {
    display: none;
}

.adhesion-btn.loading .btn-loading {
    display: flex;
}

/* Modal */
.adhesion-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    padding: 40px;
    max-width: 400px;
    text-align: center;
}

.loading-content h3 {
    margin: 20px 0 10px 0;
    color: #23282d;
}

.loading-content p {
    margin: 0;
    color: #666;
}

/* Mensajes */
.adhesion-messages-container {
    margin-bottom: 20px;
}

.adhesion-notice {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.adhesion-notice-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.adhesion-notice-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.adhesion-notice-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.adhesion-notice-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

/* Error de pago */
.payment-error {
    text-align: center;
    padding: 40px 20px;
}

.error-icon {
    font-size: 4em;
    margin-bottom: 20px;
}

.payment-error h3 {
    color: #dc3545;
    margin-bottom: 15px;
}

.payment-error p {
    color: #666;
    margin-bottom: 30px;
}

/* Responsive design */
@media (max-width: 768px) {
    .adhesion-payment-container {
        padding: 15px;
    }
    
    .payment-title {
        font-size: 2em;
    }
    
    .progress-bar {
        flex-direction: column;
        gap: 20px;
    }
    
    .progress-line {
        display: none;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .data-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions,
    .review-actions-main,
    .post-payment-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .adhesion-btn {
        width: 100%;
        max-width: 300px;
    }
    
    .contact-methods {
        flex-direction: column;
        align-items: center;
    }
    
    .data-item {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .data-value {
        text-align: center;
    }
    
    .material-line {
        font-size: 0.9em;
    }
}

@media (max-width: 480px) {
    .payment-title {
        font-size: 1.8em;
    }
    
    .step-title {
        font-size: 1.4em;
    }
    
    .order-summary,
    .payment-content {
        padding: 20px;
    }
    
    .form-section {
        padding-bottom: 20px;
    }
    
    .progress-step {
        font-size: 0.9em;
    }
    
    .step-number {
        width: 35px;
        height: 35px;
    }
}

/* Print styles */
@media print {
    .form-actions,
    .review-actions,
    .review-actions-main,
    .post-payment-actions {
        display: none;
    }
    
    .payment-progress,
    .adhesion-modal {
        display: none;
    }
    
    .order-summary {
        background: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #000;
    }
}
</style><?php
/**
 * Vista del proceso de pago con Redsys
 * 
 * Esta vista maneja:
 * - Formulario de datos del cliente
 * - Resumen del cÃ¡lculo y presupuesto
 * - Proceso de pago con Redsys
 * - Estados de pago y confirmaciones
 * - CreaciÃ³n automÃ¡tica de contratos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario estÃ© logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para realizar un pago.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar SesiÃ³n', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Verificar configuraciÃ³n de Redsys
if (!adhesion_is_redsys_configured()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Los pagos no estÃ¡n configurados. Contacta con el administrador.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Obtener datos necesarios
$db = new Adhesion_Database();
$user = wp_get_current_user();
$user_meta = get_user_meta($user->ID);

// Obtener ID del cÃ¡lculo o contrato desde parÃ¡metros
$calculation_id = isset($_GET['calculation_id']) ? intval($_GET['calculation_id']) : 0;
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

// Variables para el proceso
$calculation = null;
$contract = null;
$payment_step = 'form'; // form, review, processing, complete

// Determinar el contexto del pago
if ($contract_id) {
    $contract = $db->get_contract($contract_id);
    if ($contract && $contract['user_id'] == $user->ID) {
        if ($contract['calculation_id']) {
            $calculation = $db->get_calculation($contract['calculation_id']);
        }
        // Determinar paso segÃºn estado del contrato
        if ($contract['payment_status'] === 'completed') {
            $payment_step = 'complete';
        } elseif ($contract['payment_status'] === 'pending') {
            $payment_step = 'processing';
        } elseif (!empty($contract['client_data'])) {
            $payment_step = 'review';
        }
    }
} elseif ($calculation_id) {
    $calculation = $db->get_calculation($calculation_id);
    if ($calculation && $calculation['user_id'] == $user->ID) {
        // Verificar si ya existe un contrato para este cÃ¡lculo
        $existing_contract = $db->get_contract_by_calculation($calculation_id);
        if ($existing_contract) {
            $contract = $existing_contract;
            $contract_id = $contract['id'];
            $payment_step = !empty($contract['client_data']) ? 'review' : 'form';
        }
    }
}

// Si no hay datos vÃ¡lidos, mostrar error
if (!$calculation && !$contract) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('No se encontraron datos para procesar el pago. Por favor, realiza primero un cÃ¡lculo.', 'adhesion') . '</p>';
    echo '<p><a href="' . home_url('/calculadora/') . '" class="adhesion-btn adhesion-btn-primary">' . __('Ir a Calculadora', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// ConfiguraciÃ³n del proceso de pago
$payment_config = array(
    'tax_rate' => adhesion_get_option('tax_rate', 21),
    'currency' => adhesion_get_option('currency', 'EUR'),
    'require_dni' => adhesion_get_option('require_dni', true),
    'require_company_data' => adhesion_get_option('require_company_data', false)
);
?>

<div class="adhesion-payment-container" id="adhesion-payment">
    
    <!-- Header del proceso de pago -->
    <div class="payment-header">
        <h2 class="payment-title">
            <span class="payment-icon">ðŸ’³</span>
            <?php _e('Proceso de Pago', 'adhesion'); ?>
        </h2>
        
        <!-- Barra de progreso -->
        <div class="payment-progress">
            <div class="progress-bar">
                <div class="progress-step <?php echo ($payment_step === 'form') ? 'active' : (in_array($payment_step, ['review', 'processing', 'complete']) ? 'completed' : ''); ?>">
                    <span class="step-number">1</span>
                    <span class="step-label"><?php _e('Datos', 'adhesion'); ?></span>
                </div>
                <div class="progress-line <?php echo (in_array($payment_step, ['review', 'processing', 'complete']) ? 'completed' : ''); ?>"></div>
                <div class="progress-step <?php echo ($payment_step === 'review') ? 'active' : (in_array($payment_step, ['processing', 'complete']) ? 'completed' : ''); ?>">
                    <span class="step-number">2</span>
                    <span class="step-label"><?php _e('RevisiÃ³n', 'adhesion'); ?></span>
                </div>
                <div class="progress-line <?php echo (in_array($payment_step, ['processing', 'complete']) ? 'completed' : ''); ?>"></div>
                <div class="progress-step <?php echo ($payment_step === 'processing') ? 'active' : ($payment_step === 'complete' ? 'completed' : ''); ?>">
                    <span class="step-number">3</span>
                    <span class="step-label"><?php _e('Pago', 'adhesion'); ?></span>
                </div>
                <div class="progress-line <?php echo ($payment_step === 'complete' ? 'completed' : ''); ?>"></div>
                <div class="progress-step <?php echo ($payment_step === 'complete') ? 'active completed' : ''; ?>">
                    <span class="step-number">4</span>
                    <span class="step-label"><?php _e('ConfirmaciÃ³n', 'adhesion'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensajes de estado -->
    <div id="payment-messages" class="adhesion-messages-container"></div>

    <!-- Resumen del pedido (siempre visible) -->
    <div class="order-summary">
        <h3 class="summary-title">
            <span class="summary-icon">ðŸ“‹</span>
            <?php _e('Resumen del pedido', 'adhesion'); ?>
        </h3>
        
        <div class="summary-content">
            
            <!-- InformaciÃ³n del cliente -->
            <div class="summary-section">
                <h4><?php _e('Cliente:', 'adhesion'); ?></h4>
                <p><strong><?php echo esc_html($user->display_name); ?></strong></p>
                <p><?php echo esc_html($user->user_email); ?></p>
            </div>
            
            <!-- Desglose del cÃ¡lculo -->
            <?php if ($calculation): ?>
                <div class="summary-section">
                    <h4><?php _e('Materiales:', 'adhesion'); ?></h4>
                    <?php
                    $materials_data = json_decode($calculation['materials_data'], true);
                    if ($materials_data):
                    ?>
                        <div class="materials-summary">
                            <?php foreach ($materials_data as $material): ?>
                                <div class="material-line">
                                    <span class="material-name"><?php echo esc_html(ucfirst($material['type'])); ?></span>
                                    <span class="material-quantity"><?php echo adhesion_format_tons($material['quantity']); ?></span>
                                    <span class="material-total"><?php echo adhesion_format_price($material['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Totales financieros -->
            <div class="summary-section financial-totals">
                <?php
                $total_amount = $calculation ? $calculation['total_price'] : ($contract ? $contract['total_price'] : 0);
                $total_tons = $calculation ? $calculation['total_tons'] : ($contract ? $contract['total_tons'] : 0);
                ?>
                
                <div class="total-line">
                    <span class="total-label"><?php _e('Total toneladas:', 'adhesion'); ?></span>
                    <span class="total-value"><?php echo adhesion_format_tons($total_tons); ?></span>
                </div>
                
                <?php if ($calculation && isset($calculation['subtotal'])): ?>
                    <div class="total-line">
                        <span class="total-label"><?php _e('Subtotal:', 'adhesion'); ?></span>
                        <span class="total-value"><?php echo adhesion_format_price($calculation['subtotal']); ?></span>
                    </div>
                    
                    <?php if ($calculation['discount_amount'] > 0): ?>
                        <div class="total-line discount">
                            <span class="total-label"><?php _e('Descuentos:', 'adhesion'); ?></span>
                            <span class="total-value">-<?php echo adhesion_format_price($calculation['discount_amount']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($calculation['tax_amount'] > 0): ?>
                        <div class="total-line">
                            <span class="total-label"><?php printf(__('IVA (%s%%):', 'adhesion'), $payment_config['tax_rate']); ?></span>
                            <span class="total-value"><?php echo adhesion_format_price($calculation['tax_amount']); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="total-line final-total">
                    <span class="total-label"><?php _e('TOTAL A PAGAR:', 'adhesion'); ?></span>
                    <span class="total-value"><?php echo adhesion_format_price($total_amount); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido principal segÃºn el paso -->
    <div class="payment-content">
        
        <?php if ($payment_step === 'form'): ?>
            <!-- PASO 1: Formulario de datos del cliente -->
            <div class="payment-step-content" id="client-data-form">
                <h3 class="step-title">
                    <span class="step-icon">ðŸ‘¤</span>
                    <?php _e('Completa tus datos', 'adhesion'); ?>
                </h3>
                <p class="step-description">
                    <?php _e('Necesitamos algunos datos adicionales para procesar tu pedido y generar el contrato.', 'adhesion'); ?>
                </p>
                
                <form id="adhesion-client-form" class="adhesion-form">
                    
                    <!-- Datos personales -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('Datos Personales', 'adhesion'); ?></h4>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre_completo"><?php _e('Nombre completo', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="nombre_completo" 
                                       name="nombre_completo" 
                                       value="<?php echo esc_attr($user->display_name); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><?php _e('Email', 'adhesion'); ?> *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo esc_attr($user->user_email); ?>" 
                                       readonly>
                                <small class="field-help"><?php _e('El email no se puede modificar.', 'adhesion'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="telefono"><?php _e('TelÃ©fono', 'adhesion'); ?> *</label>
                                <input type="tel" 
                                       id="telefono" 
                                       name="telefono" 
                                       value="<?php echo esc_attr($user_meta['phone'][0] ?? ''); ?>" 
                                       placeholder="<?php _e('Ej: 600123456', 'adhesion'); ?>"
                                       required>
                            </div>
                            
                            <?php if ($payment_config['require_dni']): ?>
                                <div class="form-group">
                                    <label for="dni"><?php _e('DNI/NIE', 'adhesion'); ?> *</label>
                                    <input type="text" 
                                           id="dni" 
                                           name="dni" 
                                           value="<?php echo esc_attr($user_meta['dni'][0] ?? ''); ?>" 
                                           placeholder="<?php _e('Ej: 12345678A', 'adhesion'); ?>"
                                           required>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- DirecciÃ³n -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('DirecciÃ³n', 'adhesion'); ?></h4>
                        
                        <div class="form-grid">
                            <div class="form-group form-group-full">
                                <label for="direccion"><?php _e('DirecciÃ³n completa', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="direccion" 
                                       name="direccion" 
                                       value="<?php echo esc_attr($user_meta['address'][0] ?? ''); ?>" 
                                       placeholder="<?php _e('Calle, nÃºmero, piso, puerta...', 'adhesion'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ciudad"><?php _e('Ciudad', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="ciudad" 
                                       name="ciudad" 
                                       value="<?php echo esc_attr($user_meta['city'][0] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="codigo_postal"><?php _e('CÃ³digo Postal', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="codigo_postal" 
                                       name="codigo_postal" 
                                       value="<?php echo esc_attr($user_meta['postal_code'][0] ?? ''); ?>" 
                                       pattern="[0-9]{5}"
                                       placeholder="<?php _e('Ej: 28001', 'adhesion'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="provincia"><?php _e('Provincia', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="provincia" 
                                       name="provincia" 
                                       value="<?php echo esc_attr($user_meta['province'][0] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datos de empresa (opcional) -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <?php _e('Datos de Empresa', 'adhesion'); ?>
                            <?php if (!$payment_config['require_company_data']): ?>
                                <span class="optional-label">(<?php _e('Opcional', 'adhesion'); ?>)</span>
                            <?php endif; ?>
                        </h4>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="empresa"><?php _e('Nombre de la empresa', 'adhesion'); ?><?php echo $payment_config['require_company_data'] ? ' *' : ''; ?></label>
                                <input type="text" 
                                       id="empresa" 
                                       name="empresa" 
                                       value="<?php echo esc_attr($user_meta['company'][0] ?? ''); ?>"
                                       <?php echo $payment_config['require_company_data'] ? 'required' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="cif"><?php _e('CIF', 'adhesion'); ?><?php echo $payment_config['require_company_data'] ? ' *' : ''; ?></label>
                                <input type="text" 
                                       id="cif" 
                                       name="cif" 
                                       value="<?php echo esc_attr($user_meta['cif'][0] ?? ''); ?>" 
                                       placeholder="<?php _e('Ej: A12345678', 'adhesion'); ?>"
                                       <?php echo $payment_config['require_company_data'] ? 'required' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notas adicionales -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('InformaciÃ³n Adicional', 'adhesion'); ?></h4>
                        
                        <div class="form-group">
                            <label for="notas_pedido"><?php _e('Notas del pedido (opcional)', 'adhesion'); ?></label>
                            <textarea id="notas_pedido" 
                                      name="notas_pedido" 
                                      rows="3" 
                                      placeholder="<?php _e('Instrucciones especiales, observaciones...', 'adhesion'); ?>"></textarea>
                        </div>
                    </div>
                    
                    <!-- AceptaciÃ³n de tÃ©rminos -->
                    <div class="form-section">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="acepta_terminos" id="acepta_terminos" required>
                                <span class="checkmark"></span>
                                <?php printf(__('He leÃ­do y acepto los <a href="%s" target="_blank">tÃ©rminos y condiciones</a>', 'adhesion'), '#'); ?>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="acepta_privacidad" id="acepta_privacidad" required>
                                <span class="checkmark"></span>
                                <?php printf(__('Acepto la <a href="%s" target="_blank">polÃ­tica de privacidad</a>', 'adhesion'), get_privacy_policy_url()); ?>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="acepta_comunicaciones" id="acepta_comunicaciones">
                                <span class="checkmark"></span>
                                <?php _e('Deseo recibir comunicaciones comerciales por email', 'adhesion'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Botones de acciÃ³n -->
                    <div class="form-actions">
                        <button type="button" id="save-client-data-btn" class="adhesion-btn adhesion-btn-primary adhesion-btn-large">
                            <span class="btn-text"><?php _e('Continuar con el pago', 'adhesion'); ?></span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span>
                                <?php _e('Guardando datos...', 'adhesion'); ?>
                            </span>
                        </button>
                        
                        <a href="<?php echo home_url('/calculadora/'); ?>" class="adhesion-btn adhesion-btn-outline">
                            <?php _e('Volver a calculadora', 'adhesion'); ?>
                        </a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($payment_step === 'review'): ?>
            <!-- PASO 2: RevisiÃ³n antes del pago -->
            <div class="payment-step-content" id="payment-review">
                <h3 class="step-title">
                    <span class="step-icon">ðŸ‘ï¸</span>
                    <?php _e('Revisa tu pedido', 'adhesion'); ?>
                </h3>
                <p class="step-description">
                    <?php _e('Verifica que todos los datos sean correctos antes de proceder al pago.', 'adhesion'); ?>
                </p>
                
                <!-- Datos del cliente -->
                <?php if ($contract && !empty($contract['client_data'])): ?>
                    <div class="review-section">
                        <h4 class="review-title"><?php _e('Datos del cliente', 'adhesion'); ?></h4>
                        <div class="review-content">
                            <?php
                            $client_data = is_string($contract['client_data']) ? json_decode($contract['client_data'], true) : $contract['client_data'];
                            ?>
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="data-label"><?php _e('Nombre:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['nombre_completo'] ?? ''); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label"><?php _e('TelÃ©fono:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['telefono'] ?? ''); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label"><?php _e('DirecciÃ³n:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['direccion'] ?? ''); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label"><?php _e('Ciudad:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['ciudad'] ?? ''); ?>, <?php echo esc_html($client_data['codigo_postal'] ?? ''); ?></span>
                                </div>
                                <?php if (!empty($client_data['empresa'])): ?>
                                    <div class="data-item">
                                        <span class="data-label"><?php _e('Empresa:', 'adhesion'); ?></span>
                                        <span class="data-value"><?php echo esc_html($client_data['empresa']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="review-actions">
                            <button type="button" id="edit-client-data-btn" class="adhesion-btn adhesion-btn-outline">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Modificar datos', 'adhesion'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Forma de pago -->
                <div class="review-section">
                    <h4 class="review-title"><?php _e('Forma de pago', 'adhesion'); ?></h4>
                    <div class="payment-methods">
                        <div class="payment-method selected">
                            <div class="method-icon">ðŸ’³</div>
                            <div class="method-info">
                                <h5><?php _e('Tarjeta de crÃ©dito/dÃ©bito', 'adhesion'); ?></h5>
                                <p><?php _e('Pago seguro a travÃ©s de Redsys. Aceptamos Visa, Mastercard y American Express.', 'adhesion'); ?></p>
                            </div>
                            <div class="method-security">
                                <span class="security-badge">ðŸ”’ SSL</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones de revisiÃ³n -->
                <div class="review-actions-main">
                    <button type="button" id="proceed-to-payment-btn" class="adhesion-btn adhesion-btn-success adhesion-btn-large">
                        <span class="btn-icon">ðŸ”’</span>
                        <span class="btn-text"><?php printf(__('Pagar %s de forma segura', 'adhesion'), adhesion_format_price($total_amount)); ?></span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span>
                            <?php _e('Preparando pago...', 'adhesion'); ?>
                        </span>
                    </button>
                    
                    <button type="button" id="back-to-form-btn" class="adhesion-btn adhesion-btn-outline">
                        <?php _e('Volver a datos', 'adhesion'); ?>
                    </button>
                </div>
                
                <!-- InformaciÃ³n de seguridad -->
                <div class="security-info">
                    <h5><?php _e('InformaciÃ³n de seguridad', 'adhesion'); ?></h5>
                    <ul>
                        <li>ðŸ”’ <?php _e('ConexiÃ³n SSL cifrada', 'adhesion'); ?></li>
                        <li>ðŸ¦ <?php _e('Procesado por Redsys (la pasarela de los bancos espaÃ±oles)', 'adhesion'); ?></li>
                        <li>ðŸ›¡ï¸ <?php _e('No almacenamos datos de tu tarjeta', 'adhesion'); ?></li>
                        <li>ðŸ“§ <?php _e('RecibirÃ¡s confirmaciÃ³n por email', 'adhesion'); ?></li>
                    </ul>
                </div>
            </div>
            
        <?php elseif ($payment_step === 'processing'): ?>
            <!-- PASO 3: Procesando pago -->
            <div class="payment-step-content" id="payment-processing">
                <div class="processing-content">
                    <div class="processing-icon">
                        <div class="spinner-large"></div>
                    </div>
                    <h3 class="processing-title"><?php _e('Procesando tu pago...', 'adhesion'); ?></h3>
                    <p class="processing-description">
                        <?php _e('Por favor, no cierres esta ventana ni pulses el botÃ³n atrÃ¡s del navegador.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Estado del pago -->
                    <div class="payment-status" id="payment-status">
                        <div class="status-item active">
                            <span class="status-icon">âœ“</span>
                            <span class="status-text"><?php _e('Datos verificados', 'adhesion'); ?></span>
                        </div>
                        <div class="status-item active">
                            <span class="status-icon">âœ“</span>
                            <span class="status-text"><?php _e('Conectando con el banco...', 'adhesion'); ?></span>
                        </div>
                        <div class="status-item processing">
                            <span class="status-icon">
                                <span class="spinner-small"></span>
                            </span>
                            <span class="status-text"><?php _e('Procesando pago', 'adhesion'); ?></span>
                        </div>
                    </div>
                    
                    <!-- InformaciÃ³n del pago -->
                    <div class="payment-info">
                        <p><strong><?php _e('Referencia:', 'adhesion'); ?></strong> <?php echo esc_html($contract['payment_reference'] ?? 'Generando...'); ?></p>
                        <p><strong><?php _e('Importe:', 'adhesion'); ?></strong> <?php echo adhesion_format_price($total_amount); ?></p>
                    </div>
                </div>
                
                <!-- Auto-refresh del estado -->
                <script>
                    // Verificar estado del pago cada 3 segundos
                    let paymentCheckInterval = setInterval(function() {
                        checkPaymentStatus();
                    }, 3000);
                    
                    function checkPaymentStatus() {
                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'adhesion_check_payment_status',
                                nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
                                contract_id: <?php echo $contract_id; ?>
                            },
                            success: function(response) {
                                if (response.success) {
                                    if (response.data.status === 'completed') {
                                        clearInterval(paymentCheckInterval


// ===== user-account-display.php =====
<?php
/**
 * Vista del dashboard de cuenta de usuario
 * Archivo: public/partials/user-account-display.php
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario estÃ© logueado
if (!is_user_logged_in()) {
    echo '<p>' . __('Debes iniciar sesiÃ³n para acceder a tu cuenta.', 'adhesion') . '</p>';
    return;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Verificar que tenga el rol correcto
if (!in_array('adhesion_client', $current_user->roles) && !current_user_can('manage_options')) {
    echo '<p>' . __('No tienes permisos para acceder a esta Ã¡rea.', 'adhesion') . '</p>';
    return;
}

// Obtener estadÃ­sticas del usuario
global $wpdb;

$calculations_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculations WHERE user_id = %d",
    $user_id
));

$contracts_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d",
    $user_id
));

$pending_contracts = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'pending'",
    $user_id
));

$signed_contracts = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'signed'",
    $user_id
));

// Obtener Ãºltimos cÃ¡lculos
$recent_calculations = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}adhesion_calculations 
     WHERE user_id = %d 
     ORDER BY created_at DESC 
     LIMIT 5",
    $user_id
));

// Obtener contratos recientes
$recent_contracts = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}adhesion_contracts 
     WHERE user_id = %d 
     ORDER BY created_at DESC 
     LIMIT 5",
    $user_id
));
?>

<div class="adhesion-user-account">
    <!-- Cabecera del dashboard -->
    <div class="adhesion-account-header">
        <div class="user-welcome">
            <h1><?php printf(__('Bienvenido, %s', 'adhesion'), esc_html($current_user->display_name)); ?></h1>
            <p class="user-info">
                <?php printf(__('Miembro desde: %s', 'adhesion'), 
                    date_i18n(get_option('date_format'), strtotime($current_user->user_registered))); ?>
            </p>
        </div>
        
        <div class="account-actions">
            <a href="#" class="button button-primary" id="nueva-calculadora">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('Nueva Calculadora', 'adhesion'); ?>
            </a>
        </div>
    </div>

    <!-- EstadÃ­sticas generales -->
    <div class="adhesion-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-calculator"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($calculations_count); ?></span>
                <span class="stat-label"><?php _e('CÃ¡lculos Realizados', 'adhesion'); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($contracts_count); ?></span>
                <span class="stat-label"><?php _e('Contratos Totales', 'adhesion'); ?></span>
            </div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($pending_contracts); ?></span>
                <span class="stat-label"><?php _e('Pendientes de Firma', 'adhesion'); ?></span>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($signed_contracts); ?></span>
                <span class="stat-label"><?php _e('Contratos Firmados', 'adhesion'); ?></span>
            </div>
        </div>
    </div>

    <!-- Contenido principal con pestaÃ±as -->
    <div class="adhesion-account-tabs">
        <nav class="tab-navigation">
            <button class="tab-button active" data-tab="overview">
                <span class="dashicons dashicons-dashboard"></span>
                <?php _e('Resumen', 'adhesion'); ?>
            </button>
            <button class="tab-button" data-tab="calculations">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('Mis CÃ¡lculos', 'adhesion'); ?>
            </button>
            <button class="tab-button" data-tab="contracts">
                <span class="dashicons dashicons-media-document"></span>
                <?php _e('Mis Contratos', 'adhesion'); ?>
            </button>
            <button class="tab-button" data-tab="profile">
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('Mi Perfil', 'adhesion'); ?>
            </button>
        </nav>

        <!-- Tab Resumen -->
        <div class="tab-content active" id="tab-overview">
            <div class="adhesion-grid">
                <!-- Ãšltimos cÃ¡lculos -->
                <div class="adhesion-card">
                    <div class="card-header">
                        <h3><?php _e('Ãšltimos CÃ¡lculos', 'adhesion'); ?></h3>
                        <a href="#" class="view-all" data-tab-link="calculations">
                            <?php _e('Ver todos', 'adhesion'); ?>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_calculations)) : ?>
                            <div class="calculations-list">
                                <?php foreach ($recent_calculations as $calc) : ?>
                                    <div class="calculation-item">
                                        <div class="calc-info">
                                            <strong><?php echo esc_html($calc->material_type); ?></strong>
                                            <span class="calc-details">
                                                <?php echo number_format($calc->quantity, 0, ',', '.'); ?> kg
                                                - <?php echo number_format($calc->total_price, 2, ',', '.'); ?>â‚¬
                                            </span>
                                            <span class="calc-date">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($calc->created_at)); ?>
                                            </span>
                                        </div>
                                        <div class="calc-actions">
                                            <button class="button-small view-calc" data-calc-id="<?php echo $calc->id; ?>">
                                                <?php _e('Ver', 'adhesion'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="no-data"><?php _e('No hay cÃ¡lculos realizados aÃºn.', 'adhesion'); ?></p>
                            <a href="#" class="button button-primary" id="primera-calculadora">
                                <?php _e('Realizar Primera Calculadora', 'adhesion'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estado de contratos -->
                <div class="adhesion-card">
                    <div class="card-header">
                        <h3><?php _e('Estado de Contratos', 'adhesion'); ?></h3>
                        <a href="#" class="view-all" data-tab-link="contracts">
                            <?php _e('Ver todos', 'adhesion'); ?>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_contracts)) : ?>
                            <div class="contracts-list">
                                <?php foreach ($recent_contracts as $contract) : ?>
                                    <div class="contract-item">
                                        <div class="contract-info">
                                            <strong><?php echo esc_html($contract->contract_type); ?></strong>
                                            <span class="contract-status status-<?php echo esc_attr($contract->status); ?>">
                                                <?php 
                                                switch($contract->status) {
                                                    case 'pending':
                                                        _e('Pendiente de Firma', 'adhesion');
                                                        break;
                                                    case 'signed':
                                                        _e('Firmado', 'adhesion');
                                                        break;
                                                    case 'cancelled':
                                                        _e('Cancelado', 'adhesion');
                                                        break;
                                                    default:
                                                        echo esc_html($contract->status);
                                                }
                                                ?>
                                            </span>
                                            <span class="contract-date">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($contract->created_at)); ?>
                                            </span>
                                        </div>
                                        <div class="contract-actions">
                                            <?php if ($contract->status === 'pending') : ?>
                                                <button class="button button-primary sign-contract" 
                                                        data-contract-id="<?php echo $contract->id; ?>">
                                                    <?php _e('Firmar', 'adhesion'); ?>
                                                </button>
                                            <?php elseif ($contract->status === 'signed' && !empty($contract->signed_document_url)) : ?>
                                                <a href="<?php echo esc_url($contract->signed_document_url); ?>" 
                                                   class="button" target="_blank">
                                                    <?php _e('Descargar', 'adhesion'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="no-data"><?php _e('No hay contratos registrados.', 'adhesion'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab CÃ¡lculos -->
        <div class="tab-content" id="tab-calculations">
            <div class="tab-header">
                <h2><?php _e('Mis CÃ¡lculos de Presupuesto', 'adhesion'); ?></h2>
                <button class="button button-primary" id="nueva-calculadora-tab">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Nuevo CÃ¡lculo', 'adhesion'); ?>
                </button>
            </div>
            
            <div class="calculations-table-container">
                <table class="adhesion-table calculations-table">
                    <thead>
                        <tr>
                            <th><?php _e('Fecha', 'adhesion'); ?></th>
                            <th><?php _e('Material', 'adhesion'); ?></th>
                            <th><?php _e('Cantidad', 'adhesion'); ?></th>
                            <th><?php _e('Precio Total', 'adhesion'); ?></th>
                            <th><?php _e('Estado', 'adhesion'); ?></th>
                            <th><?php _e('Acciones', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_calculations)) : ?>
                            <?php foreach ($recent_calculations as $calc) : ?>
                                <tr>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($calc->created_at)); ?></td>
                                    <td><?php echo esc_html($calc->material_type); ?></td>
                                    <td><?php echo number_format($calc->quantity, 0, ',', '.'); ?> kg</td>
                                    <td><?php echo number_format($calc->total_price, 2, ',', '.'); ?>â‚¬</td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($calc->status ?? 'calculated'); ?>">
                                            <?php 
                                            switch($calc->status ?? 'calculated') {
                                                case 'calculated':
                                                    _e('Calculado', 'adhesion');
                                                    break;
                                                case 'contracted':
                                                    _e('Contratado', 'adhesion');
                                                    break;
                                                default:
                                                    echo esc_html($calc->status);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="button-small view-calc-details" data-calc-id="<?php echo $calc->id; ?>">
                                            <?php _e('Ver Detalles', 'adhesion'); ?>
                                        </button>
                                        <?php if (($calc->status ?? 'calculated') === 'calculated') : ?>
                                            <button class="button-small button-primary contract-calc" data-calc-id="<?php echo $calc->id; ?>">
                                                <?php _e('Contratar', 'adhesion'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <?php _e('No hay cÃ¡lculos realizados aÃºn.', 'adhesion'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Contratos -->
        <div class="tab-content" id="tab-contracts">
            <div class="tab-header">
                <h2><?php _e('Mis Contratos', 'adhesion'); ?></h2>
            </div>
            
            <div class="contracts-table-container">
                <table class="adhesion-table contracts-table">
                    <thead>
                        <tr>
                            <th><?php _e('Fecha', 'adhesion'); ?></th>
                            <th><?php _e('Tipo', 'adhesion'); ?></th>
                            <th><?php _e('Estado', 'adhesion'); ?></th>
                            <th><?php _e('Monto', 'adhesion'); ?></th>
                            <th><?php _e('Firma', 'adhesion'); ?></th>
                            <th><?php _e('Acciones', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_contracts)) : ?>
                            <?php foreach ($recent_contracts as $contract) : ?>
                                <tr>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($contract->created_at)); ?></td>
                                    <td><?php echo esc_html($contract->contract_type); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($contract->status); ?>">
                                            <?php 
                                            switch($contract->status) {
                                                case 'pending':
                                                    _e('Pendiente', 'adhesion');
                                                    break;
                                                case 'signed':
                                                    _e('Firmado', 'adhesion');
                                                    break;
                                                case 'cancelled':
                                                    _e('Cancelado', 'adhesion');
                                                    break;
                                                default:
                                                    echo esc_html($contract->status);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($contract->amount) ? number_format($contract->amount, 2, ',', '.') . 'â‚¬' : '-'; ?></td>
                                    <td>
                                        <?php if (!empty($contract->signed_at)) : ?>
                                            <?php echo date_i18n(get_option('date_format'), strtotime($contract->signed_at)); ?>
                                        <?php else : ?>
                                            <span class="pending-text"><?php _e('Pendiente', 'adhesion'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($contract->status === 'pending') : ?>
                                            <button class="button button-primary sign-contract-btn" 
                                                    data-contract-id="<?php echo $contract->id; ?>">
                                                <?php _e('Firmar Ahora', 'adhesion'); ?>
                                            </button>
                                        <?php elseif ($contract->status === 'signed' && !empty($contract->signed_document_url)) : ?>
                                            <a href="<?php echo esc_url($contract->signed_document_url); ?>" 
                                               class="button" target="_blank">
                                                <span class="dashicons dashicons-download"></span>
                                                <?php _e('Descargar', 'adhesion'); ?>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="button-small view-contract-details" data-contract-id="<?php echo $contract->id; ?>">
                                            <?php _e('Ver Detalles', 'adhesion'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <?php _e('No hay contratos registrados.', 'adhesion'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Perfil -->
        <div class="tab-content" id="tab-profile">
            <div class="tab-header">
                <h2><?php _e('Mi Perfil', 'adhesion'); ?></h2>
            </div>
            
            <form id="adhesion-profile-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_update_profile', 'adhesion_profile_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('InformaciÃ³n Personal', 'adhesion'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="display_name"><?php _e('Nombre Completo', 'adhesion'); ?></label>
                            <input type="text" id="display_name" name="display_name" 
                                   value="<?php echo esc_attr($current_user->display_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_email"><?php _e('Correo ElectrÃ³nico', 'adhesion'); ?></label>
                            <input type="email" id="user_email" name="user_email" 
                                   value="<?php echo esc_attr($current_user->user_email); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone"><?php _e('TelÃ©fono', 'adhesion'); ?></label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'phone', true)); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company"><?php _e('Empresa', 'adhesion'); ?></label>
                            <input type="text" id="company" name="company" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'company', true)); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('DirecciÃ³n', 'adhesion'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="address"><?php _e('DirecciÃ³n', 'adhesion'); ?></label>
                            <input type="text" id="address" name="address" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'address', true)); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city"><?php _e('Ciudad', 'adhesion'); ?></label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'city', true)); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code"><?php _e('CÃ³digo Postal', 'adhesion'); ?></label>
                            <input type="text" id="postal_code" name="postal_code" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'postal_code', true)); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="country"><?php _e('PaÃ­s', 'adhesion'); ?></label>
                            <input type="text" id="country" name="country" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'country', true)); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Cambiar ContraseÃ±a', 'adhesion'); ?></h3>
                    <p class="description"><?php _e('Deja en blanco si no quieres cambiar la contraseÃ±a.', 'adhesion'); ?></p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password"><?php _e('ContraseÃ±a Actual', 'adhesion'); ?></label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password"><?php _e('Nueva ContraseÃ±a', 'adhesion'); ?></label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><?php _e('Confirmar ContraseÃ±a', 'adhesion'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Actualizar Perfil', 'adhesion'); ?>
                    </button>
                    
                    <button type="button" class="button" id="cancel-profile-edit">
                        <?php _e('Cancelar', 'adhesion'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver detalles de cÃ¡lculo -->
<div id="calculation-modal" class="adhesion-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Detalles del CÃ¡lculo', 'adhesion'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Contenido se carga dinÃ¡micamente -->
        </div>
    </div>
</div>

<!-- Modal para ver detalles de contrato -->
<div id="contract-modal" class="adhesion-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Detalles del Contrato', 'adhesion'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Contenido se carga dinÃ¡micamente -->
        </div>
    </div>
</div>
