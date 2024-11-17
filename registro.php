<?php
session_start();
include 'db.php';
require 'phpqrcode/qrlib.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$nombre_tabla = filter_var($_GET['evento'], FILTER_SANITIZE_STRING);
$result = $conn->query("SHOW TABLES LIKE '$nombre_tabla'");
if ($result->num_rows === 0) {
    die("<div class='error-container'><div class='error-card'><h2>El evento no existe.</h2></div></div>");
}

// Obtener la configuración del evento, incluyendo el estado, logo, banner y configuración SMTP
$estado_result = $conn->query("SELECT estado, logo, banner, smtp_host, smtp_port, smtp_user, smtp_password FROM configuracion_eventos WHERE tabla = '$nombre_tabla'");
$evento_config = $estado_result->fetch_assoc();
$estado = $evento_config['estado'] ?? 'habilitado';
$logo = $evento_config['logo'] ?? '';
$banner = $evento_config['banner'] ?? '';
$smtp_host = $evento_config['smtp_host'] ?? 'smtp.gmail.com';
$smtp_port = $evento_config['smtp_port'] ?? 587;
$smtp_user = $evento_config['smtp_user'] ?? 'tu_correo@gmail.com';
$smtp_password = $evento_config['smtp_password'] ?? 'tu_contraseña';

if ($estado === 'deshabilitado') {
    die("<div class='error-container'><div class='error-card'><h2>Este evento está actualmente deshabilitado</h2><p>Por favor, contacte al administrador para más detalles.</p></div></div>");
}

