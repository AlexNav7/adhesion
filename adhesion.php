<?php
/**
 * Plugin Name: Adhesión - Proceso de Adhesión de Clientes
 * Plugin URI: https://tudominio.com
 * Description: Plugin para gestionar el proceso completo de adhesión de clientes con calculadora, pagos y firma de contratos.
 * Version: 1.0.0
 * Author: Alex Navarro Sempere
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

// Sistema de debug del plugin
if (!defined('ADHESION_DEBUG')) {
    define('ADHESION_DEBUG', true); // Cambiar a false para producción
}

if (defined('ADHESION_DEBUG') && ADHESION_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// ==========================================================
// CARGAR DEPENDENCIAS CRÍTICAS INMEDIATAMENTE
// ==========================================================

// Cargar las clases necesarias para los hooks de activación/desactivación
require_once ADHESION_PLUGIN_PATH . 'includes/class-activator.php';
require_once ADHESION_PLUGIN_PATH . 'includes/class-deactivator.php';

// ==========================================================
// REGISTRAR HOOKS DE ACTIVACIÓN/DESACTIVACIÓN
// ==========================================================

// AHORA SÍ podemos registrar los hooks porque las clases ya están cargadas
register_activation_hook(ADHESION_PLUGIN_FILE, array('Adhesion_Activator', 'activate'));
register_deactivation_hook(ADHESION_PLUGIN_FILE, array('Adhesion_Deactivator', 'deactivate'));

/**
 * Clase principal del plugin Adhesión
 */
class Adhesion_Plugin {

    /**
     * Instancia única del plugin
     */
    private static $instance = null;

    /**
     * Constructor privado para patrón singleton
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Obtener instancia única del plugin
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
        
        // Hooks de inicialización
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init_plugin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Las clases críticas ya se cargaron arriba, cargar el resto
        require_once ADHESION_PLUGIN_PATH . 'includes/class-database.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/class-ajax-handler.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/class-email-manager.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/functions.php';
    }

    /**
     * Inicializar componentes del plugin
     */
    public function init_plugin() {
        // Cargar componentes según el contexto
        if (is_admin()) {
            $this->init_admin();
        }
        
        $this->init_public();
        $this->init_ajax();

        Adhesion_Email_Manager::get_instance();
    }

    /**
     * Inicializar componentes del admin
     */
    private function init_admin() {
        require_once ADHESION_PLUGIN_PATH . 'admin/class-admin.php';
        new Adhesion_Admin();
    }

    /**
     * Inicializar componentes públicos
     */
    private function init_public() {
        error_log('ADHESION DEBUG: init_public() ejecutándose');
        
        require_once ADHESION_PLUGIN_PATH . 'public/class-public.php';
        require_once ADHESION_PLUGIN_PATH . 'public/class-user-account.php';
        require_once ADHESION_PLUGIN_PATH . 'public/class-contract-form.php';
        require_once ADHESION_PLUGIN_PATH . 'public/class-payment.php';
        
        error_log('ADHESION DEBUG: Archivos cargados, creando instancia de Adhesion_Public');
        new Adhesion_Public();
        
        error_log('ADHESION DEBUG: Instanciando Adhesion_User_Account');
        Adhesion_User_Account::get_instance();
        
        error_log('ADHESION DEBUG: Instanciando Adhesion_Contract_Form');
        new Adhesion_Contract_Form();
        
        error_log('ADHESION DEBUG: Instanciando Adhesion_Payment');
        new Adhesion_Payment();
        
        error_log('ADHESION DEBUG: init_public() completado');
    }

    /**
     * Inicializar manejador AJAX
     */
    private function init_ajax() {
        new Adhesion_Ajax_Handler();

        // Cargar handlers AJAX específicos
        require_once ADHESION_PLUGIN_PATH . 'includes/ajax/class-prices-ajax-handler.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/ajax/class-calculations-ajax-handler.php';
        
        // Cargar handler de transferencias si existe
        $transfer_handler_path = ADHESION_PLUGIN_PATH . 'includes/ajax/class-transfer-payment-ajax-handler.php';
        if (file_exists($transfer_handler_path)) {
            require_once $transfer_handler_path;
        }
        
        new Adhesion_Prices_Ajax_Handler();
        new Adhesion_Calculations_Ajax_Handler();
        
        // Instanciar handler de transferencias si la clase existe
        if (class_exists('Adhesion_Transfer_Payment_Ajax_Handler')) {
            new Adhesion_Transfer_Payment_Ajax_Handler();
        }

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
            'form_url' => $this->get_form_page_url(),
            'messages' => array(
                'error' => __('Ha ocurrido un error. Por favor, inténtalo de nuevo.', 'adhesion'),
                'loading' => __('Cargando...', 'adhesion'),
                'success' => __('Operación completada con éxito.', 'adhesion')
            )
        ));
    }

    /**
     * Encolar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en páginas del plugin
        if (strpos($hook, 'adhesion') === false) {
            return;
        }

        // JavaScript del admin (si no está comentado)
        wp_enqueue_script('jquery-ui-sortable');

        // CSS del admin
        wp_enqueue_style(
            'adhesion-admin-css',
            ADHESION_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ADHESION_PLUGIN_VERSION
        );

        // // JavaScript del admin
        // wp_enqueue_script(
        //     'adhesion-admin-js',
        //     ADHESION_PLUGIN_URL . 'assets/js/admin.js',
        //     array('jquery'),
        //     ADHESION_PLUGIN_VERSION,
        //     true
        // );

        // // Localizar script para AJAX del admin
        // wp_localize_script('adhesion-admin-js', 'adhesion_admin_ajax', array(
        //     'ajax_url' => admin_url('admin-ajax.php'),
        //     'nonce' => wp_create_nonce('adhesion_admin_nonce'),
        //     'messages' => array(
        //         'confirm_delete' => __('¿Estás seguro de que quieres eliminar este elemento?', 'adhesion'),
        //         'error' => __('Ha ocurrido un error. Por favor, inténtalo de nuevo.', 'adhesion'),
        //         'saved' => __('Cambios guardados correctamente.', 'adhesion')
        //     )
        // ));
    }
    
    /**
     * Obtener URL de la página de formulario
     */
    private function get_form_page_url() {
        $page_id = get_option('adhesion_settings')['page_formulario_adhesion'] ?? null;
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        // Fallback: buscar por slug
        $page = get_page_by_path('formulario-adhesion');
        if ($page) {
            return get_permalink($page->ID);
        }
        
        // Fallback final: URL por defecto
        return home_url('/formulario-adhesion/');
    }
}

/**
 * Función principal para obtener la instancia del plugin
 */
function adhesion() {
    return Adhesion_Plugin::get_instance();
}

// Inicializar el plugin cuando WordPress esté listo
add_action('plugins_loaded', 'adhesion');

/**
 * Funciones de utilidad globales
 */

/**
 * Obtener configuración del plugin
 */
function adhesion_get_option($key, $default = '') {
    $options = get_option('adhesion_settings', array());
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Actualizar configuración del plugin
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
 * Verificar si el usuario tiene permisos de adhesión
 */
function adhesion_user_can_access() {
    return is_user_logged_in() && (current_user_can('manage_options') || in_array('adhesion_client', wp_get_current_user()->roles));
}

/**
 * Hook que se ejecuta cuando el plugin se carga completamente
 */
do_action('adhesion_loaded');