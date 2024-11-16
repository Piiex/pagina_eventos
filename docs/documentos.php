<?php
include 'header.php';
include '../db.php';

// Manejar la carga de la plantilla
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_plantilla = filter_var($_POST['nombre_plantilla'], FILTER_SANITIZE_STRING);
    $logo = $_FILES['logo'];
    $encabezado = filter_var($_POST['encabezado'], FILTER_SANITIZE_STRING);
    $campos = $_POST['campos'];
    $uploadDir = 'plantillas/';

    // Subir el logo
    if ($logo['error'] === UPLOAD_ERR_OK) {
        $logoPath = $uploadDir . uniqid() . '_' . basename($logo['name']);
        move_uploaded_file($logo['tmp_name'], $logoPath);
    } else {
        $logoPath = ''; // Sin logo en caso de error
    }

    // Guardar los detalles de la plantilla en la base de datos
    $stmt = $conn->prepare("INSERT INTO plantillas (nombre, logo, encabezado, campos, created_at) VALUES (?, ?, ?, ?, NOW())");
    $campos_json = json_encode($campos);
    $stmt->bind_param("ssss", $nombre_plantilla, $logoPath, $encabezado, $campos_json);
    $stmt->execute();
    $stmt->close();
    $successMsg = "Plantilla creada exitosamente.";
}

// Obtener todas las plantillas existentes
$plantillasResult = $conn->query("SELECT * FROM plantillas ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Plantillas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Generador de Plantillas para Asistencia</h2>

    <?php if (isset($successMsg)): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>

    <!-- Formulario para crear una nueva plantilla -->
    <form method="post" enctype="multipart/form-data" class="mb-4">
        <div class="mb-3">
            <label for="nombre_plantilla" class="form-label">Nombre de la Plantilla</label>
            <input type="text" name="nombre_plantilla" id="nombre_plantilla" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="logo" class="form-label">Logo (opcional)</label>
            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
        </div>
        
        <div class="mb-3">
            <label for="encabezado" class="form-label">Encabezado de la Plantilla</label>
            <textarea name="encabezado" id="encabezado" class="form-control" rows="3" required></textarea>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Campos de Datos</label>
            <div id="campos-container">
                <input type="text" name="campos[]" class="form-control mb-2" placeholder="Nombre del campo (Ej: Nombre, Edad)" required>
            </div>
            <button type="button" class="btn btn-secondary mt-2" onclick="agregarCampo()">Agregar Campo</button>
        </div>
        
        <button type="submit" class="btn btn-primary mt-3">Crear Plantilla</button>
    </form>

    <!-- Mostrar plantillas existentes -->
    <h4 class="mt-5">Plantillas Existentes</h4>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Encabezado</th>
                <th>Fecha de Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $plantillasResult->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                    <td><?= htmlspecialchars($row['encabezado']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <button onclick="viewTemplate('<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>')" class="btn btn-info btn-sm">Vista Previa</button>
                        <a href="eliminar_plantilla.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar esta plantilla?');">Eliminar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Modal para vista previa -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Vista Previa de la Plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
    function agregarCampo() {
        const container = document.getElementById('campos-container');
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'campos[]';
        input.className = 'form-control mb-2';
        input.placeholder = 'Nombre del campo (Ej: Nombre, Edad)';
        container.appendChild(input);
    }

    function viewTemplate(data) {
        const plantilla = JSON.parse(data);
        const previewContent = document.getElementById('previewContent');
        
        previewContent.innerHTML = `
            <h5>${plantilla.nombre}</h5>
            <p><strong>Encabezado:</strong> ${plantilla.encabezado}</p>
            <p><strong>Campos:</strong> ${JSON.parse(plantilla.campos).join(', ')}</p>
            ${plantilla.logo ? `<img src="../${plantilla.logo}" alt="Logo" style="max-width: 100px;">` : ''}
        `;
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    }
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
