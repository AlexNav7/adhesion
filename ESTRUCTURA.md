# Estructura del Plugin Adhesión

## Árbol de Directorios y Archivos

```
adhesion/
├── adhesion.php                           # Archivo principal del plugin
├── uninstall.php                          # Script de desinstalación
├── readme.txt                             # Documentación del plugin
├── ESTRUCTURA.md                          # Este archivo de documentación
├── assets/
│   ├── css/
│   │   ├── admin.css                      # Estilos del backend
│   │   └── frontend.css                   # Estilos del frontend
│   ├── js/
│   │   ├── admin.js                       # JavaScript del backend
│   │   ├── frontend.js                    # JavaScript del frontend
│   │   └── calculadora.js                 # JavaScript específico de la calculadora
│   └── images/
│       └── icons/                         # Iconos del plugin
├── includes/
│   ├── class-activator.php                # Activación del plugin
│   ├── class-deactivator.php              # Desactivación del plugin
│   ├── class-database.php                 # Gestión de base de datos
│   ├── class-ajax-handler.php             # Manejador de peticiones AJAX
│   └── functions.php                      # Funciones auxiliares
├── admin/
│   ├── class-admin.php                    # Clase principal del admin
│   ├── class-settings.php                 # Configuraciones generales
│   ├── class-documents.php                # Gestión de documentos
│   ├── class-users-list.php               # Listado de usuarios
│   ├── class-contracts-list.php           # Listado de contratos
│   ├── class-calculations-list.php        # Listado de cálculos
│   └── partials/
│       ├── settings-display.php           # Vista de configuración
│       ├── documents-display.php          # Vista de documentos
│       ├── users-display.php              # Vista de usuarios
│       ├── contracts-display.php          # Vista de contratos
│       └── calculations-display.php       # Vista de cálculos
├── public/
│   ├── class-public.php                   # Clase principal del frontend
│   ├── class-calculator.php               # Calculadora de presupuestos
│   ├── class-user-account.php             # Gestión de cuentas de usuario
│   ├── class-payment.php                  # Integración con Redsys
│   ├── class-docusign.php                 # Integración con DocuSign
│   └── partials/
│       ├── calculator-display.php         # Vista de la calculadora
│       ├── user-account-display.php       # Vista de cuenta de usuario
│       ├── payment-display.php            # Vista de pago
│       └── contract-signing-display.php   # Vista de firma de contratos
├── templates/
│   ├── emails/
│   │   ├── welcome.php                    # Email de bienvenida
│   │   ├── contract-signed.php            # Email de contrato firmado
│   │   └── payment-confirmation.php       # Email de confirmación de pago
│   └── documents/
│       └── contract-template.php          # Plantilla base de contrato
└── languages/
    ├── adhesion.pot                       # Archivo de traducción base
    └── adhesion-es_ES.po                  # Traducción en español
```

## Descripción de Componentes

### 📁 **Archivos Principales**
- **`adhesion.php`**: Archivo principal que inicializa el plugin
- **`uninstall.php`**: Se ejecuta al desinstalar el plugin, limpia la BD
- **`readme.txt`**: Documentación estándar de WordPress

### 📁 **assets/**
Contiene todos los recursos estáticos del plugin:
- **`css/`**: Archivos de estilos separados para admin y frontend
- **`js/`**: Scripts JavaScript organizados por funcionalidad
- **`images/`**: Iconos y recursos gráficos

### 📁 **includes/**
Clases y funciones principales del plugin:
- **`class-activator.php`**: Maneja la activación, crea tablas y páginas
- **`class-deactivator.php`**: Limpieza al desactivar
- **`class-database.php`**: Operaciones de base de datos
- **`class-ajax-handler.php`**: Maneja todas las peticiones AJAX
- **`functions.php`**: Funciones auxiliares globales

### 📁 **admin/**
Backend del plugin (área de administración):
- **`class-admin.php`**: Controlador principal del admin
- **`class-settings.php`**: Configuraciones (APIs, etc.)
- **`class-documents.php`**: Editor de documentos para firma
- **`class-*-list.php`**: Listados de usuarios, contratos y cálculos
- **`partials/`**: Vistas HTML del backend

### 📁 **public/**
Frontend del plugin (cara visible al usuario):
- **`class-public.php`**: Controlador principal del frontend
- **`class-calculator.php`**: Lógica de la calculadora
- **`class-user-account.php`**: Gestión de cuentas de usuario
- **`class-payment.php`**: Integración con pasarela Redsys
- **`class-docusign.php`**: Integración con DocuSign
- **`partials/`**: Vistas HTML del frontend

### 📁 **templates/**
Plantillas para emails y documentos:
- **`emails/`**: Plantillas de correos electrónicos
- **`documents/`**: Plantillas de contratos editables

### 📁 **languages/**
Archivos de internacionalización para traducir el plugin.

## Base de Datos

### Tablas Creadas:
1. **`adhesion_calculations`**: Almacena cálculos de presupuestos
2. **`adhesion_contracts`**: Gestiona contratos y su estado
3. **`adhesion_documents`**: Plantillas de documentos editables
4. **`adhesion_settings`**: Configuraciones del plugin

## Flujo de Trabajo

### Proceso de Adhesión:
1. **Llamada a la acción** → Usuario accede desde bloque en home
2. **Registro/Login** → Crear cuenta o iniciar sesión
3. **Calculadora** → Calcular presupuesto personalizado
4. **Datos completos** → Formulario con información del cliente
5. **Pago** → Integración con Redsys
6. **Firma digital** → DocuSign para firmar contratos
7. **Finalización** → Notificaciones y documentos firmados

### Integraciones:
- **Redsys**: Pasarela de pagos con tarjeta
- **DocuSign**: Firma digital de contratos
- **WordPress**: Usuarios, roles y permisos nativos
- **Elementor**: Compatible con bloques de Elementor

## Características Técnicas

- **Compatible con WordPress 5.0+**
- **Responsive Design**
- **AJAX para mejor UX**
- **Seguridad con nonces**
- **Internacionalización**
- **Hooks y filtros para extensibilidad**
- **Base de datos optimizada**

## Instalación

1. Subir carpeta `adhesion/` a `/wp-content/plugins/`
2. Activar desde el menú Plugins de WordPress
3. Configurar APIs en Adhesión > Ajustes
4. Crear páginas necesarias con shortcodes

## Configuración Necesaria

### APIs Requeridas:
- **Redsys**: Merchant Code, Terminal, Secret Key
- **DocuSign**: Integration Key, Secret Key, Account ID

### Páginas a Crear:
- Calculadora: `[adhesion_calculator]`
- Mi Cuenta: `[adhesion_account]`
- Proceso de Pago: `[adhesion_payment]`

---

**Versión**: 1.0.0  
**Compatibilidad**: WordPress 5.0+  
**Licencia**: GPL v2 or later