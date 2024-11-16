<?php
include 'header.php';
include '../db.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar que se haya proporcionado un ID de documento
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de documento inválido.");
}

$user_id = $_SESSION['user_id'];
$documento_id = (int)$_GET['id'];

// Obtener la información del documento para validar y eliminar el archivo
$stmt = $conn->prepare("SELECT file_path FROM documentos WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $documento_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $documento = $result->fetch_assoc();
    $filePath = $documento['file_path'];

    // Intentar eliminar el archivo físico
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Eliminar la entrada de la base de datos
    $deleteStmt = $conn->prepare("DELETE FROM documentos WHERE id = ? AND user_id = ?");
    $deleteStmt->bind_param("ii", $documento_id, $user_id);
    if ($deleteStmt->execute()) {
        header("Location: documentos.php?success=Documento eliminado correctamente.");
        exit();
    } else {
        die("Error al eliminar el documento de la base de datos.");
    }
} else {
    die("Documento no encontrado o no tiene permisos para eliminarlo.");
}
?>
