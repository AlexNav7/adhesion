<?php
/**
 * Vista de gestión de documentos editables
 * 
 * Esta vista maneja:
 * - Listado de documentos/plantillas
 * - Editor de documentos con 3 secciones
 * - Vista previa en tiempo real
 * - Gestión de variables dinámicas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar la acción actual
$action = sanitize_text_field($_GET['action'] ?? 'list');
$document_id = intval($_GET['document'] ?? 0);

// Instanciar clases necesarias
$documents = new Adhesion_Documents();
$db = new Adhesion_Database();

// Manejar acciones específicas
switch ($action) {
    case 'edit':
    case 'new':
        adhesion_display_document_editor($document_id, $documents);
        break;
        
    case 'preview':
        adhesion_display_document_preview($document_id, $documents);
        break;
        
    default:
        adhesion_display_documents_list($documents);
        break;
}

/**
 * Mostrar listado principal de documentos
 */
function adhesion_display_documents_list($documents) {
    $all_documents = $documents->get_documents();
    
    // Agrupar por tipo
    $documents_by_type = array();
    foreach ($all_documents as $doc) {
        $documents_by_type[$doc['document_type']][] = $doc;
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('Gestión de Documentos', 'adhesion'); ?>
            <span class="title-count"><?php echo sprintf(__('(%s plantillas)', 'adhesion'), count($all_documents)); ?></span>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-documents&action=new'); ?>" class="page-title-action">
            <?php _e('+ Nuevo Documento', 'adhesion'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion'); ?>" class="page-title-action">
            <?php _e('← Dashboard', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar notificaciones
        adhesion_display_notices();
        ?>
        
        <!-- Información sobre documentos -->
        <div class="adhesion-documents-info">
            <div class="adhesion-card">
                <div class="adhesion-card-header">
                    <h2><?php _e('Sobre los Documentos Editables', 'adhesion'); ?></h2>
                </div>
                <div class="adhesion-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-edit-page"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Editor de 3 Secciones', 'adhesion'); ?></h3>
                                <p><?php _e('Cada documento se divide en Header, Cuerpo y Footer para máxima flexibilidad.', 'adhesion'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-admin-generic"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Variables Dinámicas', 'adhesion'); ?></h3>
                                <p><?php _e('Usa [variable] para insertar datos del cliente, contrato o cálculo automáticamente.', 'adhesion'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-visibility"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Vista Previa', 'adhesion'); ?></h3>
                                <p><?php _e('Previsualiza cómo se verá el documento con datos reales antes de enviarlo.', 'adhesion'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <span class="dashicons dashicons-migrate"></span>
                            </div>
                            <div class="info-content">
                                <h3><?php _e('Integración DocuSign', 'adhesion'); ?></h3>
                                <p><?php _e('Los documentos activos se envían automáticamente para firma digital.', 'adhesion'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Listado de documentos por tipo -->
        <?php if (empty($all_documents)): ?>
        <div class="adhesion-empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <h3><?php _e('No hay documentos creados', 'adhesion'); ?></h3>
            <p><?php _e('Crea tu primera plantilla de documento para empezar a generar contratos personalizados.', 'adhesion'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=adhesion-documents&action=new'); ?>" class="button button-primary button-large">
                <?php _e('Crear Primer Documento', 'adhesion'); ?>
            </a>
        </div>
        
        <?php else: ?>
        <div class="adhesion-documents-grid">
            <?php foreach ($documents_by_type as $type => $type_documents): ?>
            <div class="documents-type-section">
                <h2 class="type-title">
                    <?php echo sprintf(__('Tipo: %s', 'adhesion'), ucfirst($type)); ?>
                    <span class="type-count">(<?php echo count($type_documents); ?>)</span>
                </h2>
                
                <div class="documents-list">
                    <?php foreach ($type_documents as $document): ?>
                    <div class="document-card <?php echo $document['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="document-header">
                            <h3 class="document-title">
                                <?php echo esc_html($document['title']); ?>
                                <?php if ($document['is_active']): ?>
                                <span class="adhesion-badge adhesion-badge-success"><?php _e('Activo', 'adhesion'); ?></span>
                                <?php else: ?>
                                <span class="adhesion-badge adhesion-badge-secondary"><?php _e('Inactivo', 'adhesion'); ?></span>
                                <?php endif; ?>
                            </h3>
                            
                            <div class="document-meta">
                                <span class="document-date">
                                    <?php echo sprintf(__('Modificado: %s', 'adhesion'), adhesion_format_date($document['updated_at'], 'd/m/Y H:i')); ?>
                                </span>
                                
                                <?php if (!empty($document['variables_list'])): ?>
                                <span class="document-variables">
                                    <?php 
                                    $variables = json_decode($document['variables_list'], true);
                                    echo sprintf(__('%d variables', 'adhesion'), count($variables));
                                    ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="document-preview">
                            <?php 
                            $preview_content = strip_tags($document['body_content']);
                            $preview_content = wp_trim_words($preview_content, 20, '...');
                            echo esc_html($preview_content);
                            ?>
                        </div>
                        
                        <div class="document-actions">
                            <a href="<?php echo admin_url('admin.php?page=adhesion-documents&action=edit&document=' . $document['id']); ?>" 
                               class="button button-primary">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Editar', 'adhesion'); ?>
                            </a>
                            
                            <button type="button" class="button" onclick="adhesionPreviewDocument(<?php echo $document['id']; ?>)">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Vista Previa', 'adhesion'); ?>
                            </button>
                            
                            <button type="button" class="button" onclick="adhesionDuplicateDocument(<?php echo $document['id']; ?>)">
                                <span class="dashicons dashicons-admin-page"></span>
                                <?php _e('Duplicar', 'adhesion'); ?>
                            </button>
                            
                            <button type="button" class="button" onclick="adhesionToggleDocumentStatus(<?php echo $document['id']; ?>, <?php echo $document['is_active'] ? '0' : '1'; ?>)">
                                <span class="dashicons dashicons-<?php echo $document['is_active'] ? 'hidden' : 'visibility'; ?>"></span>
                                <?php echo $document['is_active'] ? __('Desactivar', 'adhesion') : __('Activar', 'adhesion'); ?>
                            </button>
                            
                            <button type="button" class="button button-link-delete" onclick="adhesionDeleteDocument(<?php echo $document['id']; ?>)">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Eliminar', 'adhesion'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para vista previa -->
    <div id="preview-modal" class="adhesion-modal" style="display: none;">
        <div class="adhesion-modal-content large">
            <div class="adhesion-modal-header">
                <h2><?php _e('Vista Previa del Documento', 'adhesion'); ?></h2>
                <button type="button" class="adhesion-modal-close" onclick="adhesionClosePreviewModal()">&times;</button>
            </div>
            <div class="adhesion-modal-body">
                <div id="preview-content"></div>
            </div>
            <div class="adhesion-modal-footer">
                <button type="button" class="button" onclick="adhesionClosePreviewModal()">
                    <?php _e('Cerrar', 'adhesion'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <style>
    .adhesion-documents-info {
        margin: 20px 0 30px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .info-item {
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    
    .info-icon {
        width: 40px;
        height: 40px;
        background: #0073aa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .info-content h3 {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: 600;
        color: #1d2327;
    }
    
    .info-content p {
        margin: 0;
        font-size: 13px;
        color: #646970;
        line-height: 1.4;
    }
    
    .documents-type-section {
        margin-bottom: 40px;
    }
    
    .type-title {
        font-size: 18px;
        margin-bottom: 20px;
        color: #1d2327;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 10px;
    }
    
    .type-count {
        font-size: 14px;
        color: #646970;
        font-weight: normal;
    }
    
    .documents-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .document-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 6px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        transition: box-shadow 0.2s;
    }
    
    .document-card:hover {
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
    }
    
    .document-card.inactive {
        opacity: 0.7;
        border-style: dashed;
    }
    
    .document-header {
        margin-bottom: 15px;
    }
    
    .document-title {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1d2327;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .document-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 12px;
        color: #646970;
    }
    
    .document-preview {
        margin-bottom: 20px;
        padding: 15px;
        background: #f6f7f7;
        border-radius: 4px;
        font-size: 13px;
        color: #646970;
        line-height: 1.4;
        min-height: 60px;
    }
    
    .document-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .document-actions .button {
        font-size: 11px;
        padding: 4px 8px;
        height: auto;
        line-height: 1.3;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .adhesion-empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 6px;
        margin-top: 20px;
    }
    
    .empty-icon {
        width: 80px;
        height: 80px;
        background: #f0f0f1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 32px;
        color: #8c8f94;
    }
    
    .adhesion-empty-state h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        color: #1d2327;
    }
    
    .adhesion-empty-state p {
        margin: 0 0 25px 0;
        color: #646970;
        font-size: 16px;
    }
    
    /* Modal styles */
    .adhesion-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .adhesion-modal-content {
        background-color: #fff;
        margin: 2% auto;
        padding: 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .adhesion-modal-content.large {
        max-width: 900px;
    }
    
    .adhesion-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ccd0d4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f6f7f7;
        flex-shrink: 0;
    }
    
    .adhesion-modal-header h2 {
        margin: 0;
        font-size: 16px;
    }
    
    .adhesion-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #646970;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .adhesion-modal-close:hover {
        color: #d63638;
    }
    
    .adhesion-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }
    
    .adhesion-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ccd0d4;
        background: #f6f7f7;
        text-align: right;
        flex-shrink: 0;
    }
    
    #preview-content {
        font-family: 'Times New Roman', serif;
        line-height: 1.6;
        color: #1d2327;
    }
    
    @media (max-width: 768px) {
        .documents-list {
            grid-template-columns: 1fr;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .document-actions {
            flex-direction: column;
        }
        
        .adhesion-modal-content {
            margin: 5% auto;
            width: 95%;
        }
    }
    </style>
    
    <script>
    function adhesionPreviewDocument(documentId) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_preview_document',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                jQuery('#preview-content').html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active"></span></div>');
                jQuery('#preview-modal').show();
            },
            success: function(response) {
                if (response.success) {
                    jQuery('#preview-content').html(response.data.content);
                } else {
                    jQuery('#preview-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                }
            },
            error: function() {
                jQuery('#preview-content').html('<p style="color: #d63638;"><?php echo esc_js(__('Error de conexión', 'adhesion')); ?></p>');
            }
        });
    }
    
    function adhesionClosePreviewModal() {
        jQuery('#preview-modal').hide();
    }
    
    function adhesionDuplicateDocument(documentId) {
        if (!confirm('<?php echo esc_js(__('¿Duplicar este documento?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_duplicate_document',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
    
    function adhesionToggleDocumentStatus(documentId, newStatus) {
        const action = newStatus ? '<?php echo esc_js(__('activar', 'adhesion')); ?>' : '<?php echo esc_js(__('desactivar', 'adhesion')); ?>';
        
        if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres', 'adhesion')); ?> ' + action + ' <?php echo esc_js(__('este documento?', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_toggle_document_status',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
    
    function adhesionDeleteDocument(documentId) {
        if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres eliminar este documento? Esta acción no se puede deshacer.', 'adhesion')); ?>')) {
            return;
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_delete_document',
                document_id: documentId,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('preview-modal');
        if (event.target === modal) {
            adhesionClosePreviewModal();
        }
    }
    </script>
    <?php
}

/**
 * Mostrar editor de documentos
 */
function adhesion_display_document_editor($document_id, $documents) {
    $document = null;
    $is_new = true;
    
    if ($document_id) {
        $document = $documents->get_document($document_id);
        $is_new = false;
        
        if (!$document) {
            ?>
            <div class="wrap">
                <h1><?php _e('Documento no encontrado', 'adhesion'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('El documento solicitado no existe.', 'adhesion'); ?></p>
                </div>
                <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="button">
                    <?php _e('← Volver al listado', 'adhesion'); ?>
                </a>
            </div>
            <?php
            return;
        }
    }
    
    // Valores por defecto para nuevo documento
    if ($is_new) {
        $document = array(
            'id' => 0,
            'document_type' => 'contract',
            'title' => '',
            'header_content' => '<h1>CONTRATO DE ADHESIÓN</h1>
<p><strong>Número:</strong> [numero_contrato]</p>
<p><strong>Fecha:</strong> [fecha_contrato]</p>
<hr>',
            'body_content' => '<h2>DATOS DEL CLIENTE</h2>
<p><strong>Nombre completo:</strong> [nombre_completo]</p>
<p><strong>DNI/CIF:</strong> [dni_cif]</p>
<p><strong>Dirección:</strong> [direccion], [codigo_postal] [ciudad] ([provincia])</p>
<p><strong>Teléfono:</strong> [telefono]</p>
<p><strong>Email:</strong> [email]</p>

<h2>CONDICIONES DEL SERVICIO</h2>
<p><strong>Materiales:</strong> [materiales_resumen]</p>
<p><strong>Precio total:</strong> [precio_total]</p>
<p><strong>Precio por tonelada:</strong> [precio_tonelada]</p>',
            'footer_content' => '<hr>
<p><strong>Firma del cliente:</strong></p>
<br><br>
<p>_________________________</p>
<p>Fecha: [fecha_hoy]</p>',
            'is_active' => false
        );
    }
    
    // Obtener variables disponibles
    $available_variables = $documents->get_available_variables();
    
    ?>
    <div class="wrap">
        <h1><?php echo $is_new ? __('Nuevo Documento', 'adhesion') : sprintf(__('Editar: %s', 'adhesion'), esc_html($document['title'])); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="page-title-action">
            <?php _e('← Volver al listado', 'adhesion'); ?>
        </a>
        
        <hr class="wp-header-end">
        
        <?php adhesion_display_notices(); ?>
        
        <div class="adhesion-document-editor">
            <form id="document-form" method="post">
                <?php wp_nonce_field('adhesion_save_document', 'adhesion_document_nonce'); ?>
                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                
                <div class="editor-layout">
                    <!-- Panel izquierdo: Editor -->
                    <div class="editor-panel">
                        <!-- Configuración básica -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Configuración Básica', 'adhesion'); ?></h2>
                            </div>
                            <div class="adhesion-card-body">
                                <div class="adhesion-form-row">
                                    <label for="document-title"><?php _e('Título del documento:', 'adhesion'); ?></label>
                                    <input type="text" id="document-title" name="title" 
                                           value="<?php echo esc_attr($document['title']); ?>" 
                                           placeholder="<?php _e('Ej: Contrato de Adhesión Estándar', 'adhesion'); ?>" required>
                                </div>
                                
                                <div class="adhesion-form-row">
                                    <label for="document-type"><?php _e('Tipo de documento:', 'adhesion'); ?></label>
                                    <select id="document-type" name="document_type">
                                        <option value="contract" <?php selected($document['document_type'], 'contract'); ?>><?php _e('Contrato', 'adhesion'); ?></option>
                                        <option value="notification" <?php selected($document['document_type'], 'notification'); ?>><?php _e('Notificación', 'adhesion'); ?></option>
                                        <option value="invoice" <?php selected($document['document_type'], 'invoice'); ?>><?php _e('Factura', 'adhesion'); ?></option>
                                        <option value="other" <?php selected($document['document_type'], 'other'); ?>><?php _e('Otro', 'adhesion'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="adhesion-form-row">
                                    <label>
                                        <input type="checkbox" name="is_active" value="1" <?php checked($document['is_active'], 1); ?>>
                                        <?php _e('Documento activo (se usa en el proceso de adhesión)', 'adhesion'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Editor de Header -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Header (Encabezado)', 'adhesion'); ?></h2>
                                <p class="description"><?php _e('Contenido que aparece al inicio del documento', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <?php
                                wp_editor($document['header_content'], 'header_content', array(
                                    'textarea_name' => 'header_content',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <!-- Editor de Body -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Cuerpo Principal', 'adhesion'); ?></h2>
                                <p class="description"><?php _e('Contenido principal del documento', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <?php
                                wp_editor($document['body_content'], 'body_content', array(
                                    'textarea_name' => 'body_content',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false,
                                    'teeny' => false,
                                    'quicktags' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <!-- Editor de Footer -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h2><?php _e('Footer (Pie)', 'adhesion'); ?></h2>
                                <p class="description"><?php _e('Contenido que aparece al final del documento', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <?php
                                wp_editor($document['footer_content'], 'footer_content', array(
                                    'textarea_name' => 'footer_content',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-footer">
                                <button type="submit" class="button button-primary button-large">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php echo $is_new ? __('Crear Documento', 'adhesion') : __('Actualizar Documento', 'adhesion'); ?>
                                </button>
                                
                                <?php if (!$is_new): ?>
                                <button type="button" class="button" onclick="adhesionPreviewDocument(<?php echo $document['id']; ?>)">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Vista Previa', 'adhesion'); ?>
                                </button>
                                <?php endif; ?>
                                
                                <a href="<?php echo admin_url('admin.php?page=adhesion-documents'); ?>" class="button">
                                    <?php _e('Cancelar', 'adhesion'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panel derecho: Variables y ayuda -->
                    <div class="sidebar-panel">
                        <!-- Variables disponibles -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h3><?php _e('Variables Disponibles', 'adhesion'); ?></h3>
                                <p class="description"><?php _e('Haz clic para insertar en el editor', 'adhesion'); ?></p>
                            </div>
                            <div class="adhesion-card-body">
                                <div class="variables-grid">
                                    <?php foreach ($available_variables as $variable => $description): ?>
                                    <div class="variable-item" onclick="adhesionInsertVariable('[<?php echo $variable; ?>]')">
                                        <code>[<?php echo $variable; ?>]</code>
                                        <span class="variable-description"><?php echo esc_html($description); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ayuda y tips -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h3><?php _e('Ayuda', 'adhesion'); ?></h3>
                            </div>
                            <div class="adhesion-card-body">
                                <div class="help-section">
                                    <h4><?php _e('Cómo usar las variables:', 'adhesion'); ?></h4>
                                    <ul>
                                        <li><?php _e('Las variables se escriben entre corchetes: [variable]', 'adhesion'); ?></li>
                                        <li><?php _e('Se reemplazan automáticamente con datos reales', 'adhesion'); ?></li>
                                        <li><?php _e('Haz clic en una variable para insertarla', 'adhesion'); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="help-section">
                                    <h4><?php _e('Estructura del documento:', 'adhesion'); ?></h4>
                                    <ul>
                                        <li><strong><?php _e('Header:', 'adhesion'); ?></strong> <?php _e('Título, número de contrato, fecha', 'adhesion'); ?></li>
                                        <li><strong><?php _e('Cuerpo:', 'adhesion'); ?></strong> <?php _e('Condiciones, datos del cliente', 'adhesion'); ?></li>
                                        <li><strong><?php _e('Footer:', 'adhesion'); ?></strong> <?php _e('Firmas, fecha final', 'adhesion'); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="help-section">
                                    <h4><?php _e('Consejos:', 'adhesion'); ?></h4>
                                    <ul>
                                        <li><?php _e('Usa solo un documento activo por tipo', 'adhesion'); ?></li>
                                        <li><?php _e('Prueba siempre la vista previa antes de activar', 'adhesion'); ?></li>
                                        <li><?php _e('Guarda borradores como inactivos', 'adhesion'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Variables detectadas -->
                        <div class="adhesion-card">
                            <div class="adhesion-card-header">
                                <h3><?php _e('Variables en Uso', 'adhesion'); ?></h3>
                            </div>
                            <div class="adhesion-card-body">
                                <div id="detected-variables">
                                    <p class="no-variables"><?php _e('No hay variables detectadas aún', 'adhesion'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .adhesion-document-editor {
        margin-top: 20px;
    }
    
    .editor-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 20px;
    }
    
    .editor-panel .adhesion-card {
        margin-bottom: 20px;
    }
    
    .sidebar-panel .adhesion-card {
        margin-bottom: 15px;
    }
    
    .adhesion-form-row {
        margin-bottom: 15px;
    }
    
    .adhesion-form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .adhesion-form-row input[type="text"],
    .adhesion-form-row select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    
    .variables-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .variable-item {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .variable-item:hover {
        background-color: #f0f0f1;
        border-color: #0073aa;
    }
    
    .variable-item code {
        display: block;
        font-weight: bold;
        color: #0073aa;
        margin-bottom: 2px;
    }
    
    .variable-description {
        font-size: 11px;
        color: #646970;
        line-height: 1.3;
    }
    
    .help-section {
        margin-bottom: 20px;
    }
    
    .help-section h4 {
        margin: 0 0 8px 0;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
    }
    
    .help-section ul {
        margin: 0;
        padding-left: 20px;
    }
    
    .help-section li {
        font-size: 12px;
        color: #646970;
        margin-bottom: 4px;
        line-height: 1.4;
    }
    
    #detected-variables {
        font-size: 12px;
    }
    
    .detected-variable {
        display: inline-block;
        padding: 2px 6px;
        margin: 2px;
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        border-radius: 3px;
        color: #0073aa;
    }
    
    .no-variables {
        color: #646970;
        font-style: italic;
        text-align: center;
        margin: 10px 0;
    }
    
    @media (max-width: 1200px) {
        .editor-layout {
            grid-template-columns: 1fr;
        }
        
        .sidebar-panel {
            order: -1;
        }
        
        .variables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            max-height: none;
        }
    }
    </style>
    
    <script>
    let currentEditor = null;
    
    function adhesionInsertVariable(variable) {
        // Intentar insertar en el editor activo
        if (typeof tinymce !== 'undefined') {
            const activeEditor = tinymce.activeEditor;
            if (activeEditor && !activeEditor.isHidden()) {
                activeEditor.insertContent(variable + ' ');
                return;
            }
        }
        
        // Fallback: insertar en el textarea activo
        const textareas = document.querySelectorAll('textarea');
        let activeTextarea = null;
        
        for (let textarea of textareas) {
            if (textarea === document.activeElement) {
                activeTextarea = textarea;
                break;
            }
        }
        
        if (activeTextarea) {
            const start = activeTextarea.selectionStart;
            const end = activeTextarea.selectionEnd;
            const text = activeTextarea.value;
            
            activeTextarea.value = text.substring(0, start) + variable + ' ' + text.substring(end);
            activeTextarea.selectionStart = activeTextarea.selectionEnd = start + variable.length + 1;
            activeTextarea.focus();
        } else {
            // Último recurso: copiar al portapapeles
            navigator.clipboard.writeText(variable).then(function() {
                alert('<?php echo esc_js(__('Variable copiada al portapapeles:', 'adhesion')); ?> ' + variable);
            });
        }
        
        // Actualizar variables detectadas
        adhesionUpdateDetectedVariables();
    }
    
    function adhesionUpdateDetectedVariables() {
        let allContent = '';
        
        // Recoger contenido de todos los editores
        if (typeof tinymce !== 'undefined') {
            tinymce.editors.forEach(function(editor) {
                if (editor.getContent) {
                    allContent += ' ' + editor.getContent();
                }
            });
        }
        
        // También de los textareas
        const textareas = document.querySelectorAll('textarea[name$="_content"]');
        textareas.forEach(function(textarea) {
            allContent += ' ' + textarea.value;
        });
        
        // Extraer variables
        const variableRegex = /\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g;
        const matches = allContent.match(variableRegex);
        const uniqueVariables = [...new Set(matches || [])];
        
        // Mostrar variables detectadas
        const container = document.getElementById('detected-variables');
        if (uniqueVariables.length > 0) {
            container.innerHTML = uniqueVariables.map(variable => 
                '<span class="detected-variable">' + variable + '</span>'
            ).join('');
        } else {
            container.innerHTML = '<p class="no-variables"><?php echo esc_js(__('No hay variables detectadas aún', 'adhesion')); ?></p>';
        }
    }
    
    // Procesar formulario
    jQuery(document).ready(function($) {
        $('#document-form').on('submit', function(e) {
            e.preventDefault();
            
            // Obtener datos del formulario
            const formData = new FormData(this);
            formData.append('action', 'adhesion_save_document');
            formData.append('nonce', '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>');
            
            // Obtener contenido de los editores TinyMCE
            if (typeof tinymce !== 'undefined') {
                tinymce.editors.forEach(function(editor) {
                    if (editor.targetElm) {
                        formData.set(editor.targetElm.name, editor.getContent());
                    }
                });
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#document-form button[type="submit"]').prop('disabled', true).html('<span class="spinner is-active" style="float: none;"></span> <?php echo esc_js(__('Guardando...', 'adhesion')); ?>');
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        <?php if ($is_new): ?>
                        // Redirigir al modo edición para nuevos documentos
                        window.location.href = '<?php echo admin_url('admin.php?page=adhesion-documents&action=edit&document='); ?>' + response.data.document_id;
                        <?php else: ?>
                        // Actualizar variables detectadas
                        adhesionUpdateDetectedVariables();
                        <?php endif; ?>
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Error de conexión', 'adhesion')); ?>');
                },
                complete: function() {
                    $('#document-form button[type="submit"]').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> <?php echo esc_js($is_new ? __('Crear Documento', 'adhesion') : __('Actualizar Documento', 'adhesion')); ?>');
                }
            });
        });
        
        // Actualizar variables detectadas cuando se cambie el contenido
        $(document).on('keyup change', 'textarea[name$="_content"]', function() {
            setTimeout(adhesionUpdateDetectedVariables, 500);
        });
        
        if (typeof tinymce !== 'undefined') {
            $(document).on('tinymce-editor-init', function(event, editor) {
                editor.on('keyup change', function() {
                    setTimeout(adhesionUpdateDetectedVariables, 500);
                });
            });
        }
        
        // Actualizar al cargar
        setTimeout(adhesionUpdateDetectedVariables, 1000);
    });
    </script>
    <?php
}

/**
 * Mostrar vista previa de documento (función independiente)
 */
function adhesion_display_document_preview($document_id, $documents) {
    // Esta función se llamaría desde una URL específica para preview
    // Por ahora la vista previa se maneja vía AJAX en el modal
    wp_redirect(admin_url('admin.php?page=adhesion-documents'));
    exit;
}