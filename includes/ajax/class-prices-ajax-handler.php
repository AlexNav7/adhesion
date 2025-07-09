<?php
/**
 * Manejador AJAX específico para gestión de precios
 * 
 * Esta clase maneja todas las peticiones AJAX relacionadas con:
 * - UBICA: Material + Tipo × Toneladas
 * - REINICIA: Categorías + Medida (Kg/Unidades) [futuro]
 * - CRUD completo de precios
 * - Ordenación y estado de precios
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Prices_Ajax_Handler {
    
    /**
     * Repository para precios UBICA
     */
    private $ubica_repository;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_repositories();
        $this->init_hooks();
        
        adhesion_debug_log('Prices AJAX Handler inicializado correctamente', 'PRICES_AJAX');
    }
    
    /**
     * Inicializar repositories
     */
    private function init_repositories() {
        require_once ADHESION_PLUGIN_PATH . 'includes/repositories/class-base-repository.php';
        require_once ADHESION_PLUGIN_PATH . 'includes/repositories/class-ubica-prices-repository.php';
        $this->ubica_repository = new Adhesion_Ubica_Prices_Repository();
    }
    
    /**
     * Inicializar hooks AJAX
     */
    private function init_hooks() {
        // AJAX para gestión de precios (solo admin)
        add_action('wp_ajax_adhesion_get_price_data', array($this, 'handle_get_price_data'));
        add_action('wp_ajax_adhesion_update_price', array($this, 'handle_update_price'));
        add_action('wp_ajax_adhesion_delete_price', array($this, 'handle_delete_price'));
        add_action('wp_ajax_adhesion_toggle_price_status', array($this, 'handle_toggle_price_status'));
        add_action('wp_ajax_adhesion_add_ubica_price', array($this, 'handle_add_ubica_price'));
        add_action('wp_ajax_adhesion_sort_prices', array($this, 'handle_sort_prices'));
        
        adhesion_debug_log('Hooks AJAX para precios registrados', 'PRICES_AJAX');
    }
    
    // ==========================================
    // MÉTODOS AJAX PRINCIPALES
    // ==========================================
    
    /**
     * AJAX: Obtener datos de un precio para editar
     */
    public function handle_get_price_data() {
        adhesion_debug_log('=== handle_get_price_data ejecutado ===', 'PRICES_AJAX');
        
        try {
            check_ajax_referer('adhesion_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                adhesion_error_log('Usuario sin permisos intentó acceder a datos de precio', 'PRICES_AJAX');
                wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
            }
            
            $price_id = intval($_POST['price_id']);
            $price_type = sanitize_text_field($_POST['price_type']);
            
            adhesion_info_log("Obteniendo precio ID: {$price_id}, Tipo: {$price_type}", 'PRICES_AJAX');
            
            if ($price_type === 'ubica') {
                $data = $this->ubica_repository->get_by_id($price_id);
                adhesion_debug_log('Datos obtenidos del repository UBICA: ' . print_r($data, true), 'PRICES_AJAX');
            } else {
                adhesion_error_log("Tipo de precio no soportado: {$price_type}", 'PRICES_AJAX');
                wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
                return;
            }
            
            if ($data) {
                adhesion_debug_log('SUCCESS: Enviando datos al frontend', 'PRICES_AJAX');
                wp_send_json_success($data);
            } else {
                adhesion_error_log("Precio no encontrado para ID: {$price_id}", 'PRICES_AJAX');
                wp_send_json_error(__('Precio no encontrado', 'adhesion'));
            }
            
        } catch (Exception $e) {
            adhesion_error_log('Error en handle_get_price_data: ' . $e->getMessage(), 'PRICES_AJAX');
            wp_send_json_error(__('Error interno del servidor', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Actualizar precio existente
     */
    public function handle_update_price() {
        adhesion_debug_log('=== handle_update_price ejecutado ===', 'PRICES_AJAX');
        
        try {
            check_ajax_referer('adhesion_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
            }
            
            $price_id = intval($_POST['price_id']);
            $price_type = sanitize_text_field($_POST['price_type']);
            
            adhesion_info_log("Actualizando precio ID: {$price_id}, Tipo: {$price_type}", 'PRICES_AJAX');
            
            if ($price_type === 'ubica') {
                $material_name = sanitize_text_field($_POST['material_name']);
                $price_domestic = floatval($_POST['price_domestic']);
                $price_commercial = floatval($_POST['price_commercial']);
                $price_industrial = floatval($_POST['price_industrial']);
                
                $result = $this->ubica_repository->update_price(
                    $price_id, 
                    $material_name, 
                    $price_domestic, 
                    $price_commercial, 
                    $price_industrial
                );
                
                adhesion_debug_log("Resultado actualización UBICA: " . ($result ? 'SUCCESS' : 'FAILED'), 'PRICES_AJAX');
                
            } else {
                wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
                return;
            }
            
            if ($result) {
                adhesion_info_log("Precio ID {$price_id} actualizado correctamente", 'PRICES_AJAX');
                wp_send_json_success(__('Precio actualizado correctamente', 'adhesion'));
            } else {
                adhesion_error_log("Error al actualizar precio ID {$price_id}", 'PRICES_AJAX');
                wp_send_json_error(__('Error al actualizar el precio', 'adhesion'));
            }
            
        } catch (Exception $e) {
            adhesion_error_log('Error en handle_update_price: ' . $e->getMessage(), 'PRICES_AJAX');
            wp_send_json_error(__('Error interno del servidor', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Eliminar precio
     */
    public function handle_delete_price() {
        adhesion_debug_log('=== handle_delete_price ejecutado ===', 'PRICES_AJAX');
        
        try {
            check_ajax_referer('adhesion_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
            }
            
            $price_id = intval($_POST['price_id']);
            $price_type = sanitize_text_field($_POST['price_type']);
            
            adhesion_info_log("Eliminando precio ID: {$price_id}, Tipo: {$price_type}", 'PRICES_AJAX');
            
            if ($price_type === 'ubica') {
                $result = $this->ubica_repository->delete($price_id);
            } else {
                wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
                return;
            }
            
            if ($result) {
                adhesion_info_log("Precio ID {$price_id} eliminado correctamente", 'PRICES_AJAX');
                wp_send_json_success(__('Precio eliminado correctamente', 'adhesion'));
            } else {
                adhesion_error_log("Error al eliminar precio ID {$price_id}", 'PRICES_AJAX');
                wp_send_json_error(__('Error al eliminar el precio', 'adhesion'));
            }
            
        } catch (Exception $e) {
            adhesion_error_log('Error en handle_delete_price: ' . $e->getMessage(), 'PRICES_AJAX');
            wp_send_json_error(__('Error interno del servidor', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Cambiar estado activo/inactivo
     */
    public function handle_toggle_price_status() {
        adhesion_debug_log('=== handle_toggle_price_status ejecutado ===', 'PRICES_AJAX');
        
        try {
            check_ajax_referer('adhesion_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
            }
            
            $price_id = intval($_POST['price_id']);
            $price_type = sanitize_text_field($_POST['price_type']);
            
            adhesion_info_log("Cambiando estado precio ID: {$price_id}, Tipo: {$price_type}", 'PRICES_AJAX');
            
            if ($price_type === 'ubica') {
                $result = $this->ubica_repository->toggle_status($price_id);
            } else {
                wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
                return;
            }
            
            if ($result) {
                adhesion_info_log("Estado de precio ID {$price_id} cambiado correctamente", 'PRICES_AJAX');
                wp_send_json_success(__('Estado cambiado correctamente', 'adhesion'));
            } else {
                adhesion_error_log("Error al cambiar estado de precio ID {$price_id}", 'PRICES_AJAX');
                wp_send_json_error(__('Error al cambiar el estado', 'adhesion'));
            }
            
        } catch (Exception $e) {
            adhesion_error_log('Error en handle_toggle_price_status: ' . $e->getMessage(), 'PRICES_AJAX');
            wp_send_json_error(__('Error interno del servidor', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Añadir nuevo precio UBICA
     */
    public function handle_add_ubica_price() {
        adhesion_debug_log('=== handle_add_ubica_price ejecutado ===', 'PRICES_AJAX');
        
        try {
            check_ajax_referer('adhesion_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
            }
            
            $material_name = sanitize_text_field($_POST['material_name']);
            $price_domestic = floatval($_POST['price_domestic']);
            $price_commercial = floatval($_POST['price_commercial']);
            $price_industrial = floatval($_POST['price_industrial']);
            
            adhesion_info_log("Añadiendo nuevo material UBICA: {$material_name}", 'PRICES_AJAX');
            
            $result = $this->ubica_repository->add_price(
                $material_name, 
                $price_domestic, 
                $price_commercial, 
                $price_industrial
            );
            
            if ($result) {
                adhesion_info_log("Material UBICA '{$material_name}' añadido con ID: {$result}", 'PRICES_AJAX');
                wp_send_json_success(array(
                    'message' => __('Material añadido correctamente', 'adhesion'),
                    'id' => $result,
                    'data' => $this->ubica_repository->get_all_prices()
                ));
            } else {
                adhesion_error_log("Error al añadir material UBICA: {$material_name}", 'PRICES_AJAX');
                wp_send_json_error(__('Error al añadir el material', 'adhesion'));
            }
            
        } catch (Exception $e) {
            adhesion_error_log('Error en handle_add_ubica_price: ' . $e->getMessage(), 'PRICES_AJAX');
            wp_send_json_error(__('Error interno del servidor', 'adhesion'));
        }
    }
    
    /**
     * AJAX: Ordenar precios (drag & drop)
     */
    public function handle_sort_prices() {
        adhesion_debug_log('=== handle_sort_prices ejecutado ===', 'PRICES_AJAX');
        
        try {
            check_ajax_referer('adhesion_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'adhesion'));
            }
            
            $price_ids = array_map('intval', $_POST['price_ids']);
            $price_type = sanitize_text_field($_POST['price_type']);
            
            adhesion_info_log("Reordenando precios tipo: {$price_type}, IDs: " . implode(',', $price_ids), 'PRICES_AJAX');
            
            if ($price_type === 'ubica') {
                $result = $this->ubica_repository->update_sort_order($price_ids);
            } else {
                wp_send_json_error(__('Tipo de precio no válido', 'adhesion'));
                return;
            }
            
            if ($result) {
                adhesion_info_log("Orden de precios actualizado correctamente", 'PRICES_AJAX');
                wp_send_json_success(__('Orden actualizado correctamente', 'adhesion'));
            } else {
                adhesion_error_log("Error al actualizar orden de precios", 'PRICES_AJAX');
                wp_send_json_error(__('Error al actualizar el orden', 'adhesion'));
            }
            
        } catch (Exception $e) {
            adhesion_error_log('Error en handle_sort_prices: ' . $e->getMessage(), 'PRICES_AJAX');
            wp_send_json_error(__('Error interno del servidor', 'adhesion'));
        }
    }
}