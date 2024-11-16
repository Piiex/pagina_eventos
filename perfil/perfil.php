<?php
include 'header.php';
include '../db.php';

// Verificar que el usuario está autenticado


// Obtener el ID del usuario actual
$user_id = $_SESSION['user_id'];

// Obtener los datos del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Actualizar la información del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = filter_var($_POST['nombre'], FILTER_SANITIZE_STRING);
    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
    $clave = !empty($_POST['clave']) ? password_hash($_POST['clave'], PASSWORD_DEFAULT) : $user['clave'];
    $estado = filter_var($_POST['estado'], FILTER_SANITIZE_STRING);
    $encargado = filter_var($_POST['encargado'], FILTER_SANITIZE_STRING);
    
    // Actualizar los datos del usuario en la base de datos
    $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ?, clave = ?, estado = ?, encargado = ?, ultima_conexion = NOW() WHERE id = ?");
    $stmt->bind_param("sssssi", $nombre, $correo, $clave, $estado, $encargado, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $successMsg = "Perfil actualizado correctamente.";
    // Refrescar los datos del usuario
    header("Location: perfil.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Usuario</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; margin-top: 50px; }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-control[readonly] { background-color: #e9ecef; }
    </style>
</head>
<body>
<div class="container">
    <h2>Perfil de Usuario</h2>
    <?php if (isset($successMsg)): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($user['nombre']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="correo" class="form-label">Correo Electrónico</label>
            <input type="email" name="correo" id="correo" class="form-control" value="<?= htmlspecialchars($user['correo']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="clave" class="form-label">Contraseña</label>
            <input type="password" name="clave" id="clave" class="form-control" placeholder="Dejar en blanco para mantener la misma contraseña">
        </div>
        <div class="mb-3">
            <label for="estado" class="form-label">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="activo" <?= $user['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= $user['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="encargado" class="form-label">Encargado</label>
            <input type="text" name="encargado" id="encargado" class="form-control" value="<?= htmlspecialchars($user['encargado']) ?>">
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Rol</label>
            <input type="text" name="role" id="role" class="form-control" value="<?= htmlspecialchars($user['role']) ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="permissions" class="form-label">Permisos</label>
            <input type="text" name="permissions" id="permissions" class="form-control" value="<?= htmlspecialchars($user['permissions']) ?>" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Fecha de Creación</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user['fecha_creacion']) ?>" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Última Conexión</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user['ultima_conexion']) ?>" readonly>
        </div>
        <button type="submit" class="btn btn-primary w-100">Actualizar Perfil</button>
    </form>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
