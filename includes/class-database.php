<?php
/**
 * Clase para gestión de base de datos
 * 
 * Esta clase maneja todas las operaciones CRUD del plugin:
 * - Cálculos de presupuestos
 * - Contratos de adhesión
 * - Documentos editables
 * - Configuraciones
 * - Precios de calculadora
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Database {
    
    /**
     * Instancia de WordPress Database
     */
    private $wpdb;
    
    /**
     * Nombres de las tablas
     */
    private $table_calculations;
    private $table_contracts;
    private $table_documents;
    private $table_settings;
    private $table_calculator_prices;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Definir nombres de tablas
        $this->table_calculations = $wpdb->prefix . 'adhesion_calculations';
        $this->table_contracts = $wpdb->prefix . 'adhesion_contracts';
        $this->table_documents = $wpdb->prefix . 'adhesion_documents';
        $this->table_settings = $wpdb->prefix . 'adhesion_settings';
        $this->table_calculator_prices = $wpdb->prefix . 'adhesion_calculator_prices';
    }
    
    // ==========================================
    // MÉTODOS PARA CÁLCULOS DE PRESUPUESTOS
    // ==========================================
    
    /**
     * Crear un nuevo cálculo
     */
    public function create_calculation($user_id, $calculation_data, $total_price, $price_per_ton = null, $total_tons = null) {
        $data = array(
            'user_id' => $user_id,
            'calculation_data' => is_array($calculation_data) ? json_encode($calculation_data) : $calculation_data,
            'total_price' => $total_price,
            'price_per_ton' => $price_per_ton,
            'total_tons' => $total_tons,
            'status' => 'active'
        );
        
        $result = $this->wpdb->insert(
            $this->table_calculations,
            $data,
            array('%d', '%s', '%f', '%f', '%f', '%s')
        );
        
        if ($result === false) {
            adhesion_log('Error al crear cálculo: ' . $this->wpdb->last_error, 'error');
            return false;
        }
        
        $calculation_id = $this->wpdb->insert_id;
        adhesion_log("Cálculo creado con ID: $calculation_id", 'info');
        
        return $calculation_id;
    }
    
    /**
     * Obtener cálculo por ID
     */
    public function get_calculation($calculation_id) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_calculations} WHERE id = %d",
                $calculation_id
            ),
            ARRAY_A
        );
        
        if ($result && !empty($result['calculation_data'])) {
            $result['calculation_data'] = json_decode($result['calculation_data'], true);
        }
        
        return $result;
    }
    
    /**
     * Obtener cálculos de un usuario
     */
    public function get_user_calculations($user_id, $limit = 10, $offset = 0) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_calculations} 
                 WHERE user_id = %d AND status = 'active'
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $user_id, $limit, $offset
            ),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['calculation_data'])) {
                $result['calculation_data'] = json_decode($result['calculation_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Obtener todos los cálculos (para admin)
     */
    public function get_all_calculations($limit = 50, $offset = 0, $filters = array()) {
        $where_clauses = array("status = 'active'");
        $params = array();
        
        // Aplicar filtros
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT c.*, u.display_name as user_name, u.user_email 
                FROM {$this->table_calculations} c
                LEFT JOIN {$this->wpdb->users} u ON c.user_id = u.ID
                WHERE $where_sql
                ORDER BY c.created_at DESC 
                LIMIT %d OFFSET %d";
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['calculation_data'])) {
                $result['calculation_data'] = json_decode($result['calculation_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Actualizar cálculo
     */
    public function update_calculation($calculation_id, $data) {
        if (isset($data['calculation_data']) && is_array($data['calculation_data'])) {
            $data['calculation_data'] = json_encode($data['calculation_data']);
        }
        
        $result = $this->wpdb->update(
            $this->table_calculations,
            $data,
            array('id' => $calculation_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    // ==========================================
    // MÉTODOS PARA CONTRATOS
    // ==========================================
    
    /**
     * Crear un nuevo contrato
     */
    public function create_contract($user_id, $calculation_id = null, $client_data = array()) {
        // Generar número de contrato único
        $contract_number = $this->generate_contract_number();
        
        $data = array(
            'user_id' => $user_id,
            'calculation_id' => $calculation_id,
            'contract_number' => $contract_number,
            'status' => 'pending',
            'client_data' => json_encode($client_data),
            'payment_status' => 'pending'
        );
        
        $result = $this->wpdb->insert(
            $this->table_contracts,
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            adhesion_log('Error al crear contrato: ' . $this->wpdb->last_error, 'error');
            return false;
        }
        
        $contract_id = $this->wpdb->insert_id;
        adhesion_log("Contrato creado con ID: $contract_id, Número: $contract_number", 'info');
        
        return $contract_id;
    }
    
    /**
     * Generar número de contrato único
     */
    private function generate_contract_number() {
        $prefix = 'ADH';
        $year = date('Y');
        $month = date('m');
        
        // Buscar el último número del mes
        $last_number = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT MAX(CAST(SUBSTRING(contract_number, -4) AS UNSIGNED)) 
                 FROM {$this->table_contracts} 
                 WHERE contract_number LIKE %s",
                $prefix . $year . $month . '%'
            )
        );
        
        $next_number = ($last_number ? $last_number : 0) + 1;
        
        return sprintf('%s%s%s%04d', $prefix, $year, $month, $next_number);
    }
    
    /**
     * Obtener contrato por ID
     */
    public function get_contract($contract_id) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT c.*, calc.total_price, calc.calculation_data, u.display_name as user_name, u.user_email
                 FROM {$this->table_contracts} c
                 LEFT JOIN {$this->table_calculations} calc ON c.calculation_id = calc.id
                 LEFT JOIN {$this->wpdb->users} u ON c.user_id = u.ID
                 WHERE c.id = %d",
                $contract_id
            ),
            ARRAY_A
        );
        
        if ($result) {
            // Decodificar datos JSON
            if (!empty($result['client_data'])) {
                $result['client_data'] = json_decode($result['client_data'], true);
            }
            if (!empty($result['calculation_data'])) {
                $result['calculation_data'] = json_decode($result['calculation_data'], true);
            }
        }
        
        return $result;
    }
    
    /**
     * Obtener contratos de un usuario
     */
    public function get_user_contracts($user_id, $limit = 10, $offset = 0) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT c.*, calc.total_price 
                 FROM {$this->table_contracts} c
                 LEFT JOIN {$this->table_calculations} calc ON c.calculation_id = calc.id
                 WHERE c.user_id = %d
                 ORDER BY c.created_at DESC 
                 LIMIT %d OFFSET %d",
                $user_id, $limit, $offset
            ),
            ARRAY_A
        );
        
        // Decodificar datos JSON
        foreach ($results as &$result) {
            if (!empty($result['client_data'])) {
                $result['client_data'] = json_decode($result['client_data'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Actualizar estado del contrato
     */
    public function update_contract_status($contract_id, $status, $additional_data = array()) {
        $data = array_merge(array('status' => $status), $additional_data);
        
        // Agregar timestamp específico según el estado
        if ($status === 'signed') {
            $data['signed_at'] = current_time('mysql');
        } elseif ($status === 'completed' && !empty($additional_data['payment_status']) && $additional_data['payment_status'] === 'completed') {
            $data['payment_completed_at'] = current_time('mysql');
        }
        
        $result = $this->wpdb->update(
            $this->table_contracts,
            $data,
            array('id' => $contract_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            adhesion_log("Contrato $contract_id actualizado a estado: $status", 'info');
        }
        
        return $result !== false;
    }
    
    // ==========================================
    // MÉTODOS PARA DOCUMENTOS
    // ==========================================
    
    /**
     * Obtener documentos activos por tipo
     */
    public function get_active_documents($document_type = null) {
        $where = "is_active = 1";
        $params = array();
        
        if ($document_type) {
            $where .= " AND document_type = %s";
            $params[] = $document_type;
        }
        
        $sql = "SELECT * FROM {$this->table_documents} WHERE $where ORDER BY created_at DESC";
        
        if (!empty($params)) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $params),
                ARRAY_A
            );
        } else {
            return $this->wpdb->get_results($sql, ARRAY_A);
        }
    }
    
    /**
     * Crear o actualizar documento
     */
    public function save_document($data, $document_id = null) {
        if ($document_id) {
            // Actualizar documento existente
            $result = $this->wpdb->update(
                $this->table_documents,
                $data,
                array('id' => $document_id),
                null,
                array('%d')
            );
        } else {
            // Crear nuevo documento
            $result = $this->wpdb->insert(
                $this->table_documents,
                $data
            );
            $document_id = $this->wpdb->insert_id;
        }
        
        return $result !== false ? $document_id : false;
    }
    
    // ==========================================
    // MÉTODOS PARA PRECIOS DE CALCULADORA
    // ==========================================
    
    /**
     * Obtener precios activos de calculadora
     */
    public function get_calculator_prices() {
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_calculator_prices} 
             WHERE is_active = 1 
             ORDER BY material_type ASC",
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Actualizar precio de material
     */
    public function update_material_price($material_type, $price_per_ton, $minimum_quantity = 0) {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_calculator_prices} WHERE material_type = %s",
                $material_type
            )
        );
        
        $data = array(
            'material_type' => $material_type,
            'price_per_ton' => $price_per_ton,
            'minimum_quantity' => $minimum_quantity,
            'is_active' => 1
        );
        
        if ($existing) {
            $result = $this->wpdb->update(
                $this->table_calculator_prices,
                $data,
                array('id' => $existing),
                array('%s', '%f', '%f', '%d'),
                array('%d')
            );
        } else {
            $result = $this->wpdb->insert(
                $this->table_calculator_prices,
                $data,
                array('%s', '%f', '%f', '%d')
            );
        }
        
        return $result !== false;
    }
    
    // ==========================================
    // MÉTODOS ESTADÍSTICOS Y REPORTES
    // ==========================================
    
    /**
     * Obtener estadísticas básicas
     */
    public function get_basic_stats() {
        $stats = array();
        
        // Total de cálculos
        $stats['total_calculations'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_calculations} WHERE status = 'active'"
        );
        
        // Total de contratos
        $stats['total_contracts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_contracts}"
        );
        
        // Contratos firmados
        $stats['signed_contracts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_contracts} WHERE status = 'signed'"
        );
        
        // Contratos pagados
        $stats['paid_contracts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_contracts} WHERE payment_status = 'completed'"
        );
        
        // Ingresos totales
        $stats['total_revenue'] = $this->wpdb->get_var(
            "SELECT SUM(payment_amount) FROM {$this->table_contracts} WHERE payment_status = 'completed'"
        ) ?: 0;
        
        return $stats;
    }
    
    /**
     * Obtener estadísticas por período
     */
    public function get_period_stats($date_from, $date_to) {
        $stats = array();
        
        // Cálculos en el período
        $stats['period_calculations'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_calculations} 
                 WHERE created_at BETWEEN %s AND %s AND status = 'active'",
                $date_from, $date_to
            )
        );
        
        // Contratos en el período
        $stats['period_contracts'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_contracts} 
                 WHERE created_at BETWEEN %s AND %s",
                $date_from, $date_to
            )
        );
        
        // Ingresos en el período
        $stats['period_revenue'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(payment_amount) FROM {$this->table_contracts} 
                 WHERE payment_completed_at BETWEEN %s AND %s AND payment_status = 'completed'",
                $date_from, $date_to
            )
        ) ?: 0;
        
        return $stats;
    }
    
    // ==========================================
    // MÉTODOS DE UTILIDAD
    // ==========================================
    
    /**
     * Verificar si las tablas existen
     */
    public function tables_exist() {
        $tables = array(
            $this->table_calculations,
            $this->table_contracts,
            $this->table_documents,
            $this->table_settings,
            $this->table_calculator_prices
        );
        
        foreach ($tables as $table) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Limpiar datos antiguos
     */
    public function cleanup_old_data($days = 365) {
        $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Eliminar cálculos antiguos sin contratos asociados
        $deleted_calculations = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE c FROM {$this->table_calculations} c
                 LEFT JOIN {$this->table_contracts} ct ON c.id = ct.calculation_id
                 WHERE c.created_at < %s AND ct.id IS NULL",
                $date_limit
            )
        );
        
        if ($deleted_calculations > 0) {
            adhesion_log("Limpieza automática: $deleted_calculations cálculos antiguos eliminados", 'info');
        }
        
        return $deleted_calculations;
    }
}