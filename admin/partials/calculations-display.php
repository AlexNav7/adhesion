<?php
/**
 * Vista del listado de cálculos de presupuestos
 * 
 * Esta vista maneja:
 * - Listado principal de cálculos
 * - Vista detallada de un cálculo específico
 * - Estadísticas y resúmenes
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acción actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$calculation_id = intval($_GET['calculation'] ?? 0);

// Instanciar base de datos
$db = new Adhesion_Database();

// Manejar acciones específicas
switch ($action) {
    case 'view':
        if ($calculation_id) {
            adhesion_display_calculation_detail($calculation_id, $db);
        } else {
            adhesion_display_calculations_list();
        }
        break;
        
    case 'delete':
        if ($calculation_id && wp_verify_nonce($_GET['_wpnonce'], 'delete_calculation_' . $calculation_id)) {
            adhesion_handle_delete_calculation($calculation_id, $db);
        }
        adhesion_display_calculations_list();
        break;
        
    default:
        adhesion_display_calculations_list();
        break;
}

/**
 * Mostrar listado principal de cálculos
 */
function adhesion_display_calculations_list() {
    // Obtener estadísticas rápidas
    $db = new Adhesion_Database();
    $stats = $db->get_basic_stats();
    
    // Estadísticas de la última semana
    $week_stats = $db->get_period_stats(
        date('Y-m-d', strtotime('-7 days')),
        date('Y-m-d')
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('Cálculos de Presupuestos', 'adhesion'); ?>
            <span class="title-count"><?php echo sprintf(__('(%s total)', 'adhesion'), number_format($stats['total_calculations'])); ?></span>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('← Volver al Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar notificaciones
        adhesion_display_notices();
        ?>
        
        <!-- Estadísticas rápidas -->
        <div class="adhesion-quick-stats">
            <div class="adhesion-stats-grid">
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calculator"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_calculations']); ?></div>
                        <div class="stat-label"><?php _e('Total Cálculos', 'adhesion'); ?></div>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($week_stats['period_calculations']); ?></div>
                        <div class="stat-label"><?php _e('Esta Semana', 'adhesion'); ?></div>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo adhesion_format_price($week_stats['period_revenue']); ?></div>
                        <div class="stat-label"><?php _e('Ingresos Semana', 'adhesion'); ?></div>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <?php 
                        $avg_calc_value = $stats['total_calculations'] > 0 ? $stats['total_revenue'] / $stats['total_calculations'] : 0;
                        ?>
                        <div class="stat-number"><?php echo adhesion_format_price($avg_calc_value); ?></div>
                        <div class="stat-label"><?php _e('Valor Promedio', 'adhesion'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de cálculos -->
        <div class="adhesion-table-container">
            <?php
            // Crear y mostrar la tabla
            $list_table = new Adhesion_Calculations_List();
            $list_table->prepare_items();
            ?>
            
            <form method="get" id="calculations-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                <?php
                $list_table->search_box(__('Buscar cálculos', 'adhesion'), 'calculation');
                $list_table->display();
                ?>
            </form>
        </div>
    </div>
    
    <style>
    .adhesion-quick-stats {
        margin: 20px 0 30px;
    }
    
    .quick-stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    .quick-stat-card .stat-icon {
        width: 40px;
        height: 40px;
        background: #0073aa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }
    
    .quick-stat-card .stat-number {
        font-size: 20px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
    }
    
    .quick-stat-card .stat-label {
        font-size: 12px;
        color: #646970;
        margin-top: 2px;
    }
    
    .adhesion-table-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    @media (max-width: 768px) {
        .adhesion-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .quick-stat-card {
            padding: 12px;
        }
        
        .quick-stat-card .stat-number {
            font-size: 16px;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-submit del formulario de filtros cuando cambian los selects
        $('#filter-user, input[name="date_from"], input[name="date_to"]').on('change', function() {
            $('#calculations-filter').submit();
        });
        
        // Confirmación para eliminaciones
        $('.delete-calculation').on('click', function(e) {
            if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres eliminar este cálculo?', 'adhesion')); ?>')) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * Mostrar detalle de un cálculo específico
 */
function adhesion_display_calculation_detail($calculation_id, $db) {
    $calculation = $db->get_calculation($calculation_id);
    
    if (!$calculation) {
        ?>
        <div class="wrap">
            <h1><?php _e('Cálculo no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El cálculo solicitado no existe o ha sido eliminado.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                <?php _e('← Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener información adicional
    $user = get_userdata($calculation['user_id']);
    $contracts = $db->get_user_contracts($calculation['user_id']);
    $related_calculations = $db->get_user_calculations($calculation['user_id'], 5);
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php echo sprintf(__('Cálculo #%s', 'adhesion'), $calculation['id']); ?>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="page-title-action">
            <?php _e('← Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <div class="adhesion-calculation-detail">
            <div class="adhesion-detail-grid">
                <!-- Información del cálculo -->
                <div class="adhesion-card">
                    <div class="adhesion-card-header">
                        <h2><?php _e('Información del Cálculo', 'adhesion'); ?></h2>
                    </div>
                    <div class="adhesion-card-body">
                        <table class="adhesion-detail-table">
                            <tr>
                                <th><?php _e('ID:', 'adhesion'); ?></th>
                                <td><?php echo esc_html($calculation['id']); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Fecha de creación:', 'adhesion'); ?></th>
                                <td><?php echo adhesion_format_date($calculation['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Total toneladas:', 'adhesion'); ?></th>
                                <td><?php echo $calculation['total_tons'] ? adhesion_format_tons($calculation['total_tons']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Precio total:', 'adhesion'); ?></th>
                                <td><strong><?php echo adhesion_format_price($calculation['total_price']); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php _e('Precio por tonelada:', 'adhesion'); ?></th>
                                <td><?php echo $calculation['price_per_ton'] ? adhesion_format_price($calculation['price_per_ton']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Estado:', 'adhesion'); ?></th>
                                <td>
                                    <span class="adhesion-badge adhesion-badge-success">
                                        <?php echo ucfirst($calculation['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Información del usuario -->
                <div class="adhesion-card">
                    <div class="adhesion-card-header">
                        <h2><?php _e('Información del Usuario', 'adhesion'); ?></h2>
                    </div>
                    <div class="adhesion-card-body">
                        <?php if ($user): ?>
                        <table class="adhesion-detail-table">
                            <tr>
                                <th><?php _e('Nombre:', 'adhesion'); ?></th>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Email:', 'adhesion'); ?></th>
                                <td><a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a></td>
                            </tr>
                            <tr>
                                <th><?php _e('Rol:', 'adhesion'); ?></th>
                                <td><?php echo implode(', ', $user->roles); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Registrado:', 'adhesion'); ?></th>
                                <td><?php echo adhesion_format_date($user->user_registered); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Cálculos totales:', 'adhesion'); ?></th>
                                <td><?php echo count($related_calculations); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Contratos:', 'adhesion'); ?></th>
                                <td><?php echo count($contracts); ?></td>
                            </tr>
                        </table>
                        <?php else: ?>
                        <p class="adhesion-no-data"><?php _e('Usuario eliminado o no encontrado.', 'adhesion'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Detalle de materiales -->
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php _e('Detalle de Materiales', 'adhesion'); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <?php if (!empty($calculation['calculation_data']['materials'])): ?>
                    <div class="adhesion-materials-grid">
                        <?php foreach ($calculation['calculation_data']['materials'] as $material): ?>
                        <div class="material-detail-card">
                            <div class="material-type">
                                <strong><?php echo esc_html($material['type']); ?></strong>
                            </div>
                            <div class="material-quantity">
                                <?php echo adhesion_format_tons($material['quantity']); ?>
                            </div>
                            <?php if (isset($material['price_per_ton'])): ?>
                            <div class="material-price">
                                <?php echo adhesion_format_price($material['price_per_ton']); ?>/t
                            </div>
                            <?php endif; ?>
                            <?php if (isset($material['total'])): ?>
                            <div class="material-total">
                                <strong><?php echo adhesion_format_price($material['total']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="adhesion-no-data"><?php _e('No hay información detallada de materiales.', 'adhesion'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php _e('Acciones', 'adhesion'); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <div class="adhesion-actions-grid">
                        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=create&calculation=' . $calculation['id']); ?>" 
                           class="button button-primary">
                            <?php _e('Crear Contrato', 'adhesion'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $calculation['user_id']); ?>" 
                           class="button">
                            <?php _e('Ver Usuario', 'adhesion'); ?>
                        </a>
                        
                        <button type="button" class="button" onclick="adhesionExportCalculation(<?php echo $calculation['id']; ?>)">
                            <?php _e('Exportar Datos', 'adhesion'); ?>
                        </button>
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=adhesion-calculations&action=delete&calculation=' . $calculation['id']), 'delete_calculation_' . $calculation['id']); ?>" 
                           class="button button-link-delete"
                           onclick="return confirm('<?php echo esc_js(__('¿Estás seguro de que quieres eliminar este cálculo?', 'adhesion')); ?>')">
                            <?php _e('Eliminar', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .adhesion-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .adhesion-detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .adhesion-detail-table th,
    .adhesion-detail-table td {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f1;
        text-align: left;
    }
    
    .adhesion-detail-table th {
        width: 40%;
        font-weight: 600;
        color: #646970;
    }
    
    .adhesion-detail-table td {
        color: #1d2327;
    }
    
    .adhesion-materials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .material-detail-card {
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f9f9f9;
        text-align: center;
    }
    
    .material-type {
        font-size: 16px;
        margin-bottom: 8px;
        color: #0073aa;
    }
    
    .material-quantity {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 5px;
        color: #1d2327;
    }
    
    .material-price {
        font-size: 12px;
        color: #646970;
        margin-bottom: 5px;
    }
    
    .material-total {
        font-size: 14px;
        color: #00a32a;
    }
    
    .adhesion-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
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
        }
        
        .adhesion-materials-grid {
            grid-template-columns: 1fr;
        }
        
        .adhesion-actions-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script>
    function adhesionExportCalculation(calculationId) {
        // TODO: Implementar exportación individual
        alert('<?php echo esc_js(__('Funcionalidad de exportación individual en desarrollo.', 'adhesion')); ?>');
    }
    </script>
    <?php
}

/**
 * Manejar eliminación de cálculo
 */
function adhesion_handle_delete_calculation($calculation_id, $db) {
    // Verificar que no tenga contratos asociados
    global $wpdb;
    $has_contracts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE calculation_id = %d",
        $calculation_id
    ));
    
    if ($has_contracts > 0) {
        adhesion_add_notice(__('No se puede eliminar un cálculo que tiene contratos asociados.', 'adhesion'), 'error');
        return;
    }
    
    // Marcar como eliminado
    $result = $wpdb->update(
        $wpdb->prefix . 'adhesion_calculations',
        array('status' => 'deleted'),
        array('id' => $calculation_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        adhesion_add_notice(__('Cálculo eliminado correctamente.', 'adhesion'), 'success');
        adhesion_log("Cálculo $calculation_id eliminado por administrador", 'info');
    } else {
        adhesion_add_notice(__('Error al eliminar el cálculo.', 'adhesion'), 'error');
    }
}