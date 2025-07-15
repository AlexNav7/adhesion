<?php
/**
 * Handler AJAX para pagos por transferencia bancaria
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Transfer_Payment_Ajax_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_adhesion_create_transfer_payment', array($this, 'create_transfer_payment'));
        add_action('wp_ajax_nopriv_adhesion_create_transfer_payment', array($this, 'create_transfer_payment'));
        add_action('wp_ajax_adhesion_confirm_transfer', array($this, 'confirm_transfer'));
    }
    
    /**
     * Crear pago por transferencia bancaria
     */
    public function create_transfer_payment() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_send_json_error('Error de seguridad');
            return;
        }
        
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no autenticado');
            return;
        }
        
        // Obtener datos del formulario
        $contract_id = intval($_POST['contract_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        if (!$contract_id || !$amount || $payment_method !== 'transfer') {
            wp_send_json_error('Datos de pago inválidos');
            return;
        }
        
        // Verificar que el contrato existe y pertenece al usuario
        $db = new Adhesion_Database();
        $contract = $db->get_contract($contract_id);
        
        if (!$contract || $contract['user_id'] != get_current_user_id()) {
            wp_send_json_error('Contrato no válido o sin permisos');
            return;
        }
        
        // Verificar que la transferencia bancaria está configurada
        $settings = get_option('adhesion_settings', array());
        if (empty($settings['bank_transfer_iban'])) {
            wp_send_json_error('La transferencia bancaria no está configurada');
            return;
        }
        
        try {
            // Actualizar estado del contrato para transferencia bancaria
            global $wpdb;
            $updated = $wpdb->update(
                $wpdb->prefix . 'adhesion_contracts',
                array(
                    'payment_status' => 'pending_transfer',
                    'payment_amount' => $amount,
                    'payment_reference' => 'ADH-' . str_pad($contract_id, 6, '0', STR_PAD_LEFT)
                ),
                array('id' => $contract_id),
                array('%s', '%f', '%s'),
                array('%d')
            );
            
            if ($updated === false) {
                throw new Exception('Error al actualizar el contrato');
            }
            
            // Log del inicio de transferencia
            error_log('[ADHESION] Transferencia bancaria iniciada - Contrato: ' . $contract_id . ' - Usuario: ' . get_current_user_id());
            
            // Construir URL de instrucciones de transferencia
            $redirect_url = add_query_arg(array(
                'payment_method' => 'transfer',
                'contract_id' => $contract_id,
                'amount' => $amount
            ), home_url('/instrucciones-transferencia/'));
            
            wp_send_json_success(array(
                'message' => 'Transferencia bancaria preparada',
                'redirect_url' => $redirect_url
            ));
            
        } catch (Exception $e) {
            error_log('[ADHESION] Error en transferencia bancaria: ' . $e->getMessage());
            wp_send_json_error('Error al procesar la transferencia: ' . $e->getMessage());
        }
    }
    
    /**
     * Confirmar transferencia realizada por el usuario
     */
    public function confirm_transfer() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adhesion_nonce')) {
            wp_send_json_error('Error de seguridad');
            return;
        }
        
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no autenticado');
            return;
        }
        
        // Obtener datos del formulario
        $contract_id = intval($_POST['contract_id']);
        $amount = floatval($_POST['amount']);
        $payment_reference = sanitize_text_field($_POST['payment_reference']);
        $transfer_date = sanitize_text_field($_POST['transfer_date']);
        $transfer_notes = sanitize_textarea_field($_POST['transfer_notes']);
        
        if (!$contract_id || !$amount || !$payment_reference || !$transfer_date) {
            wp_send_json_error('Datos de confirmación incompletos');
            return;
        }
        
        // Verificar que el contrato existe y pertenece al usuario
        $db = new Adhesion_Database();
        $contract = $db->get_contract($contract_id);
        
        if (!$contract || $contract['user_id'] != get_current_user_id()) {
            wp_send_json_error('Contrato no válido o sin permisos');
            return;
        }
        
        try {
            // Actualizar estado del contrato con confirmación de transferencia
            global $wpdb;
            $updated = $wpdb->update(
                $wpdb->prefix . 'adhesion_contracts',
                array(
                    'payment_status' => 'transfer_confirmed',
                    'payment_amount' => $amount,
                    'payment_reference' => $payment_reference,
                    'payment_completed_at' => $transfer_date . ' 00:00:00'
                ),
                array('id' => $contract_id),
                array('%s', '%f', '%s', '%s'),
                array('%d')
            );
            
            if ($updated === false) {
                throw new Exception('Error al confirmar la transferencia');
            }
            
            // Guardar notas adicionales como meta del contrato
            if (!empty($transfer_notes)) {
                $wpdb->insert(
                    $wpdb->prefix . 'adhesion_contract_meta',
                    array(
                        'contract_id' => $contract_id,
                        'meta_key' => 'transfer_notes',
                        'meta_value' => $transfer_notes,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s')
                );
            }
            
            // Log de la confirmación
            error_log('[ADHESION] Transferencia confirmada por usuario - Contrato: ' . $contract_id . ' - Fecha: ' . $transfer_date);
            
            // Enviar email de notificación al admin (opcional)
            $this->send_transfer_notification_email($contract, $amount, $transfer_date, $payment_reference, $transfer_notes);
            
            wp_send_json_success(array(
                'message' => 'Transferencia confirmada correctamente',
                'redirect_url' => home_url('/mi-cuenta/')
            ));
            
        } catch (Exception $e) {
            error_log('[ADHESION] Error confirmando transferencia: ' . $e->getMessage());
            wp_send_json_error('Error al confirmar la transferencia: ' . $e->getMessage());
        }
    }
    
    /**
     * Enviar email de notificación de transferencia al admin
     */
    private function send_transfer_notification_email($contract, $amount, $transfer_date, $reference, $notes) {
        try {
            $settings = get_option('adhesion_settings', array());
            $admin_email = $settings['admin_email'] ?? get_option('admin_email');
            
            if (empty($admin_email)) {
                return false;
            }
            
            // Obtener datos del cliente
            $client_data = array();
            if (!empty($contract['client_data'])) {
                if (is_array($contract['client_data'])) {
                    $client_data = $contract['client_data'];
                } elseif (is_string($contract['client_data'])) {
                    $decoded = json_decode($contract['client_data'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $client_data = $decoded;
                    }
                }
            }
            
            $company_name = $client_data['company_name'] ?? 'N/A';
            $formatted_amount = number_format($amount, 2, ',', '.') . ' €';
            
            $subject = sprintf(
                '[%s] Nueva transferencia bancaria confirmada - Contrato #%d',
                get_bloginfo('name'),
                $contract['id']
            );
            
            $message = "Se ha confirmado una nueva transferencia bancaria:\n\n";
            $message .= "DATOS DEL CONTRATO:\n";
            $message .= "- Contrato: #" . $contract['id'] . "\n";
            $message .= "- Empresa: " . $company_name . "\n";
            $message .= "- Importe: " . $formatted_amount . "\n";
            $message .= "- Referencia: " . $reference . "\n\n";
            
            $message .= "DATOS DE LA TRANSFERENCIA:\n";
            $message .= "- Fecha declarada: " . $transfer_date . "\n";
            if (!empty($notes)) {
                $message .= "- Notas del cliente: " . $notes . "\n";
            }
            $message .= "\n";
            
            $message .= "ACCIÓN REQUERIDA:\n";
            $message .= "- Verificar la transferencia en tu cuenta bancaria\n";
            $message .= "- Confirmar el pago en el panel de administración\n";
            $message .= "- Enviar el contrato para firma una vez verificado el pago\n\n";
            
            $message .= "Panel de administración: " . admin_url('admin.php?page=adhesion-contracts&action=view&contract=' . $contract['id']) . "\n";
            
            wp_mail($admin_email, $subject, $message);
            
            return true;
            
        } catch (Exception $e) {
            error_log('[ADHESION] Error enviando email de notificación de transferencia: ' . $e->getMessage());
            return false;
        }
    }
}

// Inicializar el handler
new Adhesion_Transfer_Payment_Ajax_Handler();