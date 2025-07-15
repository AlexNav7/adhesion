<?php
/**
 * Vista de configuración del plugin
 * 
 * Página para configurar todas las opciones del plugin:
 * - APIs de Redsys y DocuSign
 * - Configuraciones generales
 * - Precios de calculadora
 * - Opciones de email
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener configuraciones actuales
$settings = get_option('adhesion_settings', array());

// Procesar formulario si se envió
if (isset($_POST['submit']) && wp_verify_nonce($_POST['adhesion_settings_nonce'], 'adhesion_settings_save')) {
    $settings = $_POST['adhesion_settings'];
    
    // Debug temporal: Ver qué llega en POST
    if (defined('ADHESION_DEBUG') && ADHESION_DEBUG) {
        error_log('[ADHESION DEBUG] POST completo: ' . print_r($_POST, true));
        error_log('[ADHESION DEBUG] POST adhesion_settings: ' . print_r($settings, true));
        error_log('[ADHESION DEBUG] bank_transfer_iban: ' . ($settings['bank_transfer_iban'] ?? 'NO ENCONTRADO'));
        error_log('[ADHESION DEBUG] bank_transfer_bank_name: ' . ($settings['bank_transfer_bank_name'] ?? 'NO ENCONTRADO'));
        error_log('[ADHESION DEBUG] bank_transfer_instructions: ' . ($settings['bank_transfer_instructions'] ?? 'NO ENCONTRADO'));
    }
    
    // Sanitizar configuraciones - MANTENER datos existentes
    $current_settings = get_option('adhesion_settings', array());
    $sanitized_settings = $current_settings; // Partir de la configuración existente
    
    // Debug: Mostrar configuración actual antes de modificar
    if (defined('ADHESION_DEBUG') && ADHESION_DEBUG) {
        error_log('[ADHESION DEBUG] Configuración ANTES de modificar:');
        error_log('[ADHESION DEBUG] - IBAN actual: ' . ($current_settings['bank_transfer_iban'] ?? 'VACIO'));
        error_log('[ADHESION DEBUG] - Banco actual: ' . ($current_settings['bank_transfer_bank_name'] ?? 'VACIO'));
        error_log('[ADHESION DEBUG] - Instrucciones actual: ' . ($current_settings['bank_transfer_instructions'] ?? 'VACIO'));
    }
    
    // Configuraciones de Redsys
    $sanitized_settings['redsys_merchant_code'] = sanitize_text_field($settings['redsys_merchant_code'] ?? '');
    $sanitized_settings['redsys_terminal'] = sanitize_text_field($settings['redsys_terminal'] ?? '001');
    $sanitized_settings['redsys_secret_key'] = sanitize_text_field($settings['redsys_secret_key'] ?? '');
    $sanitized_settings['redsys_environment'] = in_array($settings['redsys_environment'] ?? 'test', array('test', 'production')) ? $settings['redsys_environment'] : 'test';
    $sanitized_settings['redsys_currency'] = sanitize_text_field($settings['redsys_currency'] ?? '978');
    
    // Configuraciones de DocuSign
    $sanitized_settings['docusign_integration_key'] = sanitize_text_field($settings['docusign_integration_key'] ?? '');
    $sanitized_settings['docusign_secret_key'] = sanitize_text_field($settings['docusign_secret_key'] ?? '');
    $sanitized_settings['docusign_account_id'] = sanitize_text_field($settings['docusign_account_id'] ?? '');
    $sanitized_settings['docusign_environment'] = in_array($settings['docusign_environment'] ?? 'demo', array('demo', 'production')) ? $settings['docusign_environment'] : 'demo';
    
    // Configuraciones de transferencia bancaria
    $sanitized_settings['bank_transfer_iban'] = sanitize_text_field($settings['bank_transfer_iban'] ?? '');
    $sanitized_settings['bank_transfer_bank_name'] = sanitize_text_field($settings['bank_transfer_bank_name'] ?? '');
    $sanitized_settings['bank_transfer_instructions'] = wp_kses_post($settings['bank_transfer_instructions'] ?? '');
    
    // Debug temporal: Verificar sanitización
    if (defined('ADHESION_DEBUG') && ADHESION_DEBUG) {
        error_log('[ADHESION DEBUG] Sanitización transferencia:');
        error_log('[ADHESION DEBUG] - IBAN original: ' . ($settings['bank_transfer_iban'] ?? 'VACIO'));
        error_log('[ADHESION DEBUG] - IBAN sanitizado: ' . $sanitized_settings['bank_transfer_iban']);
        error_log('[ADHESION DEBUG] - Banco original: ' . ($settings['bank_transfer_bank_name'] ?? 'VACIO'));
        error_log('[ADHESION DEBUG] - Banco sanitizado: ' . $sanitized_settings['bank_transfer_bank_name']);
        error_log('[ADHESION DEBUG] - Instrucciones original: ' . ($settings['bank_transfer_instructions'] ?? 'VACIO'));
        error_log('[ADHESION DEBUG] - Instrucciones sanitizado: ' . $sanitized_settings['bank_transfer_instructions']);
    }
    
    // Configuraciones generales
    $sanitized_settings['calculator_enabled'] = isset($settings['calculator_enabled']) ? '1' : '0';
    $sanitized_settings['auto_create_users'] = isset($settings['auto_create_users']) ? '1' : '0';
    $sanitized_settings['email_notifications'] = isset($settings['email_notifications']) ? '1' : '0';
    $sanitized_settings['contract_auto_send'] = isset($settings['contract_auto_send']) ? '1' : '0';
    $sanitized_settings['require_payment'] = isset($settings['require_payment']) ? '1' : '0';
    
    // Configuraciones de email
    $sanitized_settings['admin_email'] = sanitize_email($settings['admin_email'] ?? get_option('admin_email'));
    $sanitized_settings['email_from_name'] = sanitize_text_field($settings['email_from_name'] ?? get_bloginfo('name'));
    $sanitized_settings['email_from_address'] = sanitize_email($settings['email_from_address'] ?? get_option('admin_email'));
    
    // Guardar configuraciones
    $update_result = update_option('adhesion_settings', $sanitized_settings);
    $settings = $sanitized_settings;
    
    // Debug temporal: Ver qué se guardó
    if (defined('ADHESION_DEBUG') && ADHESION_DEBUG) {
        error_log('[ADHESION DEBUG] Settings guardados: ' . print_r($sanitized_settings, true));
        error_log('[ADHESION DEBUG] Update result: ' . ($update_result ? 'SUCCESS' : 'NO_CHANGES'));
        error_log('[ADHESION DEBUG] IBAN guardado: ' . ($sanitized_settings['bank_transfer_iban'] ?? 'VACIO'));
        
        // Verificar inmediatamente lo que se guardó
        $saved_settings = get_option('adhesion_settings', array());
        error_log('[ADHESION DEBUG] Settings recuperados: ' . print_r($saved_settings, true));
        
        // Verificar específicamente los campos de transferencia
        error_log('[ADHESION DEBUG] Verificación final:');
        error_log('[ADHESION DEBUG] - IBAN final: ' . ($saved_settings['bank_transfer_iban'] ?? 'NO ENCONTRADO'));
        error_log('[ADHESION DEBUG] - Banco final: ' . ($saved_settings['bank_transfer_bank_name'] ?? 'NO ENCONTRADO'));
        error_log('[ADHESION DEBUG] - Instrucciones final: ' . ($saved_settings['bank_transfer_instructions'] ?? 'NO ENCONTRADO'));
    }
    
    // Mostrar mensaje de éxito
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Configuraciones guardadas correctamente.', 'adhesion') . '</strong></p></div>';
}

// Verificar estado de configuración
$redsys_configured = !empty($settings['redsys_merchant_code']) && !empty($settings['redsys_secret_key']);
$docusign_configured = !empty($settings['docusign_integration_key']) && !empty($settings['docusign_account_id']);
$bank_transfer_configured = !empty($settings['bank_transfer_iban']);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Configuración de Adhesión', 'adhesion'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <?php
    // Mostrar notificaciones
    adhesion_display_notices();
    ?>
    
    <!-- Estado de configuración -->
    <div class="adhesion-config-status">
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Estado de Configuración', 'adhesion'); ?></h2>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-status-grid">
                    <div class="status-item-card">
                        <div class="status-icon <?php echo $redsys_configured ? 'status-ok' : 'status-pending'; ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('Redsys (Pagos)', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo $redsys_configured ? __('Configurado correctamente', 'adhesion') : __('Configuración pendiente', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="status-item-card">
                        <div class="status-icon <?php echo $docusign_configured ? 'status-ok' : 'status-pending'; ?>">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('DocuSign (Firmas)', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo $docusign_configured ? __('Configurado correctamente', 'adhesion') : __('Configuración pendiente', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="status-item-card">
                        <div class="status-icon <?php echo $bank_transfer_configured ? 'status-ok' : 'status-pending'; ?>">
                            <span class="dashicons dashicons-admin-home"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('Transferencia Bancaria', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo $bank_transfer_configured ? __('Configurado correctamente', 'adhesion') : __('Configuración pendiente', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="status-item-card">
                        <div class="status-icon <?php echo ($redsys_configured && $docusign_configured) ? 'status-ok' : 'status-warning'; ?>">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="status-content">
                            <h3><?php _e('Plugin', 'adhesion'); ?></h3>
                            <p class="status-text">
                                <?php echo ($redsys_configured && $docusign_configured) ? __('Listo para usar', 'adhesion') : __('Configuración incompleta', 'adhesion'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de configuración -->
    <form method="post" action="" class="adhesion-settings-form">
        <?php wp_nonce_field('adhesion_settings_save', 'adhesion_settings_nonce'); ?>
        
        <!-- Configuración de Redsys -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Configuración de Redsys (Pagos)', 'adhesion'); ?></h2>
                <p class="description"><?php _e('Configuración para procesar pagos con tarjeta a través de Redsys.', 'adhesion'); ?></p>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="redsys_environment"><?php _e('Entorno', 'adhesion'); ?></label>
                        <select name="adhesion_settings[redsys_environment]" id="redsys_environment">
                            <option value="test" <?php selected($settings['redsys_environment'] ?? 'test', 'test'); ?>><?php _e('Pruebas', 'adhesion'); ?></option>
                            <option value="production" <?php selected($settings['redsys_environment'] ?? 'test', 'production'); ?>><?php _e('Producción', 'adhesion'); ?></option>
                        </select>
                        <p class="adhesion-form-help"><?php _e('Usa "Pruebas" mientras desarrollas y "Producción" cuando esté todo listo.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_merchant_code"><?php _e('Código de Comercio', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[redsys_merchant_code]" id="redsys_merchant_code" 
                               value="<?php echo esc_attr($settings['redsys_merchant_code'] ?? ''); ?>" 
                               placeholder="999008881" />
                        <p class="adhesion-form-help"><?php _e('Código proporcionado por Redsys (9 dígitos).', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_terminal"><?php _e('Terminal', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[redsys_terminal]" id="redsys_terminal" 
                               value="<?php echo esc_attr($settings['redsys_terminal'] ?? '001'); ?>" 
                               placeholder="001" />
                        <p class="adhesion-form-help"><?php _e('Número de terminal (por defecto: 001).', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_secret_key"><?php _e('Clave Secreta', 'adhesion'); ?></label>
                        <input type="password" name="adhesion_settings[redsys_secret_key]" id="redsys_secret_key" 
                               value="<?php echo esc_attr($settings['redsys_secret_key'] ?? ''); ?>" 
                               placeholder="sq7HjrUOBfKmC576ILgskD5srU870gJ7" />
                        <p class="adhesion-form-help"><?php _e('Clave secreta proporcionada por Redsys.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="redsys_currency"><?php _e('Moneda', 'adhesion'); ?></label>
                        <select name="adhesion_settings[redsys_currency]" id="redsys_currency">
                            <option value="978" <?php selected($settings['redsys_currency'] ?? '978', '978'); ?>><?php _e('EUR (Euro)', 'adhesion'); ?></option>
                            <option value="840" <?php selected($settings['redsys_currency'] ?? '978', '840'); ?>><?php _e('USD (Dólar)', 'adhesion'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración de DocuSign -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Configuración de DocuSign (Firmas)', 'adhesion'); ?></h2>
                <p class="description"><?php _e('Configuración para la firma digital de contratos con DocuSign.', 'adhesion'); ?></p>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="docusign_environment"><?php _e('Entorno', 'adhesion'); ?></label>
                        <select name="adhesion_settings[docusign_environment]" id="docusign_environment">
                            <option value="demo" <?php selected($settings['docusign_environment'] ?? 'demo', 'demo'); ?>><?php _e('Demo', 'adhesion'); ?></option>
                            <option value="production" <?php selected($settings['docusign_environment'] ?? 'demo', 'production'); ?>><?php _e('Producción', 'adhesion'); ?></option>
                        </select>
                        <p class="adhesion-form-help"><?php _e('Usa "Demo" para pruebas y "Producción" para firmas reales.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="docusign_integration_key"><?php _e('Integration Key', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[docusign_integration_key]" id="docusign_integration_key" 
                               value="<?php echo esc_attr($settings['docusign_integration_key'] ?? ''); ?>" 
                               placeholder="12345678-1234-1234-1234-123456789012" />
                        <p class="adhesion-form-help"><?php _e('Clave de integración de tu aplicación DocuSign.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="docusign_secret_key"><?php _e('Secret Key', 'adhesion'); ?></label>
                        <input type="password" name="adhesion_settings[docusign_secret_key]" id="docusign_secret_key" 
                               value="<?php echo esc_attr($settings['docusign_secret_key'] ?? ''); ?>" 
                               placeholder="abcd1234-ef56-78gh-90ij-klmnopqrstuv" />
                        <p class="adhesion-form-help"><?php _e('Clave secreta de tu aplicación DocuSign.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="docusign_account_id"><?php _e('Account ID', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[docusign_account_id]" id="docusign_account_id" 
                               value="<?php echo esc_attr($settings['docusign_account_id'] ?? ''); ?>" 
                               placeholder="12345678-1234-1234-1234-123456789012" />
                        <p class="adhesion-form-help"><?php _e('ID de cuenta de DocuSign.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración de Transferencia Bancaria -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Configuración de Transferencia Bancaria', 'adhesion'); ?></h2>
                <p class="description"><?php _e('Configuración para pagos por transferencia bancaria como alternativa a Redsys.', 'adhesion'); ?></p>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="bank_transfer_bank_name"><?php _e('Nombre del Banco', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[bank_transfer_bank_name]" id="bank_transfer_bank_name" 
                               value="<?php echo esc_attr($settings['bank_transfer_bank_name'] ?? ''); ?>" 
                               placeholder="Banco Santander, BBVA, CaixaBank..." />
                        <p class="adhesion-form-help"><?php _e('Nombre de la entidad bancaria donde se encuentra la cuenta.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="bank_transfer_iban"><?php _e('IBAN de la Cuenta Bancaria', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[bank_transfer_iban]" id="bank_transfer_iban" 
                               value="<?php echo esc_attr($settings['bank_transfer_iban'] ?? ''); ?>" 
                               placeholder="ES91 2100 0418 4502 0005 1332"
                               pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}"
                               title="Formato IBAN válido: ES91 2100 0418 4502 0005 1332" />
                        <p class="adhesion-form-help"><?php _e('Número IBAN de la cuenta bancaria donde se recibirán las transferencias.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="bank_transfer_instructions"><?php _e('Instrucciones de Transferencia', 'adhesion'); ?></label>
                        <textarea name="adhesion_settings[bank_transfer_instructions]" id="bank_transfer_instructions" 
                                  rows="6" style="width: 100%; max-width: 600px;"
                                  placeholder="<?php _e('Introduce las instrucciones que verán los usuarios para realizar la transferencia...', 'adhesion'); ?>"><?php echo esc_textarea($settings['bank_transfer_instructions'] ?? ''); ?></textarea>
                        <p class="adhesion-form-help"><?php _e('Instrucciones detalladas que verán los usuarios al seleccionar pago por transferencia. Puedes incluir información adicional como el nombre del banco, titular de la cuenta, concepto de pago, etc.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuraciones Generales -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Configuraciones Generales', 'adhesion'); ?></h2>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[calculator_enabled]" value="1" 
                                   <?php checked($settings['calculator_enabled'] ?? '1', '1'); ?> />
                            <?php _e('Habilitar calculadora de presupuestos', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Permite a los usuarios calcular presupuestos antes de adherirse.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[auto_create_users]" value="1" 
                                   <?php checked($settings['auto_create_users'] ?? '1', '1'); ?> />
                            <?php _e('Crear usuarios automáticamente', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Crea cuentas de usuario automáticamente durante el proceso de adhesión.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[require_payment]" value="1" 
                                   <?php checked($settings['require_payment'] ?? '0', '1'); ?> />
                            <?php _e('Requerir pago antes de la firma', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Los usuarios deben pagar antes de firmar el contrato.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[contract_auto_send]" value="1" 
                                   <?php checked($settings['contract_auto_send'] ?? '1', '1'); ?> />
                            <?php _e('Enviar contratos automáticamente', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Envía automáticamente los contratos a DocuSign tras completar los datos.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label>
                            <input type="checkbox" name="adhesion_settings[email_notifications]" value="1" 
                                   <?php checked($settings['email_notifications'] ?? '1', '1'); ?> />
                            <?php _e('Habilitar notificaciones por email', 'adhesion'); ?>
                        </label>
                        <p class="adhesion-form-help"><?php _e('Envía emails de confirmación y actualizaciones de estado.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración de Email -->
        <div class="adhesion-card">
            <div class="adhesion-card-header">
                <h2><?php _e('Configuración de Email', 'adhesion'); ?></h2>
            </div>
            <div class="adhesion-card-body">
                <div class="adhesion-form-group">
                    <div class="adhesion-form-row">
                        <label for="admin_email"><?php _e('Email del Administrador', 'adhesion'); ?></label>
                        <input type="email" name="adhesion_settings[admin_email]" id="admin_email" 
                               value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>" />
                        <p class="adhesion-form-help"><?php _e('Email donde se enviarán las notificaciones administrativas.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="email_from_name"><?php _e('Nombre del Remitente', 'adhesion'); ?></label>
                        <input type="text" name="adhesion_settings[email_from_name]" id="email_from_name" 
                               value="<?php echo esc_attr($settings['email_from_name'] ?? get_bloginfo('name')); ?>" />
                        <p class="adhesion-form-help"><?php _e('Nombre que aparecerá como remitente en los emails.', 'adhesion'); ?></p>
                    </div>
                    
                    <div class="adhesion-form-row">
                        <label for="email_from_address"><?php _e('Email del Remitente', 'adhesion'); ?></label>
                        <input type="email" name="adhesion_settings[email_from_address]" id="email_from_address" 
                               value="<?php echo esc_attr($settings['email_from_address'] ?? get_option('admin_email')); ?>" />
                        <p class="adhesion-form-help"><?php _e('Dirección de email que aparecerá como remitente.', 'adhesion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div class="adhesion-card">
            <div class="adhesion-card-footer">
                <button type="submit" name="submit" class="button button-primary button-large">
                    <?php _e('Guardar Configuración', 'adhesion'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="button button-secondary">
                    <?php _e('Volver al Dashboard', 'adhesion'); ?>
                </a>
                
                <button type="button" class="button" onclick="adhesionTestConfiguration()">
                    <?php _e('Probar Configuración', 'adhesion'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function adhesionTestConfiguration() {
    // Verificar configuración de Redsys
    const merchantCode = document.getElementById('redsys_merchant_code').value;
    const secretKey = document.getElementById('redsys_secret_key').value;
    const integrationKey = document.getElementById('docusign_integration_key').value;
    const accountId = document.getElementById('docusign_account_id').value;
    const bankIban = document.getElementById('bank_transfer_iban').value;
    
    let errors = [];
    let warnings = [];
    
    // Verificaciones obligatorias para DocuSign
    if (!integrationKey) {
        errors.push('<?php echo esc_js(__('Falta la Integration Key de DocuSign', 'adhesion')); ?>');
    }
    
    if (!accountId) {
        errors.push('<?php echo esc_js(__('Falta el Account ID de DocuSign', 'adhesion')); ?>');
    }
    
    // Verificaciones para métodos de pago (al menos uno debe estar configurado)
    const hasRedsys = merchantCode && secretKey;
    const hasBankTransfer = bankIban;
    
    if (!hasRedsys && !hasBankTransfer) {
        errors.push('<?php echo esc_js(__('Debes configurar al menos un método de pago: Redsys o Transferencia Bancaria', 'adhesion')); ?>');
    }
    
    if (!hasRedsys) {
        warnings.push('<?php echo esc_js(__('Redsys no configurado - los pagos con tarjeta no estarán disponibles', 'adhesion')); ?>');
    }
    
    if (!hasBankTransfer) {
        warnings.push('<?php echo esc_js(__('Transferencia bancaria no configurada - esta opción de pago no estará disponible', 'adhesion')); ?>');
    }
    
    if (errors.length > 0) {
        alert('<?php echo esc_js(__('Errores de configuración:', 'adhesion')); ?>\n\n' + errors.join('\n'));
        return;
    }
    
    let message = '<?php echo esc_js(__('Configuración básica completa.', 'adhesion')); ?>';
    
    if (warnings.length > 0) {
        message += '\n\n<?php echo esc_js(__('Advertencias:', 'adhesion')); ?>\n' + warnings.join('\n');
    }
    
    message += '\n\n<?php echo esc_js(__('Para pruebas completas, guarda la configuración y realiza un proceso de adhesión de prueba.', 'adhesion')); ?>';
    
    alert(message);
}

// Mostrar/ocultar campos según el entorno
document.addEventListener('DOMContentLoaded', function() {
    const redsysEnv = document.getElementById('redsys_environment');
    const docusignEnv = document.getElementById('docusign_environment');
    
    function updateEnvironmentNotices() {
        // Agregar avisos visuales para entornos de producción
        const isRedsysProduction = redsysEnv.value === 'production';
        const isDocusignProduction = docusignEnv.value === 'production';
        
        // TODO: Agregar indicadores visuales para entornos de producción
    }
    
    redsysEnv.addEventListener('change', updateEnvironmentNotices);
    docusignEnv.addEventListener('change', updateEnvironmentNotices);
    
    updateEnvironmentNotices();
});
</script>

<style>
/* Estilos específicos para la página de configuración */
.adhesion-config-status {
    margin-bottom: 30px;
}

