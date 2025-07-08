<?php
/**
 * Gestión centralizada de emails
 * Archivo: includes/class-email-manager.php
 * 
 * Esta clase maneja:
 * - Carga de templates de email
 * - Reemplazo de variables dinámicas  
 * - Envío de emails con configuración correcta
 * - Logging centralizado de emails
 * - Headers y formato HTML/texto
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Email_Manager {
    
    /**
     * Instancia única
     */
    private static $instance = null;
    
    /**
     * Configuración de emails
     */
    private $settings;
    
    /**
     * Log de emails enviados
     */
    private $email_log = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar configuración
     */
    private function load_settings() {
        $this->settings = array(
            'from_name' => get_option('blogname'),
            'from_email' => get_option('admin_email'),
            'support_email' => adhesion_get_setting('support_email', get_option('admin_email')),
            'reply_to' => adhesion_get_setting('reply_to_email', get_option('admin_email')),
            'enable_html' => true,
            'enable_logging' => adhesion_get_setting('email_logging', true)
        );
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Configurar headers de WordPress
        add_filter('wp_mail_from', array($this, 'set_from_email'));
        add_filter('wp_mail_from_name', array($this, 'set_from_name'));
        add_filter('wp_mail_content_type', array($this, 'set_content_type'));
    }
    
    /**
     * ================================
     * MÉTODOS PRINCIPALES
     * ================================
     */
    
    /**
     * Enviar email usando template
     */
    public function send_email($template, $to, $variables = array(), $options = array()) {
        try {
            // Configuración por defecto
            $options = wp_parse_args($options, array(
                'subject' => '',
                'attachments' => array(),
                'headers' => array(),
                'reply_to' => $this->settings['reply_to'],
                'priority' => 'normal' // normal, high, low
            ));
            
            // Cargar y procesar template
            $template_data = $this->load_template($template, $variables);
            
            if (!$template_data) {
                throw new Exception("Template no encontrado: {$template}");
            }
            
            // Preparar datos del email
            $subject = !empty($options['subject']) ? $options['subject'] : $template_data['subject'];
            $message = $template_data['body'];
            $headers = $this->prepare_headers($options);
            
            // Log antes de enviar
            $this->log_email_attempt($to, $subject, $template);
            
            // Enviar email
            $result = wp_mail($to, $subject, $message, $headers, $options['attachments']);
            
            // Log resultado
            $this->log_email_result($to, $subject, $result);
            
            if (!$result) {
                throw new Exception("Error enviando email a: {$to}");
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->log_email_error($to, $template, $e->getMessage());
            error_log('[ADHESION EMAIL ERROR] ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar email de bienvenida
     */
    public function send_welcome_email($user_id) {

        adhesion_debug_log('=== send_welcome_email() INICIANDO ===', 'EMAIL-MANAGER');
        adhesion_debug_log('User ID: ' . $user_id, 'EMAIL-MANAGER');
        
        $user = get_userdata($user_id);
        if (!$user) {
            adhesion_error_log('Usuario no encontrado con ID: ' . $user_id, 'EMAIL-MANAGER');
            return false;
        }
        
        adhesion_debug_log('Usuario encontrado: ' . $user->user_email, 'EMAIL-MANAGER');
        
        // Obtener metadatos del usuario
        $user_meta = get_user_meta($user_id);
        
        // Preparar variables para el template
        $variables = array(
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_cif' => $user_meta['cif'][0] ?? 'N/A',
            'empresa' => $user_meta['empresa'][0] ?? 'N/A',
            'site_name' => get_bloginfo('name'),
            'account_url' => home_url('/mi-cuenta/'),
            'login_url' => wp_login_url(),
            'support_email' => $this->settings['support_email']
        );
        
        // Configurar opciones del email
        $options = array(
            'subject' => sprintf(__('¡Bienvenido a %s! Tu cuenta está lista', 'adhesion'), get_bloginfo('name')),
            'priority' => 'high'
        );
        
        return $this->send_email('user/welcome', $user->user_email, $variables, $options);
    }
    
    /**
     * ================================
     * MÉTODOS DE TEMPLATE
     * ================================
     */
    
    /**
     * Cargar template de email
     */
    private function load_template($template, $variables = array()) {
        // Construir ruta del template
        $template_path = ADHESION_PLUGIN_PATH . 'templates/emails/' . $template . '.php';
        
        if (!file_exists($template_path)) {
            error_log("[ADHESION EMAIL] Template no encontrado: {$template_path}");
            return false;
        }
        
        // Extraer variables para el template
        extract($variables);
        
        // Capturar output del template
        ob_start();
        include $template_path;
        $body = ob_get_clean();
        
        // Obtener subject del template si existe
        $subject = $this->extract_subject_from_template($body);
        
        return array(
            'body' => $body,
            'subject' => $subject
        );
    }
    
    /**
     * Extraer subject del template (si está definido)
     */
    private function extract_subject_from_template($body) {
        // Buscar tag <title> en el template
        preg_match('/<title>(.*?)<\/title>/i', $body, $matches);
        return isset($matches[1]) ? strip_tags($matches[1]) : '';
    }
    
    /**
     * ================================
     * CONFIGURACIÓN DE HEADERS
     * ================================
     */
    
    /**
     * Preparar headers del email
     */
    private function prepare_headers($options) {
        $headers = array();
        
        // Content-Type
        if ($this->settings['enable_html']) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        
        // Reply-To
        if (!empty($options['reply_to'])) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }
        
        // Prioridad
        switch ($options['priority']) {
            case 'high':
                $headers[] = 'X-Priority: 1';
                $headers[] = 'X-MSMail-Priority: High';
                break;
            case 'low':
                $headers[] = 'X-Priority: 5';
                $headers[] = 'X-MSMail-Priority: Low';
                break;
        }
        
        // Headers adicionales
        if (!empty($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }
        
        // Headers del plugin
        $headers[] = 'X-Mailer: Adhesion Plugin v' . ADHESION_PLUGIN_VERSION;
        $headers[] = 'X-Adhesion-Type: automated';
        
        return $headers;
    }
    
    /**
     * Configurar email remitente
     */
    public function set_from_email($email) {
        return $this->settings['from_email'];
    }
    
    /**
     * Configurar nombre del remitente
     */
    public function set_from_name($name) {
        return $this->settings['from_name'];
    }
    
    /**
     * Configurar tipo de contenido
     */
    public function set_content_type($content_type) {
        return $this->settings['enable_html'] ? 'text/html' : 'text/plain';
    }
    
    /**
     * ================================
     * LOGGING Y DEBUG
     * ================================
     */
    
    /**
     * Log intento de envío
     */
    private function log_email_attempt($to, $subject, $template) {
        if (!$this->settings['enable_logging']) return;
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'status' => 'sending'
        );
        
        $this->email_log[] = $log_entry;
        error_log("[ADHESION EMAIL] Enviando a {$to} | Template: {$template} | Subject: {$subject}");
    }
    
    /**
     * Log resultado del envío
     */
    private function log_email_result($to, $subject, $success) {
        if (!$this->settings['enable_logging']) return;
        
        $status = $success ? 'sent' : 'failed';
        $level = $success ? 'info' : 'error';
        
        // Actualizar último log
        if (!empty($this->email_log)) {
            $last_index = count($this->email_log) - 1;
            $this->email_log[$last_index]['status'] = $status;
            $this->email_log[$last_index]['sent_at'] = current_time('mysql');
        }
        
        error_log("[ADHESION EMAIL {$level}] Email {$status} a {$to}");
        
        // Guardar en database para admin
        $this->save_email_log($to, $subject, $status);
    }
    
    /**
     * Log errores
     */
    private function log_email_error($to, $template, $error) {
        error_log("[ADHESION EMAIL ERROR] Error enviando {$template} a {$to}: {$error}");
        $this->save_email_log($to, "Error: {$template}", 'error', $error);
    }
    
    /**
     * Guardar log en base de datos
     */
    private function save_email_log($to, $subject, $status, $error = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'adhesion_email_log';
        
        // Crear tabla si no existe
        $this->maybe_create_email_log_table();
        
        $wpdb->insert(
            $table,
            array(
                'recipient' => $to,
                'subject' => $subject,
                'status' => $status,
                'error_message' => $error,
                'sent_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Crear tabla de logs si no existe
     */
    private function maybe_create_email_log_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'adhesion_email_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            $sql = "CREATE TABLE {$table} (
                id int(11) NOT NULL AUTO_INCREMENT,
                recipient varchar(255) NOT NULL,
                subject varchar(500) NOT NULL,
                status varchar(20) NOT NULL,
                error_message text,
                sent_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY recipient (recipient),
                KEY status (status),
                KEY sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * ================================
     * MÉTODOS DE UTILIDAD
     * ================================
     */
    
    /**
     * Obtener estadísticas de emails
     */
    public function get_email_stats($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'adhesion_email_log';
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
             FROM {$table} 
             WHERE sent_at >= %s 
             GROUP BY status",
            $since
        ));
    }
    
    /**
     * Verificar configuración de email
     */
    public function test_email_configuration() {
        $test_email = get_option('admin_email');
        
        return $this->send_email(
            'system/test',
            $test_email,
            array(
                'site_name' => get_bloginfo('name'),
                'test_time' => current_time('mysql')
            ),
            array('subject' => 'Test de configuración - Adhesión Plugin')
        );
    }
}