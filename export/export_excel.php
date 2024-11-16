<?php
session_start();
include '../db.php';
require '../vendor/autoload.php'; // Autoload de Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

// Obtener el evento específico desde la URL
$nombre_tabla = filter_input(INPUT_GET, 'evento', FILTER_SANITIZE_STRING);

if (!$nombre_tabla) {
    die("Evento no especificado.");
}

// Consultar registros de asistencia para el evento actual
$sql = "SELECT * FROM registros_asistencia WHERE evento = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombre_tabla);
$stmt->execute();
$result = $stmt->get_result();

// Crear archivo Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Establecer encabezados de columnas
$sheet->setCellValue("A1", "ID");
$sheet->setCellValue("B1", "Evento");
$sheet->setCellValue("C1", "Nombre");
$sheet->setCellValue("D1", "Email");
$sheet->setCellValue("E1", "Fecha de Registro");
$sheet->setCellValue("F1", "Edad");
$sheet->setCellValue("G1", "Número de Control");
$sheet->setCellValue("H1", "Tipo");
$sheet->setCellValue("I1", "Fecha y Hora de Asistencia");

// Rellenar los datos de la tabla en el archivo Excel
$rowNumber = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A" . $rowNumber, $row['id']);
    $sheet->setCellValue("B" . $rowNumber, $row['evento']);
    $sheet->setCellValue("C" . $rowNumber, $row['nombre']);
    $sheet->setCellValue("D" . $rowNumber, $row['email']);
    $sheet->setCellValue("E" . $rowNumber, $row['created_at']);
    $sheet->setCellValue("F" . $rowNumber, $row['edad']);
    $sheet->setCellValue("G" . $rowNumber, $row['numerodecontrol']);
    $sheet->setCellValue("H" . $rowNumber, $row['tipo']);
    $sheet->setCellValue("I" . $rowNumber, $row['fecha_hora']);
    $rowNumber++;
}

// Configurar nombre del archivo y tipo de salida
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Registros_Asistencia_' . $nombre_tabla . '.xls"');
header('Cache-Control: max-age=0');

$writer = new Xls($spreadsheet);
$writer->save('php://output');
exit;
?>
