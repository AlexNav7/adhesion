<?php
/**
 * Clase para listado de usuarios adheridos
 * 
 * Esta clase extiende WP_List_Table para mostrar:
 * - Listado paginado de usuarios con rol adhesion_client
 * - Estadísticas de actividad por usuario
 * - Filtros por actividad y fechas
 * - Acciones de gestión de usuarios
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no está disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adhesion_Users_List extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (!isset($GLOBALS['current_screen'])) {
            set_current_screen('adhesion_users');
        }
        parent::__construct(array(
            'singular' => 'user',
            'plural' => 'users',
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
        add_action('wp_ajax_adhesion_send_user_email', array($this, 'ajax_send_user_email'));
        add_action('wp_ajax_adhesion_export_users', array($this, 'ajax_export_users'));
        add_action('wp_ajax_adhesion_update_user_status', array($this, 'ajax_update_user_status'));
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'user_info' => __('Usuario', 'adhesion'),
            'contact_info' => __('Contacto', 'adhesion'),
            'activity_stats' => __('Actividad', 'adhesion'),
            'financial_stats' => __('Financiero', 'adhesion'),
            'last_activity' => __('Última Actividad', 'adhesion'),
            'user_registered' => __('Registrado', 'adhesion'),
            'status' => __('Estado', 'adhesion'),
            'actions' => __('Acciones', 'adhesion')
        );
    }
    
    /**
     * Obtener columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'user_info' => array('display_name', false),
            'contact_info' => array('user_email', false),
            'last_activity' => array('last_activity', true),
            'user_registered' => array('user_registered', true),
            'status' => array('user_status', false)
        );
    }
    
    /**
     * Obtener acciones en masa
     */
    public function get_bulk_actions() {
        return array(
            'send_email' => __('Enviar Email', 'adhesion'),
            'export' => __('Exportar seleccionados', 'adhesion'),
            'activate' => __('Activar usuarios', 'adhesion'),
            'deactivate' => __('Desactivar usuarios', 'adhesion'),
            'delete' => __('Eliminar usuarios', 'adhesion')
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
        $per_page = $this->get_items_per_page('adhesion_users_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Obtener filtros
        $filters = $this->get_filters();
        
        // Obtener elementos
        $this->items = $this->get_adhesion_users($per_page, $offset, $filters);
        
        // Configurar paginación
        $total_items = $this->get_total_adhesion_users($filters);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Obtener usuarios adheridos con estadísticas
     */
    private function get_adhesion_users($per_page, $offset, $filters) {
        global $wpdb;
        
        
        $where_clauses = array();
        $params = array();
        
        // Filtro base: solo usuarios con rol adhesion_client
        $capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
        $where_clauses[] = "um.meta_key = %s AND um.meta_value LIKE %s";
        $params[] = $capabilities_key;
        $params[] = '%adhesion_client%';
        
        // Filtros adicionales
        if (!empty($filters['status'])) {
            $where_clauses[] = "u.user_status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "u.user_registered >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "u.user_registered <= %s";
            $params[] = $filters['date_to'];
        }
        
        // Búsqueda
        if (!empty($filters['search'])) {
            $where_clauses[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Ordenación
        $orderby = sanitize_sql_orderby($filters['orderby'] . ' ' . $filters['order']);
        $orderby = $orderby ?: 'u.user_registered DESC';
        
        // Query principal con estadísticas - VERSIÓN CORREGIDA
        $sql = "SELECT u.*, 
                    um_first.meta_value as first_name,
                    um_last.meta_value as last_name,
                    um_phone.meta_value as phone,
                    um_company.meta_value as company,
                    COUNT(DISTINCT c.id) as calculation_count,
                    COUNT(DISTINCT ct.id) as contract_count,
                    COALESCE(SUM(CASE WHEN ct.payment_status = 'completed' THEN ct.payment_amount ELSE 0 END), 0) as total_paid,
                    MAX(GREATEST(
                        COALESCE(c.created_at, '1970-01-01'),
                        COALESCE(ct.created_at, '1970-01-01')
                    )) as last_activity
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
                LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id AND um_phone.meta_key = 'phone'
                LEFT JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'company'
                LEFT JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id
                LEFT JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
                WHERE {$where_sql}
                GROUP BY u.ID
                ORDER BY {$orderby}
                LIMIT %d OFFSET %d";

        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }
    
    /**
     * Obtener filtros de la URL
     */
    private function get_filters() {
        $filters = array();
        
        // Filtros básicos
        $filters['status'] = sanitize_text_field($_GET['status'] ?? '');
        
        // Filtros de fecha
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        // Filtro de actividad
        if (!empty($_GET['activity'])) {
            $filters['activity'] = sanitize_text_field($_GET['activity']);
        }
        
        // Búsqueda
        $filters['search'] = sanitize_text_field($_GET['s'] ?? '');
        
        // Ordenación
        $filters['orderby'] = sanitize_text_field($_GET['orderby'] ?? 'user_registered');
        $filters['order'] = sanitize_text_field($_GET['order'] ?? 'desc');
        
        return $filters;
    }
    
    /**
     * Obtener total de usuarios adheridos
     */
    private function get_total_adhesion_users($filters) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        // Filtro base: solo usuarios con rol adhesion_client
        $capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
        $where_clauses[] = "um.meta_key = %s AND um.meta_value LIKE %s";
        $params[] = $capabilities_key;
        $params[] = '%adhesion_client%';
        
        // Aplicar los mismos filtros
        if (!empty($filters['status'])) {
            $where_clauses[] = "u.user_status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "u.user_registered >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "u.user_registered <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT COUNT(DISTINCT u.ID)
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
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
            case 'user_registered':
                return adhesion_format_date($item['user_registered']);
                
            case 'last_activity':
                return $item['last_activity'] && $item['last_activity'] !== '1970-01-01 00:00:00' 
                    ? adhesion_format_date($item['last_activity']) 
                    : '<em>' . __('Sin actividad', 'adhesion') . '</em>';
                
            default:
                return $item[$column_name] ?? '-';
        }
    }
    
    /**
     * Columna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="user[]" value="%s" />',
            $item['ID']
        );
    }
    
    /**
     * Columna de información del usuario
     */
    public function column_user_info($item) {
        $display_name = $item['display_name'] ?: $item['user_login'];
        $full_name = trim($item['first_name'] . ' ' . $item['last_name']);
        
        $output = '<strong><a href="' . admin_url('user-edit.php?user_id=' . $item['ID']) . '">' . esc_html($display_name) . '</a></strong>';
        
        if ($full_name && $full_name !== $display_name) {
            $output .= '<br><small><em>' . esc_html($full_name) . '</em></small>';
        }
        
        $output .= '<br><small>ID: ' . $item['ID'] . '</small>';
        
        // Mostrar empresa si existe
        if ($item['company']) {
            $output .= '<br><small><strong>' . esc_html($item['company']) . '</strong></small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de información de contacto
     */
    public function column_contact_info($item) {
        $output = '<a href="mailto:' . esc_attr($item['user_email']) . '">' . esc_html($item['user_email']) . '</a>';
        
        if ($item['phone']) {
            $output .= '<br><small><a href="tel:' . esc_attr($item['phone']) . '">' . esc_html($item['phone']) . '</a></small>';
        }
        
        return $output;
    }
    
    /**
     * Columna de estadísticas de actividad
     */
    public function column_activity_stats($item) {
        $calculations = intval($item['calculation_count']);
        $contracts = intval($item['contract_count']);
        
        $output = '<div class="activity-stats">';
        
        // Cálculos
        $output .= '<div class="stat-item">';
        $output .= '<span class="dashicons dashicons-calculator"></span>';
        $output .= '<span class="stat-number">' . $calculations . '</span>';
        $output .= '<span class="stat-label">' . _n('cálculo', 'cálculos', $calculations, 'adhesion') . '</span>';
        $output .= '</div>';
        
        // Contratos
        $output .= '<div class="stat-item">';
        $output .= '<span class="dashicons dashicons-media-document"></span>';
        $output .= '<span class="stat-number">' . $contracts . '</span>';
        $output .= '<span class="stat-label">' . _n('contrato', 'contratos', $contracts, 'adhesion') . '</span>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Columna de estadísticas financieras
     */
    public function column_financial_stats($item) {
        $total_paid = floatval($item['total_paid']);
        
        if ($total_paid > 0) {
            return '<strong>' . adhesion_format_price($total_paid) . '</strong>';
        }
        
        return '<em>' . __('Sin pagos', 'adhesion') . '</em>';
    }
    
    /**
     * Columna de estado
     */
    public function column_status($item) {
        $status = intval($item['user_status']);
        
        if ($status === 0) {
            return '<span class="adhesion-badge adhesion-badge-success">' . __('Activo', 'adhesion') . '</span>';
        } else {
            return '<span class="adhesion-badge adhesion-badge-warning">' . __('Inactivo', 'adhesion') . '</span>';
        }
    }
    
    /**
     * Columna de acciones
     */
    public function column_actions($item) {
        $actions = array();
        
        // Ver perfil
        $actions['view'] = '<a href="' . admin_url('user-edit.php?user_id=' . $item['ID']) . '" class="button button-small">' . __('Ver Perfil', 'adhesion') . '</a>';
        
        // Ver cálculos si tiene
        if ($item['calculation_count'] > 0) {
            $calc_url = admin_url('admin.php?page=adhesion-calculations&user_id=' . $item['ID']);
            $actions['calculations'] = '<a href="' . esc_url($calc_url) . '" class="button button-small">' . __('Ver Cálculos', 'adhesion') . '</a>';
        }
        
        // Ver contratos si tiene
        if ($item['contract_count'] > 0) {
            $contract_url = admin_url('admin.php?page=adhesion-contracts&user_id=' . $item['ID']);
            $actions['contracts'] = '<a href="' . esc_url($contract_url) . '" class="button button-small">' . __('Ver Contratos', 'adhesion') . '</a>';
        }
        
        
        return '<div class="row-actions-wrapper">' . implode(' ', $actions) . '</div>';
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
                <option value="0" <?php selected($_GET['status'] ?? '', '0'); ?>><?php _e('Activos', 'adhesion'); ?></option>
                <option value="1" <?php selected($_GET['status'] ?? '', '1'); ?>><?php _e('Inactivos', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtro por actividad -->
            <label for="filter-activity" class="screen-reader-text"><?php _e('Filtrar por actividad', 'adhesion'); ?></label>
            <select name="activity" id="filter-activity">
                <option value=""><?php _e('Toda la actividad', 'adhesion'); ?></option>
                <option value="with_calculations" <?php selected($_GET['activity'] ?? '', 'with_calculations'); ?>><?php _e('Con cálculos', 'adhesion'); ?></option>
                <option value="with_contracts" <?php selected($_GET['activity'] ?? '', 'with_contracts'); ?>><?php _e('Con contratos', 'adhesion'); ?></option>
                <option value="with_payments" <?php selected($_GET['activity'] ?? '', 'with_payments'); ?>><?php _e('Con pagos', 'adhesion'); ?></option>
                <option value="no_activity" <?php selected($_GET['activity'] ?? '', 'no_activity'); ?>><?php _e('Sin actividad', 'adhesion'); ?></option>
            </select>
            
            <!-- Filtros de fecha -->
            <label for="filter-date-from" class="screen-reader-text"><?php _e('Registrado desde', 'adhesion'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" 
                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                   placeholder="<?php _e('Desde', 'adhesion'); ?>">
            
            <label for="filter-date-to" class="screen-reader-text"><?php _e('Registrado hasta', 'adhesion'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" 
                   value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                   placeholder="<?php _e('Hasta', 'adhesion'); ?>">
            
            <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar', 'adhesion'); ?>">
            
            <?php if (!empty($_GET['status']) || !empty($_GET['activity']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                    <?php _e('Limpiar', 'adhesion'); ?>
                </a>
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
        
        $user_ids = $_GET['user'] ?? array();
        
        if (empty($user_ids)) {
            return;
        }
        
        // Asegurar que son arrays
        if (!is_array($user_ids)) {
            $user_ids = array($user_ids);
        }
        
        switch ($action) {
            case 'send_email':
                $this->bulk_send_email($user_ids);
                break;
                
            case 'export':
                $this->bulk_export($user_ids);
                break;
                
            case 'activate':
                $this->bulk_activate($user_ids);
                break;
                
            case 'deactivate':
                $this->bulk_deactivate($user_ids);
                break;
                
            case 'delete':
                $this->bulk_delete($user_ids);
                break;
        }
    }
    
    /**
     * Enviar email en masa
     */
    private function bulk_send_email($user_ids) {
        // Redirigir a página de composición de email
        $url = admin_url('admin.php?page=adhesion-users&action=compose_email&users=' . implode(',', $user_ids));
        wp_redirect($url);
        exit;
    }
    
    /**
     * Exportar usuarios en masa
     */
    private function bulk_export($user_ids) {
        $users = array();
        
        foreach ($user_ids as $user_id) {
            $user_data = $this->get_user_full_data(intval($user_id));
            if ($user_data) {
                $users[] = $user_data;
            }
        }
        
        $this->export_users_csv($users);
    }
    
    /**
     * Activar usuarios en masa
     */
    private function bulk_activate($user_ids) {
        $activated_count = 0;
        
        foreach ($user_ids as $user_id) {
            $result = wp_update_user(array(
                'ID' => intval($user_id),
                'user_status' => 0
            ));
            
            if (!is_wp_error($result)) {
                $activated_count++;
            }
        }
        
        if ($activated_count > 0) {
            $message = sprintf(__('Se activaron %d usuarios correctamente.', 'adhesion'), $activated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-users'));
        exit;
    }
    
    /**
     * Desactivar usuarios en masa
     */
    private function bulk_deactivate($user_ids) {
        $deactivated_count = 0;
        
        foreach ($user_ids as $user_id) {
            // No desactivar el usuario actual
            if (intval($user_id) === get_current_user_id()) {
                continue;
            }
            
            $result = wp_update_user(array(
                'ID' => intval($user_id),
                'user_status' => 1
            ));
            
            if (!is_wp_error($result)) {
                $deactivated_count++;
            }
        }
        
        if ($deactivated_count > 0) {
            $message = sprintf(__('Se desactivaron %d usuarios correctamente.', 'adhesion'), $deactivated_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-users'));
        exit;
    }
    
    /**
     * Eliminar usuarios en masa (con precaución)
     */
    private function bulk_delete($user_ids) {
        $deleted_count = 0;
        
        foreach ($user_ids as $user_id) {
            $user_id = intval($user_id);
            
            // No eliminar el usuario actual
            if ($user_id === get_current_user_id()) {
                continue;
            }
            
            // No eliminar si tiene contratos firmados
            global $wpdb;
            $signed_contracts = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'signed'",
                $user_id
            ));
            
            if ($signed_contracts > 0) {
                continue; // Saltar usuarios con contratos firmados
            }
            
            // Eliminar usuario
            if (wp_delete_user($user_id)) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            $message = sprintf(__('Se eliminaron %d usuarios correctamente.', 'adhesion'), $deleted_count);
            adhesion_add_notice($message, 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=adhesion-users'));
        exit;
    }
    
    /**
     * Obtener datos completos de un usuario
     */
    private function get_user_full_data($user_id) {
        global $wpdb;
        
        $sql = "SELECT u.*, 
                       um_first.meta_value as first_name,
                       um_last.meta_value as last_name,
                       um_phone.meta_value as phone,
                       um_company.meta_value as company,
                       COUNT(DISTINCT c.id) as calculation_count,
                       COUNT(DISTINCT ct.id) as contract_count,
                       SUM(CASE WHEN ct.payment_status = 'completed' THEN ct.payment_amount ELSE 0 END) as total_paid
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
                LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id AND um_phone.meta_key = 'phone'
                LEFT JOIN {$wpdb->usermeta} um_company ON u.ID = um_company.user_id AND um_company.meta_key = 'company'
                LEFT JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id AND c.status = 'active'
                LEFT JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
                WHERE u.ID = %d
                GROUP BY u.ID";
        
        return $wpdb->get_row($wpdb->prepare($sql, $user_id), ARRAY_A);
    }
    
    /**
     * Exportar usuarios a CSV
     */
    private function export_users_csv($users) {
        $filename = 'adhesion_users_' . date('Y-m-d_H-i-s') . '.csv';
        
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
            'Nombre',
            'Apellidos',
            'Email',
            'Teléfono',
            'Empresa',
            'Estado',
            'Fecha Registro',
            'Cálculos',
            'Contratos',
            'Total Pagado',
            'Última Actividad'
        ), ';');
        
        // Datos
        foreach ($users as $user) {
            fputcsv($output, array(
                $user['ID'],
                $user['user_login'],
                $user['first_name'] ?: '',
                $user['last_name'] ?: '',
                $user['user_email'],
                $user['phone'] ?: '',
                $user['company'] ?: '',
                $user['user_status'] == 0 ? 'Activo' : 'Inactivo',
                $user['user_registered'],
                $user['calculation_count'] ?: '0',
                $user['contract_count'] ?: '0',
                $user['total_paid'] ?: '0',
                $user['last_activity'] ?: ''
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Enviar email a usuario
     */
    public function ajax_send_user_email() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $user_id = intval($_POST['user_id']);
            $subject = sanitize_text_field($_POST['subject']);
            $message = sanitize_textarea_field($_POST['message']);
            
            $user = get_userdata($user_id);
            if (!$user) {
                throw new Exception(__('Usuario no encontrado.', 'adhesion'));
            }
            
            // Enviar email
            $sent = adhesion_send_email(
                $user->user_email,
                $subject,
                'user-notification',
                array(
                    'user_name' => $user->display_name,
                    'message' => $message,
                    'site_name' => get_bloginfo('name')
                )
            );
            
            if (!$sent) {
                throw new Exception(__('Error al enviar el email.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Email enviado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Exportar usuarios
     */
    public function ajax_export_users() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Obtener todos los usuarios con filtros actuales
            $filters = $this->get_filters();
            $users = $this->get_adhesion_users(1000, 0, $filters); // Máximo 1000
            
            $this->export_users_csv($users);
            
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }
    
    /**
     * AJAX: Actualizar estado de usuario
     */
    public function ajax_update_user_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $user_id = intval($_POST['user_id']);
            $status = intval($_POST['status']);
            
            // No permitir cambiar el estado del usuario actual
            if ($user_id === get_current_user_id()) {
                throw new Exception(__('No puedes cambiar tu propio estado.', 'adhesion'));
            }
            
            $result = wp_update_user(array(
                'ID' => $user_id,
                'user_status' => $status
            ));
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $status_text = $status == 0 ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Usuario marcado como %s.', 'adhesion'), strtolower($status_text)),
                'new_status' => $status_text
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

/**
 * Función para mostrar la página de listado de usuarios
 */
function adhesion_display_users_page() {
    $list_table = new Adhesion_Users_List();
    $list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Usuarios Adheridos', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action">
            <?php _e('Añadir Usuario', 'adhesion'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('← Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <form method="get" id="users-filter">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <?php
            $list_table->search_box(__('Buscar usuarios', 'adhesion'), 'user');
            $list_table->display();
            ?>
        </form>
        
        <!-- Modal para enviar email -->
        <div id="email-modal" class="adhesion-modal" style="display: none;">
            <div class="adhesion-modal-content">
                <div class="adhesion-modal-header">
                    <h2><?php _e('Enviar Email', 'adhesion'); ?></h2>
                    <button type="button" class="adhesion-modal-close" onclick="adhesionCloseEmailModal()">&times;</button>
                </div>
                <div class="adhesion-modal-body">
                    <form id="email-form">
                        <div class="adhesion-form-row">
                            <label for="email-to"><?php _e('Para:', 'adhesion'); ?></label>
                            <input type="email" id="email-to" readonly>
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label for="email-subject"><?php _e('Asunto:', 'adhesion'); ?></label>
                            <input type="text" id="email-subject" required>
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label for="email-message"><?php _e('Mensaje:', 'adhesion'); ?></label>
                            <textarea id="email-message" rows="6" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="adhesion-modal-footer">
                    <button type="button" class="button button-primary" onclick="adhesionSendEmailConfirm()">
                        <?php _e('Enviar Email', 'adhesion'); ?>
                    </button>
                    <button type="button" class="button" onclick="adhesionCloseEmailModal()">
                        <?php _e('Cancelar', 'adhesion'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros
        $('#filter-status, #filter-activity, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#users-filter').submit();
        });
    });
    
    // Variables globales para el modal
    let currentUserId = null;
    
    function adhesionSendUserEmail(userId, userEmail) {
        currentUserId = userId;
        document.getElementById('email-to').value = userEmail;
        document.getElementById('email-subject').value = '';
        document.getElementById('email-message').value = '';
        document.getElementById('email-modal').style.display = 'block';
    }
    
    function adhesionCloseEmailModal() {
        document.getElementById('email-modal').style.display = 'none';
        currentUserId = null;
    }
    
    function adhesionSendEmailConfirm() {
        if (!currentUserId) return;
        
        const subject = document.getElementById('email-subject').value;
        const message = document.getElementById('email-message').value;
        
        if (!subject || !message) {
            alert('<?php echo esc_js(__('Por favor, completa todos los campos.', 'adhesion')); ?>');
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_send_user_email',
                user_id: currentUserId,
                subject: subject,
                message: message,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#email-modal .adhesion-modal-content').css('opacity', '0.6');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    adhesionCloseEmailModal();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error de conexión', 'adhesion')); ?>');
            },
            complete: function() {
                jQuery('#email-modal .adhesion-modal-content').css('opacity', '1');
            }
        });
    }
    
    function adhesionExportUsers() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        // Agregar campos ocultos con filtros actuales
        const fields = {
            action: 'adhesion_export_users',
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>',
            status: '<?php echo esc_js($_GET['status'] ?? ''); ?>',
            activity: '<?php echo esc_js($_GET['activity'] ?? ''); ?>',
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
    
    function adhesionBulkEmail() {
        const checkedBoxes = document.querySelectorAll('input[name="user[]"]:checked');
        
        if (checkedBoxes.length === 0) {
            alert('<?php echo esc_js(__('Selecciona al menos un usuario.', 'adhesion')); ?>');
            return;
        }
        
        // TODO: Implementar modal de email masivo
        alert('<?php echo esc_js(__('Funcionalidad de email masivo en desarrollo.', 'adhesion')); ?>');
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('email-modal');
        if (event.target === modal) {
            adhesionCloseEmailModal();
        }
    }
    </script>
    
    <style>
    .activity-stats {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }
    
    .stat-item .dashicons {
        width: 16px;
        height: 16px;
        font-size: 16px;
        color: #646970;
    }
    
    .stat-number {
        font-weight: bold;
        color: #0073aa;
        min-width: 20px;
    }
    
    .stat-label {
        color: #646970;
    }
    
    .row-actions-wrapper {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .row-actions-wrapper .button {
        margin: 0;
        font-size: 11px;
        padding: 3px 6px;
        height: auto;
        line-height: 1.2;
    }
    
    /* Modal styles */
    .adhesion-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .adhesion-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .adhesion-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ccd0d4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f6f7f7;
    }
    
    .adhesion-modal-header h2 {
        margin: 0;
        font-size: 16px;
    }
    
    .adhesion-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #646970;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .adhesion-modal-close:hover {
        color: #d63638;
    }
    
    .adhesion-modal-body {
        padding: 20px;
    }
    
    .adhesion-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ccd0d4;
        background: #f6f7f7;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .adhesion-form-row {
        margin-bottom: 15px;
    }
    
    .adhesion-form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .adhesion-form-row input,
    .adhesion-form-row textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    
    .adhesion-form-row input:focus,
    .adhesion-form-row textarea:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }
    
    @media (max-width: 768px) {
        .column-activity_stats,
        .column-financial_stats,
        .column-last_activity {
            display: none;
        }
        
        .activity-stats {
            flex-direction: row;
        }
        
        .row-actions-wrapper {
            flex-direction: column;
        }
        
        .adhesion-modal-content {
            margin: 10% auto;
            width: 95%;
        }
    }
    </style>
    <?php
}