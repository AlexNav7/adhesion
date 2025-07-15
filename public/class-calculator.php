<?php
/**
 * Clase para la calculadora de presupuestos
 * 
 * Esta clase maneja toda la lógica de la calculadora:
 * - Cálculos de precios por materiales
 * - Validaciones de cantidades
 * - Generación de presupuestos
 * - Guardado de cálculos
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
     * Precios de materiales en caché
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
        // Registrar shortcode de la calculadora
        add_shortcode('adhesion_calculator', array($this, 'calculator_shortcode'));

        // AJAX para usuarios logueados
        add_action('wp_ajax_adhesion_calculate_budget', array($this, 'ajax_calculate_budget'));
        add_action('wp_ajax_adhesion_save_calculation', array($this, 'ajax_save_calculation'));
        add_action('wp_ajax_adhesion_get_material_prices', array($this, 'ajax_get_material_prices'));
        add_action('wp_ajax_adhesion_validate_materials', array($this, 'ajax_validate_materials'));
        
        // AJAX para usuarios no logueados (solo cálculos, no guardado)
        add_action('wp_ajax_nopriv_adhesion_calculate_budget', array($this, 'ajax_calculate_budget_preview'));
        add_action('wp_ajax_nopriv_adhesion_get_material_prices', array($this, 'ajax_get_material_prices'));
    }
    
    /**
     * Cargar precios de materiales
     */
    private function load_material_prices() {
        // Usar caché transitorio para mejorar rendimiento
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
    // MÉTODOS PRINCIPALES DE CÁLCULO
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
            
            // Verificar pedido mínimo
            if ($options['minimum_order'] > 0 && $calculation_result['total_price'] < $options['minimum_order']) {
                $calculation_result['warnings'][] = sprintf(
                    __('El pedido mínimo es de %s. Cantidad actual: %s', 'adhesion'),
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
            adhesion_log('Error en cálculo de presupuesto: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Calcular un material específico
     */
    private function calculate_material($material, $options) {
        try {
            // Sanitizar y validar datos del material
            $material_type = sanitize_text_field($material['type']);
            $quantity = floatval($material['quantity']);
            
            // Validaciones básicas
            if (empty($material_type)) {
                throw new Exception(__('Tipo de material no especificado.', 'adhesion'));
            }
            
            if ($quantity <= 0) {
                throw new Exception(sprintf(__('Cantidad inválida para %s.', 'adhesion'), $material_type));
            }
            
            if ($quantity > 1000) { // Límite máximo de 1000 toneladas
                throw new Exception(sprintf(__('Cantidad máxima excedida para %s (máximo: 1000t).', 'adhesion'), $material_type));
            }
            
            // Verificar si el material está disponible
            if (!isset($this->material_prices[$material_type])) {
                throw new Exception(sprintf(__('Material no disponible: %s', 'adhesion'), $material_type));
            }
            
            $material_info = $this->material_prices[$material_type];
            
            // Verificar si el material está activo
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
            
            // Verificar cantidad mínima
            if ($minimum_quantity > 0 && $quantity < $minimum_quantity) {
                $result['warnings'][] = sprintf(
                    __('Cantidad mínima para %s es %s (actual: %s)', 'adhesion'),
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
     * Calcular descuento por volumen para un material específico
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
        
        // Usar descuentos específicos del material o los por defecto
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
            $discount += $subtotal * 0.03; // 3% para pedidos > 10.000€
        } elseif ($subtotal >= 5000) {
            $discount += $subtotal * 0.02; // 2% para pedidos > 5.000€
        }
        
        // Descuento adicional por tonelaje
        if ($total_tons >= 500) {
            $discount += $subtotal * 0.02; // 2% adicional para > 500t
        }
        
        return apply_filters('adhesion_calculate_discount', $discount, $subtotal, $total_tons);
    }
    
    // ==========================================
    // MÉTODOS AJAX
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
                throw new Exception(__('Error en el cálculo del presupuesto.', 'adhesion'));
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
     * AJAX: Vista previa de cálculo (usuarios no logueados)
     */
    public function ajax_calculate_budget_preview() {
        try {
            // Verificar nonce básico
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
                throw new Exception(__('Error en el cálculo del presupuesto.', 'adhesion'));
            }
            
            // Formatear resultado
            $formatted_result = $this->format_result_for_display($result);
            
            wp_send_json_success(array(
                'calculation' => $formatted_result,
                'can_save' => false,
                'login_required' => true,
                'message' => __('Vista previa del presupuesto. Inicia sesión para guardar.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Guardar cálculo
     */
    public function ajax_save_calculation() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!is_user_logged_in()) {
                throw new Exception(__('Debes estar logueado para guardar cálculos.', 'adhesion'));
            }
            
            $user_id = get_current_user_id();
            
            // Obtener datos del cálculo
            $calculation_data = json_decode(stripslashes($_POST['calculation_data']), true);
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            
            if (!$calculation_data) {
                throw new Exception(__('Datos de cálculo inválidos.', 'adhesion'));
            }
            
            // Añadir notas a los datos del cálculo
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
                throw new Exception(__('Error al guardar el cálculo en la base de datos.', 'adhesion'));
            }
            
            // Log de la acción
            adhesion_log("Cálculo guardado por usuario $user_id con ID $calculation_id", 'info');
            
            wp_send_json_success(array(
                'calculation_id' => $calculation_id,
                'message' => __('Cálculo guardado correctamente.', 'adhesion'),
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
    // MÉTODOS DE UTILIDAD
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
        
        // Verificar si está activo
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
            $result['errors'][] = sprintf(__('Cantidad máxima excedida para %s (máximo: 1000t)', 'adhesion'), $type);
        }
        
        // Verificar cantidad mínima (advertencia, no error)
        if ($material_info['minimum_quantity'] > 0 && $quantity < $material_info['minimum_quantity']) {
            $result['warnings'][] = sprintf(
                __('Cantidad mínima recomendada para %s: %s', 'adhesion'),
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
     * Limpiar caché de precios
     */
    public function clear_prices_cache() {
        delete_transient('adhesion_calculator_prices');
        $this->material_prices = null;
        $this->load_material_prices();
    }
    
    /**
     * Exportar cálculo a PDF (funcionalidad futura)
     */
    public function export_calculation_to_pdf($calculation_id) {
        // TODO: Implementar exportación a PDF
        // Esta funcionalidad se puede implementar más adelante
        return false;
    }

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
     * Mensaje cuando se requiere login
     */
    private function login_required_message() {
        $login_url = wp_login_url(get_permalink());
        $register_url = $this->get_register_url();
        
        $message = '<div class="adhesion-notice adhesion-notice-info">';
        $message .= '<div class="notice-content">';
        $message .= '<h3>' . __('Acceso requerido', 'adhesion') . '</h3>';
        $message .= '<p>' . __('Para usar la calculadora de presupuestos necesitas estar registrado.', 'adhesion') . '</p>';
        $message .= '<div class="notice-actions">';
        $message .= '<a href="' . esc_url($login_url) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar sesión', 'adhesion') . '</a>';
        if ($register_url) {
            $message .= '<a href="' . esc_url($register_url) . '" class="adhesion-btn adhesion-btn-outline">' . __('Registrarse', 'adhesion') . '</a>';
        }
        $message .= '</div>';
        $message .= '</div>';
        $message .= '</div>';
        
        return $message;
    }

    /**
     * Obtener URL de registro
     */
    private function get_register_url() {
        $page_id = adhesion_get_setting('page_registro');
        return $page_id ? get_permalink($page_id) : wp_registration_url();
    }


}