<?php
/**
 * Clase para manejo de peticiones AJAX
 * 
 * Esta clase maneja todas las peticiones AJAX del plugin:
 * - Calculadora de presupuestos
 * - Gestión de formularios
 * - Operaciones CRUD
 * - Validación y seguridad
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
     * Manejar cálculo de presupuesto
     */
    public function handle_calculate() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
                throw new Exception(__('Error de seguridad. Recarga la página e inténtalo de nuevo.', 'adhesion'));
            }
            
            // Verificar que el usuario esté logueado
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
                'message' => __('Cálculo realizado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            adhesion_log('Error en cálculo AJAX: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Guardar cálculo en base de datos
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
                throw new Exception(__('Error al guardar el cálculo.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'calculation_id' => $calculation_id,
                'message' => __('Cálculo guardado correctamente.', 'adhesion')
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
            
            // Obtener cálculos del usuario
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
                throw new Exception(__('El email ya está registrado.', 'adhesion'));
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
     * Obtener cálculos para admin
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
                throw new Exception(__('Datos de precios inválidos.', 'adhesion'));
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
     * Obtener estadísticas para admin
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
            
            // Estadísticas del último mes
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
    // MÉTODOS AUXILIARES
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
     * Sanitizar datos de cálculo
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