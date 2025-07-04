/**
 * JavaScript para la Calculadora de Adhesión
 * 
 * Funcionalidades:
 * - Cálculos en tiempo real
 * - Gestión dinámica de materiales
 * - AJAX para guardar/cargar datos
 * - Validaciones automáticas
 * - Interfaz interactiva
 */

(function($) {
    'use strict';

    // Variables globales
    let calculatorApp = {
        config: {},
        materials: {},
        currentCalculation: null,
        materialRowIndex: 1,
        isCalculating: false,
        isSaving: false
    };

    // Inicialización cuando el DOM está listo
    $(document).ready(function() {
        if (typeof window.adhesionCalculatorConfig !== 'undefined') {
            calculatorApp.config = window.adhesionCalculatorConfig;
            init();
        }
    });

    /**
     * Inicialización principal
     */
    function init() {
        // Preparar datos de materiales
        prepareMaterialsData();
        
        // Configurar event listeners
        setupEventListeners();
        
        // Configurar validaciones
        setupValidations();
        
        // Cargar historial de cálculos
        loadRecentCalculations();
        
        // Log de inicialización
        console.log('Calculadora de Adhesión inicializada');
    }

    /**
     * Preparar datos de materiales para fácil acceso
     */
    function prepareMaterialsData() {
        calculatorApp.materials = {};
        
        if (calculatorApp.config.materials) {
            calculatorApp.config.materials.forEach(function(material) {
                calculatorApp.materials[material.material_type] = {
                    price: parseFloat(material.price_per_ton),
                    minimum: parseFloat(material.minimum_quantity),
                    description: material.description || '',
                    active: material.is_active
                };
            });
        }
    }

    /**
     * Configurar todos los event listeners
     */
    function setupEventListeners() {
        // Botones principales
        $('#calculate-btn').on('click', handleCalculateClick);
        $('#save-calculation-btn').on('click', handleSaveCalculation);
        $('#create-contract-btn').on('click', handleCreateContract);
        $('#print-calculation-btn').on('click', handlePrintCalculation);
        $('#reset-calculator-btn').on('click', handleResetCalculator);
        
        // Gestión de materiales
        $('#add-material-btn').on('click', addMaterialRow);
        $(document).on('click', '.remove-material-btn', removeMaterialRow);
        
        // Cambios en formulario
        $(document).on('change', '.material-type-select', handleMaterialTypeChange);
        $(document).on('input', '.material-quantity-input', handleQuantityChange);
        $(document).on('change', '#apply_discounts, #include_taxes', updateCalculationDisplay);
        
        // Histórico de cálculos
        $(document).on('click', '.load-calculation-btn', loadPreviousCalculation);
        
        // Auto-cálculo en tiempo real (con debounce)
        let debounceTimer;
        $(document).on('input change', '.material-type-select, .material-quantity-input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                if (hasValidMaterials()) {
                    performQuickCalculation();
                }
            }, 500);
        });
    }

    /**
     * Configurar validaciones en tiempo real
     */
    function setupValidations() {
        // Validación de cantidad en tiempo real
        $(document).on('blur', '.material-quantity-input', function() {
            validateMaterialQuantity($(this));
        });
        
        // Validación de selección de material
        $(document).on('change', '.material-type-select', function() {
            validateMaterialSelection($(this));
        });
    }

    /**
     * Manejar clic en calcular
     */
    function handleCalculateClick(e) {
        e.preventDefault();
        
        if (calculatorApp.isCalculating) {
            return;
        }
        
        // Validar formulario completo
        if (!validateForm()) {
            return;
        }
        
        performFullCalculation();
    }

    /**
     * Validar formulario completo
     */
    function validateForm() {
        let isValid = true;
        let firstErrorField = null;
        
        // Limpiar errores previos
        $('.form-group').removeClass('has-error');
        $('.error-message').remove();
        
        // Validar que hay al menos un material
        const materialRows = $('.material-row:visible');
        if (materialRows.length === 0) {
            showMessage(calculatorApp.config.messages.noMaterialsSelected, 'error');
            return false;
        }
        
        // Validar cada fila de material
        materialRows.each(function() {
            const $row = $(this);
            const $typeSelect = $row.find('.material-type-select');
            const $quantityInput = $row.find('.material-quantity-input');
            
            // Validar tipo de material
            if (!$typeSelect.val()) {
                markFieldError($typeSelect, calculatorApp.config.messages.selectMaterial);
                isValid = false;
                if (!firstErrorField) firstErrorField = $typeSelect;
            }
            
            // Validar cantidad
            const quantity = parseFloat($quantityInput.val());
            if (!quantity || quantity <= 0) {
                markFieldError($quantityInput, calculatorApp.config.messages.enterQuantity);
                isValid = false;
                if (!firstErrorField) firstErrorField = $quantityInput;
            } else if (quantity > calculatorApp.config.config.max_quantity_per_material) {
                markFieldError($quantityInput, `Cantidad máxima: ${calculatorApp.config.config.max_quantity_per_material}t`);
                isValid = false;
                if (!firstErrorField) firstErrorField = $quantityInput;
            }
        });
        
        // Enfocar primer campo con error
        if (!isValid && firstErrorField) {
            firstErrorField.focus();
        }
        
        return isValid;
    }

    /**
     * Marcar campo con error
     */
    function markFieldError($field, message) {
        const $formGroup = $field.closest('.form-group');
        $formGroup.addClass('has-error');
        
        if (message) {
            $formGroup.append(`<div class="error-message">${message}</div>`);
        }
    }

    /**
     * Realizar cálculo completo
     */
    function performFullCalculation() {
        calculatorApp.isCalculating = true;
        updateCalculateButton(true);
        
        const formData = collectFormData();
        
        $.ajax({
            url: calculatorApp.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_calculate_budget',
                nonce: calculatorApp.config.nonce,
                ...formData
            },
            success: function(response) {
                if (response.success) {
                    calculatorApp.currentCalculation = response.data.calculation;
                    displayCalculationResults(response.data.calculation);
                    showMessage('Cálculo realizado correctamente', 'success');
                } else {
                    showMessage(response.data || calculatorApp.config.messages.errorCalculating, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error en cálculo:', error);
                showMessage(calculatorApp.config.messages.errorCalculating, 'error');
            },
            complete: function() {
                calculatorApp.isCalculating = false;
                updateCalculateButton(false);
            }
        });
    }

    /**
     * Realizar cálculo rápido (para preview)
     */
    function performQuickCalculation() {
        const materials = collectMaterialsData();
        
        if (materials.length === 0) {
            return;
        }
        
        let subtotal = 0;
        let totalTons = 0;
        
        materials.forEach(function(material) {
            if (material.type && material.quantity && calculatorApp.materials[material.type]) {
                const materialPrice = calculatorApp.materials[material.type].price;
                const materialTotal = material.quantity * materialPrice;
                
                subtotal += materialTotal;
                totalTons += material.quantity;
                
                // Actualizar subtotal del material en la interfaz
                const $row = $(`.material-row[data-row-index="${material.rowIndex}"]`);
                $row.find('.material-total').text(formatCurrency(materialTotal));
            }
        });
        
        // Calcular descuentos y impuestos básicos
        let discountAmount = 0;
        let taxAmount = 0;
        
        if ($('#apply_discounts').prop('checked')) {
            discountAmount = calculateDiscount(subtotal, totalTons);
        }
        
        if ($('#include_taxes').prop('checked')) {
            const taxableAmount = subtotal - discountAmount;
            taxAmount = taxableAmount * (calculatorApp.config.config.tax_rate / 100);
        }
        
        const totalPrice = subtotal - discountAmount + taxAmount;
        
        // Actualizar preview si está visible
        updateCalculationPreview({
            subtotal: subtotal,
            total_tons: totalTons,
            discount_amount: discountAmount,
            tax_amount: taxAmount,
            total_price: totalPrice
        });
    }

    /**
     * Calcular descuentos por volumen
     */
    function calculateDiscount(subtotal, totalTons) {
        let discountRate = 0;
        
        // Descuentos por volumen (basado en especificaciones)
        if (totalTons >= 100) {
            discountRate += 0.15; // 15% por más de 100t
        } else if (totalTons >= 50) {
            discountRate += 0.10; // 10% por más de 50t
        } else if (totalTons >= 25) {
            discountRate += 0.05; // 5% por más de 25t
        }
        
        // Descuentos por importe
        if (subtotal >= 50000) {
            discountRate += 0.03; // 3% por más de 50k€
        } else if (subtotal >= 25000) {
            discountRate += 0.02; // 2% por más de 25k€
        }
        
        // Descuento adicional por tonelaje extremo
        if (totalTons >= 500) {
            discountRate += 0.02; // 2% adicional por más de 500t
        }
        
        // Limitar descuento máximo al 20%
        discountRate = Math.min(discountRate, 0.20);
        
        return subtotal * discountRate;
    }

    /**
     * Recopilar datos del formulario
     */
    function collectFormData() {
        const materials = collectMaterialsData();
        
        return {
            materials: materials,
            apply_discounts: $('#apply_discounts').prop('checked') ? 1 : 0,
            include_taxes: $('#include_taxes').prop('checked') ? 1 : 0,
            tax_rate: calculatorApp.config.config.tax_rate,
            notes: $('#calculation_notes').val().trim()
        };
    }

    /**
     * Recopilar datos de materiales
     */
    function collectMaterialsData() {
        const materials = [];
        
        $('.material-row:visible').each(function() {
            const $row = $(this);
            const type = $row.find('.material-type-select').val();
            const quantity = parseFloat($row.find('.material-quantity-input').val());
            
            if (type && quantity && quantity > 0) {
                materials.push({
                    type: type,
                    quantity: quantity,
                    rowIndex: $row.data('row-index')
                });
            }
        });
        
        return materials;
    }

    /**
     * Verificar si hay materiales válidos
     */
    function hasValidMaterials() {
        const materials = collectMaterialsData();
        return materials.length > 0;
    }

    /**
     * Mostrar resultados del cálculo
     */
    function displayCalculationResults(calculation) {
        // Mostrar sección de resultados
        $('#calculation-results').show();
        
        // Actualizar fecha
        $('.calculation-date').text(new Date().toLocaleDateString('es-ES'));
        
        // Mostrar desglose de materiales
        displayMaterialsBreakdown(calculation.materials);
        
        // Mostrar resumen financiero
        displayFinancialSummary(calculation);
        
        // Mostrar advertencias si las hay
        displayWarnings(calculation.warnings);
        
        // Scroll suave a resultados
        $('html, body').animate({
            scrollTop: $('#calculation-results').offset().top - 50
        }, 800);
    }

    /**
     * Mostrar desglose de materiales
     */
    function displayMaterialsBreakdown(materials) {
        const $tbody = $('#materials-breakdown-body');
        $tbody.empty();
        
        if (materials && materials.length > 0) {
            materials.forEach(function(material) {
                const row = `
                    <tr>
                        <td><strong>${escapeHtml(material.type)}</strong></td>
                        <td>${formatTons(material.quantity)}</td>
                        <td>${formatCurrency(material.price_per_ton)}</td>
                        <td>${formatCurrency(material.total)}</td>
                    </tr>
                `;
                $tbody.append(row);
            });
        }
    }

    /**
     * Mostrar resumen financiero
     */
    function displayFinancialSummary(calculation) {
        $('#total-tons').text(formatTons(calculation.total_tons));
        $('#subtotal').text(formatCurrency(calculation.subtotal));
        $('#total-price').text(formatCurrency(calculation.total_price));
        $('#average-price').text(formatCurrency(calculation.average_price_per_ton));
        
        // Mostrar/ocultar descuentos
        if (calculation.discount_amount > 0) {
            $('#discount-amount').text('-' + formatCurrency(calculation.discount_amount));
            $('.discount-item').show();
        } else {
            $('.discount-item').hide();
        }
        
        // Mostrar/ocultar impuestos
        if (calculation.tax_amount > 0) {
            $('#tax-amount').text(formatCurrency(calculation.tax_amount));
            $('.tax-item').show();
        } else {
            $('.tax-item').hide();
        }
    }

    /**
     * Mostrar advertencias
     */
    function displayWarnings(warnings) {
        const $warningsContainer = $('#calculation-warnings');
        const $warningsList = $('#warnings-list');
        
        if (warnings && warnings.length > 0) {
            $warningsList.empty();
            
            warnings.forEach(function(warning) {
                const warningItem = `
                    <div class="warning-item">
                        <span class="warning-icon">⚠️</span>
                        <span>${escapeHtml(warning)}</span>
                    </div>
                `;
                $warningsList.append(warningItem);
            });
            
            $warningsContainer.show();
        } else {
            $warningsContainer.hide();
        }
    }

    /**
     * Actualizar preview de cálculo (durante escritura)
     */
    function updateCalculationPreview(preview) {
        // Esta función actualiza valores en tiempo real sin mostrar toda la sección de resultados
        // Se podría implementar un mini-resumen que se muestre mientras el usuario escribe
    }

    /**
     * Agregar nueva fila de material
     */
    function addMaterialRow() {
        const $template = $('.material-row-template');
        const $newRow = $template.find('.material-row').clone();
        
        // Actualizar índices y IDs
        const newIndex = calculatorApp.materialRowIndex++;
        updateRowIndexes($newRow, newIndex);
        
        // Mostrar botón de eliminar en todas las filas si hay más de una
        $('.remove-material-btn').show();
        
        // Agregar nueva fila
        $('#materials-container').append($newRow);
        
        // Actualizar números de material
        updateMaterialNumbers();
        
        // Animar entrada
        $newRow.hide().slideDown(300);
        
        // Enfocar selector de material
        $newRow.find('.material-type-select').focus();
    }

    /**
     * Eliminar fila de material
     */
    function removeMaterialRow(e) {
        e.preventDefault();
        
        const $row = $(this).closest('.material-row');
        const $allRows = $('.material-row:visible');
        
        // No permitir eliminar si solo hay una fila
        if ($allRows.length <= 1) {
            showMessage('Debe haber al menos un material', 'warning');
            return;
        }
        
        // Animar salida y eliminar
        $row.slideUp(300, function() {
            $row.remove();
            updateMaterialNumbers();
            
            // Ocultar botón eliminar si solo queda una fila
            if ($('.material-row:visible').length === 1) {
                $('.remove-material-btn').hide();
            }
            
            // Recalcular si hay datos válidos
            if (hasValidMaterials()) {
                performQuickCalculation();
            }
        });
    }

    /**
     * Actualizar índices de una fila
     */
    function updateRowIndexes($row, newIndex) {
        $row.attr('data-row-index', newIndex);
        
        // Actualizar IDs y names
        $row.find('select, input').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            const id = $field.attr('id');
            
            if (name) {
                $field.attr('name', name.replace(/\[\d+\]/, `[${newIndex}]`));
            }
            if (id) {
                $field.attr('id', id.replace(/_\d+$/, `_${newIndex}`));
            }
        });
        
        // Actualizar labels
        $row.find('label').each(function() {
            const $label = $(this);
            const forAttr = $label.attr('for');
            if (forAttr) {
                $label.attr('for', forAttr.replace(/_\d+$/, `_${newIndex}`));
            }
        });
    }

    /**
     * Actualizar números de materiales
     */
    function updateMaterialNumbers() {
        $('.material-row:visible').each(function(index) {
            $(this).find('.material-number').text(index + 1);
        });
    }

    /**
     * Manejar cambio de tipo de material
     */
    function handleMaterialTypeChange() {
        const $select = $(this);
        const $row = $select.closest('.material-row');
        const materialType = $select.val();
        
        if (materialType && calculatorApp.materials[materialType]) {
            const material = calculatorApp.materials[materialType];
            
            // Actualizar precio mostrado
            $row.find('.price-per-ton').text(formatCurrency(material.price));
            
            // Mostrar/actualizar cantidad mínima
            const $minText = $row.find('.minimum-quantity-text');
            const $minValue = $row.find('.minimum-value');
            
            if (material.minimum > 0) {
                $minValue.text(formatTons(material.minimum));
                $minText.show();
                
                // Actualizar atributo min del input
                $row.find('.material-quantity-input').attr('min', material.minimum);
            } else {
                $minText.hide();
                $row.find('.material-quantity-input').attr('min', 0.1);
            }
            
            // Limpiar alertas previas
            $row.find('.material-alerts').empty();
            
            // Validar cantidad actual si existe
            const $quantityInput = $row.find('.material-quantity-input');
            if ($quantityInput.val()) {
                validateMaterialQuantity($quantityInput);
            }
        } else {
            // Limpiar campos si no hay material seleccionado
            $row.find('.price-per-ton').text('--');
            $row.find('.material-total').text('0,00');
            $row.find('.minimum-quantity-text').hide();
            $row.find('.material-alerts').empty();
        }
        
        // Recalcular totales
        handleQuantityChange.call($row.find('.material-quantity-input')[0]);
    }

    /**
     * Manejar cambio de cantidad
     */
    function handleQuantityChange() {
        const $input = $(this);
        const $row = $input.closest('.material-row');
        const quantity = parseFloat($input.val());
        const materialType = $row.find('.material-type-select').val();
        
        if (materialType && quantity && calculatorApp.materials[materialType]) {
            const material = calculatorApp.materials[materialType];
            const total = quantity * material.price;
            
            // Actualizar total del material
            $row.find('.material-total').text(formatCurrency(total));
            
            // Validar cantidad
            validateMaterialQuantity($input);
        } else {
            $row.find('.material-total').text('0,00');
        }
    }

    /**
     * Validar cantidad de material
     */
    function validateMaterialQuantity($input) {
        const $row = $input.closest('.material-row');
        const $alertsContainer = $row.find('.material-alerts');
        const quantity = parseFloat($input.val());
        const materialType = $row.find('.material-type-select').val();
        
        // Limpiar alertas previas
        $alertsContainer.empty();
        $row.removeClass('has-error has-warning');
        
        if (!quantity || !materialType || !calculatorApp.materials[materialType]) {
            return;
        }
        
        const material = calculatorApp.materials[materialType];
        
        // Validar cantidad mínima
        if (material.minimum > 0 && quantity < material.minimum) {
            addMaterialAlert($alertsContainer, 'warning', 
                `${calculatorApp.config.messages.minQuantityWarning}: ${formatTons(material.minimum)}`);
            $row.addClass('has-warning');
        }
        
        // Validar cantidad máxima
        if (quantity > calculatorApp.config.config.max_quantity_per_material) {
            addMaterialAlert($alertsContainer, 'error', 
                `${calculatorApp.config.messages.maxQuantityWarning}: ${calculatorApp.config.config.max_quantity_per_material}t`);
            $row.addClass('has-error');
        }
        
        // Validaciones adicionales
        if (quantity <= 0) {
            addMaterialAlert($alertsContainer, 'error', 'La cantidad debe ser mayor que 0');
            $row.addClass('has-error');
        }
    }

    /**
     * Validar selección de material
     */
    function validateMaterialSelection($select) {
        const $row = $select.closest('.material-row');
        const materialType = $select.val();
        
        $row.removeClass('has-error has-success');
        
        if (materialType) {
            $row.addClass('has-success');
        }
    }

    /**
     * Agregar alerta a material
     */
    function addMaterialAlert($container, type, message) {
        const alertClass = type === 'error' ? 'material-alert error' : 'material-alert warning';
        const alert = `<div class="${alertClass}">${escapeHtml(message)}</div>`;
        $container.append(alert);
    }

    /**
     * Guardar cálculo
     */
    function handleSaveCalculation(e) {
        e.preventDefault();
        
        if (calculatorApp.isSaving || !calculatorApp.currentCalculation) {
            return;
        }
        
        calculatorApp.isSaving = true;
        updateSaveButton(true);
        
        const formData = collectFormData();
        
        $.ajax({
            url: calculatorApp.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_save_calculation',
                nonce: calculatorApp.config.nonce,
                calculation_data: JSON.stringify(calculatorApp.currentCalculation),
                total_price: calculatorApp.currentCalculation.total_price,
                total_tons: calculatorApp.currentCalculation.total_tons,
                price_per_ton: calculatorApp.currentCalculation.average_price_per_ton,
                notes: formData.notes
            },
            success: function(response) {
                if (response.success) {
                    showMessage(calculatorApp.config.messages.calculationSaved, 'success');
                    
                    // Actualizar historial
                    loadRecentCalculations();
                    
                    // Opcional: redirigir a mi cuenta
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 2000);
                    }
                } else {
                    showMessage(response.data || calculatorApp.config.messages.errorSaving, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error guardando:', error);
                showMessage(calculatorApp.config.messages.errorSaving, 'error');
            },
            complete: function() {
                calculatorApp.isSaving = false;
                updateSaveButton(false);
            }
        });
    }

    /**
     * Crear contrato desde cálculo
     */
    function handleCreateContract(e) {
        e.preventDefault();
        
        if (!calculatorApp.currentCalculation) {
            showMessage('Primero debes realizar un cálculo', 'warning');
            return;
        }
        
        // Aquí se podría abrir un modal o redirigir a página de contrato
        // Por ahora, guardamos el cálculo y redirigimos
        if (!confirm('¿Deseas crear un contrato basado en este cálculo?')) {
            return;
        }
        
        // Implementar lógica de creación de contrato
        showMessage('Funcionalidad de contrato en desarrollo', 'info');
    }

    /**
     * Imprimir cálculo
     */
    function handlePrintCalculation(e) {
        e.preventDefault();
        
        if (!calculatorApp.currentCalculation) {
            return;
        }
        
        // Abrir ventana de impresión
        window.print();
    }

    /**
     * Reiniciar calculadora
     */
    function handleResetCalculator(e) {
        e.preventDefault();
        
        if (!confirm(calculatorApp.config.messages.confirmReset)) {
            return;
        }
        
        // Limpiar formulario
        $('#adhesion-calculator-form')[0].reset();
        
        // Eliminar filas extra de materiales
        $('.material-row:not(:first)').remove();
        
        // Ocultar botones de eliminar
        $('.remove-material-btn').hide();
        
        // Limpiar resultados
        $('#calculation-results').hide();
        
        // Limpiar alertas
        $('.material-alerts').empty();
        $('.form-group').removeClass('has-error has-warning has-success');
        $('.error-message').remove();
        
        // Limpiar datos globales
        calculatorApp.currentCalculation = null;
        calculatorApp.materialRowIndex = 1;
        
        // Actualizar números
        updateMaterialNumbers();
        
        // Limpiar mensajes
        hideMessage();
        
        showMessage('Calculadora reiniciada', 'info');
    }

    /**
     * Cargar cálculos recientes
     */
    function loadRecentCalculations() {
        $.ajax({
            url: calculatorApp.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_get_recent_calculations',
                nonce: calculatorApp.config.nonce,
                limit: 5
            },
            success: function(response) {
                if (response.success) {
                    displayRecentCalculations(response.data.calculations);
                } else {
                    $('#recent-calculations').html('<p>No hay cálculos recientes</p>');
                }
            },
            error: function() {
                $('#recent-calculations').html('<p>Error cargando historial</p>');
            }
        });
    }

    /**
     * Mostrar cálculos recientes
     */
    function displayRecentCalculations(calculations) {
        const $container = $('#recent-calculations');
        
        if (!calculations || calculations.length === 0) {
            $container.html('<p>No hay cálculos recientes</p>');
            return;
        }
        
        let html = '';
        calculations.forEach(function(calc) {
            html += `
                <div class="calculation-history-item">
                    <div class="history-info">
                        <h5>${calc.total_tons} toneladas</h5>
                        <div class="history-meta">
                            ${new Date(calc.created_at).toLocaleDateString('es-ES')} • 
                            ${calc.materials_count || 'N/A'} materiales
                        </div>
                    </div>
                    <div class="history-amount">${formatCurrency(calc.total_price)}</div>
                    <button type="button" class="adhesion-btn adhesion-btn-outline load-calculation-btn" 
                            data-calc-id="${calc.id}">Cargar</button>
                </div>
            `;
        });
        
        $container.html(html);
    }

    /**
     * Cargar cálculo previo
     */
    function loadPreviousCalculation(e) {
        e.preventDefault();
        
        const calcId = $(this).data('calc-id');
        
        if (!confirm('¿Cargar este cálculo? Se perderán los datos actuales.')) {
            return;
        }
        
        $.ajax({
            url: calculatorApp.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_get_calculation',
                nonce: calculatorApp.config.nonce,
                calculation_id: calcId
            },
            success: function(response) {
                if (response.success) {
                    loadCalculationData(response.data.calculation);
                    showMessage('Cálculo cargado correctamente', 'success');
                } else {
                    showMessage('Error cargando el cálculo', 'error');
                }
            },
            error: function() {
                showMessage('Error cargando el cálculo', 'error');
            }
        });
    }

    /**
     * Cargar datos de cálculo en el formulario
     */
    function loadCalculationData(calculation) {
        try {
            // Limpiar formulario actual
            handleResetCalculator({ preventDefault: function() {} });
            
            // Cargar materiales
            if (calculation.materials_data) {
                const materials = JSON.parse(calculation.materials_data);
                loadMaterialsIntoForm(materials);
            }
            
            // Cargar notas
            if (calculation.notes) {
                $('#calculation_notes').val(calculation.notes);
            }
            
            // Mostrar resultados si están disponibles
            if (calculation.calculation_data) {
                const calcData = JSON.parse(calculation.calculation_data);
                calculatorApp.currentCalculation = calcData;
                displayCalculationResults(calcData);
            }
            
        } catch (error) {
            console.error('Error cargando datos del cálculo:', error);
            showMessage('Error procesando los datos del cálculo', 'error');
        }
    }

    /**
     * Cargar materiales en el formulario
     */
    function loadMaterialsIntoForm(materials) {
        // Limpiar filas existentes excepto la primera
        $('.material-row:not(:first)').remove();
        
        materials.forEach(function(material, index) {
            let $row;
            
            if (index === 0) {
                // Usar primera fila existente
                $row = $('.material-row:first');
            } else {
                // Agregar nueva fila
                addMaterialRow();
                $row = $('.material-row').last();
            }
            
            // Cargar datos en la fila
            $row.find('.material-type-select').val(material.type).trigger('change');
            $row.find('.material-quantity-input').val(material.quantity).trigger('input');
        });
        
        // Actualizar visibilidad de botones eliminar
        if (materials.length > 1) {
            $('.remove-material-btn').show();
        }
    }

    /**
     * Actualizar botón de calcular
     */
    function updateCalculateButton(loading) {
        const $btn = $('#calculate-btn');
        const $text = $btn.find('.btn-text');
        const $loading = $btn.find('.btn-loading');
        
        if (loading) {
            $btn.prop('disabled', true).addClass('loading');
            $text.hide();
            $loading.show();
        } else {
            $btn.prop('disabled', false).removeClass('loading');
            $text.show();
            $loading.hide();
        }
    }

    /**
     * Actualizar botón de guardar
     */
    function updateSaveButton(loading) {
        const $btn = $('#save-calculation-btn');
        const $text = $btn.find('.btn-text');
        const $loading = $btn.find('.btn-loading');
        
        if (loading) {
            $btn.prop('disabled', true).addClass('loading');
            $text.hide();
            $loading.show();
        } else {
            $btn.prop('disabled', false).removeClass('loading');
            $text.show();
            $loading.hide();
        }
    }

    /**
     * Mostrar mensaje al usuario
     */
    function showMessage(message, type) {
        const $container = $('#adhesion-calculator-messages');
        const alertClass = `adhesion-notice adhesion-notice-${type}`;
        
        const html = `<div class="${alertClass}">${escapeHtml(message)}</div>`;
        
        $container.html(html);
        
        // Auto-hide para mensajes de éxito e info
        if (type === 'success' || type === 'info') {
            setTimeout(hideMessage, 5000);
        }
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 500);
    }

    /**
     * Ocultar mensaje
     */
    function hideMessage() {
        $('#adhesion-calculator-messages').empty();
    }

    /**
     * Formatear precio
     */
    function formatCurrency(amount) {
        if (typeof amount !== 'number') {
            amount = parseFloat(amount) || 0;
        }
        
        const config = calculatorApp.config.currency;
        
        return amount.toLocaleString('es-ES', {
            minimumFractionDigits: config.decimals,
            maximumFractionDigits: config.decimals
        }) + ' ' + config.symbol;
    }

    /**
     * Formatear toneladas
     */
    function formatTons(amount) {
        if (typeof amount !== 'number') {
            amount = parseFloat(amount) || 0;
        }
        
        return amount.toLocaleString('es-ES', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 2
        }) + 't';
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Debounce function para limitar llamadas
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    /**
     * Validaciones adicionales en tiempo real
     */
    function setupAdvancedValidations() {
        // Validación de números decimales
        $(document).on('input', '.material-quantity-input', function() {
            const value = this.value;
            // Permitir solo números y punto decimal
            this.value = value.replace(/[^0-9.]/g, '');
            
            // Evitar múltiples puntos decimales
            const parts = this.value.split('.');
            if (parts.length > 2) {
                this.value = parts[0] + '.' + parts.slice(1).join('');
            }
        });
        
        // Validación de paste
        $(document).on('paste', '.material-quantity-input', function(e) {
            const paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
            if (!/^\d*\.?\d*$/.test(paste)) {
                e.preventDefault();
            }
        });
    }

    /**
     * Configurar shortcuts de teclado
     */
    function setupKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + Enter para calcular
            if ((e.ctrlKey || e.metaKey) && e.which === 13) {
                e.preventDefault();
                if (!calculatorApp.isCalculating && hasValidMaterials()) {
                    $('#calculate-btn').click();
                }
            }
            
            // Ctrl/Cmd + S para guardar
            if ((e.ctrlKey || e.metaKey) && e.which === 83) {
                e.preventDefault();
                if (calculatorApp.currentCalculation && !calculatorApp.isSaving) {
                    $('#save-calculation-btn').click();
                }
            }
            
            // Escape para cerrar mensajes
            if (e.which === 27) {
                hideMessage();
            }
        });
    }

    /**
     * Configurar tooltips dinámicos
     */
    function setupTooltips() {
        // Tooltip para precios
        $(document).on('mouseenter', '.price-display', function() {
            const $this = $(this);
            const price = $this.find('.price-per-ton').text();
            
            if (price !== '--' && !$this.attr('title')) {
                $this.attr('title', `Precio por tonelada: ${price}`);
            }
        });
        
        // Tooltip para totales
        $(document).on('mouseenter', '.total-display', function() {
            const $this = $(this);
            const $row = $this.closest('.material-row');
            const quantity = $row.find('.material-quantity-input').val();
            const price = $row.find('.price-per-ton').text();
            
            if (quantity && price !== '--') {
                $this.attr('title', `${quantity}t × ${price} = ${$this.find('.material-total').text()}`);
            }
        });
    }

    /**
     * Configurar persistencia local (opcional)
     */
    function setupLocalPersistence() {
        // Guardar borrador automáticamente cada 30 segundos
        setInterval(function() {
            if (hasValidMaterials()) {
                const formData = collectFormData();
                localStorage.setItem('adhesion_calculator_draft', JSON.stringify({
                    data: formData,
                    timestamp: Date.now()
                }));
            }
        }, 30000);
        
        // Cargar borrador al iniciar si existe
        const draft = localStorage.getItem('adhesion_calculator_draft');
        if (draft) {
            try {
                const draftData = JSON.parse(draft);
                const ageHours = (Date.now() - draftData.timestamp) / (1000 * 60 * 60);
                
                // Solo cargar si el borrador tiene menos de 24 horas
                if (ageHours < 24 && draftData.data.materials.length > 0) {
                    setTimeout(function() {
                        if (confirm('Se encontró un borrador guardado. ¿Deseas cargarlo?')) {
                            loadMaterialsIntoForm(draftData.data.materials);
                            if (draftData.data.notes) {
                                $('#calculation_notes').val(draftData.data.notes);
                            }
                        }
                    }, 1000);
                }
            } catch (error) {
                console.log('Error cargando borrador:', error);
            }
        }
    }

    /**
     * Configurar analytics y tracking
     */
    function setupAnalytics() {
        // Track eventos importantes
        $(document).on('click', '#calculate-btn', function() {
            trackEvent('Calculator', 'Calculate', 'Button Click');
        });
        
        $(document).on('click', '#save-calculation-btn', function() {
            trackEvent('Calculator', 'Save', 'Button Click');
        });
        
        $(document).on('click', '#add-material-btn', function() {
            trackEvent('Calculator', 'Add Material', 'Button Click');
        });
    }

    /**
     * Función de tracking (implementar según analytics usado)
     */
    function trackEvent(category, action, label) {
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                event_category: category,
                event_label: label
            });
        }
        
        // O usar otra herramienta de analytics
        console.log('Analytics:', category, action, label);
    }

    /**
     * Inicializar funcionalidades avanzadas
     */
    function initAdvancedFeatures() {
        setupAdvancedValidations();
        setupKeyboardShortcuts();
        setupTooltips();
        setupLocalPersistence();
        setupAnalytics();
    }

    /**
     * Configurar accesibilidad
     */
    function setupAccessibility() {
        // ARIA labels dinámicos
        $(document).on('change', '.material-type-select', function() {
            const $this = $(this);
            const $row = $this.closest('.material-row');
            const materialName = $this.find('option:selected').text();
            
            $row.find('.material-quantity-input').attr('aria-label', 
                `Cantidad para ${materialName}`);
        });
        
        // Anuncios para lectores de pantalla
        $(document).on('click', '#calculate-btn', function() {
            announceToScreenReader('Calculando presupuesto...');
        });
        
        $(document).on('calculationComplete', function() {
            announceToScreenReader('Cálculo completado. Resultados disponibles.');
        });
    }

    /**
     * Anunciar a lectores de pantalla
     */
    function announceToScreenReader(message) {
        const $announcer = $('#screen-reader-announcer');
        if ($announcer.length === 0) {
            $('body').append('<div id="screen-reader-announcer" class="sr-only" aria-live="polite"></div>');
        }
        
        $('#screen-reader-announcer').text(message);
        
        // Limpiar después de 3 segundos
        setTimeout(function() {
            $('#screen-reader-announcer').empty();
        }, 3000);
    }

    /**
     * Configurar responsive behavior
     */
    function setupResponsiveBehavior() {
        let isMobile = window.innerWidth <= 768;
        
        $(window).on('resize', debounce(function() {
            const wasMobile = isMobile;
            isMobile = window.innerWidth <= 768;
            
            if (wasMobile !== isMobile) {
                // Ajustar layout para móvil/desktop
                adjustLayoutForDevice(isMobile);
            }
        }, 250));
        
        // Configuración inicial
        adjustLayoutForDevice(isMobile);
    }

    /**
     * Ajustar layout según dispositivo
     */
    function adjustLayoutForDevice(isMobile) {
        if (isMobile) {
            // Comportamiento específico para móvil
            $('.materials-table-container').addClass('mobile-scroll');
            
            // Scroll horizontal en tablas
            $('.materials-table').wrap('<div class="table-scroll"></div>');
        } else {
            // Comportamiento para desktop
            $('.materials-table-container').removeClass('mobile-scroll');
            $('.table-scroll').children().unwrap();
        }
    }

    /**
     * Configuración final e inicialización completa
     */
    function finalizeInitialization() {
        initAdvancedFeatures();
        setupAccessibility();
        setupResponsiveBehavior();
        
        // Trigger evento de inicialización completa
        $(document).trigger('calculatorInitialized');
        
        // Log final
        console.log('Calculadora de Adhesión completamente inicializada');
    }

    // Llamar inicialización completa cuando todo esté listo
    $(window).on('load', function() {
        if (calculatorApp.config && Object.keys(calculatorApp.config).length > 0) {
            finalizeInitialization();
        }
    });

    // Exponer funciones públicas si es necesario
    window.AdhesionCalculator = {
        recalculate: performQuickCalculation,
        reset: handleResetCalculator,
        formatCurrency: formatCurrency,
        formatTons: formatTons,
        showMessage: showMessage,
        hideMessage: hideMessage
    };

})(jQuery);