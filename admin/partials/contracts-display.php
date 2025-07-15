<?php
/**
 * Vista del listado de contratos de adhesión
 * 
 * Esta vista maneja:
 * - Listado principal de contratos
 * - Vista detallada de un contrato específico
 * - Creación de nuevos contratos
 * - Edición de contratos existentes
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acción actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$contract_id = intval($_GET['contract'] ?? 0);
$calculation_id = intval($_GET['calculation'] ?? 0);

// Instanciar base de datos
$db = new Adhesion_Database();

// Manejar acciones específicas
switch ($action) {
    case 'view':
        if ($contract_id) {
            adhesion_display_contract_detail($contract_id, $db);
        } else {
            adhesion_display_contracts_list();
        }
        break;
        
    case 'create':
        adhesion_display_contract_create($calculation_id, $db);
        break;
        
    case 'edit':
        if ($contract_id) {
            adhesion_display_contract_edit($contract_id, $db);
        } else {
            adhesion_display_contracts_list();
        }
        break;
        
    default:
        adhesion_display_contracts_list();
        break;
}

/**
 * Mostrar listado principal de contratos
 */
function adhesion_display_contracts_list() {
    // Obtener estadísticas rápidas
    $db = new Adhesion_Database();
    $stats = $db->get_basic_stats();
    
    // Estadísticas específicas de contratos
    global $wpdb;
    
    $contract_stats = array();
    $contract_stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'pending'");
    $contract_stats['sent'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'sent'");
    $contract_stats['signed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'signed'");
    $contract_stats['completed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE status = 'completed'");
    
    // Estadísticas de completado por fecha
    $monthly_stats = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM {$wpdb->prefix}adhesion_contracts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Contratos de Adhesión', 'adhesion'); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=create'); ?>" class="page-title-action">
            <?php _e('Añadir nuevo', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <!-- Estadísticas de contratos -->
        <div class="contracts-stats-grid">
            <div class="contracts-stat-card pending">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['pending']); ?></div>
                    <div class="stat-label"><?php _e('Pendientes', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&status=pending'); ?>" class="button button-small">
                        <?php _e('Ver', 'adhesion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contracts-stat-card sent">
                <div class="stat-icon">📤</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['sent']); ?></div>
                    <div class="stat-label"><?php _e('Enviados', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&status=sent'); ?>" class="button button-small">
                        <?php _e('Ver', 'adhesion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contracts-stat-card signed">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['signed']); ?></div>
                    <div class="stat-label"><?php _e('Firmados', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&status=signed'); ?>" class="button button-small">
                        <?php _e('Ver', 'adhesion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contracts-stat-card completed">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($contract_stats['completed']); ?></div>
                    <div class="stat-label"><?php _e('Completados', 'adhesion'); ?></div>
                </div>
                <div class="stat-action">
                    <span class="stat-percentage">
                        <?php 
                        $total_contracts = array_sum($contract_stats);
                        if ($total_contracts > 0) {
                            echo round(($contract_stats['completed'] / $total_contracts) * 100, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Tabla de contratos -->
        <form method="get" id="contracts-filter">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="status" id="filter-status">
                        <option value=""><?php _e('Todos los estados', 'adhesion'); ?></option>
                        <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('Pendientes', 'adhesion'); ?></option>
                        <option value="sent" <?php selected($_GET['status'] ?? '', 'sent'); ?>><?php _e('Enviados', 'adhesion'); ?></option>
                        <option value="signed" <?php selected($_GET['status'] ?? '', 'signed'); ?>><?php _e('Firmados', 'adhesion'); ?></option>
                        <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Completados', 'adhesion'); ?></option>
                    </select>
                    
                    <select name="payment_status" id="filter-payment-status">
                        <option value=""><?php _e('Todos los pagos', 'adhesion'); ?></option>
                        <option value="pending" <?php selected($_GET['payment_status'] ?? '', 'pending'); ?>><?php _e('Pago pendiente', 'adhesion'); ?></option>
                        <option value="paid" <?php selected($_GET['payment_status'] ?? '', 'paid'); ?>><?php _e('Pagado', 'adhesion'); ?></option>
                        <option value="failed" <?php selected($_GET['payment_status'] ?? '', 'failed'); ?>><?php _e('Pago fallido', 'adhesion'); ?></option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" placeholder="<?php _e('Fecha desde', 'adhesion'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="<?php _e('Fecha hasta', 'adhesion'); ?>">
                    
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Filtrar', 'adhesion'); ?>">
                </div>
                
                <div class="alignright actions">
                    <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="<?php _e('Buscar contratos...', 'adhesion'); ?>">
                    <input type="submit" id="search-submit" class="button" value="<?php _e('Buscar', 'adhesion'); ?>">
                </div>
            </div>
        </form>
        
        <!-- Lista de contratos recientes -->
        <?php
        // Obtener contratos con filtros aplicados
        $contracts = adhesion_get_contracts_with_filters();
        
        if (empty($contracts)) {
            ?>
            <div class="notice notice-info">
                <p><?php _e('No se encontraron contratos que coincidan con los filtros aplicados.', 'adhesion'); ?></p>
            </div>
            <?php
        } else {
            adhesion_display_contracts_table($contracts);
        }
        ?>
    </div>
    
    <style>
    .contracts-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .contracts-stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        position: relative;
        overflow: hidden;
    }
    
    .contracts-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .contracts-stat-card.pending::before { background: #dba617; }
    .contracts-stat-card.sent::before { background: #0073aa; }
    .contracts-stat-card.signed::before { background: #00a32a; }
    .contracts-stat-card.completed::before { background: #8c8f94; }
    
    .contracts-stat-card .stat-icon {
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
    
    .contracts-stat-card.pending .stat-icon { background: #dba617; }
    .contracts-stat-card.sent .stat-icon { background: #0073aa; }
    .contracts-stat-card.signed .stat-icon { background: #00a32a; }
    .contracts-stat-card.completed .stat-icon { background: #8c8f94; }
    
    .contracts-stat-card .stat-content {
        flex: 1;
    }
    
    .contracts-stat-card .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #1d2327;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .contracts-stat-card .stat-label {
        font-size: 14px;
        color: #646970;
        font-weight: 500;
    }
    
    .contracts-stat-card .stat-action {
        flex-shrink: 0;
    }
    
    .stat-percentage {
        font-size: 16px;
        font-weight: bold;
        color: #646970;
    }
    
    @media (max-width: 768px) {
        .contracts-stat-card {
            padding: 15px;
        }
        
        .contracts-stat-card .stat-number {
            font-size: 20px;
        }
        
        .contracts-stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
    }
    </style>
    <?php
}

/**
 * Mostrar formulario de creación de contrato
 */
function adhesion_display_contract_create($calculation_id, $db) {
    $calculation = null;
    if ($calculation_id) {
        $calculation = $db->get_calculation($calculation_id);
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Crear Nuevo Contrato', 'adhesion'); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Funcionalidad de creación de contratos en desarrollo. Por ahora, los contratos se crean automáticamente desde el proceso de adhesión del frontend.', 'adhesion'); ?></p>
        </div>
        
        <?php if ($calculation): ?>
        <div class="notice notice-success">
            <p>
                <?php echo sprintf(__('Contrato basado en el cálculo #%s de %s', 'adhesion'), $calculation['id'], adhesion_format_price($calculation['total_price'])); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
            <?php _e('← Volver al listado', 'adhesion'); ?>
        </a>
    </div>
    <?php
}

/**
 * Mostrar formulario de edición de contrato
 */
function adhesion_display_contract_edit($contract_id, $db) {
    $contract = $db->get_contract($contract_id);
    
    if (!$contract) {
        ?>
        <div class="wrap">
            <h1><?php _e('Contrato no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El contrato solicitado no existe.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                <?php _e('← Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo sprintf(__('Editar Contrato %s', 'adhesion'), $contract['contract_number']); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Funcionalidad de edición de contratos en desarrollo.', 'adhesion'); ?></p>
        </div>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract_id); ?>" class="button">
            <?php _e('← Ver contrato', 'adhesion'); ?>
        </a>
    </div>
    <?php
}

/**
 * Obtener contratos con filtros aplicados
 */
function adhesion_get_contracts_with_filters() {
    global $wpdb;
    
    $where_conditions = array("1=1");
    $query_params = array();
    
    // Filtro por estado
    if (!empty($_GET['status'])) {
        $where_conditions[] = "status = %s";
        $query_params[] = sanitize_text_field($_GET['status']);
    }
    
    // Filtro por estado de pago
    if (!empty($_GET['payment_status'])) {
        $where_conditions[] = "payment_status = %s";
        $query_params[] = sanitize_text_field($_GET['payment_status']);
    }
    
    // Filtro por rango de fechas
    if (!empty($_GET['date_from'])) {
        $where_conditions[] = "DATE(created_at) >= %s";
        $query_params[] = sanitize_text_field($_GET['date_from']);
    }
    
    if (!empty($_GET['date_to'])) {
        $where_conditions[] = "DATE(created_at) <= %s";
        $query_params[] = sanitize_text_field($_GET['date_to']);
    }
    
    // Filtro de búsqueda
    if (!empty($_GET['s'])) {
        $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
        $where_conditions[] = "(contract_number LIKE %s OR client_name LIKE %s OR client_email LIKE %s)";
        $query_params[] = $search;
        $query_params[] = $search;
        $query_params[] = $search;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT * FROM {$wpdb->prefix}adhesion_contracts WHERE $where_clause ORDER BY created_at DESC LIMIT 50";
    
    if (!empty($query_params)) {
        $query = $wpdb->prepare($query, $query_params);
    }
    
    return $wpdb->get_results($query, ARRAY_A);
}

/**
 * Mostrar tabla de contratos
 */
function adhesion_display_contracts_table($contracts) {
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('Número', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Cliente', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Estado', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Pago', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Importe', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Fecha', 'adhesion'); ?></th>
                <th scope="col"><?php _e('Acciones', 'adhesion'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contracts as $contract): ?>
            <tr>
                <td>
                    <strong>
                        <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']); ?>">
                            <?php echo esc_html($contract['contract_number'] ?? 'Sin número'); ?>
                        </a>
                    </strong>
                </td>
                <td>
                    <div>
                        <strong><?php echo esc_html($contract['client_name'] ?? 'Sin nombre'); ?></strong><br>
                        <small><?php echo esc_html($contract['client_email'] ?? ''); ?></small>
                    </div>
                </td>
                <td>
                    <?php echo adhesion_get_status_badge($contract['status']); ?>
                </td>
                <td>
                    <?php echo adhesion_get_payment_status_badge($contract['payment_status']); ?>
                </td>
                <td>
                    <strong><?php echo adhesion_format_price($contract['total_amount'] ?? 0); ?></strong>
                </td>
                <td>
                    <?php echo adhesion_format_date($contract['created_at'], 'd/m/Y'); ?>
                </td>
                <td>
                    <div class="row-actions">
                        <span class="view">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']); ?>">
                                <?php _e('Ver', 'adhesion'); ?>
                            </a>
                        </span>
                        
                        <?php if ($contract['status'] === 'pending'): ?>
                        | <span class="send">
                            <a href="#" onclick="adhesionSendToDocusign(<?php echo $contract['id']; ?>)">
                                <?php _e('Enviar a firma', 'adhesion'); ?>
                            </a>
                        </span>
                        <?php endif; ?>
                        
                        | <span class="edit">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=edit&contract=' . $contract['id']); ?>">
                                <?php _e('Editar', 'adhesion'); ?>
                            </a>
                        </span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
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
        
        jQuery.post(ajaxurl, {
            action: 'adhesion_send_contract_docusign',
            contract_id: contractId,
            nonce: '<?php echo wp_create_nonce('adhesion_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Contrato enviado correctamente', 'adhesion')); ?>');
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error al enviar contrato: ', 'adhesion')); ?>' + response.data);
            }
        });
    }
    </script>
    <?php
}

/**
 * Mostrar detalle de un contrato específico
 */
function adhesion_display_contract_detail($contract_id, $db) {
    $contract = $db->get_contract($contract_id);
    
    if (!$contract) {
        ?>
        <div class="wrap">
            <h1><?php _e('Contrato no encontrado', 'adhesion'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('El contrato solicitado no existe o ha sido eliminado.', 'adhesion'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                <?php _e('← Volver al listado', 'adhesion'); ?>
            </a>
        </div>
        <?php
        return;
    }
    
    // Obtener información adicional
    $user = get_userdata($contract['user_id']);
    $calculation = $contract['calculation_id'] ? $db->get_calculation($contract['calculation_id']) : null;
    
    ?>
    <div class="wrap">
        <h1><?php echo sprintf(__('Contrato %s', 'adhesion'), esc_html($contract['contract_number'])); ?></h1>
        
        <div class="contract-detail-header">
            <div class="contract-status-info">
                <div class="status-badge">
                    <?php echo adhesion_get_status_badge($contract['status']); ?>
                </div>
                <div class="payment-badge">
                    <?php echo adhesion_get_payment_status_badge($contract['payment_status']); ?>
                </div>
            </div>
            
            <div class="contract-actions">
                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="button">
                    <?php _e('← Volver al listado', 'adhesion'); ?>
                </a>
                
                <?php if ($contract['status'] === 'pending'): ?>
                <button type="button" class="button button-primary" onclick="adhesionSendToDocusign(<?php echo $contract['id']; ?>)">
                    <?php _e('Enviar a DocuSign', 'adhesion'); ?>
                </button>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=adhesion-contracts&action=edit&contract=' . $contract['id']); ?>" class="button">
                    <?php _e('Editar', 'adhesion'); ?>
                </a>
            </div>
        </div>
        
        <div class="contract-detail-content">
            <!-- Información del cliente -->
            <div class="detail-section">
                <h3><?php _e('Información del cliente', 'adhesion'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Nombre:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['client_name'] ?? ($user ? $user->display_name : 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['client_email'] ?? ($user ? $user->user_email : 'N/A')); ?></td>
                    </tr>
                    <?php if (!empty($contract['client_phone'])): ?>
                    <tr>
                        <th><?php _e('Teléfono:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['client_phone']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Información del contrato -->
            <div class="detail-section">
                <h3><?php _e('Detalles del contrato', 'adhesion'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Número de contrato:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['contract_number']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Fecha de creación:', 'adhesion'); ?></th>
                        <td><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y H:i'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Importe total:', 'adhesion'); ?></th>
                        <td><strong><?php echo adhesion_format_price($contract['total_amount']); ?></strong></td>
                    </tr>
                    <?php if ($contract['signed_at']): ?>
                    <tr>
                        <th><?php _e('Fecha de firma:', 'adhesion'); ?></th>
                        <td><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Información del cálculo asociado -->
            <?php if ($calculation): ?>
            <div class="detail-section">
                <h3><?php _e('Cálculo asociado', 'adhesion'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID del cálculo:', 'adhesion'); ?></th>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&action=view&calculation=' . $calculation['id']); ?>">
                                #<?php echo esc_html($calculation['id']); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Total toneladas:', 'adhesion'); ?></th>
                        <td><?php echo number_format($calculation['total_tons'], 2, ',', '.'); ?> t</td>
                    </tr>
                    <tr>
                        <th><?php _e('Precio total:', 'adhesion'); ?></th>
                        <td><?php echo adhesion_format_price($calculation['total_price']); ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Documentos y firma -->
            <div class="detail-section">
                <h3><?php _e('Documentos y firma', 'adhesion'); ?></h3>
                <table class="form-table">
                    <?php if (!empty($contract['docusign_envelope_id'])): ?>
                    <tr>
                        <th><?php _e('DocuSign Envelope ID:', 'adhesion'); ?></th>
                        <td><?php echo esc_html($contract['docusign_envelope_id']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($contract['signed_document_url'])): ?>
                    <tr>
                        <th><?php _e('Documento firmado:', 'adhesion'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($contract['signed_document_url']); ?>" target="_blank" class="button">
                                <?php _e('Descargar documento', 'adhesion'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Historial de actividad -->
            <div class="detail-section">
                <h3><?php _e('Historial de actividad', 'adhesion'); ?></h3>
                <div class="activity-timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Contrato creado', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    
                    <?php if ($contract['sent_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker sent"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Enviado para firma', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['sent_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($contract['signed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker signed"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Firmado por el cliente', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($contract['completed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker completed"></div>
                        <div class="timeline-content">
                            <strong><?php _e('Proceso completado', 'adhesion'); ?></strong><br>
                            <small><?php echo adhesion_format_date($contract['completed_at'], 'd/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .contract-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
        padding: 20px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .contract-status-info {
        display: flex;
        gap: 10px;
    }
    
    .contract-actions {
        display: flex;
        gap: 10px;
    }
    
    .contract-detail-content {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .detail-section {
        padding: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .detail-section:last-child {
        border-bottom: none;
    }
    
    .detail-section h3 {
        margin: 0 0 15px 0;
        color: #1d2327;
        font-size: 16px;
    }
    
    .activity-timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .activity-timeline::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #ddd;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    
    .timeline-marker {
        position: absolute;
        left: -18px;
        top: 3px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0073aa;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #ddd;
    }
    
    .timeline-marker.sent { background: #dba617; }
    .timeline-marker.signed { background: #00a32a; }
    .timeline-marker.completed { background: #8c8f94; }
    
    .timeline-content strong {
        color: #1d2327;
    }
    
    .timeline-content small {
        color: #646970;
    }
    
    @media (max-width: 768px) {
        .contract-detail-header {
            flex-direction: column;
            gap: 15px;
            align-items: stretch;
        }
        
        .contract-actions {
            justify-content: center;
        }
    }
    </style>
    
    <script>
    // Funciones AJAX para acciones de contratos
    function adhesionSendToDocusign(contractId) {
        if (!confirm('<?php echo esc_js(__('¿Enviar este contrato a DocuSign para firma?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.post(ajaxurl, {
            action: 'adhesion_send_contract_docusign',
            contract_id: contractId,
            nonce: '<?php echo wp_create_nonce('adhesion_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Contrato enviado correctamente', 'adhesion')); ?>');
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error al enviar contrato: ', 'adhesion')); ?>' + response.data);
            }
        });
    }
    </script>
    <?php
}

/**
 * Funciones auxiliares para badges de estado
 */
function adhesion_get_status_badge($status) {
    $badges = array(
        'pending' => '<span class="status-badge pending">' . __('Pendiente', 'adhesion') . '</span>',
        'sent' => '<span class="status-badge sent">' . __('Enviado', 'adhesion') . '</span>',
        'signed' => '<span class="status-badge signed">' . __('Firmado', 'adhesion') . '</span>',
        'completed' => '<span class="status-badge completed">' . __('Completado', 'adhesion') . '</span>',
        'cancelled' => '<span class="status-badge cancelled">' . __('Cancelado', 'adhesion') . '</span>'
    );
    
    return $badges[$status] ?? '<span class="status-badge unknown">' . esc_html($status) . '</span>';
}

function adhesion_get_payment_status_badge($payment_status) {
    $badges = array(
        'pending' => '<span class="payment-badge pending">' . __('Pendiente', 'adhesion') . '</span>',
        'paid' => '<span class="payment-badge paid">' . __('Pagado', 'adhesion') . '</span>',
        'failed' => '<span class="payment-badge failed">' . __('Fallido', 'adhesion') . '</span>',
        'refunded' => '<span class="payment-badge refunded">' . __('Reembolsado', 'adhesion') . '</span>'
    );
    
    return $badges[$payment_status] ?? '<span class="payment-badge unknown">' . esc_html($payment_status) . '</span>';
}

// Estilos CSS para los badges
add_action('admin_head', function() {
    ?>
    <style>
    .status-badge, .payment-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge.pending { background: #fff3cd; color: #856404; }
    .status-badge.sent { background: #cce5ff; color: #004085; }
    .status-badge.signed { background: #d4edda; color: #155724; }
    .status-badge.completed { background: #e2e3e5; color: #383d41; }
    .status-badge.cancelled { background: #f8d7da; color: #721c24; }
    .status-badge.unknown { background: #ffeeba; color: #856404; }
    
    .payment-badge.pending { background: #fff3cd; color: #856404; }
    .payment-badge.paid { background: #d4edda; color: #155724; }
    .payment-badge.failed { background: #f8d7da; color: #721c24; }
    .payment-badge.refunded { background: #e2e3e5; color: #383d41; }
    .payment-badge.unknown { background: #ffeeba; color: #856404; }
    </style>
    <?php
});

?>