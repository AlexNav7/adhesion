<?php
/**
 * Clase base para repositories
 * 
 * Proporciona funcionalidad común para todos los repositories:
 * - Conexión a base de datos
 * - Métodos auxiliares comunes
 * - Logging de errores
 * - Validaciones básicas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

abstract class Adhesion_Base_Repository {
    
    /**
     * Instancia de WordPress Database
     */
    protected $wpdb;
    
    /**
     * Nombre de la tabla (debe ser definido en las clases hijas)
     */
    protected $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // La clase hija debe definir $this->table_name
        if (empty($this->table_name)) {
            throw new Exception('La clase repository debe definir $table_name');
        }
    }
    
    /**
     * Obtener el nombre completo de la tabla con prefijo
     */
    protected function get_table_name() {
        return $this->wpdb->prefix . $this->table_name;
    }
    
    /**
     * Verificar si la tabla existe
     */
    public function table_exists() {
        $table_name = $this->get_table_name();
        $query = $this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        return $this->wpdb->get_var($query) === $table_name;
    }
    
    /**
     * Obtener todos los registros
     */
    public function get_all($order_by = 'sort_order', $order = 'ASC') {
        $table_name = $this->get_table_name();
        
        // Sanitizar order_by y order para prevenir SQL injection
        $allowed_orders = array('ASC', 'DESC');
        $order = in_array(strtoupper($order), $allowed_orders) ? strtoupper($order) : 'ASC';
        
        // Solo permitir columnas válidas (esto debe ser sobrescrito en clases hijas si es necesario)
        $order_by = $this->sanitize_order_by($order_by);
        
        $sql = "SELECT * FROM {$table_name} ORDER BY {$order_by} {$order}";
        
        $results = $this->wpdb->get_results($sql);
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al obtener registros: " . $this->wpdb->last_error);
            return array();
        }
        
        return $results ? $results : array();
    }
    
    /**
     * Obtener solo registros activos
     */
    public function get_active($order_by = 'sort_order', $order = 'ASC') {
        $table_name = $this->get_table_name();
        
        $order = in_array(strtoupper($order), array('ASC', 'DESC')) ? strtoupper($order) : 'ASC';
        $order_by = $this->sanitize_order_by($order_by);
        
        $sql = "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY {$order_by} {$order}";
        
        $results = $this->wpdb->get_results($sql);
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al obtener registros activos: " . $this->wpdb->last_error);
            return array();
        }
        
        return $results ? $results : array();
    }
    
    /**
     * Obtener registro por ID
     */
    public function get_by_id($id) {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }
        
        $table_name = $this->get_table_name();
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $id
        );
        
        $result = $this->wpdb->get_row($sql);
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al obtener registro por ID {$id}: " . $this->wpdb->last_error);
            return null;
        }
        
        return $result;
    }
    
    /**
     * Eliminar registro por ID
     */
    public function delete($id) {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }
        
        $table_name = $this->get_table_name();
        
        $result = $this->wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al eliminar registro ID {$id}: " . $this->wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Cambiar estado activo/inactivo
     */
    public function toggle_status($id) {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }
        
        $table_name = $this->get_table_name();
        
        // Primero obtener el estado actual
        $current = $this->get_by_id($id);
        if (!$current) {
            return false;
        }
        
        $new_status = $current->is_active ? 0 : 1;
        
        $result = $this->wpdb->update(
            $table_name,
            array('is_active' => $new_status),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
        if ($this->wpdb->last_error) {
            $this->log_error("Error al cambiar estado del registro ID {$id}: " . $this->wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Actualizar orden de elementos (drag & drop)
     */
    public function update_sort_order($id_order_array) {
        if (!is_array($id_order_array) || empty($id_order_array)) {
            return false;
        }
        
        $table_name = $this->get_table_name();
        $success = true;
        
        foreach ($id_order_array as $order => $id) {
            if (!is_numeric($id) || $id <= 0) {
                continue;
            }
            
            $result = $this->wpdb->update(
                $table_name,
                array('sort_order' => $order + 1), // +1 porque el array empieza en 0
                array('id' => $id),
                array('%d'),
                array('%d')
            );
            
            if ($result === false) {
                $success = false;
                $this->log_error("Error al actualizar orden del ID {$id}: " . $this->wpdb->last_error);
            }
        }
        
        return $success;
    }
    
    /**
     * Obtener el siguiente número de orden
     */
    protected function get_next_sort_order() {
        $table_name = $this->get_table_name();
        
        $max_order = $this->wpdb->get_var("SELECT MAX(sort_order) FROM {$table_name}");
        
        return is_numeric($max_order) ? $max_order + 1 : 1;
    }
    
    /**
     * Sanitizar el campo order_by
     */
    protected function sanitize_order_by($order_by) {
        // Lista de campos permitidos por defecto (las clases hijas pueden sobrescribir esto)
        $allowed_fields = array('id', 'sort_order', 'is_active', 'created_at', 'updated_at');
        
        if (in_array($order_by, $allowed_fields)) {
            return $order_by;
        }
        
        return 'sort_order'; // Default fallback
    }
    
    /**
     * Log de errores
     */
    protected function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ADHESION REPOSITORY] ' . $message);
        }
    }
    
    /**
     * Validar datos comunes
     */
    protected function validate_common_data($data) {
        $errors = array();
        
        // Validaciones que las clases hijas pueden usar
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors[] = __('El orden debe ser un número positivo.', 'adhesion');
        }
        
        return $errors;
    }
    
    /**
     * Preparar datos comunes antes de insertar/actualizar
     */
    protected function prepare_common_data($data, $is_update = false) {
        // Añadir sort_order si no existe (solo en inserts)
        if (!$is_update && !isset($data['sort_order'])) {
            $data['sort_order'] = $this->get_next_sort_order();
        }
        
        // Asegurar que is_active tenga un valor por defecto
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }
        
        return $data;
    }
}