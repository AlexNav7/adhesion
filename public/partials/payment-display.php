<?php
/**
 * Vista de pago para el sistema de adhesión
 * 
 * Vista que muestra el formulario de pago integrado con Redsys
 * dentro del sistema de pasos del formulario de contrato
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

// Formatear datos para mostrar
$formatted_amount = number_format($amount, 2, ',', '.') . ' €';

// Validar y decodificar datos del contrato
$contract_data = array();
if (isset($contract['client_data']) && !empty($contract['client_data'])) {
    if (is_array($contract['client_data'])) {
        // Ya es un array, usar directamente
        $contract_data = $contract['client_data'];
    } elseif (is_string($contract['client_data'])) {
        // Es una cadena JSON, decodificar
        $decoded = json_decode($contract['client_data'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $contract_data = $decoded;
        }
    }
}

$company_name = $contract_data['company_name'] ?? 'N/A';
$contract_number = (isset($contract['contract_number']) && !empty($contract['contract_number'])) ? $contract['contract_number'] : 'N/A';
?>

<div class="adhesion-payment-container">
    
    <!-- Información del contrato -->
    <div class="contract-summary">
        <h3><?php _e('Resumen del Pedido', 'adhesion'); ?></h3>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="label"><?php _e('Empresa:', 'adhesion'); ?></span>
                <span class="value"><?php echo esc_html($company_name); ?></span>
            </div>
            <div class="summary-item">
                <span class="label"><?php _e('Número de Contrato:', 'adhesion'); ?></span>
                <span class="value"><?php echo esc_html($contract_number); ?></span>
            </div>
            <?php if ($calculation): ?>
            <div class="summary-item">
                <span class="label"><?php _e('Fecha de Cálculo:', 'adhesion'); ?></span>
                <span class="value"><?php echo date_i18n(get_option('date_format'), strtotime($calculation['created_at'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Detalles del pago -->
    <div class="payment-details">
        <h3><?php _e('Detalles del Pago', 'adhesion'); ?></h3>
        <div class="amount-display">
            <span class="amount-label"><?php _e('Importe Total:', 'adhesion'); ?></span>
            <span class="amount-value"><?php echo $formatted_amount; ?></span>
        </div>
        
        <?php if ($calculation): ?>
        <div class="calculation-breakdown">
            <h4><?php _e('Desglose del Cálculo:', 'adhesion'); ?></h4>
            <?php 
            // Validar y decodificar datos del cálculo
            $calc_data = array();
            if (isset($calculation['calculation_data']) && !empty($calculation['calculation_data'])) {
                if (is_array($calculation['calculation_data'])) {
                    // Ya es un array, usar directamente
                    $calc_data = $calculation['calculation_data'];
                } elseif (is_string($calculation['calculation_data'])) {
                    // Es una cadena JSON, decodificar
                    $decoded = json_decode($calculation['calculation_data'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $calc_data = $decoded;
                    }
                }
            }
            
            if ($calc_data && isset($calc_data['materials'])): 
            ?>
            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th><?php _e('Material', 'adhesion'); ?></th>
                        <th><?php _e('Cantidad', 'adhesion'); ?></th>
                        <th><?php _e('Precio/Tn', 'adhesion'); ?></th>
                        <th><?php _e('Total', 'adhesion'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calc_data['materials'] as $material): ?>
                    <tr>
                        <td><?php echo esc_html($material['material'] ?? $material['type'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($material['quantity'] ?? 0, 2, ',', '.'); ?> Tn</td>
                        <td><?php echo number_format($material['price_per_ton'] ?? 0, 2, ',', '.'); ?> €</td>
                        <td><?php 
                        // Buscar el campo de total correcto
                        $total = $material['total_cost'] ?? $material['total'] ?? 0;
                        echo number_format($total, 2, ',', '.'); 
                        ?> €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong><?php _e('Total:', 'adhesion'); ?></strong></td>
                        <td><strong><?php echo $formatted_amount; ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Selección de método de pago -->
    <div class="payment-form-section">
        <h3><?php _e('Selecciona el Método de Pago', 'adhesion'); ?></h3>
        <p class="payment-info">
            <?php _e('Elige cómo deseas realizar el pago de tu adhesión:', 'adhesion'); ?>
        </p>
        
        <div class="payment-methods">
            <!-- Pago con tarjeta (Redsys) -->
            <div class="payment-method" id="payment-card">
                <div class="payment-method-header">
                    <div class="payment-icon">
                        💳
                    </div>
                    <div class="payment-info-text">
                        <h4><?php _e('Pago con Tarjeta', 'adhesion'); ?></h4>
                        <p><?php _e('Pago seguro e inmediato con tarjeta de crédito/débito', 'adhesion'); ?></p>
                    </div>
                </div>
                <div class="payment-features">
                    <ul>
                        <li>✓ <?php _e('Pago inmediato', 'adhesion'); ?></li>
                        <li>✓ <?php _e('Conexión segura SSL', 'adhesion'); ?></li>
                        <li>✓ <?php _e('Tarjetas Visa, Mastercard, etc.', 'adhesion'); ?></li>
                    </ul>
                </div>
                <button type="button" class="btn-payment-method btn-card" data-method="card">
                    <?php _e('Pagar con Tarjeta', 'adhesion'); ?>
                </button>
            </div>
            
            <!-- Transferencia bancaria -->
            <div class="payment-method" id="payment-transfer">
                <div class="payment-method-header">
                    <div class="payment-icon">
                        🏦
                    </div>
                    <div class="payment-info-text">
                        <h4><?php _e('Transferencia Bancaria', 'adhesion'); ?></h4>
                        <p><?php _e('Realiza el pago mediante transferencia bancaria', 'adhesion'); ?></p>
                    </div>
                </div>
                <div class="payment-features">
                    <ul>
                        <li>✓ <?php _e('Sin comisiones adicionales', 'adhesion'); ?></li>
                        <li>✓ <?php _e('Confirmación en 24-48h', 'adhesion'); ?></li>
                        <li>✓ <?php _e('Referencia de pago incluida', 'adhesion'); ?></li>
                    </ul>
                </div>
                <button type="button" class="btn-payment-method btn-transfer" data-method="transfer">
                    <?php _e('Pagar por Transferencia', 'adhesion'); ?>
                </button>
            </div>
        </div>
        
        <!-- Botones de navegación -->
        <div class="payment-navigation">
            <button type="button" class="btn-secondary" onclick="history.back()">
                <?php _e('Volver', 'adhesion'); ?>
            </button>
        </div>
        
        <!-- Formularios ocultos para cada método -->
        <form id="card-payment-form" method="post" class="adhesion-payment-form" style="display: none;">
            <?php wp_nonce_field('adhesion_payment_nonce', 'adhesion_payment_nonce'); ?>
            <input type="hidden" name="contract_id" value="<?php echo esc_attr($contract_id); ?>">
            <input type="hidden" name="amount" value="<?php echo esc_attr($amount); ?>">
            <input type="hidden" name="payment_method" value="card">
        </form>
        
        <form id="transfer-payment-form" method="post" class="adhesion-payment-form" style="display: none;">
            <?php wp_nonce_field('adhesion_payment_nonce', 'adhesion_payment_nonce'); ?>
            <input type="hidden" name="contract_id" value="<?php echo esc_attr($contract_id); ?>">
            <input type="hidden" name="amount" value="<?php echo esc_attr($amount); ?>">
            <input type="hidden" name="payment_method" value="transfer">
        </form>
    </div>
    
    <!-- Información de seguridad -->
    <div class="security-info">
        <h4><?php _e('Información de Seguridad', 'adhesion'); ?></h4>
        <ul>
            <li><?php _e('Todos los pagos se procesan de forma segura a través de Redsys.', 'adhesion'); ?></li>
            <li><?php _e('Tus datos de pago están protegidos con encriptación SSL.', 'adhesion'); ?></li>
            <li><?php _e('No almacenamos información de tarjetas de crédito.', 'adhesion'); ?></li>
        </ul>
    </div>
</div>

<!-- Estilos CSS -->
<style>
.adhesion-payment-container {
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
    border-left: 4px solid #007cba;
}

.contract-summary h3 {
    margin: 0 0 15px 0;
    color: #007cba;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-item .label {
    font-weight: 600;
    color: #666;
}

.summary-item .value {
    font-weight: 500;
    color: #333;
}

.payment-details {
    margin-bottom: 30px;
}

.payment-details h3 {
    color: #dc6c42;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dc6c42;
}

.amount-display {
    background: #e8f5e8;
    padding: 20px;
    border-radius: 6px;
    text-align: center;
    margin-bottom: 20px;
    border: 2px solid #28a745;
}

.amount-label {
    display: block;
    font-size: 18px;
    color: #666;
    margin-bottom: 10px;
}

.amount-value {
    font-size: 32px;
    font-weight: bold;
    color: #28a745;
}

.calculation-breakdown {
    margin-top: 20px;
}

.calculation-breakdown h4 {
    color: #666;
    margin-bottom: 15px;
}

.breakdown-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.breakdown-table th,
.breakdown-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.breakdown-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #666;
}

.breakdown-table .total-row {
    background: #f8f9fa;
    font-weight: 600;
}

.payment-form-section {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 6px;
    margin-bottom: 30px;
}

.payment-form-section h3 {
    color: #dc6c42;
    margin-bottom: 15px;
}

.payment-info {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

.payment-method {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    background: white;
    transition: all 0.3s ease;
    cursor: pointer;
}

.payment-method:hover {
    border-color: #007cba;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 124, 186, 0.1);
}

.payment-method-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.payment-icon {
    font-size: 40px;
    margin-right: 15px;
}

.payment-info-text h4 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 18px;
}

.payment-info-text p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.payment-features {
    margin-bottom: 20px;
}

.payment-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.payment-features li {
    color: #28a745;
    font-size: 14px;
    margin-bottom: 5px;
}

.btn-payment-method {
    width: 100%;
    padding: 15px 20px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    margin-top: 10px;
}

.btn-card {
    background: #007cba;
    color: white;
}

.btn-card:hover {
    background: #0056b3;
}

.btn-transfer {
    background: #28a745;
    color: white;
}

.btn-transfer:hover {
    background: #218838;
}

.payment-navigation {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.btn-secondary,
.btn-primary {
    padding: 15px 30px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-primary {
    background: #007cba;
    color: white;
    position: relative;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.btn-loading {
    display: none;
}

.security-info {
    background: #fff3cd;
    padding: 20px;
    border-radius: 6px;
    border-left: 4px solid #ffc107;
}

.security-info h4 {
    color: #856404;
    margin-bottom: 15px;
}

.security-info ul {
    margin: 0;
    padding-left: 20px;
}

.security-info li {
    color: #856404;
    margin-bottom: 8px;
}

/* Estilos para las instrucciones de transferencia bancaria */
.transfer-instructions-container {
    max-width: 800px;
    margin: 20px auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.transfer-header {
    margin-bottom: 30px;
    text-align: center;
}

.transfer-header h2 {
    color: #dc6c42;
    margin-bottom: 20px;
    font-size: 28px;
}

.transfer-header .contract-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    border-left: 4px solid #28a745;
    margin-bottom: 0;
}

