<?php
session_start();
include '../db.php';

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Obtener la plantilla y el usuario en sesión
$plantilla = $_GET['plantilla'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

$uploadDir = '../documentos/uploads/';  // Ruta correcta para las plantillas subidas
$plantillaPath = $uploadDir . $plantilla;  // Construimos la ruta completa

if (!$plantilla || !file_exists($plantillaPath)) {
    die("La plantilla seleccionada no existe.");
}

// Consultar registros de asistencia
$query = "SELECT * FROM registros_asistencia";
$result = $conn->query($query);

// Determinar tipo de archivo de la plantilla
$fileType = strtolower(pathinfo($plantillaPath, PATHINFO_EXTENSION));

if ($fileType === 'xls' || $fileType === 'xlsx') {
    // Exportación en plantilla de Excel
    $spreadsheet = IOFactory::load($plantillaPath);
    $sheet = $spreadsheet->getActiveSheet();

    $rowIndex = 2;
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue("A$rowIndex", $row['evento']);
        $sheet->setCellValue("B$rowIndex", $row['nombre']);
        $sheet->setCellValue("C$rowIndex", $row['email']);
        $sheet->setCellValue("D$rowIndex", $row['created_at']);
        $sheet->setCellValue("E$rowIndex", $row['edad']);
        $sheet->setCellValue("F$rowIndex", $row['numerodecontrol']);
        $sheet->setCellValue("G$rowIndex", $row['tipo']);
        $sheet->setCellValue("H$rowIndex", $row['fecha_hora']);
        $rowIndex++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="AsistenciaConPlantilla.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit();
} elseif ($fileType === 'pdf') {
    // Exportación en plantilla de PDF
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');
    
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Registros de Asistencia');
    $pdf->AddPage();

    // Cargar plantilla PDF
    $pdf->setSourceFile($plantillaPath);
    $templateId = $pdf->importPage(1);
    $pdf->useTemplate($templateId, 0, 0, 210);

    // Insertar datos
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetXY(10, 80);
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(30, 10, $row['evento'], 0, 0);
        $pdf->Cell(30, 10, $row['nombre'], 0, 0);
        $pdf->Cell(30, 10, $row['email'], 0, 0);
        $pdf->Cell(30, 10, $row['created_at'], 0, 1);
        $pdf->SetX(10);
    }

    $pdf->Output('AsistenciaConPlantilla.pdf', 'I');
    exit();
} else {
    die("Formato de plantilla no soportado.");
}
?>
