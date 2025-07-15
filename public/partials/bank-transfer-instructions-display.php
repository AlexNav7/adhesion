<?php
/**
 * Vista de instrucciones para transferencia bancaria
 * 
 * Muestra los datos bancarios e instrucciones para realizar
 * el pago por transferencia bancaria
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario esté logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para acceder a esta página.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar Sesión', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Verificar variables necesarias
if (!isset($contract) || !isset($amount) || !is_array($contract)) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Error: Datos de pago no disponibles.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Obtener configuración de transferencia bancaria
$settings = get_option('adhesion_settings', array());
$bank_name = $settings['bank_transfer_bank_name'] ?? '';
$bank_iban = $settings['bank_transfer_iban'] ?? '';
$bank_instructions = $settings['bank_transfer_instructions'] ?? '';

// Verificar que la transferencia bancaria esté configurada
if (empty($bank_iban)) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Error: La transferencia bancaria no está configurada. Contacta con el administrador.', 'adhesion') . '</p>';
    echo '</div>';
    return;
}

// Formatear datos para mostrar
$formatted_amount = number_format($amount, 2, ',', '.') . ' €';

// Validar y decodificar datos del contrato
$contract_data = array();
if (isset($contract['client_data']) && !empty($contract['client_data'])) {
    if (is_array($contract['client_data'])) {
        $contract_data = $contract['client_data'];
    } elseif (is_string($contract['client_data'])) {
        $decoded = json_decode($contract['client_data'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $contract_data = $decoded;
        }
    }
}

$company_name = $contract_data['company_name'] ?? 'N/A';
$contract_number = (isset($contract['contract_number']) && !empty($contract['contract_number'])) ? $contract['contract_number'] : 'N/A';

// Generar referencia de pago única
$payment_reference = 'ADH-' . str_pad($contract_id, 6, '0', STR_PAD_LEFT);
?>

<div class="bank-transfer-container">
    
    <!-- Información del contrato -->
    <div class="contract-summary">
        <h2><?php _e('Pago por Transferencia Bancaria', 'adhesion'); ?></h2>
        <div class="summary-info">
            <div class="info-item">
                <span class="label"><?php _e('Empresa:', 'adhesion'); ?></span>
                <span class="value"><?php echo esc_html($company_name); ?></span>
            </div>
            <div class="info-item">
                <span class="label"><?php _e('Contrato:', 'adhesion'); ?></span>
                <span class="value"><?php echo esc_html($contract_number); ?></span>
            </div>
            <div class="info-item">
                <span class="label"><?php _e('Importe:', 'adhesion'); ?></span>
                <span class="value amount-highlight"><?php echo $formatted_amount; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Datos bancarios -->
    <div class="bank-details-section">
        <h3><?php _e('Datos para la Transferencia', 'adhesion'); ?></h3>
        
        <div class="bank-details-grid">
            <?php if (!empty($bank_name)): ?>
            <div class="bank-detail-item">
                <label><?php _e('Banco:', 'adhesion'); ?></label>
                <div class="bank-detail-value">
                    <span class="bank-value"><?php echo esc_html($bank_name); ?></span>
                    <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo esc_js($bank_name); ?>', this)">
                        📋 <?php _e('Copiar', 'adhesion'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="bank-detail-item">
                <label><?php _e('IBAN:', 'adhesion'); ?></label>
                <div class="bank-detail-value">
                    <span class="bank-value iban-value"><?php echo esc_html($bank_iban); ?></span>
                    <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo esc_js($bank_iban); ?>', this)">
                        📋 <?php _e('Copiar', 'adhesion'); ?>
                    </button>
                </div>
            </div>
            
            <div class="bank-detail-item">
                <label><?php _e('Importe:', 'adhesion'); ?></label>
                <div class="bank-detail-value">
                    <span class="bank-value amount-value"><?php echo $formatted_amount; ?></span>
                    <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo esc_js($amount); ?>', this)">
                        📋 <?php _e('Copiar', 'adhesion'); ?>
                    </button>
                </div>
            </div>
            
            <div class="bank-detail-item">
                <label><?php _e('Concepto/Referencia:', 'adhesion'); ?></label>
                <div class="bank-detail-value">
                    <span class="bank-value reference-value"><?php echo esc_html($payment_reference); ?></span>
                    <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo esc_js($payment_reference); ?>', this)">
                        📋 <?php _e('Copiar', 'adhesion'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Instrucciones adicionales -->
    <?php if (!empty($bank_instructions)): ?>
    <div class="instructions-section">
        <h3><?php _e('Instrucciones Adicionales', 'adhesion'); ?></h3>
        <div class="instructions-content">
            <?php echo wp_kses_post(nl2br($bank_instructions)); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Información importante -->
    <div class="important-info-section">
        <h3><?php _e('Información Importante', 'adhesion'); ?></h3>
        <div class="important-info-content">
            <ul>
                <li><strong><?php _e('Referencia obligatoria:', 'adhesion'); ?></strong> <?php _e('Es imprescindible incluir la referencia de pago para identificar tu transferencia.', 'adhesion'); ?></li>
                <li><strong><?php _e('Importe exacto:', 'adhesion'); ?></strong> <?php _e('Transfiere exactamente el importe indicado.', 'adhesion'); ?></li>
                <li><strong><?php _e('Confirmación:', 'adhesion'); ?></strong> <?php _e('Una vez realizada la transferencia, recibirás confirmación en 24-48 horas.', 'adhesion'); ?></li>
                <li><strong><?php _e('Comprobante:', 'adhesion'); ?></strong> <?php _e('Guarda el comprobante de la transferencia como justificante.', 'adhesion'); ?></li>
            </ul>
        </div>
    </div>
    
    <!-- Formulario para confirmar transferencia -->
    <div class="transfer-confirmation-section">
        <h3><?php _e('Confirmar Transferencia Realizada', 'adhesion'); ?></h3>
        <p><?php _e('Una vez hayas realizado la transferencia, puedes confirmarla aquí:', 'adhesion'); ?></p>
        
        <form id="transfer-confirmation-form" method="post" class="confirmation-form">
            <?php wp_nonce_field('adhesion_transfer_confirmation', 'adhesion_transfer_confirmation_nonce'); ?>
            <input type="hidden" name="contract_id" value="<?php echo esc_attr($contract_id); ?>">
            <input type="hidden" name="amount" value="<?php echo esc_attr($amount); ?>">
            <input type="hidden" name="payment_reference" value="<?php echo esc_attr($payment_reference); ?>">
            
            <div class="form-group">
                <label for="transfer_date"><?php _e('Fecha de la transferencia:', 'adhesion'); ?></label>
                <input type="date" id="transfer_date" name="transfer_date" required 
                       max="<?php echo date('Y-m-d'); ?>" 
                       value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="transfer_notes"><?php _e('Notas adicionales (opcional):', 'adhesion'); ?></label>
                <textarea id="transfer_notes" name="transfer_notes" rows="3" 
                          placeholder="<?php _e('Puedes añadir información adicional sobre la transferencia...', 'adhesion'); ?>"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="history.back()">
                    <?php _e('Volver', 'adhesion'); ?>
                </button>
                <button type="submit" class="btn-primary">
                    <?php _e('Confirmar Transferencia Realizada', 'adhesion'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estilos CSS -->
<style>
.bank-transfer-container {
    max-width: 800px;
    margin: 20px auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.contract-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 30px;
    border-left: 4px solid #28a745;
}

.contract-summary h2 {
    margin: 0 0 15px 0;
    color: #28a745;
    font-size: 24px;
}

.summary-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.info-item .label {
    font-weight: 600;
    color: #666;
}

.info-item .value {
    font-weight: 500;
    color: #333;
}

.amount-highlight {
    color: #28a745;
    font-size: 18px;
    font-weight: bold;
}

.bank-details-section,
.instructions-section,
.important-info-section,
.transfer-confirmation-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.bank-details-section h3,
.instructions-section h3,
.important-info-section h3,
.transfer-confirmation-section h3 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #28a745;
}

.bank-details-grid {
    display: grid;
    gap: 20px;
}

.bank-detail-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bank-detail-item label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
}

.bank-detail-value {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.bank-value {
    flex: 1;
    font-family: 'Courier New', monospace;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.iban-value {
    letter-spacing: 1px;
}

.amount-value {
    color: #28a745;
    font-size: 18px;
}

.reference-value {
    color: #007cba;
}

.copy-btn {
    background: #007cba;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.3s ease;
    white-space: nowrap;
}

.copy-btn:hover {
    background: #0056b3;
}

.copy-btn.copied {
    background: #28a745;
}

.instructions-content,
.important-info-content {
    line-height: 1.6;
}

.important-info-content ul {
    margin: 0;
    padding-left: 20px;
}

.important-info-content li {
    margin-bottom: 10px;
}

.confirmation-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: space-between;
    margin-top: 20px;
}

.btn-secondary,
.btn-primary {
    padding: 12px 24px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-primary:hover {
    background: #218838;
}

/* Responsive */
@media (max-width: 768px) {
    .bank-transfer-container {
        margin: 10px;
        padding: 20px;
    }
    
    .summary-info {
        grid-template-columns: 1fr;
    }
    
    .bank-detail-value {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .copy-btn {
        align-self: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<!-- JavaScript -->
<script>
jQuery(document).ready(function($) {
    
    // Manejar envío del formulario de confirmación
    $('#transfer-confirmation-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'adhesion_confirm_transfer',
            nonce: adhesion_ajax.nonce,
            contract_id: $('input[name="contract_id"]').val(),
            amount: $('input[name="amount"]').val(),
            payment_reference: $('input[name="payment_reference"]').val(),
            transfer_date: $('#transfer_date').val(),
            transfer_notes: $('#transfer_notes').val()
        };
        
        const $submitBtn = $(this).find('.btn-primary');
        $submitBtn.prop('disabled', true).text('<?php _e('Confirmando...', 'adhesion'); ?>');
        
        $.post(adhesion_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    alert('<?php _e('Transferencia confirmada. Recibirás una confirmación por email.', 'adhesion'); ?>');
                    window.location.href = response.data.redirect_url || '<?php echo home_url('/mi-cuenta/'); ?>';
                } else {
                    alert('Error: ' + response.data);
                    $submitBtn.prop('disabled', false).text('<?php _e('Confirmar Transferencia Realizada', 'adhesion'); ?>');
                }
            })
            .fail(function() {
                alert('<?php _e('Error de conexión. Por favor, inténtalo de nuevo.', 'adhesion'); ?>');
                $submitBtn.prop('disabled', false).text('<?php _e('Confirmar Transferencia Realizada', 'adhesion'); ?>');
            });
    });
});

/**
 * Copiar texto al portapapeles
 */
function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(function() {
        // Cambiar texto del botón temporalmente
        const originalText = button.textContent;
        button.textContent = '✓ <?php _e('Copiado', 'adhesion'); ?>';
        button.classList.add('copied');
        
        setTimeout(function() {
            button.textContent = originalText;
            button.classList.remove('copied');
        }, 2000);
    }).catch(function() {
        // Fallback para navegadores que no soportan clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Feedback visual
        const originalText = button.textContent;
        button.textContent = '✓ <?php _e('Copiado', 'adhesion'); ?>';
        button.classList.add('copied');
        
        setTimeout(function() {
            button.textContent = originalText;
            button.classList.remove('copied');
        }, 2000);
    });
}
</script>