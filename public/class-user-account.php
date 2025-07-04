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
            <form id="adhesion-login-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_user_login', 'adhesion_login_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('Iniciar Sesión', 'adhesion'); ?></h3>
                    
                    <div class="form-group">
                        <label for="user_login"><?php _e('Email o Usuario', 'adhesion'); ?></label>
                        <input type="text" id="user_login" name="user_login" required>
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
                    
                    <?php if (!empty($atts['redirect'])) : ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-unlock"></span>
                        <?php _e('Iniciar Sesión', 'adhesion'); ?>
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
                        <label for="reg_email"><?php _e('Correo Electrónico', 'adhesion'); ?></label>
                        <input type="email" id="reg_email" name="user_email" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_password"><?php _e('Contraseña', 'adhesion'); ?></label>
                            <input type="password" id="reg_password" name="user_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_confirm_password"><?php _e('Confirmar Contraseña', 'adhesion'); ?></label>
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
                            <?php _e('Acepto los términos y condiciones', 'adhesion'); ?>
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
     * Obtener detalles de cálculo vía AJAX
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
        
        // Obtener cálculo
        $calculation = $this->db->get_calculation($calculation_id);
        
        if (!$calculation || $calculation->user_id != $user_id) {
            wp_send_json_error(__('Cálculo no encontrado o sin permisos.', 'adhesion'));
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
     * Obtener detalles de contrato vía AJAX
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
     * Procesar contratación de cálculo vía AJAX
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
        
        // Obtener cálculo
        $calculation = $this->db->get_calculation($calculation_id);
        
        if (!$calculation || $calculation->user_id != $user_id) {
            wp_send_json_error(__('Cálculo no encontrado o sin permisos.', 'adhesion'));
        }
        
        if ($calculation->status !== 'calculated') {
            wp_send_json_error(__('Este cálculo ya ha sido procesado.', 'adhesion'));
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
            
            // Actualizar estado del cálculo
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
     * Iniciar firma de contrato vía AJAX
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
            wp_send_json_error(__('Este contrato no está disponible para firma.', 'adhesion'));
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
                'subject' => sprintf(__('Contrato de Adhesión - %s', 'adhesion'), get_bloginfo('name')),
                'message' => __('Por favor, revisa y firma el contrato de adhesión.', 'adhesion')
            );
            
            $result = $docusign->send_envelope($envelope_data);
            
            if ($result['success']) {
                // Actualizar contrato con información de DocuSign
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
     * Actualizar perfil de usuario vía AJAX
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
                throw new Exception(__('Email no válido.', 'adhesion'));
            }
            
            // Verificar si el email ya existe (solo si es diferente al actual)
            if ($user_email !== $current_user->user_email) {
                if (email_exists($user_email)) {
                    throw new Exception(__('Este email ya está registrado.', 'adhesion'));
                }
            }
            
            // Actualizar datos básicos de usuario
            $user_data = array(
                'ID' => $user_id,
                'display_name' => $display_name,
                'user_email' => $user_email
            );
            
            // Manejar cambio de contraseña
            if (!empty($_POST['new_password'])) {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verificar contraseña actual
                if (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
                    throw new Exception(__('La contraseña actual no es correcta.', 'adhesion'));
                }
                
                // Verificar que coincidan las nuevas contraseñas
                if ($new_password !== $confirm_password) {
                    throw new Exception(__('Las contraseñas nuevas no coinciden.', 'adhesion'));
                }
                
                // Verificar longitud mínima
                if (strlen($new_password) < 6) {
                    throw new Exception(__('La contraseña debe tener al menos 6 caracteres.', 'adhesion'));
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
            
            // Log de la actualización
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
     * Verificar estado de contratos vía AJAX
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
        
        // Solo verificar si han pasado al menos 30 segundos desde la última verificación
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
            
            // Actualizar timestamp de última verificación
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
            wp_send_json_error(__('Usuario y contraseña son obligatorios.', 'adhesion'));
        }
        
        // Intentar login
        $creds = array(
            'user_login' => $user_login,
            'user_password' => $user_password,
            'remember' => $remember
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error(__('Usuario o contraseña incorrectos.', 'adhesion'));
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
                throw new Exception(__('Email no válido.', 'adhesion'));
            }
            
            if (email_exists($user_email)) {
                throw new Exception(__('Este email ya está registrado.', 'adhesion'));
            }
            
            if ($user_password !== $confirm_password) {
                throw new Exception(__('Las contraseñas no coinciden.', 'adhesion'));
            }
            
            if (strlen($user_password) < 6) {
                throw new Exception(__('La contraseña debe tener al menos 6 caracteres.', 'adhesion'));
            }
            
            if (!$accept_terms) {
                throw new Exception(__('Debes aceptar los términos y condiciones.', 'adhesion'));
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
            
            // Login automático después del registro
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            // Enviar email de bienvenida (si está configurado)
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
     * Generar contenido del contrato
     */
    private function generate_contract_content($calculation) {
        $user = wp_get_current_user();
        
        $content = sprintf(
            __('CONTRATO DE ADHESIÓN

Fecha: %s

DATOS DEL CLIENTE:
Nombre: %s
Email: %s
Empresa: %s

DATOS DEL SERVICIO:
Material: %s
Cantidad: %s kg
Precio por kg: %s€
Precio total: %s€

El cliente acepta los términos y condiciones del servicio.

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
        // Esto debería generar la URL hacia la página de pago
        $payment_page = get_option('adhesion_payment_page_id');
        
        if ($payment_page) {
            return add_query_arg(array(
                'contract_id' => $contract_id,
                'action' => 'process_payment'
            ), get_permalink($payment_page));
        }
        
        // Fallback: usar URL actual con parámetros
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

¡Bienvenido a %s!

Tu cuenta ha sido creada exitosamente. Ya puedes acceder a tu área de cliente para:
- Realizar cálculos de presupuesto
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
     * Obtener estadísticas del usuario
     */
    public function get_user_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Cálculos totales
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