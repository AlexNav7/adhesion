<?php
/**
 * Clase para gestión del formulario de contrato
 * 
 * Esta clase maneja:
 * - Formulario de datos de empresa
 * - Formulario de datos del representante legal
 * - Validación de datos
 * - Redirección después del cálculo
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Contract_Form {
    
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
     * Inicializar hooks
     */
    private function init_hooks() {
        // Shortcode para mostrar el formulario
        add_shortcode('adhesion_contract_form', array($this, 'contract_form_shortcode'));
        
        // AJAX para procesar el formulario
        add_action('wp_ajax_adhesion_save_company_data', array($this, 'ajax_save_company_data'));
        add_action('wp_ajax_adhesion_validate_company_data', array($this, 'ajax_validate_company_data'));
        
        // Hook para procesar el formulario en la misma página
        add_action('wp', array($this, 'process_form_submission'));
    }
    
    /**
     * Procesar envío del formulario
     */
    public function process_form_submission() {
        // Verificar si estamos en la página correcta y hay datos del formulario
        if (!isset($_GET['step']) || $_GET['step'] !== 'company_data') {
            return;
        }
        
        if (!isset($_POST['adhesion_company_form_nonce']) || !wp_verify_nonce($_POST['adhesion_company_form_nonce'], 'adhesion_company_form')) {
            return;
        }
        
        // Procesar datos del formulario
        $this->save_company_data();
    }
    
    /**
     * Shortcode del formulario de contrato
     */
    public function contract_form_shortcode($atts) {
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }
        
        // Obtener step actual
        $step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'calculator';
        $calc_id = isset($_GET['calc_id']) ? intval($_GET['calc_id']) : 0;
        
        // Mostrar formulario según el step
        switch ($step) {
            case 'company_data':
                return $this->render_company_data_form($calc_id);
            case 'payment':
                return $this->render_payment_form($calc_id);
            case 'contract_signing':
                return $this->render_contract_signing($calc_id);
            default:
                // Mostrar calculadora por defecto
                return do_shortcode('[adhesion_calculator]');
        }
    }
    
    /**
     * Renderizar formulario de datos de empresa
     */
    private function render_company_data_form($calc_id) {
        // Obtener datos del cálculo si existe y verificar ownership
        $calculation = null;
        if ($calc_id > 0) {
            $calculation = $this->db->get_calculation($calc_id);
            
            // Verificar que el cálculo pertenece al usuario actual
            if ($calculation && !$this->verify_calculation_ownership($calc_id)) {
                // Anular el cálculo y mostrar mensaje de error
                $calculation = null;
                add_action('wp_footer', function() {
                    echo '<script>
                        if (typeof showMessage === "function") {
                            showMessage("Error: No tienes permisos para acceder a este cálculo", "error");
                        }
                    </script>';
                });
            }
        }
        
        // Obtener datos existentes del usuario si los hay
        $user_id = get_current_user_id();
        $existing_data = $this->get_existing_company_data($user_id);
        
        // Hacer variables disponibles para el partial
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/company-data-form.php';
        return ob_get_clean();
    }
    
    /**
     * Obtener datos existentes de empresa del usuario
     */
    private function get_existing_company_data($user_id) {
        // Buscar datos existentes en la base de datos
        $existing_contract = $this->db->get_user_latest_contract($user_id);
        
        if ($existing_contract) {
            return json_decode($existing_contract['client_data'], true);
        }
        
        // Si no hay datos previos, obtener del perfil de usuario
        return array(
            'company_name' => get_user_meta($user_id, 'company_name', true),
            'cif' => get_user_meta($user_id, 'cif', true),
            'address' => get_user_meta($user_id, 'address', true),
            'city' => get_user_meta($user_id, 'city', true),
            'postal_code' => get_user_meta($user_id, 'postal_code', true),
            'province' => get_user_meta($user_id, 'province', true),
            'cnae' => get_user_meta($user_id, 'cnae', true),
            'phone' => get_user_meta($user_id, 'phone', true),
            'email' => get_user_meta($user_id, 'email', true),
            'legal_representative_name' => get_user_meta($user_id, 'legal_representative_name', true),
            'legal_representative_surname' => get_user_meta($user_id, 'legal_representative_surname', true),
            'legal_representative_dni' => get_user_meta($user_id, 'legal_representative_dni', true),
            'legal_representative_phone' => get_user_meta($user_id, 'legal_representative_phone', true),
            'legal_representative_email' => get_user_meta($user_id, 'legal_representative_email', true)
        );
    }
    
    /**
     * Guardar datos de empresa
     */
    private function save_company_data() {
        try {
            // Obtener y sanitizar datos
            $company_data = $this->sanitize_company_data($_POST);
            
            // Validar datos
            $validation = $this->validate_company_data($company_data);
            if (!$validation['is_valid']) {
                wp_die('Error de validación: ' . implode(', ', $validation['errors']));
            }
            
            $user_id = get_current_user_id();
            $calc_id = intval($_POST['calc_id']);
            
            // Verificar ownership del cálculo si se proporciona
            if (!$this->verify_calculation_ownership($calc_id, $user_id)) {
                wp_die('Error: No tienes permisos para usar este cálculo.');
            }
            
            // Guardar en base de datos
            $contract_id = $this->db->create_contract(
                $user_id,
                $calc_id,
                $company_data,
                'company_data_completed'
            );
            
            if ($contract_id) {
                // Actualizar meta del usuario con los nuevos datos
                $this->update_user_meta_from_company_data($user_id, $company_data);
                
                // Redirigir al siguiente paso
                wp_redirect(add_query_arg(array(
                    'step' => 'payment',
                    'contract_id' => $contract_id
                ), get_permalink()));
                exit;
            } else {
                wp_die('Error al guardar los datos. Inténtalo de nuevo.');
            }
            
        } catch (Exception $e) {
            wp_die('Error procesando formulario: ' . $e->getMessage());
        }
    }
    
    /**
     * Sanitizar datos del formulario
     */
    private function sanitize_company_data($data) {
        return array(
            'company_name' => sanitize_text_field($data['company_name'] ?? ''),
            'cif' => sanitize_text_field($data['cif'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'postal_code' => sanitize_text_field($data['postal_code'] ?? ''),
            'province' => sanitize_text_field($data['province'] ?? ''),
            'cnae' => sanitize_text_field($data['cnae'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'legal_representative_name' => sanitize_text_field($data['legal_representative_name'] ?? ''),
            'legal_representative_surname' => sanitize_text_field($data['legal_representative_surname'] ?? ''),
            'legal_representative_dni' => sanitize_text_field($data['legal_representative_dni'] ?? ''),
            'legal_representative_phone' => sanitize_text_field($data['legal_representative_phone'] ?? ''),
            'legal_representative_email' => sanitize_email($data['legal_representative_email'] ?? '')
        );
    }
    
    /**
     * Validar datos de empresa
     */
    private function validate_company_data($data) {
        $result = array(
            'is_valid' => true,
            'errors' => array()
        );
        
        // Validaciones requeridas
        $required_fields = array(
            'company_name' => 'Denominación social',
            'cif' => 'CIF',
            'address' => 'Domicilio social',
            'city' => 'Municipio',
            'postal_code' => 'Código postal',
            'province' => 'Provincia',
            'phone' => 'Teléfono',
            'email' => 'Email',
            'legal_representative_name' => 'Nombre del representante legal',
            'legal_representative_surname' => 'Apellidos del representante legal',
            'legal_representative_dni' => 'DNI del representante legal',
            'legal_representative_phone' => 'Teléfono del representante legal'
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $result['is_valid'] = false;
                $result['errors'][] = "El campo '$label' es obligatorio";
            }
        }
        
        // Validaciones específicas
        if (!empty($data['cif']) && !$this->validate_cif($data['cif'])) {
            $result['is_valid'] = false;
            $result['errors'][] = 'El CIF no tiene un formato válido';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $result['is_valid'] = false;
            $result['errors'][] = 'El email no tiene un formato válido';
        }
        
        if (!empty($data['legal_representative_email']) && !is_email($data['legal_representative_email'])) {
            $result['is_valid'] = false;
            $result['errors'][] = 'El email del representante legal no tiene un formato válido';
        }
        
        if (!empty($data['legal_representative_dni']) && !$this->validate_dni($data['legal_representative_dni'])) {
            $result['is_valid'] = false;
            $result['errors'][] = 'El DNI del representante legal no tiene un formato válido';
        }
        
        return $result;
    }
    
    /**
     * Validar formato CIF
     */
    private function validate_cif($cif) {
        $cif = strtoupper(trim($cif));
        return preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $cif);
    }
    
    /**
     * Validar formato DNI
     */
    private function validate_dni($dni) {
        $dni = strtoupper(trim($dni));
        return preg_match('/^[0-9]{8}[A-Z]$/', $dni);
    }
    
    /**
     * Actualizar meta del usuario con datos de empresa
     */
    private function update_user_meta_from_company_data($user_id, $company_data) {
        foreach ($company_data as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
    }
    
    /**
     * Verificar que el cálculo pertenece al usuario actual
     */
    private function verify_calculation_ownership($calc_id, $user_id = null) {
        if ($calc_id <= 0) {
            return true; // No hay cálculo que verificar
        }
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $calculation = $this->db->get_calculation($calc_id);
        
        if (!$calculation || $calculation['user_id'] != $user_id) {
            adhesion_log(
                sprintf(
                    'Usuario %d intentó acceder al cálculo %d sin permisos',
                    $user_id,
                    $calc_id
                ),
                'warning'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * AJAX: Guardar datos de empresa
     */
    public function ajax_save_company_data() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception('Error de seguridad');
            }
            
            if (!is_user_logged_in()) {
                throw new Exception('Debes estar logueado');
            }
            
            // Procesar datos igual que en el formulario normal
            $company_data = $this->sanitize_company_data($_POST);
            $validation = $this->validate_company_data($company_data);
            
            if (!$validation['is_valid']) {
                wp_send_json_error($validation['errors']);
            }
            
            $user_id = get_current_user_id();
            $calc_id = intval($_POST['calc_id']);
            
            // Verificar ownership del cálculo si se proporciona
            if (!$this->verify_calculation_ownership($calc_id, $user_id)) {
                wp_send_json_error('No tienes permisos para usar este cálculo');
            }
            
            $contract_id = $this->db->create_contract(
                $user_id,
                $calc_id,
                $company_data,
                'company_data_completed'
            );
            
            if ($contract_id) {
                $this->update_user_meta_from_company_data($user_id, $company_data);
                
                wp_send_json_success(array(
                    'contract_id' => $contract_id,
                    'message' => 'Datos guardados correctamente',
                    'redirect_url' => add_query_arg(array(
                        'step' => 'payment',
                        'contract_id' => $contract_id
                    ), get_permalink())
                ));
            } else {
                throw new Exception('Error al guardar los datos');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Validar datos de empresa
     */
    public function ajax_validate_company_data() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception('Error de seguridad');
            }
            
            if (!is_user_logged_in()) {
                throw new Exception('Debes estar logueado');
            }
            
            // Verificar ownership del cálculo si se proporciona
            $calc_id = intval($_POST['calc_id'] ?? 0);
            if (!$this->verify_calculation_ownership($calc_id)) {
                wp_send_json_error('No tienes permisos para usar este cálculo');
            }
            
            $company_data = $this->sanitize_company_data($_POST);
            $validation = $this->validate_company_data($company_data);
            
            wp_send_json_success($validation);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Renderizar formulario de pago
     */
    private function render_payment_form($calc_id) {
        // Obtener contract_id de la URL
        $contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;
        
        if ($contract_id <= 0) {
            return '<div class="adhesion-notice adhesion-notice-error">
                <h3>Error</h3>
                <p>No se ha encontrado el contrato. Por favor, vuelve a completar el formulario de datos.</p>
                <p><a href="' . esc_url(remove_query_arg(array('step', 'contract_id'))) . '" class="adhesion-btn adhesion-btn-primary">Volver al Formulario</a></p>
            </div>';
        }
        
        // Verificar que el contrato pertenece al usuario actual
        $contract = $this->db->get_contract($contract_id);
        if (!$contract || $contract['user_id'] != get_current_user_id()) {
            return '<div class="adhesion-notice adhesion-notice-error">
                <h3>Error de Acceso</h3>
                <p>No tienes permisos para acceder a este contrato.</p>
            </div>';
        }
        
        // Obtener instancia del payment handler
        $payment = new Adhesion_Payment();
        
        // Verificar que Redsys está configurado
        if (!$payment->is_configured()) {
            return '<div class="adhesion-notice adhesion-notice-error">
                <h3>Pagos No Disponibles</h3>
                <p>El sistema de pagos no está configurado. Por favor, contacta con el administrador.</p>
            </div>';
        }
        
        // Obtener información del cálculo asociado
        $calculation = null;
        if ($contract['calculation_id']) {
            $calculation = $this->db->get_calculation($contract['calculation_id']);
        }
        
        // Determinar el importe a pagar
        $amount = 0;
        if ($calculation && $calculation['total_price']) {
            $amount = floatval($calculation['total_price']);
        }
        
        if ($amount <= 0) {
            return '<div class="adhesion-notice adhesion-notice-warning">
                <h3>Importe no Válido</h3>
                <p>No se ha podido determinar el importe a pagar. Por favor, vuelve a la calculadora.</p>
            </div>';
        }
        
        // Renderizar vista de pago
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/payment-display.php';
        return ob_get_clean();
    }
    
    /**
     * Renderizar firma de contrato (placeholder)
     */
    private function render_contract_signing($calc_id) {
        return '<div class="adhesion-notice adhesion-notice-info">
            <h3>Paso 3: Firma de Contrato</h3>
            <p>Funcionalidad de firma digital pendiente de implementación.</p>
        </div>';
    }
    
    /**
     * Mensaje cuando se requiere login
     */
    private function login_required_message() {
        $login_url = wp_login_url(get_permalink());
        return '<div class="adhesion-notice adhesion-notice-error">
            <h3>Acceso requerido</h3>
            <p>Para continuar con el proceso de adhesión necesitas estar registrado.</p>
            <p><a href="' . esc_url($login_url) . '" class="adhesion-btn adhesion-btn-primary">Iniciar sesión</a></p>
        </div>';
    }
}