.adhesion-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.status-item-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #ddd;
}

.status-item-card .status-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.status-item-card .status-icon.status-ok {
    background: var(--adhesion-success);
    border-left-color: var(--adhesion-success);
}

.status-item-card .status-icon.status-pending {
    background: var(--adhesion-warning);
    border-left-color: var(--adhesion-warning);
}

.status-item-card .status-icon.status-warning {
    background: var(--adhesion-error);
    border-left-color: var(--adhesion-error);
}

.status-content h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
}

.status-text {
    margin: 0;
    font-size: 14px;
    color: var(--adhesion-text-secondary);
}

.adhesion-settings-form .adhesion-card {
    margin-bottom: 25px;
}

.adhesion-form-group {
    display: grid;
    gap: 20px;
}

.adhesion-form-row label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--adhesion-text-primary);
}

.adhesion-form-row input[type="text"],
.adhesion-form-row input[type="email"],
.adhesion-form-row input[type="password"],
.adhesion-form-row select {
    width: 100%;
    max-width: 400px;
    padding: 10px 12px;
    border: 1px solid var(--adhesion-border);
    border-radius: var(--adhesion-radius);
    font-size: 14px;
}

.adhesion-form-row input[type="checkbox"] {
    margin-right: 8px;
}

.adhesion-form-help {
    font-size: 13px;
    color: var(--adhesion-text-secondary);
    margin-top: 5px;
    font-style: italic;
    line-height: 1.4;
}

.description {
    color: var(--adhesion-text-secondary);
    font-size: 14px;
    margin: 5px 0 0 0;
}

@media (max-width: 768px) {
    .adhesion-status-grid {
        grid-template-columns: 1fr;
    }
    
    .status-item-card {
        padding: 15px;
    }
    
    .adhesion-form-row input,
    .adhesion-form-row select {
        max-width: 100%;
    }
}
</style>