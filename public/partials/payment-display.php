                                        clearInterval(paymentCheckInterval);
                                        window.location.reload(); // Recargar para mostrar confirmación
                                    } else if (response.data.status === 'failed') {
                                        clearInterval(paymentCheckInterval);
                                        showPaymentError(response.data.message || 'Error en el pago');
                                    }
                                }
                            }
                        });
                    }
                    
                    function showPaymentError(message) {
                        document.getElementById('payment-processing').innerHTML = 
                            '<div class="payment-error">' +
                            '<div class="error-icon">❌</div>' +
                            '<h3>Error en el pago</h3>' +
                            '<p>' + message + '</p>' +
                            '<a href="<?php echo get_permalink(); ?>" class="adhesion-btn adhesion-btn-primary">Intentar de nuevo</a>' +
                            '</div>';
                    }
                </script>
            </div>
            
        <?php elseif ($payment_step === 'complete'): ?>
            <!-- PASO 4: Pago completado -->
            <div class="payment-step-content" id="payment-complete">
                <div class="success-content">
                    <div class="success-icon">
                        <div class="checkmark">✓</div>
                    </div>
                    <h3 class="success-title"><?php _e('¡Pago realizado con éxito!', 'adhesion'); ?></h3>
                    <p class="success-description">
                        <?php _e('Tu pedido ha sido procesado correctamente. En breve recibirás un email con la confirmación.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Información del pedido completado -->
                    <div class="order-completed-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label"><?php _e('Número de contrato:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo esc_html($contract['contract_number'] ?? 'Generando...'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Importe pagado:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo adhesion_format_price($total_amount); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Fecha de pago:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo date('d/m/Y H:i'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Referencia:', 'adhesion'); ?></span>
                                <span class="info-value"><?php echo esc_html($contract['payment_reference'] ?? ''); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Próximos pasos -->
                    <div class="next-steps">
                        <h4><?php _e('Próximos pasos:', 'adhesion'); ?></h4>
                        <ol>
                            <li><?php _e('Recibirás un email con la confirmación del pago', 'adhesion'); ?></li>
                            <li><?php _e('Te enviaremos el contrato para firma digital', 'adhesion'); ?></li>
                            <li><?php _e('Una vez firmado, coordinaremos la entrega', 'adhesion'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Acciones después del pago -->
                    <div class="post-payment-actions">
                        <a href="<?php echo home_url('/mi-cuenta/'); ?>" class="adhesion-btn adhesion-btn-primary">
                            <span class="dashicons dashicons-admin-home"></span>
                            <?php _e('Ir a Mi Cuenta', 'adhesion'); ?>
                        </a>
                        
                        <?php if (adhesion_is_docusign_configured()): ?>
                            <button type="button" id="sign-contract-btn" class="adhesion-btn adhesion-btn-success">
                                <span class="dashicons dashicons-media-document"></span>
                                <?php _e('Firmar Contrato Ahora', 'adhesion'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo home_url('/calculadora/'); ?>" class="adhesion-btn adhesion-btn-outline">
                            <?php _e('Nuevo Cálculo', 'adhesion'); ?>
                        </a>
                    </div>
                    
                    <!-- Información de contacto -->
                    <div class="contact-info">
                        <h5><?php _e('¿Necesitas ayuda?', 'adhesion'); ?></h5>
                        <p><?php _e('Si tienes alguna duda sobre tu pedido, puedes contactarnos:', 'adhesion'); ?></p>
                        <div class="contact-methods">
                            <span>📞 <?php echo adhesion_get_option('support_phone', '900 123 456'); ?></span>
                            <span>📧 <?php echo adhesion_get_option('support_email', get_option('admin_email')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>

    <!-- Formulario oculto para Redsys -->
    <div id="redsys-form-container" style="display: none;">
        <form id="redsys-payment-form" method="POST" target="_blank">
            <input type="hidden" name="Ds_SignatureVersion" value="HMAC_SHA256_V1">
            <input type="hidden" name="Ds_MerchantParameters" value="">
            <input type="hidden" name="Ds_Signature" value="">
        </form>
    </div>

    <!-- Modal de carga -->
    <div id="payment-loading-modal" class="adhesion-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-body">
                <div class="loading-content">
                    <div class="spinner-large"></div>
                    <h3><?php _e('Preparando pago...', 'adhesion'); ?></h3>
                    <p><?php _e('Por favor, espera mientras preparamos tu pago seguro.', 'adhesion'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript específico para el proceso de pago -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Configuración global del pago
    window.adhesionPaymentConfig = {
        contractId: <?php echo $contract_id; ?>,
        calculationId: <?php echo $calculation_id; ?>,
        totalAmount: <?php echo $total_amount; ?>,
        currentStep: '<?php echo $payment_step; ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
        messages: {
            savingData: '<?php echo esc_js(__('Guardando datos...', 'adhesion')); ?>',
            dataRequired: '<?php echo esc_js(__('Por favor, completa todos los campos obligatorios.', 'adhesion')); ?>',
            preparingPayment: '<?php echo esc_js(__('Preparando pago...', 'adhesion')); ?>',
            paymentError: '<?php echo esc_js(__('Error procesando el pago. Inténtalo de nuevo.', 'adhesion')); ?>',
            invalidDNI: '<?php echo esc_js(__('El DNI/NIE no es válido.', 'adhesion')); ?>',
            invalidPhone: '<?php echo esc_js(__('El teléfono no es válido.', 'adhesion')); ?>',
            invalidPostal: '<?php echo esc_js(__('El código postal no es válido.', 'adhesion')); ?>'
        }
    };
    
    // Event listeners para el formulario de datos
    $('#save-client-data-btn').on('click', function(e) {
        e.preventDefault();
        saveClientData();
    });
    
    // Event listeners para revisión
    $('#proceed-to-payment-btn').on('click', function(e) {
        e.preventDefault();
        createPayment();
    });
    
    $('#edit-client-data-btn').on('click', function(e) {
        e.preventDefault();
        goToStep('form');
    });
    
    $('#back-to-form-btn').on('click', function(e) {
        e.preventDefault();
        goToStep('form');
    });
    
    // Event listener para firma de contrato
    $('#sign-contract-btn').on('click', function(e) {
        e.preventDefault();
        startContractSigning();
    });
    
    // Validaciones en tiempo real
    setupRealtimeValidation();
    
    /**
     * Guardar datos del cliente
     */
    function saveClientData() {
        // Validar formulario
        if (!validateClientForm()) {
            return;
        }
        
        const formData = collectClientFormData();
        const $btn = $('#save-client-data-btn');
        
        // Mostrar estado de carga
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionPaymentConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_save_client_data',
                nonce: window.adhesionPaymentConfig.nonce,
                contract_id: window.adhesionPaymentConfig.contractId,
                calculation_id: window.adhesionPaymentConfig.calculationId,
                client_data: formData
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar contract_id si se creó uno nuevo
                    if (response.data.contract_id) {
                        window.adhesionPaymentConfig.contractId = response.data.contract_id;
                    }
                    
                    // Ir al paso de revisión
                    goToStep('review');
                } else {
                    showPaymentMessage(response.data || window.adhesionPaymentConfig.messages.paymentError, 'error');
                }
            },
            error: function() {
                showPaymentMessage(window.adhesionPaymentConfig.messages.paymentError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Crear pago en Redsys
     */
    function createPayment() {
        const $btn = $('#proceed-to-payment-btn');
        
        // Mostrar modal de carga
        $('#payment-loading-modal').show();
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionPaymentConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_create_payment',
                nonce: window.adhesionPaymentConfig.nonce,
                contract_id: window.adhesionPaymentConfig.contractId,
                amount: window.adhesionPaymentConfig.totalAmount
            },
            success: function(response) {
                if (response.success) {
                    // Enviar formulario a Redsys
                    submitRedsysForm(response.data);
                } else {
                    $('#payment-loading-modal').hide();
                    showPaymentMessage(response.data || window.adhesionPaymentConfig.messages.paymentError, 'error');
                }
            },
            error: function() {
                $('#payment-loading-modal').hide();
                showPaymentMessage(window.adhesionPaymentConfig.messages.paymentError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Enviar formulario a Redsys
     */
    function submitRedsysForm(paymentData) {
        const $form = $('#redsys-payment-form');
        
        // Configurar formulario
        $form.attr('action', paymentData.form_url);
        $form.find('input[name="Ds_MerchantParameters"]').val(paymentData.merchant_parameters);
        $form.find('input[name="Ds_Signature"]').val(paymentData.signature);
        
        // Ocultar modal y enviar
        $('#payment-loading-modal').hide();
        
        // Cambiar a paso de procesamiento
        goToStep('processing');
        
        // Enviar formulario (abre en nueva ventana)
        $form.submit();
    }
    
    /**
     * Iniciar proceso de firma de contrato
     */
    function startContractSigning() {
        const $btn = $('#sign-contract-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionPaymentConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_start_signing',
                nonce: window.adhesionPaymentConfig.nonce,
                contract_id: window.adhesionPaymentConfig.contractId
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    // Abrir DocuSign en nueva ventana
                    window.open(response.data.url, '_blank');
                } else {
                    showPaymentMessage(response.data || 'Error iniciando firma', 'error');
                }
            },
            error: function() {
                showPaymentMessage('Error iniciando firma', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Validar formulario del cliente
     */
    function validateClientForm() {
        let isValid = true;
        
        // Limpiar errores previos
        $('.form-group').removeClass('has-error');
        $('.error-message').remove();
        
        // Validar campos requeridos
        $('input[required], textarea[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                markFieldError($field, 'Este campo es obligatorio');
                isValid = false;
            }
        });
        
        // Validaciones específicas
        const dni = $('#dni').val().trim();
        if (dni && !validateDNI(dni)) {
            markFieldError($('#dni'), window.adhesionPaymentConfig.messages.invalidDNI);
            isValid = false;
        }
        
        const phone = $('#telefono').val().trim();
        if (phone && !validatePhone(phone)) {
            markFieldError($('#telefono'), window.adhesionPaymentConfig.messages.invalidPhone);
            isValid = false;
        }
        
        const postal = $('#codigo_postal').val().trim();
        if (postal && !validatePostalCode(postal)) {
            markFieldError($('#codigo_postal'), window.adhesionPaymentConfig.messages.invalidPostal);
            isValid = false;
        }
        
        // Validar checkboxes requeridos
        if (!$('#acepta_terminos').prop('checked')) {
            markFieldError($('#acepta_terminos'), 'Debes aceptar los términos y condiciones');
            isValid = false;
        }
        
        if (!$('#acepta_privacidad').prop('checked')) {
            markFieldError($('#acepta_privacidad'), 'Debes aceptar la política de privacidad');
            isValid = false;
        }
        
        if (!isValid) {
            showPaymentMessage(window.adhesionPaymentConfig.messages.dataRequired, 'error');
            
            // Scroll al primer error
            const $firstError = $('.has-error').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
                $firstError.find('input, textarea, select').focus();
            }
        }
        
        return isValid;
    }
    
    /**
     * Recopilar datos del formulario
     */
    function collectClientFormData() {
        return {
            nombre_completo: $('#nombre_completo').val().trim(),
            email: $('#email').val().trim(),
            telefono: $('#telefono').val().trim(),
            dni: $('#dni').val().trim(),
            direccion: $('#direccion').val().trim(),
            ciudad: $('#ciudad').val().trim(),
            codigo_postal: $('#codigo_postal').val().trim(),
            provincia: $('#provincia').val().trim(),
            empresa: $('#empresa').val().trim(),
            cif: $('#cif').val().trim(),
            notas_pedido: $('#notas_pedido').val().trim(),
            acepta_terminos: $('#acepta_terminos').prop('checked'),
            acepta_privacidad: $('#acepta_privacidad').prop('checked'),
            acepta_comunicaciones: $('#acepta_comunicaciones').prop('checked')
        };
    }
    
    /**
     * Configurar validación en tiempo real
     */
    function setupRealtimeValidation() {
        // Validación de DNI
        $('#dni').on('blur', function() {
            const value = $(this).val().trim();
            if (value && !validateDNI(value)) {
                markFieldError($(this), window.adhesionPaymentConfig.messages.invalidDNI);
            } else {
                clearFieldError($(this));
            }
        });
        
        // Validación de teléfono
        $('#telefono').on('blur', function() {
            const value = $(this).val().trim();
            if (value && !validatePhone(value)) {
                markFieldError($(this), window.adhesionPaymentConfig.messages.invalidPhone);
            } else {
                clearFieldError($(this));
            }
        });
        
        // Validación de código postal
        $('#codigo_postal').on('blur', function() {
            const value = $(this).val().trim();
            if (value && !validatePostalCode(value)) {
                markFieldError($(this), window.adhesionPaymentConfig.messages.invalidPostal);
            } else {
                clearFieldError($(this));
            }
        });
        
        // Formato automático de teléfono
        $('#telefono').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            $(this).val(value);
        });
        
        // Formato automático de código postal
        $('#codigo_postal').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5);
            }
            $(this).val(value);
        });
    }
    
    /**
     * Funciones de validación
     */
    function validateDNI(dni) {
        const dniRegex = /^[0-9]{8}[A-Z]$/;
        if (!dniRegex.test(dni)) return false;
        
        const number = dni.substring(0, 8);
        const letter = dni.substring(8, 9);
        const validLetters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        const calculatedLetter = validLetters[parseInt(number) % 23];
        
        return letter === calculatedLetter;
    }
    
    function validatePhone(phone) {
        const phoneRegex = /^[67][0-9]{8}$/;
        return phoneRegex.test(phone);
    }
    
    function validatePostalCode(postal) {
        const postalRegex = /^[0-5][0-9]{4}$/;
        return postalRegex.test(postal);
    }
    
    /**
     * Funciones de UI
     */
    function markFieldError($field, message) {
        const $formGroup = $field.closest('.form-group');
        $formGroup.addClass('has-error');
        $formGroup.find('.error-message').remove();
        if (message) {
            $formGroup.append('<div class="error-message">' + message + '</div>');
        }
    }
    
    function clearFieldError($field) {
        const $formGroup = $field.closest('.form-group');
        $formGroup.removeClass('has-error');
        $formGroup.find('.error-message').remove();
    }
    
    function updateButtonLoading($btn, loading) {
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
    
    function showPaymentMessage(message, type) {
        const $container = $('#payment-messages');
        const alertClass = `adhesion-notice adhesion-notice-${type}`;
        const html = `<div class="${alertClass}">${message}</div>`;
        
        $container.html(html);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 500);
        
        // Auto-hide para éxito
        if (type === 'success') {
            setTimeout(function() {
                $container.empty();
            }, 5000);
        }
    }
    
    function goToStep(step) {
        // Actualizar URL sin recargar página
        const url = new URL(window.location);
        url.searchParams.set('step', step);
        window.history.pushState({}, '', url);
        
        // Recargar página para mostrar nuevo paso
        window.location.reload();
    }
});
</script>

