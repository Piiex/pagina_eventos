<?php

include 'header.php';
include 'db.php';

// Verificar si el usuario ha iniciado sesión
$user_id = $_SESSION['user_id'] ?? null;
$nombre_tabla = filter_var($_GET['evento'], FILTER_SANITIZE_STRING);

// Verificar que el evento especificado está vinculado al usuario
$stmt = $conn->prepare("SELECT * FROM configuracion_eventos WHERE tabla = ? AND user_id = ?");
$stmt->bind_param("si", $nombre_tabla, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("El evento no existe o no está vinculado a su cuenta.");
}

// Obtener todas las plantillas disponibles para el usuario actual
$plantillasResult = $conn->prepare("SELECT * FROM documentos WHERE user_id = ? AND file_type IN ('xls', 'xlsx', 'pdf')");
$plantillasResult->bind_param("i", $user_id);
$plantillasResult->execute();
$plantillas = $plantillasResult->get_result();

// Consultar solo los registros de asistencia para el evento actual
$sql = "SELECT * FROM registros_asistencia WHERE evento = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombre_tabla);
$stmt->execute();
$result = $stmt->get_result();

// Preparar datos para gráficos
$eventos = [];
$edades = [];
$horas_entrada = [];

while ($row = $result->fetch_assoc()) {
    $eventos[$row['evento']] = ($eventos[$row['evento']] ?? 0) + 1;
    $edades[$row['edad']] = ($edades[$row['edad']] ?? 0) + 1;
    $hora = date('H:00', strtotime($row['fecha_hora']));
    $horas_entrada[$hora] = ($horas_entrada[$hora] ?? 0) + 1;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Asistencia</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            margin-top: 50px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Registros de Asistencia para el Evento: <?= htmlspecialchars(str_replace('_', ' ', $nombre_tabla)) ?></h2>

    <!-- Selector de plantillas para exportación -->
    <form method="GET" action="export/export_with_template.php" class="d-flex align-items-center mb-4">
        <label for="plantilla" class="me-2">Selecciona una plantilla para exportar:</label>
        <select name="plantilla" id="plantilla" class="form-select me-2" required>
            <?php while ($plantilla = $plantillas->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($plantilla['file_path']) ?>"><?= htmlspecialchars($plantilla['file_name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-success">Exportar con Plantilla</button>
    </form>

    
 <!-- Botones de exportación -->
<div class="d-flex justify-content-end mb-3">
    <a href="export/export_excel.php?evento=<?= urlencode($nombre_tabla) ?>" class="btn btn-success me-2">Exportar a Excel</a>
    <a href="export/export_pdf.php?evento=<?= urlencode($nombre_tabla) ?>" class="btn btn-danger">Exportar a PDF</a>
</div>


    <!-- Tabla de Registros -->
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Evento</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Fecha de Registro</th>
                <th>Edad</th>
                <th>Número de Control</th>
                <th>Tipo</th>
                <th>Fecha y Hora de Asistencia</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php foreach ($result as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['evento']) ?></td>
                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                        <td><?= htmlspecialchars($row['edad']) ?></td>
                        <td><?= htmlspecialchars($row['numerodecontrol']) ?></td>
                        <td><?= htmlspecialchars($row['tipo']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_hora']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" class="text-center">No hay registros de asistencia para este evento.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Gráficos -->
    <div class="mt-5">
        <h4>Visualización de Datos</h4>

        <!-- Gráfico de Eventos -->
        <canvas id="eventosChart" class="mt-4"></canvas>

        <!-- Gráfico de Edades -->
        <canvas id="edadesChart" class="mt-4"></canvas>

        <!-- Gráfico de Horas de Entrada -->
        <canvas id="horasChart" class="mt-4"></canvas>
    </div>
</div>

<script>
    // Datos de PHP a JavaScript
    const eventosData = <?= json_encode($eventos) ?>;
    const edadesData = <?= json_encode($edades) ?>;
    const horasEntradaData = <?= json_encode($horas_entrada) ?>;

    // Gráfico de Eventos
    new Chart(document.getElementById('eventosChart'), {
        type: 'bar',
        data: {
            labels: Object.keys(eventosData),
            datasets: [{
                label: 'Asistentes por Evento',
                data: Object.values(eventosData)
            }]
        }
    });

    // Gráfico de Edades
    new Chart(document.getElementById('edadesChart'), {
        type: 'pie',
        data: {
            labels: Object.keys(edadesData),
            datasets: [{
                label: 'Distribución de Edades',
                data: Object.values(edadesData)
            }]
        }
    });

    // Gráfico de Horas de Entrada
    new Chart(document.getElementById('horasChart'), {
        type: 'line',
        data: {
            labels: Object.keys(horasEntradaData),
            datasets: [{
                label: 'Entradas por Hora',
                data: Object.values(horasEntradaData)
            }]
        }
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>

