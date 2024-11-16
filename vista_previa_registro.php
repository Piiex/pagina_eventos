<?php
include 'db.php';
session_start();

// Verificación y obtención de la configuración del evento
$nombre_tabla = filter_var($_GET['evento'], FILTER_SANITIZE_STRING);
$result = $conn->query("SELECT estado, logo FROM configuracion_eventos WHERE tabla = '$nombre_tabla'");
$evento_config = $result->fetch_assoc();
$estado = $evento_config['estado'] ?? 'habilitado';
$logo = $evento_config['logo'] ?? '';



// Obtener los campos del formulario del evento
$columnas = $conn->query("DESCRIBE $nombre_tabla");
$campos = [];
while ($columna = $columnas->fetch_assoc()) {
    if ($columna['Field'] !== 'id' && $columna['Field'] !== 'created_at') {
        $campos[] = $columna;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
        }
        .logo-container img {
            max-width: 100px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <?php if ($logo): ?>
        <div class="logo-container text-center">
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo del Evento">
        </div>
    <?php endif; ?>
    <h4 class="text-center">Registro al Evento: <?= htmlspecialchars(str_replace('_', ' ', $nombre_tabla)) ?></h4>
    <form>
        <?php foreach ($campos as $campo): ?>
            <div class="mb-3">
                <label class="form-label"><?= ucfirst(str_replace('_', ' ', $campo['Field'])) ?></label>
                <input type="text" class="form-control" placeholder="<?= $campo['Type'] ?>" disabled>
            </div>
        <?php endforeach; ?>
    </form>
</div>
</body>
</html>
