<?php
/**
 * Nueva vista de la calculadora UBICA
 * 
 * Interfaz moderna basada en tabla con resumen lateral
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

// Obtener materiales UBICA desde la base de datos
$ubica_repository = new Adhesion_Ubica_Prices_Repository();
$materials = $ubica_repository->get_active_prices();

// Si no hay materiales, mostrar mensaje
if (empty($materials)) {
    echo '<div class="adhesion-notice adhesion-notice-warning">';
    echo '<p>' . __('No hay materiales UBICA disponibles. Contacta con el administrador.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Verificar si hay un cálculo a cargar
$calculation_to_load = null;
$calc_id = isset($_GET['calc_id']) ? intval($_GET['calc_id']) : 0;

if ($calc_id > 0) {
    // Cargar el cálculo existente
    $db = new Adhesion_Database();
    $calculation_to_load = $db->get_calculation($calc_id);
    
    // Verificar que el cálculo pertenece al usuario actual
    if ($calculation_to_load && $calculation_to_load['user_id'] != get_current_user_id()) {
        // El cálculo no pertenece al usuario actual, no permitir carga
        $calculation_to_load = null;
        echo '<div class="adhesion-notice adhesion-notice-error">';
        echo '<p>' . __('No tienes permisos para cargar este cálculo.', 'adhesion') . '</p>';
        echo '</div>';
    } else if ($calculation_to_load) {
        // Mostrar mensaje de carga exitosa
        echo '<div class="adhesion-notice adhesion-notice-success">';
        echo '<p>' . sprintf(__('Cálculo #%d cargado correctamente.', 'adhesion'), $calc_id) . '</p>';
        echo '</div>';
    } else {
        // Cálculo no encontrado
        echo '<div class="adhesion-notice adhesion-notice-error">';
        echo '<p>' . __('No se encontró el cálculo solicitado.', 'adhesion') . '</p>';
        echo '</div>';
    }
}
?>

<div class="ubica-calculator-container">
    
    <!-- Contenedor principal con tabla y resumen -->
    <div class="calculator-main-content">
        
        <!-- Tabla de materiales -->
        <div class="materials-table-container">
            <form id="ubica-calculator-form">
                <table class="ubica-materials-table">
                    <thead>
                        <tr>
                            <th class="material-column"><?php _e('Material del envase', 'adhesion'); ?></th>
                            <th class="domestic-column"><?php _e('Doméstico (t)', 'adhesion'); ?></th>
                            <th class="cost-column"><?php _e('Coste €', 'adhesion'); ?></th>
                            <th class="commercial-column"><?php _e('Comercial (t)', 'adhesion'); ?></th>
                            <th class="cost-column"><?php _e('Coste €', 'adhesion'); ?></th>
                            <th class="industrial-column"><?php _e('Industrial (t)', 'adhesion'); ?></th>
                            <th class="cost-column"><?php _e('Coste €', 'adhesion'); ?></th>
                            <th class="total-column"><?php _e('Total', 'adhesion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $index => $material): ?>
                        <tr class="material-row" data-material="<?php echo esc_attr($material->material_name); ?>">
                            <!-- Número y nombre del material -->
                            <td class="material-info">
                                <span class="material-number"><?php echo ($index + 1); ?>.</span>
                                <span class="material-name"><?php echo esc_html($material->material_name); ?></span>
                                
                                <!-- Subcategorías si las hay (para materiales como plástico) -->
                                <?php if (strpos(strtolower($material->material_name), 'plástico') !== false): ?>
                                <div class="subcategories">
                                    <div class="subcategory">
                                        <span class="subcategory-label">No peligroso</span>
                                    </div>
                                    <div class="subcategory">
                                        <span class="subcategory-label">Peligroso<sup>2</sup></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Doméstico toneladas -->
                            <td class="input-cell">
                                <input type="number" 
                                       step="1" 
                                       min="0" 
                                       class="quantity-input domestic-input" 
                                       data-type="domestic"
                                       data-material="<?php echo esc_attr($material->material_name); ?>"
                                       data-price="<?php echo esc_attr($material->price_domestic); ?>"
                                       placeholder="0">
                            </td>
                            
                            <!-- Doméstico coste -->
                            <td class="cost-display">
                                <span class="cost-value domestic-cost" data-material="<?php echo esc_attr($material->material_name); ?>"></span>
                            </td>
                            
                            <!-- Comercial toneladas -->
                            <td class="input-cell">
                                <input type="number" 
                                       step="1" 
                                       min="0" 
                                       class="quantity-input commercial-input" 
                                       data-type="commercial"
                                       data-material="<?php echo esc_attr($material->material_name); ?>"
                                       data-price="<?php echo esc_attr($material->price_commercial); ?>"
                                       placeholder="0">
                            </td>
                            
                            <!-- Comercial coste -->
                            <td class="cost-display">
                                <span class="cost-value commercial-cost" data-material="<?php echo esc_attr($material->material_name); ?>"></span>
                            </td>
                            
                            <!-- Industrial toneladas -->
                            <td class="input-cell">
                                <input type="number" 
                                       step="1" 
                                       min="0" 
                                       class="quantity-input industrial-input" 
                                       data-type="industrial"
                                       data-material="<?php echo esc_attr($material->material_name); ?>"
                                       data-price="<?php echo esc_attr($material->price_industrial); ?>"
                                       placeholder="0">
                            </td>
                            
                            <!-- Industrial coste -->
                            <td class="cost-display">
                                <span class="cost-value industrial-cost" data-material="<?php echo esc_attr($material->material_name); ?>"></span>
                            </td>
                            
                            <!-- Total del material -->
                            <td class="total-display">
                                <span class="material-total" data-material="<?php echo esc_attr($material->material_name); ?>"></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Panel de resumen lateral -->
        <div class="summary-panel">
            <div class="summary-card">
                <h3 class="summary-title"><?php _e('Resumen', 'adhesion'); ?></h3>
                
                <div class="summary-lines">
                    <div class="summary-line">
                        <span class="summary-label"><?php _e('Doméstico:', 'adhesion'); ?></span>
                        <span class="summary-value" id="total-domestic"></span>
                    </div>
                    
                    <div class="summary-line">
                        <span class="summary-label"><?php _e('Coste:', 'adhesion'); ?></span>
                        <span class="summary-value" id="cost-domestic"></span>
                    </div>
                    
                    <div class="summary-line">
                        <span class="summary-label"><?php _e('Comercial:', 'adhesion'); ?></span>
                        <span class="summary-value" id="total-commercial"></span>
                    </div>
                    
                    <div class="summary-line">
                        <span class="summary-label"><?php _e('Coste:', 'adhesion'); ?></span>
                        <span class="summary-value" id="cost-commercial"></span>
                    </div>
                    
                    <div class="summary-line">
                        <span class="summary-label"><?php _e('Industrial:', 'adhesion'); ?></span>
                        <span class="summary-value" id="total-industrial"></span>
                    </div>
                    
                    <div class="summary-line">
                        <span class="summary-label"><?php _e('Coste:', 'adhesion'); ?></span>
                        <span class="summary-value" id="cost-industrial"></span>
                    </div>
                    
                    <div class="summary-line total-line">
                        <span class="summary-label total-label"><?php _e('Total:', 'adhesion'); ?></span>
                        <span class="summary-value total-value" id="grand-total"></span>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="summary-actions">
                    <button type="button" class="btn-limpiar" id="clear-calculator">
                        <?php _e('Limpiar', 'adhesion'); ?>
                    </button>
                    <button type="button" class="btn-calcular" id="calculate-button">
                        <?php _e('Calcular', 'adhesion'); ?>
                    </button>
                </div>

                <!-- Botón formalizar contrato (oculto hasta que se haga cálculo) -->
                <div class="contract-actions" id="contract-actions" style="display: none;">
                    <button type="button" class="btn-formalizar" id="formalize-contract">
                        <?php _e('Formalizar Contrato', 'adhesion'); ?>
                    </button>
                </div>
                

            </div>
        </div>
    </div>
</div>

<!-- Estilos CSS inline temporales (moveremos a archivo CSS después) -->
<style>
.ubica-calculator-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
}

.calculator-header {
    text-align: center;
    margin-bottom: 30px;
}

.calculator-title {
    font-size: 24px;
    color: #333;
    margin: 0 0 10px 0;
}

.highlight-text {
    color: #007cba;
    font-weight: bold;
}

.tarifas-generales-link {
    color: #007cba;
    text-decoration: none;
    font-size: 14px;
}

.tarifas-generales-link:hover {
    text-decoration: underline;
}

.calculator-main-content {
    display: flex;
    gap: 30px;
    align-items: flex-start;
}

.materials-table-container {
    flex: 1;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
}

.ubica-materials-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.ubica-materials-table th {
    background: #e9ecef;
    padding: 15px 10px;
    text-align: center;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.ubica-materials-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #e9ecef;
    text-align: center;
    vertical-align: middle;
}

.material-info {
    text-align: left !important;
    padding-left: 20px !important;
}

.material-number {
    font-weight: bold;
    margin-right: 8px;
}

.material-name {
    font-weight: 500;
    color: #333;
}

.subcategories {
    margin-top: 8px;
    padding-left: 20px;
}

.subcategory {
    margin: 4px 0;
    font-size: 14px;
    color: #666;
}

.subcategory-label {
    background: #f8f9fa;
    padding: 2px 8px;
    border-radius: 3px;
    display: inline-block;
}

.quantity-input {
    width: 80px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
    font-size: 14px;
}

.quantity-input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 5px rgba(0, 124, 186, 0.3);
}

.cost-display {
    font-weight: 500;
    color: #495057;
}

.material-total {
    font-weight: bold;
    color: #333;
}

.summary-panel {
    flex: 0 0 300px;
}

.summary-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.summary-title {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin: 0 0 20px 0;
    text-align: center;
}

.summary-lines {
    margin-bottom: 25px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f4;
}

.summary-line:last-child {
    border-bottom: none;
}

.total-line {
    border-top: 2px solid #007cba;
    border-bottom: none !important;
    margin-top: 15px;
    padding-top: 15px;
}

.summary-label {
    font-weight: 500;
    color: #495057;
}

.total-label {
    font-weight: bold;
    color: #333;
    font-size: 16px;
}

.summary-value {
    font-weight: 600;
    color: #333;
}

.total-value {
    font-weight: bold;
    color: #007cba;
    font-size: 18px;
}

.summary-actions {
    display: flex;
    gap: 10px;
}

.btn-limpiar,
.btn-calcular {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 5px;
}

.btn-limpiar {
    background: #6c757d;
    color: white;
}

.btn-limpiar:hover {
    background: #5a6268;
}

.btn-calcular {
    background: #007cba;
    color: white;
}

.btn-calcular:hover {
    background: #0056b3;
}

.contract-actions {
    margin-top: 15px;
    border-top: 1px solid #e9ecef;
    padding-top: 15px;
}

.btn-formalizar,
.btn-compartir {
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    text-transform: uppercase;
}

.btn-formalizar {
    background: #28a745;
    color: white;
}

.btn-formalizar:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn-compartir {
    background: #007cba;
    color: white;
    margin-top: 10px;
}

.btn-compartir:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}



/* Responsive */
@media (max-width: 768px) {
    .calculator-main-content {
        flex-direction: column;
    }
    
    .summary-panel {
        flex: none;
        width: 100%;
    }
    
    .ubica-materials-table {
        font-size: 12px;
    }
    
    .quantity-input {
        width: 60px;
    }
}
</style>

