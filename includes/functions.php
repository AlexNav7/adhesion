<?php
/**
 * Funciones auxiliares del plugin Adhesión
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

/**
 * Sistema de debug logging para el plugin
 * 
 * @param string $message Mensaje a logear
 * @param string $context Contexto adicional (opcional)
 */
function adhesion_debug_log($message, $context = '') {
    if (defined('ADHESION_DEBUG') && ADHESION_DEBUG) {
        $context_str = $context ? " - {$context}" : '';
        error_log("[ADHESION DEBUG{$context_str}] {$message}");
    }
}

/**
 * Log de errores (siempre se registra, independiente del debug)
 * 
 * @param string $message Mensaje de error
 * @param string $context Contexto del error
 */
function adhesion_error_log($message, $context = '') {
    $context_str = $context ? " - {$context}" : '';
    error_log("[ADHESION ERROR{$context_str}] {$message}");
}

/**
 * Log de información importante (siempre se registra)
 * 
 * @param string $message Mensaje informativo
 * @param string $context Contexto
 */
function adhesion_info_log($message, $context = '') {
    $context_str = $context ? " - {$context}" : '';
    error_log("[ADHESION INFO{$context_str}] {$message}");
}

// ==========================================
// FUNCIONES DE FORMATO
// ==========================================

/**
 * Formatear precio con moneda
 */
function adhesion_format_price($price, $currency = '€') {
    return number_format($price, 2, ',', '.') . ' ' . $currency;
}

/**
 * Formatear cantidad en toneladas
 */
function adhesion_format_tons($tons) {
    return number_format($tons, 2, ',', '.') . ' t';
}

/**
 * Formatear fecha en español
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
// FUNCIONES DE VALIDACIÓN
// ==========================================

/**
 * Validar DNI/CIF español
 */
function adhesion_validate_dni_cif($value) {
    $value = strtoupper(trim($value));
    
    // Validar DNI (8 números + 1 letra)
    if (preg_match('/^[0-9]{8}[A-Z]$/', $value)) {
        $number = substr($value, 0, 8);
        $letter = substr($value, 8, 1);
        $valid_letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $calculated_letter = $valid_letters[intval($number) % 23];
        return $letter === $calculated_letter;
    }
    
    // Validar CIF (1 letra + 7 números + 1 dígito de control)
    if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $value)) {
        return true; // Validación básica para CIF
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
 * Validar teléfono español
 */
function adhesion_validate_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Formatos válidos: 9 dígitos, +34 seguido de 9 dígitos
    return preg_match('/^(?:\+34)?[67][0-9]{8}$/', $phone);
}

/**
 * Validar código postal español
 */
function adhesion_validate_postal_code($code) {
    return preg_match('/^[0-5][0-9]{4}$/', $code);
}

/**
 * Validar cantidad de material
 */
function adhesion_validate_material_quantity($quantity) {
    $quantity = floatval($quantity);
    return $quantity > 0 && $quantity <= 1000; // Máximo 1000 toneladas
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
 * Generar número de pedido único
 */
function adhesion_generate_order_number($prefix = 'ADH') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(wp_generate_password(6, false));
}

/**
 * Obtener configuración del plugin
 */
function adhesion_get_setting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $settings = get_option('adhesion_settings', array());
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Actualizar configuración del plugin
 */
function adhesion_update_setting($key, $value) {
    $settings = get_option('adhesion_settings', array());
    $settings[$key] = $value;
    return update_option('adhesion_settings', $settings);
}

/**
 * Verificar si las integraciones están configuradas
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
 * Generar URL de acción
 */
function adhesion_get_action_url($action, $extra_args = array()) {
    $args = array_merge(array('adhesion_action' => $action), $extra_args);
    return add_query_arg($args, home_url());
}

// ==========================================
// FUNCIONES DE NOTIFICACIÓN
// ==========================================

/**
 * Agregar notificación al usuario
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
    // Obtener configuración de email
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
    
    // Verificar capacidades específicas del plugin
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
    
    // Fallback a capacidades estándar de WordPress
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
 * Obtener información del sistema
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

