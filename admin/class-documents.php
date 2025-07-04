<?php
/**
 * Clase para gestión de documentos editables
 * 
 * Esta clase maneja:
 * - Plantillas de contratos editables (header, body, footer)
 * - Variables dinámicas en documentos
 * - Generación de documentos personalizados
 * - Integración con DocuSign
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Adhesion_Documents {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Variables disponibles para documentos
     */
    private $available_variables;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Adhesion_Database();
        $this->init_hooks();
        $this->init_variables();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para gestión de documentos
        add_action('wp_ajax_adhesion_save_document', array($this, 'ajax_save_document'));
        add_action('wp_ajax_adhesion_delete_document', array($this, 'ajax_delete_document'));
        add_action('wp_ajax_adhesion_preview_document', array($this, 'ajax_preview_document'));
        add_action('wp_ajax_adhesion_duplicate_document', array($this, 'ajax_duplicate_document'));
        add_action('wp_ajax_adhesion_toggle_document_status', array($this, 'ajax_toggle_document_status'));
        
        // Shortcodes para documentos
        add_shortcode('adhesion_document', array($this, 'render_document_shortcode'));
    }
    
    /**
     * Inicializar variables disponibles
     */
    private function init_variables() {
        $this->available_variables = array(
            // Datos del cliente
            'nombre_completo' => __('Nombre completo del cliente', 'adhesion'),
            'dni_cif' => __('DNI o CIF del cliente', 'adhesion'),
            'direccion' => __('Dirección completa', 'adhesion'),
            'codigo_postal' => __('Código postal', 'adhesion'),
            'ciudad' => __('Ciudad', 'adhesion'),
            'provincia' => __('Provincia', 'adhesion'),
            'telefono' => __('Número de teléfono', 'adhesion'),
            'email' => __('Dirección de email', 'adhesion'),
            'empresa' => __('Nombre de la empresa', 'adhesion'),
            
            // Datos del contrato
            'numero_contrato' => __('Número de contrato', 'adhesion'),
            'fecha_contrato' => __('Fecha de creación del contrato', 'adhesion'),
            'fecha_firma' => __('Fecha de firma', 'adhesion'),
            'precio_total' => __('Precio total del servicio', 'adhesion'),
            'precio_tonelada' => __('Precio por tonelada', 'adhesion'),
            'cantidad_toneladas' => __('Cantidad total en toneladas', 'adhesion'),
            
            // Datos del cálculo
            'materiales_detalle' => __('Detalle de materiales calculados', 'adhesion'),
            'materiales_resumen' => __('Resumen de materiales', 'adhesion'),
            
            // Datos generales
            'fecha_hoy' => __('Fecha actual', 'adhesion'),
            'sitio_nombre' => __('Nombre del sitio web', 'adhesion'),
            'sitio_url' => __('URL del sitio web', 'adhesion'),
            'ano_actual' => __('Año actual', 'adhesion')
        );
    }
    
    /**
     * Obtener todos los documentos
     */
    public function get_documents($type = null, $active_only = false) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        if ($type) {
            $where_clauses[] = "document_type = %s";
            $params[] = $type;
        }
        
        if ($active_only) {
            $where_clauses[] = "is_active = 1";
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = "SELECT * FROM {$wpdb->prefix}adhesion_documents $where_sql ORDER BY created_at DESC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            return $wpdb->get_results($sql, ARRAY_A);
        }
    }
    
    /**
     * Obtener un documento por ID
     */
    public function get_document($document_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adhesion_documents WHERE id = %d",
            $document_id
        ), ARRAY_A);
        
        if ($result && !empty($result['variables_list'])) {
            $result['variables_list'] = json_decode($result['variables_list'], true);
        }
        
        return $result;
    }
    
    /**
     * Crear o actualizar documento
     */
    public function save_document($data, $document_id = null) {
        global $wpdb;
        
        // Sanitizar datos
        $sanitized_data = array(
            'document_type' => sanitize_text_field($data['document_type']),
            'title' => sanitize_text_field($data['title']),
            'header_content' => wp_kses_post($data['header_content']),
            'body_content' => wp_kses_post($data['body_content']),
            'footer_content' => wp_kses_post($data['footer_content']),
            'variables_list' => json_encode($this->extract_variables_from_content($data)),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        if ($document_id) {
            // Actualizar documento existente
            $result = $wpdb->update(
                $wpdb->prefix . 'adhesion_documents',
                $sanitized_data,
                array('id' => $document_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                adhesion_log("Documento $document_id actualizado", 'info');
                return $document_id;
            }
        } else {
            // Crear nuevo documento
            $result = $wpdb->insert(
                $wpdb->prefix . 'adhesion_documents',
                $sanitized_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result !== false) {
                $document_id = $wpdb->insert_id;
                adhesion_log("Documento $document_id creado", 'info');
                return $document_id;
            }
        }
        
        return false;
    }
    
    /**
     * Extraer variables del contenido
     */
    private function extract_variables_from_content($data) {
        $content = $data['header_content'] . ' ' . $data['body_content'] . ' ' . $data['footer_content'];
        
        // Buscar variables con formato [variable]
        preg_match_all('/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/', $content, $matches);
        
        return array_unique($matches[1]);
    }
    
    /**
     * Eliminar documento
     */
    public function delete_document($document_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'adhesion_documents',
            array('id' => $document_id),
            array('%d')
        );
        
        if ($result !== false) {
            adhesion_log("Documento $document_id eliminado", 'info');
        }
        
        return $result !== false;
    }
    
    /**
     * Cambiar estado activo/inactivo
     */
    public function toggle_document_status($document_id) {
        global $wpdb;
        
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$wpdb->prefix}adhesion_documents WHERE id = %d",
            $document_id
        ));
        
        $new_status = $current_status ? 0 : 1;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'adhesion_documents',
            array('is_active' => $new_status),
            array('id' => $document_id),
            array('%d'),
            array('%d')
        );
        
        return $result !== false ? $new_status : false;
    }
    
    /**
     * Duplicar documento
     */
    public function duplicate_document($document_id) {
        $original = $this->get_document($document_id);
        
        if (!$original) {
            return false;
        }
        
        // Crear copia con nuevo título
        $copy_data = $original;
        unset($copy_data['id']);
        $copy_data['title'] = $original['title'] . ' (Copia)';
        $copy_data['is_active'] = 0; // Las copias empiezan inactivas
        
        return $this->save_document($copy_data);
    }
    
    /**
     * Generar documento personalizado
     */
    public function generate_document($document_id, $variables_data) {
        $document = $this->get_document($document_id);
        
        if (!$document) {
            return false;
        }
        
        // Combinar todas las secciones
        $full_content = $document['header_content'] . "\n\n" . $document['body_content'] . "\n\n" . $document['footer_content'];
        
        // Reemplazar variables
        $processed_content = $this->replace_variables($full_content, $variables_data);
        
        return array(
            'title' => $document['title'],
            'content' => $processed_content,
            'header' => $this->replace_variables($document['header_content'], $variables_data),
            'body' => $this->replace_variables($document['body_content'], $variables_data),
            'footer' => $this->replace_variables($document['footer_content'], $variables_data)
        );
    }
    
    /**
     * Reemplazar variables en contenido
     */
    private function replace_variables($content, $variables_data) {
        // Agregar variables del sistema
        $system_variables = array(
            'fecha_hoy' => date_i18n('d/m/Y'),
            'sitio_nombre' => get_bloginfo('name'),
            'sitio_url' => get_home_url(),
            'ano_actual' => date('Y')
        );
        
        $all_variables = array_merge($system_variables, $variables_data);
        
        // Reemplazar cada variable
        foreach ($all_variables as $key => $value) {
            // Formatear valores especiales
            if (is_numeric($value) && in_array($key, array('precio_total', 'precio_tonelada'))) {
                $value = adhesion_format_price($value);
            } elseif (is_numeric($value) && $key === 'cantidad_toneladas') {
                $value = adhesion_format_tons($value);
            } elseif (strpos($key, 'fecha_') === 0 && $value) {
                $value = adhesion_format_date($value, 'd/m/Y');
            }
            
            $content = str_replace('[' . $key . ']', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Generar variables desde un contrato
     */
    public function get_variables_from_contract($contract_id) {
        $contract = $this->db->get_contract($contract_id);
        
        if (!$contract) {
            return array();
        }
        
        $variables = array();
        
        // Datos del cliente
        if (!empty($contract['client_data'])) {
            foreach ($contract['client_data'] as $key => $value) {
                $variables[$key] = $value;
            }
        }
        
        // Datos del contrato
        $variables['numero_contrato'] = $contract['contract_number'];
        $variables['fecha_contrato'] = $contract['created_at'];
        $variables['fecha_firma'] = $contract['signed_at'];
        
        // Datos financieros
        if ($contract['payment_amount']) {
            $variables['precio_total'] = $contract['payment_amount'];
        } elseif ($contract['total_price']) {
            $variables['precio_total'] = $contract['total_price'];
        }
        
        // Datos del cálculo si existe
        if ($contract['calculation_data']) {
            $calc_data = $contract['calculation_data'];
            
            if (isset($calc_data['materials'])) {
                $variables['materiales_detalle'] = $this->format_materials_detail($calc_data['materials']);
                $variables['materiales_resumen'] = $this->format_materials_summary($calc_data['materials']);
            }
        }
        
        // Datos del cálculo base
        if (!empty($contract['total_tons'])) {
            $variables['cantidad_toneladas'] = $contract['total_tons'];
        }
        
        if (!empty($contract['price_per_ton'])) {
            $variables['precio_tonelada'] = $contract['price_per_ton'];
        }
        
        return $variables;
    }
    
    /**
     * Formatear detalle de materiales
     */
    private function format_materials_detail($materials) {
        if (empty($materials)) {
            return '';
        }
        
        $details = array();
        foreach ($materials as $material) {
            $detail = $material['type'] . ': ' . adhesion_format_tons($material['quantity']);
            if (isset($material['price_per_ton'])) {
                $detail .= ' (' . adhesion_format_price($material['price_per_ton']) . '/t)';
            }
            if (isset($material['total'])) {
                $detail .= ' = ' . adhesion_format_price($material['total']);
            }
            $details[] = $detail;
        }
        
        return implode("\n", $details);
    }
    
    /**
     * Formatear resumen de materiales
     */
    private function format_materials_summary($materials) {
        if (empty($materials)) {
            return '';
        }
        
        $types = array();
        $total_quantity = 0;
        
        foreach ($materials as $material) {
            $types[] = $material['type'];
            $total_quantity += $material['quantity'];
        }
        
        return implode(', ', $types) . ' (' . adhesion_format_tons($total_quantity) . ' total)';
    }
    
    /**
     * Obtener variables disponibles
     */
    public function get_available_variables() {
        return $this->available_variables;
    }
    
    /**
     * AJAX: Guardar documento
     */
    public function ajax_save_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = !empty($_POST['document_id']) ? intval($_POST['document_id']) : null;
            
            $data = array(
                'document_type' => sanitize_text_field($_POST['document_type']),
                'title' => sanitize_text_field($_POST['title']),
                'header_content' => wp_kses_post($_POST['header_content']),
                'body_content' => wp_kses_post($_POST['body_content']),
                'footer_content' => wp_kses_post($_POST['footer_content']),
                'is_active' => isset($_POST['is_active'])
            );
            
            // Validar datos obligatorios
            if (empty($data['title']) || empty($data['document_type'])) {
                throw new Exception(__('El título y tipo de documento son obligatorios.', 'adhesion'));
            }
            
            $saved_id = $this->save_document($data, $document_id);
            
            if (!$saved_id) {
                throw new Exception(__('Error al guardar el documento.', 'adhesion'));
            }
            
            // Obtener variables extraídas
            $variables = $this->extract_variables_from_content($data);
            
            wp_send_json_success(array(
                'message' => $document_id ? __('Documento actualizado correctamente.', 'adhesion') : __('Documento creado correctamente.', 'adhesion'),
                'document_id' => $saved_id,
                'variables_found' => $variables
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Eliminar documento
     */
    public function ajax_delete_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            
            if (!$this->delete_document($document_id)) {
                throw new Exception(__('Error al eliminar el documento.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Documento eliminado correctamente.', 'adhesion')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Vista previa de documento
     */
    public function ajax_preview_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            $contract_id = intval($_POST['contract_id'] ?? 0);
            
            // Obtener datos de ejemplo o del contrato
            if ($contract_id) {
                $variables = $this->get_variables_from_contract($contract_id);
            } else {
                // Datos de ejemplo
                $variables = array(
                    'nombre_completo' => 'Juan Pérez García',
                    'dni_cif' => '12345678Z',
                    'direccion' => 'Calle Ejemplo, 123, 1ºA',
                    'codigo_postal' => '28001',
                    'ciudad' => 'Madrid',
                    'provincia' => 'Madrid',
                    'telefono' => '666 777 888',
                    'email' => 'juan.perez@email.com',
                    'empresa' => 'Empresa Ejemplo S.L.',
                    'numero_contrato' => 'ADH202412001',
                    'precio_total' => 1500.00,
                    'precio_tonelada' => 150.00,
                    'cantidad_toneladas' => 10.0,
                    'materiales_resumen' => 'Cartón, Papel (10.0 t total)'
                );
            }
            
            $generated = $this->generate_document($document_id, $variables);
            
            if (!$generated) {
                throw new Exception(__('Error al generar la vista previa.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'content' => $generated['content'],
                'title' => $generated['title']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Duplicar documento
     */
    public function ajax_duplicate_document() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            
            $new_id = $this->duplicate_document($document_id);
            
            if (!$new_id) {
                throw new Exception(__('Error al duplicar el documento.', 'adhesion'));
            }
            
            wp_send_json_success(array(
                'message' => __('Documento duplicado correctamente.', 'adhesion'),
                'new_document_id' => $new_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Cambiar estado activo/inactivo
     */
    public function ajax_toggle_document_status() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('No tienes permisos suficientes.', 'adhesion'));
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'adhesion_admin_nonce')) {
                throw new Exception(__('Error de seguridad.', 'adhesion'));
            }
            
            $document_id = intval($_POST['document_id']);
            
            $new_status = $this->toggle_document_status($document_id);
            
            if ($new_status === false) {
                throw new Exception(__('Error al cambiar el estado del documento.', 'adhesion'));
            }
            
            $status_text = $new_status ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Documento marcado como %s.', 'adhesion'), strtolower($status_text)),
                'new_status' => $new_status,
                'status_text' => $status_text
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Shortcode para mostrar documento
     */
    public function render_document_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'contract_id' => 0,
            'type' => 'contract'
        ), $atts);
        
        $document_id = intval($atts['id']);
        $contract_id = intval($atts['contract_id']);
        
        if (!$document_id) {
            return '<p>' . __('ID de documento inválido.', 'adhesion') . '</p>';
        }
        
        // Obtener variables del contrato si se proporciona
        $variables = array();
        if ($contract_id) {
            $variables = $this->get_variables_from_contract($contract_id);
        }
        
        $generated = $this->generate_document($document_id, $variables);
        
        if (!$generated) {
            return '<p>' . __('Error al generar el documento.', 'adhesion') . '</p>';
        }
        
        return '<div class="adhesion-document-content">' . $generated['content'] . '</div>';
    }
}