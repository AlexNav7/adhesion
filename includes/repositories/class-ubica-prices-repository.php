<?php
/**
 * Repository para precios de UBICA
 * 
 * Gestiona operaciones de base de datos para:
 * - Material + Tipo (Doméstico/Comercial/Industrial) × Toneladas
 * - CRUD completo de materiales UBICA
 * - Validaciones específicas de UBICA
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Ubica_Prices_Repository extends Adhesion_Base_Repository {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table_name = 'adhesion_ubica_prices';
        parent::__construct();
    }
    
    /**
     * Obtener todos los precios UBICA
     */
    public function get_all_prices($order_by = 'sort_order', $order = 'ASC') {
        return $this->get_all($order_by, $order);
    }
    
    /**
     * Obtener solo precios activos de UBICA
     */
    public function get_active_prices($order_by = 'sort_order', $order = 'ASC') {
        return $this->get_active($order_by, $order);
    }
    
    /**
     * Verificar si un material ya existe
     */
    public function material_exists($material_name, $exclude_id = null) {
        if (empty($material_name)) {
            return false;
        }
        
        $table_name = $this->get_table_name();
        $material_name = sanitize_text_field($material_name);
        
        $sql = "SELECT id FROM {$table_name} WHERE material_name = %s";
        $params = array($material_name);
        
        // Si estamos actualizando, excluir el ID actual
        if ($exclude_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $prepared_sql = $this->wpdb->prepare($sql, $params);
        $exists = $this->wpdb->get_var($prepared_sql);
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al verificar si existe material '{$material_name}': " . $this->wpdb->last_error);
            return false;
        }
        
        return $exists !== null;
    }
    
    /**
     * Añadir nuevo precio UBICA
     */
    public function add_price($material_name, $price_domestic, $price_commercial, $price_industrial) {
        // Validar datos
        $validation_errors = $this->validate_price_data($material_name, $price_domestic, $price_commercial, $price_industrial);
        if (!empty($validation_errors)) {
            $this->log_error("Errores de validación al añadir precio UBICA: " . implode(', ', $validation_errors));
            return false;
        }
        
        // Verificar que no existe ya
        if ($this->material_exists($material_name)) {
            $this->log_error("El material '{$material_name}' ya existe en UBICA");
            return false;
        }
        
        // Preparar datos
        $data = array(
            'material_name' => sanitize_text_field($material_name),
            'price_domestic' => floatval($price_domestic),
            'price_commercial' => floatval($price_commercial),
            'price_industrial' => floatval($price_industrial)
        );
        
        $data = $this->prepare_common_data($data, false);
        
        $table_name = $this->get_table_name();
        
        $result = $this->wpdb->insert(
            $table_name,
            $data,
            array('%s', '%f', '%f', '%f', '%d', '%d')
        );
        
        if ($result === false) {
            $this->log_error("Error al insertar precio UBICA: " . $this->wpdb->last_error);
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Actualizar precio UBICA existente
     */
    public function update_price($id, $material_name, $price_domestic, $price_commercial, $price_industrial) {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }
        
        // Validar datos
        $validation_errors = $this->validate_price_data($material_name, $price_domestic, $price_commercial, $price_industrial);
        if (!empty($validation_errors)) {
            $this->log_error("Errores de validación al actualizar precio UBICA ID {$id}: " . implode(', ', $validation_errors));
            return false;
        }
        
        // Verificar que no existe otro material con el mismo nombre
        if ($this->material_exists($material_name, $id)) {
            $this->log_error("Ya existe otro material con el nombre '{$material_name}' en UBICA");
            return false;
        }
        
        // Preparar datos
        $data = array(
            'material_name' => sanitize_text_field($material_name),
            'price_domestic' => floatval($price_domestic),
            'price_commercial' => floatval($price_commercial),
            'price_industrial' => floatval($price_industrial)
        );
        
        $table_name = $this->get_table_name();
        
        $result = $this->wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            array('%s', '%f', '%f', '%f'),
            array('%d')
        );
        
        if ($result === false) {
            $this->log_error("Error al actualizar precio UBICA ID {$id}: " . $this->wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener precio por ID
     */
    public function get_price_by_id($id) {
        return $this->get_by_id($id);
    }
    
    /**
     * Eliminar precio UBICA
     */
    public function delete_price($id) {
        return $this->delete($id);
    }
    
    /**
     * Cambiar estado activo/inactivo
     */
    public function toggle_price_status($id) {
        return $this->toggle_status($id);
    }
    
    /**
     * Actualizar orden de precios (drag & drop)
     */
    public function update_prices_order($id_order_array) {
        return $this->update_sort_order($id_order_array);
    }
    
    /**
     * Obtener precios para calculadora (solo activos)
     */
    public function get_calculator_prices() {
        $table_name = $this->get_table_name();
        
        $sql = "SELECT 
                    material_name,
                    price_domestic,
                    price_commercial,
                    price_industrial,
                    sort_order
                FROM {$table_name} 
                WHERE is_active = 1 
                ORDER BY sort_order ASC";
        
        $results = $this->wpdb->get_results($sql);
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al obtener precios para calculadora UBICA: " . $this->wpdb->last_error);
            return array();
        }
        
        return $results ? $results : array();
    }
    
    /**
     * Calcular precio por material y tipo
     */
    public function calculate_price($material_name, $type, $tons) {
        if (empty($material_name) || empty($type) || !is_numeric($tons) || $tons <= 0) {
            return null;
        }
        
        $table_name = $this->get_table_name();
        
        $sql = $this->wpdb->prepare(
            "SELECT price_domestic, price_commercial, price_industrial 
             FROM {$table_name} 
             WHERE material_name = %s AND is_active = 1",
            sanitize_text_field($material_name)
        );
        
        $price_data = $this->wpdb->get_row($sql);
        
        if (!$price_data) {
            return null;
        }
        
        $price_per_ton = 0;
        switch (strtolower($type)) {
            case 'domestic':
            case 'domestico':
                $price_per_ton = $price_data->price_domestic;
                break;
            case 'commercial':
            case 'comercial':
                $price_per_ton = $price_data->price_commercial;
                break;
            case 'industrial':
                $price_per_ton = $price_data->price_industrial;
                break;
            default:
                return null;
        }
        
        return array(
            'material' => $material_name,
            'type' => $type,
            'tons' => floatval($tons),
            'price_per_ton' => floatval($price_per_ton),
            'total_price' => floatval($price_per_ton) * floatval($tons)
        );
    }
    
    /**
     * Validar datos de precio UBICA
     */
    private function validate_price_data($material_name, $price_domestic, $price_commercial, $price_industrial) {
        $errors = array();
        
        // Validar nombre del material
        if (empty($material_name) || strlen(trim($material_name)) < 2) {
            $errors[] = __('El nombre del material debe tener al menos 2 caracteres.', 'adhesion');
        }
        
        if (strlen($material_name) > 100) {
            $errors[] = __('El nombre del material no puede exceder 100 caracteres.', 'adhesion');
        }
        
        // Validar precios
        if (!is_numeric($price_domestic) || $price_domestic < 0) {
            $errors[] = __('El precio doméstico debe ser un número positivo.', 'adhesion');
        }
        
        if (!is_numeric($price_commercial) || $price_commercial < 0) {
            $errors[] = __('El precio comercial debe ser un número positivo.', 'adhesion');
        }
        
        if (!is_numeric($price_industrial) || $price_industrial < 0) {
            $errors[] = __('El precio industrial debe ser un número positivo.', 'adhesion');
        }
        
        // Validar que al menos un precio sea mayor que 0
        if (floatval($price_domestic) == 0 && floatval($price_commercial) == 0 && floatval($price_industrial) == 0) {
            $errors[] = __('Al menos uno de los precios debe ser mayor que 0.', 'adhesion');
        }
        
        return $errors;
    }
    
    /**
     * Sobrescribir sanitize_order_by para campos específicos de UBICA
     */
    protected function sanitize_order_by($order_by) {
        $allowed_fields = array(
            'id', 
            'material_name', 
            'price_domestic', 
            'price_commercial', 
            'price_industrial', 
            'sort_order', 
            'is_active', 
            'created_at', 
            'updated_at'
        );
        
        if (in_array($order_by, $allowed_fields)) {
            return $order_by;
        }
        
        return 'sort_order'; // Default fallback
    }
    
    /**
     * Obtener estadísticas de precios UBICA
     */
    public function get_statistics() {
        $table_name = $this->get_table_name();
        
        $stats = array();
        
        // Total de materiales
        $stats['total_materials'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Materiales activos
        $stats['active_materials'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE is_active = 1");
        
        // Precio promedio por tipo
        $stats['avg_price_domestic'] = $this->wpdb->get_var("SELECT AVG(price_domestic) FROM {$table_name} WHERE is_active = 1");
        $stats['avg_price_commercial'] = $this->wpdb->get_var("SELECT AVG(price_commercial) FROM {$table_name} WHERE is_active = 1");
        $stats['avg_price_industrial'] = $this->wpdb->get_var("SELECT AVG(price_industrial) FROM {$table_name} WHERE is_active = 1");
        
        // Material más caro por tipo
        $stats['max_price_domestic'] = $this->wpdb->get_var("SELECT MAX(price_domestic) FROM {$table_name} WHERE is_active = 1");
        $stats['max_price_commercial'] = $this->wpdb->get_var("SELECT MAX(price_commercial) FROM {$table_name} WHERE is_active = 1");
        $stats['max_price_industrial'] = $this->wpdb->get_var("SELECT MAX(price_industrial) FROM {$table_name} WHERE is_active = 1");
        
        return $stats;
    }
}