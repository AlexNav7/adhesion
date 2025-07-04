<?php
/**
 * Clase para desactivación del plugin
 * 
 * Esta clase se encarga de las tareas de limpieza cuando se desactiva el plugin:
 * - Limpiar datos temporales
 * - Limpiar cache y transients
 * - Log de desactivación
 * 
 * NOTA: Esta clase NO elimina datos permanentes como tablas o configuraciones.
 * Para eso está el archivo uninstall.php que se ejecuta al DESINSTALAR el plugin.
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Deactivator {
    
    /**
     * Método principal de desactivación
     */
    public static function deactivate() {
        // Limpiar tareas programadas
        self::clear_scheduled_tasks();
        
        // Limpiar cache y transients
        self::clear_cache_and_transients();
        
        // Limpiar archivos temporales
        self::clear_temp_files();
        
        // Actualizar permalinks
        flush_rewrite_rules();
        
        // Log de desactivación
        adhesion_log('Plugin desactivado correctamente', 'info');
        
        // Marcar fecha de desactivación
        update_option('adhesion_deactivated', current_time('mysql'));
    }
    
    /**
     * Limpiar tareas programadas (cron jobs)
     */
    private static function clear_scheduled_tasks() {
        // Limpiar eventos cron del plugin
        $scheduled_events = array(
            'adhesion_cleanup_temp_files',
            'adhesion_check_pending_contracts',
            'adhesion_send_reminder_emails',
            'adhesion_docusign_status_check'
        );
        
        foreach ($scheduled_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
                adhesion_log("Evento programado eliminado: $event", 'info');
            }
        }
        
        // Limpiar todos los eventos cron relacionados con el plugin
        wp_clear_scheduled_hook('adhesion_cleanup_temp_files');
        wp_clear_scheduled_hook('adhesion_check_pending_contracts');
        wp_clear_scheduled_hook('adhesion_send_reminder_emails');
        wp_clear_scheduled_hook('adhesion_docusign_status_check');
    }
    
    /**
     * Limpiar cache y transients
     */
    private static function clear_cache_and_transients() {
        global $wpdb;
        
        // Eliminar transients específicos del plugin
        $transients_to_delete = array(
            'adhesion_calculator_prices',
            'adhesion_docusign_token',
            'adhesion_redsys_settings',
            'adhesion_active_documents',
            'adhesion_user_calculations_%', // % para wildcards
            'adhesion_contract_status_%'
        );
        
        foreach ($transients_to_delete as $transient) {
            if (strpos($transient, '%') !== false) {
                // Para transients con wildcards
                $transient_pattern = str_replace('%', '', $transient);
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_' . $transient_pattern . '%',
                    '_transient_timeout_' . $transient_pattern . '%'
                ));
            } else {
                // Para transients específicos
                delete_transient($transient);
            }
        }
        
        // Limpiar object cache si está disponible
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        adhesion_log('Cache y transients limpiados', 'info');
    }
    
    /**
     * Limpiar archivos temporales
     */
    private static function clear_temp_files() {
        // Directorio de archivos temporales del plugin
        $temp_dir = WP_CONTENT_DIR . '/uploads/adhesion/temp/';
        
        if (is_dir($temp_dir)) {
            self::delete_directory_contents($temp_dir, false); // No eliminar el directorio, solo el contenido
            adhesion_log('Archivos temporales limpiados', 'info');
        }
        
        // Limpiar archivos de documentos temporales más antiguos de 24 horas
        $uploads_dir = WP_CONTENT_DIR . '/uploads/adhesion/documents/temp/';
        if (is_dir($uploads_dir)) {
            self::cleanup_old_temp_files($uploads_dir, 24 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Eliminar contenido de un directorio
     */
    private static function delete_directory_contents($dir, $delete_dir = false) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $dir . '/' . $file;
            
            if (is_dir($file_path)) {
                self::delete_directory_contents($file_path, true);
            } else {
                unlink($file_path);
            }
        }
        
        if ($delete_dir) {
            rmdir($dir);
        }
        
        return true;
    }
    
    /**
     * Limpiar archivos temporales antiguos
     */
    private static function cleanup_old_temp_files($dir, $max_age_seconds) {
        if (!is_dir($dir)) {
            return;
        }
        
        $current_time = time();
        $files = glob($dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $file_age = $current_time - filemtime($file);
                
                if ($file_age > $max_age_seconds) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Limpiar opciones temporales (opcional)
     * 
     * Estas opciones se pueden limpiar en la desactivación si se desea,
     * pero generalmente se mantienen para cuando se reactive el plugin
     */
    private static function clear_temporary_options() {
        $temp_options = array(
            'adhesion_temp_calculations',
            'adhesion_pending_notifications',
            'adhesion_last_cron_run',
            'adhesion_docusign_temp_tokens'
        );
        
        foreach ($temp_options as $option) {
            delete_option($option);
        }
        
        adhesion_log('Opciones temporales limpiadas', 'info');
    }
    
    /**
     * Remover capacidades de roles (opcional)
     * 
     * CUIDADO: Solo descomentar si se quiere remover las capacidades
     * al desactivar el plugin. Normalmente se mantienen.
     */
    private static function remove_capabilities() {
        /*
        // Remover capacidades del administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('adhesion_manage_all');
            $admin_role->remove_cap('adhesion_manage_settings');
            $admin_role->remove_cap('adhesion_manage_documents');
            $admin_role->remove_cap('adhesion_view_reports');
        }
        
        // Remover capacidades del editor
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->remove_cap('adhesion_view_reports');
            $editor_role->remove_cap('adhesion_manage_documents');
        }
        
        // NOTA: No eliminamos el rol 'adhesion_client' porque los usuarios
        // podrían tener ese rol asignado y causaría problemas
        */
    }
    
    /**
     * Crear respaldo de configuraciones importantes antes de limpiar
     */
    private static function backup_important_settings() {
        $important_settings = array(
            'redsys_merchant_code',
            'redsys_secret_key',
            'docusign_integration_key',
            'docusign_secret_key',
            'docusign_account_id'
        );
        
        $backup_data = array();
        $current_settings = get_option('adhesion_settings', array());
        
        foreach ($important_settings as $setting) {
            if (isset($current_settings[$setting]) && !empty($current_settings[$setting])) {
                $backup_data[$setting] = $current_settings[$setting];
            }
        }
        
        if (!empty($backup_data)) {
            update_option('adhesion_settings_backup', $backup_data);
            adhesion_log('Respaldo de configuraciones creado', 'info');
        }
    }
}