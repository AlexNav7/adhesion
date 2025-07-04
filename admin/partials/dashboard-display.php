<?php
/**
 * Vista del dashboard principal del admin
 * 
 * Muestra estadísticas, estado del sistema y accesos rápidos
 * Variables disponibles: $stats, $recent_stats, $recent_calculations, $config_status
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Dashboard de Adhesión', 'adhesion'); ?>
        <span class="title-count"><?php echo sprintf(__('v%s', 'adhesion'), ADHESION_PLUGIN_VERSION); ?></span>
    </h1>
    
    <hr class="wp-header-end">
    
    <?php
    // Mostrar notificaciones
    adhesion_display_notices();
    ?>
    
    <!-- Estado de configuración -->
    <?php if (!$config_status['redsys'] || !$config_status['docusign']): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('¡Configuración pendiente!', 'adhesion'); ?></strong>
            <?php if (!$config_status['redsys']): ?>
                <?php _e('Redsys no configurado.', 'adhesion'); ?>
            <?php endif; ?>
            <?php if (!$config_status['docusign']): ?>
                <?php _e('DocuSign no configurado.', 'adhesion'); ?>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>" class="button button-primary">
                <?php _e('Configurar ahora', 'adhesion'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Estadísticas principales -->
    <div class="adhesion-dashboard-stats">
        <div class="adhesion-stats-grid">
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calculator"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_calculations']); ?></div>
                    <div class="stat-label"><?php _e('Cálculos Totales', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <span class="stat-period"><?php echo sprintf(__('%d esta semana', 'adhesion'), $recent_stats['period_calculations']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_contracts']); ?></div>
                    <div class="stat-label"><?php _e('Contratos Totales', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <span class="stat-period"><?php echo sprintf(__('%d esta semana', 'adhesion'), $recent_stats['period_contracts']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['signed_contracts']); ?></div>
                    <div class="stat-label"><?php _e('Contratos Firmados', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <?php 
                        $conversion_rate = $stats['total_contracts'] > 0 ? ($stats['signed_contracts'] / $stats['total_contracts']) * 100 : 0;
                        ?>
                        <span class="stat-period"><?php echo sprintf(__('%.1f%% conversión', 'adhesion'), $conversion_rate); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="adhesion-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo adhesion_format_price($stats['total_revenue']); ?></div>
                    <div class="stat-label"><?php _e('Ingresos Totales', 'adhesion'); ?></div>
                    <div class="stat-change">
                        <span class="stat-period"><?php echo sprintf(__('%s esta semana', 'adhesion'), adhesion_format_price($recent_stats['period_revenue'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal del dashboard -->
    <div class="adhesion-dashboard-content">
        <div class="adhesion-dashboard-left">
            
            <!-- Actividad reciente -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Actividad Reciente', 'adhesion'); ?></h2>
                
                <?php if (!empty($recent_calculations)): ?>
                <div class="adhesion-activity-list">
                    <?php foreach ($recent_calculations as $calc): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <span class="dashicons dashicons-calculator"></span>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <strong><?php echo esc_html($calc['user_name'] ?: __('Usuario desconocido', 'adhesion')); ?></strong>
                                <?php _e('realizó un cálculo', 'adhesion'); ?>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-price"><?php echo adhesion_format_price($calc['total_price']); ?></span>
                                <span class="activity-date"><?php echo adhesion_format_date($calc['created_at'], 'd/m/Y H:i'); ?></span>
                            </div>
                        </div>
                        <div class="activity-actions">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-calculations&calculation_id=' . $calc['id']); ?>" class="button button-small">
                                <?php _e('Ver', 'adhesion'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="adhesion-section-footer">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="button">
                        <?php _e('Ver todos los cálculos', 'adhesion'); ?>
                    </a>
                </div>
                
                <?php else: ?>
                <div class="adhesion-empty-state">
                    <p><?php _e('No hay actividad reciente.', 'adhesion'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>" class="button button-primary">
                        <?php _e('Configurar plugin', 'adhesion'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Estado del sistema -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Estado del Sistema', 'adhesion'); ?></h2>
                
                <div class="adhesion-system-status">
                    <div class="status-item">
                        <span class="status-label"><?php _e('Calculadora:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo adhesion_get_setting('calculator_enabled') ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo adhesion_get_setting('calculator_enabled') ? __('Activa', 'adhesion') : __('Inactiva', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('Redsys:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo $config_status['redsys'] ? 'status-configured' : 'status-pending'; ?>">
                            <?php echo $config_status['redsys'] ? __('Configurado', 'adhesion') : __('Pendiente', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('DocuSign:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo $config_status['docusign'] ? 'status-configured' : 'status-pending'; ?>">
                            <?php echo $config_status['docusign'] ? __('Configurado', 'adhesion') : __('Pendiente', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('Tablas BD:', 'adhesion'); ?></span>
                        <span class="status-value <?php echo $this->db->tables_exist() ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $this->db->tables_exist() ? __('OK', 'adhesion') : __('Error', 'adhesion'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('Permisos:', 'adhesion'); ?></span>
                        <?php 
                        $upload_dir = wp_upload_dir();
                        $adhesion_dir = $upload_dir['basedir'] . '/adhesion/';
                        $writable = is_writable($adhesion_dir);
                        ?>
                        <span class="status-value <?php echo $writable ? 'status-ok' : 'status-warning'; ?>">
                            <?php echo $writable ? __('OK', 'adhesion') : __('Verificar', 'adhesion'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="adhesion-dashboard-right">
            
            <!-- Accesos rápidos -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Accesos Rápidos', 'adhesion'); ?></h2>
                
                <div class="adhesion-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=adhesion-calculations'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-calculator"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Ver Cálculos', 'adhesion'); ?></h3>
                            <p><?php _e('Gestionar todos los cálculos de presupuestos', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-contracts'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Ver Contratos', 'adhesion'); ?></h3>
                            <p><?php _e('Gestionar contratos de adhesión', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-users'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Ver Usuarios', 'adhesion'); ?></h3>
                            <p><?php _e('Gestionar usuarios adheridos', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-edit-page"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Editar Documentos', 'adhesion'); ?></h3>
                            <p><?php _e('Personalizar plantillas de contratos', 'adhesion'); ?></p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=adhesion-settings'); ?>" class="quick-action-card">
                        <div class="quick-action-icon">
                            <span class="dashicons dashicons-admin-settings"></span>
                        </div>
                        <div class="quick-action-content">
                            <h3><?php _e('Configuración', 'adhesion'); ?></h3>
                            <p><?php _e('Configurar APIs y opciones', 'adhesion'); ?></p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Información del plugin -->
            <div class="adhesion-dashboard-section">
                <h2><?php _e('Información del Plugin', 'adhesion'); ?></h2>
                
                <div class="adhesion-plugin-info">
                    <div class="info-item">
                        <span class="info-label"><?php _e('Versión:', 'adhesion'); ?></span>
                        <span class="info-value"><?php echo ADHESION_PLUGIN_VERSION; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><?php _e('WordPress:', 'adhesion'); ?></span>
                        <span class="info-value"><?php echo get_bloginfo('version'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><?php _e('PHP:', 'adhesion'); ?></span>
                        <span class="info-value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><?php _e('Última activación:', 'adhesion'); ?></span>
                        <span class="info-value">
                            <?php 
                            $activated = get_option('adhesion_activated');
                            echo $activated ? adhesion_format_date($activated, 'd/m/Y H:i') : __('Desconocida', 'adhesion');
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="adhesion-plugin-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('adhesion_admin_action', 'adhesion_nonce'); ?>
                        <input type="hidden" name="adhesion_action" value="clear_cache">
                        <button type="submit" class="button">
                            <?php _e('Limpiar Cache', 'adhesion'); ?>
                        </button>
                    </form>
                    
                    <a href="#" class="button" onclick="adhesionExportData(); return false;">
                        <?php _e('Exportar Datos', 'adhesion'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function adhesionExportData() {
    if (confirm('<?php echo esc_js(__('¿Estás seguro de que quieres exportar todos los datos?', 'adhesion')); ?>')) {
        // TODO: Implementar exportación
        alert('<?php echo esc_js(__('Funcionalidad en desarrollo', 'adhesion')); ?>');
    }
}
</script>

<style>
/* Estilos específicos para el dashboard */
.adhesion-dashboard-stats {
    margin: 20px 0;
}

