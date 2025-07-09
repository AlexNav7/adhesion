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
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para gestión de precios
        add_action('wp_ajax_adhesion_add_ubica_price', array($this, 'ajax_add_ubica_price'));
        add_action('wp_ajax_adhesion_add_reinicia_price', array($this, 'ajax_add_reinicia_price'));
        add_action('wp_ajax_adhesion_update_price', array($this, 'ajax_update_price'));
        add_action('wp_ajax_adhesion_delete_price', array($this, 'ajax_delete_price'));
        add_action('wp_ajax_adhesion_toggle_price_status', array($this, 'ajax_toggle_price_status'));
        add_action('wp_ajax_adhesion_sort_prices', array($this, 'ajax_sort_prices'));
        add_action('wp_ajax_adhesion_get_price_data', array($this, 'ajax_get_price_data'));
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
            $prices_data = $this->db->get_ubica_prices();
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
    
    /**
     * AJAX: Añadir precio UBICA
     */
    public function ajax_add_ubica_price() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $material_name = sanitize_text_field($_POST['material_name']);
        $price_domestic = floatval($_POST['price_domestic']);
        $price_commercial = floatval($_POST['price_commercial']);
        $price_industrial = floatval($_POST['price_industrial']);
        
        $result = $this->ubica_repository->add_price($material_name, $price_domestic, $price_commercial, $price_industrial);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Material añadido correctamente', 'adhesion'),
                'data' => $this->ubica_repository->get_all_prices()
            ));
        } else {
            wp_send_json_error(__('Error al añadir el material', 'adhesion'));
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
    
    // ==========================================
    // OPERACIONES COMUNES
    // ==========================================
    
    /**
     * AJAX: Obtener datos de un precio para editar
     */
    public function ajax_get_price_data() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $price_id = intval($_POST['price_id']);
        $price_type = sanitize_text_field($_POST['price_type']);
        
        if ($price_type === 'ubica') {
            $data = $this->ubica_repository->get_price_by_id($price_id);
        } else {
            wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
            return;
        }
        
        if ($data) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(__('Precio no encontrado', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Actualizar precio
     */
    public function ajax_update_price() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $price_id = intval($_POST['price_id']);
        $price_type = sanitize_text_field($_POST['price_type']);
        
        if ($price_type === 'ubica') {
            $material_name = sanitize_text_field($_POST['material_name']);
            $price_domestic = floatval($_POST['price_domestic']);
            $price_commercial = floatval($_POST['price_commercial']);
            $price_industrial = floatval($_POST['price_industrial']);
            
            $result = $this->ubica_repository->update_price($price_id, $material_name, $price_domestic, $price_commercial, $price_industrial);
        } else {
            wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
            return;
        }
        
        if ($result) {
            wp_send_json_success(__('Precio actualizado correctamente', 'adhesion'));
        } else {
            wp_send_json_error(__('Error al actualizar el precio', 'adhesion'));
        }
    }

    /**
     * Actualizar precio UBICA
     */
    private function update_ubica_price($price_id) {
        $material_name = sanitize_text_field($_POST['material_name']);
        $price_domestic = floatval($_POST['price_domestic']);
        $price_commercial = floatval($_POST['price_commercial']);
        $price_industrial = floatval($_POST['price_industrial']);
        
        return $this->db->update_ubica_price($price_id, $material_name, $price_domestic, $price_commercial, $price_industrial);
    }
    
    /**
     * Actualizar precio REINICIA
     */
    private function update_reinicia_price($price_id) {
        $category_name = sanitize_text_field($_POST['category_name']);
        $price_kg = floatval($_POST['price_kg']);
        $price_units = floatval($_POST['price_units']);
        $allows_punctual_import = isset($_POST['allows_punctual_import']) ? 1 : 0;
        
        return $this->db->update_reinicia_price($price_id, $category_name, $price_kg, $price_units, $allows_punctual_import);
    }
    
    /**
     * AJAX: Eliminar precio
     */
    public function ajax_delete_price() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $price_id = intval($_POST['price_id']);
        $price_type = sanitize_text_field($_POST['price_type']);
        
        if ($price_type === 'ubica') {
            $result = $this->ubica_repository->delete_ubica_price($price_id);
        } else {
            $result = $this->ubica_repository->delete_reinicia_price($price_id);
        }
        
        if ($result) {
            wp_send_json_success(__('Precio eliminado correctamente', 'adhesion'));
        } else {
            wp_send_json_error(__('Error al eliminar el precio', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Cambiar estado activo/inactivo
     */
    public function ajax_toggle_price_status() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $price_id = intval($_POST['price_id']);
        $price_type = sanitize_text_field($_POST['price_type']);
        
        if ($price_type === 'ubica') {
            $result = $this->ubica_repository->toggle_ubica_price_status($price_id);
        } else {
            $result = $this->ubica_repository->toggle_reinicia_price_status($price_id);
        }
        
        if ($result) {
            wp_send_json_success(__('Estado cambiado correctamente', 'adhesion'));
        } else {
            wp_send_json_error(__('Error al cambiar el estado', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Ordenar precios (drag & drop)
     */
    public function ajax_sort_prices() {
        check_ajax_referer('adhesion_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
        }
        
        $price_ids = array_map('intval', $_POST['price_ids']);
        $price_type = sanitize_text_field($_POST['price_type']);
        
        if ($price_type === 'ubica') {
            $result = $this->ubica_repository->update_ubica_prices_order($price_ids);
        } else {
            $result = $this->ubica_repository->update_reinicia_prices_order($price_ids);
        }
        
        if ($result) {
            wp_send_json_success(__('Orden actualizado correctamente', 'adhesion'));
        } else {
            wp_send_json_error(__('Error al actualizar el orden', 'adhesion'));
        }
    }
}