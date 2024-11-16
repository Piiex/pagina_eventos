<?php

include 'db.php';
include 'header.php';


$user_id = $_SESSION['user_id'];

// Obtener tablas de eventos creadas por el usuario en la tabla configuracion_eventos
$event_tables = [];
$result = $conn->query("SELECT tabla FROM configuracion_eventos WHERE user_id = $user_id");
while ($row = $result->fetch_array()) {
    $event_tables[] = $row['tabla'];
}

// Manejar las acciones de habilitar, deshabilitar o eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tabla = filter_var($_POST['tabla'], FILTER_SANITIZE_STRING);
    $accion = filter_var($_POST['accion'], FILTER_SANITIZE_STRING);

    if (in_array($tabla, $event_tables)) {
        if ($accion === 'eliminar') {
            // Eliminar la tabla del evento
            $sql = "DROP TABLE `$tabla`";
            if ($conn->query($sql) === TRUE) {
                echo "<div class='alert alert-success'>Evento eliminado correctamente.</div>";
                // Eliminar de configuracion_eventos
                $conn->query("DELETE FROM configuracion_eventos WHERE tabla = '$tabla'");
                // Eliminar de la lista de tablas
                $event_tables = array_filter($event_tables, fn($t) => $t !== $tabla);
            } else {
                echo "<div class='alert alert-danger'>Error al eliminar el evento: " . $conn->error . "</div>";
            }
        } elseif ($accion === 'habilitar') {
            // Habilitar el evento
            $conn->query("UPDATE configuracion_eventos SET estado = 'habilitado' WHERE tabla = '$tabla'");
            echo "<div class='alert alert-success'>Evento habilitado correctamente.</div>";
        } elseif ($accion === 'deshabilitar') {
            // Deshabilitar el evento
            $conn->query("UPDATE configuracion_eventos SET estado = 'deshabilitado' WHERE tabla = '$tabla'");
            echo "<div class='alert alert-warning'>Evento deshabilitado correctamente.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Eventos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos personalizados para una apariencia profesional */
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        h2 {
            margin-bottom: 30px;
            text-align: center;
            font-weight: bold;
            color: #343a40;
        }
        .table {
            background-color: #fff;
        }
        .table th, .table td {
            vertical-align: middle !important;
        }
        .btn-sm {
            margin-right: 5px;
        }
        .alert {
            margin-top: 20px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Eventos Creados</h2>

    <?php if (empty($event_tables)): ?>
        <div class="alert alert-info text-center">No hay eventos creados.</div>
    <?php else: ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre del Evento</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($event_tables as $tabla): ?>
                    <?php
                    // Consultar el estado del evento
                    $estado_result = $conn->query("SELECT estado FROM configuracion_eventos WHERE tabla = '$tabla' AND user_id = $user_id");
                    $estado = $estado_result->fetch_assoc()['estado'] ?? 'habilitado';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(str_replace('_evento', '', $tabla)) ?></td>
                        <td><?= ucfirst($estado) ?></td>
                        <td class="text-center">
                            <div class="actions">
                                <a href="administrar_evento?evento=<?= urlencode($tabla) ?>" class="btn btn-primary btn-sm">Administrar</a>
                                <a href="ver_registro?evento=<?= urlencode($tabla) ?>" class="btn btn-info btn-sm">Ver Registro</a>
                                <a href="registrar_entrada?evento=<?= urlencode($tabla) ?>" class="btn btn-success btn-sm">Registrar Entrada</a>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="tabla" value="<?= htmlspecialchars($tabla) ?>">
                                    <?php if ($estado === 'habilitado'): ?>
                                        <input type="hidden" name="accion" value="deshabilitar">
                                        <button type="submit" class="btn btn-warning btn-sm">Deshabilitar</button>
                                    <?php else: ?>
                                        <input type="hidden" name="accion" value="habilitar">
                                        <button type="submit" class="btn btn-success btn-sm">Habilitar</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="tabla" value="<?= htmlspecialchars($tabla) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar este evento?')">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>

