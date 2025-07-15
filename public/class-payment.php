<?php
/**
 * Clase para integración con Redsys (pagos)
 * 
 * Esta clase maneja toda la integración con Redsys:
 * - Generación de formularios de pago
 * - Procesamiento de callbacks
 * - Validación de respuestas
 * - Gestión de estados de pago
 * - Integración con contratos
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
     * Configuración de Redsys
     */
    private $config;
    
    /**
     * URLs de Redsys según entorno
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
     * Cargar configuración de Redsys
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
    // MÉTODOS PRINCIPALES DE PAGO
    // ==========================================
    
    /**
     * Crear pago en Redsys
     */
    public function create_payment($contract_id, $amount, $description = '') {
        try {
            // Verificar configuración
            if (!$this->is_configured()) {
                throw new Exception(__('Redsys no está configurado correctamente.', 'adhesion'));
            }
            
            // Obtener información del contrato
            $contract = $this->db->get_contract($contract_id);
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Generar número de pedido único
            $order_number = $this->generate_order_number($contract_id);
            
            // Preparar datos del pago
            $payment_data = array(
                'DS_MERCHANT_AMOUNT' => $this->format_amount($amount),
                'DS_MERCHANT_ORDER' => $order_number,
                'DS_MERCHANT_MERCHANTCODE' => $this->config['merchant_code'],
                'DS_MERCHANT_CURRENCY' => $this->config['currency'],
                'DS_MERCHANT_TRANSACTIONTYPE' => '0', // Autorización
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
            
            // Codificar parámetros
            $merchant_parameters = base64_encode(json_encode($payment_data));
            
            // Generar firma
            $signature = $this->generate_signature($merchant_parameters, $order_number);
            
            // Actualizar contrato con información del pago
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
        
        // Verificar que es nuestra URL de notificación
        if (strpos($_SERVER['REQUEST_URI'], 'adhesion-redsys-notification') === false) {
            return;
        }
        
        try {
            adhesion_log('Callback recibido de Redsys', 'info');
            
            // Obtener parámetros
            $signature_version = $_POST['Ds_SignatureVersion'];
            $merchant_parameters = $_POST['Ds_MerchantParameters'];
            $signature = $_POST['Ds_Signature'];
            
            // Decodificar parámetros
            $parameters = json_decode(base64_decode($merchant_parameters), true);
            
            if (!$parameters) {
                throw new Exception('Parámetros inválidos en callback de Redsys');
            }
            
            // Log de parámetros recibidos
            adhesion_log('Parámetros Redsys: ' . json_encode($parameters), 'debug');
            
            // Verificar firma
            if (!$this->verify_signature($merchant_parameters, $signature, $parameters['Ds_Order'])) {
                throw new Exception('Firma inválida en callback de Redsys');
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
        
        // Si no tenemos contract_id en merchant_data, intentar extraerlo del número de orden
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
            
            // Si el pago está completo y es requerido antes de la firma, cambiar estado
            if (adhesion_get_setting('require_payment', '0') === '1') {
                $update_data['status'] = 'paid';
            }
            
            $this->db->update_contract_status($contract_id, null, $update_data);
            
            // Log del pago exitoso
            adhesion_log("Pago exitoso - Contrato: $contract_id, Orden: {$parameters['Ds_Order']}, Importe: {$parameters['Ds_Amount']}", 'info');
            
            // Enviar notificación por email si está habilitado
            if (adhesion_get_setting('email_notifications', '1')) {
                $this->send_payment_confirmation_email($contract_id, $parameters);
            }
            
            // Hook para otras acciones después del pago exitoso
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
            adhesion_log("Pago fallido - Contrato: $contract_id, Orden: {$parameters['Ds_Order']}, Código: {$parameters['Ds_Response']}", 'warning');
            
            // Hook para otras acciones después del pago fallido
            do_action('adhesion_payment_failed', $contract_id, $parameters);
            
        } catch (Exception $e) {
            adhesion_log('Error procesando pago fallido: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    // ==========================================
    // MÉTODOS AJAX
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
                throw new Exception(__('ID de contrato inválido.', 'adhesion'));
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
                'message' => __('Pago creado correctamente. Serás redirigido a Redsys.', 'adhesion')
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
    // MÉTODOS DE UTILIDAD
    // ==========================================
    
    /**
     * Verificar si Redsys está configurado
     */
    public function is_configured() {
        return !empty($this->config['merchant_code']) && 
               !empty($this->config['secret_key']) && 
               !empty($this->config['terminal']);
    }
    
    /**
     * Generar número de orden único
     */
    private function generate_order_number($contract_id) {
        // Formato: YYYYMMDDHHMMSS + contract_id (max 12 caracteres)
        $timestamp = date('ymdHis'); // 12 caracteres
        $contract_suffix = str_pad($contract_id, 4, '0', STR_PAD_LEFT); // 4 caracteres
        
        return $timestamp . $contract_suffix; // Total: 12 caracteres (máximo permitido por Redsys)
    }
    
    /**
     * Extraer contract_id del número de orden
     */
    private function extract_contract_id_from_order($order) {
        // Los últimos 4 caracteres son el contract_id
        if (strlen($order) >= 4) {
            return intval(substr($order, -4));
        }
        return null;
    }
    
    /**
     * Formatear importe para Redsys (céntimos)
     */
    private function format_amount($amount) {
        return str_pad(round($amount * 100), 1, '0', STR_PAD_LEFT);
    }
    
    /**
     * Parsear importe desde Redsys (céntimos a euros)
     */
    private function parse_amount($amount_centimos) {
        return floatval($amount_centimos) / 100;
    }
    
    /**
     * Generar firma HMAC-SHA256
     */
    private function generate_signature($merchant_parameters, $order) {
        // Generar clave específica para la orden
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
        // Códigos de respuesta exitosos (0000-0099)
        $code = intval($response_code);
        return $code >= 0 && $code <= 99;
    }
    
    /**
     * Obtener URL de Redsys según entorno
     */
    private function get_redsys_url() {
        return $this->redsys_urls[$this->config['environment']];
    }
    
    /**
     * Obtener URL de notificación
     */
    private function get_notification_url() {
        return add_query_arg('adhesion-redsys-notification', '1', home_url());
    }
    
    /**
     * Obtener URL de éxito
     */
    private function get_success_url($contract_id) {
        // Usar la página del formulario de adhesión con step=contract_signing
        $page_id = get_option('adhesion_settings')['page_formulario_adhesion'] ?? null;
        $base_url = $page_id ? get_permalink($page_id) : home_url('/formulario-adhesion/');
        
        return add_query_arg(array(
            'step' => 'contract_signing',
            'contract_id' => $contract_id,
            'payment_status' => 'success'
        ), $base_url);
    }
    
    /**
     * Obtener URL de error
     */
    private function get_error_url($contract_id) {
        // Volver al paso de pago con error
        $page_id = get_option('adhesion_settings')['page_formulario_adhesion'] ?? null;
        $base_url = $page_id ? get_permalink($page_id) : home_url('/formulario-adhesion/');
        
        return add_query_arg(array(
            'step' => 'payment',
            'contract_id' => $contract_id,
            'payment_status' => 'error'
        ), $base_url);
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
        
        // Obtener información del contrato
        $contract = $this->db->get_contract($contract_id);
        if (!$contract) {
            return;
        }
        
        // Verificar acceso del usuario
        if (!is_user_logged_in() || 
            ($contract['user_id'] != get_current_user_id() && !current_user_can('manage_options'))) {
            return;
        }
        
        // Mostrar mensaje según el resultado
        if ($result === 'success') {
            if ($contract['payment_status'] === 'completed') {
                adhesion_add_notice(__('¡Pago realizado correctamente! Tu contrato ha sido procesado.', 'adhesion'), 'success');
            } else {
                adhesion_add_notice(__('El pago está siendo procesado. Recibirás confirmación en breve.', 'adhesion'), 'info');
            }
        } else {
            adhesion_add_notice(__('Hubo un problema con el pago. Por favor, inténtalo de nuevo o contacta con nosotros.', 'adhesion'), 'error');
        }
        
        // Redireccionar para limpiar la URL
        $redirect_url = remove_query_arg(array('adhesion_payment_return', 'contract_id'));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Enviar email de confirmación de pago
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
                sprintf(__('Confirmación de pago - Contrato %s', 'adhesion'), $contract['contract_number']),
                'payment-confirmation',
                $email_data
            );
            
        } catch (Exception $e) {
            adhesion_log('Error enviando email de confirmación de pago: ' . $e->getMessage(), 'error');
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
                   '<p>' . __('Los pagos no están configurados. Contacta con el administrador.', 'adhesion') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include ADHESION_PLUGIN_PATH . 'public/partials/payment-form.php';
        return ob_get_clean();
    }
    
    /**
     * Obtener estadísticas de pagos
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
        
        // Tasa de conversión
        $total_attempts = $stats['total_payments'] + $stats['failed_payments'];
        $stats['conversion_rate'] = $total_attempts > 0 ? ($stats['total_payments'] / $total_attempts) * 100 : 0;
        
        return $stats;
    }
}