<?php
/**
 * Vista de firma de contratos con DocuSign
 * 
 * Esta vista maneja:
 * - Preparación de documentos para firma
 * - Integración con DocuSign
 * - Estados de firma (pendiente, firmado, rechazado)
 * - Descarga de documentos firmados
 * - Seguimiento del proceso
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario esté logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para firmar contratos.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar Sesión', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Verificar configuración de DocuSign
if (!adhesion_is_docusign_configured()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('La firma digital no está configurada. Contacta con el administrador.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Obtener datos necesarios
$db = new Adhesion_Database();
$user = wp_get_current_user();

// Obtener ID del contrato desde parámetros
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : (isset($atts['contract_id']) ? intval($atts['contract_id']) : 0);

// Variables para el proceso
$contract = null;
$calculation = null;
$signing_step = 'loading'; // loading, ready, signing, signed, error

// Obtener información del contrato
if ($contract_id) {
    $contract = $db->get_contract($contract_id);
    if ($contract && $contract['user_id'] == $user->ID) {
        // Obtener cálculo asociado si existe
        if ($contract['calculation_id']) {
            $calculation = $db->get_calculation($contract['calculation_id']);
        }
        
        // Determinar paso según estado del contrato
        switch ($contract['status']) {
            case 'completed':
                $signing_step = 'signed';
                break;
            case 'sent':
            case 'pending_signature':
                $signing_step = 'signing';
                break;
            case 'declined':
            case 'voided':
                $signing_step = 'error';
                break;
            default:
                if ($contract['payment_status'] === 'completed') {
                    $signing_step = 'ready';
                } else {
                    $signing_step = 'error';
                }
                break;
        }
    }
} else {
    // Si no hay contract_id, buscar contratos pendientes del usuario
    $pending_contracts = $db->get_user_contracts($user->ID, array('completed_payment', 'pending_signature', 'sent'));
    if (!empty($pending_contracts)) {
        $contract = $pending_contracts[0];
        $contract_id = $contract['id'];
        $signing_step = $contract['status'] === 'completed' ? 'signed' : 'ready';
    }
}

// Si no hay contrato válido, mostrar error
if (!$contract) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('No se encontró un contrato válido para firmar.', 'adhesion') . '</p>';
    echo '<p><a href="' . home_url('/mi-cuenta/') . '" class="adhesion-btn adhesion-btn-primary">' . __('Ir a Mi Cuenta', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Configuración de la firma
$signing_config = array(
    'return_url' => add_query_arg(array('contract_id' => $contract_id, 'signed' => '1'), get_permalink()),
    'timeout_minutes' => adhesion_get_option('signing_timeout', 30),
    'reminder_days' => adhesion_get_option('reminder_days', 3)
);
?>

<div class="adhesion-signing-container" id="adhesion-contract-signing">
    
    <!-- Header de firma -->
    <div class="signing-header">
        <h2 class="signing-title">
            <span class="signing-icon">✍️</span>
            <?php _e('Firma de Contrato', 'adhesion'); ?>
        </h2>
        
        <!-- Estado del proceso -->
        <div class="signing-status">
            <div class="status-indicator status-<?php echo esc_attr($signing_step); ?>">
                <?php
                switch ($signing_step) {
                    case 'ready':
                        echo '<span class="status-icon">📋</span>';
                        echo '<span class="status-text">' . __('Listo para firmar', 'adhesion') . '</span>';
                        break;
                    case 'signing':
                        echo '<span class="status-icon">⏳</span>';
                        echo '<span class="status-text">' . __('Pendiente de firma', 'adhesion') . '</span>';
                        break;
                    case 'signed':
                        echo '<span class="status-icon">✅</span>';
                        echo '<span class="status-text">' . __('Firmado correctamente', 'adhesion') . '</span>';
                        break;
                    case 'error':
                        echo '<span class="status-icon">❌</span>';
                        echo '<span class="status-text">' . __('Error en la firma', 'adhesion') . '</span>';
                        break;
                    default:
                        echo '<span class="status-icon">🔄</span>';
                        echo '<span class="status-text">' . __('Cargando...', 'adhesion') . '</span>';
                        break;
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Mensajes de estado -->
    <div id="signing-messages" class="adhesion-messages-container"></div>

    <!-- Información del contrato -->
    <div class="contract-info">
        <h3 class="info-title">
            <span class="info-icon">📄</span>
            <?php _e('Información del contrato', 'adhesion'); ?>
        </h3>
        
        <div class="contract-details">
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Número de contrato:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo esc_html($contract['contract_number'] ?? 'Generando...'); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Cliente:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo esc_html($user->display_name); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Fecha de creación:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo adhesion_format_date($contract['created_at'], 'd/m/Y'); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Importe total:', 'adhesion'); ?></span>
                    <span class="detail-value"><?php echo adhesion_format_price($contract['total_price'] ?? 0); ?></span>
                </div>
                
                <?php if ($contract['payment_status'] === 'completed'): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php _e('Estado del pago:', 'adhesion'); ?></span>
                        <span class="detail-value payment-completed">✅ <?php _e('Pagado', 'adhesion'); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($contract['signed_at'])): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php _e('Fecha de firma:', 'adhesion'); ?></span>
                        <span class="detail-value"><?php echo adhesion_format_date($contract['signed_at'], 'd/m/Y H:i'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Resumen del pedido -->
        <?php if ($calculation): ?>
            <div class="order-summary-section">
                <h4><?php _e('Resumen del pedido:', 'adhesion'); ?></h4>
                <?php
                $materials_data = json_decode($calculation['materials_data'], true);
                if ($materials_data):
                ?>
                    <div class="materials-summary">
                        <?php foreach ($materials_data as $material): ?>
                            <div class="material-item">
                                <span class="material-name"><?php echo esc_html(ucfirst($material['type'])); ?></span>
                                <span class="material-quantity"><?php echo adhesion_format_tons($material['quantity']); ?></span>
                                <span class="material-total"><?php echo adhesion_format_price($material['total']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenido principal según el paso -->
    <div class="signing-content">
        
        <?php if ($signing_step === 'ready'): ?>
            <!-- PASO: Listo para firmar -->
            <div class="signing-step-content" id="ready-to-sign">
                <div class="ready-content">
                    <div class="ready-icon">
                        <span class="icon-large">📋</span>
                    </div>
                    <h3 class="ready-title"><?php _e('Contrato listo para firmar', 'adhesion'); ?></h3>
                    <p class="ready-description">
                        <?php _e('Tu pago ha sido procesado correctamente. Ahora puedes proceder a firmar el contrato digitalmente usando DocuSign.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Información importante -->
                    <div class="signing-info">
                        <h4><?php _e('Información importante:', 'adhesion'); ?></h4>
                        <ul class="info-list">
                            <li>🔒 <?php _e('La firma se realiza de forma segura a través de DocuSign', 'adhesion'); ?></li>
                            <li>📧 <?php _e('Recibirás un email con el enlace para firmar', 'adhesion'); ?></li>
                            <li>⏰ <?php printf(__('Tienes %d días para completar la firma', 'adhesion'), $signing_config['reminder_days']); ?></li>
                            <li>📱 <?php _e('Puedes firmar desde cualquier dispositivo', 'adhesion'); ?></li>
                        </ul>
                    </div>
                    
                    <!-- Acción principal -->
                    <div class="ready-actions">
                        <button type="button" id="start-signing-btn" class="adhesion-btn adhesion-btn-success adhesion-btn-large">
                            <span class="btn-icon">✍️</span>
                            <span class="btn-text"><?php _e('Firmar Contrato Ahora', 'adhesion'); ?></span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span>
                                <?php _e('Preparando firma...', 'adhesion'); ?>
                            </span>
                        </button>
                        
                        <button type="button" id="send-email-btn" class="adhesion-btn adhesion-btn-outline">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Enviar por email', 'adhesion'); ?>
                        </button>
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="additional-info">
                        <details>
                            <summary><?php _e('¿Qué sucede después de firmar?', 'adhesion'); ?></summary>
                            <div class="details-content">
                                <ol>
                                    <li><?php _e('Recibirás una copia del contrato firmado por email', 'adhesion'); ?></li>
                                    <li><?php _e('Nuestro equipo procesará tu pedido', 'adhesion'); ?></li>
                                    <li><?php _e('Te contactaremos para coordinar la entrega', 'adhesion'); ?></li>
                                    <li><?php _e('Podrás descargar el contrato desde tu área de cliente', 'adhesion'); ?></li>
                                </ol>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
            
        <?php elseif ($signing_step === 'signing'): ?>
            <!-- PASO: En proceso de firma -->
            <div class="signing-step-content" id="signing-in-progress">
                <div class="signing-progress">
                    <div class="progress-icon">
                        <div class="spinner-large"></div>
                    </div>
                    <h3 class="progress-title"><?php _e('Firma en proceso', 'adhesion'); ?></h3>
                    <p class="progress-description">
                        <?php _e('El contrato ha sido enviado para firma. Revisa tu email para continuar con el proceso.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Acciones disponibles -->
                    <div class="progress-actions">
                        <button type="button" id="check-status-btn" class="adhesion-btn adhesion-btn-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Verificar Estado', 'adhesion'); ?>
                        </button>
                        
                        <button type="button" id="resend-email-btn" class="adhesion-btn adhesion-btn-outline">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Reenviar Email', 'adhesion'); ?>
                        </button>
                        
                        <?php if (!empty($contract['docusign_envelope_id'])): ?>
                            <button type="button" id="open-docusign-btn" class="adhesion-btn adhesion-btn-secondary">
                                <span class="dashicons dashicons-external"></span>
                                <?php _e('Abrir en DocuSign', 'adhesion'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($signing_step === 'signed'): ?>
            <!-- PASO: Firmado correctamente -->
            <div class="signing-step-content" id="signing-completed">
                <div class="completed-content">
                    <div class="success-animation">
                        <div class="checkmark-circle">
                            <div class="checkmark">✓</div>
                        </div>
                    </div>
                    <h3 class="success-title"><?php _e('¡Contrato firmado exitosamente!', 'adhesion'); ?></h3>
                    <p class="success-description">
                        <?php _e('Tu contrato ha sido firmado digitalmente y está completamente procesado. Ya puedes acceder a tu copia firmada.', 'adhesion'); ?>
                    </p>
                    
                    <!-- Acciones post-firma -->
                    <div class="post-signing-actions">
                        <?php if (!empty($contract['signed_document_path'])): ?>
                            <a href="<?php echo esc_url($contract['signed_document_path']); ?>" 
                               class="adhesion-btn adhesion-btn-success" 
                               download
                               target="_blank">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Descargar Contrato Firmado', 'adhesion'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo home_url('/mi-cuenta/'); ?>" class="adhesion-btn adhesion-btn-primary">
                            <span class="dashicons dashicons-admin-home"></span>
                            <?php _e('Ir a Mi Cuenta', 'adhesion'); ?>
                        </a>
                        
                        <button type="button" id="send-copy-email-btn" class="adhesion-btn adhesion-btn-outline">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Enviar copia por email', 'adhesion'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- PASO: Error o estado no válido -->
            <div class="signing-step-content" id="signing-error">
                <div class="error-content">
                    <div class="error-icon">
                        <span class="icon-large">❌</span>
                    </div>
                    <h3 class="error-title">
                        <?php 
                        if ($contract['status'] === 'declined') {
                            _e('Contrato rechazado', 'adhesion');
                        } elseif ($contract['payment_status'] !== 'completed') {
                            _e('Pago requerido', 'adhesion');
                        } else {
                            _e('Error en el proceso de firma', 'adhesion');
                        }
                        ?>
                    </h3>
                    <p class="error-description">
                        <?php 
                        if ($contract['status'] === 'declined') {
                            _e('El contrato ha sido rechazado durante el proceso de firma. Puedes iniciar un nuevo proceso si deseas continuar.', 'adhesion');
                        } elseif ($contract['payment_status'] !== 'completed') {
                            _e('Debes completar el pago antes de poder firmar el contrato.', 'adhesion');
                        } else {
                            _e('Ha ocurrido un error en el proceso de firma. Por favor, contacta con nosotros para resolverlo.', 'adhesion');
                        }
                        ?>
                    </p>
                    
                    <!-- Acciones de error -->
                    <div class="error-actions">
                        <?php if ($contract['payment_status'] !== 'completed'): ?>
                            <a href="<?php echo add_query_arg('contract_id', $contract_id, home_url('/pago/')); ?>" 
                               class="adhesion-btn adhesion-btn-primary">
                                <span class="dashicons dashicons-money"></span>
                                <?php _e('Completar Pago', 'adhesion'); ?>
                            </a>
                        <?php elseif ($contract['status'] === 'declined'): ?>
                            <a href="<?php echo home_url('/calculadora/'); ?>" 
                               class="adhesion-btn adhesion-btn-primary">
                                <span class="dashicons dashicons-calculator"></span>
                                <?php _e('Nuevo Cálculo', 'adhesion'); ?>
                            </a>
                        <?php else: ?>
                            <button type="button" id="retry-signing-btn" class="adhesion-btn adhesion-btn-primary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Reintentar Firma', 'adhesion'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo home_url('/mi-cuenta/'); ?>" class="adhesion-btn adhesion-btn-outline">
                            <?php _e('Volver a Mi Cuenta', 'adhesion'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript específico para firma de contratos -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Configuración global
    window.adhesionSigningConfig = {
        contractId: <?php echo $contract_id; ?>,
        signingStep: '<?php echo $signing_step; ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('adhesion_nonce'); ?>',
        returnUrl: '<?php echo esc_js($signing_config['return_url']); ?>',
        messages: {
            preparingSigning: '<?php echo esc_js(__('Preparando firma...', 'adhesion')); ?>',
            signingError: '<?php echo esc_js(__('Error iniciando el proceso de firma.', 'adhesion')); ?>',
            checkingStatus: '<?php echo esc_js(__('Verificando estado...', 'adhesion')); ?>',
            emailSent: '<?php echo esc_js(__('Email enviado correctamente.', 'adhesion')); ?>',
            emailError: '<?php echo esc_js(__('Error enviando email.', 'adhesion')); ?>',
            statusUpdated: '<?php echo esc_js(__('Estado actualizado.', 'adhesion')); ?>',
            retryingSigning: '<?php echo esc_js(__('Reintentando proceso de firma...', 'adhesion')); ?>'
        }
    };
    
    // Event listeners
    $('#start-signing-btn').on('click', function(e) {
        e.preventDefault();
        startSigning();
    });
    
    $('#send-email-btn').on('click', function(e) {
        e.preventDefault();
        sendSigningEmail();
    });
    
    $('#check-status-btn').on('click', function(e) {
        e.preventDefault();
        checkSigningStatus();
    });
    
    $('#resend-email-btn').on('click', function(e) {
        e.preventDefault();
        resendSigningEmail();
    });
    
    $('#open-docusign-btn').on('click', function(e) {
        e.preventDefault();
        openDocuSign();
    });
    
    $('#send-copy-email-btn').on('click', function(e) {
        e.preventDefault();
        sendCopyEmail();
    });
    
    $('#retry-signing-btn').on('click', function(e) {
        e.preventDefault();
        retrySigning();
    });
    
    /**
     * Iniciar proceso de firma
     */
    function startSigning() {
        const $btn = $('#start-signing-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_start_signing',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.action === 'redirect' && response.data.url) {
                        // Abrir DocuSign en nueva ventana
                        const signingWindow = window.open(response.data.url, '_blank', 'width=800,height=600');
                        
                        // Opcional: detectar cuando se cierra la ventana
                        const checkClosed = setInterval(function() {
                            if (signingWindow.closed) {
                                clearInterval(checkClosed);
                                // Verificar estado después de cerrar la ventana
                                setTimeout(function() {
                                    checkSigningStatus();
                                }, 2000);
                            }
                        }, 1000);
                        
                        showSigningMessage(response.data.message || 'Redirigiendo a DocuSign...', 'success');
                    } else {
                        // El documento fue enviado por email
                        showSigningMessage(response.data.message || 'Documento enviado por email', 'success');
                        
                        // Cambiar a estado "signing"
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    showSigningMessage(response.data || window.adhesionSigningConfig.messages.signingError, 'error');
                }
            },
            error: function() {
                showSigningMessage(window.adhesionSigningConfig.messages.signingError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Enviar email de firma
     */
    function sendSigningEmail() {
        const $btn = $('#send-email-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_send_signing_email',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage(window.adhesionSigningConfig.messages.emailSent, 'success');
                    
                    // Cambiar a estado "signing"
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showSigningMessage(response.data || window.adhesionSigningConfig.messages.emailError, 'error');
                }
            },
            error: function() {
                showSigningMessage(window.adhesionSigningConfig.messages.emailError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Verificar estado de firma
     */
    function checkSigningStatus() {
        const $btn = $('#check-status-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_check_signing_status',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data.status;
                    
                    if (status === 'completed') {
                        showSigningMessage('¡Contrato firmado correctamente!', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else if (status === 'declined') {
                        showSigningMessage('El contrato ha sido rechazado', 'error');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showSigningMessage(window.adhesionSigningConfig.messages.statusUpdated, 'info');
                    }
                } else {
                    showSigningMessage(response.data || 'Error verificando estado', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error verificando estado', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Reenviar email de firma
     */
    function resendSigningEmail() {
        const $btn = $('#resend-email-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_resend_signing_email',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage(window.adhesionSigningConfig.messages.emailSent, 'success');
                } else {
                    showSigningMessage(response.data || window.adhesionSigningConfig.messages.emailError, 'error');
                }
            },
            error: function() {
                showSigningMessage(window.adhesionSigningConfig.messages.emailError, 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Abrir DocuSign en nueva ventana
     */
    function openDocuSign() {
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_get_signing_url',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    window.open(response.data.url, '_blank', 'width=800,height=600');
                } else {
                    showSigningMessage('No se pudo obtener la URL de firma', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error obteniendo URL de firma', 'error');
            }
        });
    }
    
    /**
     * Enviar copia del contrato firmado por email
     */
    function sendCopyEmail() {
        const $btn = $('#send-copy-email-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_send_contract_copy',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage('Copia enviada por email correctamente', 'success');
                } else {
                    showSigningMessage(response.data || 'Error enviando copia', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error enviando copia', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Reintentar proceso de firma
     */
    function retrySigning() {
        const $btn = $('#retry-signing-btn');
        updateButtonLoading($btn, true);
        
        $.ajax({
            url: window.adhesionSigningConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'adhesion_retry_signing',
                nonce: window.adhesionSigningConfig.nonce,
                contract_id: window.adhesionSigningConfig.contractId
            },
            success: function(response) {
                if (response.success) {
                    showSigningMessage('Proceso reiniciado correctamente', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showSigningMessage(response.data || 'Error reintentando firma', 'error');
                }
            },
            error: function() {
                showSigningMessage('Error reintentando firma', 'error');
            },
            complete: function() {
                updateButtonLoading($btn, false);
            }
        });
    }
    
    /**
     * Funciones de utilidad
     */
    function updateButtonLoading($btn, loading) {
        const $text = $btn.find('.btn-text');
        const $loading = $btn.find('.btn-loading');
        
        if (loading) {
            $btn.prop('disabled', true).addClass('loading');
            if ($text.length) $text.hide();
            if ($loading.length) $loading.show();
        } else {
            $btn.prop('disabled', false).removeClass('loading');
            if ($text.length) $text.show();
            if ($loading.length) $loading.hide();
        }
    }
    
    function showSigningMessage(message, type) {
        const $container = $('#signing-messages');
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
    
    // Verificar si venimos de un retorno de DocuSign
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('signed') === '1') {
        // Verificar estado después de regresar de DocuSign
        setTimeout(function() {
            checkSigningStatus();
        }, 1000);
    }
});
</script>

<style>
/* Estilos específicos para firma de contratos */
.adhesion-signing-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.signing-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 30px 20px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 12px;
}

.signing-title {
    font-size: 2.5em;
    margin: 0 0 15px 0;
    font-weight: 700;
}

.signing-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.signing-status {
    margin-top: 20px;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 1.1em;
}

.status-ready {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.status-signing {
    background: rgba(255, 193, 7, 0.9);
    color: #856404;
}

.status-signed {
    background: rgba(40, 167, 69, 0.9);
    color: white;
}

.status-error {
    background: rgba(220, 53, 69, 0.9);
    color: white;
}

/* Información del contrato */
.contract-info {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-title {
    font-size: 1.4em;
    margin: 0 0 20px 0;
    color: #23282d;
    display: flex;
    align-items: center;
}

.info-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.contract-details {
    margin-bottom: 25px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #495057;
}

.detail-value {
    color: #23282d;
    text-align: right;
}

.payment-completed {
    color: #28a745;
    font-weight: 600;
}

.status-completed {
    color: #28a745;
    font-weight: 600;
}

/* Contenido de pasos */
.signing-content {
    background: white;
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Paso: Listo para firmar */
.ready-content {
    text-align: center;
    padding: 20px;
}

.ready-icon {
    margin-bottom: 20px;
}

.icon-large {
    font-size: 4em;
    display: block;
}

.ready-title {
    font-size: 2em;
    margin: 0 0 15px 0;
    color: #28a745;
}

.ready-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
    line-height: 1.6;
}

.signing-info {
    background: #e8f5e8;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: left;
}

.signing-info h4 {
    margin: 0 0 15px 0;
    color: #155724;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    margin-bottom: 10px;
    color: #155724;
    font-size: 0.95em;
}

.ready-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.additional-info {
    max-width: 600px;
    margin: 0 auto;
    text-align: left;
}

.additional-info details {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
}

.additional-info summary {
    font-weight: 600;
    cursor: pointer;
    color: #007cba;
}

.details-content {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.details-content ol {
    margin: 0;
    color: #495057;
}

.details-content li {
    margin-bottom: 8px;
}

/* Paso: En proceso de firma */
.signing-progress {
    text-align: center;
    padding: 20px;
}

.progress-icon {
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

.progress-title {
    font-size: 1.8em;
    margin: 0 0 15px 0;
    color: #23282d;
}

.progress-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
}

.progress-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

/* Paso: Firmado correctamente */
.completed-content {
    text-align: center;
    padding: 20px;
}

.success-animation {
    margin-bottom: 25px;
}

.checkmark-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    animation: successPulse 0.6s ease-out;
}

.checkmark {
    font-size: 3em;
    color: white;
    font-weight: bold;
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
    margin-bottom: 30px;
}

.post-signing-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

/* Paso: Error */
.error-content {
    text-align: center;
    padding: 20px;
}

.error-icon {
    margin-bottom: 20px;
}

.error-title {
    font-size: 1.8em;
    margin: 0 0 15px 0;
    color: #dc3545;
}

.error-description {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 30px;
}

.error-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
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

.adhesion-btn-secondary {
    background: #6c757d;
    color: white;
}

.adhesion-btn-secondary:hover {
    background: #545b62;
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

/* Responsive design */
@media (max-width: 768px) {
    .adhesion-signing-container {
        padding: 15px;
    }
    
    .signing-title {
        font-size: 2em;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-item {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .detail-value {
        text-align: center;
    }
    
    .ready-actions,
    .progress-actions,
    .post-signing-actions,
    .error-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .adhesion-btn {
        width: 100%;
        max-width: 300px;
    }
}
</style>