<style>
/* Estilos específicos para el proceso de pago */
.adhesion-payment-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.payment-header {
    text-align: center;
    margin-bottom: 40px;
}

.payment-title {
    font-size: 2.5em;
    margin: 0 0 20px 0;
    color: #23282d;
}

.payment-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

/* Barra de progreso */
.payment-progress {
    margin: 30px 0;
}

.progress-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 600px;
    margin: 0 auto;
    position: relative;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 2;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e4e7;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.progress-step.active .step-number {
    background: #007cba;
    color: white;
}

.progress-step.completed .step-number {
    background: #28a745;
    color: white;
}

.step-label {
    font-size: 0.9em;
    color: #666;
    font-weight: 500;
}

.progress-step.active .step-label {
    color: #007cba;
    font-weight: 600;
}

.progress-line {
    position: absolute;
    top: 20px;
    height: 2px;
    background: #e2e4e7;
    z-index: 1;
    transition: background 0.3s ease;
}

.progress-line.completed {
    background: #28a745;
}

.progress-line:nth-of-type(2) {
    left: 16.66%;
    width: 16.66%;
}

.progress-line:nth-of-type(4) {
    left: 41.66%;
    width: 16.66%;
}

.progress-line:nth-of-type(6) {
    left: 66.66%;
    width: 16.66%;
}

