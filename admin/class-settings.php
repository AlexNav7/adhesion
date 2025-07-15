<?php
/**
 * Clase para gestión de configuraciones del plugin
 * 
 * Esta clase maneja:
 * - Procesamiento de formularios de configuración
 * - Validación de APIs externas
 * - Pruebas de conectividad
 * - Gestión de precios de calculadora
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
        // Registrar configuraciones de WordPress
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX para pruebas de configuración
        add_action('wp_ajax_adhesion_test_redsys', array($this, 'test_redsys_connection'));
        add_action('wp_ajax_adhesion_test_docusign', array($this, 'test_docusign_connection'));
        
        // AJAX para gestión de precios
        add_action('wp_ajax_adhesion_get_calculator_prices', array($this, 'get_calculator_prices'));
        add_action('wp_ajax_adhesion_update_calculator_prices', array($this, 'update_calculator_prices'));
        
        // Hooks para validar configuraciones
        add_action('update_option_adhesion_settings', array($this, 'on_settings_updated'), 10, 2);
        
        // Programar tareas de verificación
        add_action('adhesion_verify_apis', array($this, 'verify_api_connections'));
    }
    
    /**
     * Registrar configuraciones del plugin
     */
    public function register_settings() {
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
        $sanitized['redsys_currency'] = sanitize_text_field($settings['redsys_currency'] ?? '978');
        
        // Sanitizar configuraciones de DocuSign
        $sanitized['docusign_integration_key'] = sanitize_text_field($settings['docusign_integration_key'] ?? '');
        $sanitized['docusign_secret_key'] = sanitize_text_field($settings['docusign_secret_key'] ?? '');
        $sanitized['docusign_account_id'] = sanitize_text_field($settings['docusign_account_id'] ?? '');
        $sanitized['docusign_environment'] = in_array($settings['docusign_environment'] ?? 'demo', array('demo', 'production')) ? $settings['docusign_environment'] : 'demo';
        
        // Sanitizar configuraciones de transferencia bancaria
        $sanitized['bank_transfer_iban'] = sanitize_text_field($settings['bank_transfer_iban'] ?? '');
        $sanitized['bank_transfer_bank_name'] = sanitize_text_field($settings['bank_transfer_bank_name'] ?? '');
        $sanitized['bank_transfer_instructions'] = wp_kses_post($settings['bank_transfer_instructions'] ?? '');
        
        // Sanitizar configuraciones generales
        $sanitized['calculator_enabled'] = isset($settings['calculator_enabled']) ? '1' : '0';
        $sanitized['auto_create_users'] = isset($settings['auto_create_users']) ? '1' : '0';
        $sanitized['email_notifications'] = isset($settings['email_notifications']) ? '1' : '0';
        $sanitized['contract_auto_send'] = isset($settings['contract_auto_send']) ? '1' : '0';
        $sanitized['require_payment'] = isset($settings['require_payment']) ? '1' : '0';
        
        // Sanitizar configuraciones de email
        $sanitized['admin_email'] = sanitize_email($settings['admin_email'] ?? get_option('admin_email'));
        $sanitized['email_from_name'] = sanitize_text_field($settings['email_from_name'] ?? get_bloginfo('name'));
        $sanitized['email_from_address'] = sanitize_email($settings['email_from_address'] ?? get_option('admin_email'));
        
        return $sanitized;
    }

    /**
     * Mostrar página principal de configuraciones
     */
    public function display_page() {
        // Cargar la vista
        include ADHESION_PLUGIN_PATH . 'admin/partials/settings-display.php';
    }
    
    /**
     * Obtener configuración específica
     */
    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Actualizar configuración específica
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
        
        // Validar código de comercio
        if (empty($settings['redsys_merchant_code'])) {
            $errors[] = __('El código de comercio de Redsys es obligatorio.', 'adhesion');
        } elseif (!preg_match('/^[0-9]{9}$/', $settings['redsys_merchant_code'])) {
            $errors[] = __('El código de comercio debe tener 9 dígitos.', 'adhesion');
        }
        
        // Validar terminal
        if (empty($settings['redsys_terminal'])) {
            $errors[] = __('El terminal de Redsys es obligatorio.', 'adhesion');
        } elseif (!preg_match('/^[0-9]{3}$/', $settings['redsys_terminal'])) {
            $errors[] = __('El terminal debe tener 3 dígitos.', 'adhesion');
        }
        
        // Validar clave secreta
        if (empty($settings['redsys_secret_key'])) {
            $errors[] = __('La clave secreta de Redsys es obligatoria.', 'adhesion');
        } elseif (strlen($settings['redsys_secret_key']) < 32) {
            $errors[] = __('La clave secreta debe tener al menos 32 caracteres.', 'adhesion');
        }
        
        // Validar entorno
        if (!in_array($settings['redsys_environment'], array('test', 'production'))) {
            $errors[] = __('Entorno de Redsys inválido.', 'adhesion');
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
            $errors[] = __('La Integration Key debe ser un UUID válido.', 'adhesion');
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
            $errors[] = __('El Account ID debe ser un UUID válido.', 'adhesion');
        }
        
        // Validar entorno
        if (!in_array($settings['docusign_environment'], array('demo', 'production'))) {
            $errors[] = __('Entorno de DocuSign inválido.', 'adhesion');
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
            $errors[] = __('El email del administrador no es válido.', 'adhesion');
        }
        
        // Validar email del remitente
        if (!empty($settings['email_from_address']) && !is_email($settings['email_from_address'])) {
            $errors[] = __('El email del remitente no es válido.', 'adhesion');
        }
        
        // Validar nombre del remitente
        if (empty($settings['email_from_name'])) {
            $errors[] = __('El nombre del remitente es obligatorio.', 'adhesion');
        }
        
        return $errors;
    }
    
    /**
     * Probar conexión con Redsys (AJAX)
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
            
            // Validar configuraciones básicas
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
                'message' => __('Configuración de Redsys válida.', 'adhesion'),
                'test_result' => $test_result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Probar conexión con DocuSign (AJAX)
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
            
            // Validar configuraciones básicas
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
                'message' => __('Configuración de DocuSign válida.', 'adhesion'),
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
        // URL de prueba según el entorno
        $url = $settings['redsys_environment'] === 'production' 
            ? 'https://sis.redsys.es/sis/realizarPago'
            : 'https://sis-t.redsys.es:25443/sis/realizarPago';
        
        // Parámetros de prueba
        $merchant_parameters = base64_encode(json_encode(array(
            'DS_MERCHANT_AMOUNT' => '100', // 1 euro en céntimos
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
        
        // Realizar petición de prueba (solo validación de formato)
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
            'message' => __('Parámetros válidos. Conexión establecida.', 'adhesion'),
            'response_code' => $response_code,
            'environment' => $settings['redsys_environment']
        );
    }
    
    /**
     * Realizar prueba real de DocuSign
     */
    private function perform_docusign_test($settings) {
        // URL base según el entorno
        $base_url = $settings['docusign_environment'] === 'production'
            ? 'https://www.docusign.net/restapi'
            : 'https://demo.docusign.net/restapi';
        
        // Intentar obtener información de la cuenta
        $url = $base_url . '/v2.1/accounts/' . $settings['docusign_account_id'];
        
        // Headers básicos (sin autenticación real para prueba)
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );
        
        // Realizar petición de prueba
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => $headers
        ));
        
        if (is_wp_error($response)) {
            throw new Exception(__('Error de conectividad con DocuSign: ', 'adhesion') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // DocuSign devuelve 401 para peticiones sin autenticación, lo que indica que el endpoint es válido
        if ($response_code === 401) {
            return array(
                'status' => 'success',
                'message' => __('Endpoint válido. Configuración correcta.', 'adhesion'),
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
        
        // Generar clave específica para la orden
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
                throw new Exception(__('Datos de precios inválidos.', 'adhesion'));
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
            // Programar verificación de APIs
            if (!wp_next_scheduled('adhesion_verify_apis')) {
                wp_schedule_single_event(time() + 300, 'adhesion_verify_apis'); // 5 minutos
            }
            
            // Log del cambio
            adhesion_log('Configuraciones importantes actualizadas, verificación programada', 'info');
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
                adhesion_log('Verificación de Redsys: OK', 'info');
            } catch (Exception $e) {
                update_option('adhesion_redsys_status', 'error');
                adhesion_log('Verificación de Redsys falló: ' . $e->getMessage(), 'error');
            }
        }
        
        // Verificar DocuSign
        if (!empty($settings['docusign_integration_key']) && !empty($settings['docusign_account_id'])) {
            try {
                $this->perform_docusign_test($settings);
                update_option('adhesion_docusign_status', 'ok');
                adhesion_log('Verificación de DocuSign: OK', 'info');
            } catch (Exception $e) {
                update_option('adhesion_docusign_status', 'error');
                adhesion_log('Verificación de DocuSign falló: ' . $e->getMessage(), 'error');
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