.adhesion-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.adhesion-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: #0073aa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
    display: block;
}

.stat-change {
    margin-top: 5px;
}

.stat-period {
    font-size: 12px;
    color: #646970;
}

.adhesion-dashboard-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.adhesion-dashboard-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.adhesion-dashboard-section h2 {
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    font-size: 16px;
    font-weight: 600;
}

.adhesion-activity-list {
    padding: 0;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f1;
    gap: 15px;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: #f0f0f1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #646970;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-size: 14px;
    margin-bottom: 5px;
}

.activity-meta {
    font-size: 12px;
    color: #646970;
}

.activity-price {
    font-weight: bold;
    color: #1d2327;
    margin-right: 10px;
}

.adhesion-section-footer {
    padding: 15px 20px;
    border-top: 1px solid #f0f0f1;
    text-align: center;
}

.adhesion-empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #646970;
}

.adhesion-system-status {
    padding: 20px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 500;
}

.status-value {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-active, .status-ok, .status-configured {
    background: #d5e7d5;
    color: #1e4620;
}

.status-inactive, .status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
}

.adhesion-quick-actions {
    padding: 20px;
    display: grid;
    gap: 15px;
}

.quick-action-card {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: inherit;
    transition: border-color 0.2s;
    gap: 15px;
}

.quick-action-card:hover {
    border-color: #0073aa;
    text-decoration: none;
}

.quick-action-icon {
    width: 40px;
    height: 40px;
    background: #0073aa;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.quick-action-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
}

.quick-action-content p {
    margin: 0;
    font-size: 12px;
    color: #646970;
}

.adhesion-plugin-info {
    padding: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
}

.info-value {
    color: #646970;
}

.adhesion-plugin-actions {
    padding: 15px 20px;
    border-top: 1px solid #f0f0f1;
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .adhesion-dashboard-content {
        grid-template-columns: 1fr;
    }
    
    .adhesion-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>