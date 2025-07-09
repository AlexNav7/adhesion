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
                <div class="card">
                    <h3><?php _e('Añadir Nuevo Material UBICA', 'adhesion'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('adhesion_prices_action', 'adhesion_prices_nonce'); ?>
                        <input type="hidden" name="action" value="add_ubica_price">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="material_name"><?php _e('Nombre del Material', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="material_name" 
                                           name="material_name" 
                                           class="regular-text" 
                                           required
                                           placeholder="<?php _e('Ej: Vidrio, Plástico, Metal...', 'adhesion'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="price_domestic"><?php _e('Precio Doméstico (€/tonelada)', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="price_domestic" 
                                           name="price_domestic" 
                                           step="0.01" 
                                           min="0" 
                                           class="regular-text" 
                                           required
                                           placeholder="0.00">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="price_commercial"><?php _e('Precio Comercial (€/tonelada)', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="price_commercial" 
                                           name="price_commercial" 
                                           step="0.01" 
                                           min="0" 
                                           class="regular-text" 
                                           required
                                           placeholder="0.00">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="price_industrial"><?php _e('Precio Industrial (€/tonelada)', 'adhesion'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="price_industrial" 
                                           name="price_industrial" 
                                           step="0.01" 
                                           min="0" 
                                           class="regular-text" 
                                           required
                                           placeholder="0.00">
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Añadir Material UBICA', 'adhesion')); ?>
                    </form>
                </div>
                
                <!-- Lista de materiales UBICA existentes -->
                <div class="card">
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
                <div class="card">
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
                <div class="card">
                    <h3><?php _e('Categorías REINICIA Configuradas', 'adhesion'); ?></h3>
                    
                    <?php if (!empty($prices_data)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Orden', 'adhesion'); ?></th>
                                    <th><?php _e('Categoría', 'adhesion'); ?></th>
                                    <th><?php _e('Precio/Kg (€)', 'adhesion'); ?></th>
                                    <th><?php _e('Precio/Unidad (€)', 'adhesion'); ?></th>
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

<style>
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