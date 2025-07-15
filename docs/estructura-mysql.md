# Estructura de Base de Datos MySQL - Plugin Adhesión

## Tablas Principales

**Nota:** La configuración del plugin se almacena en `wp_options` usando la clave `adhesion_settings`, no en una tabla separada.

### adhesion_calculations
Almacena los cálculos de presupuesto realizados por los usuarios.

```sql
CREATE TABLE IF NOT EXISTS `wp_adhesion_calculations` (
    `id` mediumint(9) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL,
    `calculation_data` longtext NOT NULL,
    `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
    `status` varchar(20) DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`)
);
```

**Campos importantes:**
- `calculation_data`: JSON con datos de materiales y cantidades
- `total_price`: Precio total calculado
- `status`: Estado del cálculo (active, archived, etc.)

### adhesion_contracts
Almacena los contratos firmados digitalmente.

```sql
CREATE TABLE IF NOT EXISTS `wp_adhesion_contracts` (
    `id` mediumint(9) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL,
    `calculation_id` mediumint(9) DEFAULT NULL,
    `contract_number` varchar(50) DEFAULT NULL,
    `status` varchar(50) DEFAULT 'pending',
    `client_data` longtext,
    `docusign_envelope_id` varchar(255) DEFAULT NULL,
    `signed_document_url` varchar(500) DEFAULT NULL,
    `payment_status` varchar(50) DEFAULT 'pending',
    `payment_amount` decimal(10,2) DEFAULT 0.00,
    `payment_reference` varchar(100) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `signed_at` datetime DEFAULT NULL,
    `payment_completed_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    UNIQUE KEY `contract_number` (`contract_number`)
);
```

**Estados de contrato:**
- pending: Pendiente de firma
- sent: Enviado a DocuSign
- completed: Firmado y completado
- declined: Rechazado
- voided: Anulado

### adhesion_documents
Plantillas de documentos editables.

```sql
CREATE TABLE IF NOT EXISTS `wp_adhesion_documents` (
    `id` mediumint(9) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `type` varchar(50) NOT NULL,
    `content` longtext NOT NULL,
    `variables` longtext,
    `status` varchar(20) DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `type` (`type`),
    KEY `status` (`status`)
);
```

### Configuración del Plugin
La configuración del plugin se almacena en la tabla estándar de WordPress `wp_options` usando la clave `adhesion_settings`.

```php
// Obtener configuración
$settings = get_option('adhesion_settings', array());

// Guardar configuración
update_option('adhesion_settings', $settings_array);
```

**Campos de configuración disponibles:**
- `redsys_merchant_code`: Código de comercio Redsys
- `redsys_secret_key`: Clave secreta Redsys
- `redsys_environment`: Entorno (test/production)
- `docusign_integration_key`: Clave de integración DocuSign
- `docusign_account_id`: ID de cuenta DocuSign
- `bank_transfer_iban`: IBAN para transferencias bancarias
- `bank_transfer_bank_name`: Nombre del banco
- `bank_transfer_instructions`: Instrucciones de transferencia
- `admin_email`: Email del administrador
- `calculator_enabled`: Habilitar calculadora (1/0)
- `auto_create_users`: Crear usuarios automáticamente (1/0)
- `require_payment`: Requerir pago antes de firma (1/0)

### adhesion_calculator_prices
Precios para la calculadora UBICA.

```sql
CREATE TABLE IF NOT EXISTS `wp_adhesion_calculator_prices` (
    `id` mediumint(9) NOT NULL AUTO_INCREMENT,
    `material_name` varchar(255) NOT NULL,
    `price_domestic` decimal(10,2) NOT NULL DEFAULT 0.00,
    `price_commercial` decimal(10,2) NOT NULL DEFAULT 0.00,
    `price_industrial` decimal(10,2) NOT NULL DEFAULT 0.00,
    `status` varchar(20) DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `material_name` (`material_name`),
    KEY `status` (`status`)
);
```

## Relaciones entre Tablas

```
adhesion_calculations ---> adhesion_contracts
       |                          |
       |                          |
    user_id                   user_id
       |                          |
       v                          v
   wp_users <----------------> wp_users
```

## Formato de Datos JSON

### calculation_data
Almacena los datos de materiales seleccionados:

```json
{
  "materials": [
    {
      "material": "Vidrio",
      "type": "domestic",
      "quantity": 10,
      "price": 15.50
    },
    {
      "material": "Papel/Cartón",
      "type": "commercial", 
      "quantity": 5,
      "price": 12.30
    }
  ],
  "totals": {
    "domestic": 155.00,
    "commercial": 61.50,
    "industrial": 0.00
  }
}
```

### client_data
Almacena datos del cliente para contratos:

```json
{
  "company_name": "Empresa Ejemplo S.L.",
  "cif": "B12345678",
  "address": "Calle Ejemplo 123",
  "city": "Madrid",
  "postal_code": "28001",
  "province": "Madrid",
  "phone": "912345678",
  "email": "contacto@ejemplo.com",
  "legal_representative": {
    "name": "Juan",
    "surname": "Pérez",
    "dni": "12345678A",
    "phone": "612345678"
  }
}
```

## Índices y Optimización

### Índices Principales
- `user_id`: Para consultas por usuario
- `status`: Para filtrar por estado
- `material_name`: Para búsquedas de materiales
- `contract_number`: Único para contratos

### Consideraciones de Rendimiento
- Los campos `longtext` se usan para JSON flexible
- Índices en campos de consulta frecuente
- Timestamps automáticos para auditoría
- Claves foráneas lógicas (no físicas por compatibilidad WordPress)

## Migración y Mantenimiento

### Creación de Tablas
Las tablas se crean automáticamente en:
- Activación del plugin
- Actualización de versión
- Método: `Adhesion_Activator::create_with_direct_sql()`

### Backup Recomendado
```sql
-- Backup de datos críticos
SELECT * FROM wp_adhesion_calculations WHERE status = 'active';
SELECT * FROM wp_adhesion_contracts WHERE status IN ('completed', 'signed');
SELECT * FROM wp_adhesion_calculator_prices WHERE status = 'active';

-- Backup de configuración (desde wp_options)
SELECT * FROM wp_options WHERE option_name = 'adhesion_settings';
```