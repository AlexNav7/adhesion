<?php
/**
 * Desinstalación del plugin Adhesión
 * 
 * Este archivo se ejecuta cuando el plugin se DESINSTALA (no solo desactiva)
 * Elimina completamente:
 * - Todas las tablas de la base de datos
 * - Todas las opciones de configuración
 * - Páginas creadas por el plugin
 * - Roles y capacidades
 * - Archivos subidos
 */

// Si no se llama desde WordPress, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Log de inicio de desinstalación
error_log('[ADHESION] 🗑️ Iniciando desinstalación completa del plugin');

// Verificar que realmente queremos desinstalar
if (!current_user_can('delete_plugins')) {
    error_log('[ADHESION] ❌ Usuario sin permisos para desinstalar');
    return;
}

global $wpdb;

try {
    // ==========================================
    // 1. ELIMINAR TABLAS DE LA BASE DE DATOS
    // ==========================================
    
    error_log('[ADHESION] Eliminando tablas de la base de datos...');
    
    $tables_to_delete = array(
        $wpdb->prefix . 'adhesion_calculations',
        $wpdb->prefix . 'adhesion_contracts',
        $wpdb->prefix . 'adhesion_documents',
        $wpdb->prefix . 'adhesion_settings',
        $wpdb->prefix . 'adhesion_calculator_prices'
    );
    
    foreach ($tables_to_delete as $table) {
        // Verificar si la tabla existe antes de eliminarla
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if ($table_exists) {
            $result = $wpdb->query("DROP TABLE IF EXISTS `$table`");
            
            if ($result === false) {
                error_log("[ADHESION] ❌ Error eliminando tabla: $table - " . $wpdb->last_error);
            } else {
                error_log("[ADHESION] ✅ Tabla eliminada: $table");
            }
        } else {
            error_log("[ADHESION] ⚠️ Tabla no existe: $table");
        }
    }
    
    // ==========================================
    // 2. ELIMINAR OPCIONES DE WORDPRESS
    // ==========================================
    
    error_log('[ADHESION] Eliminando opciones de configuración...');
    
    $options_to_delete = array(
        'adhesion_settings',
        'adhesion_activated',
        'adhesion_tables_created',
        'adhesion_version'
    );
    
    foreach ($options_to_delete as $option) {
        $deleted = delete_option($option);
        if ($deleted) {
            error_log("[ADHESION] ✅ Opción eliminada: $option");
        } else {
            error_log("[ADHESION] ⚠️ Opción no existía: $option");
        }
    }
    
    // Eliminar también opciones de sitio para multisitio
    if (is_multisite()) {
        foreach ($options_to_delete as $option) {
            delete_site_option($option);
        }
    }
    
    // ==========================================
    // 3. ELIMINAR PÁGINAS CREADAS POR EL PLUGIN
    // ==========================================
    
    error_log('[ADHESION] Eliminando páginas creadas por el plugin...');
    
    $page_slugs_to_delete = array(
        'calculadora-presupuesto',
        'mi-cuenta-adhesion',
        'proceso-pago',
        'firma-contratos'
    );
    
    foreach ($page_slugs_to_delete as $slug) {
        $page = get_page_by_path($slug);
        
        if ($page) {
            // Verificar que la página contiene shortcodes del plugin antes de eliminarla
            $page_content = $page->post_content;
            if (strpos($page_content, '[adhesion_') !== false) {
                $deleted = wp_delete_post($page->ID, true); // true = forzar eliminación permanente
                
                if ($deleted) {
                    error_log("[ADHESION] ✅ Página eliminada: $slug (ID: {$page->ID})");
                } else {
                    error_log("[ADHESION] ❌ Error eliminando página: $slug");
                }
            } else {
                error_log("[ADHESION] ⚠️ Página existe pero no contiene shortcodes del plugin: $slug");
            }
        } else {
            error_log("[ADHESION] ⚠️ Página no encontrada: $slug");
        }
    }
    
    // ==========================================
    // 4. ELIMINAR ROLES Y CAPACIDADES
    // ==========================================
    
    error_log('[ADHESION] Eliminando roles y capacidades...');
    
    // Eliminar rol de cliente adherido
    if (get_role('adhesion_client')) {
        remove_role('adhesion_client');
        error_log('[ADHESION] ✅ Rol eliminado: adhesion_client');
    }
    
    // Eliminar capacidades de otros roles
    $roles_to_clean = array('administrator', 'editor');
    $caps_to_remove = array(
        'adhesion_manage_all',
        'adhesion_manage_settings',
        'adhesion_manage_documents',
        'adhesion_view_reports',
        'adhesion_access',
        'adhesion_calculate',
        'adhesion_view_account'
    );
    
    foreach ($roles_to_clean as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($caps_to_remove as $cap) {
                if ($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                    error_log("[ADHESION] ✅ Capacidad eliminada: $cap de $role_name");
                }
            }
        }
    }
    
    // ==========================================
    // 5. ELIMINAR ARCHIVOS Y DIRECTORIOS
    // ==========================================
    
    error_log('[ADHESION] Eliminando archivos subidos...');
    
    $upload_dir = wp_upload_dir();
    $adhesion_upload_dir = $upload_dir['basedir'] . '/adhesion/';
    
    if (is_dir($adhesion_upload_dir)) {
        // Función recursiva para eliminar directorio y contenido
        $deleted = adhesion_delete_directory_recursive($adhesion_upload_dir);
        
        if ($deleted) {
            error_log("[ADHESION] ✅ Directorio eliminado: $adhesion_upload_dir");
        } else {
            error_log("[ADHESION] ❌ Error eliminando directorio: $adhesion_upload_dir");
        }
    } else {
        error_log("[ADHESION] ⚠️ Directorio no existe: $adhesion_upload_dir");
    }
    
    // ==========================================
    // 6. LIMPIAR TRANSIENTS Y CACHE
    // ==========================================
    
    error_log('[ADHESION] Limpiando transients y cache...');
    
    // Eliminar transients específicos del plugin
    $transients_to_delete = array(
        'adhesion_calculator_prices',
        'adhesion_active_documents',
        'adhesion_system_status'
    );
    
    foreach ($transients_to_delete as $transient) {
        delete_transient($transient);
        delete_site_transient($transient); // Para multisitio
        error_log("[ADHESION] ✅ Transient eliminado: $transient");
    }
    
    // Eliminar transients de usuarios (notificaciones)
    $users = get_users(array('fields' => 'ID'));
    foreach ($users as $user_id) {
        delete_transient('adhesion_notices_' . $user_id);
    }
    
    // ==========================================
    // 7. LIMPIAR METADATOS DE USUARIOS
    // ==========================================
    
    error_log('[ADHESION] Eliminando metadatos de usuarios...');
    
    // Buscar usuarios que tenían el rol de adhesion_client
    $adhesion_users = get_users(array(
        'meta_key' => 'wp_capabilities',
        'meta_value' => 'adhesion_client',
        'meta_compare' => 'LIKE'
    ));
    
    foreach ($adhesion_users as $user) {
        // Eliminar metadata específico del plugin
        delete_user_meta($user->ID, 'adhesion_last_calculation');
        delete_user_meta($user->ID, 'adhesion_total_calculations');
        delete_user_meta($user->ID, 'adhesion_registration_date');
        
        error_log("[ADHESION] ✅ Metadata eliminado del usuario: {$user->ID}");
    }
    
    // ==========================================
    // 8. LIMPIAR CRON JOBS
    // ==========================================
    
    error_log('[ADHESION] Eliminando tareas programadas...');
    
    // Eliminar cron jobs del plugin
    $cron_hooks = array(
        'adhesion_cleanup_old_data',
        'adhesion_send_reminder_emails',
        'adhesion_update_calculator_prices'
    );
    
    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
            error_log("[ADHESION] ✅ Cron job eliminado: $hook");
        }
    }
    
    // ==========================================
    // 9. VERIFICACIÓN FINAL
    // ==========================================
    
    error_log('[ADHESION] Realizando verificación final...');
    
    $verification_errors = array();
    
    // Verificar que las tablas se eliminaron
    foreach ($tables_to_delete as $table) {
        $still_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($still_exists) {
            $verification_errors[] = "Tabla aún existe: $table";
        }
    }
    
    // Verificar que las opciones se eliminaron
    foreach ($options_to_delete as $option) {
        $still_exists = get_option($option);
        if ($still_exists !== false) {
            $verification_errors[] = "Opción aún existe: $option";
        }
    }
    
    // Verificar que el rol se eliminó
    if (get_role('adhesion_client')) {
        $verification_errors[] = "Rol 'adhesion_client' aún existe";
    }
    
    if (empty($verification_errors)) {
        error_log('[ADHESION] ✅ DESINSTALACIÓN COMPLETADA EXITOSAMENTE - Todo limpio');
    } else {
        error_log('[ADHESION] ⚠️ DESINSTALACIÓN con advertencias:');
        foreach ($verification_errors as $error) {
            error_log("[ADHESION] - $error");
        }
    }
    
    // ==========================================
    // 10. LOG FINAL
    // ==========================================
    
    error_log('[ADHESION] 🎯 Resumen de desinstalación:');
    error_log('[ADHESION] - Tablas de BD: ' . count($tables_to_delete) . ' procesadas');
    error_log('[ADHESION] - Opciones: ' . count($options_to_delete) . ' procesadas');
    error_log('[ADHESION] - Páginas: ' . count($page_slugs_to_delete) . ' verificadas');
    error_log('[ADHESION] - Roles: 1 eliminado + capacidades limpiadas');
    error_log('[ADHESION] - Archivos: directorio uploads limpiado');
    error_log('[ADHESION] - Cache: transients eliminados');
    error_log('[ADHESION] - Usuarios: metadata limpiado');
    error_log('[ADHESION] - Cron: tareas programadas eliminadas');
    error_log('[ADHESION] 🏁 FIN DE DESINSTALACIÓN');
    
} catch (Exception $e) {
    error_log('[ADHESION] ❌ ERROR CRÍTICO durante desinstalación: ' . $e->getMessage());
    error_log('[ADHESION] Stack trace: ' . $e->getTraceAsString());
}

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

/**
 * Eliminar directorio recursivamente
 */
function adhesion_delete_directory_recursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            adhesion_delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * Limpiar opciones que empiecen con cierto prefijo
 */
function adhesion_cleanup_options_by_prefix($prefix) {
    global $wpdb;
    
    $options = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix . '%'
        )
    );
    
    foreach ($options as $option) {
        delete_option($option->option_name);
        error_log("[ADHESION] ✅ Opción eliminada: {$option->option_name}");
    }
}

// Llamar limpieza adicional de opciones que puedan haberse creado dinámicamente
adhesion_cleanup_options_by_prefix('adhesion_temp_');
adhesion_cleanup_options_by_prefix('adhesion_cache_');

// Forzar limpieza de rewrite rules
flush_rewrite_rules();