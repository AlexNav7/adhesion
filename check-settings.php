<?php
/**
 * Script para verificar configuración actual
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../../wp-load.php';
}

// Solo ejecutar si estamos en modo debug
if (!defined('ADHESION_DEBUG') || !ADHESION_DEBUG) {
    die('Solo disponible en modo debug');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Sin permisos');
}

echo "<h1>Verificador de Configuración</h1>";

// Mostrar configuración actual
$settings = get_option('adhesion_settings', array());
echo "<h2>Configuración actual (wp_options):</h2>";
echo "<pre>";
print_r($settings);
echo "</pre>";

// Verificar específicamente transferencia bancaria
echo "<h2>Verificación de transferencia bancaria:</h2>";
echo "<p><strong>IBAN:</strong> " . ($settings['bank_transfer_iban'] ?? 'NO CONFIGURADO') . "</p>";
echo "<p><strong>Banco:</strong> " . ($settings['bank_transfer_bank_name'] ?? 'NO CONFIGURADO') . "</p>";
echo "<p><strong>Instrucciones:</strong> " . ($settings['bank_transfer_instructions'] ?? 'NO CONFIGURADO') . "</p>";

// Verificar si la opción existe
$option_exists = get_option('adhesion_settings') !== false;
echo "<p><strong>Opción 'adhesion_settings' existe:</strong> " . ($option_exists ? 'SÍ' : 'NO') . "</p>";

// Mostrar tamaño de la configuración
echo "<p><strong>Número de configuraciones:</strong> " . count($settings) . "</p>";

// Mostrar todas las claves
echo "<h2>Todas las claves de configuración:</h2>";
echo "<ul>";
foreach (array_keys($settings) as $key) {
    echo "<li>" . esc_html($key) . "</li>";
}
echo "</ul>";

// Información adicional
echo "<h2>Información adicional:</h2>";
echo "<p><strong>WordPress DB_NAME:</strong> " . DB_NAME . "</p>";
echo "<p><strong>WordPress table prefix:</strong> " . $GLOBALS['wpdb']->prefix . "</p>";
echo "<p><strong>Current user ID:</strong> " . get_current_user_id() . "</p>";
echo "<p><strong>Current user can manage options:</strong> " . (current_user_can('manage_options') ? 'SÍ' : 'NO') . "</p>";
?>