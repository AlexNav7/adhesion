<?php
/**
 * Clase para activación del plugin - CON VERIFICACIÓN Y FALLBACK
 * 
 * Esta versión:
 * 1. Intenta crear tablas con dbDelta
 * 2. Si falla, usa SQL directo
 * 3. Verifica que las tablas existen
 * 4. Solo continúa si TODO está correcto
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Activator {
    
    /**
     * Método principal de activación
     */
    public static function activate() {
        try {
            // Verificar requisitos mínimos
            self::check_requirements();
            
            // Crear tablas con verificación
            $tables_created = self::create_tables_with_verification();
            
            if (!$tables_created) {
                // ABORT - No se pudieron crear las tablas
                wp_die(
                    __('Error: No se pudieron crear las tablas de la base de datos. Contacta con el administrador.', 'adhesion'),
                    __('Error de Activación - Adhesión', 'adhesion')
                );
            }
            
            // Solo continuar si las tablas están OK
            self::create_pages();
            self::set_default_options();
            self::create_roles_and_capabilities();
            self::create_upload_directory();
            
            // Marcar activación exitosa
            update_option('adhesion_tables_created', 'yes');
            update_option('adhesion_activated', current_time('mysql'));
            
            flush_rewrite_rules();
            
            error_log('[ADHESION] ✅ Plugin activado correctamente con todas las tablas');
            
        } catch (Exception $e) {
            error_log('[ADHESION ERROR] ' . $e->getMessage());
            wp_die(
                sprintf(__('Error activando plugin Adhesión: %s', 'adhesion'), $e->getMessage()),
                __('Error de Activación', 'adhesion')
            );
        }
    }
    
    /**
     * Crear tablas con verificación obligatoria
     */
    private static function create_tables_with_verification() {
        global $wpdb;
        
        error_log('[ADHESION] Iniciando creación de tablas...');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // PASO 1: Intentar con dbDelta (método WordPress estándar)
        $dbdelta_success = self::try_create_with_dbdelta($charset_collate);
        
        // PASO 2: Verificar si las tablas existen
        $tables_exist = self::verify_all_tables_exist();
        
        if ($tables_exist) {
            error_log('[ADHESION] ✅ Tablas creadas correctamente con dbDelta');
            self::insert_initial_data();
            return true;
        }
        
        // PASO 3: Si dbDelta falló, intentar con SQL directo
        error_log('[ADHESION] ⚠️ dbDelta falló, intentando SQL directo...');
        $direct_sql_success = self::create_with_direct_sql($charset_collate);
        
        // PASO 4: Verificar nuevamente
        $tables_exist = self::verify_all_tables_exist();
        
        if ($tables_exist) {
            error_log('[ADHESION] ✅ Tablas creadas correctamente con SQL directo');
            self::insert_initial_data();
            return true;
        }
        
        // PASO 5: TODO FALLÓ
        error_log('[ADHESION] ❌ CRÍTICO: No se pudieron crear las tablas por ningún método');
        return false;
    }
    
    /**
     * Intentar crear tablas con dbDelta
     */
    private static function try_create_with_dbdelta($charset_collate) {
        global $wpdb;
        
        $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (!file_exists($upgrade_file)) {
            error_log('[ADHESION] ERROR: upgrade.php no encontrado');
            return false;
        }
        
        require_once($upgrade_file);
        
        if (!function_exists('dbDelta')) {
            error_log('[ADHESION] ERROR: función dbDelta no disponible');
            return false;
        }
        
        // SQLs para dbDelta (formato específico requerido)
        $sqls = array(
            "CREATE TABLE {$wpdb->prefix}adhesion_calculations (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                calculation_data longtext NOT NULL,
                material_data longtext,
                total_price decimal(10,2) NOT NULL DEFAULT 0.00,
                price_per_ton decimal(8,2) DEFAULT 0.00,
                total_tons decimal(8,2) DEFAULT 0.00,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_contracts (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                calculation_id mediumint(9) DEFAULT NULL,
                contract_number varchar(50) DEFAULT NULL,
                status varchar(50) DEFAULT 'pending',
                client_data longtext,
                docusign_envelope_id varchar(255) DEFAULT NULL,
                signed_document_url varchar(500) DEFAULT NULL,
                payment_status varchar(50) DEFAULT 'pending',
                payment_amount decimal(10,2) DEFAULT 0.00,
                payment_reference varchar(100) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                signed_at datetime DEFAULT NULL,
                payment_completed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status),
                UNIQUE KEY contract_number (contract_number)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_documents (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                document_type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                header_content longtext,
                body_content longtext,
                footer_content longtext,
                variables_list longtext,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY document_type (document_type)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_settings (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                setting_key varchar(100) NOT NULL,
                setting_value longtext,
                setting_type varchar(20) DEFAULT 'string',
                is_encrypted tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}adhesion_calculator_prices (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                material_type varchar(100) NOT NULL,
                price_per_ton decimal(8,2) NOT NULL DEFAULT 0.00,
                minimum_quantity decimal(8,2) DEFAULT 0.00,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY material_type (material_type)
            ) $charset_collate;"
        );
        
        foreach ($sqls as $sql) {
            $result = dbDelta($sql);
            error_log('[ADHESION] dbDelta resultado: ' . print_r($result, true));
        }
        
        return true; // dbDelta ejecutado (aunque puede haber fallado)
    }
    
    /**
     * Crear tablas con SQL directo (fallback)
     */
    private static function create_with_direct_sql($charset_collate) {
        global $wpdb;
        
        error_log('[ADHESION] Ejecutando SQL directo...');
        
        $sqls = array(
            'adhesion_calculations' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_calculations` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) NOT NULL,
                `calculation_data` longtext NOT NULL,
                `material_data` longtext,
                `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `price_per_ton` decimal(8,2) DEFAULT 0.00,
                `total_tons` decimal(8,2) DEFAULT 0.00,
                `status` varchar(20) DEFAULT 'active',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`)
            ) {$charset_collate}",
            
            'adhesion_contracts' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_contracts` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) NOT NULL,
                `calculation_id` mediumint(9) DEFAULT NULL,
                `contract_number` varchar(50) DEFAULT NULL,
                `status` varchar(50) DEFAULT 'pending',
                `client_data` longtext,
                `docusign_envelope_id` varchar(255) DEFAULT NULL,
                `signed_document_url` varchar(500) DEFAULT NULL,
                `payment_status` varchar(50) DEFAULT 'pending',
                `payment_amount` decimal(10,2) DEFAULT 0.00,
                `payment_reference` varchar(100) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `signed_at` datetime DEFAULT NULL,
                `payment_completed_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`),
                UNIQUE KEY `contract_number` (`contract_number`)
            ) {$charset_collate}",
            
            'adhesion_documents' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_documents` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `document_type` varchar(50) NOT NULL,
                `title` varchar(255) NOT NULL,
                `header_content` longtext,
                `body_content` longtext,
                `footer_content` longtext,
                `variables_list` longtext,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `document_type` (`document_type`)
            ) {$charset_collate}",
            
            'adhesion_settings' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_settings` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(100) NOT NULL,
                `setting_value` longtext,
                `setting_type` varchar(20) DEFAULT 'string',
                `is_encrypted` tinyint(1) DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) {$charset_collate}",
            
            'adhesion_calculator_prices' => "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}adhesion_calculator_prices` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `material_type` varchar(100) NOT NULL,
                `price_per_ton` decimal(8,2) NOT NULL DEFAULT 0.00,
                `minimum_quantity` decimal(8,2) DEFAULT 0.00,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `material_type` (`material_type`)
            ) {$charset_collate}"
        );
        
        $success_count = 0;
        foreach ($sqls as $table_name => $sql) {
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                error_log("[ADHESION] ERROR SQL directo en $table_name: " . $wpdb->last_error);
            } else {
                error_log("[ADHESION] ✅ SQL directo exitoso: $table_name");
                $success_count++;
            }
        }
        
        return $success_count === count($sqls);
    }
    
    /**
     * Verificar que TODAS las tablas existen
     */
    private static function verify_all_tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'adhesion_calculations',
            $wpdb->prefix . 'adhesion_contracts',
            $wpdb->prefix . 'adhesion_documents',
            $wpdb->prefix . 'adhesion_settings',
            $wpdb->prefix . 'adhesion_calculator_prices'
        );
        
        $existing_count = 0;
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if ($exists) {
                error_log("[ADHESION] ✅ Tabla confirmada: $table");
                $existing_count++;
            } else {
                error_log("[ADHESION] ❌ Tabla NO existe: $table");
            }
        }
        
        $all_exist = ($existing_count === count($required_tables));
        error_log("[ADHESION] Verificación: $existing_count/" . count($required_tables) . " tablas existen");
        
        return $all_exist;
    }
    
    /**
     * Insertar datos iniciales
     */
    private static function insert_initial_data() {
        global $wpdb;
        
        error_log('[ADHESION] Insertando datos iniciales...');
        
        // Documento por defecto
        $existing_doc = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_documents WHERE document_type = 'contract'"
        );
        
        if ($existing_doc == 0) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'adhesion_documents',
                array(
                    'document_type' => 'contract',
                    'title' => 'Contrato de Adhesión Estándar',
                    'header_content' => '<h1>CONTRATO DE ADHESIÓN</h1><p>Fecha: [fecha]</p>',
                    'body_content' => '<h2>DATOS DEL CLIENTE</h2><p>Nombre: [nombre_completo]</p>',
                    'footer_content' => '<p>Firma: ________________________</p>',
                    'variables_list' => json_encode(['fecha', 'nombre_completo']),
                    'is_active' => 1
                )
            );
            
            if ($result) {
                error_log('[ADHESION] ✅ Documento por defecto insertado');
            } else {
                error_log('[ADHESION] ❌ Error insertando documento: ' . $wpdb->last_error);
            }
        }
        
        // Precios por defecto
        $existing_prices = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adhesion_calculator_prices"
        );
        
        if ($existing_prices == 0) {
            $prices = array(
                array('material_type' => 'Cartón', 'price_per_ton' => 150.00),
                array('material_type' => 'Papel', 'price_per_ton' => 120.00),
                array('material_type' => 'Plástico', 'price_per_ton' => 200.00),
                array('material_type' => 'Metal', 'price_per_ton' => 300.00),
                array('material_type' => 'Vidrio', 'price_per_ton' => 80.00)
            );
            
            foreach ($prices as $price) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'adhesion_calculator_prices',
                    array(
                        'material_type' => $price['material_type'],
                        'price_per_ton' => $price['price_per_ton'],
                        'is_active' => 1
                    )
                );
                
                if (!$result) {
                    error_log('[ADHESION] Error insertando precio ' . $price['material_type'] . ': ' . $wpdb->last_error);
                }
            }
            
            error_log('[ADHESION] ✅ Precios por defecto insertados');
        }
    }
    
    /**
     * Verificar requisitos mínimos del sistema
     */
    private static function check_requirements() {
        global $wp_version;
        
        if (version_compare($wp_version, '5.0', '<')) {
            throw new Exception('Este plugin requiere WordPress 5.0 o superior.');
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            throw new Exception('Este plugin requiere PHP 7.4 o superior.');
        }
        
        if (!extension_loaded('curl')) {
            throw new Exception('Este plugin requiere la extensión cURL de PHP.');
        }
    }
    
    /**
     * Crear páginas necesarias
     */
    private static function create_pages() {
        $pages = array(
            array('title' => 'Calculadora de Presupuesto', 'content' => '[adhesion_calculator]', 'slug' => 'calculadora-presupuesto'),
            array('title' => 'Mi Cuenta - Adhesión', 'content' => '[adhesion_account]', 'slug' => 'mi-cuenta'),
            array('title' => 'Registro', 'content' => '[adhesion_register]', 'slug' => 'registro'),
            array('title' => 'Proceso de Pago', 'content' => '[adhesion_payment]', 'slug' => 'proceso-pago'),
            array('title' => 'Firma de Contratos', 'content' => '[adhesion_contract_signing]', 'slug' => 'firma-contratos')
        );
        
        foreach ($pages as $page_data) {
            if (!get_page_by_path($page_data['slug'])) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $page_data['slug'],
                    'post_author' => 1
                ));
                
                if ($page_id) {
                    $existing_settings = get_option('adhesion_settings', array());
                    $existing_settings['page_' . str_replace('-', '_', $page_data['slug'])] = $page_id;
                    update_option('adhesion_settings', $existing_settings);
                }
            }
        }
    }
    
    /**
     * Configurar opciones por defecto
     */
    private static function set_default_options() {
        $default_settings = array(
            'redsys_merchant_code' => '',
            'redsys_terminal' => '001',
            'redsys_secret_key' => '',
            'redsys_environment' => 'test',
            'redsys_currency' => '978',
            'docusign_integration_key' => '',
            'docusign_secret_key' => '',
            'docusign_account_id' => '',
            'docusign_environment' => 'demo',
            'calculator_enabled' => '1',
            'auto_create_users' => '1',
            'email_notifications' => '1',
            'contract_auto_send' => '1',
            'admin_email' => get_option('admin_email'),
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_option('admin_email'),
            'plugin_version' => ADHESION_PLUGIN_VERSION
        );
        
        $existing_settings = get_option('adhesion_settings', array());
        $final_settings = array_merge($default_settings, $existing_settings);
        
        update_option('adhesion_settings', $final_settings);
    }
    
    /**
     * Crear roles y capacidades
     */
    private static function create_roles_and_capabilities() {
        if (!get_role('adhesion_client')) {
            add_role('adhesion_client', __('Cliente Adherido', 'adhesion'), array(
                'read' => true,
                'adhesion_access' => true,
                'adhesion_calculate' => true,
                'adhesion_view_account' => true
            ));
        }
        
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('adhesion_manage_all');
            $admin_role->add_cap('adhesion_manage_settings');
            $admin_role->add_cap('adhesion_manage_documents');
            $admin_role->add_cap('adhesion_view_reports');
        }
    }
    
    /**
     * Crear directorio de uploads
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $adhesion_dir = $upload_dir['basedir'] . '/adhesion/';
        
        if (!file_exists($adhesion_dir)) {
            wp_mkdir_p($adhesion_dir);
            file_put_contents($adhesion_dir . '.htaccess', "Order deny,allow\nDeny from all\n<Files ~ \"\\.(pdf|doc|docx)$\">\nAllow from all\n</Files>");
            file_put_contents($adhesion_dir . 'index.php', '<?php // Silence is golden');
        }
    }
}