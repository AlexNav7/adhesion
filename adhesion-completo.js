

// ===== calculadora.js =====
/**
 * JavaScript para la Calculadora de AdhesiÃ³n
 * 
 * Funcionalidades:
 * - CÃ¡lculos en tiempo real
 * - GestiÃ³n dinÃ¡mica de materiales
 * - AJAX para guardar/cargar datos
 * - Validaciones automÃ¡ticas
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

    // InicializaciÃ³n cuando el DOM estÃ¡ listo
    $(document).ready(function() {
        if (typeof window.adhesionCalculatorConfig !== 'undefined') {
            calculatorApp.config = window.adhesionCalculatorConfig;
            init();
        }
    });

    /**
     * InicializaciÃ³n principal
     */
    function init() {
        // Preparar datos de materiales
        prepareMaterialsData();
        
        // Configurar event listeners
        setupEventListeners();
        
        // Configurar validaciones
        setupValidations();
        
        // Cargar historial de cÃ¡lculos
        loadRecentCalculations();
        
        // Log de inicializaciÃ³n
        console.log('Calculadora de AdhesiÃ³n inicializada');
    }

    /**
     * Preparar datos de materiales para fÃ¡cil acceso
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
        
        // GestiÃ³n de materiales
        $('#add-material-btn').on('click', addMaterialRow);
        $(document).on('click', '.remove-material-btn', removeMaterialRow);
        
        // Cambios en formulario
        $(document).on('change', '.material-type-select', handleMaterialTypeChange);
        $(document).on('input', '.material-quantity-input', handleQuantityChange);
        $(document).on('change', '#apply_discounts, #include_taxes', updateCalculationDisplay);
        
        // HistÃ³rico de cÃ¡lculos
        $(document).on('click', '.load-calculation-btn', loadPreviousCalculation);
        
        // Auto-cÃ¡lculo en tiempo real (con debounce)
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
        // ValidaciÃ³n de cantidad en tiempo real
        $(document).on('blur', '.material-quantity-input', function() {
            validateMaterialQuantity($(this));
        });
        
        // ValidaciÃ³n de selecciÃ³n de material
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
                markFieldError($quantityInput, `Cantidad mÃ¡xima: ${calculatorApp.config.config.max_quantity_per_material}t`);
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
     * Realizar cÃ¡lculo completo
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
                    showMessage('CÃ¡lculo realizado correctamente', 'success');
                } else {
                    showMessage(response.data || calculatorApp.config.messages.errorCalculating, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error en cÃ¡lculo:', error);
                showMessage(calculatorApp.config.messages.errorCalculating, 'error');
            },
            complete: function() {
                calculatorApp.isCalculating = false;
                updateCalculateButton(false);
            }
        });
    }

    /**
     * Realizar cÃ¡lculo rÃ¡pido (para preview)
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
        
        // Calcular descuentos y impuestos bÃ¡sicos
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
        
        // Actualizar preview si estÃ¡ visible
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
            discountRate += 0.15; // 15% por mÃ¡s de 100t
        } else if (totalTons >= 50) {
            discountRate += 0.10; // 10% por mÃ¡s de 50t
        } else if (totalTons >= 25) {
            discountRate += 0.05; // 5% por mÃ¡s de 25t
        }
        
        // Descuentos por importe
        if (subtotal >= 50000) {
            discountRate += 0.03; // 3% por mÃ¡s de 50kâ‚¬
        } else if (subtotal >= 25000) {
            discountRate += 0.02; // 2% por mÃ¡s de 25kâ‚¬
        }
        
        // Descuento adicional por tonelaje extremo
        if (totalTons >= 500) {
            discountRate += 0.02; // 2% adicional por mÃ¡s de 500t
        }
        
        // Limitar descuento mÃ¡ximo al 20%
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
     * Verificar si hay materiales vÃ¡lidos
     */
    function hasValidMaterials() {
        const materials = collectMaterialsData();
        return materials.length > 0;
    }

    /**
     * Mostrar resultados del cÃ¡lculo
     */
    function displayCalculationResults(calculation) {
        // Mostrar secciÃ³n de resultados
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
                        <span class="warning-icon">âš ï¸</span>
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
     * Actualizar preview de cÃ¡lculo (durante escritura)
     */
    function updateCalculationPreview(preview) {
        // Esta funciÃ³n actualiza valores en tiempo real sin mostrar toda la secciÃ³n de resultados
        // Se podrÃ­a implementar un mini-resumen que se muestre mientras el usuario escribe
    }

    /**
     * Agregar nueva fila de material
     */
    function addMaterialRow() {
        const $template = $('.material-row-template');
        const $newRow = $template.find('.material-row').clone();
        
        // Actualizar Ã­ndices y IDs
        const newIndex = calculatorApp.materialRowIndex++;
        updateRowIndexes($newRow, newIndex);
        
        // Mostrar botÃ³n de eliminar en todas las filas si hay mÃ¡s de una
        $('.remove-material-btn').show();
        
        // Agregar nueva fila
        $('#materials-container').append($newRow);
        
        // Actualizar nÃºmeros de material
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
            
            // Ocultar botÃ³n eliminar si solo queda una fila
            if ($('.material-row:visible').length === 1) {
                $('.remove-material-btn').hide();
            }
            
            // Recalcular si hay datos vÃ¡lidos
            if (hasValidMaterials()) {
                performQuickCalculation();
            }
        });
    }

    /**
     * Actualizar Ã­ndices de una fila
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
     * Actualizar nÃºmeros de materiales
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
            
            // Mostrar/actualizar cantidad mÃ­nima
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
        
        // Validar cantidad mÃ­nima
        if (material.minimum > 0 && quantity < material.minimum) {
            addMaterialAlert($alertsContainer, 'warning', 
                `${calculatorApp.config.messages.minQuantityWarning}: ${formatTons(material.minimum)}`);
            $row.addClass('has-warning');
        }
        
        // Validar cantidad mÃ¡xima
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
     * Validar selecciÃ³n de material
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
     * Guardar cÃ¡lculo
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
     * Crear contrato desde cÃ¡lculo
     */
    function handleCreateContract(e) {
        e.preventDefault();
        
        if (!calculatorApp.currentCalculation) {
            showMessage('Primero debes realizar un cÃ¡lculo', 'warning');
            return;
        }
        
        // AquÃ­ se podrÃ­a abrir un modal o redirigir a pÃ¡gina de contrato
        // Por ahora, guardamos el cÃ¡lculo y redirigimos
        if (!confirm('Â¿Deseas crear un contrato basado en este cÃ¡lculo?')) {
            return;
        }
        
        // Implementar lÃ³gica de creaciÃ³n de contrato
        showMessage('Funcionalidad de contrato en desarrollo', 'info');
    }

    /**
     * Imprimir cÃ¡lculo
     */
    function handlePrintCalculation(e) {
        e.preventDefault();
        
        if (!calculatorApp.currentCalculation) {
            return;
        }
        
        // Abrir ventana de impresiÃ³n
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
        
        // Actualizar nÃºmeros
        updateMaterialNumbers();
        
        // Limpiar mensajes
        hideMessage();
        
        showMessage('Calculadora reiniciada', 'info');
    }

    /**
     * Cargar cÃ¡lculos recientes
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
                    $('#recent-calculations').html('<p>No hay cÃ¡lculos recientes</p>');
                }
            },
            error: function() {
                $('#recent-calculations').html('<p>Error cargando historial</p>');
            }
        });
    }

    /**
     * Mostrar cÃ¡lculos recientes
     */
    function displayRecentCalculations(calculations) {
        const $container = $('#recent-calculations');
        
        if (!calculations || calculations.length === 0) {
            $container.html('<p>No hay cÃ¡lculos recientes</p>');
            return;
        }
        
        let html = '';
        calculations.forEach(function(calc) {
            html += `
                <div class="calculation-history-item">
                    <div class="history-info">
                        <h5>${calc.total_tons} toneladas</h5>
                        <div class="history-meta">
                            ${new Date(calc.created_at).toLocaleDateString('es-ES')} â€¢ 
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
     * Cargar cÃ¡lculo previo
     */
    function loadPreviousCalculation(e) {
        e.preventDefault();
        
        const calcId = $(this).data('calc-id');
        
        if (!confirm('Â¿Cargar este cÃ¡lculo? Se perderÃ¡n los datos actuales.')) {
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
                    showMessage('CÃ¡lculo cargado correctamente', 'success');
                } else {
                    showMessage('Error cargando el cÃ¡lculo', 'error');
                }
            },
            error: function() {
                showMessage('Error cargando el cÃ¡lculo', 'error');
            }
        });
    }

    /**
     * Cargar datos de cÃ¡lculo en el formulario
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
            
            // Mostrar resultados si estÃ¡n disponibles
            if (calculation.calculation_data) {
                const calcData = JSON.parse(calculation.calculation_data);
                calculatorApp.currentCalculation = calcData;
                displayCalculationResults(calcData);
            }
            
        } catch (error) {
            console.error('Error cargando datos del cÃ¡lculo:', error);
            showMessage('Error procesando los datos del cÃ¡lculo', 'error');
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
     * Actualizar botÃ³n de calcular
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
     * Actualizar botÃ³n de guardar
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
        
        // Auto-hide para mensajes de Ã©xito e info
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
        // ValidaciÃ³n de nÃºmeros decimales
        $(document).on('input', '.material-quantity-input', function() {
            const value = this.value;
            // Permitir solo nÃºmeros y punto decimal
            this.value = value.replace(/[^0-9.]/g, '');
            
            // Evitar mÃºltiples puntos decimales
            const parts = this.value.split('.');
            if (parts.length > 2) {
                this.value = parts[0] + '.' + parts.slice(1).join('');
            }
        });
        
        // ValidaciÃ³n de paste
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
     * Configurar tooltips dinÃ¡micos
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
                $this.attr('title', `${quantity}t Ã— ${price} = ${$this.find('.material-total').text()}`);
            }
        });
    }

    /**
     * Configurar persistencia local (opcional)
     */
    function setupLocalPersistence() {
        // Guardar borrador automÃ¡ticamente cada 30 segundos
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
                        if (confirm('Se encontrÃ³ un borrador guardado. Â¿Deseas cargarlo?')) {
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
     * FunciÃ³n de tracking (implementar segÃºn analytics usado)
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
        // ARIA labels dinÃ¡micos
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
            announceToScreenReader('CÃ¡lculo completado. Resultados disponibles.');
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
        
        // Limpiar despuÃ©s de 3 segundos
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
                // Ajustar layout para mÃ³vil/desktop
                adjustLayoutForDevice(isMobile);
            }
        }, 250));
        
        // ConfiguraciÃ³n inicial
        adjustLayoutForDevice(isMobile);
    }

    /**
     * Ajustar layout segÃºn dispositivo
     */
    function adjustLayoutForDevice(isMobile) {
        if (isMobile) {
            // Comportamiento especÃ­fico para mÃ³vil
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
     * ConfiguraciÃ³n final e inicializaciÃ³n completa
     */
    function finalizeInitialization() {
        initAdvancedFeatures();
        setupAccessibility();
        setupResponsiveBehavior();
        
        // Trigger evento de inicializaciÃ³n completa
        $(document).trigger('calculatorInitialized');
        
        // Log final
        console.log('Calculadora de AdhesiÃ³n completamente inicializada');
    }

    // Llamar inicializaciÃ³n completa cuando todo estÃ© listo
    $(window).on('load', function() {
        if (calculatorApp.config && Object.keys(calculatorApp.config).length > 0) {
            finalizeInitialization();
        }
    });

    // Exponer funciones pÃºblicas si es necesario
    window.AdhesionCalculator = {
        recalculate: performQuickCalculation,
        reset: handleResetCalculator,
        formatCurrency: formatCurrency,
        formatTons: formatTons,
        showMessage: showMessage,
        hideMessage: hideMessage
    };

})(jQuery);


// ===== frontend.js =====
/**
 * JavaScript del Frontend para el plugin AdhesiÃ³n
 * Archivo: assets/js/frontend.js
 * Maneja toda la interactividad del dashboard y formularios
 */

(function($) {
    'use strict';

    // Variables globales
    let currentTab = 'overview';
    let isProcessing = false;

    /**
     * InicializaciÃ³n cuando el DOM estÃ¡ listo
     */
    $(document).ready(function() {
        initTabs();
        initModals();
        initForms();
        initButtons();
        initCalculatorButtons();
        initContractButtons();
        initProfileForm();
        initDataTables();
        
        // Mostrar mensajes si los hay
        showFlashMessages();
        
        console.log('AdhesiÃ³n Frontend initialized');
    });

    /**
     * ================================
     * SISTEMA DE PESTAÃ‘AS
     * ================================
     */
    function initTabs() {
        // Cambio de pestaÃ±as
        $('.tab-button').on('click', function(e) {
            e.preventDefault();
            
            const tabId = $(this).data('tab');
            if (tabId && tabId !== currentTab) {
                switchTab(tabId);
            }
        });

        // Enlaces que cambian pestaÃ±as
        $('[data-tab-link]').on('click', function(e) {
            e.preventDefault();
            
            const tabId = $(this).data('tab-link');
            if (tabId) {
                switchTab(tabId);
            }
        });

        // Detectar hash en URL para pestaÃ±a inicial
        const hash = window.location.hash.substring(1);
        if (hash && $('.tab-button[data-tab="' + hash + '"]').length) {
            switchTab(hash);
        }
    }

    function switchTab(tabId) {
        // Actualizar botones
        $('.tab-button').removeClass('active');
        $('.tab-button[data-tab="' + tabId + '"]').addClass('active');

        // Actualizar contenido
        $('.tab-content').removeClass('active');
        $('#tab-' + tabId).addClass('active').addClass('fade-in-up');

        currentTab = tabId;

        // Actualizar URL sin recargar
        if (history.pushState) {
            history.pushState(null, null, '#' + tabId);
        }

        // Disparar evento personalizado
        $(document).trigger('adhesion:tab-changed', [tabId]);
    }

    /**
     * ================================
     * SISTEMA DE MODALES
     * ================================
     */
    function initModals() {
        // Cerrar modal con X
        $('.modal-close').on('click', function() {
            closeModal();
        });

        // Cerrar modal con clic fuera
        $('.adhesion-modal').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Cerrar modal con ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    function openModal(modalId, content = null) {
        const $modal = $('#' + modalId);
        
        if (content) {
            $modal.find('.modal-body').html(content);
        }
        
        $modal.addClass('active');
        $('body').addClass('modal-open');
        
        // Focus trap
        $modal.find('input, button, select, textarea').first().focus();
    }

    function closeModal() {
        $('.adhesion-modal').removeClass('active');
        $('body').removeClass('modal-open');
    }

    /**
     * ================================
     * BOTONES DE ACCIÃ“N
     * ================================
     */
    function initButtons() {
        // Ver detalles de cÃ¡lculo
        $(document).on('click', '.view-calc, .view-calc-details', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            const calcId = $(this).data('calc-id');
            if (calcId) {
                loadCalculationDetails(calcId);
            }
        });

        // Ver detalles de contrato
        $(document).on('click', '.view-contract-details', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            const contractId = $(this).data('contract-id');
            if (contractId) {
                loadContractDetails(contractId);
            }
        });

        // Contratar cÃ¡lculo
        $(document).on('click', '.contract-calc', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            const calcId = $(this).data('calc-id');
            if (calcId && confirm(adhesionAjax.messages.confirmContract || 'Â¿Proceder con la contrataciÃ³n?')) {
                processCalculationContract(calcId);
            }
        });
    }

    function initCalculatorButtons() {
        // Botones para nueva calculadora
        $('#nueva-calculadora, #nueva-calculadora-tab, #primera-calculadora').on('click', function(e) {
            e.preventDefault();
            
            // Redirigir a pÃ¡gina de calculadora o mostrar modal
            const calculatorUrl = $(this).data('calculator-url') || '/calculadora/';
            window.location.href = calculatorUrl;
        });
    }

    function initContractButtons() {
        // Firmar contrato
        $(document).on('click', '.sign-contract, .sign-contract-btn', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            const contractId = $(this).data('contract-id');
            if (contractId) {
                initiateContractSigning(contractId);
            }
        });
    }

    /**
     * ================================
     * FORMULARIOS
     * ================================
     */
    function initForms() {
        // ValidaciÃ³n en tiempo real
        $('.adhesion-form input, .adhesion-form select, .adhesion-form textarea').on('blur', function() {
            validateField($(this));
        });

        // Prevenir envÃ­o con Enter en campos que no deben
        $('.adhesion-form input:not([type="submit"])').on('keypress', function(e) {
            if (e.which === 13 && !$(this).is('textarea')) {
                e.preventDefault();
            }
        });
    }

    function initProfileForm() {
        $('#adhesion-profile-form').on('submit', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            if (validateProfileForm()) {
                updateUserProfile();
            }
        });

        // Cancelar ediciÃ³n
        $('#cancel-profile-edit').on('click', function(e) {
            e.preventDefault();
            resetProfileForm();
        });

        // ValidaciÃ³n de contraseÃ±as
        $('#new_password, #confirm_password').on('input', function() {
            validatePasswords();
        });
    }

    function validateField($field) {
        const value = $field.val().trim();
        const fieldType = $field.attr('type');
        const isRequired = $field.prop('required');
        let isValid = true;
        let message = '';

        // Limpiar errores previos
        $field.removeClass('error');
        $field.next('.field-error').remove();

        // Validar campos requeridos
        if (isRequired && !value) {
            isValid = false;
            message = 'Este campo es obligatorio.';
        }
        // Validar email
        else if (fieldType === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            message = 'Por favor, introduce un email vÃ¡lido.';
        }
        // Validar telÃ©fono
        else if ($field.attr('name') === 'phone' && value && !isValidPhone(value)) {
            isValid = false;
            message = 'Por favor, introduce un telÃ©fono vÃ¡lido.';
        }

        if (!isValid) {
            $field.addClass('error');
            $field.after('<div class="field-error">' + message + '</div>');
        }

        return isValid;
    }

    function validateProfileForm() {
        let isValid = true;
        
        // Validar todos los campos
        $('#adhesion-profile-form input, #adhesion-profile-form select').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        // Validar contraseÃ±as si estÃ¡n llenas
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword || confirmPassword) {
            if (newPassword !== confirmPassword) {
                $('#confirm_password').addClass('error');
                $('#confirm_password').after('<div class="field-error">Las contraseÃ±as no coinciden.</div>');
                isValid = false;
            }
            
            if (newPassword.length < 6) {
                $('#new_password').addClass('error');
                $('#new_password').after('<div class="field-error">La contraseÃ±a debe tener al menos 6 caracteres.</div>');
                isValid = false;
            }
        }

        return isValid;
    }

    function validatePasswords() {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        $('.password-strength').remove();
        
        if (newPassword) {
            let strength = getPasswordStrength(newPassword);
            let strengthClass = 'weak';
            let strengthText = 'DÃ©bil';
            
            if (strength >= 3) {
                strengthClass = 'strong';
                strengthText = 'Fuerte';
            } else if (strength >= 2) {
                strengthClass = 'medium';
                strengthText = 'Media';
            }
            
            $('#new_password').after(
                '<div class="password-strength ' + strengthClass + '">Fortaleza: ' + strengthText + '</div>'
            );
        }
        
        if (confirmPassword && newPassword !== confirmPassword) {
            $('#confirm_password').addClass('error');
        } else {
            $('#confirm_password').removeClass('error');
        }
    }

    /**
     * ================================
     * CARGAR DATOS VIA AJAX
     * ================================
     */
    function loadCalculationDetails(calcId) {
        if (!calcId) return;
        
        isProcessing = true;
        showLoading('Cargando detalles del cÃ¡lculo...');

        $.ajax({
            url: adhesionAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_get_calculation_details',
                calculation_id: calcId,
                nonce: adhesionAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const content = buildCalculationDetailsContent(response.data);
                    openModal('calculation-modal', content);
                } else {
                    showMessage('error', response.data || 'Error al cargar los detalles.');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexiÃ³n. Por favor, intÃ©ntalo de nuevo.');
            },
            complete: function() {
                isProcessing = false;
                hideLoading();
            }
        });
    }

    function loadContractDetails(contractId) {
        if (!contractId) return;
        
        isProcessing = true;
        showLoading('Cargando detalles del contrato...');

        $.ajax({
            url: adhesionAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_get_contract_details',
                contract_id: contractId,
                nonce: adhesionAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const content = buildContractDetailsContent(response.data);
                    openModal('contract-modal', content);
                } else {
                    showMessage('error', response.data || 'Error al cargar los detalles.');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexiÃ³n. Por favor, intÃ©ntalo de nuevo.');
            },
            complete: function() {
                isProcessing = false;
                hideLoading();
            }
        });
    }

    function processCalculationContract(calcId) {
        if (!calcId) return;
        
        isProcessing = true;
        showLoading('Procesando contrataciÃ³n...');

        $.ajax({
            url: adhesionAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_process_calculation_contract',
                calculation_id: calcId,
                nonce: adhesionAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', 'ContrataciÃ³n procesada correctamente.');
                    
                    // Redirigir a proceso de pago
                    if (response.data.payment_url) {
                        setTimeout(function() {
                            window.location.href = response.data.payment_url;
                        }, 1500);
                    } else {
                        // Recargar pestaÃ±a actual
                        location.reload();
                    }
                } else {
                    showMessage('error', response.data || 'Error al procesar la contrataciÃ³n.');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexiÃ³n. Por favor, intÃ©ntalo de nuevo.');
            },
            complete: function() {
                isProcessing = false;
                hideLoading();
            }
        });
    }

    function initiateContractSigning(contractId) {
        if (!contractId) return;
        
        isProcessing = true;
        showLoading('Preparando firma digital...');

        $.ajax({
            url: adhesionAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_initiate_contract_signing',
                contract_id: contractId,
                nonce: adhesionAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', 'Firma digital iniciada. Revisa tu email.');
                    
                    // Redirigir a DocuSign
                    if (response.data.signing_url) {
                        setTimeout(function() {
                            window.open(response.data.signing_url, '_blank');
                        }, 1500);
                    }
                } else {
                    showMessage('error', response.data || 'Error al iniciar la firma.');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexiÃ³n. Por favor, intÃ©ntalo de nuevo.');
            },
            complete: function() {
                isProcessing = false;
                hideLoading();
            }
        });
    }

    function updateUserProfile() {
        isProcessing = true;
        showLoading('Actualizando perfil...');

        const formData = $('#adhesion-profile-form').serialize();

        $.ajax({
            url: adhesionAjax.ajaxUrl,
            type: 'POST',
            data: formData + '&action=adhesion_update_user_profile',
            success: function(response) {
                if (response.success) {
                    showMessage('success', 'Perfil actualizado correctamente.');
                    
                    // Actualizar datos mostrados si es necesario
                    if (response.data.display_name) {
                        $('.user-welcome h1').text('Bienvenido, ' + response.data.display_name);
                    }
                } else {
                    showMessage('error', response.data || 'Error al actualizar el perfil.');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexiÃ³n. Por favor, intÃ©ntalo de nuevo.');
            },
            complete: function() {
                isProcessing = false;
                hideLoading();
            }
        });
    }

    /**
     * ================================
     * CONSTRUCCIÃ“N DE CONTENIDO
     * ================================
     */
    function buildCalculationDetailsContent(data) {
        let content = '<div class="calculation-details">';
        
        content += '<div class="detail-section">';
        content += '<h4>InformaciÃ³n General</h4>';
        content += '<div class="detail-grid">';
        content += '<div class="detail-item"><label>Material:</label> <span>' + escapeHtml(data.material_type || '') + '</span></div>';
        content += '<div class="detail-item"><label>Cantidad:</label> <span>' + formatNumber(data.quantity || 0) + ' kg</span></div>';
        content += '<div class="detail-item"><label>Precio por kg:</label> <span>' + formatPrice(data.price_per_kg || 0) + '</span></div>';
        content += '<div class="detail-item"><label>Total:</label> <span class="total-price">' + formatPrice(data.total_price || 0) + '</span></div>';
        content += '</div>';
        content += '</div>';

        if (data.additional_details) {
            content += '<div class="detail-section">';
            content += '<h4>Detalles Adicionales</h4>';
            content += '<div class="detail-text">' + escapeHtml(data.additional_details) + '</div>';
            content += '</div>';
        }

        content += '<div class="detail-section">';
        content += '<h4>InformaciÃ³n de CÃ¡lculo</h4>';
        content += '<div class="detail-grid">';
        content += '<div class="detail-item"><label>Fecha:</label> <span>' + formatDate(data.created_at || '') + '</span></div>';
        content += '<div class="detail-item"><label>Estado:</label> <span class="status-badge status-' + (data.status || 'calculated') + '">' + getStatusText(data.status || 'calculated') + '</span></div>';
        content += '</div>';
        content += '</div>';

        if ((data.status || 'calculated') === 'calculated') {
            content += '<div class="detail-actions">';
            content += '<button type="button" class="button button-primary contract-calc" data-calc-id="' + data.id + '">Proceder con ContrataciÃ³n</button>';
            content += '</div>';
        }

        content += '</div>';
        
        return content;
    }

    function buildContractDetailsContent(data) {
        let content = '<div class="contract-details">';
        
        content += '<div class="detail-section">';
        content += '<h4>InformaciÃ³n del Contrato</h4>';
        content += '<div class="detail-grid">';
        content += '<div class="detail-item"><label>Tipo:</label> <span>' + escapeHtml(data.contract_type || '') + '</span></div>';
        content += '<div class="detail-item"><label>Estado:</label> <span class="status-badge status-' + (data.status || 'pending') + '">' + getStatusText(data.status || 'pending') + '</span></div>';
        content += '<div class="detail-item"><label>Monto:</label> <span>' + formatPrice(data.amount || 0) + '</span></div>';
        content += '<div class="detail-item"><label>Fecha de CreaciÃ³n:</label> <span>' + formatDate(data.created_at || '') + '</span></div>';
        content += '</div>';
        content += '</div>';

        if (data.signed_at) {
            content += '<div class="detail-section">';
            content += '<h4>InformaciÃ³n de Firma</h4>';
            content += '<div class="detail-grid">';
            content += '<div class="detail-item"><label>Firmado el:</label> <span>' + formatDate(data.signed_at) + '</span></div>';
            if (data.docusign_envelope_id) {
                content += '<div class="detail-item"><label>ID de Sobre:</label> <span>' + escapeHtml(data.docusign_envelope_id) + '</span></div>';
            }
            content += '</div>';
            content += '</div>';
        }

        if (data.contract_data) {
            content += '<div class="detail-section">';
            content += '<h4>Datos del Contrato</h4>';
            content += '<div class="contract-preview-mini">' + escapeHtml(data.contract_data.substring(0, 300)) + '...</div>';
            content += '</div>';
        }

        content += '<div class="detail-actions">';
        
        if (data.status === 'pending') {
            content += '<button type="button" class="button button-primary sign-contract-btn" data-contract-id="' + data.id + '">Firmar Contrato</button>';
        }
        
        if (data.status === 'signed' && data.signed_document_url) {
            content += '<a href="' + escapeHtml(data.signed_document_url) + '" class="button" target="_blank">Descargar Contrato Firmado</a>';
        }
        
        content += '<button type="button" class="button" onclick="closeModal()">Cerrar</button>';
        content += '</div>';

        content += '</div>';
        
        return content;
    }

    /**
     * ================================
     * TABLAS DE DATOS
     * ================================
     */
    function initDataTables() {
        // Ordenamiento de tablas
        $('.adhesion-table th').on('click', function() {
            const $table = $(this).closest('table');
            const columnIndex = $(this).index();
            const isAscending = !$(this).hasClass('sort-desc');
            
            sortTable($table, columnIndex, isAscending);
            
            // Actualizar indicadores visuales
            $table.find('th').removeClass('sort-asc sort-desc');
            $(this).addClass(isAscending ? 'sort-asc' : 'sort-desc');
        });

        // Filtrado de tablas
        $('.table-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $table = $(this).data('table-target');
            
            if ($table && $table.length) {
                filterTable($table, searchTerm);
            }
        });
    }

    function sortTable($table, columnIndex, ascending = true) {
        const $tbody = $table.find('tbody');
        const rows = $tbody.find('tr').toArray();
        
        rows.sort(function(a, b) {
            const aText = $(a).find('td').eq(columnIndex).text().trim();
            const bText = $(b).find('td').eq(columnIndex).text().trim();
            
            // Detectar si es nÃºmero
            const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return ascending ? aNum - bNum : bNum - aNum;
            }
            
            // Detectar si es fecha
            const aDate = new Date(aText);
            const bDate = new Date(bText);
            
            if (!isNaN(aDate.getTime()) && !isNaN(bDate.getTime())) {
                return ascending ? aDate - bDate : bDate - aDate;
            }
            
            // ComparaciÃ³n de texto
            const comparison = aText.localeCompare(bText);
            return ascending ? comparison : -comparison;
        });
        
        $tbody.empty().append(rows);
    }

    function filterTable($table, searchTerm) {
        $table.find('tbody tr').each(function() {
            const rowText = $(this).text().toLowerCase();
            const shouldShow = rowText.includes(searchTerm);
            $(this).toggle(shouldShow);
        });
    }

    /**
     * ================================
     * UTILIDADES Y HELPERS
     * ================================
     */
    function showMessage(type, message, duration = 5000) {
        // Remover mensajes anteriores
        $('.adhesion-message').remove();
        
        const messageHtml = `
            <div class="adhesion-message ${type} fade-in-up">
                <span class="dashicons dashicons-${getMessageIcon(type)}"></span>
                ${escapeHtml(message)}
                <button type="button" class="message-close" onclick="$(this).parent().remove()">&times;</button>
            </div>
        `;
        
        $('.adhesion-user-account').prepend(messageHtml);
        
        // Auto-ocultar despuÃ©s del tiempo especificado
        if (duration > 0) {
            setTimeout(function() {
                $('.adhesion-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
        }
        
        // Scroll suave al mensaje
        $('html, body').animate({
            scrollTop: $('.adhesion-message').offset().top - 20
        }, 300);
    }

    function showFlashMessages() {
        // Mostrar mensajes que vienen del servidor
        if (window.adhesionMessages && window.adhesionMessages.length) {
            window.adhesionMessages.forEach(function(msg) {
                showMessage(msg.type, msg.message, 0);
            });
        }
    }

    function showLoading(message = 'Cargando...') {
        // Remover loader anterior
        $('.adhesion-loading-overlay').remove();
        
        const loadingHtml = `
            <div class="adhesion-loading-overlay">
                <div class="loading-content">
                    <div class="adhesion-spinner"></div>
                    <div class="loading-text">${escapeHtml(message)}</div>
                </div>
            </div>
        `;
        
        $('body').append(loadingHtml);
    }

    function hideLoading() {
        $('.adhesion-loading-overlay').fadeOut(300, function() {
            $(this).remove();
        });
    }

    function resetProfileForm() {
        const $form = $('#adhesion-profile-form');
        
        // Recargar valores originales (esto deberÃ­a implementarse segÃºn tus necesidades)
        $form[0].reset();
        
        // Limpiar errores
        $form.find('.error').removeClass('error');
        $form.find('.field-error, .password-strength').remove();
        
        showMessage('info', 'Cambios cancelados.');
    }

    function getMessageIcon(type) {
        const icons = {
            'success': 'yes-alt',
            'error': 'dismiss',
            'warning': 'warning',
            'info': 'info'
        };
        return icons[type] || 'info';
    }

    function getStatusText(status) {
        const statusTexts = {
            'calculated': 'Calculado',
            'contracted': 'Contratado',
            'pending': 'Pendiente',
            'signed': 'Firmado',
            'cancelled': 'Cancelado'
        };
        return statusTexts[status] || status;
    }

    function formatPrice(amount) {
        if (typeof amount === 'string') {
            amount = parseFloat(amount);
        }
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount || 0);
    }

    function formatNumber(number) {
        if (typeof number === 'string') {
            number = parseFloat(number);
        }
        return new Intl.NumberFormat('es-ES').format(number || 0);
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        return new Intl.DateTimeFormat('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function isValidPhone(phone) {
        const re = /^[\+]?[0-9\-\(\)\s]{9,}$/;
        return re.test(phone.replace(/\s/g, ''));
    }

    function getPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^A-Za-z0-9]/)) strength++;
        
        return strength;
    }

    /**
     * ================================
     * AUTO-REFRESH Y POLLING
     * ================================
     */
    function initAutoRefresh() {
        // Auto-refresh para contratos pendientes cada 30 segundos
        if ($('.status-pending').length > 0) {
            setInterval(function() {
                refreshContractStatus();
            }, 30000);
        }
    }

    function refreshContractStatus() {
        $.ajax({
            url: adhesionAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_check_contract_status',
                nonce: adhesionAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data.hasUpdates) {
                    // Mostrar notificaciÃ³n de actualizaciÃ³n
                    showMessage('info', 'Hay actualizaciones en tus contratos. Recargando...', 2000);
                    
                    // Recargar pÃ¡gina despuÃ©s de 2 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            },
            error: function() {
                // Silenciosamente ignorar errores de polling
                console.log('Error checking contract status');
            }
        });
    }

    /**
     * ================================
     * EVENTOS PERSONALIZADOS
     * ================================
     */
    $(document).on('adhesion:tab-changed', function(e, tabId) {
        // Cargar datos especÃ­ficos de la pestaÃ±a si es necesario
        switch(tabId) {
            case 'calculations':
                // Refresh calculations if needed
                break;
            case 'contracts':
                // Refresh contracts if needed
                break;
            case 'profile':
                // Focus en primer campo del formulario
                $('#display_name').focus();
                break;
        }
    });

    /**
     * ================================
     * INICIALIZACIÃ“N ADICIONAL
     * ================================
     */
    $(window).on('load', function() {
        // Inicializar auto-refresh si hay contratos pendientes
        initAutoRefresh();
        
        // Mejorar experiencia con animaciones
        $('.stat-card, .adhesion-card').each(function(i) {
            $(this).delay(i * 100).queue(function() {
                $(this).addClass('slide-in-right').dequeue();
            });
        });
    });

    /**
     * ================================
     * EXPORTAR FUNCIONES GLOBALES
     * ================================
     */
    window.AdhesionFrontend = {
        showMessage: showMessage,
        openModal: openModal,
        closeModal: closeModal,
        switchTab: switchTab,
        refreshData: function() {
            location.reload();
        }
    };

    // Agregar estilos CSS adicionales dinÃ¡micamente
    const additionalCSS = `
        .adhesion-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(4px);
        }
        
        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .loading-text {
            margin-top: 1rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .field-error {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .password-strength {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        
        .password-strength.weak {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .password-strength.medium {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .password-strength.strong {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .message-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            float: right;
            font-size: 1.2rem;
            margin-left: 1rem;
            opacity: 0.7;
        }
        
        .message-close:hover {
            opacity: 1;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .detail-item label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .detail-item span {
            color: #1e293b;
        }
        
        .detail-item .total-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #10b981;
        }
        
        .detail-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-section h4 {
            margin: 0 0 1rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .detail-text {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            white-space: pre-wrap;
        }
        
        .detail-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .contract-preview-mini {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .adhesion-table th.sort-asc::after {
            content: ' â†‘';
            color: #2563eb;
        }
        
        .adhesion-table th.sort-desc::after {
            content: ' â†“';
            color: #2563eb;
        }
        
        .adhesion-table th {
            cursor: pointer;
            user-select: none;
        }
        
        .adhesion-table th:hover {
            background: #e2e8f0;
        }
        
        body.modal-open {
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-actions {
                flex-direction: column;
            }
            
            .loading-content {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    `;

    // Inyectar estilos adicionales
    if (!$('#adhesion-dynamic-styles').length) {
        $('<style id="adhesion-dynamic-styles">' + additionalCSS + '</style>').appendTo('head');
    }

})(jQuery);
