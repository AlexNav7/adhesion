<?php
/**
 * Handler AJAX para cálculos de la calculadora UBICA
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Calculations_Ajax_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_save_ubica_calculation', array($this, 'save_ubica_calculation'));
        add_action('wp_ajax_nopriv_save_ubica_calculation', array($this, 'save_ubica_calculation'));
    }
    
    /**
     * Guardar cálculo UBICA
     */
    public function save_ubica_calculation() {
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
        
        // Obtener datos del cálculo
        $calculation_data = json_decode(stripslashes($_POST['calculation_data']), true);
        
        if (!$calculation_data) {
            wp_send_json_error('Datos de cálculo inválidos');
            return;
        }
        
        // Preparar datos para guardar
        $save_data = array(
            'user_id' => get_current_user_id(),
            'calculation_data' => json_encode($calculation_data),
            'total_price' => $calculation_data['grand_total'] ?? 0,
            'status' => 'active'
        );
        
        // Guardar en la base de datos
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'adhesion_calculations',
            $save_data,
            array('%d', '%s', '%f', '%s')
        );
        
        if ($result === false) {
            error_log('[ADHESION] Error guardando cálculo: ' . $wpdb->last_error);
            wp_send_json_error('Error al guardar el cálculo');
            return;
        }
        
        $calculation_id = $wpdb->insert_id;
        
        // Log del cálculo guardado
        error_log('[ADHESION] Cálculo guardado ID: ' . $calculation_id . ' - Usuario: ' . get_current_user_id());
        
        wp_send_json_success(array(
            'message' => 'Cálculo guardado correctamente',
            'calculation_id' => $calculation_id
        ));
    }
}

// Inicializar el handler
new Adhesion_Calculations_Ajax_Handler(); 