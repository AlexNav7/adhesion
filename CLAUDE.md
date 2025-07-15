
# CLAUDE.md

## Instrucciones Generales
- Siempre responde en español
- Realiza solo la tarea que te pido, no hagas nada más que no tenga que ver con esa tarea
- Cuando termines algún comando simplemente dime que ya realizaste el cambio solicitado, sin entrar en detalles ni resultados.


## Visión General del Proyecto

Este es un plugin de WordPress llamado "Adhesión" que gestiona un proceso completo de adhesión de clientes, incluyendo calculadora de presupuestos, pagos y firma de contratos. El plugin está diseñado para instalarse en dos sitios diferentes: reinicia.es y ubicaenvases.es.

## Arquitectura

### Estructura Principal del Plugin
- **Archivo principal**: `adhesion.php` - Implementación del patrón Singleton con la inicialización del plugin
- **Activación/Desactivación**: `includes/class-activator.php` y `includes/class-deactivator.php`
- **Capa de base de datos**: `includes/class-database.php` - Maneja todas las operaciones CRUD
- **Manejo AJAX**: `includes/class-ajax-handler.php` con manejadores específicos en `includes/ajax/`
- **Patrón repositorio**: `includes/repositories/` para separar la capa de acceso a datos

### Componentes Frontend
- **Calculadora**: `public/class-calculator.php` - Lógica de cálculo de presupuestos con AJAX
- **Cuentas de usuario**: `public/class-user-account.php` - Registro e inicio de sesión de clientes
- **Pagos**: `public/class-payment.php` - Integración con la pasarela de pago Redsys
- **DocuSign**: `public/class-docusign.php` - Firma digital de contratos
- **Vistas**: `public/partials/` - Plantillas para el frontend

### Componentes Admin
- **Admin principal**: `admin/class-admin.php` - Integración con el admin de WordPress
- **Configuración**: `admin/class-settings.php` - Configuración del plugin (APIs, etc.)
- **Editor de documentos**: `admin/class-documents.php` - Gestión de plantillas de contratos
- **Listados**: `admin/class-*-list.php` - Listados de usuarios, contratos y cálculos
- **Vistas admin**: `admin/partials/` - Plantillas para el backend

### Tablas en Base de Datos
El plugin crea estas tablas personalizadas:
- `wp_adhesion_calculations` - Cálculos de presupuestos
- `wp_adhesion_contracts` - Contratos firmados
- `wp_adhesion_documents` - Plantillas de documentos editables
- `wp_adhesion_settings` - Configuración del plugin
- `wp_adhesion_calculator_prices` - Datos de precios para la calculadora

## Funcionalidades Clave

### Flujo del Cliente
1. **Registro/Inicio de sesión**: Sistema de usuarios basado en CIF con rol `adhesion_client`
2. **Calculadora de presupuestos**: Calculadora interactiva con precios por material
3. **Pago**: Pagos con tarjeta Redsys o transferencia bancaria con subida de justificante
4. **Firma de contrato**: Integración con DocuSign para firmas digitales
5. **Panel del usuario**: Gestión de cuenta y seguimiento del proceso

### Funcionalidades Admin
- Gestión de configuraciones para integraciones API (Redsys, DocuSign)
- Editor de plantillas de documentos con variables ([nombre], [cif], etc.)
- Gestión de usuarios y contratos
- Gestión de precios de la calculadora
- Seguimiento de procesos y actualización de estados

## Entorno de Desarrollo

### Entorno WordPress
- Versión mínima de WordPress: 5.0
- Versión de PHP: 7.4+
- Es un entorno de desarrollo WAMP en Windows

### Estructura del Plugin
- Usa la arquitectura estándar de plugins de WordPress con hooks adecuados
- Implementa el patrón singleton para la clase principal
- Sigue los estándares de codificación y prácticas de seguridad de WordPress
- Utiliza funciones nativas de WordPress para base de datos, AJAX y gestión de usuarios

### Clases Clave y Sus Responsabilidades
- `Adhesion_Plugin` (adhesion.php:59): Clase principal del plugin con singleton
- `Adhesion_Database` (includes/class-database.php): Operaciones con base de datos
- `Adhesion_Calculator` (public/class-calculator.php): Lógica del cálculo de presupuestos
- `Adhesion_Admin` (admin/class-admin.php): Integración con el admin de WordPress
- `Adhesion_User_Account` (public/class-user-account.php): Gestión de usuarios

## Comandos de Desarrollo

### Desarrollo WordPress
Este plugin no usa herramientas como npm o composer. Es un plugin puro de WordPress que funciona con el enfoque estándar:

- **Activar plugin**: Desde el admin de WordPress o WP-CLI
- **Modo debug**: Configura `ADHESION_DEBUG` en true en adhesion.php:32
- **Logs**: Revisa `logs/adhesion.log` para logs específicos del plugin
- **Debug WordPress**: Habilita `WP_DEBUG` en wp-config.php

### Pruebas
- Pruebas manuales desde el admin de WordPress y el frontend
- Actualmente no hay framework de tests automatizados configurado
- Probar en los dos sitios destino: reinicia.es y ubicaenvases.es

## Notas Importantes

### Seguridad
- Todas las peticiones AJAX usan nonces de WordPress
- Se comprueban capacidades de usuario antes de operaciones sensibles
- Las consultas a la base de datos usan prepared statements
- Se previene el acceso directo con comprobación de ABSPATH

### Integraciones
- **Redsys**: Requiere credenciales API para la pasarela de pago
- **DocuSign**: Requiere configuración API para firma digital
- **Usuarios WordPress**: Extiende el sistema de usuarios con rol personalizado

### Notas de Estructura
- `assets/js/calculadora.js` fue eliminado (según git status)
- Nuevos manejadores AJAX en `includes/ajax/`
- Implementación del patrón repositorio en `includes/repositories/`
- Plantillas de emails en `templates/emails/`

### Constantes del Plugin
- `ADHESION_PLUGIN_URL`: URL del plugin
- `ADHESION_PLUGIN_PATH`: Ruta del plugin en el sistema de archivos
- `ADHESION_PLUGIN_VERSION`: Versión actual (1.0.0)
- `ADHESION_DEBUG`: Flag de modo debug

## Tareas Comunes de Desarrollo

### Añadir Nuevas Funcionalidades
1. Revisar si se necesitan cambios en el esquema en `includes/class-activator.php`
2. Añadir lógica en la clase adecuada (admin/, public/ o includes/)
3. Crear manejadores AJAX en `includes/ajax/` si se necesita
4. Añadir plantillas frontend en `public/partials/` o vistas admin en `admin/partials/`
5. Actualizar archivos de idioma si se añaden textos visibles

### Depuración
- Revisar `logs/adhesion.log` para logs del plugin
- Habilitar `ADHESION_DEBUG` en adhesion.php para logs detallados
- Usar herramientas y logs de debug de WordPress
- Revisar la consola del navegador para errores JavaScript

### Operaciones con Base de Datos
- Usar la clase `Adhesion_Database` para todas las operaciones
- Seguir los patrones de base de datos de WordPress con $wpdb
- Usar prepared statements para seguridad
