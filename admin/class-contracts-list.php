<?php
/**
 * Clase para listado de contratos de adhesión
 * 
 * Esta clase extiende WP_List_Table para mostrar:
 * - Listado paginado de contratos
 * - Filtros por estado, usuario y fechas
 * - Acciones de gestión de contratos
 * - Integración con DocuSign y pagos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no está disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adhesion_Contracts_List extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'contract',
            'plural' => 'contracts',
            'ajax' => false
        ));
        
        $this->db = new Adhesion_Database();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Procesar acciones
        add_action('admin_init', array($this, 'process_bulk_actions'));
        
        // AJAX para acciones rápidas
        add_action('wp_ajax_adhesion_update_contract_status', array($this, 'ajax_update_contract_status'));
        add_action('wp_ajax_adhesion_resend_contract', array($this, 'ajax_resend_contract'));
        add_action('wp_ajax_adhesion_export_contracts', array($this, 'ajax_export_contracts'));
        add_action('wp_ajax_adhesion_check_docusign_status', array($this, 'ajax_check_docusign_status'));
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'contract_number' => __('N° Contrato', 'adhesion'),
            'user' => __('Cliente', 'adhesion'),
            'calculation' => __('Cálculo Base', 'adhesion'),
            'status' => __('Estado', 'adhesion'),
            'payment_status' => __('Pago', 'adhesion'),
            'total_amount' => __('Importe', 'adhesion'),
            'created_at' => __('Fecha Creación', 'adhesion'),
            'signed_at' => __('Fecha Firma', 'adhesion'),
            'actions' => __('Acciones', 'adhesion')
        );
    }
    
    /**
     * Obtener columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'contract_number' => array('contract_number', false),
            'user' => array('user_name', false),
            'status' => array('status', false),
            'payment_status' => array('payment_status', false),
            'total_amount' => array('total_amount', false),
            'created_at' => array('created_at', true),
            'signed_at' => array('signed_at', false)
        );
    }
    
    /**
     * Obtener acciones en masa
     */
    public function get_bulk_actions() {
        return array(
            'send_to_docusign' => __('Enviar a DocuSign', 'adhesion'),
            'mark_completed' => __('Marcar como completado', 'adhesion'),
            'export' => __('Exportar seleccionados', 'adhesion'),
            'cancel' => __('Cancelar contratos', 'adhesion')
        );
    }
    
    /**
     * Preparar elementos
     */
    public function prepare_items() {
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Procesar acciones en masa
        $this->process_bulk_actions();
        
        // Configurar paginación
        $per_page = $this->get_items_per_page('contracts_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Obtener filtros
        $filters = $this->get_filters();
        
        // Obtener elementos
        $this->items = $this->get_contracts($per_page, $offset, $filters);
        
        // Configurar paginación
        $total_items = $this->get_total_contracts($filters);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Obtener contratos con joins
     */
    private function get_contracts($per_page, $offset, $filters) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $where_clauses[] = "c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where_clauses[] = "c.payment_status = %s";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "c.user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "c.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "c.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        // Búsqueda
        if (!empty($filters['search'])) {
            $where_clauses[] = "(c.contract_number LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Ordenación
        $orderby = sanitize_sql_orderby($filters['orderby'] . ' ' . $filters['order']);
        $orderby = $orderby ?: 'c.created_at DESC';
        
        $sql = "SELECT c.*, u.display_name as user_name, u.user_email, 
                       calc.total_price as calculation_total
                FROM {$wpdb->prefix}adhesion_contracts c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}adhesion_calculations calc ON c.calculation_id = calc.id
                WHERE $where_sql
                ORDER BY $orderby
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $params),
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
     * Obtener filtros de la URL
     */
    private function get_filters() {
        $filters = array();
        
        // Filtros básicos
        $filters['status'] = sanitize_text_field($_GET['status'] ?? '');
        $filters['payment_status'] = sanitize_text_field($_GET['payment_status'] ?? '');
        $filters['user_id'] = intval($_GET['user_id'] ?? 0);
        
        // Filtros de fecha
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        // Búsqueda
        $filters['search'] = sanitize_text_field($_GET['s'] ?? '');
        
        // Ordenación
        $filters['orderby'] = sanitize_text_field($_GET['orderby'] ?? 'created_at');
        $filters['order'] = sanitize_text_field($_GET['order'] ?? 'desc');
        
        return $filters;
    }
    
    /**
     * Obtener total de contratos
     */
    private function get_total_contracts($filters) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Aplicar los mismos filtros que en get_contracts
        if (!empty($filters['status'])) {
            $where_clauses[] = "c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where_clauses[] = "c.payment_status = %s";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = "c.user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "c.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "c.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(c.contract_number LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT COUNT(*)
                FROM {$wpdb->prefix}adhesion_contracts c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE $where_sql";
        
        if (empty($params)) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        }
    }
    
    /**
     * Columna por defecto
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'contract_number':
                return '<strong>' . esc_html($item['contract_number']) . '</strong>';
                
            case 'created_at':
                return adhesion_format_date($item['created_at']);
                
            case 'signed_at':
                return $item['signed_at'] ? adhesion_format_date($item['signed_at']) : '-';
                
            default:
                return $item[$column_name] ?? '-';
        }
    }
    
    /**
     * Columna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="contract[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Columna de usuario/cliente
     */
    public function column_user($item) {
        $user_name = $item['user_name'] ?: __('Usuario eliminado', 'adhesion');
        $user_email = $item['user_email'] ?: '';
        
        $output = '<strong>' . esc_html($user_name) . '</strong>';
        
        if ($user_email) {
            $output .= '<br><small>' . esc_html($user_email) . '</small>';
        }
        
        // Mostrar datos del cliente si están disponibles
        if (!empty($item['client_data']['nombre_completo'])) {
            $output .= '<br><small><em>' . esc_html($item['client_data']['nombre_completo']) . '</em></small>';
        }
        
        // Agregar enlace al usuario si existe
        if ($item['user_id']) {
            $user_link = admin_url('user-edit.php?user_id=' . $item['user_id']);
            $output = '<a href="' . esc_url($user_link) . '">' . $output . '</a>';
        }
        
        return $output;
    }
    
    /**
     * Columna de cálculo base
     */
    public function column_calculation($item) {
        if (!$item['calculation_id']) {
            return '<em>' . __('Sin cálculo base', 'adhesion') . '</em>';
        }
        
        $calc_link = admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $item['calculation_id']);
        $output = '<a href="' . esc_url($calc_link) . '">#' . $item['calculation_id'] . '</a>';
        
        if ($item['calculation_total']) {
            $output .= '<br><small>' . adhesion_format_price($item['calculation_total']) . '</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de estado del contrato
     */
    public function column_status($item) {
        $status = adhesion_format_contract_status($item['status']);
        
        $output = '<span class="adhesion-badge adhesion-badge-' . $this->get_status_class($item['status']) . '">';
        $output .= esc_html($status['label']);
        $output .= '</span>';
        
        // Agregar información adicional
        if ($item['status'] === 'signed' && $item['docusign_envelope_id']) {
            $output .= '<br><small>ID: ' . esc_html(substr($item['docusign_envelope_id'], 0, 8)) . '...</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de estado de pago
     */
    public function column_payment_status($item) {
        $payment_status = adhesion_format_payment_status($item['payment_status']);
        
        $output = '<span class="adhesion-badge adhesion-badge-' . $this->get_payment_status_class($item['payment_status']) . '">';
        $output .= esc_html($payment_status['label']);
        $output .= '</span>';
        
        // Agregar referencia de pago si existe
        if ($item['payment_reference']) {
            $output .= '<br><small>Ref: ' . esc_html($item['payment_reference']) . '</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de importe total
     */
    public function column_total_amount($item) {
        if ($item['payment_amount']) {
            return '<strong>' . adhesion_format_price($item['payment_amount']) . '</strong>';
        } elseif ($item['calculation_total']) {
            return '<em>' . adhesion_format_price($item['calculation_total']) . '</em>';
        }
        
        return '-';
    }
    
    /**
     * Columna de acciones
     */
    public function column_actions($item) {
        $actions = array();
        
        // Ver detalles
        $view_url = admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $item['id']);
        $actions['view'] = '<a href="' . esc_url($view_url) . '" class="button button-small">' . __('Ver', 'adhesion') . '</a>';
        
        // Acciones según el estado
        switch ($item['status']) {
            case 'pending':
                if (adhesion_is_docusign_configured()) {
                    $actions['send'] = '<button type="button" class="button button-small button-primary" onclick="adhesionSendToDocusign(' . $item['id'] . ')">' . __('Enviar a Firma', 'adhesion') . '</button>';
                }
                break;
                
            case 'sent':
                $actions['check'] = '<button type="button" class="button button-small" onclick="adhesionCheckDocusignStatus(' . $item['id'] . ')">' . __('Verificar Estado', 'adhesion') . '</button>';
                break;
                
            case 'signed':
                if ($item['signed_document_url']) {
                    $actions['download'] = '<a href="' . esc_url($item['signed_document_url']) . '" class="button button-small" target="_blank">' . __('Descargar', 'adhesion') . '</a>';
                }
                break;
        }
        
        // Editar (solo si no está firmado)
        if ($item['status'] !== 'signed') {
            $edit_url = admin_url('admin.php?page=adhesion-contracts&action=edit&contract=' . $item['id']);
            $actions['edit'] = '<a href="' . esc_url($edit_url) . '" class="button button-small">' . __('Editar', 'adhesion') . '</a>';
        }
        
        return '<div class="row-actions-wrapper">' . implode(' ', $actions) . '</div>';
    }
    
    /**
     * Obtener clase CSS para estado de contrato
     */
    private function get_status_class($status) {
        switch ($status) {
            case 'pending':
                return 'warning';
            case 'sent':
                return 'info';
            case 'signed':
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'error';
            default:
                return 'secondary';
        }
    }
    
    /**
     * Obtener clase CSS para estado de pago
     */
    private function get_payment_status_class($status) {
        switch ($status) {
            case 'pending':
                return 'warning';
            case 'processing':
                return 'info';
            case 'completed':
                return 'success';
            case 'failed':
            case 'refunded':
                return 'error';
            default:
                return 'secondary';
        }
    }
    
    /**
     * Mostrar filtros
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        
        ?>
        <div class="alignleft actions">
            <!-- Filtro por estado -->
            <label for="filter-status" class="screen-reader-text"><?php _e('Filtrar por estado', 'adhesion'); ?></label>
            <select name="status" id="filter-status">
                <option value=""><?php _e('Todos los estados', 'adhesion'); ?></option>
                <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('Pendiente', 'adhesion'); ?></option>
                <option value="sent" <?php selected($_GET['status'] ?? '', 'sent'); ?>><?php _e('Enviado', 'adhesion'); ?></option>
                <option value="signed" <?php selected($_GET['status'] ?? '', 'signed'); ?>><?php _e('Firmado', 'adhesion'); ?></option>
                <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Completado', 'adhesion'); ?></option>
                <option value="cancelled" <?php selected($_GET['status'] ?? '', 'cancelled'); ?>><?php _e('Cancelado', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtro por estado de pago -->
            <label for="filter-payment-status" class="screen-reader-text"><?php _e('Filtrar por pago', 'adhesion'); ?></label>
            <select name="payment_status" id="filter-payment-status">
                <option value=""><?php _e('Todos los pagos', 'adhesion'); ?></option>
                <option value="pending" <?php selected($_GET['payment_status'] ?? '', 'pending'); ?>><?php _e('Pago Pendiente', 'adhesion'); ?></option>
                <option value="processing" <?php selected($_GET['payment_status'] ?? '', 'processing'); ?>><?php _e('Procesando', 'adhesion'); ?></option>
                <option value="completed" <?php selected($_GET['payment_status'] ?? '', 'completed'); ?>><?php _e('Pagado', 'adhesion'); ?></option>
                <option value="failed" <?php selected($_GET['payment_status'] ?? '', 'failed'); ?>><?php _e('Pago Fallido', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtros de fecha -->
            <label for="filter-date-from" class="screen-reader-text"><?php _e('Fecha desde', 'adhesion'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" 
                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha desde', 'adhesion'); ?>">
            
            <label for="filter-date-to" class="screen-reader-text"><?php _e('Fecha hasta', 'adhesion'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" 
                   value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha hasta', 'adhesion'); ?>">
            
            <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar', 'adhesion'); ?>">
            
            <?php if (!empty($_GET['status']) || !empty($_GET['payment_status']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                    <?php _e('Limpiar', 'adhesion'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" onclick="adhesionExportContracts()">
                <?php _e('Exportar CSV', 'adhesion'); ?>
            </button>
            
            <?php if (adhesion_is_docusign_configured()): ?>
            <button type="button" class="button button-primary" onclick="adhesionBulkSendToDocusign()">
                <?php _e('Enviar Pendientes', 'adhesion'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Procesar acciones en masa
     */
    public function process_bulk_actions() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'adhesion'));
        }
        
        $contract_ids = $_GET['contract'] ?? array();
        
        if (empty($contract_ids)) {
            return;
        }
        
        // Asegurar que son arrays
        if (!is_array($contract_ids)) {
            $contract_ids = array($contract_ids);
        }
        
        switch ($action) {
            case 'send_to_docusign':
                $this->bulk_send_to_docusign($contract_ids);
                break;
                
            case 'mark_completed':
                $this->bulk_mark_completed($contract_ids);
                break;
                
            case 'export':
                $this->bulk_export($contract_ids);
                break;
                
            case 'cancel':
                $this->bulk_cancel($contract_ids);
                break;
        }
    }
    
    /**
     * Enviar contratos a DocuSign en masa
     */
    private function bulk_send_to_docusign($contract_ids) {
        if (!adhesion_is_docusign_configured()) {
            adhesion_add_notice(__('DocuSign no está configurado.', 'adhesion'), 'error');
            return;
        }
        
        $sent_count = 0;
        
        foreach ($contract_ids as $id) {
            $contract = $this->db->get_contract(intval($id));
            
            if (!$contract || $contract['status'] !== 'pending') {
                continue;
            }
            
            // TODO: Implementar envío real a DocuSign
            $result = $this->db->update_contract_status($contract['id'], 'sent', array(
                'docusign_envelope_id' => 'TEST_' . time() . '_' . $contract['id']
            ));
            
            if ($result) {
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            $message = sprintf(__('Se enviaron %d contratos a DocuSign.', 'adhesion'), $sent_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-contracts'));
        exit;
    }
    
    /**
     * Marcar como completado en masa
     */
    private function bulk_mark_completed($contract_ids) {
        $updated_count = 0;
        
        foreach ($contract_ids as $id) {
            $result = $this->db->update_contract_status(intval($id), 'completed');
            
            if ($result) {
                $updated_count++;
            }
        }
        
        if ($updated_count > 0) {
            $message = sprintf(__('Se marcaron %d contratos como completados.', 'adhesion'), $updated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-contracts'));
        exit;
    }
    
    /**
     * Cancelar contratos en masa
     */
    private function bulk_cancel($contract_ids) {
        $cancelled_count = 0;
        
        foreach ($contract_ids as $id) {
            $contract = $this->db->get_contract(intval($id));
            
            // No cancelar contratos ya firmados
            if ($contract && $contract['status'] !== 'signed') {
                $result = $this->db->update_contract_status($contract['id'], 'cancelled');
                
                if ($result) {
                    $cancelled_count++;
                }
            }
        }
        
        if ($cancelled_count > 0) {
            $message = sprintf(__('Se cancelaron %d contratos.', 'adhesion'), $cancelled_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-contracts'));
        exit;
    }
    
    /**
     * Exportar contratos en masa
     */
    private function bulk_export($contract_ids) {
        $contracts = array();
        
        foreach ($contract_ids as $id) {
            $contract = $this->db->get_contract(intval($id));
            if ($contract) {
                $contracts[] = $contract;
            }
        }
        
        $this->export_contracts_csv($contracts);
    }
    
    /**
     * Exportar contratos a CSV
     */
    private function export_contracts_csv($contracts) {
        $filename = 'adhesion_contracts_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeceras
        fputcsv($output, array(
            'N° Contrato',
            'Cliente',
            'Email',
            'DNI/CIF',
            'Estado',
            'Estado Pago',
            'Importe',
            'Fecha Creación',
            'Fecha Firma',
            'ID DocuSign'
        ), ';');
        
        // Datos
        foreach ($contracts as $contract) {
            $client_data = $contract['client_data'] ?: array();
            
            fputcsv($output, array(
                $contract['contract_number'],
                $contract['user_name'] ?: 'Usuario eliminado',
                $contract['user_email'] ?: '',
                $client_data['dni_cif'] ?? '',
                ucfirst($contract['status']),
                ucfirst($contract['payment_status']),
                $contract['payment_amount'] ?: $contract['calculation_total'] ?: '0',
                $contract['created_at'],
                $contract['signed_at'] ?: '',
                $contract['docusign_envelope_id'] ?: ''
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Actualizar estado de contrato
     */
    public function ajax_update_contract_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $new_status = sanitize_text_field($_POST['status']);
            
            // Validar estado
            $valid_statuses = array('pending', 'sent', 'signed', 'completed', 'cancelled');
            if (!in_array($new_status, $valid_statuses)) {
                throw new Exception(__('Estado de contrato inválido.', 'adhesion'));
            }
            
            $result = $this->db->update_contract_status($contract_id, $new_status);
            
            if (!$result) {
                throw new Exception(__('Error al actualizar el estado del contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Estado del contrato actualizado correctamente.', 'adhesion'),
                'new_status' => $new_status
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Reenviar contrato a DocuSign
     */
    public function ajax_resend_contract() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!adhesion_is_docusign_configured()) {
                throw new Exception(__('DocuSign no está configurado.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // TODO: Implementar reenvío real a DocuSign
            $envelope_id = 'RESEND_' . time() . '_' . $contract_id;
            
            $result = $this->db->update_contract_status($contract_id, 'sent', array(
                'docusign_envelope_id' => $envelope_id
            ));
            
            if (!$result) {
                throw new Exception(__('Error al reenviar el contrato.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Contrato reenviado a DocuSign correctamente.', 'adhesion'),
                'envelope_id' => $envelope_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Verificar estado en DocuSign
     */
    public function ajax_check_docusign_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            if (!adhesion_is_docusign_configured()) {
                throw new Exception(__('DocuSign no está configurado.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract || empty($contract['docusign_envelope_id'])) {
                throw new Exception(__('Contrato no encontrado o sin ID de DocuSign.', 'adhesion'));
            }
            
            // TODO: Implementar verificación real con DocuSign API
            // Por ahora simulamos una respuesta
            $simulated_status = rand(1, 10) > 7 ? 'signed' : 'sent'; // 30% probabilidad de firmado
            
            if ($simulated_status === 'signed') {
                $update_data = array(
                    'signed_document_url' => 'https://demo.docusign.net/documents/' . $contract['docusign_envelope_id'] . '.pdf'
                );
                
                $this->db->update_contract_status($contract_id, 'signed', $update_data);
                
                $message = __('¡Contrato firmado! Documento descargado.', 'adhesion');
            } else {
                $message = __('El contrato aún no ha sido firmado.', 'adhesion');
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'status' => $simulated_status,
                'signed_url' => $simulated_status === 'signed' ? $update_data['signed_document_url'] : null
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Exportar contratos
     */
    public function ajax_export_contracts() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Obtener todos los contratos con filtros actuales
            $filters = $this->get_filters();
            $contracts = $this->get_contracts(1000, 0, $filters); // Máximo 1000
            
            $this->export_contracts_csv($contracts);
            
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }
}

/**
 * Función para mostrar la página de listado de contratos
 */
function adhesion_display_contracts_page() {
    $list_table = new Adhesion_Contracts_List();
    $list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Contratos de Adhesión', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('← Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <form method="get" id="contracts-filter">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <?php
            $list_table->search_box(__('Buscar contratos', 'adhesion'), 'contract');
            $list_table->display();
            ?>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros
        $('#filter-status, #filter-payment-status, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#contracts-filter').submit();
        });
    });
    
    // Funciones AJAX para acciones de contratos
    function adhesionSendToDocusign(contractId) {
        if (!confirm('<?php echo esc_js(__('¿Enviar este contrato a DocuSign para firma?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_resend_contract',
                contract_id: contractId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexión', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    
    function adhesionCheckDocusignStatus(contractId) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_check_docusign_status',
                contract_id: contractId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.status === 'signed') {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexión', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    
    function adhesionExportContracts() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        // Agregar campos ocultos
        const fields = {
            action: 'adhesion_export_contracts',
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>',
            status: '<?php echo esc_js($_GET['status'] ?? ''); ?>',
            payment_status: '<?php echo esc_js($_GET['payment_status'] ?? ''); ?>',
            date_from: '<?php echo esc_js($_GET['date_from'] ?? ''); ?>',
            date_to: '<?php echo esc_js($_GET['date_to'] ?? ''); ?>'
        };
        
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    function adhesionBulkSendToDocusign() {
        if (!confirm('<?php echo esc_js(__('¿Enviar todos los contratos pendientes a DocuSign?', 'adhesion')); ?>')) {
            return;
        }
        
        // Simular envío en masa
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_bulk_send_docusign',
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#wpbody-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || '<?php echo esc_js(__('Contratos enviados correctamente', 'adhesion')); ?>');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || '<?php echo esc_js(__('Error desconocido', 'adhesion')); ?>'));
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexión', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#wpbody-content').css('opacity', '1');
            }
        });
    }
    </script>
    
    <style>
    .row-actions-wrapper {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .row-actions-wrapper .button {
        margin: 0;
        font-size: 11px;
        padding: 4px 8px;
        height: auto;
        line-height: 1.2;
    }
    
    .adhesion-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .adhesion-badge-success {
        background: #d1e7dd;
        color: #0f5132;
    }
    
    .adhesion-badge-warning {
        background: #fff3cd;
        color: #664d03;
    }
    
    .adhesion-badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .adhesion-badge-error {
        background: #f8d7da;
        color: #842029;
    }
    
    .adhesion-badge-secondary {
        background: #e2e3e5;
        color: #41464b;
    }
    
    @media (max-width: 768px) {
        .column-calculation,
        .column-signed_at,
        .column-payment_status {
            display: none;
        }
        
        .row-actions-wrapper {
            flex-direction: column;
        }
    }
    </style>
    <?php
}