<!-- JavaScript para la calculadora -->
<script>
jQuery(document).ready(function($) {
    
    // Variables globales
    let isCalculating = false;
    
    // Función principal de inicialización
    function initUbicaCalculator() {
        console.log('Inicializando calculadora UBICA...');
        
        // Eventos de los botones
        $('#clear-calculator').on('click', clearCalculator);
        $('#calculate-button').on('click', calculateAll);
        $('#formalize-contract').on('click', formalizeContract);
        $('#share-calculation').on('click', shareCalculation);
        
        // Formatear números al perder foco
        $('.quantity-input').on('blur', function() {
            formatInput($(this));
        });
        
        // Validación en tiempo real (solo formato, no cálculo)
        $('.quantity-input').on('input', function() {
            validateNumericInput($(this));
        });
        
        // Cargar cálculo existente si hay datos
        <?php if ($calculation_to_load): ?>
            console.log('Datos del cálculo recibidos desde PHP:', <?php echo wp_json_encode($calculation_to_load); ?>);
            loadCalculationData(<?php echo wp_json_encode($calculation_to_load); ?>);
        <?php endif; ?>
        
        console.log('Calculadora UBICA inicializada correctamente');
    }
    
    /**
     * Cargar datos de un cálculo existente
     */
    function loadCalculationData(calculationData) {
        console.log('Cargando datos del cálculo:', calculationData);
        
        // Limpiar primero la calculadora
        clearCalculator();
        
        // Parsear los datos de materiales
        let materialsData = [];
        try {
            // Buscar en calculation_data (campo principal de la BD)
            if (calculationData.calculation_data) {
                let parsedData = typeof calculationData.calculation_data === 'string' ? 
                    JSON.parse(calculationData.calculation_data) : 
                    calculationData.calculation_data;
                
                // Los materiales pueden estar en parsedData.materials
                if (parsedData.materials) {
                    materialsData = parsedData.materials;
                } else {
                    console.warn('No se encontró campo "materials" en calculation_data:', parsedData);
                }
            } else if (calculationData.materials_data) {
                // Formato alternativo: materials_data como JSON string
                materialsData = typeof calculationData.materials_data === 'string' ? 
                    JSON.parse(calculationData.materials_data) : 
                    calculationData.materials_data;
            } else if (calculationData.materials) {
                // Formato directo: materials como array
                materialsData = calculationData.materials;
            } else {
                console.warn('No se encontraron datos de materiales en:', calculationData);
                return;
            }
        } catch (e) {
            console.error('Error parsing materials data:', e);
            return;
        }
        
        console.log('Materiales a cargar:', materialsData);
        
        // Poblar cada material en el formulario
        materialsData.forEach(function(material) {
            console.log('Procesando material:', material);
            
            // Verificar estructura de datos correcta
            if (!material.material || !material.type || !material.quantity) {
                console.warn('Material con datos incompletos:', material);
                return;
            }
            
            const materialName = material.material;
            const materialType = material.type;
            const quantity = parseInt(material.quantity) || 0;
            
            if (quantity <= 0) {
                console.log('Cantidad 0 o inválida para:', materialName, materialType);
                return;
            }
            
            // Buscar el input correspondiente
            const inputSelector = `input[data-material="${materialName}"][data-type="${materialType}"]`;
            const $input = $(inputSelector);
            
            console.log('Buscando input:', inputSelector);
            console.log('Elementos encontrados:', $input.length);
            console.log('Material:', materialName, 'Tipo:', materialType, 'Cantidad:', quantity);
            
            if ($input.length > 0) {
                // Llenar el campo
                $input.val(quantity);
                console.log(`✓ Cargado: ${materialName} ${materialType} = ${quantity}`);
                
                // Disparar evento change para recalcular automáticamente
                $input.trigger('input');
            } else {
                console.warn(`✗ No se encontró input para: ${materialName} ${materialType}`);
                // Debug: mostrar todos los inputs disponibles
                console.log('Inputs disponibles:');
                $('.quantity-input').each(function() {
                    const $this = $(this);
                    console.log(`- Material: "${$this.data('material')}", Tipo: "${$this.data('type')}"`);
                });
            }
        });
        
        // Recalcular automáticamente después de cargar
        setTimeout(function() {
            calculateAll();
        }, 100);
        
        console.log('Datos del cálculo cargados correctamente');
    }
    
    /**
     * Calcular coste de un material específico
     */
    function calculateMaterialCost($input) {
        
        const material = $input.data('material');
        const type = $input.data('type'); // domestic, commercial, industrial
        const price = parseFloat($input.data('price')) || 0;
        const quantity = parseInt($input.val()) || 0;
        
        console.log('=== DEBUG calculateMaterialCost ===');
        console.log('Material:', material);
        console.log('Type:', type);
        console.log('Price:', price);
        console.log('Quantity:', quantity);
        
        // Calcular coste total para este tipo
        const totalCost = quantity * price;
        console.log('Total cost:', totalCost);
        
        // Actualizar el coste en la tabla
        const costSelector = `.cost-value.${type}-cost[data-material="${material}"]`;
        console.log('Selector:', costSelector);
        
        const $targetElement = $(costSelector);
        console.log('Target element found:', $targetElement.length);
        
        if (totalCost > 0) {
            const formattedCost = formatCurrency(totalCost);
            console.log('Formatted cost:', formattedCost);
            $targetElement.text(formattedCost);
            console.log('Element text after update:', $targetElement.text());
        } else {
            $targetElement.text('');
        }
        
        // Calcular total del material (suma de todos los tipos)
        updateMaterialTotal(material);
        
        console.log(`Calculado: ${material} ${type} = ${quantity}t × ${price}€ = ${totalCost}€`);
        console.log('=== END DEBUG ===');
    }
    
    /**
     * Actualizar total de un material específico
     */
    function updateMaterialTotal(material) {
        let materialTotal = 0;
        
        // Sumar todos los costes de este material
        $(`.cost-value[data-material="${material}"]`).each(function() {
            const costText = $(this).text().replace(/[^\d.,]/g, '');
            const cost = parseFloat(costText.replace(',', '.')) || 0;
            materialTotal += cost;
        });
        
        // Actualizar el total del material (ocultar si es 0)
        if (materialTotal === 0) {
            $(`.material-total[data-material="${material}"]`).text('');
        } else {
            $(`.material-total[data-material="${material}"]`).text(Math.round(materialTotal) + ' €');
        }
    }
    
    /**
     * Actualizar resumen lateral
     */
    function updateSummary() {
        let totals = {
            domestic: { tons: 0, cost: 0 },
            commercial: { tons: 0, cost: 0 },
            industrial: { tons: 0, cost: 0 }
        };
        
        // Recorrer todos los inputs y sumar por tipo
        $('.quantity-input').each(function() {
            const $input = $(this);
            const type = $input.data('type');
            const quantity = parseInt($input.val()) || 0;
            const price = parseFloat($input.data('price')) || 0;
            const cost = quantity * price;
            
            if (totals[type]) {
                totals[type].tons += quantity;
                totals[type].cost += cost;
            }
        });
        
        // Actualizar los valores en el resumen (ocultar si es 0)
        $('#total-domestic').text(totals.domestic.tons > 0 ? Math.round(totals.domestic.tons) + ' (t)' : '');
        $('#cost-domestic').text(formatCurrency(totals.domestic.cost));
        
        $('#total-commercial').text(totals.commercial.tons > 0 ? Math.round(totals.commercial.tons) + ' (t)' : '');
        $('#cost-commercial').text(formatCurrency(totals.commercial.cost));
        
        $('#total-industrial').text(totals.industrial.tons > 0 ? Math.round(totals.industrial.tons) + ' (t)' : '');
        $('#cost-industrial').text(formatCurrency(totals.industrial.cost));
        
        // Total general
        const grandTotal = totals.domestic.cost + totals.commercial.cost + totals.industrial.cost;
        $('#grand-total').text(grandTotal > 0 ? formatCurrency(grandTotal) : '');
        
        console.log('Resumen actualizado:', totals, 'Total:', grandTotal);
    }
    
    /**
     * Limpiar toda la calculadora
     */
    function clearCalculator() {
        console.log('Limpiando calculadora...');
        
        // Limpiar todos los inputs
        $('.quantity-input').val('');
        
        // Limpiar todos los costes
        $('.cost-value').text('');
        
        // Limpiar totales de materiales
        $('.material-total').text('');
        
        // Limpiar resumen
        $('#total-domestic, #total-commercial, #total-industrial').text('');
        $('#cost-domestic, #cost-commercial, #cost-industrial').text('');
        $('#grand-total').text('');
        
        // Ocultar botón de formalizar contrato
        $('#contract-actions').hide();

        
        console.log('Calculadora limpiada');
    }
    
    /**
     * Recalcular todo (botón Calcular)
     */
    function calculateAll() {
        console.log('Calculando toda la calculadora...');
        
        // Verificar que hay al menos un input con valor
        let hasValues = false;
        $('.quantity-input').each(function() {
            if ($(this).val() && parseInt($(this).val()) > 0) {
                hasValues = true;
                return false; // break
            }
        });
        
        if (!hasValues) {
            showMessage('Por favor, introduce al menos una cantidad antes de calcular', 'warning');
            return;
        }
        
        // Recalcular cada input que tenga valor
        $('.quantity-input').each(function() {
            const $input = $(this);
            if ($input.val() && parseInt($input.val()) > 0) {
                console.log('Procesando input:', $input);
                calculateMaterialCost($input);
            }
        });
        
        // Actualizar resumen
        updateSummary();
        
        // Guardar cálculo automáticamente
        saveCalculation();
        
        // Mostrar botón de formalizar contrato si hay total
        const grandTotal = parseFloat($('#grand-total').text().replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
        if (grandTotal > 0) {
            $('#contract-actions').show();
        }
        
        console.log('Cálculo completo finalizado');
    }
    
    
    /**
     * Guardar cálculo en la base de datos (automático)
     */
    function saveCalculation() {
        console.log('Guardando cálculo automáticamente...');
        
        // Recopilar datos del cálculo
        const calculationData = {
            materials: [],
            totals: {
                domestic: { tons: 0, cost: 0 },
                commercial: { tons: 0, cost: 0 },
                industrial: { tons: 0, cost: 0 }
            },
            grand_total: 0
        };
        
        // Recopilar datos de cada material
        $('.quantity-input').each(function() {
            const $input = $(this);
            const quantity = parseInt($input.val()) || 0;
            
            if (quantity > 0) {
                const material = $input.data('material');
                const type = $input.data('type');
                const price = parseFloat($input.data('price')) || 0;
                const cost = quantity * price;
                
                calculationData.materials.push({
                    material: material,
                    type: type,
                    quantity: quantity,
                    price_per_ton: price,
                    total_cost: cost
                });
                
                // Sumar a totales
                if (calculationData.totals[type]) {
                    calculationData.totals[type].tons += quantity;
                    calculationData.totals[type].cost += cost;
                }
            }
        });
        
        // Calcular gran total
        calculationData.grand_total = 
            calculationData.totals.domestic.cost + 
            calculationData.totals.commercial.cost + 
            calculationData.totals.industrial.cost;
        
        // Verificar que hay datos para guardar
        if (calculationData.materials.length === 0) {
            console.log('No hay datos para guardar');
            return;
        }
        
        // Llamada AJAX para guardar (silenciosa)
        $.ajax({
            url: adhesion_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_ubica_calculation',
                nonce: adhesion_ajax.nonce,
                calculation_data: JSON.stringify(calculationData)
            },
            success: function(response) {
                if (response.success) {
                    console.log('Cálculo guardado correctamente, ID:', response.data.calculation_id);
                    // Guardar ID del cálculo para uso posterior
                    localStorage.setItem('last_calculation_id', response.data.calculation_id);
                    
                    // Actualizar URL para incluir el calc_id (sin recargar la página)
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.set('calc_id', response.data.calculation_id);
                    window.history.replaceState({}, '', newUrl);
                } else {
                    console.error('Error al guardar cálculo:', response.data);
                }
            },
            error: function() {
                console.error('Error de conexión al guardar el cálculo');
            }
        });
        
        console.log('Datos del cálculo preparados para guardar:', calculationData);
    }
    
    /**
     * Formalizar contrato - redirigir a formulario
     */
    function formalizeContract() {
        // Verificar que hay un cálculo guardado
        const grandTotal = parseFloat($('#grand-total').text().replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
        if (grandTotal === 0) {
            showMessage('Primero debes realizar un cálculo antes de formalizar el contrato', 'error');
            return;
        }
        
        // Obtener el último calculation_id del localStorage
        const lastCalculationId = localStorage.getItem('last_calculation_id');
        
        // Construir URL de la página formulario-adhesion usando la URL desde WordPress
        let formUrl = adhesion_ajax.form_url;
        
        // Agregar parámetros
        const separator = formUrl.includes('?') ? '&' : '?';
        formUrl += separator + 'step=company_data';
        
        if (lastCalculationId) {
            formUrl += '&calc_id=' + lastCalculationId;
        }
        
        console.log('Redirigiendo a:', formUrl);
        
        // Redirigir al formulario
        window.location.href = formUrl;
    }
    
    /**
     * Compartir cálculo - generar enlace compartible
     */
    function shareCalculation() {
        const calculationId = localStorage.getItem('last_calculation_id');
        if (!calculationId) {
            showMessage('No hay cálculo para compartir', 'error');
            return;
        }
        
        // Crear URL con el calc_id
        const shareUrl = new URL(window.location.href);
        shareUrl.searchParams.set('calc_id', calculationId);
        
        // Copiar al portapapeles
        navigator.clipboard.writeText(shareUrl.toString()).then(function() {
            showMessage('Enlace del cálculo copiado al portapapeles', 'success');
        }).catch(function() {
            // Fallback para navegadores que no soportan clipboard API
            const textArea = document.createElement('textarea');
            textArea.value = shareUrl.toString();
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showMessage('Enlace del cálculo copiado al portapapeles', 'success');
        });
    }
    
    /**
     * Formatear input al perder foco
     */
    function formatInput($input) {
        const value = parseInt($input.val());
        if (!isNaN(value) && value > 0) {
            $input.val(value);
        }
    }
    
    /**
     * Formatear cantidad como moneda
     */
    function formatCurrency(amount) {
        if (isNaN(amount) || amount === 0) {
            return '';
        }
        return Math.round(amount) + ' €';
    }
    
    /**
     * Mostrar mensaje temporal
     */
    function showMessage(message, type = 'info') {
        // Crear elemento de mensaje si no existe
        let $messageContainer = $('#ubica-messages');
        if ($messageContainer.length === 0) {
            $messageContainer = $('<div id="ubica-messages" class="ubica-messages"></div>');
            $('.ubica-calculator-container').prepend($messageContainer);
        }
        
        // Crear mensaje
        const $message = $(`
            <div class="ubica-message ubica-message-${type}">
                ${message}
            </div>
        `);
        
        // Mostrar mensaje
        $messageContainer.html($message);
        
        // Auto-ocultar después de 3 segundos
        setTimeout(function() {
            $message.fadeOut(500, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Validar entrada numérica (solo enteros)
     */
    function validateNumericInput($input) {
        const value = $input.val();
        const numericValue = value.replace(/[^0-9]/g, '');
        
        if (value !== numericValue) {
            $input.val(numericValue);
        }
        
        // Limitar a números positivos
        const parsed = parseInt(numericValue);
        if (parsed < 0) {
            $input.val('0');
        }
    }
    
    // Evento adicional para validación
    $('.quantity-input').on('keyup change', function() {
        validateNumericInput($(this));
    });
    
    // CSS adicional para mensajes
    $('head').append(`
        <style>
        .ubica-messages {
            margin-bottom: 20px;
        }
        
        .ubica-message {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .ubica-message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .ubica-message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .ubica-message-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .ubica-message-info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        </style>
    `);
    
    // Inicializar la calculadora cuando el DOM esté listo
    initUbicaCalculator();
    
    console.log('Script de calculadora UBICA cargado completamente');
});
</script>