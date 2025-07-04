<?php
/**
 * Vista del dashboard de cuenta de usuario
 * Archivo: public/partials/user-account-display.php
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario esté logueado
if (!is_user_logged_in()) {
    echo '<p>' . __('Debes iniciar sesión para acceder a tu cuenta.', 'adhesion') . '</p>';
    return;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Verificar que tenga el rol correcto
if (!in_array('adhesion_client', $current_user->roles) && !current_user_can('manage_options')) {
    echo '<p>' . __('No tienes permisos para acceder a esta área.', 'adhesion') . '</p>';
    return;
}

// Obtener estadísticas del usuario
global $wpdb;

$calculations_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculations WHERE user_id = %d",
    $user_id
));

$contracts_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d",
    $user_id
));

$pending_contracts = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'pending'",
    $user_id
));

$signed_contracts = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_contracts WHERE user_id = %d AND status = 'signed'",
    $user_id
));

// Obtener últimos cálculos
$recent_calculations = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}adhesion_calculations 
     WHERE user_id = %d 
     ORDER BY created_at DESC 
     LIMIT 5",
    $user_id
));

// Obtener contratos recientes
$recent_contracts = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}adhesion_contracts 
     WHERE user_id = %d 
     ORDER BY created_at DESC 
     LIMIT 5",
    $user_id
));
?>

<div class="adhesion-user-account">
    <!-- Cabecera del dashboard -->
    <div class="adhesion-account-header">
        <div class="user-welcome">
            <h1><?php printf(__('Bienvenido, %s', 'adhesion'), esc_html($current_user->display_name)); ?></h1>
            <p class="user-info">
                <?php printf(__('Miembro desde: %s', 'adhesion'), 
                    date_i18n(get_option('date_format'), strtotime($current_user->user_registered))); ?>
            </p>
        </div>
        
        <div class="account-actions">
            <a href="#" class="button button-primary" id="nueva-calculadora">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('Nueva Calculadora', 'adhesion'); ?>
            </a>
        </div>
    </div>

    <!-- Estadísticas generales -->
    <div class="adhesion-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-calculator"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($calculations_count); ?></span>
                <span class="stat-label"><?php _e('Cálculos Realizados', 'adhesion'); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($contracts_count); ?></span>
                <span class="stat-label"><?php _e('Contratos Totales', 'adhesion'); ?></span>
            </div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($pending_contracts); ?></span>
                <span class="stat-label"><?php _e('Pendientes de Firma', 'adhesion'); ?></span>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo intval($signed_contracts); ?></span>
                <span class="stat-label"><?php _e('Contratos Firmados', 'adhesion'); ?></span>
            </div>
        </div>
    </div>

    <!-- Contenido principal con pestañas -->
    <div class="adhesion-account-tabs">
        <nav class="tab-navigation">
            <button class="tab-button active" data-tab="overview">
                <span class="dashicons dashicons-dashboard"></span>
                <?php _e('Resumen', 'adhesion'); ?>
            </button>
            <button class="tab-button" data-tab="calculations">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('Mis Cálculos', 'adhesion'); ?>
            </button>
            <button class="tab-button" data-tab="contracts">
                <span class="dashicons dashicons-media-document"></span>
                <?php _e('Mis Contratos', 'adhesion'); ?>
            </button>
            <button class="tab-button" data-tab="profile">
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('Mi Perfil', 'adhesion'); ?>
            </button>
        </nav>

        <!-- Tab Resumen -->
        <div class="tab-content active" id="tab-overview">
            <div class="adhesion-grid">
                <!-- Últimos cálculos -->
                <div class="adhesion-card">
                    <div class="card-header">
                        <h3><?php _e('Últimos Cálculos', 'adhesion'); ?></h3>
                        <a href="#" class="view-all" data-tab-link="calculations">
                            <?php _e('Ver todos', 'adhesion'); ?>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_calculations)) : ?>
                            <div class="calculations-list">
                                <?php foreach ($recent_calculations as $calc) : ?>
                                    <div class="calculation-item">
                                        <div class="calc-info">
                                            <strong><?php echo esc_html($calc->material_type); ?></strong>
                                            <span class="calc-details">
                                                <?php echo number_format($calc->quantity, 0, ',', '.'); ?> kg
                                                - <?php echo number_format($calc->total_price, 2, ',', '.'); ?>€
                                            </span>
                                            <span class="calc-date">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($calc->created_at)); ?>
                                            </span>
                                        </div>
                                        <div class="calc-actions">
                                            <button class="button-small view-calc" data-calc-id="<?php echo $calc->id; ?>">
                                                <?php _e('Ver', 'adhesion'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="no-data"><?php _e('No hay cálculos realizados aún.', 'adhesion'); ?></p>
                            <a href="#" class="button button-primary" id="primera-calculadora">
                                <?php _e('Realizar Primera Calculadora', 'adhesion'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estado de contratos -->
                <div class="adhesion-card">
                    <div class="card-header">
                        <h3><?php _e('Estado de Contratos', 'adhesion'); ?></h3>
                        <a href="#" class="view-all" data-tab-link="contracts">
                            <?php _e('Ver todos', 'adhesion'); ?>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_contracts)) : ?>
                            <div class="contracts-list">
                                <?php foreach ($recent_contracts as $contract) : ?>
                                    <div class="contract-item">
                                        <div class="contract-info">
                                            <strong><?php echo esc_html($contract->contract_type); ?></strong>
                                            <span class="contract-status status-<?php echo esc_attr($contract->status); ?>">
                                                <?php 
                                                switch($contract->status) {
                                                    case 'pending':
                                                        _e('Pendiente de Firma', 'adhesion');
                                                        break;
                                                    case 'signed':
                                                        _e('Firmado', 'adhesion');
                                                        break;
                                                    case 'cancelled':
                                                        _e('Cancelado', 'adhesion');
                                                        break;
                                                    default:
                                                        echo esc_html($contract->status);
                                                }
                                                ?>
                                            </span>
                                            <span class="contract-date">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($contract->created_at)); ?>
                                            </span>
                                        </div>
                                        <div class="contract-actions">
                                            <?php if ($contract->status === 'pending') : ?>
                                                <button class="button button-primary sign-contract" 
                                                        data-contract-id="<?php echo $contract->id; ?>">
                                                    <?php _e('Firmar', 'adhesion'); ?>
                                                </button>
                                            <?php elseif ($contract->status === 'signed' && !empty($contract->signed_document_url)) : ?>
                                                <a href="<?php echo esc_url($contract->signed_document_url); ?>" 
                                                   class="button" target="_blank">
                                                    <?php _e('Descargar', 'adhesion'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="no-data"><?php _e('No hay contratos registrados.', 'adhesion'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Cálculos -->
        <div class="tab-content" id="tab-calculations">
            <div class="tab-header">
                <h2><?php _e('Mis Cálculos de Presupuesto', 'adhesion'); ?></h2>
                <button class="button button-primary" id="nueva-calculadora-tab">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Nuevo Cálculo', 'adhesion'); ?>
                </button>
            </div>
            
            <div class="calculations-table-container">
                <table class="adhesion-table calculations-table">
                    <thead>
                        <tr>
                            <th><?php _e('Fecha', 'adhesion'); ?></th>
                            <th><?php _e('Material', 'adhesion'); ?></th>
                            <th><?php _e('Cantidad', 'adhesion'); ?></th>
                            <th><?php _e('Precio Total', 'adhesion'); ?></th>
                            <th><?php _e('Estado', 'adhesion'); ?></th>
                            <th><?php _e('Acciones', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_calculations)) : ?>
                            <?php foreach ($recent_calculations as $calc) : ?>
                                <tr>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($calc->created_at)); ?></td>
                                    <td><?php echo esc_html($calc->material_type); ?></td>
                                    <td><?php echo number_format($calc->quantity, 0, ',', '.'); ?> kg</td>
                                    <td><?php echo number_format($calc->total_price, 2, ',', '.'); ?>€</td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($calc->status ?? 'calculated'); ?>">
                                            <?php 
                                            switch($calc->status ?? 'calculated') {
                                                case 'calculated':
                                                    _e('Calculado', 'adhesion');
                                                    break;
                                                case 'contracted':
                                                    _e('Contratado', 'adhesion');
                                                    break;
                                                default:
                                                    echo esc_html($calc->status);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="button-small view-calc-details" data-calc-id="<?php echo $calc->id; ?>">
                                            <?php _e('Ver Detalles', 'adhesion'); ?>
                                        </button>
                                        <?php if (($calc->status ?? 'calculated') === 'calculated') : ?>
                                            <button class="button-small button-primary contract-calc" data-calc-id="<?php echo $calc->id; ?>">
                                                <?php _e('Contratar', 'adhesion'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <?php _e('No hay cálculos realizados aún.', 'adhesion'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Contratos -->
        <div class="tab-content" id="tab-contracts">
            <div class="tab-header">
                <h2><?php _e('Mis Contratos', 'adhesion'); ?></h2>
            </div>
            
            <div class="contracts-table-container">
                <table class="adhesion-table contracts-table">
                    <thead>
                        <tr>
                            <th><?php _e('Fecha', 'adhesion'); ?></th>
                            <th><?php _e('Tipo', 'adhesion'); ?></th>
                            <th><?php _e('Estado', 'adhesion'); ?></th>
                            <th><?php _e('Monto', 'adhesion'); ?></th>
                            <th><?php _e('Firma', 'adhesion'); ?></th>
                            <th><?php _e('Acciones', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_contracts)) : ?>
                            <?php foreach ($recent_contracts as $contract) : ?>
                                <tr>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($contract->created_at)); ?></td>
                                    <td><?php echo esc_html($contract->contract_type); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($contract->status); ?>">
                                            <?php 
                                            switch($contract->status) {
                                                case 'pending':
                                                    _e('Pendiente', 'adhesion');
                                                    break;
                                                case 'signed':
                                                    _e('Firmado', 'adhesion');
                                                    break;
                                                case 'cancelled':
                                                    _e('Cancelado', 'adhesion');
                                                    break;
                                                default:
                                                    echo esc_html($contract->status);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($contract->amount) ? number_format($contract->amount, 2, ',', '.') . '€' : '-'; ?></td>
                                    <td>
                                        <?php if (!empty($contract->signed_at)) : ?>
                                            <?php echo date_i18n(get_option('date_format'), strtotime($contract->signed_at)); ?>
                                        <?php else : ?>
                                            <span class="pending-text"><?php _e('Pendiente', 'adhesion'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($contract->status === 'pending') : ?>
                                            <button class="button button-primary sign-contract-btn" 
                                                    data-contract-id="<?php echo $contract->id; ?>">
                                                <?php _e('Firmar Ahora', 'adhesion'); ?>
                                            </button>
                                        <?php elseif ($contract->status === 'signed' && !empty($contract->signed_document_url)) : ?>
                                            <a href="<?php echo esc_url($contract->signed_document_url); ?>" 
                                               class="button" target="_blank">
                                                <span class="dashicons dashicons-download"></span>
                                                <?php _e('Descargar', 'adhesion'); ?>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="button-small view-contract-details" data-contract-id="<?php echo $contract->id; ?>">
                                            <?php _e('Ver Detalles', 'adhesion'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <?php _e('No hay contratos registrados.', 'adhesion'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Perfil -->
        <div class="tab-content" id="tab-profile">
            <div class="tab-header">
                <h2><?php _e('Mi Perfil', 'adhesion'); ?></h2>
            </div>
            
            <form id="adhesion-profile-form" class="adhesion-form">
                <?php wp_nonce_field('adhesion_update_profile', 'adhesion_profile_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('Información Personal', 'adhesion'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="display_name"><?php _e('Nombre Completo', 'adhesion'); ?></label>
                            <input type="text" id="display_name" name="display_name" 
                                   value="<?php echo esc_attr($current_user->display_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_email"><?php _e('Correo Electrónico', 'adhesion'); ?></label>
                            <input type="email" id="user_email" name="user_email" 
                                   value="<?php echo esc_attr($current_user->user_email); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone"><?php _e('Teléfono', 'adhesion'); ?></label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'phone', true)); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company"><?php _e('Empresa', 'adhesion'); ?></label>
                            <input type="text" id="company" name="company" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'company', true)); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Dirección', 'adhesion'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="address"><?php _e('Dirección', 'adhesion'); ?></label>
                            <input type="text" id="address" name="address" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'address', true)); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city"><?php _e('Ciudad', 'adhesion'); ?></label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'city', true)); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code"><?php _e('Código Postal', 'adhesion'); ?></label>
                            <input type="text" id="postal_code" name="postal_code" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'postal_code', true)); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="country"><?php _e('País', 'adhesion'); ?></label>
                            <input type="text" id="country" name="country" 
                                   value="<?php echo esc_attr(get_user_meta($user_id, 'country', true)); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Cambiar Contraseña', 'adhesion'); ?></h3>
                    <p class="description"><?php _e('Deja en blanco si no quieres cambiar la contraseña.', 'adhesion'); ?></p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password"><?php _e('Contraseña Actual', 'adhesion'); ?></label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password"><?php _e('Nueva Contraseña', 'adhesion'); ?></label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><?php _e('Confirmar Contraseña', 'adhesion'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Actualizar Perfil', 'adhesion'); ?>
                    </button>
                    
                    <button type="button" class="button" id="cancel-profile-edit">
                        <?php _e('Cancelar', 'adhesion'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver detalles de cálculo -->
<div id="calculation-modal" class="adhesion-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Detalles del Cálculo', 'adhesion'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Contenido se carga dinámicamente -->
        </div>
    </div>
</div>

<!-- Modal para ver detalles de contrato -->
<div id="contract-modal" class="adhesion-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Detalles del Contrato', 'adhesion'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Contenido se carga dinámicamente -->
        </div>
    </div>
</div>