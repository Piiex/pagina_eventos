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
$estado_result = $conn->query("SELECT estado, logo, banner, smtp_host,descripccion, smtp_port, smtp_user, smtp_password FROM configuracion_eventos WHERE tabla = '$nombre_tabla'");
$evento_config = $estado_result->fetch_assoc();
$estado = $evento_config['estado'] ?? 'habilitado';
$logo = $evento_config['logo'] ?? '';
$banner = $evento_config['banner'] ?? '';
$descripccion = $evento_config['descripccion'] ?? '';
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
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; color: #333; border: 1px solid #ddd; border-radius: 8px;'>
    <!-- Encabezado -->
    <div style='background-color: #007bff; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
        <h2 style='margin: 0; font-size: 24px;'>¡Registro Confirmado!</h2>
        <p style='margin: 5px 0;'>Gracias por registrarte en nuestro evento.</p>
    </div>
    <!-- Contenido principal -->
    <div style='padding: 20px;'>
        <h3 style='font-size: 20px; color: #007bff;'>Detalles del Evento</h3>
        <p>Estimado/a <strong>{$data['nombre']}</strong>,</p>
        <p>Nos complace confirmarte que tu registro ha sido exitoso. A continuación, encontrarás tu código QR para ingresar al evento:</p>
        <div style='text-align: center; margin: 20px 0;'>
            <img src='cid:qr_image' style='width: 150px; height: 150px;' alt='Código QR'>
        </div>
        <p style='margin: 15px 0; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 8px;'>
            <strong>Nombre del Evento:</strong> " . str_replace('_', ' ', $nombre_tabla) . "<br>
            <strong>Fecha de Registro:</strong> " . date('Y-m-d') . "<br>
            
            <strong>Descripción:</strong> {$evento_config['descripccion']}

        </p>
        <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
        <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
    </div>
    <!-- Pie de página -->
    <div style='background-color: #f1f1f1; color: #666; text-align: center; padding: 10px; border-radius: 0 0 8px 8px;'>
        <p style='margin: 0; font-size: 12px;'>© 2024 Brandon Auyon Estudiante ISC. Todos los derechos reservados.</p>
        <p style='margin: 5px 0; font-size: 12px;'>Puedes responder este correo para más información.</p>
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
function corregirTexto($texto) {
    // Lista de correcciones comunes
    $correcciones = [
        'tecnologa' => 'tecnología',
        'informtica' => 'informática',
        'programacin' => 'programación',
        'ingeniera' => 'ingeniería',
        // Agrega más correcciones según sea necesario
    ];

    // No corregir palabras entre comillas
    preg_match_all('/"([^"]*)"/', $texto, $exclusiones);
    foreach ($exclusiones[1] as $excluido) {
        $texto = str_replace('"' . $excluido . '"', '##EXCLUIR_' . $excluido . '_##', $texto);
    }

    // Aplicar correcciones
    foreach ($correcciones as $mal => $bien) {
        $texto = str_ireplace($mal, $bien, $texto);
    }

    // Restaurar palabras excluidas
    foreach ($exclusiones[1] as $excluido) {
        $texto = str_replace('##EXCLUIR_' . $excluido . '_##', '"' . $excluido . '"', $texto);
    }

    return $texto;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro al Evento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        /* Fondo animado */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: url('https://fondosmil.co/fondo/17010.jpg') center/cover no-repeat fixed;
            color: #333;
        }

        .page-container {
            width: 100%;
            max-width: 900px;
            background: rgba(255, 255, 255, 0.9); /* Transparencia */
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            position: relative;
            animation: fadeIn 1s ease;
            padding: 30px;
        }

        .image-section {
            position: relative;
            text-align: center;
            margin-bottom: 30px;
        }

        .banner-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 15px;
            filter: brightness(0.8);
        }

        .logo-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #ffffff;
            border-radius: 90%;
            padding: 10px;
            box-shadow: 0 10px 10px rgba(0, 0, 0, 0.2);
        }

        .logo-image {
            width: 70px;
            height: 70px;
            border-radius: 50%;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 500;
            text-align: center;
            margin-top: 20px;
            color: #333;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 400;
            text-align: center;
            color: #4A4A4A;
            margin-bottom: 20px;
        }

        .form-container {
            margin-top: 20px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            font-size: 1rem;
            color: #333;
        }

        .form-control:focus {
            border-color: #4285f4;
            box-shadow: 0 0 5px rgba(66, 133, 244, 0.5);
        }

        .btn-submit {
            display: block;
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            color: #ffffff;
            background-color: #4285f4;
            border: none;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-submit:hover {
            background-color: #357ae8;
            transform: translateY(-2px);
        }

        .form-check-label {
            margin-left: 8px;
            font-size: 0.9rem;
            color: #555;
        }

        .btn-open {
            background: none;
            border: none;
            color: #4285f4;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 1.5rem;
            }

            h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
<div class="page-container">
    <!-- Sección del encabezado -->
    <div class="image-section">
        <img src="<?= htmlspecialchars($banner) ?>" alt="Banner del Evento" class="banner-image">
        <div class="logo-container">
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo del Evento" class="logo-image">
        </div>
    </div>

    <h1>Registro al Evento</h1>
    <h2><?= htmlspecialchars(strtoupper(str_replace('_', ' ', corregirTexto($nombre_tabla)))) ?></h2>

    <!-- Sección del formulario -->
    <div class="form-container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= $success_message ?>
            </div>
        <?php endif; ?>
        <form method="post" class="needs-validation" novalidate>
            <?php foreach ($campos as $campo): ?>
                <div class="mb-3">
                    <label for="<?= htmlspecialchars($campo['Field']) ?>" class="form-label">
                        <?= ucfirst(str_replace('_', ' ', $campo['Field'])) ?>
                    </label>
                    <?php
                    $inputType = 'text';
                    $additionalAttributes = '';

                    // Si el campo es género o sexo, crea un select con opciones específicas
                    if (in_array(strtolower($campo['Field']), ['genero', 'sexo'])) {
                        echo "
                        <select name='" . htmlspecialchars($campo['Field']) . "' class='form-control' required>
                            <option value=''>Seleccionar</option>
                            <option value='Hombre'>Hombre</option>
                            <option value='Mujer'>Mujer</option>
                        </select>";
                        continue;
                    }

                    // Si el campo es edad o años, aplica restricciones coherentes
                    if (in_array(strtolower($campo['Field']), ['edad', 'años'])) {
                        $inputType = 'number';
                        $additionalAttributes = 'min="1" max="120"';
                    }

                    // Detectar otros tipos de campos
                    if (strpos($campo['Type'], 'int') !== false && !in_array(strtolower($campo['Field']), ['edad', 'años'])) {
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
                </div>
            <?php endforeach; ?>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="terms" required>
                <label class="form-check-label" for="terms">Acepto los términos y condiciones</label>
                <button type="button" class="btn-open" onclick="window.open('terminos', '_blank')">Ver Términos</button>
            </div>
            <button type="submit" class="btn btn-submit">Registrar</button>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        'use strict';
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
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