/* Resumen del pedido */
.order-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.summary-title {
    font-size: 1.4em;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
}

.summary-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.summary-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.summary-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.summary-section h4 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
    opacity: 0.9;
}

.materials-summary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.material-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.95em;
}

.material-name {
    font-weight: 500;
}

.material-quantity {
    opacity: 0.8;
}

.material-total {
    font-weight: 600;
}

.financial-totals {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.total-line:last-child {
    margin-bottom: 0;
}

.final-total {
    border-top: 2px solid rgba(255,255,255,0.3);
    padding-top: 10px;
    margin-top: 10px;
    font-size: 1.2em;
    font-weight: 700;
}

.discount .total-value {
    color: #90EE90;
}

/* Contenido de pasos */
.payment-content {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.step-title {
    font-size: 1.6em;
    margin: 0 0 10px 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.step-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.step-description {
    color: #666;
    margin-bottom: 30px;
    font-size: 1.1em;
}

/* Formulario */
.adhesion-form {
    max-width: 800px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #f0f0f1;
}

.form-section:last-child {
    border-bottom: none;
}

.section-title {
    font-size: 1.3em;
    margin: 0 0 20px 0;
    color: #23282d;
}

.optional-label {
    font-size: 0.9em;
    color: #666;
    font-weight: normal;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group-full {
    grid-column: 1 / -1;
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

.form-group input,
.form-group textarea,
.form-group select {
    padding: 12px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.form-group input[readonly] {
    background: #f8f9fa;
    color: #666;
}

.field-help {
    font-size: 0.85em;
    color: #666;
    margin-top: 4px;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    font-weight: 500;
    line-height: 1.4;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 12px;
    margin-top: 2px;
    transform: scale(1.2);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 40px;
    flex-wrap: wrap;
}

/* Estados de validación */
.form-group.has-error input,
.form-group.has-error textarea,
.form-group.has-error select {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
}

.error-message {
    color: #dc3545;
    font-size: 0.9em;
    margin-top: 5px;
    display: flex;
    align-items: center;
}

.error-message::before {
    content: '⚠️';
    margin-right: 5px;
}

/* Sección de revisión */
.review-section {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.review-title {
    font-size: 1.2em;
    margin: 0 0 15px 0;
    color: #23282d;
}

.data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.data-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.data-item:last-child {
    border-bottom: none;
}

.data-label {
    font-weight: 600;
    color: #495057;
}

.data-value {
    color: #23282d;
    text-align: right;
}

.review-actions {
    margin-top: 20px;
    text-align: center;
}

/* Métodos de pago */
.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.payment-method {
    display: flex;
    align-items: center;
    padding: 20px;
    border: 2px solid #e2e4e7;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method.selected {
    border-color: #007cba;
    background: #f0f8ff;
}

.method-icon {
    font-size: 2em;
    margin-right: 20px;
}

.method-info {
    flex: 1;
}

.method-info h5 {
    margin: 0 0 5px 0;
    color: #23282d;
}

.method-info p {
    margin: 0;
    color: #666;
    font-size: 0.9em;
}

.method-security {
    display: flex;
    align-items: center;
}

.security-badge {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    font-weight: 600;
}

/* Acciones principales de revisión */
.review-actions-main {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin: 40px 0;
    flex-wrap: wrap;
}

/* Información de seguridad */
.security-info {
    background: #e8f5e8;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.security-info h5 {
    margin: 0 0 15px 0;
    color: #155724;
}

.security-info ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.security-info li {
    margin-bottom: 8px;
    color: #155724;
    font-size: 0.95em;
}

/* Paso de procesamiento */
.processing-content {
    text-align: center;
    padding: 40px 20px;
}

.processing-icon {
    margin-bottom: 20px;
}

.spinner-large {
    width: 60px;
    height: 60px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

.processing-title {
    font-size: 1.8em;
    margin: 0 0 10px 0;
    color: #23282d;
}

.processing-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 40px;
}

.payment-status {
    max-width: 400px;
    margin: 0 auto 30px;
}

.status-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
}

.status-item:last-child {
    border-bottom: none;
}

.status-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e2e4e7;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-weight: 600;
}

.status-item.active .status-icon {
    background: #007cba;
    color: white;
}

.status-item.processing .status-icon {
    background: transparent;
}

.spinner-small {
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.status-text {
    color: #23282d;
    font-weight: 500;
}

.payment-info {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    text-align: left;
    max-width: 400px;
    margin: 0 auto;
}

.payment-info p {
    margin: 8px 0;
}

/* Paso completado */
.success-content {
    text-align: center;
    padding: 40px 20px;
}

.success-icon {
    margin-bottom: 20px;
}

.checkmark {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #28a745;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5em;
    margin: 0 auto;
    animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
    0% {
        transform: scale(0.8);
        opacity: 0.5;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.success-title {
    font-size: 2em;
    margin: 0 0 15px 0;
    color: #28a745;
}

.success-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 40px;
}

.order-completed-info {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    text-align: center;
}

.info-label {
    font-weight: 500;
    color: #666;
    font-size: 0.9em;
    margin-bottom: 5px;
}

.info-value {
    font-weight: 600;
    color: #23282d;
    font-size: 1.1em;
}

.next-steps {
    background: #e8f4fd;
    border: 1px solid #bee5eb;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: left;
}

.next-steps h4 {
    margin: 0 0 15px 0;
    color: #0c5460;
}

.next-steps ol {
    margin: 0;
    color: #0c5460;
}

.next-steps li {
    margin-bottom: 8px;
}

.post-payment-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.contact-info {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.contact-info h5 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.contact-methods {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 15px;
}

.contact-methods span {
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #e2e4e7;
    font-size: 0.9em;
}

/* Botones */
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
    margin: 0 5px 10px 0;
    text-align: center;
    justify-content: center;
}

.adhesion-btn-primary {
    background: #007cba;
    color: white;
}

.adhesion-btn-primary:hover {
    background: #005a87;
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

/* Modal */
.adhesion-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    padding: 40px;
    max-width: 400px;
    text-align: center;
}

.loading-content h3 {
    margin: 20px 0 10px 0;
    color: #23282d;
}

.loading-content p {
    margin: 0;
    color: #666;
}

/* Mensajes */
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

/* Error de pago */
.payment-error {
    text-align: center;
    padding: 40px 20px;
}

.error-icon {
    font-size: 4em;
    margin-bottom: 20px;
}

.payment-error h3 {
    color: #dc3545;
    margin-bottom: 15px;
}

.payment-error p {
    color: #666;
    margin-bottom: 30px;
}

/* Responsive design */
@media (max-width: 768px) {
    .adhesion-payment-container {
        padding: 15px;
    }
    
    .payment-title {
        font-size: 2em;
    }
    
    .progress-bar {
        flex-direction: column;
        gap: 20px;
    }
    
    .progress-line {
        display: none;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .data-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions,
    .review-actions-main,
    .post-payment-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .adhesion-btn {
        width: 100%;
        max-width: 300px;
    }
    
    .contact-methods {
        flex-direction: column;
        align-items: center;
    }
    
    .data-item {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .data-value {
        text-align: center;
    }
    
    .material-line {
        font-size: 0.9em;
    }
}

@media (max-width: 480px) {
    .payment-title {
        font-size: 1.8em;
    }
    
    .step-title {
        font-size: 1.4em;
    }
    
    .order-summary,
    .payment-content {
        padding: 20px;
    }
    
    .form-section {
        padding-bottom: 20px;
    }
    
    .progress-step {
        font-size: 0.9em;
    }
    
    .step-number {
        width: 35px;
        height: 35px;
    }
}

/* Print styles */
@media print {
    .form-actions,
    .review-actions,
    .review-actions-main,
    .post-payment-actions {
        display: none;
    }
    
    .payment-progress,
    .adhesion-modal {
        display: none;
    }
    
    .order-summary {
        background: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #000;
    }
}
</style><?php
/**
 * Vista del proceso de pago con Redsys
 * 
 * Esta vista maneja:
 * - Formulario de datos del cliente
 * - Resumen del cálculo y presupuesto
 * - Proceso de pago con Redsys
 * - Estados de pago y confirmaciones
 * - Creación automática de contratos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario esté logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para realizar un pago.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar Sesión', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Verificar configuración de Redsys
if (!adhesion_is_redsys_configured()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Los pagos no están configurados. Contacta con el administrador.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Obtener datos necesarios
$db = new Adhesion_Database();
$user = wp_get_current_user();
$user_meta = get_user_meta($user->ID);

// Obtener ID del cálculo o contrato desde parámetros
$calculation_id = isset($_GET['calculation_id']) ? intval($_GET['calculation_id']) : 0;
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

// Variables para el proceso
$calculation = null;
$contract = null;
$payment_step = 'form'; // form, review, processing, complete

// Determinar el contexto del pago
if ($contract_id) {
    $contract = $db->get_contract($contract_id);
    if ($contract && $contract['user_id'] == $user->ID) {
        if ($contract['calculation_id']) {
            $calculation = $db->get_calculation($contract['calculation_id']);
        }
        // Determinar paso según estado del contrato
        if ($contract['payment_status'] === 'completed') {
            $payment_step = 'complete';
        } elseif ($contract['payment_status'] === 'pending') {
            $payment_step = 'processing';
        } elseif (!empty($contract['client_data'])) {
            $payment_step = 'review';
        }
    }
} elseif ($calculation_id) {
    $calculation = $db->get_calculation($calculation_id);
    if ($calculation && $calculation['user_id'] == $user->ID) {
        // Verificar si ya existe un contrato para este cálculo
        $existing_contract = $db->get_contract_by_calculation($calculation_id);
        if ($existing_contract) {
            $contract = $existing_contract;
            $contract_id = $contract['id'];
            $payment_step = !empty($contract['client_data']) ? 'review' : 'form';
        }
    }
}

// Si no hay datos válidos, mostrar error
if (!$calculation && !$contract) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('No se encontraron datos para procesar el pago. Por favor, realiza primero un cálculo.', 'adhesion') . '</p>';
    echo '<p><a href="' . home_url('/calculadora/') . '" class="adhesion-btn adhesion-btn-primary">' . __('Ir a Calculadora', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Configuración del proceso de pago
$payment_config = array(
    'tax_rate' => adhesion_get_option('tax_rate', 21),
    'currency' => adhesion_get_option('currency', 'EUR'),
    'require_dni' => adhesion_get_option('require_dni', true),
    'require_company_data' => adhesion_get_option('require_company_data', false)
);
?>

<div class="adhesion-payment-container" id="adhesion-payment">
    
    <!-- Header del proceso de pago -->
    <div class="payment-header">
        <h2 class="payment-title">
            <span class="payment-icon">💳</span>
            <?php _e('Proceso de Pago', 'adhesion'); ?>
        </h2>
        
        <!-- Barra de progreso -->
        <div class="payment-progress">
            <div class="progress-bar">
                <div class="progress-step <?php echo ($payment_step === 'form') ? 'active' : (in_array($payment_step, ['review', 'processing', 'complete']) ? 'completed' : ''); ?>">
                    <span class="step-number">1</span>
                    <span class="step-label"><?php _e('Datos', 'adhesion'); ?></span>
                </div>
                <div class="progress-line <?php echo (in_array($payment_step, ['review', 'processing', 'complete']) ? 'completed' : ''); ?>"></div>
                <div class="progress-step <?php echo ($payment_step === 'review') ? 'active' : (in_array($payment_step, ['processing', 'complete']) ? 'completed' : ''); ?>">
                    <span class="step-number">2</span>
                    <span class="step-label"><?php _e('Revisión', 'adhesion'); ?></span>
                </div>
                <div class="progress-line <?php echo (in_array($payment_step, ['processing', 'complete']) ? 'completed' : ''); ?>"></div>
                <div class="progress-step <?php echo ($payment_step === 'processing') ? 'active' : ($payment_step === 'complete' ? 'completed' : ''); ?>">
                    <span class="step-number">3</span>
                    <span class="step-label"><?php _e('Pago', 'adhesion'); ?></span>
                </div>
                <div class="progress-line <?php echo ($payment_step === 'complete' ? 'completed' : ''); ?>"></div>
                <div class="progress-step <?php echo ($payment_step === 'complete') ? 'active completed' : ''; ?>">
                    <span class="step-number">4</span>
                    <span class="step-label"><?php _e('Confirmación', 'adhesion'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensajes de estado -->
    <div id="payment-messages" class="adhesion-messages-container"></div>

    <!-- Resumen del pedido (siempre visible) -->
    <div class="order-summary">
        <h3 class="summary-title">
            <span class="summary-icon">📋</span>
            <?php _e('Resumen del pedido', 'adhesion'); ?>
        </h3>
        
        <div class="summary-content">
            
            <!-- Información del cliente -->
            <div class="summary-section">
                <h4><?php _e('Cliente:', 'adhesion'); ?></h4>
                <p><strong><?php echo esc_html($user->display_name); ?></strong></p>
                <p><?php echo esc_html($user->user_email); ?></p>
            </div>
            
            <!-- Desglose del cálculo -->
            <?php if ($calculation): ?>
                <div class="summary-section">
                    <h4><?php _e('Materiales:', 'adhesion'); ?></h4>
                    <?php
                    $materials_data = json_decode($calculation['materials_data'], true);
                    if ($materials_data):
                    ?>
                        <div class="materials-summary">
                            <?php foreach ($materials_data as $material): ?>
                                <div class="material-line">
                                    <span class="material-name"><?php echo esc_html(ucfirst($material['type'])); ?></span>
                                    <span class="material-quantity"><?php echo adhesion_format_tons($material['quantity']); ?></span>
                                    <span class="material-total"><?php echo adhesion_format_price($material['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Totales financieros -->
            <div class="summary-section financial-totals">
                <?php
                $total_amount = $calculation ? $calculation['total_price'] : ($contract ? $contract['total_price'] : 0);
                $total_tons = $calculation ? $calculation['total_tons'] : ($contract ? $contract['total_tons'] : 0);
                ?>
                
                <div class="total-line">
                    <span class="total-label"><?php _e('Total toneladas:', 'adhesion'); ?></span>
                    <span class="total-value"><?php echo adhesion_format_tons($total_tons); ?></span>
                </div>
                
                <?php if ($calculation && isset($calculation['subtotal'])): ?>
                    <div class="total-line">
                        <span class="total-label"><?php _e('Subtotal:', 'adhesion'); ?></span>
                        <span class="total-value"><?php echo adhesion_format_price($calculation['subtotal']); ?></span>
                    </div>
                    
                    <?php if ($calculation['discount_amount'] > 0): ?>
                        <div class="total-line discount">
                            <span class="total-label"><?php _e('Descuentos:', 'adhesion'); ?></span>
                            <span class="total-value">-<?php echo adhesion_format_price($calculation['discount_amount']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($calculation['tax_amount'] > 0): ?>
                        <div class="total-line">
                            <span class="total-label"><?php printf(__('IVA (%s%%):', 'adhesion'), $payment_config['tax_rate']); ?></span>
                            <span class="total-value"><?php echo adhesion_format_price($calculation['tax_amount']); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="total-line final-total">
                    <span class="total-label"><?php _e('TOTAL A PAGAR:', 'adhesion'); ?></span>
                    <span class="total-value"><?php echo adhesion_format_price($total_amount); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido principal según el paso -->
    <div class="payment-content">
        
        <?php if ($payment_step === 'form'): ?>
            <!-- PASO 1: Formulario de datos del cliente -->
            <div class="payment-step-content" id="client-data-form">
                <h3 class="step-title">
                    <span class="step-icon">👤</span>
                    <?php _e('Completa tus datos', 'adhesion'); ?>
                </h3>
                <p class="step-description">
                    <?php _e('Necesitamos algunos datos adicionales para procesar tu pedido y generar el contrato.', 'adhesion'); ?>
                </p>
                
                <form id="adhesion-client-form" class="adhesion-form">
                    
                    <!-- Datos personales -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('Datos Personales', 'adhesion'); ?></h4>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre_completo"><?php _e('Nombre completo', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="nombre_completo" 
                                       name="nombre_completo" 
                                       value="<?php echo esc_attr($user->display_name); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><?php _e('Email', 'adhesion'); ?> *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo esc_attr($user->user_email); ?>" 
                                       readonly>
                                <small class="field-help"><?php _e('El email no se puede modificar.', 'adhesion'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="telefono"><?php _e('Teléfono', 'adhesion'); ?> *</label>
                                <input type="tel" 
                                       id="telefono" 
                                       name="telefono" 
                                       value="<?php echo esc_attr($user_meta['phone'][0] ?? ''); ?>" 
                                       placeholder="<?php _e('Ej: 600123456', 'adhesion'); ?>"
                                       required>
                            </div>
                            
                            <?php if ($payment_config['require_dni']): ?>
                                <div class="form-group">
                                    <label for="dni"><?php _e('DNI/NIE', 'adhesion'); ?> *</label>
                                    <input type="text" 
                                           id="dni" 
                                           name="dni" 
                                           value="<?php echo esc_attr($user_meta['dni'][0] ?? ''); ?>" 
                                           placeholder="<?php _e('Ej: 12345678A', 'adhesion'); ?>"
                                           required>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Dirección -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('Dirección', 'adhesion'); ?></h4>
                        
                        <div class="form-grid">
                            <div class="form-group form-group-full">
                                <label for="direccion"><?php _e('Dirección completa', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="direccion" 
                                       name="direccion" 
                                       value="<?php echo esc_attr($user_meta['address'][0] ?? ''); ?>" 
                                       placeholder="<?php _e('Calle, número, piso, puerta...', 'adhesion'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ciudad"><?php _e('Ciudad', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="ciudad" 
                                       name="ciudad" 
                                       value="<?php echo esc_attr($user_meta['city'][0] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="codigo_postal"><?php _e('Código Postal', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="codigo_postal" 
                                       name="codigo_postal" 
                                       value="<?php echo esc_attr($user_meta['postal_code'][0] ?? ''); ?>" 
                                       pattern="[0-9]{5}"
                                       placeholder="<?php _e('Ej: 28001', 'adhesion'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="provincia"><?php _e('Provincia', 'adhesion'); ?> *</label>
                                <input type="text" 
                                       id="provincia" 
                                       name="provincia" 
                                       value="<?php echo esc_attr($user_meta['province'][0] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datos de empresa (opcional) -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <?php _e('Datos de Empresa', 'adhesion'); ?>
                            <?php if (!$payment_config['require_company_data']): ?>
                                <span class="optional-label">(<?php _e('Opcional', 'adhesion'); ?>)</span>
                            <?php endif; ?>
                        </h4>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="empresa"><?php _e('Nombre de la empresa', 'adhesion'); ?><?php echo $payment_config['require_company_data'] ? ' *' : ''; ?></label>
                                <input type="text" 
                                       id="empresa" 
                                       name="empresa" 
                                       value="<?php echo esc_attr($user_meta['company'][0] ?? ''); ?>"
                                       <?php echo $payment_config['require_company_data'] ? 'required' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="cif"><?php _e('CIF', 'adhesion'); ?><?php echo $payment_config['require_company_data'] ? ' *' : ''; ?></label>
                                <input type="text" 
                                       id="cif" 
                                       name="cif" 
                                       value="<?php echo esc_attr($user_meta['cif'][0] ?? ''); ?>" 
                                       placeholder="<?php _e('Ej: A12345678', 'adhesion'); ?>"
                                       <?php echo $payment_config['require_company_data'] ? 'required' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notas adicionales -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('Información Adicional', 'adhesion'); ?></h4>
                        
                        <div class="form-group">
                            <label for="notas_pedido"><?php _e('Notas del pedido (opcional)', 'adhesion'); ?></label>
                            <textarea id="notas_pedido" 
                                      name="notas_pedido" 
                                      rows="3" 
                                      placeholder="<?php _e('Instrucciones especiales, observaciones...', 'adhesion'); ?>"></textarea>
                        </div>
                    </div>
                    
                    <!-- Aceptación de términos -->
                    <div class="form-section">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="acepta_terminos" id="acepta_terminos" required>
                                <span class="checkmark"></span>
                                <?php printf(__('He leído y acepto los <a href="%s" target="_blank">términos y condiciones</a>', 'adhesion'), '#'); ?>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="acepta_privacidad" id="acepta_privacidad" required>
                                <span class="checkmark"></span>
                                <?php printf(__('Acepto la <a href="%s" target="_blank">política de privacidad</a>', 'adhesion'), get_privacy_policy_url()); ?>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="acepta_comunicaciones" id="acepta_comunicaciones">
                                <span class="checkmark"></span>
                                <?php _e('Deseo recibir comunicaciones comerciales por email', 'adhesion'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="form-actions">
                        <button type="button" id="save-client-data-btn" class="adhesion-btn adhesion-btn-primary adhesion-btn-large">
                            <span class="btn-text"><?php _e('Continuar con el pago', 'adhesion'); ?></span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span>
                                <?php _e('Guardando datos...', 'adhesion'); ?>
                            </span>
                        </button>
                        
                        <a href="<?php echo home_url('/calculadora/'); ?>" class="adhesion-btn adhesion-btn-outline">
                            <?php _e('Volver a calculadora', 'adhesion'); ?>
                        </a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($payment_step === 'review'): ?>
            <!-- PASO 2: Revisión antes del pago -->
            <div class="payment-step-content" id="payment-review">
                <h3 class="step-title">
                    <span class="step-icon">👁️</span>
                    <?php _e('Revisa tu pedido', 'adhesion'); ?>
                </h3>
                <p class="step-description">
                    <?php _e('Verifica que todos los datos sean correctos antes de proceder al pago.', 'adhesion'); ?>
                </p>
                
                <!-- Datos del cliente -->
                <?php if ($contract && !empty($contract['client_data'])): ?>
                    <div class="review-section">
                        <h4 class="review-title"><?php _e('Datos del cliente', 'adhesion'); ?></h4>
                        <div class="review-content">
                            <?php
                            $client_data = is_string($contract['client_data']) ? json_decode($contract['client_data'], true) : $contract['client_data'];
                            ?>
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="data-label"><?php _e('Nombre:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['nombre_completo'] ?? ''); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label"><?php _e('Teléfono:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['telefono'] ?? ''); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label"><?php _e('Dirección:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['direccion'] ?? ''); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label"><?php _e('Ciudad:', 'adhesion'); ?></span>
                                    <span class="data-value"><?php echo esc_html($client_data['ciudad'] ?? ''); ?>, <?php echo esc_html($client_data['codigo_postal'] ?? ''); ?></span>
                                </div>
                                <?php if (!empty($client_data['empresa'])): ?>
                                    <div class="data-item">
                                        <span class="data-label"><?php _e('Empresa:', 'adhesion'); ?></span>
                                        <span class="data-value"><?php echo esc_html($client_data['empresa']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="review-actions">
                            <button type="button" id="edit-client-data-btn" class="adhesion-btn adhesion-btn-outline">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Modificar datos', 'adhesion'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Forma de pago -->
                <div class="review-section">
                    <h4 class="review-title"><?php _e('Forma de pago', 'adhesion'); ?></h4>
                    <div class="payment-methods">
                        <div class="payment-method selected">
                            <div class="method-icon">💳</div>
                            <div class="method-info">
                                <h5><?php _e('Tarjeta de crédito/débito', 'adhesion'); ?></h5>
                                <p><?php _e('Pago seguro a través de Redsys. Aceptamos Visa, Mastercard y American Express.', 'adhesion'); ?></p>
                            </div>
                            <div class="method-security">
                                <span class="security-badge">🔒 SSL</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones de revisión -->
                <div class="review-actions-main">
                    <button type="button" id="proceed-to-payment-btn" class="adhesion-btn adhesion-btn-success adhesion-btn-large">
                        <span class="btn-icon">🔒</span>
                        <span class="btn-text"><?php printf(__('Pagar %s de forma segura', 'adhesion'), adhesion_format_price($total_amount)); ?></span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span>
                            <?php _e('Preparando pago...', 'adhesion'); ?>
                        </span>
                    </button>
                    
                    <button type="button" id="back-to-form-btn" class="adhesion-btn adhesion-btn-outline">
                        <?php _e('Volver a datos', 'adhesion'); ?>
                    </button>
                </div>
                
                <!-- Información de seguridad -->
                <div class="security-info">
                    <h5><?php _e('Información de seguridad', 'adhesion'); ?></h5>
                    <ul>
                        <li>🔒 <?php _e('Conexión SSL cifrada', 'adhesion'); ?></li>
                        <li>🏦 <?php _e('Procesado por Redsys (la pasarela de los bancos españoles)', 'adhesion'); ?></li>
                        <li>🛡️ <?php _e('No almacenamos datos de tu tarjeta', 'adhesion'); ?></li>
                        <li>📧 <?php _e('Recibirás confirmación por email', 'adhesion'); ?></li>
                    </ul>
                </div>
            </div>
            
        <?php elseif ($payment_step === 'processing'): ?>
            <!-- PASO 3: Procesando pago -->
            <div class="payment-step-content" id="payment-processing">
                <div class="processing-content">
                    <div class="processing-icon">
                        <div class="spinner-large"></div>
                    </div>
                    <h3 class="processing-title"><?php _e('Procesando tu pago...', 'adhesion'); ?></h3>
                    <p class="processing-description">
                        <?php _e('Por favor, no cierres esta ventana ni pulses el botón atrás del navegador.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Estado del pago -->
                    <div class="payment-status" id="payment-status">
                        <div class="status-item active">
                            <span class="status-icon">✓</span>
                            <span class="status-text"><?php _e('Datos verificados', 'adhesion'); ?></span>
                        </div>
                        <div class="status-item active">
                            <span class="status-icon">✓</span>
                            <span class="status-text"><?php _e('Conectando con el banco...', 'adhesion'); ?></span>
                        </div>
                        <div class="status-item processing">
                            <span class="status-icon">
                                <span class="spinner-small"></span>
                            </span>
                            <span class="status-text"><?php _e('Procesando pago', 'adhesion'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Información del pago -->
                    <div class="payment-info">
                        <p><strong><?php _e('Referencia:', 'adhesion'); ?></strong> <?php echo esc_html($contract['payment_reference'] ?? 'Generando...'); ?></p>
                        <p><strong><?php _e('Importe:', 'adhesion'); ?></strong> <?php echo adhesion_format_price($total_amount); ?></p>
                    </div>
                </div>
                
                <!-- Auto-refresh del estado -->
                <script>
                    // Verificar estado del pago cada 3 segundos
                    let paymentCheckInterval = setInterval(function() {
                        checkPaymentStatus();
                    }, 3000);
                    
                    function checkPaymentStatus() {
                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'adhesion_check_payment_status',
                                nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
                                contract_id: <?php echo $contract_id; ?>
                            },
                            success: function(response) {
                                if (response.success) {
                                    if (response.data.status === 'completed') {
                                        clearInterval(paymentCheckInterval