.transfer-header .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.transfer-header .info-item:last-child {
    border-bottom: none;
}

.transfer-header .info-item .label {
    font-weight: 600;
    color: #666;
}

.transfer-header .info-item .value {
    font-weight: 500;
    color: #333;
}

.transfer-header .amount-highlight {
    color: #28a745;
    font-size: 18px;
    font-weight: bold;
}

.bank-details-section {
    margin-bottom: 30px;
}

.bank-details-section h3 {
    color: #dc6c42;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dc6c42;
}

.bank-details-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.bank-detail-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #007cba;
}

.bank-detail-item label {
    display: block;
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
    font-size: 14px;
}

.bank-detail-value {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.bank-value {
    font-family: 'Courier New', monospace;
    font-size: 16px;
    font-weight: 500;
    color: #333;
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #ddd;
    flex: 1;
}

.iban-value {
    letter-spacing: 1px;
    font-size: 14px;
}

.amount-value {
    color: #28a745;
    font-weight: bold;
}

.reference-value {
    color: #dc6c42;
    font-weight: bold;
}

.copy-btn {
    background: #007cba;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.copy-btn:hover {
    background: #0056b3;
}

.copy-btn.copied {
    background: #28a745;
}

.instructions-section {
    margin-bottom: 30px;
}

.instructions-section h3 {
    color: #dc6c42;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dc6c42;
}

.instructions-content {
    background: #fff3cd;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #ffc107;
    color: #856404;
    line-height: 1.6;
}

.important-info-section {
    margin-bottom: 30px;
}

.important-info-section h3 {
    color: #dc3545;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dc3545;
}

.important-info-content {
    background: #f8d7da;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #dc3545;
}

.important-info-content ul {
    margin: 0;
    padding-left: 20px;
}

.important-info-content li {
    color: #721c24;
    margin-bottom: 8px;
    line-height: 1.5;
}

.important-info-content strong {
    color: #721c24;
}

.transfer-confirmation-section {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 6px;
    border-left: 4px solid #007cba;
}

.transfer-confirmation-section h3 {
    color: #007cba;
    margin-bottom: 15px;
}

.confirmation-form {
    margin-top: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #666;
    margin-bottom: 5px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .adhesion-payment-container {
        margin: 10px;
        padding: 20px;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-methods {
        gap: 15px;
    }
    
    .payment-method-header {
        flex-direction: column;
        text-align: center;
    }
    
    .payment-icon {
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .amount-value {
        font-size: 24px;
    }
    
    .breakdown-table {
        font-size: 14px;
    }
    
    /* Responsive para las instrucciones de transferencia */
    .transfer-instructions-container {
        margin: 10px;
        padding: 20px;
    }
    
    .transfer-header h2 {
        font-size: 24px;
    }
    
    .bank-detail-value {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .bank-value {
        font-size: 14px;
    }
    
    .copy-btn {
        align-self: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn-secondary,
    .form-actions .btn-primary {
        width: 100%;
        text-align: center;
    }
}
</style>

<!-- JavaScript para el formulario -->
<script>
jQuery(document).ready(function($) {
    
    // Manejar selección de método de pago
    $('.btn-payment-method').on('click', function() {
        const method = $(this).data('method');
        const $button = $(this);
        
        // Deshabilitar botón y mostrar loading
        $button.prop('disabled', true).text('<?php _e('Procesando...', 'adhesion'); ?>');
        
        if (method === 'card') {
            processCardPayment();
        } else if (method === 'transfer') {
            processTransferPayment();
        }
    });
    
    // Manejar navegación del navegador (botón atrás)
    $(window).on('popstate', function(event) {
        if (event.originalEvent.state) {
            const state = event.originalEvent.state;
            
            if (state.step === 'payment-bankwire' && state.transfer_data) {
                // Mostrar instrucciones de transferencia
                showTransferInstructions(state.transfer_data);
            } else {
                // Recargar la página para otros estados
                location.reload();
            }
        } else {
            // No hay estado, recargar la página
            location.reload();
        }
    });
    
    /**
     * Procesar pago con tarjeta (Redsys)
     */
    function processCardPayment() {
        const formData = {
            action: 'adhesion_create_payment',
            nonce: adhesion_ajax.nonce,
            contract_id: $('input[name="contract_id"]').val(),
            amount: $('input[name="amount"]').val(),
            payment_method: 'card',
            description: '<?php echo esc_js(sprintf(__("Contrato %s - %s", "adhesion"), $contract_number, $company_name)); ?>'
        };
        
        $.post(adhesion_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    // Crear formulario oculto para redirección a Redsys
                    const paymentData = response.data.payment_data;
                    const redsysForm = $('<form>', {
                        action: paymentData.form_url,
                        method: 'post',
                        style: 'display: none;'
                    });
                    
                    // Agregar campos requeridos por Redsys
                    redsysForm.append($('<input>', {
                        type: 'hidden',
                        name: 'Ds_SignatureVersion',
                        value: 'HMAC_SHA256_V1'
                    }));
                    
                    redsysForm.append($('<input>', {
                        type: 'hidden',
                        name: 'Ds_MerchantParameters',
                        value: paymentData.merchant_parameters
                    }));
                    
                    redsysForm.append($('<input>', {
                        type: 'hidden',
                        name: 'Ds_Signature',
                        value: paymentData.signature
                    }));
                    
                    // Agregar al DOM y enviar
                    $('body').append(redsysForm);
                    redsysForm.submit();
                    
                } else {
                    alert('Error: ' + response.data);
                    restoreButtons();
                }
            })
            .fail(function() {
                alert('Error de conexión. Por favor, inténtalo de nuevo.');
                restoreButtons();
            });
    }
    
    /**
     * Procesar pago por transferencia
     */
    function processTransferPayment() {
        const formData = {
            action: 'adhesion_create_transfer_payment',
            nonce: adhesion_ajax.nonce,
            contract_id: $('input[name="contract_id"]').val(),
            amount: $('input[name="amount"]').val(),
            payment_method: 'transfer',
            description: '<?php echo esc_js(sprintf(__("Contrato %s - %s", "adhesion"), $contract_number, $company_name)); ?>'
        };
        
        $.post(adhesion_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    // Cambiar la URL para reflejar el nuevo paso
                    if (response.data.next_step === 'transfer_instructions') {
                        // Obtener parámetros actuales de la URL
                        const urlParams = new URLSearchParams(window.location.search);
                        const contractId = urlParams.get('contract_id');
                        
                        // Construir nueva URL con el paso de transferencia
                        const newUrl = window.location.pathname + '?step=payment-bankwire&contract_id=' + contractId;
                        
                        // Cambiar la URL sin recargar la página
                        window.history.pushState({
                            step: 'payment-bankwire',
                            contract_id: contractId,
                            transfer_data: response.data.transfer_data
                        }, '', newUrl);
                        
                        // Mostrar las instrucciones de transferencia
                        showTransferInstructions(response.data.transfer_data);
                    }
                } else {
                    alert('Error: ' + response.data);
                    restoreButtons();
                }
            })
            .fail(function() {
                alert('Error de conexión. Por favor, inténtalo de nuevo.');
                restoreButtons();
            });
    }
    
    /**
     * Mostrar instrucciones de transferencia bancaria
     */
    function showTransferInstructions(transferData) {
        const instructionsHtml = `
            <div class="transfer-instructions-container">
                <div class="transfer-header">
                    <h2><?php _e('Pago por Transferencia Bancaria', 'adhesion'); ?></h2>
                    <div class="contract-summary">
                        <div class="info-item">
                            <span class="label"><?php _e('Empresa:', 'adhesion'); ?></span>
                            <span class="value">${transferData.company_name}</span>
                        </div>
                        <div class="info-item">
                            <span class="label"><?php _e('Contrato:', 'adhesion'); ?></span>
                            <span class="value">${transferData.contract_number}</span>
                        </div>
                        <div class="info-item">
                            <span class="label"><?php _e('Importe:', 'adhesion'); ?></span>
                            <span class="value amount-highlight">${transferData.formatted_amount}</span>
                        </div>
                    </div>
                </div>
                
                <div class="bank-details-section">
                    <h3><?php _e('Datos para la Transferencia', 'adhesion'); ?></h3>
                    <div class="bank-details-grid">
                        ${transferData.bank_name ? `
                        <div class="bank-detail-item">
                            <label><?php _e('Banco:', 'adhesion'); ?></label>
                            <div class="bank-detail-value">
                                <span class="bank-value">${transferData.bank_name}</span>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('${transferData.bank_name}', this)">
                                    📋 <?php _e('Copiar', 'adhesion'); ?>
                                </button>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="bank-detail-item">
                            <label><?php _e('IBAN:', 'adhesion'); ?></label>
                            <div class="bank-detail-value">
                                <span class="bank-value iban-value">${transferData.bank_iban}</span>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('${transferData.bank_iban}', this)">
                                    📋 <?php _e('Copiar', 'adhesion'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="bank-detail-item">
                            <label><?php _e('Importe:', 'adhesion'); ?></label>
                            <div class="bank-detail-value">
                                <span class="bank-value amount-value">${transferData.formatted_amount}</span>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('${transferData.amount}', this)">
                                    📋 <?php _e('Copiar', 'adhesion'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="bank-detail-item">
                            <label><?php _e('Concepto/Referencia:', 'adhesion'); ?></label>
                            <div class="bank-detail-value">
                                <span class="bank-value reference-value">${transferData.payment_reference}</span>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('${transferData.payment_reference}', this)">
                                    📋 <?php _e('Copiar', 'adhesion'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${transferData.bank_instructions ? `
                <div class="instructions-section">
                    <h3><?php _e('Instrucciones Adicionales', 'adhesion'); ?></h3>
                    <div class="instructions-content">
                        ${transferData.bank_instructions.replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
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
                
                <div class="transfer-confirmation-section">
                    <h3><?php _e('Confirmar Transferencia Realizada', 'adhesion'); ?></h3>
                    <p><?php _e('Una vez hayas realizado la transferencia, puedes confirmarla aquí:', 'adhesion'); ?></p>
                    
                    <form id="transfer-confirmation-form" class="confirmation-form">
                        <input type="hidden" name="contract_id" value="${transferData.contract_id}">
                        <input type="hidden" name="amount" value="${transferData.amount}">
                        <input type="hidden" name="payment_reference" value="${transferData.payment_reference}">
                        
                        <div class="form-group">
                            <label for="transfer_date"><?php _e('Fecha de la transferencia:', 'adhesion'); ?></label>
                            <input type="date" id="transfer_date" name="transfer_date" required 
                                   max="${new Date().toISOString().split('T')[0]}" 
                                   value="${new Date().toISOString().split('T')[0]}">
                        </div>
                        
                        <div class="form-group">
                            <label for="transfer_notes"><?php _e('Notas adicionales (opcional):', 'adhesion'); ?></label>
                            <textarea id="transfer_notes" name="transfer_notes" rows="3" 
                                      placeholder="<?php _e('Puedes añadir información adicional sobre la transferencia...', 'adhesion'); ?>"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="showPaymentMethods()">
                                <?php _e('Volver', 'adhesion'); ?>
                            </button>
                            <button type="submit" class="btn-primary">
                                <?php _e('Confirmar Transferencia Realizada', 'adhesion'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        // Reemplazar el contenido actual con las instrucciones
        $('.adhesion-payment-container').html(instructionsHtml);
        
        // Agregar event listener para el formulario de confirmación
        $('#transfer-confirmation-form').on('submit', function(e) {
            e.preventDefault();
            confirmTransferPayment();
        });
    }
    
    /**
     * Confirmar transferencia realizada
     */
    function confirmTransferPayment() {
        const formData = {
            action: 'adhesion_confirm_transfer',
            nonce: adhesion_ajax.nonce,
            contract_id: $('input[name="contract_id"]').val(),
            amount: $('input[name="amount"]').val(),
            payment_reference: $('input[name="payment_reference"]').val(),
            transfer_date: $('#transfer_date').val(),
            transfer_notes: $('#transfer_notes').val()
        };
        
        const $submitBtn = $('.btn-primary');
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
    }
    
    /**
     * Mostrar métodos de pago (volver al step anterior)
     */
    function showPaymentMethods() {
        // Obtener parámetros actuales de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const contractId = urlParams.get('contract_id');
        
        // Construir URL del paso de pago original
        const paymentUrl = window.location.pathname + '?step=payment&contract_id=' + contractId;
        
        // Cambiar la URL y recargar para volver al paso anterior
        window.location.href = paymentUrl;
    }
    
    /**
     * Restaurar estado de los botones
     */
    function restoreButtons() {
        $('.btn-card').prop('disabled', false).text('<?php _e('Pagar con Tarjeta', 'adhesion'); ?>');
        $('.btn-transfer').prop('disabled', false).text('<?php _e('Pagar por Transferencia', 'adhesion'); ?>');
    }
    
    /**
     * Copiar texto al portapapeles
     */
    window.copyToClipboard = function(text, button) {
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
    };
});
</script>