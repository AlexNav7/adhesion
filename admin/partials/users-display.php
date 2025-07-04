<?php
/**
 * Vista del listado de usuarios adheridos
 * 
 * Esta vista maneja:
 * - Listado principal de usuarios adheridos
 * - Vista detallada de un usuario específico
 * - Composición y envío de emails
 * - Estadísticas y resúmenes de usuarios
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acción actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$user_id = intval($_GET['user_id'] ?? 0);

// Instanciar base de datos
$db = new Adhesion_Database();

// Manejar acciones específicas
switch ($action) {
    case 'view':
        if ($user_id) {
            adhesion_display_user_detail($user_id, $db);
        } else {
            adhesion_display_users_list();
        }
        break;
        
    case 'compose_email':
        adhesion_display_email_compose();
        break;
        
    default:
        adhesion_display_users_list();
        break;
}

/**
 * Mostrar listado principal de usuarios
 */
function adhesion_display_users_list() {
    // Obtener estadísticas de usuarios
    global $wpdb;
    
    $user_stats = array();
    
    // Total de usuarios adheridos
    $user_stats['total'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'"
    );
    
    // Usuarios activos
    $user_stats['active'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND u.user_status = 0"
    );
    
    // Usuarios con actividad (últimos 30 días)
    $user_stats['active_recently'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         LEFT JOIN {$wpdb->prefix}adhesion_calculations c ON u.ID = c.user_id
         LEFT JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND (c.created_at >= %s OR ct.created_at >= %s)",
        date('Y-m-d', strtotime('-30 days')),
        date('Y-m-d', strtotime('-30 days'))
    ));
    
    // Usuarios con contratos firmados
    $user_stats['with_contracts'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         INNER JOIN {$wpdb->prefix}adhesion_contracts ct ON u.ID = ct.user_id
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND ct.status = 'signed'"
    );
    
    // Nuevos usuarios este mes
    $user_stats['new_this_month'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%adhesion_client%'
         AND u.user_registered >= %s",
        date('Y-m-01')
    ));
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('Usuarios Adheridos', 'adhesion'); ?>
            <span class="title-count"><?php echo sprintf(__('(%s total)', 'adhesion'), number_format($user_stats['total'])); ?></span>
        </h1>
        
        <a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action">
            <?php _e('+ Añadir Usuario', 'adhesion'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('← Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar notificaciones
        adhesion_display_notices();
        ?>
        
        <!-- Estadísticas de usuarios -->
        <div class="adhesion-users-overview">
            <div class="adhesion-stats-grid">
                <div class="users-stat-card total">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['total']); ?></div>
                        <div class="stat-label"><?php _e('Total Usuarios', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button button-small">
                            <?php _e('Ver Todos', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="users-stat-card active">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['active']); ?></div>
                        <div class="stat-label"><?php _e('Usuarios Activos', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <div class="stat-percentage">
                            <?php 
                            $active_percentage = $user_stats['total'] > 0 ? ($user_stats['active'] / $user_stats['total']) * 100 : 0;
                            echo sprintf(__('%.1f%%', 'adhesion'), $active_percentage);
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="users-stat-card recent">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['active_recently']); ?></div>
                        <div class="stat-label"><?php _e('Activos (30 días)', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users&activity=recent'); ?>" class="button button-small">
                            <?php _e('Ver', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="users-stat-card contracts">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['with_contracts']); ?></div>
                        <div class="stat-label"><?php _e('Con Contratos', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users&activity=with_contracts'); ?>" class="button button-small">
                            <?php _e('Ver', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="users-stat-card new">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($user_stats['new_this_month']); ?></div>
                        <div class="stat-label"><?php _e('Nuevos Este Mes', 'adhesion'); ?></div>
                    </div>
                    <div class="stat-action">
                        <?php if ($user_stats['new_this_month'] > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=adhesion-users&date_from=' . date('Y-m-01')); ?>" class="button button-small">
                            <?php _e('Ver', 'adhesion'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accesos rápidos -->
        <div class="adhesion-quick-actions-row">
            <div class="quick-action-item">
                <h3><?php _e('Acciones Rápidas', 'adhesion'); ?></h3>
                <div class="quick-actions-buttons">
                    <button type="button" class="button button-primary" onclick="adhesionBulkEmailModal()">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php _e('Email a Todos los Activos', 'adhesion'); ?>
                    </button>
                    
                    <button type="button" class="button" onclick="adhesionExportAllUsers()">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Exportar Todos', 'adhesion'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-users&activity=no_activity'); ?>" class="button">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Ver Sin Actividad', 'adhesion'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tabla de usuarios -->
        <div class="adhesion-table-container">
            <?php
            // Crear y mostrar la tabla
            $list_table = new Adhesion_Users_List();
            $list_table->prepare_items();
            ?>
            
            <form method="get" id="users-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                <?php
                $list_table->search_box(__('Buscar usuarios', 'adhesion'), 'user');
                $list_table->display();
                ?>
            </form>
        </div>
    </div>
    
    <style>
    .adhesion-users-overview {
        margin: 20px 0 30px;
    }
    
    .users-stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 6px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        position: relative;
        overflow: hidden;
    }
    
    .users-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .users-stat-card.total::before { background: #0073aa; }
    .users-stat-card.active::before { background: #00a32a; }
    .users-stat-card.recent::before { background: #dba617; }
    .users-stat-card.contracts::before { background: #8c8f94; }
    .users-stat-card.new::before { background: #d63638; }
    
    .users-stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .users-stat-card.total .stat-icon { background: #0073aa; }
    .users-stat-card.active .stat-icon { background: #00a32a; }
    .users-stat-card.recent .stat-icon { background: #dba617; }
    .users-stat-card.contracts .stat-icon { background: #8c8f94; }
    .users-stat-card.new .stat-icon { background: #d63638; }
    
    .users-stat-card .stat-content {
        flex: 1;
    }
    
    .users-stat-card .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .users-stat-card .stat-label {
        font-size: 14px;
        color: #646970;
        font-weight: 500;
    }
    
    .users-stat-card .stat-action {
        flex-shrink: 0;
    }
    
    .stat-percentage {
        font-size: 14px;
        font-weight: bold;
        color: #646970;
        text-align: center;
    }
    
    .adhesion-quick-actions-row {
        margin: 20px 0;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    .adhesion-quick-actions-row h3 {
        margin: 0 0 15px 0;
        font-size: 16px;
        color: #1d2327;
    }
    
    .quick-actions-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .quick-actions-buttons .button {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    @media (max-width: 768px) {
        .users-stat-card {
            padding: 15px;
        }
        
        .users-stat-card .stat-number {
            font-size: 20px;
        }
        
        .users-stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .quick-actions-buttons {
            flex-direction: column;
        }
        
        .quick-actions-buttons .button {
            justify-content: center;
        }
    }
    </style>
    <?php
}

/**
 * Mostrar detalle de un usuario específico
 */
function adhesion_display_user_detail($user_id, $db) {
    $user = get_userdata($user_id);
    
    if (!$user || !in_array('adhesion_client', $user->roles)) {
        ?>
        <div class="wrap">
            <h1><?php _e('Usuario no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El usuario solicitado no existe o no es un usuario adherido.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                <?php _e('← Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener datos adicionales
    $calculations = $db->get_user_calculations($user_id, 10);
    $contracts = $db->get_user_contracts($user_id, 10);
    
    // Obtener metadatos del usuario
    $user_meta = array(
        'first_name' => get_user_meta($user_id, 'first_name', true),
        'last_name' => get_user_meta($user_id, 'last_name', true),
        'phone' => get_user_meta($user_id, 'phone', true),
        'company' => get_user_meta($user_id, 'company', true)
    );
    
    // Calcular estadísticas
    $total_paid = 0;
    $contracts_signed = 0;
    foreach ($contracts as $contract) {
        if ($contract['payment_status'] === 'completed') {
            $total_paid += floatval($contract['payment_amount']);
        }
        if ($contract['status'] === 'signed') {
            $contracts_signed++;
        }
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php echo sprintf(__('Usuario: %s', 'adhesion'), esc_html($user->display_name)); ?>
            <span class="user-status-badge">
                <?php 
                $status_class = $user->user_status == 0 ? 'success' : 'warning';
                $status_text = $user->user_status == 0 ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
                echo '<span class="adhesion-badge adhesion-badge-' . $status_class . '">' . $status_text . '</span>';
                ?>
            </span>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="page-title-action">
            <?php _e('← Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <div class="adhesion-user-detail">
            <div class="adhesion-detail-grid">
                <!-- Panel izquierdo -->
                <div class="adhesion-detail-left">
                    <!-- Información personal -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Información Personal', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <table class="adhesion-detail-table">
                                <tr>
                                    <th><?php _e('ID:', 'adhesion'); ?></th>
                                    <td><?php echo $user->ID; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Usuario:', 'adhesion'); ?></th>
                                    <td><?php echo esc_html($user->user_login); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Nombre completo:', 'adhesion'); ?></th>
                                    <td><?php echo esc_html(trim($user_meta['first_name'] . ' ' . $user_meta['last_name'])) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Email:', 'adhesion'); ?></th>
                                    <td><a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Teléfono:', 'adhesion'); ?></th>
                                    <td><?php echo $user_meta['phone'] ? '<a href="tel:' . esc_attr($user_meta['phone']) . '">' . esc_html($user_meta['phone']) . '</a>' : '-'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Empresa:', 'adhesion'); ?></th>
                                    <td><?php echo esc_html($user_meta['company']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Registrado:', 'adhesion'); ?></th>
                                    <td><?php echo adhesion_format_date($user->user_registered); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Estado:', 'adhesion'); ?></th>
                                    <td>
                                        <?php 
                                        $status_class = $user->user_status == 0 ? 'success' : 'warning';
                                        $status_text = $user->user_status == 0 ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
                                        echo '<span class="adhesion-badge adhesion-badge-' . $status_class . '">' . $status_text . '</span>';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Estadísticas -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Estadísticas', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <div class="user-stats-grid">
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-calculator"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo count($calculations); ?></div>
                                        <div class="stat-label"><?php _e('Cálculos', 'adhesion'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-media-document"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo count($contracts); ?></div>
                                        <div class="stat-label"><?php _e('Contratos', 'adhesion'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $contracts_signed; ?></div>
                                        <div class="stat-label"><?php _e('Firmados', 'adhesion'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="user-stat-item">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-money-alt"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo adhesion_format_price($total_paid); ?></div>
                                        <div class="stat-label"><?php _e('Total Pagado', 'adhesion'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Panel derecho -->
                <div class="adhesion-detail-right">
                    <!-- Últimos cálculos -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Últimos Cálculos', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <?php if (!empty($calculations)): ?>
                            <div class="activity-list">
                                <?php foreach (array_slice($calculations, 0, 5) as $calc): ?>
                                <div class="activity-item">
                                    <div class="activity-content">
                                        <strong><?php echo adhesion_format_price($calc['total_price']); ?></strong>
                                        <span class="activity-date"><?php echo adhesion_format_date($calc['created_at'], 'd/m/Y'); ?></span>
                                    </div>
                                    <div class="activity-action">
                                        <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $calc['id']); ?>" class="button button-small">
                                            <?php _e('Ver', 'adhesion'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($calculations) > 5): ?>
                            <div class="card-footer">
                                <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&user_id=' . $user_id); ?>" class="button">
                                    <?php _e('Ver todos los cálculos', 'adhesion'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <p class="adhesion-no-data"><?php _e('No hay cálculos registrados.', 'adhesion'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Últimos contratos -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Últimos Contratos', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <?php if (!empty($contracts)): ?>
                            <div class="activity-list">
                                <?php foreach (array_slice($contracts, 0, 5) as $contract): ?>
                                <div class="activity-item">
                                    <div class="activity-content">
                                        <strong><?php echo esc_html($contract['contract_number']); ?></strong>
                                        <?php 
                                        $status = adhesion_format_contract_status($contract['status']);
                                        echo '<span class="adhesion-badge adhesion-badge-' . esc_attr($status['class']) . '">' . esc_html($status['label']) . '</span>';
                                        ?>
                                        <span class="activity-date"><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y'); ?></span>
                                    </div>
                                    <div class="activity-action">
                                        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']); ?>" class="button button-small">
                                            <?php _e('Ver', 'adhesion'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($contracts) > 5): ?>
                            <div class="card-footer">
                                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&user_id=' . $user_id); ?>" class="button">
                                    <?php _e('Ver todos los contratos', 'adhesion'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <p class="adhesion-no-data"><?php _e('No hay contratos registrados.', 'adhesion'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="adhesion-card">
                        <div class="adhesion-card-header">
                            <h2><?php _e('Acciones', 'adhesion'); ?></h2>
                        </div>
                        <div class="adhesion-card-body">
                            <div class="adhesion-actions-list">
                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $user_id); ?>" class="button button-primary">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php _e('Editar Usuario', 'adhesion'); ?>
                                </a>
                                
                                <button type="button" class="button" onclick="adhesionExportUserData(<?php echo $user_id; ?>)">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php _e('Exportar Datos', 'adhesion'); ?>
                                </button>
                                
                                <?php if ($user->user_status == 0): ?>
                                <button type="button" class="button button-secondary" onclick="adhesionToggleUserStatus(<?php echo $user_id; ?>, 1)">
                                    <span class="dashicons dashicons-hidden"></span>
                                    <?php _e('Desactivar Usuario', 'adhesion'); ?>
                                </button>
                                <?php else: ?>
                                <button type="button" class="button button-secondary" onclick="adhesionToggleUserStatus(<?php echo $user_id; ?>, 0)">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Activar Usuario', 'adhesion'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .user-status-badge {
        margin-left: 10px;
    }
    
    .adhesion-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .adhesion-detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .adhesion-detail-table th,
    .adhesion-detail-table td {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f1;
        text-align: left;
        vertical-align: top;
    }
    
    .adhesion-detail-table th {
        width: 35%;
        font-weight: 600;
        color: #646970;
    }
    
    .adhesion-detail-table td {
        color: #1d2327;
    }
    
    .user-stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .user-stat-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px;
        background: #f6f7f7;
        border-radius: 4px;
    }
    
    .user-stat-item .stat-icon {
        width: 35px;
        height: 35px;
        background: #0073aa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
    }
    
    .user-stat-item .stat-number {
        font-size: 18px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
    }
    
    .user-stat-item .stat-label {
        font-size: 12px;
        color: #646970;
        margin-top: 2px;
    }
    
    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .activity-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 4px;
        border-left: 3px solid #0073aa;
    }
    
    .activity-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .activity-date {
        font-size: 12px;
        color: #646970;
    }
    
    .card-footer {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f1;
        text-align: center;
    }
    
    .adhesion-actions-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .adhesion-actions-list .button {
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-start;
        width: 100%;
        text-align: left;
    }
    
    .adhesion-no-data {
        color: #646970;
        font-style: italic;
        text-align: center;
        padding: 20px;
    }
    
    @media (max-width: 768px) {
        .adhesion-detail-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .user-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .activity-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
    </style>
    
    <script>
    function adhesionExportUserData(userId) {
        // TODO: Implementar exportación individual de usuario
        alert('<?php echo esc_js(__('Funcionalidad de exportación individual en desarrollo.', 'adhesion')); ?>');
    }
    
    function adhesionToggleUserStatus(userId, newStatus) {
        const statusText = newStatus == 0 ? '<?php echo esc_js(__('activar', 'adhesion')); ?>' : '<?php echo esc_js(__('desactivar', 'adhesion')); ?>';
        
        if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres', 'adhesion')); ?> ' + statusText + ' <?php echo esc_js(__('este usuario?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_update_user_status',
                user_id: userId,
                status: newStatus,
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
    </script>
    <?php
}

/**
 * Mostrar página de composición de email
 */
function adhesion_display_email_compose() {
    $user_ids = explode(',', sanitize_text_field($_GET['users'] ?? ''));
    $user_ids = array_filter(array_map('intval', $user_ids));
    
    if (empty($user_ids)) {
        ?>
        <div class="wrap">
            <h1><?php _e('Componer Email', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('No se han seleccionado usuarios para enviar el email.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                <?php _e('← Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener información de los usuarios
    $users = array();
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user && in_array('adhesion_client', $user->roles)) {
            $users[] = $user;
        }
    }
    
    if (empty($users)) {
        ?>
        <div class="wrap">
            <h1><?php _e('Componer Email', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('Los usuarios seleccionados no son válidos.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button">
                <?php _e('← Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Componer Email Masivo', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="page-title-action">
            <?php _e('← Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <div class="adhesion-email-compose">
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php echo sprintf(__('Enviando a %d usuarios', 'adhesion'), count($users)); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <!-- Lista de destinatarios -->
                    <div class="recipients-list">
                        <h3><?php _e('Destinatarios:', 'adhesion'); ?></h3>
                        <div class="recipients-grid">
                            <?php foreach ($users as $user): ?>
                            <div class="recipient-item">
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Formulario de email -->
                    <form id="bulk-email-form" method="post" action="">
                        <?php wp_nonce_field('adhesion_bulk_email', 'adhesion_bulk_email_nonce'); ?>
                        
                        <?php foreach ($user_ids as $user_id): ?>
                        <input type="hidden" name="user_ids[]" value="<?php echo $user_id; ?>">
                        <?php endforeach; ?>
                        
                        <div class="adhesion-form-row">
                            <label for="email-subject"><?php _e('Asunto:', 'adhesion'); ?></label>
                            <input type="text" id="email-subject" name="subject" required 
                                   placeholder="<?php _e('Escribe el asunto del email...', 'adhesion'); ?>">
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label for="email-message"><?php _e('Mensaje:', 'adhesion'); ?></label>
                            <textarea id="email-message" name="message" rows="10" required 
                                      placeholder="<?php _e('Escribe tu mensaje aquí...', 'adhesion'); ?>"></textarea>
                            <p class="adhesion-form-help">
                                <?php _e('Variables disponibles: {nombre}, {email}, {empresa}', 'adhesion'); ?>
                            </p>
                        </div>
                        
                        <div class="adhesion-form-row">
                            <label>
                                <input type="checkbox" name="send_test" value="1">
                                <?php _e('Enviar email de prueba solo a mi dirección primero', 'adhesion'); ?>
                            </label>
                        </div>
                        
                        <div class="adhesion-form-actions">
                            <button type="submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php _e('Enviar Emails', 'adhesion'); ?>
                            </button>
                            
                            <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="button button-large">
                                <?php _e('Cancelar', 'adhesion'); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .adhesion-email-compose {
        max-width: 800px;
        margin-top: 20px;
    }
    
    .recipients-list {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f0f1;
    }
    
    .recipients-list h3 {
        margin-bottom: 15px;
        color: #1d2327;
    }
    
    .recipients-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 10px;
    }
    
    .recipient-item {
        padding: 8px 12px;
        background: #f6f7f7;
        border-radius: 4px;
        border-left: 3px solid #0073aa;
    }
    
    .recipient-item strong {
        display: block;
        color: #1d2327;
    }
    
    .recipient-item small {
        color: #646970;
        font-size: 12px;
    }
    
    .adhesion-form-row {
        margin-bottom: 20px;
    }
    
    .adhesion-form-row label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #1d2327;
    }
    
    .adhesion-form-row input[type="text"],
    .adhesion-form-row textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .adhesion-form-row input:focus,
    .adhesion-form-row textarea:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }
    
    .adhesion-form-help {
        font-size: 12px;
        color: #646970;
        margin-top: 5px;
        font-style: italic;
    }
    
    .adhesion-form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #f0f0f1;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .button-large {
        padding: 8px 16px;
        height: auto;
        line-height: 1.4;
    }
    
    @media (max-width: 768px) {
        .recipients-grid {
            grid-template-columns: 1fr;
        }
        
        .adhesion-form-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .adhesion-form-actions .button {
            justify-content: center;
        }
    }
    </style>
    
    <?php
    // Procesar envío del formulario
    if ($_POST && wp_verify_nonce($_POST['adhesion_bulk_email_nonce'], 'adhesion_bulk_email')) {
        adhesion_process_bulk_email($_POST);
    }
    ?>
    
    <script>
    jQuery(document).ready(function($) {
        $('#bulk-email-form').on('submit', function(e) {
            const subject = $('#email-subject').val().trim();
            const message = $('#email-message').val().trim();
            
            if (!subject || !message) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Por favor, completa todos los campos obligatorios.', 'adhesion')); ?>');
                return false;
            }
            
            if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres enviar este email a todos los usuarios seleccionados?', 'adhesion')); ?>')) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * Procesar envío de email masivo
 */
function adhesion_process_bulk_email($post_data) {
    $user_ids = array_map('intval', $post_data['user_ids'] ?? array());
    $subject = sanitize_text_field($post_data['subject']);
    $message = sanitize_textarea_field($post_data['message']);
    $send_test = isset($post_data['send_test']);
    
    if (empty($user_ids) || empty($subject) || empty($message)) {
        adhesion_add_notice(__('Faltan datos obligatorios para enviar el email.', 'adhesion'), 'error');
        return;
    }
    
    $sent_count = 0;
    $failed_count = 0;
    
    if ($send_test) {
        // Enviar solo a administrador como prueba
        $admin_email = get_option('admin_email');
        $test_message = "[PRUEBA] " . $message . "\n\n---\nEste es un email de prueba. Se enviaría a " . count($user_ids) . " usuarios.";
        
        $sent = adhesion_send_email($admin_email, "[PRUEBA] " . $subject, 'user-notification', array(
            'user_name' => 'Administrador',
            'message' => $test_message,
            'site_name' => get_bloginfo('name')
        ));
        
        if ($sent) {
            adhesion_add_notice(__('Email de prueba enviado a tu dirección.', 'adhesion'), 'success');
        } else {
            adhesion_add_notice(__('Error enviando email de prueba.', 'adhesion'), 'error');
        }
        
        return;
    }
    
    // Enviar a todos los usuarios
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            $failed_count++;
            continue;
        }
        
        // Reemplazar variables en el mensaje
        $personalized_message = str_replace(
            array('{nombre}', '{email}', '{empresa}'),
            array(
                $user->display_name,
                $user->user_email,
                get_user_meta($user_id, 'company', true) ?: ''
            ),
            $message
        );
        
        $sent = adhesion_send_email($user->user_email, $subject, 'user-notification', array(
            'user_name' => $user->display_name,
            'message' => $personalized_message,
            'site_name' => get_bloginfo('name')
        ));
        
        if ($sent) {
            $sent_count++;
        } else {
            $failed_count++;
        }
        
        // Pausa pequeña para evitar sobrecarga del servidor
        usleep(100000); // 0.1 segundos
    }
    
    // Mostrar resultado
    if ($sent_count > 0) {
        $message = sprintf(__('Se enviaron %d emails correctamente.', 'adhesion'), $sent_count);
        if ($failed_count > 0) {
            $message .= ' ' . sprintf(__('%d emails fallaron.', 'adhesion'), $failed_count);
        }
        adhesion_add_notice($message, 'success');
    } else {
        adhesion_add_notice(__('No se pudo enviar ningún email.', 'adhesion'), 'error');
    }
}

// Funciones JavaScript globales
?>
<script>
function adhesionBulkEmailModal() {
    alert('<?php echo esc_js(__('Selecciona usuarios desde la tabla usando los checkboxes y luego usa la acción "Enviar Email".', 'adhesion')); ?>');
}

function adhesionExportAllUsers() {
    if (confirm('<?php echo esc_js(__('¿Exportar todos los usuarios adheridos a CSV?', 'adhesion')); ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        
        const fields = {
            action: 'adhesion_export_users',
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
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
}
</script>