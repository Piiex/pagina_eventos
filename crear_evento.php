<?php

include 'header.php';
include 'db.php';


$user_id = $_SESSION['user_id'];  // Obtener el ID del usuario de la sesión

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $nombre_evento = filter_var($_POST['nombre_evento'], FILTER_SANITIZE_STRING);
    $campos = $_POST['campos'] ?? [];

    // Agregar el campo de email obligatoriamente
    $campos[] = [
        'nombre' => 'email',
        'tipo' => 'VARCHAR(255)'
    ];

    // Crear un nombre de tabla seguro para el evento
    $nombre_tabla = strtolower(preg_replace('/\s+/', '_', $nombre_evento . '_evento'));

    // Construir la consulta SQL para crear la tabla con los campos definidos por el usuario
    $sql = "CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
                id INT AUTO_INCREMENT PRIMARY KEY";

    foreach ($campos as $campo) {
        $nombre_campo = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($campo['nombre']));
        $tipo_campo = strtoupper($campo['tipo']);
        $sql .= ", `$nombre_campo` $tipo_campo";
    }
    $sql .= ", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)";

    // Intentar crear la tabla
    if ($conn->query($sql) === TRUE) {
        // Subir el logo si está presente
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoDir = 'logos/';
            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0777, true);
            }
            $logoName = basename($_FILES['logo']['name']);
            $logoPath = $logoDir . uniqid() . '_' . $logoName;

            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                $error_message = "Error al subir el logo.";
            }
        }

        // Guardar la configuración del evento en `configuracion_eventos`
        $sql_config = "INSERT INTO configuracion_eventos (tabla, estado, logo, user_id) VALUES (?, 'habilitado', ?, ?)";
        $stmt_config = $conn->prepare($sql_config);
        $stmt_config->bind_param("ssi", $nombre_tabla, $logoPath, $user_id);
        
        if ($stmt_config->execute()) {
            // Redirigir a la página de administración del evento
            header("Location: administrar_evento?evento=" . urlencode($nombre_tabla));
            exit();
        } else {
            $error_message = "Error al guardar la configuración del evento: " . $conn->error;
        }
    } else {
        $error_message = "Error al crear el evento: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Evento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Crear Nuevo Evento</h2>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <form method="post" action="crear_evento" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="nombre_evento" class="form-label">Nombre del Evento</label>
            <input type="text" name="nombre_evento" id="nombre_evento" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="logo" class="form-label">Logo del Evento</label>
            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
        </div>

        <h5>Definir Campos del Formulario de Registro</h5>
        <div id="campos-container">
            <div class="campo">
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" name="campos[0][nombre]" class="form-control" placeholder="Nombre del Campo" required>
                    </div>
                    <div class="col-md-6">
                        <select name="campos[0][tipo]" class="form-control" required>
                            <option value="VARCHAR(255)">Texto</option>
                            <option value="INT">Número</option>
                            <option value="DATE">Fecha</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-secondary mt-3" onclick="agregarCampo()">Agregar Campo</button>
        <button type="submit" class="btn btn-primary mt-3">Crear Evento</button>
    </form>
</div>

<script>
let campoIndex = 1;

function agregarCampo() {
    const container = document.getElementById('campos-container');
    const newCampo = document.createElement('div');
    newCampo.classList.add('campo');
    newCampo.innerHTML = `
        <div class="row mt-3">
            <div class="col-md-6">
                <input type="text" name="campos[${campoIndex}][nombre]" class="form-control" placeholder="Nombre del Campo" required>
            </div>
            <div class="col-md-6">
                <select name="campos[${campoIndex}][tipo]" class="form-control" required>
                    <option value="VARCHAR(255)">Texto</option>
                    <option value="INT">Número</option>
                    <option value="DATE">Fecha</option>
                </select>
            </div>
        </div>`;
    container.appendChild(newCampo);
    campoIndex++;
}
</script>
<?php include 'footer.php'; ?>
