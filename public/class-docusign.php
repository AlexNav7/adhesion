<?php
/**
 * Integración con DocuSign para firma digital
 * 
 * Esta clase maneja toda la integración con DocuSign:
 * - Autenticación OAuth2
 * - Creación de sobres para firma
 * - Gestión de firmantes y documentos
 * - Procesamiento de callbacks
 * - Descarga de documentos firmados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_DocuSign {
    
    /**
     * @var Adhesion_Database
     */
    private $db;
    
    /**
     * @var array Configuraciones de DocuSign
     */
    private $settings;
    
    /**
     * @var string URL base de DocuSign
     */
    private $base_url;
    
    /**
     * @var string Token de acceso actual
     */
    private $access_token;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->load_settings();
        $this->setup_base_url();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para admin
        add_action('wp_ajax_adhesion_send_to_docusign', array($this, 'ajax_send_to_docusign'));
        add_action('wp_ajax_adhesion_check_docusign_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_adhesion_download_signed_document', array($this, 'ajax_download_document'));
        
        // AJAX para frontend
        add_action('wp_ajax_adhesion_start_signing', array($this, 'ajax_start_signing'));
        add_action('wp_ajax_nopriv_adhesion_docusign_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_adhesion_docusign_callback', array($this, 'handle_callback'));
        
        // Callback URL personalizada
        add_action('init', array($this, 'setup_callback_endpoint'));
        add_action('template_redirect', array($this, 'handle_callback_endpoint'));
        
        // Cron job para verificar estados
        add_action('adhesion_check_docusign_status', array($this, 'check_pending_envelopes'));
        
        // Programar cron si no existe
        if (!wp_next_scheduled('adhesion_check_docusign_status')) {
            wp_schedule_event(time(), 'hourly', 'adhesion_check_docusign_status');
        }
    }
    
    /**
     * Cargar configuraciones
     */
    private function load_settings() {
        $this->settings = get_option('adhesion_settings', array());
        
        // Configuraciones por defecto
        $defaults = array(
            'docusign_integration_key' => '',
            'docusign_secret_key' => '',
            'docusign_account_id' => '',
            'docusign_environment' => 'demo',
            'docusign_redirect_uri' => home_url('/adhesion-docusign-callback/')
        );
        
        $this->settings = wp_parse_args($this->settings, $defaults);
    }
    
    /**
     * Configurar URL base según entorno
     */
    private function setup_base_url() {
        $this->base_url = $this->settings['docusign_environment'] === 'production'
            ? 'https://www.docusign.net/restapi'
            : 'https://demo.docusign.net/restapi';
    }
    
    /**
     * Configurar endpoint para callbacks
     */
    public function setup_callback_endpoint() {
        add_rewrite_rule(
            '^adhesion-docusign-callback/?$',
            'index.php?adhesion_docusign_callback=1',
            'top'
        );
        
        add_rewrite_rule(
            '^adhesion-docusign-return/([^/]+)/?$',
            'index.php?adhesion_docusign_return=$matches[1]',
            'top'
        );
    }
    
    /**
     * Manejar endpoint de callback
     */
    public function handle_callback_endpoint() {
        if (get_query_var('adhesion_docusign_callback')) {
            $this->handle_callback();
            exit;
        }
        
        if ($envelope_id = get_query_var('adhesion_docusign_return')) {
            $this->handle_return($envelope_id);
            exit;
        }
    }
    
    /**
     * Obtener token de acceso OAuth2
     */
    private function get_access_token() {
        // Verificar si hay token en caché válido
        $cached_token = get_transient('adhesion_docusign_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return $cached_token;
        }
        
        // Solicitar nuevo token
        $token_url = $this->base_url . '/oauth/token';
        
        $auth_header = base64_encode($this->settings['docusign_integration_key'] . ':' . $this->settings['docusign_secret_key']);
        
        $response = wp_remote_post($token_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . $auth_header,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'scope' => 'signature impersonation'
            )
        ));
        
        if (is_wp_error($response)) {
            adhesion_log('Error obteniendo token DocuSign: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            adhesion_log('Respuesta inválida de DocuSign OAuth: ' . $body, 'error');
            return false;
        }
        
        // Guardar token en caché (expires_in - 60 segundos de margen)
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) - 60 : 3540;
        set_transient('adhesion_docusign_token', $data['access_token'], $expires_in);
        
        $this->access_token = $data['access_token'];
        return $data['access_token'];
    }
    
    /**
     * Crear sobre para firma
     */
    public function create_envelope($contract_id, $recipient_email, $recipient_name, $document_content, $document_name = 'Contrato de Adhesión') {
        try {
            // Validar configuración
            if (!$this->is_configured()) {
                throw new Exception(__('DocuSign no está configurado correctamente.', 'adhesion'));
            }
            
            // Obtener token de acceso
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception(__('No se pudo obtener token de acceso de DocuSign.', 'adhesion'));
            }
            
            // Obtener información del contrato
            $contract = $this->db->get_contract($contract_id);
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Preparar documento PDF
            $pdf_content = $this->generate_pdf_document($document_content);
            if (!$pdf_content) {
                throw new Exception(__('Error generando documento PDF.', 'adhesion'));
            }
            
            // Configurar sobre
            $envelope_definition = array(
                'emailSubject' => sprintf(__('Firma de Contrato - %s', 'adhesion'), $contract['contract_number']),
                'documents' => array(
                    array(
                        'documentBase64' => base64_encode($pdf_content),
                        'name' => $document_name,
                        'fileExtension' => 'pdf',
                        'documentId' => '1'
                    )
                ),
                'recipients' => array(
                    'signers' => array(
                        array(
                            'email' => $recipient_email,
                            'name' => $recipient_name,
                            'recipientId' => '1',
                            'routingOrder' => '1',
                            'tabs' => array(
                                'signHereTabs' => array(
                                    array(
                                        'documentId' => '1',
                                        'pageNumber' => '1',
                                        'xPosition' => '400',
                                        'yPosition' => '650'
                                    )
                                ),
                                'dateSignedTabs' => array(
                                    array(
                                        'documentId' => '1',
                                        'pageNumber' => '1',
                                        'xPosition' => '400',
                                        'yPosition' => '700'
                                    )
                                )
                            )
                        )
                    )
                ),
                'status' => 'sent',
                'eventNotification' => array(
                    'url' => home_url('/adhesion-docusign-callback/'),
                    'loggingEnabled' => 'true',
                    'requireAcknowledgment' => 'true',
                    'envelopeEvents' => array(
                        array('envelopeEventStatusCode' => 'completed'),
                        array('envelopeEventStatusCode' => 'declined'),
                        array('envelopeEventStatusCode' => 'voided')
                    )
                )
            );
            
            // URL del endpoint
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes';
            
            // Realizar petición
            $response = wp_remote_post($url, array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($envelope_definition)
            ));
            
            if (is_wp_error($response)) {
                throw new Exception(__('Error enviando a DocuSign: ', 'adhesion') . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 201) {
                adhesion_log('Error DocuSign: ' . $body, 'error');
                throw new Exception(__('Error creando sobre en DocuSign: ', 'adhesion') . ($data['message'] ?? 'Error desconocido'));
            }
            
            // Guardar envelope ID en el contrato
            $envelope_id = $data['envelopeId'];
            $this->db->update_contract($contract_id, array(
                'docusign_envelope_id' => $envelope_id,
                'status' => 'sent',
                'sent_at' => current_time('mysql')
            ));
            
            // Log de éxito
            adhesion_log("Sobre DocuSign creado: {$envelope_id} para contrato {$contract_id}", 'info');
            
            return array(
                'success' => true,
                'envelope_id' => $envelope_id,
                'status' => 'sent',
                'message' => __('Documento enviado para firma correctamente.', 'adhesion')
            );
            
        } catch (Exception $e) {
            adhesion_log('Error en create_envelope: ' . $e->getMessage(), 'error');
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Obtener URL de firma embedded
     */
    public function get_signing_url($envelope_id, $recipient_email, $recipient_name, $return_url = null) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception(__('No se pudo obtener token de acceso.', 'adhesion'));
            }
            
            if (!$return_url) {
                $return_url = home_url('/adhesion-docusign-return/' . $envelope_id);
            }
            
            $recipient_view = array(
                'authenticationMethod' => 'none',
                'email' => $recipient_email,
                'returnUrl' => $return_url,
                'recipientId' => '1',
                'userName' => $recipient_name
            );
            
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes/' . $envelope_id . '/views/recipient';
            
            $response = wp_remote_post($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($recipient_view)
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['url'])) {
                throw new Exception(__('No se pudo obtener URL de firma.', 'adhesion'));
            }
            
            return $data['url'];
            
        } catch (Exception $e) {
            adhesion_log('Error obteniendo URL de firma: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Verificar estado de sobre
     */
    public function check_envelope_status($envelope_id) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return false;
            }
            
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes/' . $envelope_id;
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return isset($data['status']) ? $data : false;
            
        } catch (Exception $e) {
            adhesion_log('Error verificando estado: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Descargar documento firmado
     */
    public function download_signed_document($envelope_id, $document_id = '1') {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return false;
            }
            
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'] . '/envelopes/' . $envelope_id . '/documents/' . $document_id;
            
            $response = wp_remote_get($url, array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                )
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            return wp_remote_retrieve_body($response);
            
        } catch (Exception $e) {
            adhesion_log('Error descargando documento: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Generar PDF desde contenido HTML
     */
    private function generate_pdf_document($html_content) {
        // Aquí puedes usar una librería como TCPDF, mPDF o DOMPDF
        // Por simplicidad, vamos a generar un PDF básico
        
        // Si tienes TCPDF disponible:
        if (class_exists('TCPDF')) {
            return $this->generate_pdf_with_tcpdf($html_content);
        }
        
        // Alternativa: generar HTML que se puede convertir a PDF
        return $this->generate_html_for_pdf($html_content);
    }
    
    /**
     * Generar PDF con TCPDF
     */
    private function generate_pdf_with_tcpdf($html_content) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configurar documento
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle(__('Contrato de Adhesión', 'adhesion'));
            
            // Configurar página
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Agregar página
            $pdf->AddPage();
            
            // Escribir contenido
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            adhesion_log('Error generando PDF: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Generar HTML para PDF
     */
    private function generate_html_for_pdf($content) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . __('Contrato de Adhesión', 'adhesion') . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 40px; }
                .content { line-height: 1.6; }
                .signature { margin-top: 100px; text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . __('CONTRATO DE ADHESIÓN', 'adhesion') . '</h1>
                <p>' . get_bloginfo('name') . '</p>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="signature">
                <p>' . __('Firma del Cliente:', 'adhesion') . '</p>
                <p>_________________________</p>
                <p>' . __('Fecha:', 'adhesion') . ' _____________</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Manejar callback de DocuSign
     */
    public function handle_callback() {
        try {
            // Obtener datos del callback
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data) {
                adhesion_log('Callback DocuSign inválido: ' . $input, 'error');
                return;
            }
            
            adhesion_log('Callback DocuSign recibido: ' . $input, 'info');
            
            // Extraer información del sobre
            $envelope_id = $data['envelopeId'] ?? '';
            $status = $data['status'] ?? '';
            
            if (!$envelope_id || !$status) {
                adhesion_log('Datos incompletos en callback DocuSign', 'error');
                return;
            }
            
            // Buscar contrato por envelope ID
            $contract = $this->db->get_contract_by_envelope($envelope_id);
            if (!$contract) {
                adhesion_log("Contrato no encontrado para envelope: {$envelope_id}", 'error');
                return;
            }
            
            // Procesar según el estado
            switch ($status) {
                case 'completed':
                    $this->process_completed_envelope($contract, $envelope_id);
                    break;
                    
                case 'declined':
                    $this->process_declined_envelope($contract, $envelope_id);
                    break;
                    
                case 'voided':
                    $this->process_voided_envelope($contract, $envelope_id);
                    break;
            }
            
            // Responder a DocuSign
            wp_send_json_success('Callback procesado correctamente');
            
        } catch (Exception $e) {
            adhesion_log('Error en callback DocuSign: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Procesar sobre completado
     */
    private function process_completed_envelope($contract, $envelope_id) {
        // Descargar documento firmado
        $signed_document = $this->download_signed_document($envelope_id);
        
        if ($signed_document) {
            // Guardar documento firmado
            $upload_dir = wp_upload_dir();
            $filename = 'contrato-firmado-' . $contract['id'] . '-' . time() . '.pdf';
            $filepath = $upload_dir['path'] . '/' . $filename;
            
            file_put_contents($filepath, $signed_document);
            
            // Actualizar contrato
            $this->db->update_contract($contract['id'], array(
                'status' => 'completed',
                'signed_at' => current_time('mysql'),
                'signed_document_path' => $upload_dir['url'] . '/' . $filename
            ));
            
            // Enviar email de confirmación
            $this->send_completion_email($contract, $filepath);
            
            adhesion_log("Contrato {$contract['id']} completado y firmado", 'info');
        }
    }
    
    /**
     * Procesar sobre rechazado
     */
    private function process_declined_envelope($contract, $envelope_id) {
        $this->db->update_contract($contract['id'], array(
            'status' => 'declined',
            'declined_at' => current_time('mysql')
        ));
        
        // Enviar email de notificación
        $this->send_declined_email($contract);
        
        adhesion_log("Contrato {$contract['id']} rechazado por el cliente", 'info');
    }
    
    /**
     * Procesar sobre anulado
     */
    private function process_voided_envelope($contract, $envelope_id) {
        $this->db->update_contract($contract['id'], array(
            'status' => 'voided',
            'voided_at' => current_time('mysql')
        ));
        
        adhesion_log("Contrato {$contract['id']} anulado", 'info');
    }
    
    /**
     * Enviar email de contrato completado
     */
    private function send_completion_email($contract, $document_path = null) {
        $user = get_user_by('ID', $contract['user_id']);
        if (!$user) return;
        
        $subject = sprintf(__('Contrato firmado correctamente - %s', 'adhesion'), $contract['contract_number']);
        
        $message = sprintf(
            __('Estimado/a %s,

Su contrato de adhesión %s ha sido firmado correctamente.

Detalles del contrato:
- Número: %s
- Importe: %s €
- Fecha de firma: %s

El documento firmado está disponible en su área de cliente.

Gracias por confiar en nosotros.

Saludos cordiales,
%s', 'adhesion'),
            $user->display_name,
            $contract['contract_number'],
            $contract['contract_number'],
            number_format($contract['total_amount'], 2, ',', '.'),
            adhesion_format_date($contract['signed_at']),
            get_bloginfo('name')
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Adjuntar documento si está disponible
        $attachments = array();
        if ($document_path && file_exists($document_path)) {
            $attachments[] = $document_path;
        }
        
        wp_mail($user->user_email, $subject, $message, $headers, $attachments);
        
        // Email al administrador
        $admin_email = adhesion_get_option('admin_email', get_option('admin_email'));
        if ($admin_email && $admin_email !== $user->user_email) {
            $admin_subject = sprintf(__('Nuevo contrato firmado - %s', 'adhesion'), $contract['contract_number']);
            wp_mail($admin_email, $admin_subject, $message, $headers);
        }
    }
    
    /**
     * Enviar email de contrato rechazado
     */
    private function send_declined_email($contract) {
        $user = get_user_by('ID', $contract['user_id']);
        if (!$user) return;
        
        $subject = sprintf(__('Contrato no firmado - %s', 'adhesion'), $contract['contract_number']);
        
        $message = sprintf(
            __('Estimado/a %s,

Hemos detectado que no ha firmado el contrato %s.

Si desea proceder con la adhesión, puede volver a acceder al proceso desde su área de cliente.

Si tiene alguna duda, no dude en contactarnos.

Saludos cordiales,
%s', 'adhesion'),
            $user->display_name,
            $contract['contract_number'],
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Verificar configuración de DocuSign
     */
    public function is_configured() {
        return !empty($this->settings['docusign_integration_key']) &&
               !empty($this->settings['docusign_secret_key']) &&
               !empty($this->settings['docusign_account_id']);
    }
    
    /**
     * AJAX: Enviar contrato a DocuSign
     */
    public function ajax_send_to_docusign() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Obtener datos del usuario
            $user = get_user_by('ID', $contract['user_id']);
            if (!$user) {
                throw new Exception(__('Usuario no encontrado.', 'adhesion'));
            }
            
            // Generar contenido del documento
            $document_content = $this->generate_contract_content($contract);
            
            // Crear sobre en DocuSign
            $result = $this->create_envelope(
                $contract_id,
                $user->user_email,
                $user->display_name,
                $document_content
            );
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Iniciar proceso de firma (frontend)
     */
    public function ajax_start_signing() {
        try {
            // Verificar usuario logueado
            if (!is_user_logged_in()) {
                throw new Exception(__('Debe estar logueado.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_public_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $contract_id = intval($_POST['contract_id']);
            $contract = $this->db->get_contract($contract_id);
            
            if (!$contract) {
                throw new Exception(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Verificar que el contrato pertenece al usuario actual
            if ($contract['user_id'] != get_current_user_id()) {
                throw new Exception(__('No tiene permisos para este contrato.', 'adhesion'));
            }
            
            // Verificar que el contrato está listo para firmar
            if ($contract['payment_status'] !== 'completed') {
                throw new Exception(__('El pago debe estar completado antes de firmar.', 'adhesion'));
            }
            
            // Si ya tiene envelope_id, obtener URL de firma
            if (!empty($contract['docusign_envelope_id'])) {
                $signing_url = $this->get_signing_url(
                    $contract['docusign_envelope_id'],
                    wp_get_current_user()->user_email,
                    wp_get_current_user()->display_name
                );
                
                if ($signing_url) {
                    wp_send_json_success(array(
                        'action' => 'redirect',
                        'url' => $signing_url,
                        'message' => __('Redirigiendo a la plataforma de firma...', 'adhesion')
                    ));
                }
            }
            
            // Crear nuevo sobre si no existe
            $user = wp_get_current_user();
            $document_content = $this->generate_contract_content($contract);
            
            $result = $this->create_envelope(
                $contract_id,
                $user->user_email,
                $user->display_name,
                $document_content
            );
            
            if ($result['success']) {
                // Obtener URL de firma
                $signing_url = $this->get_signing_url(
                    $result['envelope_id'],
                    $user->user_email,
                    $user->display_name
                );
                
                if ($signing_url) {
                    wp_send_json_success(array(
                        'action' => 'redirect',
                        'url' => $signing_url,
                        'envelope_id' => $result['envelope_id'],
                        'message' => __('Documento preparado. Redirigiendo...', 'adhesion')
                    ));
                } else {
                    wp_send_json_error(__('Error obteniendo URL de firma.', 'adhesion'));
                }
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Verificar estado de DocuSign
     */
    public function ajax_check_status() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $envelope_id = sanitize_text_field($_POST['envelope_id']);
            
            if (empty($envelope_id)) {
                throw new Exception(__('ID de sobre requerido.', 'adhesion'));
            }
            
            $status = $this->check_envelope_status($envelope_id);
            
            if ($status) {
                wp_send_json_success($status);
            } else {
                wp_send_json_error(__('No se pudo verificar el estado.', 'adhesion'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Descargar documento firmado
     */
    public function ajax_download_document() {
        try {
            // Verificar permisos
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $envelope_id = sanitize_text_field($_POST['envelope_id']);
            $document_id = sanitize_text_field($_POST['document_id'] ?? '1');
            
            $document = $this->download_signed_document($envelope_id, $document_id);
            
            if ($document) {
                // Configurar headers para descarga
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="contrato-firmado-' . $envelope_id . '.pdf"');
                header('Content-Length: ' . strlen($document));
                
                echo $document;
                exit;
            } else {
                wp_send_json_error(__('No se pudo descargar el documento.', 'adhesion'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Manejar retorno desde DocuSign
     */
    public function handle_return($envelope_id) {
        try {
            // Buscar contrato por envelope ID
            $contract = $this->db->get_contract_by_envelope($envelope_id);
            
            if (!$contract) {
                wp_die(__('Contrato no encontrado.', 'adhesion'));
            }
            
            // Verificar estado del sobre
            $status = $this->check_envelope_status($envelope_id);
            
            if ($status && $status['status'] === 'completed') {
                // Redirigir a página de éxito
                $success_url = add_query_arg(array(
                    'adhesion_message' => 'contract_signed',
                    'contract_id' => $contract['id']
                ), home_url('/mi-cuenta/'));
                
                wp_redirect($success_url);
                exit;
            } else {
                // Redirigir a página de contrato con estado actualizado
                $return_url = add_query_arg(array(
                    'adhesion_message' => 'signing_pending',
                    'contract_id' => $contract['id']
                ), home_url('/mi-cuenta/'));
                
                wp_redirect($return_url);
                exit;
            }
            
        } catch (Exception $e) {
            adhesion_log('Error en handle_return: ' . $e->getMessage(), 'error');
            wp_die(__('Error procesando respuesta de firma.', 'adhesion'));
        }
    }
    
    /**
     * Generar contenido del contrato
     */
    private function generate_contract_content($contract) {
        // Obtener plantilla de documento
        $documents = $this->db->get_active_documents();
        $template = '';
        
        if (!empty($documents)) {
            $document = $documents[0]; // Usar primer documento activo
            $template = $document['header'] . "\n\n" . $document['body'] . "\n\n" . $document['footer'];
        } else {
            // Plantilla por defecto
            $template = $this->get_default_contract_template();
        }
        
        // Obtener datos del usuario
        $user = get_user_by('ID', $contract['user_id']);
        $user_meta = get_user_meta($contract['user_id']);
        
        // Obtener datos del cálculo si existe
        $calculation = null;
        if ($contract['calculation_id']) {
            $calculation = $this->db->get_calculation($contract['calculation_id']);
        }
        
        // Variables para reemplazar
        $variables = array(
            '[fecha]' => date('d/m/Y'),
            '[nombre]' => $user->display_name,
            '[email]' => $user->user_email,
            '[numero_contrato]' => $contract['contract_number'],
            '[importe_total]' => number_format($contract['total_amount'], 2, ',', '.') . ' €',
            '[fecha_creacion]' => adhesion_format_date($contract['created_at'], 'd/m/Y'),
            '[empresa]' => get_bloginfo('name'),
            
            // Datos del usuario extendidos
            '[telefono]' => $user_meta['phone'][0] ?? '',
            '[dni]' => $user_meta['dni'][0] ?? '',
            '[direccion]' => $user_meta['address'][0] ?? '',
            '[ciudad]' => $user_meta['city'][0] ?? '',
            '[codigo_postal]' => $user_meta['postal_code'][0] ?? '',
            '[empresa_cliente]' => $user_meta['company'][0] ?? '',
            '[cif_cliente]' => $user_meta['cif'][0] ?? '',
        );
        
        // Variables del cálculo si existe
        if ($calculation) {
            $variables['[materiales]'] = $this->format_calculation_materials($calculation);
            $variables['[subtotal]'] = number_format($calculation['subtotal'], 2, ',', '.') . ' €';
            $variables['[descuentos]'] = number_format($calculation['discount_amount'], 2, ',', '.') . ' €';
            $variables['[iva]'] = number_format($calculation['tax_amount'], 2, ',', '.') . ' €';
            $variables['[total_toneladas]'] = number_format($calculation['total_tons'], 2, ',', '.') . ' t';
        }
        
        // Reemplazar variables en la plantilla
        $content = str_replace(array_keys($variables), array_values($variables), $template);
        
        return $content;
    }
    
    /**
     * Formatear materiales del cálculo para el contrato
     */
    private function format_calculation_materials($calculation) {
        $materials_data = json_decode($calculation['materials_data'], true);
        
        if (!$materials_data) {
            return '';
        }
        
        $formatted = "<table border='1' cellpadding='5' cellspacing='0' width='100%'>\n";
        $formatted .= "<tr><th>Material</th><th>Cantidad (t)</th><th>Precio/t</th><th>Importe</th></tr>\n";
        
        foreach ($materials_data as $material) {
            $formatted .= sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s €</td><td>%s €</td></tr>\n",
                esc_html($material['name']),
                number_format($material['quantity'], 2, ',', '.'),
                number_format($material['price'], 2, ',', '.'),
                number_format($material['total'], 2, ',', '.')
            );
        }
        
        $formatted .= "</table>";
        
        return $formatted;
    }
    
    /**
     * Obtener plantilla por defecto
     */
    private function get_default_contract_template() {
        return '
<h2>CONTRATO DE ADHESIÓN</h2>

<p><strong>Fecha:</strong> [fecha]</p>
<p><strong>Número de contrato:</strong> [numero_contrato]</p>

<h3>DATOS DEL CLIENTE</h3>
<p><strong>Nombre:</strong> [nombre]</p>
<p><strong>Email:</strong> [email]</p>
<p><strong>Teléfono:</strong> [telefono]</p>
<p><strong>DNI/NIE:</strong> [dni]</p>
<p><strong>Dirección:</strong> [direccion]</p>
<p><strong>Ciudad:</strong> [ciudad]</p>
<p><strong>Código Postal:</strong> [codigo_postal]</p>

<h3>DATOS DE LA EMPRESA CLIENTE</h3>
<p><strong>Empresa:</strong> [empresa_cliente]</p>
<p><strong>CIF:</strong> [cif_cliente]</p>

<h3>DETALLE DEL SERVICIO</h3>
<p>El presente contrato regula la adhesión del cliente a los servicios de [empresa].</p>

<h4>Materiales contratados:</h4>
[materiales]

<h3>IMPORTE</h3>
<p><strong>Subtotal:</strong> [subtotal]</p>
<p><strong>Descuentos:</strong> [descuentos]</p>
<p><strong>IVA:</strong> [iva]</p>
<p><strong>TOTAL:</strong> [importe_total]</p>

<h3>CONDICIONES GENERALES</h3>
<p>El cliente acepta las condiciones generales de contratación de [empresa].</p>
<p>El presente contrato entra en vigor a partir de la fecha de firma.</p>

<p>En prueba de conformidad, las partes firman el presente contrato:</p>
';
    }
    
    /**
     * Verificar sobres pendientes (cron job)
     */
    public function check_pending_envelopes() {
        if (!$this->is_configured()) {
            return;
        }
        
        // Obtener contratos con estado 'sent' que no han sido actualizados en las últimas 24h
        $pending_contracts = $this->db->get_pending_docusign_contracts();
        
        foreach ($pending_contracts as $contract) {
            if (empty($contract['docusign_envelope_id'])) {
                continue;
            }
            
            $status = $this->check_envelope_status($contract['docusign_envelope_id']);
            
            if ($status && $status['status'] !== $contract['status']) {
                // Actualizar estado del contrato
                switch ($status['status']) {
                    case 'completed':
                        $this->process_completed_envelope($contract, $contract['docusign_envelope_id']);
                        break;
                        
                    case 'declined':
                        $this->process_declined_envelope($contract, $contract['docusign_envelope_id']);
                        break;
                        
                    case 'voided':
                        $this->process_voided_envelope($contract, $contract['docusign_envelope_id']);
                        break;
                }
                
                adhesion_log("Estado actualizado para contrato {$contract['id']}: {$status['status']}", 'info');
            }
        }
    }
    
    /**
     * Obtener estadísticas de DocuSign
     */
    public function get_docusign_stats() {
        return array(
            'total_sent' => $this->db->count_contracts_by_status('sent'),
            'total_completed' => $this->db->count_contracts_by_status('completed'),
            'total_declined' => $this->db->count_contracts_by_status('declined'),
            'total_pending' => $this->db->count_contracts_by_status('pending'),
            'success_rate' => $this->calculate_success_rate()
        );
    }
    
    /**
     * Calcular tasa de éxito
     */
    private function calculate_success_rate() {
        $total_sent = $this->db->count_contracts_by_status('sent') + $this->db->count_contracts_by_status('completed') + $this->db->count_contracts_by_status('declined');
        $completed = $this->db->count_contracts_by_status('completed');
        
        if ($total_sent === 0) {
            return 0;
        }
        
        return round(($completed / $total_sent) * 100, 2);
    }
    
    /**
     * Limpiar tokens expirados
     */
    public function cleanup_expired_tokens() {
        delete_transient('adhesion_docusign_token');
    }
    
    /**
     * Probar configuración de DocuSign
     */
    public function test_configuration() {
        try {
            if (!$this->is_configured()) {
                throw new Exception(__('DocuSign no está configurado.', 'adhesion'));
            }
            
            $token = $this->get_access_token();
            
            if (!$token) {
                throw new Exception(__('No se pudo obtener token de acceso.', 'adhesion'));
            }
            
            // Probar obteniendo información de la cuenta
            $url = $this->base_url . '/v2.1/accounts/' . $this->settings['docusign_account_id'];
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                return array(
                    'success' => true,
                    'message' => __('Configuración de DocuSign válida.', 'adhesion')
                );
            } else {
                throw new Exception(__('Error de autenticación con DocuSign.', 'adhesion'));
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}

// Función de ayuda global
function adhesion_docusign() {
    return new Adhesion_DocuSign();
}

