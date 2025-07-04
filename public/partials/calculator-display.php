<?php
/**
 * Vista de la calculadora de presupuestos
 * 
 * Esta vista maneja:
 * - Formulario interactivo de materiales
 * - Cálculos en tiempo real
 * - Visualización de precios y descuentos
 * - Guardado de cálculos
 * - Responsive design
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario esté logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para acceder a la calculadora.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar Sesión', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Obtener datos necesarios
$db = new Adhesion_Database();
$materials = $db->get_calculator_prices();
$user = wp_get_current_user();

// Filtrar solo materiales activos
$active_materials = array_filter($materials, function($material) {
    return $material['is_active'];
});

// Configuración de la calculadora
$calculator_config = array(
    'tax_rate' => adhesion_get_option('tax_rate', 21),
    'currency' => adhesion_get_option('currency', 'EUR'),
    'minimum_order' => adhesion_get_option('minimum_order', 0),
    'max_quantity_per_material' => 1000,
    'apply_discounts' => adhesion_get_option('apply_discounts', true)
);
?>

<div class="adhesion-calculator-container" id="adhesion-calculator">
    
    <!-- Header de la calculadora -->
    <div class="adhesion-calculator-header">
        <h2 class="calculator-title">
            <span class="calculator-icon">📊</span>
            <?php echo esc_html($atts['title']); ?>
        </h2>
        <p class="calculator-description">
            <?php _e('Calcula el presupuesto para tus materiales de construcción. Los precios incluyen descuentos por volumen automáticos.', 'adhesion'); ?>
        </p>
    </div>

    <!-- Mensajes de estado -->
    <div id="adhesion-calculator-messages" class="adhesion-messages-container"></div>

    <!-- Formulario de la calculadora -->
    <form id="adhesion-calculator-form" class="adhesion-calculator-form">
        
        <!-- Información del usuario -->
        <div class="calculator-user-info">
            <div class="user-info-card">
                <h3><?php _e('Calculando para:', 'adhesion'); ?></h3>
                <p><strong><?php echo esc_html($user->display_name); ?></strong></p>
                <p><?php echo esc_html($user->user_email); ?></p>
            </div>
        </div>

        <!-- Sección de materiales -->
        <div class="calculator-materials-section">
            <h3 class="section-title">
                <span class="section-icon">🏗️</span>
                <?php _e('Selecciona tus materiales', 'adhesion'); ?>
            </h3>
            
            <div class="materials-container" id="materials-container">
                
                <?php if (empty($active_materials)): ?>
                    <div class="no-materials-message">
                        <p><?php _e('No hay materiales disponibles en este momento. Contacta con el administrador.', 'adhesion'); ?></p>
                    </div>
                <?php else: ?>
                    
                    <!-- Plantilla de material (se clona con JavaScript) -->
                    <div class="material-row-template" style="display: none;">
                        <div class="material-row" data-row-index="0">
                            <div class="material-row-header">
                                <h4 class="material-row-title"><?php _e('Material', 'adhesion'); ?> <span class="material-number">1</span></h4>
                                <button type="button" class="remove-material-btn" title="<?php _e('Eliminar material', 'adhesion'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            
                            <div class="material-row-content">
                                <div class="form-grid">
                                    
                                    <!-- Selector de material -->
                                    <div class="form-group">
                                        <label for="material_type_0"><?php _e('Tipo de Material', 'adhesion'); ?> *</label>
                                        <select name="materials[0][type]" id="material_type_0" class="material-type-select" required>
                                            <option value=""><?php _e('Selecciona un material...', 'adhesion'); ?></option>
                                            <?php foreach ($active_materials as $material): ?>
                                                <option value="<?php echo esc_attr($material['material_type']); ?>" 
                                                        data-price="<?php echo esc_attr($material['price_per_ton']); ?>"
                                                        data-minimum="<?php echo esc_attr($material['minimum_quantity']); ?>"
                                                        data-description="<?php echo esc_attr($material['description'] ?? ''); ?>">
                                                    <?php echo esc_html(ucfirst($material['material_type'])); ?>
                                                    (<?php echo adhesion_format_price($material['price_per_ton']); ?>/t)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Cantidad -->
                                    <div class="form-group">
                                        <label for="material_quantity_0"><?php _e('Cantidad (toneladas)', 'adhesion'); ?> *</label>
                                        <input type="number" 
                                               name="materials[0][quantity]" 
                                               id="material_quantity_0" 
                                               class="material-quantity-input"
                                               min="0.1" 
                                               step="0.1" 
                                               max="<?php echo $calculator_config['max_quantity_per_material']; ?>"
                                               placeholder="<?php _e('Ej: 25.5', 'adhesion'); ?>"
                                               required>
                                        <div class="input-help">
                                            <span class="minimum-quantity-text" style="display: none;">
                                                <?php _e('Mínimo:', 'adhesion'); ?> <span class="minimum-value">0</span>t
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Precio unitario (solo lectura) -->
                                    <div class="form-group">
                                        <label><?php _e('Precio por tonelada', 'adhesion'); ?></label>
                                        <div class="price-display">
                                            <span class="price-per-ton">--</span>
                                            <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Total del material -->
                                    <div class="form-group">
                                        <label><?php _e('Subtotal', 'adhesion'); ?></label>
                                        <div class="total-display">
                                            <span class="material-total">0,00</span>
                                            <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Alertas del material -->
                                <div class="material-alerts"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Primer material (visible por defecto) -->
                    <div class="material-row" data-row-index="0">
                        <div class="material-row-header">
                            <h4 class="material-row-title"><?php _e('Material', 'adhesion'); ?> <span class="material-number">1</span></h4>
                            <button type="button" class="remove-material-btn" title="<?php _e('Eliminar material', 'adhesion'); ?>" style="display: none;">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        
                        <div class="material-row-content">
                            <div class="form-grid">
                                
                                <!-- Selector de material -->
                                <div class="form-group">
                                    <label for="material_type_0"><?php _e('Tipo de Material', 'adhesion'); ?> *</label>
                                    <select name="materials[0][type]" id="material_type_0" class="material-type-select" required>
                                        <option value=""><?php _e('Selecciona un material...', 'adhesion'); ?></option>
                                        <?php foreach ($active_materials as $material): ?>
                                            <option value="<?php echo esc_attr($material['material_type']); ?>" 
                                                    data-price="<?php echo esc_attr($material['price_per_ton']); ?>"
                                                    data-minimum="<?php echo esc_attr($material['minimum_quantity']); ?>"
                                                    data-description="<?php echo esc_attr($material['description'] ?? ''); ?>">
                                                <?php echo esc_html(ucfirst($material['material_type'])); ?>
                                                (<?php echo adhesion_format_price($material['price_per_ton']); ?>/t)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Cantidad -->
                                <div class="form-group">
                                    <label for="material_quantity_0"><?php _e('Cantidad (toneladas)', 'adhesion'); ?> *</label>
                                    <input type="number" 
                                           name="materials[0][quantity]" 
                                           id="material_quantity_0" 
                                           class="material-quantity-input"
                                           min="0.1" 
                                           step="0.1" 
                                           max="<?php echo $calculator_config['max_quantity_per_material']; ?>"
                                           placeholder="<?php _e('Ej: 25.5', 'adhesion'); ?>"
                                           required>
                                    <div class="input-help">
                                        <span class="minimum-quantity-text" style="display: none;">
                                            <?php _e('Mínimo:', 'adhesion'); ?> <span class="minimum-value">0</span>t
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Precio unitario (solo lectura) -->
                                <div class="form-group">
                                    <label><?php _e('Precio por tonelada', 'adhesion'); ?></label>
                                    <div class="price-display">
                                        <span class="price-per-ton">--</span>
                                        <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Total del material -->
                                <div class="form-group">
                                    <label><?php _e('Subtotal', 'adhesion'); ?></label>
                                    <div class="total-display">
                                        <span class="material-total">0,00</span>
                                        <span class="currency"><?php echo esc_html($calculator_config['currency']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Alertas del material -->
                            <div class="material-alerts"></div>
                        </div>
                    </div>
                
                <?php endif; ?>
            </div>
            
            <!-- Botón para agregar materiales -->
            <?php if (!empty($active_materials)): ?>
                <div class="add-material-section">
                    <button type="button" id="add-material-btn" class="adhesion-btn adhesion-btn-secondary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Agregar otro material', 'adhesion'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sección de opciones adicionales -->
        <div class="calculator-options-section">
            <h3 class="section-title">
                <span class="section-icon">⚙️</span>
                <?php _e('Opciones del cálculo', 'adhesion'); ?>
            </h3>
            
            <div class="options-grid">
                
                <!-- Aplicar descuentos -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="apply_discounts" id="apply_discounts" value="1" checked>
                        <span class="checkmark"></span>
                        <?php _e('Aplicar descuentos por volumen', 'adhesion'); ?>
                    </label>
                    <p class="option-description">
                        <?php _e('Se aplicarán descuentos automáticos según la cantidad total del pedido.', 'adhesion'); ?>
                    </p>
                </div>
                
                <!-- Incluir IVA -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="include_taxes" id="include_taxes" value="1" checked>
                        <span class="checkmark"></span>
                        <?php printf(__('Incluir IVA (%s%%)', 'adhesion'), $calculator_config['tax_rate']); ?>
                    </label>
                    <p class="option-description">
                        <?php _e('El precio final incluirá el IVA correspondiente.', 'adhesion'); ?>
                    </p>
                </div>
                
                <!-- Notas adicionales -->
                <div class="form-group form-group-full">
                    <label for="calculation_notes"><?php _e('Notas del cálculo (opcional)', 'adhesion'); ?></label>
                    <textarea name="notes" 
                              id="calculation_notes" 
                              rows="3" 
                              placeholder="<?php _e('Añade cualquier observación sobre este cálculo...', 'adhesion'); ?>"></textarea>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="calculator-actions">
            <button type="button" id="calculate-btn" class="adhesion-btn adhesion-btn-primary adhesion-btn-large">
                <span class="btn-icon">🧮</span>
                <span class="btn-text"><?php _e('Calcular Presupuesto', 'adhesion'); ?></span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span>
                    <?php _e('Calculando...', 'adhesion'); ?>
                </span>
            </button>
            
            <button type="button" id="reset-calculator-btn" class="adhesion-btn adhesion-btn-outline">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Reiniciar', 'adhesion'); ?>
            </button>
        </div>
    </form>

    <!-- Sección de resultados -->
    <div id="calculation-results" class="calculation-results" style="display: none;">
        
        <!-- Resumen del cálculo -->
        <div class="results-header">
            <h3 class="results-title">
                <span class="results-icon">📋</span>
                <?php _e('Resultado del cálculo', 'adhesion'); ?>
            </h3>
            <div class="results-date">
                <?php _e('Calculado el', 'adhesion'); ?>: <span class="calculation-date">--</span>
            </div>
        </div>
        
        <!-- Desglose de materiales -->
        <div class="materials-breakdown">
            <h4><?php _e('Desglose por materiales', 'adhesion'); ?></h4>
            <div class="materials-table-container">
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th><?php _e('Material', 'adhesion'); ?></th>
                            <th><?php _e('Cantidad', 'adhesion'); ?></th>
                            <th><?php _e('Precio/t', 'adhesion'); ?></th>
                            <th><?php _e('Subtotal', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="materials-breakdown-body">
                        <!-- Se rellena con JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Resumen financiero -->
        <div class="financial-summary">
            <div class="summary-grid">
                
                <div class="summary-item">
                    <span class="summary-label"><?php _e('Total toneladas:', 'adhesion'); ?></span>
                    <span class="summary-value" id="total-tons">0</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label"><?php _e('Subtotal:', 'adhesion'); ?></span>
                    <span class="summary-value" id="subtotal">0,00 €</span>
                </div>
                
                <div class="summary-item discount-item" style="display: none;">
                    <span class="summary-label"><?php _e('Descuentos:', 'adhesion'); ?></span>
                    <span class="summary-value discount-value" id="discount-amount">-0,00 €</span>
                </div>
                
                <div class="summary-item tax-item" style="display: none;">
                    <span class="summary-label"><?php printf(__('IVA (%s%%):', 'adhesion'), $calculator_config['tax_rate']); ?></span>
                    <span class="summary-value" id="tax-amount">0,00 €</span>
                </div>
                
                <div class="summary-item total-item">
                    <span class="summary-label"><?php _e('TOTAL:', 'adhesion'); ?></span>
                    <span class="summary-value total-value" id="total-price">0,00 €</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label"><?php _e('Precio promedio/t:', 'adhesion'); ?></span>
                    <span class="summary-value" id="average-price">0,00 €</span>
                </div>
            </div>
        </div>
        
        <!-- Alertas y avisos -->
        <div class="calculation-warnings" id="calculation-warnings" style="display: none;">
            <h4><?php _e('Avisos importantes', 'adhesion'); ?></h4>
            <div class="warnings-list" id="warnings-list">
                <!-- Se rellena con JavaScript -->
            </div>
        </div>
        
        <!-- Acciones del resultado -->
        <div class="results-actions">
            <button type="button" id="save-calculation-btn" class="adhesion-btn adhesion-btn-success">
                <span class="dashicons dashicons-saved"></span>
                <span class="btn-text"><?php _e('Guardar Cálculo', 'adhesion'); ?></span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span>
                    <?php _e('Guardando...', 'adhesion'); ?>
                </span>
            </button>
            
            <button type="button" id="create-contract-btn" class="adhesion-btn adhesion-btn-primary">
                <span class="dashicons dashicons-media-document"></span>
                <?php _e('Crear Contrato', 'adhesion'); ?>
            </button>
            
            <button type="button" id="print-calculation-btn" class="adhesion-btn adhesion-btn-outline">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('Imprimir', 'adhesion'); ?>
            </button>
        </div>
    </div>

    <!-- Historial de cálculos -->
    <div class="calculation-history-section">
        <h3 class="section-title">
            <span class="section-icon">📈</span>
            <?php _e('Mis cálculos recientes', 'adhesion'); ?>
        </h3>
        
        <div id="recent-calculations" class="recent-calculations">
            <div class="loading-calculations">
                <span class="spinner"></span>
                <?php _e('Cargando cálculos...', 'adhesion'); ?>
            </div>
        </div>
        
        <div class="history-actions">
            <a href="<?php echo esc_url(home_url('/mi-cuenta/')); ?>" class="adhesion-btn adhesion-btn-outline">
                <?php _e('Ver todos mis cálculos', 'adhesion'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Datos para JavaScript -->
<script type="text/javascript">
    window.adhesionCalculatorConfig = <?php echo json_encode(array(
        'materials' => $active_materials,
        'config' => $calculator_config,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('adhesion_nonce'),
        'userId' => get_current_user_id(),
        'messages' => array(
            'selectMaterial' => __('Por favor, selecciona un material', 'adhesion'),
            'enterQuantity' => __('Por favor, introduce una cantidad válida', 'adhesion'),
            'calculationSaved' => __('Cálculo guardado correctamente', 'adhesion'),
            'errorSaving' => __('Error al guardar el cálculo', 'adhesion'),
            'errorCalculating' => __('Error al calcular el presupuesto', 'adhesion'),
            'confirmReset' => __('¿Estás seguro de que quieres reiniciar la calculadora?', 'adhesion'),
            'minQuantityWarning' => __('La cantidad está por debajo del mínimo recomendado', 'adhesion'),
            'maxQuantityWarning' => __('La cantidad excede el máximo permitido', 'adhesion'),
            'noMaterialsSelected' => __('Debes seleccionar al menos un material', 'adhesion')
        ),
        'currency' => array(
            'symbol' => '€',
            'code' => $calculator_config['currency'],
            'decimals' => 2,
            'decimal_separator' => ',',
            'thousand_separator' => '.'
        )
    )); ?>
    </script>
</script>

<style>
/* Estilos específicos para la calculadora - Se integrarán en frontend.css */
.adhesion-calculator-container {
    max-width: 1200px;
    margin: 0 10px 10px 0;
}

