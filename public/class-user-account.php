<?php
/**
 * Clase para gestión de cuentas de usuario
 * Archivo: public/class-user-account.php
 * 
 * Maneja toda la funcionalidad del dashboard de usuario:
 * - Visualización de datos
 * - Actualización de perfil
 * - Gestión de cálculos y contratos
 * - Shortcodes relacionados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_User_Account {
    
    /**
     * Instancia única de la clase
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
     * Obtener instancia única
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
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            return $this->login_shortcode($atts);
        }
        
        $current_user = wp_get_current_user();
        
        // Verificar permisos
        if (!$this->user_can_access_account($current_user)) {
            return '<div class="adhesion-message error">' . 
                   __('No tienes permisos para acceder a esta área.', 'adhesion') . 
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
                __('Ya estás conectado.', 'adhesion') . 
                ' <a href="' . wp_logout_url(get_permalink()) . '">' . __('Cerrar sesión', 'adhesion') . '</a>' .
                '</div>';
        }
        
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_register_link' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
        <div class="adhesion-login-form">
            
            <?php if ($atts['show_register_link'] === 'yes') : ?>
                <div class="adhesion-form-header">
                    <p><?php _e('¿No tienes cuenta?', 'adhesion'); ?> 
                    <a href="<?php echo home_url('/registro/'); ?>">
                        <?php _e('Crear cuenta', 'adhesion'); ?>
                    </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <form id="adhesion-login-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_user_login', 'adhesion_login_nonce'); ?>
                <input type="hidden" name="action" value="adhesion_user_login">
                <div class="form-section">
                    <h3><?php _e('Iniciar Sesión', 'adhesion'); ?></h3>
                    
                    <div class="form-group">
                        <label for="user_login"><?php _e('Email', 'adhesion'); ?></label>
                        <input type="email" id="user_login" name="user_login" required 
                            placeholder="<?php _e('Introduzca su email', 'adhesion'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="user_password"><?php _e('Contraseña', 'adhesion'); ?></label>
                        <input type="password" id="user_password" name="user_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="remember" value="1">
                            <?php _e('Recordarme', 'adhesion'); ?>
                        </label>
                    </div>
                    
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($atts['redirect']); ?>">
                    
                    <div class="form-actions">
                        <button type="submit" class="adhesion-btn adhesion-btn-primary">
                            <?php _e('Iniciar Sesión', 'adhesion'); ?>
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="adhesion-form-messages"></div>
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
                __('Ya estás registrado y conectado.', 'adhesion') . 
                '</div>';
        }
        
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_login_link' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
        <div class="adhesion-register-form">
            
            <?php if ($atts['show_login_link'] === 'yes') : ?>
                <div class="adhesion-form-header">
                    <p><?php _e('¿Ya tienes cuenta?', 'adhesion'); ?> 
                    <a href="<?php echo home_url('/mi-cuenta/'); ?>">
                        <?php _e('Inicia sesión', 'adhesion'); ?>
                    </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <form id="adhesion-register-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_user_register', 'adhesion_register_nonce'); ?>
                <input type="hidden" name="action" value="adhesion_user_register">
                <div class="form-section">
                    <h3><?php _e('Crear Cuenta', 'adhesion'); ?></h3>
                    
                    <!-- Email - Campo principal para login -->
                    <div class="form-group">
                        <label for="reg_email"><?php _e('Correo Electrónico *', 'adhesion'); ?></label>
                        <input type="email" id="reg_email" name="user_email" required>
                    </div>
                    
                    
                    <!-- Nombre completo -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_first_name"><?php _e('Nombre *', 'adhesion'); ?></label>
                            <input type="text" id="reg_first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_last_name"><?php _e('Apellidos *', 'adhesion'); ?></label>
                            <input type="text" id="reg_last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    
                    <!-- Contraseñas -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_password"><?php _e('Contraseña *', 'adhesion'); ?></label>
                            <input type="password" id="reg_password" name="user_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_confirm_password"><?php _e('Confirmar Contraseña *', 'adhesion'); ?></label>
                            <input type="password" id="reg_confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <!-- Términos y condiciones -->
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="accept_terms" value="1" required>
                            <?php _e('Acepto los términos y condiciones *', 'adhesion'); ?>
                        </label>
                    </div>
                    
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($atts['redirect']); ?>">
                    
                    <div class="form-actions">
                        <button type="submit" class="adhesion-btn adhesion-btn-primary">
                            <?php _e('Crear Cuenta', 'adhesion'); ?>
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="adhesion-form-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    

    /**
     * ================================
     * PROCESAMIENTO AJAX
     * ================================
     */
    
    /**
     * Procesar login de usuario vía AJAX
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
            wp_send_json_error(__('Email y contraseña son obligatorios.', 'adhesion'));
        }
        
        // Intentar login
        $creds = array(
            'user_login' => $user_login,
            'user_password' => $user_password,
            'remember' => $remember
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error(__('Email o contraseña incorrectos.', 'adhesion'));
        }
        
        // Verificar que es usuario de adhesión
        if (!in_array('adhesion_client', $user->roles)) {
            wp_send_json_error(__('No tienes permisos para acceder a esta área.', 'adhesion'));
        }
        
        // Login exitoso
        wp_send_json_success(array(
            'message' => __('Login exitoso. Redirigiendo...', 'adhesion'),
            'redirect_to' => $redirect_to ?: get_permalink()
        ));
    }
    
    /**
     * Procesar registro de usuario vía AJAX
     */
    public function ajax_user_register() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['adhesion_register_nonce'], 'adhesion_user_register')) {
            wp_die(__('Error de seguridad.', 'adhesion'));
        }
        
        try {
            // Validar datos requeridos según especificaciones v4
            $user_email = sanitize_email($_POST['user_email']);
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $user_password = $_POST['user_password'];
            $confirm_password = $_POST['confirm_password'];
            $accept_terms = isset($_POST['accept_terms']);
            $redirect_to = isset($_POST['redirect_to']) ? esc_url($_POST['redirect_to']) : '';
            
            // Validaciones obligatorias
            if (empty($user_email) || empty($first_name) || empty($last_name) || empty($user_password)) {
                throw new Exception(__('Todos los campos obligatorios deben ser completados.', 'adhesion'));
            }
            
            // Validar formato de email
            if (!is_email($user_email)) {
                throw new Exception(__('Email no válido.', 'adhesion'));
            }
            
            // Verificar que el email no exista como email o como username
            if (email_exists($user_email) || username_exists($user_email)) {
                throw new Exception(__('Este email ya está registrado en el sistema.', 'adhesion'));
            }
            
            
            // Verificar contraseñas
            if ($user_password !== $confirm_password) {
                throw new Exception(__('Las contraseñas no coinciden.', 'adhesion'));
            }
            
            if (strlen($user_password) < 6) {
                throw new Exception(__('La contraseña debe tener al menos 6 caracteres.', 'adhesion'));
            }
            
            // Verificar términos
            if (!$accept_terms) {
                throw new Exception(__('Debes aceptar los términos y condiciones.', 'adhesion'));
            }
            
            // Crear usuario - EMAIL como username
            $user_data = array(
                'user_login' => $user_email,  // Email será el usuario
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
            
            // Agregar metadatos adicionales según especificaciones
            update_user_meta($user_id, 'registration_date', current_time('mysql'));
            update_user_meta($user_id, 'terms_accepted', current_time('mysql'));
            
            // Login automático después del registro
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            adhesion_debug_log('Usuario creado con ID: ' . $user_id . ', intentando enviar email...', 'REGISTRO');

            // Enviar email de bienvenida
            $this->send_welcome_email($user_id);

            adhesion_debug_log('Email de bienvenida procesado', 'REGISTRO');
            
            // Log del registro
            adhesion_log(sprintf('New user registered: %s (ID: %d)', $user_email, $user_id), 'info');
            
            wp_send_json_success(array(
                'message' => __('Cuenta creada correctamente. Bienvenido!', 'adhesion'),
                'redirect_to' => $redirect_to ?: home_url('/calculadora-presupuesto/')
            ));
            
        } catch (Exception $e) {
            adhesion_log('Error during user registration: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }    


    /**
     * ================================
     * MÉTODOS AUXILIARES
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
                'error' => __('Ha ocurrido un error. Por favor, inténtalo de nuevo.', 'adhesion'),
                'loading' => __('Cargando...', 'adhesion'),
                'success' => __('Operación completada con éxito.', 'adhesion'),
                'confirmContract' => __('¿Estás seguro de que quieres proceder con la contratación?', 'adhesion'),
                'confirmDelete' => __('¿Estás seguro de que quieres eliminar este elemento?', 'adhesion')
            )
        ));
    }
    
    /**
     * Enviar email de bienvenida (usando Email Manager)
     */
    private function send_welcome_email($user_id) {
        adhesion_debug_log('=== INICIANDO send_welcome_email() ===', 'EMAIL');
        adhesion_debug_log('User ID recibido: ' . $user_id, 'EMAIL');
        
        try {
            // Verificar que Email Manager existe
            if (!class_exists('Adhesion_Email_Manager')) {
                adhesion_error_log('Clase Adhesion_Email_Manager no encontrada', 'EMAIL');
                return false;
            }
            
            adhesion_debug_log('Clase Email Manager encontrada, obteniendo instancia...', 'EMAIL');
            
            // Usar la nueva clase Email Manager
            $email_manager = Adhesion_Email_Manager::get_instance();
            
            if (!$email_manager) {
                adhesion_error_log('No se pudo obtener instancia de Email Manager', 'EMAIL');
                return false;
            }
            
            adhesion_debug_log('Instancia Email Manager obtenida, enviando email...', 'EMAIL');
            
            $result = $email_manager->send_welcome_email($user_id);
            
            adhesion_debug_log('Resultado send_welcome_email(): ' . ($result ? 'SUCCESS' : 'FAILED'), 'EMAIL');
            adhesion_debug_log('=== FIN send_welcome_email() ===', 'EMAIL');
            
            return $result;
            
        } catch (Exception $e) {
            adhesion_error_log('Excepción en send_welcome_email(): ' . $e->getMessage(), 'EMAIL');
            adhesion_debug_log('=== FIN send_welcome_email() (CON ERROR) ===', 'EMAIL');
            return false;
        }
    }


    /**
     * AJAX - Obtener detalles de cálculo
     */
    public function ajax_get_calculation_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $calculation_id = intval($_POST['calculation_id']);
        
        // TODO: Implementar obtención de detalles del cálculo
        wp_send_json_success(array(
            'message' => __('Funcionalidad en desarrollo.', 'adhesion')
        ));
    }
    
    /**
     * AJAX - Obtener detalles de contrato
     */
    public function ajax_get_contract_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        $contract_id = intval($_POST['contract_id']);
        
        // TODO: Implementar obtención de detalles del contrato
        wp_send_json_success(array(
            'message' => __('Funcionalidad en desarrollo.', 'adhesion')
        ));
    }
    
    /**
     * AJAX - Procesar contrato desde cálculo
     */
    public function ajax_process_calculation_contract() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        // TODO: Implementar procesamiento de contrato
        wp_send_json_success(array(
            'message' => __('Funcionalidad en desarrollo.', 'adhesion')
        ));
    }
    
    /**
     * AJAX - Iniciar firma de contrato
     */
    public function ajax_initiate_contract_signing() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        // TODO: Implementar inicio de firma con DocuSign
        wp_send_json_success(array(
            'message' => __('Funcionalidad en desarrollo.', 'adhesion')
        ));
    }
    
    /**
     * AJAX - Actualizar perfil de usuario
     */
    public function ajax_update_user_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        // TODO: Implementar actualización de perfil
        wp_send_json_success(array(
            'message' => __('Funcionalidad en desarrollo.', 'adhesion')
        ));
    }
    
    /**
     * AJAX - Verificar estado de contrato
     */
    public function ajax_check_contract_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes estar logueado.', 'adhesion'));
        }
        
        // TODO: Implementar verificación de estado
        wp_send_json_success(array(
            'message' => __('Funcionalidad en desarrollo.', 'adhesion')
        ));
    }

    

}