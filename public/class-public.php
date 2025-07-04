<?php
/**
 * Clase principal del frontend público
 * 
 * Esta clase maneja todo el frontend del plugin:
 * - Shortcodes para calculadora, cuenta de usuario, pagos
 * - Enqueue de assets del frontend
 * - Hooks públicos
 * - Integración con WordPress público
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
        error_log('ADHESION DEBUG: Constructor de Adhesion_Public ejecutándose');
        
        $this->db = new Adhesion_Database();
        
        error_log('ADHESION DEBUG: Llamando a init_hooks()');
        $this->init_hooks();
        
        error_log('ADHESION DEBUG: Llamando a load_dependencies()');
        $this->load_dependencies();
        
        error_log('ADHESION DEBUG: Constructor de Adhesion_Public completado');
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
        
        // Redirecciones después del login
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);
        
        // Body classes
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Páginas de adhesión
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

        // Instanciar la clase de cuentas de usuario para que registre sus shortcodes
        Adhesion_User_Account::get_instance();

        // Instanciar la calculadora
        new Adhesion_Calculator();

    }
    
    /**
     * Registrar shortcodes
     */
    public function register_shortcodes() {
            error_log('ADHESION DEBUG: Registrando shortcode adhesion_calculator');
            add_shortcode('adhesion_calculator', array($this, 'calculator_shortcode'));
            add_shortcode('adhesion_payment', array($this, 'payment_shortcode'));
            add_shortcode('adhesion_contract_signing', array($this, 'contract_signing_shortcode'));
            error_log('ADHESION DEBUG: Shortcodes registrados en class-public');
    }
    
    /**
     * Encolar scripts y estilos
     */
    public function enqueue_scripts() {
        // Solo cargar en páginas necesarias
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
                'error' => __('Ha ocurrido un error. Por favor, inténtalo de nuevo.', 'adhesion'),
                'loading' => __('Cargando...', 'adhesion'),
                'success' => __('Operación completada con éxito.', 'adhesion'),
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
     * Assets específicos para calculadora
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
     * Assets específicos para pagos
     */
    private function enqueue_payment_assets() {
        // Script específico de Redsys si está configurado
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
        // Verificar si la calculadora está habilitada
        if (!adhesion_get_setting('calculator_enabled', '1')) {
            return '<div class="adhesion-notice adhesion-notice-warning">' . 
                '<p>' . __('La calculadora no está disponible temporalmente.', 'adhesion') . '</p>' .
                '</div>';
        }
        
        // Verificar si el usuario está logueado (según especificaciones)
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
        // Delegar a la clase especializada de user account
        if (class_exists('Adhesion_User_Account')) {
            $user_account = Adhesion_User_Account::get_instance();
            return $user_account->account_shortcode($atts);
        }
        
        // Fallback si la clase no existe
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }
        
        return '<div class="adhesion-notice adhesion-notice-error"><p>' . 
            __('Error: Clase de cuenta de usuario no encontrada.', 'adhesion') . 
            '</p></div>';
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
        
        // Verificar configuración de Redsys
        if (!adhesion_is_redsys_configured()) {
            return '<div class="adhesion-notice adhesion-notice-error">' . 
                   '<p>' . __('Los pagos no están configurados. Contacta con el administrador.', 'adhesion') . '</p>' .
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
        
        // Verificar configuración de DocuSign
        if (!adhesion_is_docusign_configured()) {
            return '<div class="adhesion-notice adhesion-notice-error">' . 
                   '<p>' . __('La firma digital no está configurada. Contacta con el administrador.', 'adhesion') . '</p>' .
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
                   '<p>' . sprintf(__('Ya estás logueado. <a href="%s">Ir a mi cuenta</a>', 'adhesion'), $account_url) . '</p>' .
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
                   '<p>' . sprintf(__('Ya estás registrado. <a href="%s">Ir a mi cuenta</a>', 'adhesion'), $account_url) . '</p>' .
                   '</div>';
        }
        
        // Verificar si el registro está habilitado
        if (!get_option('users_can_register')) {
            return '<div class="adhesion-notice adhesion-notice-warning">' . 
                   '<p>' . __('El registro de usuarios no está habilitado.', 'adhesion') . '</p>' .
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
     * Manejar envíos de formularios
     */
    public function handle_form_submissions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verificar nonce
        if (!isset($_POST['adhesion_nonce']) || !wp_verify_nonce($_POST['adhesion_nonce'], 'adhesion_form')) {
            return;
        }
        
        // Determinar qué formulario se envió
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
            // Verificar que el registro esté habilitado
            if (!get_option('users_can_register')) {
                throw new Exception(__('El registro no está habilitado.', 'adhesion'));
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
                throw new Exception(__('Las contraseñas no coinciden.', 'adhesion'));
            }
            
            if (username_exists($username)) {
                throw new Exception(__('El nombre de usuario ya existe.', 'adhesion'));
            }
            
            if (email_exists($email)) {
                throw new Exception(__('El email ya está registrado.', 'adhesion'));
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
            
            // Login automático
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            // Enviar email de bienvenida si está habilitado
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
     * Manejar guardado de cálculo
     */
    private function handle_save_calculation() {
        if (!is_user_logged_in()) {
            adhesion_add_notice(__('Debes estar logueado para guardar cálculos.', 'adhesion'), 'error');
            return;
        }
        
        try {
            $user_id = get_current_user_id();
            
            // Sanitizar datos del cálculo
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
                throw new Exception(__('Error al guardar el cálculo.', 'adhesion'));
            }
            
            adhesion_add_notice(__('Cálculo guardado correctamente.', 'adhesion'), 'success');
            
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
     * Verificar si estamos en una página de adhesión
     */
    private function is_adhesion_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Verificar shortcodes de adhesión en el contenido
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
     * Verificar si estamos en la página de calculadora
     */
    private function is_calculator_page() {
        global $post;
        return $post && has_shortcode($post->post_content, 'adhesion_calculator');
    }
    
    /**
     * Verificar si estamos en la página de pagos
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
        $message .= '<p>' . __('Debes iniciar sesión para acceder a esta función.', 'adhesion') . '</p>';
        $message .= '<div class="adhesion-login-buttons">';
        $message .= '<a href="' . esc_url($login_url) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar Sesión', 'adhesion') . '</a>';
        
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
     * Manejar redirección después del login
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
        // Actualizar última actividad
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
     * Manejar páginas especiales de adhesión
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
        // Esta funcionalidad se implementará en class-payment.php
        $payment_handler = new Adhesion_Payment();
        $payment_handler->handle_return();
    }
    
    /**
     * Manejar retorno de DocuSign
     */
    private function handle_docusign_return() {
        // Esta funcionalidad se implementará en class-docusign.php
        $docusign_handler = new Adhesion_DocuSign();
        $docusign_handler->handle_return();
    }
}