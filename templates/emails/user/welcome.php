<?php
/**
 * Template de email de bienvenida
 * Archivo: templates/emails/user/welcome.php
 * 
 * Variables disponibles:
 * $user_name - Nombre completo del usuario
 * $user_email - Email del usuario
 * $user_cif - CIF de la empresa
 * $empresa - Nombre de la empresa
 * $site_name - Nombre del sitio web
 * $login_url - URL para iniciar sesión
 * $account_url - URL de la cuenta de usuario
 * $support_email - Email de soporte
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a <?php echo esc_html($site_name); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0073aa;
        }
        .header h1 {
            color: #0073aa;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin-bottom: 30px;
        }
        .credentials-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials-box h3 {
            margin-top: 0;
            color: #495057;
            font-size: 18px;
        }
        .credential-item {
            margin: 10px 0;
            padding: 8px 0;
        }
        .credential-label {
            font-weight: bold;
            color: #495057;
        }
        .credential-value {
            color: #0073aa;
            font-family: monospace;
            background-color: #e9ecef;
            padding: 4px 8px;
            border-radius: 3px;
            display: inline-block;
            margin-left: 10px;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
        }
        .btn-primary {
            background-color: #0073aa;
            color: #ffffff;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: #ffffff;
        }
        .footer {
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            margin-top: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .next-steps {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .next-steps h3 {
            margin-top: 0;
            color: #155724;
        }
        .steps-list {
            margin: 0;
            padding-left: 20px;
        }
        .steps-list li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>¡Bienvenido a <?php echo esc_html($site_name); ?>!</h1>
        </div>

        <!-- Contenido principal -->
        <div class="content">
            <p><strong>Hola <?php echo esc_html($user_name); ?>,</strong></p>
            
            <p>¡Nos complace darte la bienvenida a nuestra plataforma de adhesión! Tu cuenta ha sido creada correctamente y ya puedes comenzar a utilizar todos nuestros servicios.</p>

            <!-- Datos de acceso -->
            <div class="credentials-box">
                <h3>📋 Datos de tu cuenta</h3>
                
                <div class="credential-item">
                    <span class="credential-label">Email de acceso:</span>
                    <span class="credential-value"><?php echo esc_html($user_email); ?></span>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">Empresa:</span>
                    <span class="credential-value"><?php echo esc_html($empresa); ?></span>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">CIF:</span>
                    <span class="credential-value"><?php echo esc_html($user_cif); ?></span>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">Contraseña:</span>
                    <span class="credential-value">La que elegiste al registrarte</span>
                </div>
            </div>

            <!-- Próximos pasos -->
            <div class="next-steps">
                <h3>🚀 Próximos pasos</h3>
                <ol class="steps-list">
                    <li><strong>Accede a tu cuenta</strong> usando tu email y contraseña</li>
                    <li><strong>Calcula tu presupuesto</strong> en nuestra calculadora personalizada</li>
                    <li><strong>Completa los datos</strong> necesarios para tu adhesión</li>
                    <li><strong>Procede con el pago</strong> de forma segura</li>
                    <li><strong>Firma digitalmente</strong> tu contrato de adhesión</li>
                </ol>
            </div>

            <!-- Botones de acción -->
            <div class="action-buttons">
                <a href="<?php echo esc_url($account_url); ?>" class="btn btn-primary">
                    🏠 Ir a mi cuenta
                </a>
                <a href="<?php echo esc_url(home_url('/calculadora-presupuesto/')); ?>" class="btn btn-secondary">
                    🧮 Empezar cálculo
                </a>
            </div>

            <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos respondiendo a este email o escribiendo a <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a>.</p>

            <p><strong>¡Gracias por confiar en nosotros!</strong></p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Este es un email automático de <strong><?php echo esc_html($site_name); ?></strong></p>
            <p>Si no solicitaste esta cuenta, puedes ignorar este mensaje.</p>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html($site_name); ?></a> | 
                <a href="mailto:<?php echo esc_attr($support_email); ?>">Soporte</a>
            </p>
        </div>
    </div>
</body>
</html>