.adhesion-btn-primary {
    background: #007cba;
    color: white;
}

.adhesion-btn-primary:hover {
    background: #005a87;
    color: white;
}

.adhesion-btn-secondary {
    background: #6c757d;
    color: white;
}

.adhesion-btn-secondary:hover {
    background: #545b62;
    color: white;
}

.adhesion-btn-success {
    background: #28a745;
    color: white;
}

.adhesion-btn-success:hover {
    background: #1e7e34;
    color: white;
}

.adhesion-btn-outline {
    background: transparent;
    color: #007cba;
    border: 2px solid #007cba;
}

.adhesion-btn-outline:hover {
    background: #007cba;
    color: white;
}

.adhesion-btn-large {
    padding: 16px 32px;
    font-size: 18px;
}

.btn-loading {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.calculation-results {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e4e7;
}

.results-title {
    font-size: 1.4em;
    margin: 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.results-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.results-date {
    color: #666;
    font-size: 0.9em;
}

.materials-breakdown {
    margin-bottom: 25px;
}

.materials-breakdown h4 {
    margin: 0 0 15px 0;
    color: #23282d;
}

.materials-table-container {
    overflow-x: auto;
}

.materials-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.materials-table th,
.materials-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e4e7;
}

.materials-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #23282d;
}

.materials-table tbody tr:hover {
    background: #f8f9fa;
}

