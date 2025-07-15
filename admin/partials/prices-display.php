<?php
/**
 * Vista de gestión de precios del admin
 * 
 * @package Adhesion
 * @subpackage Admin/Partials
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Asegurar que tenemos los datos necesarios
$active_tab = isset($active_tab) ? $active_tab : 'ubica';
$prices_data = isset($prices_data) ? $prices_data : array();
?>

<div class="wrap">
    <h1><?php _e('Gestión de Precios', 'adhesion'); ?></h1>
    
    <!-- Pestañas de navegación -->
    <nav class="nav-tab-wrapper">
        <a href="?page=adhesion-prices&tab=ubica" 
           class="nav-tab <?php echo $active_tab === 'ubica' ? 'nav-tab-active' : ''; ?>">
            <?php _e('UBICA', 'adhesion'); ?>
        </a>
        <a href="?page=adhesion-prices&tab=reinicia" 
           class="nav-tab <?php echo $active_tab === 'reinicia' ? 'nav-tab-active' : ''; ?>">
            <?php _e('REINICIA', 'adhesion'); ?>
        </a>
    </nav>
    
    <!-- Contenido de la pestaña activa -->
    <div class="tab-content">
        <?php if ($active_tab === 'ubica'): ?>
            <!-- Pestaña UBICA -->
            <div class="ubica-prices-section">
                <h2><?php _e('Precios UBICA - Material + Tipo × Toneladas', 'adhesion'); ?></h2>
                
                <!-- Formulario para añadir nuevo material UBICA -->
                <div class="card" style="width: 100%; max-width: none;">
                    <h3><?php _e('Añadir Nuevo Material UBICA', 'adhesion'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('adhesion_prices_action', 'adhesion_prices_nonce'); ?>
                        <input type="hidden" name="action" value="add_ubica_price">
                        
                        <div class="adhesion-form-grid">
                            <div class="form-field">
                                <label for="material_name"><?php _e('Nombre del Material', 'adhesion'); ?></label>
                                <input type="text" 
                                       id="material_name" 
                                       name="material_name" 
                                       class="regular-text" 
                                       required
                                       placeholder="<?php _e('Ej: Vidrio, Plástico, Metal...', 'adhesion'); ?>">
                            </div>
                            <div class="form-field">
                                <label for="price_domestic"><?php _e('Precio Doméstico (€/tonelada)', 'adhesion'); ?></label>
                                <input type="number" 
                                       id="price_domestic" 
                                       name="price_domestic" 
                                       step="0.01" 
                                       min="0" 
                                       class="regular-text" 
                                       required
                                       placeholder="0.00">
                            </div>
                            <div class="form-field">
                                <label for="price_commercial"><?php _e('Precio Comercial (€/tonelada)', 'adhesion'); ?></label>
                                <input type="number" 
                                       id="price_commercial" 
                                       name="price_commercial" 
                                       step="0.01" 
                                       min="0" 
                                       class="regular-text" 
                                       required
                                       placeholder="0.00">
                            </div>
                            <div class="form-field">
                                <label for="price_industrial"><?php _e('Precio Industrial (€/tonelada)', 'adhesion'); ?></label>
                                <input type="number" 
                                       id="price_industrial" 
                                       name="price_industrial" 
                                       step="0.01" 
                                       min="0" 
                                       class="regular-text" 
                                       required
                                       placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="submit-row">
                            <?php submit_button(__('Añadir Material UBICA', 'adhesion')); ?>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de materiales UBICA existentes -->
                <div class="card" style="width: 100%; max-width: none;">
                    <h3><?php _e('Materiales UBICA Configurados', 'adhesion'); ?></h3>
                    
                    <?php if (!empty($prices_data)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Orden', 'adhesion'); ?></th>
                                    <th><?php _e('Material', 'adhesion'); ?></th>
                                    <th><?php _e('Doméstico (€/t)', 'adhesion'); ?></th>
                                    <th><?php _e('Comercial (€/t)', 'adhesion'); ?></th>
                                    <th><?php _e('Industrial (€/t)', 'adhesion'); ?></th>
                                    <th><?php _e('Estado', 'adhesion'); ?></th>
                                    <th><?php _e('Acciones', 'adhesion'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="ubica-prices-list">
                                <?php foreach ($prices_data as $price): ?>
                                    <tr data-id="<?php echo esc_attr($price->id); ?>">
                                        <td class="sort-handle">
                                            <span class="dashicons dashicons-menu"></span>
                                            <?php echo esc_html($price->sort_order); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($price->material_name); ?></strong>
                                        </td>
                                        <td><?php echo number_format($price->price_domestic, 2); ?> €</td>
                                        <td><?php echo number_format($price->price_commercial, 2); ?> €</td>
                                        <td><?php echo number_format($price->price_industrial, 2); ?> €</td>
                                        <td>
                                            <span class="status-badge <?php echo $price->is_active ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $price->is_active ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small edit-price" 
                                                    data-id="<?php echo esc_attr($price->id); ?>"
                                                    data-type="ubica">
                                                <?php _e('Editar', 'adhesion'); ?>
                                            </button>
                                            <button type="button" class="button button-small toggle-status" 
                                                    data-id="<?php echo esc_attr($price->id); ?>"
                                                    data-type="ubica">
                                                <?php echo $price->is_active ? __('Desactivar', 'adhesion') : __('Activar', 'adhesion'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete delete-price" 
                                                    data-id="<?php echo esc_attr($price->id); ?>"
                                                    data-type="ubica">
                                                <?php _e('Eliminar', 'adhesion'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No hay materiales UBICA configurados todavía.', 'adhesion'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Pestaña REINICIA -->
            <div class="reinicia-prices-section">
                <h2><?php _e('Precios REINICIA - Categorías + Medida (Kg/Unidades)', 'adhesion'); ?></h2>
                
                <!-- Formulario para añadir nueva categoría REINICIA -->
                <div class="card" style="width: 100%; max-width: none;">
                    <h3><?php _e('Añadir Nueva Categoría REINICIA', 'adhesion'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('adhesion_prices_action', 'adhesion_prices_nonce'); ?>
                        <input type="hidden" name="action" value="add_reinicia_price">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name"><?php _e('Nombre de la Categoría', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="category_name" 
                                           name="category_name" 
                                           class="regular-text" 
                                           required
                                           placeholder="<?php _e('Ej: Electrodomésticos, Muebles, Textil...', 'adhesion'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="price_kg"><?php _e('Precio por Kg (€/kg)', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="price_kg" 
                                           name="price_kg" 
                                           step="0.001" 
                                           min="0" 
                                           class="regular-text" 
                                           required
                                           placeholder="0.000">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="price_units"><?php _e('Precio por Unidad (€/unidad)', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="price_units" 
                                           name="price_units" 
                                           step="0.01" 
                                           min="0" 
                                           class="regular-text" 
                                           required
                                           placeholder="0.00">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="allows_punctual_import"><?php _e('Permite Importación Puntual', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="allows_punctual_import" 
                                           name="allows_punctual_import" 
                                           value="1">
                                    <span class="description"><?php _e('Marca si esta categoría permite importación puntual', 'adhesion'); ?></span>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Añadir Categoría REINICIA', 'adhesion')); ?>
                    </form>
                </div>
                
                <!-- Lista de categorías REINICIA existentes -->
                <div class="card" style="width: 100%; max-width: none;">
                    <h3><?php _e('Categorías REINICIA Configuradas', 'adhesion'); ?></h3>
                    
                    <?php if (!empty($prices_data)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Orden', 'adhesion'); ?></th>
                                    <th><?php _e('Categoría', 'adhesion'); ?></th>
                                    <th><?php _e('Precio/Kg €', 'adhesion'); ?></th>
                                    <th><?php _e('Precio/Unidad €', 'adhesion'); ?></th>
                                    <th><?php _e('Import. Puntual', 'adhesion'); ?></th>
                                    <th><?php _e('Estado', 'adhesion'); ?></th>
                                    <th><?php _e('Acciones', 'adhesion'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="reinicia-prices-list">
                                <?php foreach ($prices_data as $price): ?>
                                    <tr data-id="<?php echo esc_attr($price->id); ?>">
                                        <td class="sort-handle">
                                            <span class="dashicons dashicons-menu"></span>
                                            <?php echo esc_html($price->sort_order); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($price->category_name); ?></strong>
                                        </td>
                                        <td><?php echo number_format($price->price_kg, 3); ?> €</td>
                                        <td><?php echo number_format($price->price_units, 2); ?> €</td>
                                        <td>
                                            <span class="dashicons <?php echo $price->allows_punctual_import ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $price->is_active ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $price->is_active ? __('Activo', 'adhesion') : __('Inactivo', 'adhesion'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small edit-price" 
                                                    data-id="<?php echo esc_attr($price->id); ?>"
                                                    data-type="reinicia">
                                                <?php _e('Editar', 'adhesion'); ?>
                                            </button>
                                            <button type="button" class="button button-small toggle-status" 
                                                    data-id="<?php echo esc_attr($price->id); ?>"
                                                    data-type="reinicia">
                                                <?php echo $price->is_active ? __('Desactivar', 'adhesion') : __('Activar', 'adhesion'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete delete-price" 
                                                    data-id="<?php echo esc_attr($price->id); ?>"
                                                    data-type="reinicia">
                                                <?php _e('Eliminar', 'adhesion'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No hay categorías REINICIA configuradas todavía.', 'adhesion'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para editar precios -->
    <div id="edit-price-modal" class="adhesion-modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modal-title"><?php _e('Editar Precio', 'adhesion'); ?></h3>
            <form id="edit-price-form">
                <div id="modal-form-content">
                    <!-- El contenido se cargará dinámicamente -->
                </div>
                <div class="modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Guardar Cambios', 'adhesion'); ?></button>
                    <button type="button" class="button cancel-edit"><?php _e('Cancelar', 'adhesion'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Variables globales
    var currentEditId = null;
    var currentEditType = null;
    
    // Abrir modal de edición
    $(document).on('click', '.edit-price', function(e) {
        e.preventDefault();
        
        currentEditId = $(this).data('id');
        currentEditType = $(this).data('type');

       
        // Cargar datos del precio
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_get_price_data',
                price_id: currentEditId,
                price_type: currentEditType,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                $('#modal-form-content').html('<p><?php _e("Cargando...", "adhesion"); ?></p>');
                $('#edit-price-modal').show();
            },
            success: function(response) {
                if (response.success) {
                    loadEditForm(response.data, currentEditType);
                } else {
                    alert('<?php _e("Error al cargar los datos", "adhesion"); ?>: ' + response.data);
                    $('#edit-price-modal').hide();
                }
            },
            error: function() {
                alert('<?php _e("Error de conexión", "adhesion"); ?>');
                $('#edit-price-modal').hide();
            }
        });
    });
    
    // Cargar formulario de edición
    function loadEditForm(data, type) {
        var formHtml = '';
        
        if (type === 'ubica') {
            formHtml = '<div class="adhesion-form-grid">' +
                '<div class="form-field">' +
                    '<label for="edit_material_name"><?php _e("Nombre del Material", "adhesion"); ?></label>' +
                    '<input type="text" id="edit_material_name" name="material_name" value="' + data.material_name + '" required>' +
                '</div>' +
                '<div class="form-field">' +
                    '<label for="edit_price_domestic"><?php _e("Precio Doméstico (€/t)", "adhesion"); ?></label>' +
                    '<input type="number" id="edit_price_domestic" name="price_domestic" value="' + data.price_domestic + '" step="0.01" min="0" required>' +
                '</div>' +
                '<div class="form-field">' +
                    '<label for="edit_price_commercial"><?php _e("Precio Comercial (€/t)", "adhesion"); ?></label>' +
                    '<input type="number" id="edit_price_commercial" name="price_commercial" value="' + data.price_commercial + '" step="0.01" min="0" required>' +
                '</div>' +
                '<div class="form-field">' +
                    '<label for="edit_price_industrial"><?php _e("Precio Industrial (€/t)", "adhesion"); ?></label>' +
                    '<input type="number" id="edit_price_industrial" name="price_industrial" value="' + data.price_industrial + '" step="0.01" min="0" required>' +
                '</div>' +
            '</div>';
        }
        
        $('#modal-form-content').html(formHtml);
    }
    
    // Guardar cambios
    $('#edit-price-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'adhesion_update_price',
            price_id: currentEditId,
            price_type: currentEditType,
            nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
        };
        
        // Recoger datos del formulario
        $(this).find('input[name]').each(function() {
            formData[$(this).attr('name')] = $(this).val();
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('#edit-price-form .button-primary').prop('disabled', true).text('<?php _e("Guardando...", "adhesion"); ?>');
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e("Precio actualizado correctamente", "adhesion"); ?>');
                    $('#edit-price-modal').hide();
                    location.reload(); // Recargar para mostrar cambios
                } else {
                    alert('<?php _e("Error al actualizar", "adhesion"); ?>: ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e("Error de conexión", "adhesion"); ?>');
            },
            complete: function() {
                $('#edit-price-form .button-primary').prop('disabled', false).text('<?php _e("Guardar Cambios", "adhesion"); ?>');
            }
        });
    });
    
    // Cerrar modal
    $('.close-modal, .cancel-edit').on('click', function() {
        $('#edit-price-modal').hide();
    });
    
    // Cerrar modal al hacer clic fuera
    $('#edit-price-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Eliminar precio
    $(document).on('click', '.delete-price', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e("¿Estás seguro de que quieres eliminar este precio?", "adhesion"); ?>')) {
            return;
        }
        
        var priceId = $(this).data('id');
        var priceType = $(this).data('type');
        var $row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_delete_price',
                price_id: priceId,
                price_type: priceType,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                $row.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('<?php _e("Error al eliminar", "adhesion"); ?>: ' + response.data);
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                alert('<?php _e("Error de conexión", "adhesion"); ?>');
                $row.css('opacity', '1');
            }
        });
    });
    
    // Toggle status
    $(document).on('click', '.toggle-status', function(e) {
        e.preventDefault();
        
        var priceId = $(this).data('id');
        var priceType = $(this).data('type');
        var $button = $(this);
        var $statusBadge = $button.closest('tr').find('.status-badge');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'adhesion_toggle_price_status',
                price_id: priceId,
                price_type: priceType,
                nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
            },
            beforeSend: function() {
                $button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Cambiar texto del botón y estado
                    if ($button.text().indexOf('<?php _e("Desactivar", "adhesion"); ?>') !== -1) {
                        $button.text('<?php _e("Activar", "adhesion"); ?>');
                        $statusBadge.removeClass('status-active').addClass('status-inactive').text('<?php _e("Inactivo", "adhesion"); ?>');
                    } else {
                        $button.text('<?php _e("Desactivar", "adhesion"); ?>');
                        $statusBadge.removeClass('status-inactive').addClass('status-active').text('<?php _e("Activo", "adhesion"); ?>');
                    }
                } else {
                    alert('<?php _e("Error al cambiar estado", "adhesion"); ?>: ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e("Error de conexión", "adhesion"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    $('#ubica-prices-list, #reinicia-prices-list').sortable({
        handle: '.sort-handle',
        cursor: 'move',
        axis: 'y',
        placeholder: 'ui-state-highlight',
        helper: function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width());
            });
            return $helper;
        },
        update: function(event, ui) {
            var $table = $(this);
            var priceType = $table.closest('.ubica-prices-section').length ? 'ubica' : 'reinicia';
            var priceIds = [];
            
            // Recoger los IDs en el nuevo orden
            $table.find('tr[data-id]').each(function(index) {
                var id = $(this).data('id');
                priceIds.push(id);
                
                // Actualizar el número de orden visualmente
                $(this).find('.sort-handle').html(
                    '<span class="dashicons dashicons-menu"></span>' + (index + 1)
                );
            });
            
            // Enviar al servidor
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'adhesion_sort_prices',
                    price_ids: priceIds,
                    price_type: priceType,
                    nonce: '<?php echo wp_create_nonce('adhesion_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito brevemente
                        $('<div class="notice notice-success is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999;"><p><?php _e("Orden actualizado correctamente", "adhesion"); ?></p></div>')
                            .appendTo('body')
                            .delay(2000)
                            .fadeOut();
                    } else {
                        alert('<?php _e("Error al actualizar el orden", "adhesion"); ?>: ' + response.data);
                        // Recargar para restaurar el orden original
                        location.reload();
                    }
                },
                error: function() {
                    alert('<?php _e("Error de conexión al actualizar orden", "adhesion"); ?>');
                    location.reload();
                }
            });
        }
    });

    // Hacer que las filas se vean como draggables
    $('#ubica-prices-list tr[data-id], #reinicia-prices-list tr[data-id]').css({
        'cursor': 'move'
    });

    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .ui-state-highlight {
                height: 40px;
                background-color: #ffffcc;
                border: 2px dashed #ccc;
            }
            .sort-handle:hover {
                background-color: #f0f0f1;
                cursor: move;
            }
            .ui-sortable-helper {
                background-color: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            }
        `)
        .appendTo('head');


});




</script>

<style>
.adhesion-form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-field {
    display: flex;
    flex-direction: column;
}

.form-field label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #23282d;
}

.form-field input {
    width: 100% !important;
}

.submit-row {
    text-align: left;
    padding-top: 10px;
    border-top: 1px solid #e1e1e1;
}

@media (max-width: 1200px) {
    .adhesion-form-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .adhesion-form-grid {
        grid-template-columns: 1fr;
    }
}

.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.status-active {
    background-color: #d1e7dd;
    color: #0f5132;
}

.status-inactive {
    background-color: #f8d7da;
    color: #842029;
}

.sort-handle {
    cursor: move;
    text-align: center;
}

.sort-handle:hover {
    background-color: #f0f0f1;
}

.adhesion-modal {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 60%;
    max-width: 600px;
    border-radius: 5px;
}

.close-modal {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-modal:hover {
    color: black;
}

.modal-actions {
    margin-top: 20px;
    text-align: right;
}

.modal-actions .button {
    margin-left: 10px;
}
</style>
