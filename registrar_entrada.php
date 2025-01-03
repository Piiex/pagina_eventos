<?php
session_start();
include 'header.php';

include 'db.php';




// Verificar si el evento existe en la URL y es válido
$evento = filter_input(INPUT_GET, 'evento', FILTER_SANITIZE_STRING);
if (!$evento) {
    die("<div class='alert alert-danger'>Evento no especificado o inválido.</div>");
}

// Verificar que la tabla del evento existe en la base de datos
$evento_valido = $conn->prepare("SELECT COUNT(*) FROM configuracion_eventos WHERE tabla = ?");
$evento_valido->bind_param("s", $evento);
$evento_valido->execute();
$evento_valido->bind_result($evento_existe);
$evento_valido->fetch();
$evento_valido->close();

if ($evento_existe === 0) {
    die("<div class='alert alert-danger'>El evento especificado no existe.</div>");
}

// Verificar si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_qr = $_POST['codigo_qr'];
    
    if (!empty($codigo_qr)) {
        // Decodificar JSON del código QR
        $data = json_decode($codigo_qr, true);
        
        // Verificar que el JSON contiene los campos necesarios
        if (isset($data['nombre'], $data['email'], $data['edad'], $data['numero_de_control'])) {
            // Extraer los datos decodificados
            $nombre = $data['nombre'];
            $email = $data['email'];
            $edad = $data['edad'];
            $numerodecontrol = $data['numero_de_control']; // Corregido para coincidir con el QR
            $tipo = "entrada"; // Define el tipo de registro
            
            // Verificación para evitar duplicados en el mismo día
            $sql_check = "SELECT id FROM registros_asistencia 
                          WHERE numerodecontrol = ? 
                          AND evento = ? 
                          AND DATE(fecha_hora) = CURDATE()";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ss", $numerodecontrol, $evento);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                // Insertar los datos en la tabla registros_asistencia si no hay duplicados
                $sql = "INSERT INTO registros_asistencia (evento, nombre, email, created_at, edad, numerodecontrol, tipo, fecha_hora) 
                        VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssisi", $evento, $nombre, $email, $edad, $numerodecontrol, $tipo);
                
                if ($stmt->execute()) {
                    echo "<div class='alert alert-success'>Registro de asistencia exitoso para el evento " . htmlspecialchars($evento) . ".</div>";
                } else {
                    echo "<div class='alert alert-danger'>Error al registrar la asistencia: " . $conn->error . "</div>";
                }
            } else {
                // Mensaje de error si el registro ya existe
                echo "<div class='alert alert-warning'>Ya has registrado tu asistencia para este evento hoy.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>El código QR no contiene datos válidos.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Por favor, escanea un código QR válido.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asistencia - <?= htmlspecialchars($evento) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
            text-align: center;
        }
        #video {
            width: 100%;
            max-width: 400px;
            border: 1px solid #ced4da;
            border-radius: 8px;
        }
        .alert {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Registro de Asistencia para <?= htmlspecialchars(str_replace('_', ' ', $evento)) ?></h2>
    <p>Escanea tu código QR para registrar tu asistencia.</p>

    <!-- Selector de cámaras -->
    <select id="cameraSelect" class="form-select mt-3" style="width: 100%; max-width: 400px;"></select>

    <!-- Vista de la cámara para el escaneo QR -->
    <video id="video" autoplay></video>
    <div id="qr-reader-results"></div>

    <!-- Formulario oculto para enviar el código QR -->
    <form method="post" id="form-entrada">
        <input type="hidden" name="codigo_qr" id="codigo_qr">
    </form>
</div>

<script>
  const codeReader = new ZXing.BrowserQRCodeReader();
const videoElement = document.getElementById('video');
const cameraSelect = document.getElementById('cameraSelect');
const pauseTime = 2000; // Pausa de 2 segundos
let isPaused = false;

// Obtener dispositivos de video
codeReader.getVideoInputDevices()
    .then(videoInputDevices => {
        if (videoInputDevices.length > 0) {
            let defaultCameraId = null;

            // Crear opciones en el selector de cámaras y priorizar la cámara trasera
            videoInputDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.textContent = device.label || `Cámara ${index + 1}`;

                if (device.label.toLowerCase().includes('back')) {
                    defaultCameraId = device.deviceId; // Preferir la cámara trasera
                }

                cameraSelect.appendChild(option);
            });

            // Usar la cámara trasera como predeterminada si está disponible
            defaultCameraId = defaultCameraId || videoInputDevices[0].deviceId; // Si no hay trasera, usar la primera disponible
            cameraSelect.value = defaultCameraId;

            startScanner(defaultCameraId);

            // Cambiar de cámara al seleccionar una nueva
            cameraSelect.addEventListener('change', () => {
                if (!isPaused) {
                    codeReader.reset();
                    startScanner(cameraSelect.value);
                }
            });
        } else {
            document.getElementById("qr-reader-results").innerHTML =
                `<div class="alert alert-danger">No se encontraron cámaras.</div>`;
        }
    })
    .catch(err => {
        console.error("Error al acceder a las cámaras:", err);
        document.getElementById("qr-reader-results").innerHTML =
            `<div class="alert alert-danger">Error al acceder a la cámara: ${err}</div>`;
    });

function startScanner(deviceId) {
    codeReader.decodeFromVideoDevice(deviceId, 'video', (result, error) => {
        if (result && !isPaused) {
            isPaused = true; // Pausar el escáner
            // Código QR detectado, enviarlo al formulario
            document.getElementById('codigo_qr').value = result.text;
            document.getElementById('form-entrada').submit();
            // Mostrar un mensaje de pausa
            document.getElementById("qr-reader-results").innerHTML =
                `<div class="alert alert-success">QR detectado. Esperando...</div>`;
            // Detener el escáner durante la pausa
            setTimeout(() => {
                isPaused = false;
                document.getElementById("qr-reader-results").innerHTML = '';
            }, pauseTime);
        }
        if (error && !(error instanceof ZXing.NotFoundException)) {
            console.error(error);
        }
    });
}

</script>

</body>
</html>
