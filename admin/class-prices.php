<?php
/**
 * Clase para gestión de precios del admin
 * 
 * Esta clase maneja toda la funcionalidad de precios:
 * - UBICA: Material + Tipo × Toneladas
 * - REINICIA: Categorías + Medida (Kg/Unidades)
 * - CRUD completo de precios
 * - Ordenación drag & drop
 * - Activación/desactivación
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Prices {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Cargar el repository
        require_once ADHESION_PLUGIN_PATH . 'includes/repositories/class-base-repository.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/repositories/class-ubica-prices-repository.php';
        $this->ubica_repository = new Adhesion_Ubica_Prices_Repository();
    }
    
    
    /**
     * Mostrar página principal de precios
     */
    public function display_page() {
        // Determinar qué pestaña mostrar
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'ubica';
        
        // Procesar acciones si las hay
        $this->process_form_actions();
        
        // Obtener datos según la pestaña activa
        if ($active_tab === 'ubica') {
            $prices_data = $this->ubica_repository->get_all_prices();
        } else {
            $prices_data = array(); // Por ahora vacío hasta que creemos REINICIA
        }
        
        // Cargar la vista
        include ADHESION_PLUGIN_PATH . 'admin/partials/prices-display.php';
    }
    
    /**
     * Procesar acciones del formulario
     */
    private function process_form_actions() {
        if (!isset($_POST['action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['adhesion_prices_nonce'], 'adhesion_prices_action')) {
            wp_die(__('Error de seguridad. Inténtalo de nuevo.', 'adhesion'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'add_ubica_price':
                $this->handle_add_ubica_price();
                break;
            case 'add_reinicia_price':
                $this->handle_add_reinicia_price();
                break;
        }
    }
    
    // ==========================================
    // GESTIÓN DE PRECIOS UBICA
    // ==========================================
    
    /**
     * Añadir nuevo precio UBICA
     */
    private function handle_add_ubica_price() {
        $material_name = sanitize_text_field($_POST['material_name']);
        $price_domestic = floatval($_POST['price_domestic']);
        $price_commercial = floatval($_POST['price_commercial']);
        $price_industrial = floatval($_POST['price_industrial']);
        
        // El repository ya valida todo, solo necesitamos llamarlo
        $result = $this->ubica_repository->add_price($material_name, $price_domestic, $price_commercial, $price_industrial);
        
        if ($result) {
            add_action('admin_notices', function() use ($material_name) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('Material "%s" añadido correctamente.', 'adhesion'), esc_html($material_name)) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error al añadir el material. Puede que ya exista o los datos sean inválidos.', 'adhesion') . '</p></div>';
            });
        }
    }
    
   

    // ==========================================
    // GESTIÓN DE PRECIOS REINICIA
    // ==========================================
    
    /**
     * Añadir nuevo precio REINICIA
     */
    private function handle_add_reinicia_price() {
        $category_name = sanitize_text_field($_POST['category_name']);
        $price_kg = floatval($_POST['price_kg']);
        $price_units = floatval($_POST['price_units']);
        $allows_punctual_import = isset($_POST['allows_punctual_import']) ? 1 : 0;
        
        // Validar datos
        if (empty($category_name) || $price_kg < 0 || $price_units < 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error: Todos los campos son obligatorios y los precios deben ser positivos.', 'adhesion') . '</p></div>';
            });
            return;
        }
        
        // Verificar que no existe ya
        if ($this->db->reinicia_category_exists($category_name)) {
            add_action('admin_notices', function() use ($category_name) {
                echo '<div class="notice notice-error"><p>' . sprintf(__('Error: La categoría "%s" ya existe.', 'adhesion'), esc_html($category_name)) . '</p></div>';
            });
            return;
        }
        
        // Añadir a la base de datos
        $result = $this->db->add_reinicia_price($category_name, $price_kg, $price_units, $allows_punctual_import);
        
        if ($result) {
            add_action('admin_notices', function() use ($category_name) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('Categoría "%s" añadida correctamente.', 'adhesion'), esc_html($category_name)) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error al añadir la categoría. Inténtalo de nuevo.', 'adhesion') . '</p></div>';
            });
        }
    }
    
    /**
     * AJAX: Añadir precio REINICIA
     */
    public function ajax_add_reinicia_price() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $category_name = sanitize_text_field($_POST['category_name']);
        $price_kg = floatval($_POST['price_kg']);
        $price_units = floatval($_POST['price_units']);
        $allows_punctual_import = isset($_POST['allows_punctual_import']) ? 1 : 0;
        
        // Validar
        if (empty($category_name) || $price_kg < 0 || $price_units < 0) {
            wp_send_json_error(__('Datos inválidos', 'adhesion'));
        }
        
        if ($this->db->reinicia_category_exists($category_name)) {
            wp_send_json_error(__('La categoría ya existe', 'adhesion'));
        }
        
        $result = $this->db->add_reinicia_price($category_name, $price_kg, $price_units, $allows_punctual_import);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Categoría añadida correctamente', 'adhesion'),
                'data' => $this->db->get_reinicia_prices()
            ));
        } else {
            wp_send_json_error(__('Error al añadir la categoría', 'adhesion'));
        }
    }
    
    
    

}