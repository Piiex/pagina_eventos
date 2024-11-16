<?php
session_start();
include '../db.php';
require '../vendor/autoload.php'; // Cargar el autoload de Composer

use Dompdf\Dompdf;
use Dompdf\Options;

// Obtener el evento específico desde la URL
$nombre_tabla = filter_input(INPUT_GET, 'evento', FILTER_SANITIZE_STRING);

if (!$nombre_tabla) {
    die("Evento no especificado.");
}

// Consultar configuración del evento para obtener el banner y logo
$stmtConfig = $conn->prepare("SELECT banner, logo FROM configuracion_eventos WHERE tabla = ?");
$stmtConfig->bind_param("s", $nombre_tabla);
$stmtConfig->execute();
$resultConfig = $stmtConfig->get_result();
$config = $resultConfig->fetch_assoc();
$banner = $config['banner'];
$logo = $config['logo'];

// Consultar registros de asistencia para el evento actual
$sql = "SELECT * FROM registros_asistencia WHERE evento = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombre_tabla);
$stmt->execute();
$result = $stmt->get_result();

// Iniciar el contenido HTML para el PDF
$html = '
    <div style="text-align: center; margin-bottom: 20px;">';
if ($banner) {
    $html .= '<img src="../' . htmlspecialchars($banner) . '" alt="Banner" style="width: 100%; max-height: 200px; object-fit: cover;">';
}
$html .= '</div>';

$html .= '
    <div style="text-align: center; margin-bottom: 20px;">';
if ($logo) {
    $html .= '<img src="../' . htmlspecialchars($logo) . '" alt="Logo" style="width: 100px; height: 100px; object-fit: contain;">';
}
$html .= '</div>';

$html .= '
    <h2 style="text-align: center;">Registros de Asistencia para el Evento: ' . htmlspecialchars(str_replace('_', ' ', $nombre_tabla)) . '</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Evento</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Fecha de Registro</th>
                <th>Edad</th>
                <th>Número de Control</th>
              
                <th>Fecha y Hora de Asistencia</th>
            </tr>
        </thead>
        <tbody>';

// Agregar filas de registros al contenido HTML
while ($row = $result->fetch_assoc()) {
    $html .= '
        <tr>
            <td>' . htmlspecialchars($row['id']) . '</td>
            <td>' . htmlspecialchars($row['evento']) . '</td>
            <td>' . htmlspecialchars($row['nombre']) . '</td>
            <td>' . htmlspecialchars($row['email']) . '</td>
            <td>' . htmlspecialchars($row['created_at']) . '</td>
            <td>' . htmlspecialchars($row['edad']) . '</td>
            <td>' . htmlspecialchars($row['numerodecontrol']) . '</td>
          
            <td>' . htmlspecialchars($row['fecha_hora']) . '</td>
        </tr>';
}

$html .= '
        </tbody>
    </table>';

// Configurar Dompdf y opciones
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Opcional) Configurar el tamaño y orientación de la página
$dompdf->setPaper('A4', 'landscape');

// Renderizar el PDF
$dompdf->render();

// Enviar el PDF como descarga
$dompdf->stream("Registros_Asistencia_" . $nombre_tabla . ".pdf", ["Attachment" => true]);
exit;
?>