$columnas = $conn->query("DESCRIBE $nombre_tabla");
$campos = [];
while ($columna = $columnas->fetch_assoc()) {
    if ($columna['Field'] !== 'id' && $columna['Field'] !== 'created_at') {
        $campos[] = $columna;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    $placeholders = [];
    $tipos = '';
    $valores = [];

    foreach ($campos as $campo) {
        $nombre_campo = $campo['Field'];
        $data[$nombre_campo] = $_POST[$nombre_campo];
        $placeholders[] = '?';
        $tipos .= 's';
        $valores[] = $_POST[$nombre_campo];
    }

    $email = $data['email'];
    $verificarEmail = $conn->prepare("SELECT id FROM $nombre_tabla WHERE email = ?");
    $verificarEmail->bind_param("s", $email);
    $verificarEmail->execute();
    $verificarEmail->store_result();

    if ($verificarEmail->num_rows > 0) {
        echo "<div class='alert alert-danger'>Este correo electrónico ya está registrado para este evento.</div>";
    } else {
        $sql = "INSERT INTO $nombre_tabla (" . implode(', ', array_keys($data)) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($tipos, ...$valores);

        if ($stmt->execute()) {
            // Generar el QR con todos los datos
            $qrData = json_encode($data);
            $qrDir = "qrs";
            if (!is_dir($qrDir)) {
                mkdir($qrDir, 0777, true);
            }
            $qrFile = "$qrDir/qr_" . uniqid() . ".png";
            QRcode::png($qrData, $qrFile, QR_ECLEVEL_H, 5);

            // Configurar correo electrónico
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_user;
                $mail->Password = $smtp_password;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $smtp_port;

                $mail->setFrom($smtp_user, 'Registro de Evento');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Registro Confirmado para el Evento ' . str_replace('_', ' ', $nombre_tabla);

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f4f4f9; color: #333;'>
                        <div style='background-color: #007bff; color: #ffffff; padding: 15px; text-align: center; border-radius: 8px 8px 0 0;'>
                            <h2 style='margin: 0; font-size: 24px;'>Registro Confirmado</h2>
                            <p style='margin: 0;'>¡Gracias por registrarte en nuestro evento!</p>
                        </div>
                        <div style='padding: 20px; background-color: #ffffff; border-radius: 0 0 8px 8px;'>
                            <h3 style='font-size: 20px; color: #007bff;'>Detalles del Evento</h3>
                            <p>Estimado/a <strong>{$data['nombre']}</strong>,</p>
                            <p>Nos complace confirmarte que tu registro ha sido exitoso. A continuación, encontrarás el código QR que deberás presentar para tu ingreso al evento:</p>
                            <div style='text-align: center; margin: 20px 0;'>
                                <img src='cid:qr_image' style='width: 150px; height: 150px;' alt='Código QR'>
                            </div>
                            <p style='margin: 20px 0; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 8px;'>
                                <strong>Nombre del Evento:</strong> " . str_replace('_', ' ', $nombre_tabla) . "<br>
                                <strong>Fecha de Registro:</strong> " . date('Y-m-d') . "<br>
                                <strong>Ubicación:</strong> Auditorio Principal
                            </p>
                            <p style='text-align: center; color: #666;'>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                            <p style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>© 2024 Brandon Auyon Estudiante ISC. Todos los derechos reservados.</p>
                        </div>
                    </div>";

                $mail->addAttachment($qrFile, 'qr_image.png', PHPMailer::ENCODING_BASE64, 'image/png');
                $mail->addEmbeddedImage($qrFile, 'qr_image');

                $mail->send();
                $_SESSION['success_message'] = "Registro exitoso. Revisa tu correo para obtener los detalles del evento y tu código QR.";
                header("Location: " . $_SERVER['PHP_SELF'] . "?evento=" . urlencode($nombre_tabla));
                exit();
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>Error al enviar el correo: {$mail->ErrorInfo}</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Error al registrar: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
    $verificarEmail->close();
}

$success_message = "";
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro al Evento </title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta name="theme-color" content="#121212">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>

<body>
<link rel="stylesheet" href="css/registro.css">
<div class="page-container">
    <!-- Sección de la imagen -->
    <div class="image-section">
    <div class="logo-container">
        <img src="<?= htmlspecialchars($logo) ?>" alt="Logo del Evento" class="logo-image">
    </div>
    <img src="<?= htmlspecialchars($banner) ?>" alt="Banner del Evento" class="banner-image">
    <div class="text-overlay">
        
        <h1>Bienvenido a Nuestro Evento <?= htmlspecialchars(str_replace('_', ' ', $nombre_tabla)) ?></h1>
        <p></p>
    </div>
</div>

    <!-- Sección del formulario -->
    <div class="form-section">
        <div class="form-container">
            <h2>Registro</h2>
            <!-- Mensaje de éxito -->
            <?php if ($success_message): ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $success_message ?>
                </div>
            <?php endif; ?>
            <!-- Formulario dinámico -->
            <form method="post" class="needs-validation" novalidate>
                <div class="row">
                    <?php foreach ($campos as $campo): ?>
                        <div class="mb-3">
                            <label for="<?= htmlspecialchars($campo['Field']) ?>" class="form-label">
                                <?= ucfirst(str_replace('_', ' ', $campo['Field'])) ?>
                            </label>
                            <?php
                            $inputType = 'text';
                            $additionalAttributes = '';

                            // Configuración dinámica del tipo de campo
                            if (strtolower($campo['Field']) === 'genero') {
                                echo "
                                <select name='" . htmlspecialchars($campo['Field']) . "' class='form-control' required>
                                    <option value=''>Seleccionar</option>
                                    <option value='Hombre'>Hombre</option>
                                    <option value='Mujer'>Mujer</option>
                                    
                                </select>";
                                continue;
                            }

                            if (strpos($campo['Type'], 'int') !== false || stripos($campo['Field'], 'edad') !== false) {
                                $inputType = 'number';
                                $additionalAttributes = 'min="1"';
                            } elseif (strpos($campo['Type'], 'date') !== false) {
                                $inputType = 'date';
                            } elseif (stripos($campo['Field'], 'email') !== false) {
                                $inputType = 'email';
                            }
                            ?>
                            <input type="<?= $inputType ?>" 
                                   name="<?= htmlspecialchars($campo['Field']) ?>" 
                                   class="form-control" 
                                   id="<?= htmlspecialchars($campo['Field']) ?>" 
                                   required
                                   <?= $additionalAttributes ?>>
                            <div class="invalid-feedback">
                                Este campo es requerido.
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" id="terms" required>
    <label class="form-check-label" for="terms">Acepto los términos y condiciones</label>
    <button type="button" class="btn-open" onclick="window.open('terminos', '_blank')">Ver Términos</button>
</div>

                <button type="submit" class="btn btn-submit">Registrar</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<script>
    // Validación del formulario en tiempo real
    (function() {
        'use strict';
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>
</body>
</html>