.financial-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.summary-label {
    font-weight: 500;
    opacity: 0.9;
}

.summary-value {
    font-weight: 700;
    font-size: 1.1em;
}

.total-item {
    grid-column: 1 / -1;
    border-top: 2px solid rgba(255,255,255,0.3);
    padding-top: 15px;
    margin-top: 10px;
}

.total-value {
    font-size: 1.5em;
    color: #fff;
}

.discount-value {
    color: #90EE90;
}

.calculation-warnings {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.calculation-warnings h4 {
    margin: 0 0 15px 0;
    color: #856404;
}

.warnings-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.warning-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 0;
    color: #856404;
}

.warning-icon {
    color: #f0ad4e;
    font-size: 1.2em;
    margin-top: 2px;
}

.results-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.calculation-history-section {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-top: 30px;
}

.recent-calculations {
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading-calculations {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #666;
}

.calculation-history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid #e2e4e7;
    border-radius: 6px;
    margin-bottom: 10px;
    transition: background 0.3s ease;
}

.calculation-history-item:hover {
    background: #f8f9fa;
}

.history-info h5 {
    margin: 0 0 5px 0;
    color: #23282d;
}

.history-meta {
    font-size: 0.9em;
    color: #666;
}

.history-amount {
    font-weight: 700;
    font-size: 1.1em;
    color: #007cba;
}

