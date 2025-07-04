<?php
/**
 * Clase para listado de cálculos de presupuestos
 * 
 * Esta clase extiende WP_List_Table para mostrar:
 * - Listado paginado de cálculos
 * - Filtros por usuario, fecha y estado
 * - Acciones en masa
 * - Exportación de datos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no está disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adhesion_Calculations_List extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'calculation',
            'plural' => 'calculations',
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
        add_action('wp_ajax_adhesion_delete_calculation', array($this, 'ajax_delete_calculation'));
        add_action('wp_ajax_adhesion_export_calculations', array($this, 'ajax_export_calculations'));
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'adhesion'),
            'user' => __('Usuario', 'adhesion'),
            'materials' => __('Materiales', 'adhesion'),
            'total_tons' => __('Toneladas', 'adhesion'),
            'total_price' => __('Precio Total', 'adhesion'),
            'created_at' => __('Fecha', 'adhesion'),
            'actions' => __('Acciones', 'adhesion')
        );
    }
    
    /**
     * Obtener columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'id' => array('id', false),
            'user' => array('user_name', false),
            'total_price' => array('total_price', false),
            'total_tons' => array('total_tons', false),
            'created_at' => array('created_at', true) // Por defecto descendente
        );
    }
    
    /**
     * Obtener acciones en masa
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Eliminar', 'adhesion'),
            'export' => __('Exportar', 'adhesion'),
            'mark_inactive' => __('Marcar como inactivo', 'adhesion')
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
        $per_page = $this->get_items_per_page('calculations_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Obtener filtros
        $filters = $this->get_filters();
        
        // Obtener elementos
        $this->items = $this->db->get_all_calculations($per_page, $offset, $filters);
        
        // Configurar paginación
        $total_items = $this->get_total_calculations($filters);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Obtener filtros de la URL
     */
    private function get_filters() {
        $filters = array();
        
        // Filtro por usuario
        if (!empty($_GET['user_id'])) {
            $filters['user_id'] = intval($_GET['user_id']);
        }
        
        // Filtro por fecha desde
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        // Filtro por fecha hasta
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        // Filtro por rango de precio
        if (!empty($_GET['price_min'])) {
            $filters['price_min'] = floatval($_GET['price_min']);
        }
        
        if (!empty($_GET['price_max'])) {
            $filters['price_max'] = floatval($_GET['price_max']);
        }
        
        // Ordenación
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'created_at');
        $order = sanitize_text_field($_GET['order'] ?? 'desc');
        
        $filters['orderby'] = $orderby;
        $filters['order'] = $order;
        
        return $filters;
    }
    
    /**
     * Obtener total de cálculos (para paginación)
     */
    private function get_total_calculations($filters) {
        global $wpdb;
        
        $where_clauses = array("c.status = 'active'");
        $params = array();
        
        // Aplicar filtros
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
        
        if (!empty($filters['price_min'])) {
            $where_clauses[] = "c.total_price >= %f";
            $params[] = $filters['price_min'];
        }
        
        if (!empty($filters['price_max'])) {
            $where_clauses[] = "c.total_price <= %f";
            $params[] = $filters['price_max'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculations c 
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
            case 'id':
                return $item['id'];
                
            case 'total_tons':
                return $item['total_tons'] ? adhesion_format_tons($item['total_tons']) : '-';
                
            case 'total_price':
                return adhesion_format_price($item['total_price']);
                
            case 'created_at':
                return adhesion_format_date($item['created_at']);
                
            default:
                return $item[$column_name] ?? '-';
        }
    }
    
    /**
     * Columna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="calculation[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Columna de usuario
     */
    public function column_user($item) {
        $user_name = $item['user_name'] ?: __('Usuario eliminado', 'adhesion');
        $user_email = $item['user_email'] ?: '';
        
        $output = '<strong>' . esc_html($user_name) . '</strong>';
        
        if ($user_email) {
            $output .= '<br><small>' . esc_html($user_email) . '</small>';
        }
        
        // Agregar enlace al usuario si existe
        if ($item['user_id']) {
            $user_link = admin_url('user-edit.php?user_id=' . $item['user_id']);
            $output = '<a href="' . esc_url($user_link) . '">' . $output . '</a>';
        }
        
        return $output;
    }
    
    /**
     * Columna de materiales
     */
    public function column_materials($item) {
        $calculation_data = $item['calculation_data'];
        
        if (empty($calculation_data) || !isset($calculation_data['materials'])) {
            return '-';
        }
        
        $materials = $calculation_data['materials'];
        $output = '';
        
        if (count($materials) <= 2) {
            // Mostrar todos si son pocos
            foreach ($materials as $material) {
                $output .= '<div class="material-item">';
                $output .= '<strong>' . esc_html($material['type']) . '</strong>: ';
                $output .= adhesion_format_tons($material['quantity']);
                $output .= '</div>';
            }
        } else {
            // Mostrar resumen si son muchos
            $total_types = count($materials);
            $output .= sprintf(__('%d tipos de materiales', 'adhesion'), $total_types);
            $output .= '<br><small>' . __('Ver detalles', 'adhesion') . '</small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de acciones
     */
    public function column_actions($item) {
        $actions = array();
        
        // Ver detalles
        $view_url = admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $item['id']);
        $actions['view'] = '<a href="' . esc_url($view_url) . '" class="button button-small">' . __('Ver', 'adhesion') . '</a>';
        
        // Crear contrato si no existe
        $has_contract = $this->calculation_has_contract($item['id']);
        if (!$has_contract) {
            $contract_url = admin_url('admin.php?page=adhesion-contracts&action=create&calculation=' . $item['id']);
            $actions['contract'] = '<a href="' . esc_url($contract_url) . '" class="button button-small button-primary">' . __('Crear Contrato', 'adhesion') . '</a>';
        }
        
        // Eliminar
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=adhesion-calculations&action=delete&calculation=' . $item['id']),
            'delete_calculation_' . $item['id']
        );
        $actions['delete'] = '<a href="' . esc_url($delete_url) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . 
                           esc_js(__('¿Estás seguro de que quieres eliminar este cálculo?', 'adhesion')) . '\')">' . 
                           __('Eliminar', 'adhesion') . '</a>';
        
        return '<div class="row-actions-wrapper">' . implode(' ', $actions) . '</div>';
    }
    
    /**
     * Verificar si un cálculo tiene contrato asociado
     */
    private function calculation_has_contract($calculation_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE calculation_id = %d",
            $calculation_id
        ));
        
        return $count > 0;
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
            <label for="filter-user" class="screen-reader-text"><?php _e('Filtrar por usuario', 'adhesion'); ?></label>
            <select name="user_id" id="filter-user">
                <option value=""><?php _e('Todos los usuarios', 'adhesion'); ?></option>
                <?php
                // Obtener usuarios con cálculos
                $users = $this->get_users_with_calculations();
                foreach ($users as $user) {
                    printf(
                        '<option value="%d" %s>%s (%d)</option>',
                        $user['user_id'],
                        selected($_GET['user_id'] ?? '', $user['user_id'], false),
                        esc_html($user['user_name']),
                        $user['calculation_count']
                    );
                }
                ?>
            </select>
            
            <label for="filter-date-from" class="screen-reader-text"><?php _e('Fecha desde', 'adhesion'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" 
                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha desde', 'adhesion'); ?>">
            
            <label for="filter-date-to" class="screen-reader-text"><?php _e('Fecha hasta', 'adhesion'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" 
                   value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                   placeholder="<?php _e('Fecha hasta', 'adhesion'); ?>">
            
            <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php _e('Filtrar', 'adhesion'); ?>">
            
            <?php if (!empty($_GET['user_id']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                    <?php _e('Limpiar filtros', 'adhesion'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" onclick="adhesionExportCalculations()">
                <?php _e('Exportar CSV', 'adhesion'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Obtener usuarios con cálculos
     */
    private function get_users_with_calculations() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT u.ID as user_id, u.display_name as user_name, COUNT(c.id) as calculation_count
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id
             WHERE c.status = 'active'
             GROUP BY u.ID
             ORDER BY u.display_name ASC",
            ARRAY_A
        );
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
        
        $calculation_ids = $_GET['calculation'] ?? array();
        
        if (empty($calculation_ids)) {
            return;
        }
        
        // Asegurar que son arrays
        if (!is_array($calculation_ids)) {
            $calculation_ids = array($calculation_ids);
        }
        
        switch ($action) {
            case 'delete':
                $this->bulk_delete($calculation_ids);
                break;
                
            case 'export':
                $this->bulk_export($calculation_ids);
                break;
                
            case 'mark_inactive':
                $this->bulk_mark_inactive($calculation_ids);
                break;
        }
    }
    
    /**
     * Eliminar en masa
     */
    private function bulk_delete($calculation_ids) {
        global $wpdb;
        
        $deleted_count = 0;
        
        foreach ($calculation_ids as $id) {
            $id = intval($id);
            
            // Verificar que no tenga contratos asociados
            $has_contracts = $this->calculation_has_contract($id);
            
            if ($has_contracts) {
                continue; // No eliminar si tiene contratos
            }
            
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_calculations',
                array('status' => 'deleted'),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            $message = sprintf(__('Se eliminaron %d cálculos correctamente.', 'adhesion'), $deleted_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-calculations'));
        exit;
    }
    
    /**
     * Marcar como inactivo en masa
     */
    private function bulk_mark_inactive($calculation_ids) {
        global $wpdb;
        
        $updated_count = 0;
        
        foreach ($calculation_ids as $id) {
            $id = intval($id);
            
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_calculations',
                array('status' => 'inactive'),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
        
        if ($updated_count > 0) {
            $message = sprintf(__('Se marcaron %d cálculos como inactivos.', 'adhesion'), $updated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-calculations'));
        exit;
    }
    
    /**
     * Exportar en masa
     */
    private function bulk_export($calculation_ids) {
        $calculations = array();
        
        foreach ($calculation_ids as $id) {
            $calculation = $this->db->get_calculation(intval($id));
            if ($calculation) {
                $calculations[] = $calculation;
            }
        }
        
        $this->export_calculations_csv($calculations);
    }
    
    /**
     * Exportar cálculos a CSV
     */
    private function export_calculations_csv($calculations) {
        $filename = 'adhesion_calculations_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeceras
        fputcsv($output, array(
            'ID',
            'Usuario',
            'Email',
            'Materiales',
            'Total Toneladas',
            'Precio Total',
            'Fecha Creación'
        ), ';');
        
        // Datos
        foreach ($calculations as $calc) {
            $materials_summary = '';
            if (!empty($calc['calculation_data']['materials'])) {
                $materials = array();
                foreach ($calc['calculation_data']['materials'] as $material) {
                    $materials[] = $material['type'] . ': ' . $material['quantity'] . 't';
                }
                $materials_summary = implode(', ', $materials);
            }
            
            fputcsv($output, array(
                $calc['id'],
                $calc['user_name'] ?: 'Usuario eliminado',
                $calc['user_email'] ?: '',
                $materials_summary,
                $calc['total_tons'],
                $calc['total_price'],
                $calc['created_at']
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Eliminar cálculo
     */
    public function ajax_delete_calculation() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $calculation_id = intval($_POST['calculation_id']);
            
            // Verificar que no tenga contratos
            if ($this->calculation_has_contract($calculation_id)) {
                throw new Exception(__('No se puede eliminar un cálculo que tiene contratos asociados.', 'adhesion'));
            }
            
            global $wpdb;
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_calculations',
                array('status' => 'deleted'),
                array('id' => $calculation_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception(__('Error al eliminar el cálculo.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Cálculo eliminado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Exportar cálculos
     */
    public function ajax_export_calculations() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Obtener todos los cálculos con filtros actuales
            $filters = $this->get_filters();
            $calculations = $this->db->get_all_calculations(1000, 0, $filters); // Máximo 1000
            
            $this->export_calculations_csv($calculations);
            
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }
}

/**
 * Función para mostrar la página de listado
 */
function adhesion_display_calculations_page() {
    $list_table = new Adhesion_Calculations_List();
    $list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Cálculos de Presupuestos', 'adhesion'); ?></h1>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <?php
            $list_table->search_box(__('Buscar cálculos', 'adhesion'), 'calculation');
            $list_table->display();
            ?>
        </form>
    </div>
    
    <script>
    function adhesionExportCalculations() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'adhesion_export_calculations';
        form.appendChild(actionInput);
        
        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = 'nonce';
        nonceInput.value = '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>';
        form.appendChild(nonceInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    </script>
    
    <style>
    .material-item {
        margin-bottom: 2px;
        font-size: 12px;
    }
    
    .row-actions-wrapper {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .row-actions-wrapper .button {
        margin: 0;
    }
    
    @media (max-width: 768px) {
        .column-materials,
        .column-total_tons {
            display: none;
        }
        
        .row-actions-wrapper {
            flex-direction: column;
        }
    }
    </style>
    <?php
}