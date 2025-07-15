# Plugin Adhesión - Árbol Completo de Ficheros REALES

```
adhesion/
├── adhesion.php                           → Archivo principal del plugin
├── uninstall.php                          → Limpia todo al desinstalar
├── readme.txt                             → Documentación para WordPress
├── docs/                                  → Documentación técnica del proyecto
│   ├── estructura.md                      → Mapa del proyecto (este archivo)
│   └── especificaciones_tecnicas_v4.md    → Especificaciones técnicas detalladas
├── assets/
│   ├── css/
│   │   ├── admin.css                      → Estilos del backend/administración
│   │   └── frontend.css                   → Estilos del frontend/usuarios
│   ├── js/
│   │   ├── admin.js                       → JavaScript del backend
│   │   ├── frontend.js                    → JavaScript del frontend
│   │   └── calculadora.js                 → JavaScript específico calculadora
│   └── images/
│       └── icons/                         → Iconos del plugin
├── includes/
│   ├── class-activator.php                → Crea tablas y configura plugin
│   ├── class-deactivator.php              → Gestiona desactivación temporal
│   ├── class-database.php                 → Operaciones de base de datos
│   ├── class-ajax-handler.php             → Maneja peticiones AJAX
│   ├── class-email-manager.php            → Gestión centralizada de emails
│   ├── functions.php                      → Funciones auxiliares globales
│   ├── repositories/                      → Repository Pattern (separación datos)
│   │   ├── class-base-repository.php      → Clase base para repositories
│   │   └── class-ubica-prices-repository.php → Repository precios UBICA
│   └── ajax/                              → Handlers AJAX específicos
│       └── class-prices-ajax-handler.php  → Handler AJAX gestión precios
├── admin/
│   ├── class-admin.php                    → Controlador principal del admin
│   ├── class-settings.php                 → Configuraciones (APIs, etc.)
│   ├── class-documents.php                → Editor de documentos firma
│   ├── class-users-list.php               → Listado de usuarios
│   ├── class-contracts-list.php           → Listado de contratos
│   ├── class-calculations-list.php        → Listado de cálculos
│   ├── class-prices.php                   → Gestión de precios (vista)
│   └── partials/
│       ├── dashboard-display.php          → Vista dashboard principal
│       ├── settings-display.php           → Vista de configuración
│       ├── documents-display.php          → Vista de documentos
│       ├── users-display.php              → Vista de usuarios
│       ├── contracts-display.php          → Vista de contratos
│       ├── calculations-display.php       → Vista de cálculos
│       └── prices-display.php             → Vista gestión de precios
├── public/
│   ├── class-public.php                   → Controlador principal frontend
│   ├── class-calculator.php               → Lógica de la calculadora
│   ├── class-user-account.php             → Gestión cuentas de usuario
│   ├── class-payment.php                  → Integración con Redsys
│   ├── class-docusign.php                 → Integración con DocuSign
│   └── partials/
│       ├── calculator-display.php         → Vista de la calculadora
│       ├── user-account-display.php       → Vista de cuenta de usuario
│       ├── payment-display.php            → Vista de pago
│       └── contract-signing-display.php   → Vista de firma de contratos
└── templates/
    └── emails/
        └── user/
            └── welcome.php                → Email bienvenida nuevo usuario
```

**NUEVAS CARPETAS AÑADIDAS**:
- 📂 `includes/repositories/` → Implementación Repository Pattern para separación de datos
- 📂 `includes/ajax/` → Handlers AJAX específicos por funcionalidad  
- 📄 `admin/class-prices.php` → Nueva clase gestión de precios
- 📄 `admin/partials/prices-display.php` → Vista panel administración precios

**ARQUITECTURA MEJORADA**: Repository Pattern + Separación AJAX handlers para escalabilidad