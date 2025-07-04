/**
 * JavaScript del Frontend para el plugin Adhesión
 * Archivo: assets/js/frontend.js
 * Maneja toda la interactividad del dashboard y formularios
 */

(function($) { 
    'use strict';

    // Variables globales
    let currentTab = 'overview';
    let isProcessing = false;

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function() {
        initTabs();
        initModals();
        initForms();
        initButtons();
        initCalculatorButtons();
        initLoginRegisterForms();
        initContractButtons();
        initProfileForm();
        initDataTables();
        
        // Mostrar mensajes si los hay
        showFlashMessages();
        
        console.log('Adhesión Frontend initialized');
    });

    /**
     * ================================
     * SISTEMA DE PESTAÑAS
     * ================================
     */
    function initTabs() {
        // Cambio de pestañas
        $('.tab-button').on('click', function(e) {
            e.preventDefault();
            
            const tabId = $(this).data('tab');
            if (tabId && tabId !== currentTab) {
                switchTab(tabId);
            }
        });

        // Enlaces que cambian pestañas
        $('[data-tab-link]').on('click', function(e) {
            e.preventDefault();
            
            const tabId = $(this).data('tab-link');
            if (tabId) {
                switchTab(tabId);
            }
        });

        // Detectar hash en URL para pestaña inicial
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
     * BOTONES DE ACCIÓN
     * ================================
     */
    function initButtons() {
        // Ver detalles de cálculo
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

        // Contratar cálculo
        $(document).on('click', '.contract-calc', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            const calcId = $(this).data('calc-id');
            if (calcId && confirm(adhesionAjax.messages.confirmContract || '¿Proceder con la contratación?')) {
                processCalculationContract(calcId);
            }
        });
    }

    function initCalculatorButtons() {
        // Botones para nueva calculadora
        $('#nueva-calculadora, #nueva-calculadora-tab, #primera-calculadora').on('click', function(e) {
            e.preventDefault();
            
            // Redirigir a página de calculadora o mostrar modal
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
        // Validación en tiempo real
        $('.adhesion-form input, .adhesion-form select, .adhesion-form textarea').on('blur', function() {
            validateField($(this));
        });

        // Prevenir envío con Enter en campos que no deben
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

        // Cancelar edición
        $('#cancel-profile-edit').on('click', function(e) {
            e.preventDefault();
            resetProfileForm();
        });

        // Validación de contraseñas
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
            message = 'Por favor, introduce un email válido.';
        }
        // Validar teléfono
        else if ($field.attr('name') === 'phone' && value && !isValidPhone(value)) {
            isValid = false;
            message = 'Por favor, introduce un teléfono válido.';
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

        // Validar contraseñas si están llenas
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword || confirmPassword) {
            if (newPassword !== confirmPassword) {
                $('#confirm_password').addClass('error');
                $('#confirm_password').after('<div class="field-error">Las contraseñas no coinciden.</div>');
                isValid = false;
            }
            
            if (newPassword.length < 6) {
                $('#new_password').addClass('error');
                $('#new_password').after('<div class="field-error">La contraseña debe tener al menos 6 caracteres.</div>');
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
            let strengthText = 'Débil';
            
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
        showLoading('Cargando detalles del cálculo...');

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
                showMessage('error', 'Error de conexión. Por favor, inténtalo de nuevo.');
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
                showMessage('error', 'Error de conexión. Por favor, inténtalo de nuevo.');
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
        showLoading('Procesando contratación...');

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
                    showMessage('success', 'Contratación procesada correctamente.');
                    
                    // Redirigir a proceso de pago
                    if (response.data.payment_url) {
                        setTimeout(function() {
                            window.location.href = response.data.payment_url;
                        }, 1500);
                    } else {
                        // Recargar pestaña actual
                        location.reload();
                    }
                } else {
                    showMessage('error', response.data || 'Error al procesar la contratación.');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexión. Por favor, inténtalo de nuevo.');
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
                showMessage('error', 'Error de conexión. Por favor, inténtalo de nuevo.');
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
                showMessage('error', 'Error de conexión. Por favor, inténtalo de nuevo.');
            },
            complete: function() {
                isProcessing = false;
                hideLoading();
            }
        });
    }

    /**
     * ================================
     * CONSTRUCCIÓN DE CONTENIDO
     * ================================
     */
    function buildCalculationDetailsContent(data) {
        let content = '<div class="calculation-details">';
        
        content += '<div class="detail-section">';
        content += '<h4>Información General</h4>';
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
        content += '<h4>Información de Cálculo</h4>';
        content += '<div class="detail-grid">';
        content += '<div class="detail-item"><label>Fecha:</label> <span>' + formatDate(data.created_at || '') + '</span></div>';
        content += '<div class="detail-item"><label>Estado:</label> <span class="status-badge status-' + (data.status || 'calculated') + '">' + getStatusText(data.status || 'calculated') + '</span></div>';
        content += '</div>';
        content += '</div>';

        if ((data.status || 'calculated') === 'calculated') {
            content += '<div class="detail-actions">';
            content += '<button type="button" class="button button-primary contract-calc" data-calc-id="' + data.id + '">Proceder con Contratación</button>';
            content += '</div>';
        }

        content += '</div>';
        
        return content;
    }

    function buildContractDetailsContent(data) {
        let content = '<div class="contract-details">';
        
        content += '<div class="detail-section">';
        content += '<h4>Información del Contrato</h4>';
        content += '<div class="detail-grid">';
        content += '<div class="detail-item"><label>Tipo:</label> <span>' + escapeHtml(data.contract_type || '') + '</span></div>';
        content += '<div class="detail-item"><label>Estado:</label> <span class="status-badge status-' + (data.status || 'pending') + '">' + getStatusText(data.status || 'pending') + '</span></div>';
        content += '<div class="detail-item"><label>Monto:</label> <span>' + formatPrice(data.amount || 0) + '</span></div>';
        content += '<div class="detail-item"><label>Fecha de Creación:</label> <span>' + formatDate(data.created_at || '') + '</span></div>';
        content += '</div>';
        content += '</div>';

        if (data.signed_at) {
            content += '<div class="detail-section">';
            content += '<h4>Información de Firma</h4>';
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
            
            // Detectar si es número
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
            
            // Comparación de texto
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
        
        // Auto-ocultar después del tiempo especificado
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
        
        // Recargar valores originales (esto debería implementarse según tus necesidades)
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
                    // Mostrar notificación de actualización
                    showMessage('info', 'Hay actualizaciones en tus contratos. Recargando...', 2000);
                    
                    // Recargar página después de 2 segundos
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
        // Cargar datos específicos de la pestaña si es necesario
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
     * INICIALIZACIÓN ADICIONAL
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

    // Agregar estilos CSS adicionales dinámicamente
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
            content: ' ↑';
            color: #2563eb;
        }
        
        .adhesion-table th.sort-desc::after {
            content: ' ↓';
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


    /**
     * ================================
     * FUNCIONES LOGIN/REGISTRO
     * ================================
     */

    /**
     * Inicializar formularios de login y registro
     */
    function initLoginRegisterForms() {
        // Botón para mostrar formulario de registro
        $(document).on('click', '#show-register-form', function(e) {
            e.preventDefault();
            showRegisterForm();
        });
        
        // Función global para mostrar registro (llamada desde PHP)
        window.showRegisterForm = showRegisterForm;
        
        // Botón para mostrar formulario de login
        $(document).on('click', '#show-login-form', function(e) {
            e.preventDefault();
            showLoginForm();
        });
        
        // Función global para mostrar login (llamada desde PHP)
        window.showLoginForm = showLoginForm;
        
        // Procesar formulario de login
        $(document).on('submit', '#adhesion-login-form', function(e) {
            e.preventDefault();
            processLoginForm($(this));
        });
        
        // Procesar formulario de registro
        $(document).on('submit', '#adhesion-register-form', function(e) {
            e.preventDefault();
            processRegisterForm($(this));
        });
    }

    /**
     * Mostrar formulario de registro (versión simplificada)
     */
    function showRegisterForm() {
        window.location.href = '/registro/';
    }

    function showLoginForm() {
        window.location.href = '/mi-cuenta-adhesion/';
    }

    /**
     * Mostrar formulario de login
     */
    function showLoginForm() {
        $('.adhesion-register-form').hide();
        $('.adhesion-login-form').show();
    }

    /**
     * Procesar formulario de login
     */
    function processLoginForm($form) {
        if (isProcessing) return;
        
        // Validar campos requeridos
        if (!validateForm($form)) {
            return;
        }
        
        isProcessing = true;
        
        $.ajax({
            url: adhesion_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true).text('Iniciando sesión...');
                clearFormMessages($form);
            },
            success: function(response) {
                if (response.success) {
                    showFormMessage($form, 'success', response.data.message);
                    
                    // Redireccionar después de un breve delay
                    setTimeout(function() {
                        window.location.href = response.data.redirect_to || window.location.href;
                    }, 1500);
                } else {
                    showFormMessage($form, 'error', response.data || 'Error en el login');
                    $form.find('button[type="submit"]').prop('disabled', false).text('Iniciar Sesión');
                }
            },
            error: function() {
                showFormMessage($form, 'error', 'Error de conexión. Por favor, inténtalo de nuevo.');
                $form.find('button[type="submit"]').prop('disabled', false).text('Iniciar Sesión');
            },
            complete: function() {
                isProcessing = false;
            }
        });
    }

    /**
     * Procesar formulario de registro
     */
    function processRegisterForm($form) {
        if (isProcessing) return;
        
        // Validar campos requeridos
        if (!validateForm($form)) {
            return;
        }
        
        // Validar contraseñas
        const password = $form.find('[name="user_password"]').val();
        const confirmPassword = $form.find('[name="confirm_password"]').val();
        
        if (password !== confirmPassword) {
            showFormMessage($form, 'error', 'Las contraseñas no coinciden');
            return;
        }
        
        if (password.length < 6) {
            showFormMessage($form, 'error', 'La contraseña debe tener al menos 6 caracteres');
            return;
        }
        
        // Verificar términos y condiciones
        if (!$form.find('[name="accept_terms"]').is(':checked')) {
            showFormMessage($form, 'error', 'Debes aceptar los términos y condiciones');
            return;
        }
        
        isProcessing = true;
        
        $.ajax({
            url: adhesion_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true).text('Creando cuenta...');
                clearFormMessages($form);
            },
            success: function(response) {
                if (response.success) {
                    showFormMessage($form, 'success', response.data.message);
                    
                    // Redireccionar después de un breve delay
                    setTimeout(function() {
                        window.location.href = response.data.redirect_to || window.location.href;
                    }, 1500);
                } else {
                    showFormMessage($form, 'error', response.data || 'Error en el registro');
                    $form.find('button[type="submit"]').prop('disabled', false).text('Crear Cuenta');
                }
            },
            error: function() {
                showFormMessage($form, 'error', 'Error de conexión. Por favor, inténtalo de nuevo.');
                $form.find('button[type="submit"]').prop('disabled', false).text('Crear Cuenta');
            },
            complete: function() {
                isProcessing = false;
            }
        });
    }

    /**
     * Inicializar eventos específicos del formulario de registro
     */
    function initRegisterFormEvents() {
        const $registerForm = $('#adhesion-register-form');
        
        // Validación en tiempo real del CIF
        $registerForm.find('[name="user_cif"]').on('blur', function() {
            const cif = $(this).val().trim();
            if (cif) {
                validateCIF(cif, $(this));
            }
        });
        
        // Validación de contraseñas en tiempo real
        $registerForm.find('[name="user_password"], [name="confirm_password"]').on('input', function() {
            validatePasswordMatch($registerForm);
        });
        
        // Validación de email
        $registerForm.find('[name="user_email"]').on('blur', function() {
            const email = $(this).val().trim();
            if (email && isValidEmail(email)) {
                checkEmailExists(email, $(this));
            }
        });
    }

    /**
     * Validar formato de CIF
     */
    function validateCIF(cif, $field) {
        // Validación básica de formato CIF español
        const cifRegex = /^[ABCDEFGHJNPQRSUVW]\d{8}$/;
        
        if (!cifRegex.test(cif.toUpperCase())) {
            showFieldError($field, 'Formato de CIF no válido');
            return false;
        }
        
        // Verificar si el CIF ya existe
        $.ajax({
            url: adhesion_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'adhesion_check_cif_exists',
                cif: cif,
                nonce: adhesion_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.exists) {
                        showFieldError($field, 'Este CIF ya está registrado');
                    } else {
                        clearFieldError($field);
                    }
                }
            }
        });
        
        return true;
    }

    /**
     * Validar coincidencia de contraseñas
     */
    function validatePasswordMatch($form) {
        const password = $form.find('[name="user_password"]').val();
        const confirmPassword = $form.find('[name="confirm_password"]').val();
        const $confirmField = $form.find('[name="confirm_password"]');
        
        if (confirmPassword && password !== confirmPassword) {
            showFieldError($confirmField, 'Las contraseñas no coinciden');
            return false;
        } else if (confirmPassword && password === confirmPassword) {
            clearFieldError($confirmField);
            return true;
        }
        
        return true;
    }

    /**
     * Verificar si email existe
     */
    function checkEmailExists(email, $field) {
        $.ajax({
            url: adhesion_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'adhesion_check_email_exists',
                email: email,
                nonce: adhesion_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.exists) {
                        showFieldError($field, 'Este email ya está registrado');
                    } else {
                        clearFieldError($field);
                    }
                }
            }
        });
    }

    /**
     * Mostrar mensaje en formulario específico
     */
    function showFormMessage($form, type, message) {
        const $messagesContainer = $form.find('.adhesion-form-messages');
        
        if ($messagesContainer.length === 0) {
            $form.append('<div class="adhesion-form-messages"></div>');
        }
        
        const icon = type === 'success' ? 'yes-alt' : 'warning';
        const messageHtml = `
            <div class="adhesion-form-message ${type}">
                <span class="dashicons dashicons-${icon}"></span>
                ${escapeHtml(message)}
            </div>
        `;
        
        $form.find('.adhesion-form-messages').html(messageHtml);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $form.find('.adhesion-form-messages').offset().top - 20
        }, 300);
    }

    /**
     * Limpiar mensajes del formulario
     */
    function clearFormMessages($form) {
        $form.find('.adhesion-form-messages').empty();
    }

    /**
     * Mostrar error en campo específico
     */
    function showFieldError($field, message) {
        clearFieldError($field);
        $field.addClass('error');
        $field.after(`<div class="field-error">${escapeHtml(message)}</div>`);
    }

    /**
     * Limpiar error de campo específico
     */
    function clearFieldError($field) {
        $field.removeClass('error');
        $field.next('.field-error').remove();
    }

})(jQuery);