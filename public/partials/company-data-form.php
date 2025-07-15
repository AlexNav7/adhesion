<?php
/**
 * Formulario de datos de empresa para adhesión
 * 
 * Vista basada en la imagen proporcionada con campos básicos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario esté logueado
if (!is_user_logged_in()) {
    echo '<div class="adhesion-notice adhesion-notice-error">';
    echo '<p>' . __('Debes estar logueado para acceder a este formulario.', 'adhesion') . '</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="adhesion-btn adhesion-btn-primary">' . __('Iniciar Sesión', 'adhesion') . '</a></p>';
    echo '</div>';
    return;
}

// Verificar variables necesarias
if (!isset($calc_id)) {
    $calc_id = isset($_GET['calc_id']) ? intval($_GET['calc_id']) : 0;
}

if (!isset($existing_data)) {
    $existing_data = array();
}

// Obtener información del cálculo si existe
$calculation_summary = '';
if (isset($calculation) && $calculation && is_array($calculation)) {
    if (isset($calculation['calculation_data']) && !empty($calculation['calculation_data'])) {
        $calc_data = is_string($calculation['calculation_data']) 
            ? json_decode($calculation['calculation_data'], true) 
            : $calculation['calculation_data'];
    }
    
    if (isset($calculation['total_price']) && is_numeric($calculation['total_price'])) {
        $calculation_summary = sprintf(
            __('Presupuesto calculado: %s', 'adhesion'),
            number_format($calculation['total_price'], 2) . '€'
        );
    }
}
?>

<div class="adhesion-company-form-container">
    
    <!-- Información del cálculo -->
    <?php if ($calculation_summary): ?>
    <div class="calculation-summary">
        <h3><?php _e('Resumen del Cálculo', 'adhesion'); ?></h3>
        <p><?php echo $calculation_summary; ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Botón de datos demo (solo en modo debug) -->
    <?php if (defined('ADHESION_DEBUG') && ADHESION_DEBUG): ?>
    <div class="demo-data-section">
        <button type="button" id="fill-demo-data" class="btn-demo">
            <?php _e('Datos Demo', 'adhesion'); ?>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Formulario de datos de empresa -->
    <form id="company-data-form" method="post" class="adhesion-form">
        <?php wp_nonce_field('adhesion_company_form', 'adhesion_company_form_nonce'); ?>
        <input type="hidden" name="calc_id" value="<?php echo esc_attr($calc_id); ?>">
        
        <!-- Datos de la empresa -->
        <div class="form-section">
            <h3 class="section-title"><?php _e('DATOS DE LA EMPRESA', 'adhesion'); ?></h3>
            
            <div class="form-row">
                <div class="form-group col-8">
                    <label for="company_name"><?php _e('Denominación social:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="company_name" 
                           name="company_name" 
                           value="<?php echo esc_attr($existing_data['company_name'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-4">
                    <label for="cif"><?php _e('CIF:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="cif" 
                           name="cif" 
                           value="<?php echo esc_attr($existing_data['cif'] ?? ''); ?>" 
                           pattern="[A-Z][0-9]{7}[0-9A-J]"
                           title="Formato: A12345678 (letra + 7 dígitos + letra/número)"
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-12">
                    <label for="address"><?php _e('Domicilio social:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="address" 
                           name="address" 
                           value="<?php echo esc_attr($existing_data['address'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="city"><?php _e('Municipio:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="city" 
                           name="city" 
                           value="<?php echo esc_attr($existing_data['city'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="postal_code"><?php _e('C.P.:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="postal_code" 
                           name="postal_code" 
                           value="<?php echo esc_attr($existing_data['postal_code'] ?? ''); ?>" 
                           pattern="[0-9]{5}"
                           title="Código postal de 5 dígitos"
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="province"><?php _e('Provincia:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="province" 
                           name="province" 
                           value="<?php echo esc_attr($existing_data['province'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="cnae"><?php _e('CNAE:', 'adhesion'); ?></label>
                    <input type="text" 
                           id="cnae" 
                           name="cnae" 
                           value="<?php echo esc_attr($existing_data['cnae'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="phone"><?php _e('Teléfono:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           value="<?php echo esc_attr($existing_data['phone'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="email"><?php _e('e-mail:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo esc_attr($existing_data['email'] ?? ''); ?>" 
                           required>
                </div>
            </div>
        </div>
        
        <!-- Datos del representante legal -->
        <div class="form-section">
            <h3 class="section-title"><?php _e('DATOS DEL REPRESENTANTE LEGAL', 'adhesion'); ?></h3>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="legal_representative_name"><?php _e('Nombre:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="legal_representative_name" 
                           name="legal_representative_name" 
                           value="<?php echo esc_attr($existing_data['legal_representative_name'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="legal_representative_surname"><?php _e('Apellidos:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="legal_representative_surname" 
                           name="legal_representative_surname" 
                           value="<?php echo esc_attr($existing_data['legal_representative_surname'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="legal_representative_dni"><?php _e('DNI:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="legal_representative_dni" 
                           name="legal_representative_dni" 
                           value="<?php echo esc_attr($existing_data['legal_representative_dni'] ?? ''); ?>" 
                           pattern="[0-9]{8}[A-Z]"
                           title="Formato: 12345678A (8 dígitos + letra)"
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="legal_representative_phone"><?php _e('Teléfono:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="tel" 
                           id="legal_representative_phone" 
                           name="legal_representative_phone" 
                           value="<?php echo esc_attr($existing_data['legal_representative_phone'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-12">
                    <label for="legal_representative_email"><?php _e('e-mail:', 'adhesion'); ?></label>
                    <input type="email" 
                           id="legal_representative_email" 
                           name="legal_representative_email" 
                           value="<?php echo esc_attr($existing_data['legal_representative_email'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Datos del contacto para las declaraciones -->
        <div class="form-section">
            <h3 class="section-title"><?php _e('DATOS DE CONTACTO PARA LAS DECLARACIONES', 'adhesion'); ?></h3>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="contact_declaration_name"><?php _e('Nombre:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="contact_declaration_name" 
                           name="contact_declaration_name" 
                           value="<?php echo esc_attr($existing_data['contact_declaration_name'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="contact_declaration_surname"><?php _e('Apellidos:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="contact_declaration_surname" 
                           name="contact_declaration_surname" 
                           value="<?php echo esc_attr($existing_data['legal_representative_surname'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">

                
                <div class="form-group col-6">
                    <label for="contact_declaration_phone"><?php _e('Teléfono:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="tel" 
                           id="contact_declaration_phone" 
                           name="contact_declaration_phone" 
                           value="<?php echo esc_attr($existing_data['legal_representative_phone'] ?? ''); ?>" 
                           required>
                </div>
                <div class="form-group col-12">
                    <label for="contact_declaration_email"><?php _e('e-mail:', 'adhesion'); ?></label>
                    <input type="email" 
                           id="contact_declaration_email" 
                           name="contact_declaration_email" 
                           value="<?php echo esc_attr($existing_data['contact_declaration_email'] ?? ''); ?>">
                </div>
            </div>

        </div>

        <!-- Datos del contacto para la facturacion -->
        <div class="form-section">
            <h3 class="section-title"><?php _e('DATOS DE CONTACTO PARA LA FACTURACIÓN', 'adhesion'); ?></h3>
            
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="contact_billing_name"><?php _e('Nombre:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="contact_billing_name" 
                           name="contact_billing_name" 
                           value="<?php echo esc_attr($existing_data['contact_billing_name'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group col-6">
                    <label for="contact_billing_surname"><?php _e('Apellidos:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="text" 
                           id="contact_billing_surname" 
                           name="contact_billing_surname" 
                           value="<?php echo esc_attr($existing_data['contact_billing_surname'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">

                
                <div class="form-group col-6">
                    <label for="contact_billing_phone"><?php _e('Teléfono:', 'adhesion'); ?> <span class="required">*</span></label>
                    <input type="tel" 
                           id="contact_billing_phone" 
                           name="contact_billing_phone" 
                           value="<?php echo esc_attr($existing_data['contact_billing_phone'] ?? ''); ?>" 
                           required>
                </div>
                <div class="form-group col-12">
                    <label for="contact_billing_email"><?php _e('e-mail:', 'adhesion'); ?></label>
                    <input type="email" 
                           id="contact_billing_email" 
                           name="contact_billing_email" 
                           value="<?php echo esc_attr($existing_data['contact_billing_email'] ?? ''); ?>">
                </div>
            </div>

        </div>

        
        <!-- Botones de acción -->
        <div class="form-actions">
            <?php
            // Construir URL de vuelta a la calculadora con el calc_id
            $calculator_url = home_url('/calculadora-presupuesto/');
            if ($calc_id > 0) {
                $calculator_url = add_query_arg('calc_id', $calc_id, $calculator_url);
            }
            ?>
            <a href="<?php echo esc_url($calculator_url); ?>" class="btn-secondary">
                <?php _e('Volver al Cálculo', 'adhesion'); ?>
            </a>
            <button type="submit" class="btn-primary" id="submit-company-data">
                <?php _e('Continuar al Pago', 'adhesion'); ?>
            </button>
        </div>
    </form>
</div>

<!-- Estilos CSS -->
<style>
.adhesion-company-form-container {
    max-width: 800px;
    margin: 20px auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.calculation-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 30px;
    border-left: 4px solid #007cba;
}

.calculation-summary h3 {
    margin: 0 0 10px 0;
    color: #007cba;
}

.demo-data-section {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.btn-demo {
    background: #ff9f43;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-demo:hover {
    background: #ff7675;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(255, 159, 67, 0.3);
}

.form-section {
    margin-bottom: 40px;
}

.section-title {
    color: white;
    margin: 0 0 25px 0;
    border-radius: 4px;
    font-size: 16px;
    font-weight: bold;
    text-align: left;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.col-4 { flex: 0 0 33.333%; }
.col-6 { flex: 0 0 50%; }
.col-8 { flex: 0 0 66.666%; }
.col-12 { flex: 1; }

.form-group label {
    font-weight: 500;
    margin-bottom: 5px;
    font-size: 14px;
}

.required {
    color: #dc3545;
}

.form-group input {
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 5px rgba(0, 124, 186, 0.3);
}



.form-actions {
    display: flex;
    gap: 15px;
    justify-content: space-between;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.btn-secondary,
.btn-primary {
    padding: 12px 30px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
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
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
}


/* Responsive */
@media (max-width: 768px) {
    .adhesion-company-form-container {
        margin: 10px;
        padding: 20px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .col-4, .col-6, .col-8, .col-12 {
        flex: 1;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<!-- JavaScript para validación -->
<script>
jQuery(document).ready(function($) {
    
    // Limpiar errores al cargar la página
    $('.form-error').remove();
    $('.has-error').removeClass('has-error');
    
    // Formatear CIF en mayúsculas
    $('#cif').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
    
    // Formatear DNI en mayúsculas
    $('#legal_representative_dni').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
    
    // Manejar botón de datos demo
    $('#fill-demo-data').on('click', function() {
        fillDemoData();
    });
    
    // Solo validar al enviar formulario
    $('#company-data-form').on('submit', function(e) {
        // Permitir que HTML5 validation se ejecute primero
        if (!this.checkValidity()) {
            // Si HTML5 validation falla, no hacer nada más
            return;
        }
        
        e.preventDefault();
        submitForm();
    });
    
    function submitForm() {
        const $submitBtn = $('#submit-company-data');
        $submitBtn.prop('disabled', true).text('Guardando...');
        
        // Enviar formulario normalmente (sin AJAX por simplicidad)
        $('#company-data-form')[0].submit();
    }
    
    /**
     * Llenar formulario con datos demo para testing
     */
    function fillDemoData() {
        console.log('Llenando formulario con datos demo...');
        
        // Datos de la empresa
        $('#company_name').val('Empresa Demo S.L.');
        $('#cif').val('B12345678');
        $('#address').val('Calle de la Innovación, 123');
        $('#city').val('Madrid');
        $('#postal_code').val('28001');
        $('#province').val('Madrid');
        $('#cnae').val('4639');
        $('#phone').val('912345678');
        $('#email').val('demo@empresademo.com');
        
        // Datos del representante legal
        $('#legal_representative_name').val('Juan Carlos');
        $('#legal_representative_surname').val('Pérez García');
        $('#legal_representative_dni').val('12345678A');
        $('#legal_representative_phone').val('612345678');
        $('#legal_representative_email').val('juan.perez@empresademo.com');
        
        // Datos del contacto para declaraciones
        $('#contact_declaration_name').val('María');
        $('#contact_declaration_surname').val('López Ruiz');
        $('#contact_declaration_phone').val('623456789');
        $('#contact_declaration_email').val('maria.lopez@empresademo.com');
        
        // Datos del contacto para facturación
        $('#contact_billing_name').val('Carlos');
        $('#contact_billing_surname').val('Martín Sánchez');
        $('#contact_billing_phone').val('634567890');
        $('#contact_billing_email').val('carlos.martin@empresademo.com');
        
        // Mostrar mensaje de confirmación
        alert('✓ Datos demo cargados correctamente');
        
        console.log('Datos demo cargados en todos los campos');
    }
});
</script>