<?php
include 'header.php';
include '../db.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['documento'];
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uploadDir = 'documentos/';
        $newFileName = uniqid() . '_' . basename($fileName);
        $filePath = $uploadDir . $newFileName;

        // Validar tipo de archivo y tamaño
        if ($fileExt !== 'pdf') {
            $error_message = "Solo se permiten archivos PDF.";
        } elseif ($fileSize > 5 * 1024 * 1024) { // Limitar a 5 MB
            $error_message = "El archivo es demasiado grande. Tamaño máximo: 5 MB.";
        } else {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (move_uploaded_file($fileTmp, $filePath)) {
                // Guardar detalles en la base de datos
                $stmt = $conn->prepare("INSERT INTO documentos (user_id, file_name, file_path, file_type, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
                $fileType = "PDF";
                $stmt->bind_param("isss", $user_id, $fileName, $filePath, $fileType);
                if ($stmt->execute()) {
                    $success_message = "El archivo se ha subido correctamente.";
                } else {
                    $error_message = "Error al guardar en la base de datos.";
                }
                $stmt->close();
            } else {
                $error_message = "Error al mover el archivo.";
            }
        }
    } else {
        $error_message = "Por favor selecciona un archivo válido.";
    }
}

// Obtener archivos existentes
$archivos = $conn->query("SELECT id, file_name, uploaded_at FROM documentos WHERE user_id = $user_id ORDER BY uploaded_at DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Documentos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Gestión de Documentos</h2>

    <!-- Mensajes de éxito y error -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Formulario de subida -->
    <form method="post" enctype="multipart/form-data" class="mb-4">
        <div class="mb-3">
            <label for="documento" class="form-label">Subir Documento (Solo PDF)</label>
            <input type="file" name="documento" id="documento" class="form-control" accept="application/pdf" required>
        </div>
        <button type="submit" class="btn btn-primary">Subir Documento</button>
    </form>

    <!-- Tabla de documentos existentes -->
    <h3>Documentos Subidos</h3>
    <table class="table table-bordered">
        <thead class="table-dark">
        <tr>
            <th>Nombre del Archivo</th>
            <th>Fecha de Subida</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($archivos->num_rows > 0): ?>
            <?php while ($archivo = $archivos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($archivo['file_name']) ?></td>
                    <td><?= htmlspecialchars($archivo['uploaded_at']) ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($archivo['file_path']) ?>" target="_blank" class="btn btn-info btn-sm">Ver</a>
                        <a href="eliminar_documento.php?id=<?= $archivo['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar este documento?');">Eliminar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">No hay documentos subidos.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