.history-actions {
    text-align: center;
    margin-top: 20px;
}

.adhesion-messages-container {
    margin-bottom: 20px;
}

.adhesion-notice {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.adhesion-notice-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.adhesion-notice-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.adhesion-notice-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.adhesion-notice-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.no-materials-message {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

/* Responsive design */
@media (max-width: 768px) {
    .adhesion-calculator-container {
        padding: 15px;
    }
    
    .calculator-title {
        font-size: 2em;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .results-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .results-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .adhesion-btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
    
    .materials-table {
        font-size: 0.9em;
    }
    
    .calculation-history-item {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .options-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .calculator-title {
        font-size: 1.5em;
    }
    
    .calculator-description {
        font-size: 1em;
    }
    
    .material-row-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .form-group select,
    .form-group input {
        font-size: 16px; /* Evitar zoom en iOS */
    }
}

/* Animaciones */
.material-row {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.calculation-results {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estados de validación */
.form-group.has-error input,
.form-group.has-error select {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
}

.form-group.has-success input,
.form-group.has-success select {
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.1);
}

.error-message {
    color: #dc3545;
    font-size: 0.9em;
    margin-top: 5px;
}

.success-message {
    color: #28a745;
    font-size: 0.9em;
    margin-top: 5px;
}

/* Estados de los botones */
.adhesion-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.adhesion-btn.loading {
    pointer-events: none;
}

.adhesion-btn.loading .btn-text {
    display: none;
}

.adhesion-btn.loading .btn-loading {
    display: flex;
}

/* Mejoras de accesibilidad */
.adhesion-btn:focus,
.form-group input:focus,
.form-group select:focus {
    outline: 2px solid #007cba;
    outline-offset: 2px;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Print styles */
@media print {
    .calculator-actions,
    .results-actions,
    .add-material-section,
    .history-actions,
    .remove-material-btn {
        display: none;
    }
    
    .calculation-results {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .financial-summary {
        background: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #000;
    }
}
</style>0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.adhesion-calculator-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 30px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
}

.calculator-title {
    font-size: 2.5em;
    margin: 0 0 10px 0;
    font-weight: 700;
}

.calculator-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.calculator-description {
    font-size: 1.1em;
    margin: 0;
    opacity: 0.9;
}

.calculator-user-info {
    margin-bottom: 30px;
}

.user-info-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #007cba;
}

.user-info-card h3 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.calculator-materials-section,
.calculator-options-section {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 1.4em;
    margin: 0 0 20px 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.section-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.material-row {
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow 0.3s ease;
}

.material-row:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.material-row-header {
    background: #f8f9fa;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e4e7;
}

.material-row-title {
    margin: 0;
    font-size: 1.1em;
    color: #23282d;
}

.remove-material-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.remove-material-btn:hover {
    background: #c82333;
}

.material-row-content {
    padding: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #23282d;
}

.form-group select,
.form-group input {
    padding: 12px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.form-group select:focus,
.form-group input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.price-display,
.total-display {
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    font-weight: 600;
    font-size: 1.1em;
}

.input-help {
    margin-top: 5px;
    font-size: 0.9em;
    color: #666;
}

.minimum-quantity-text {
    color: #007cba;
    font-weight: 500;
}

.material-alerts {
    margin-top: 15px;
}

.material-alert {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.material-alert.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.material-alert.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.add-material-section {
    text-align: center;
    padding: 20px 0;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-group-full {
    grid-column: 1 / -1;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 10px;
    transform: scale(1.2);
}

.option-description {
    margin: 8px 0 0 0;
    font-size: 0.9em;
    color: #666;
}

.calculator-actions {
    text-align: center;
    margin: 30px 0;
}

.adhesion-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    margin: 