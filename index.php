<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// Manejo del formulario de inicio de sesión
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'];
    $clave = $_POST['clave'];

    // Consulta para verificar el usuario
    $stmt = $conn->prepare("SELECT id, nombre, clave, role FROM usuarios WHERE correo = ? AND estado = 'activo'");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->bind_result($id, $nombre, $hashed_password, $role);
    $stmt->fetch();
    $stmt->close();

    // Verificación de la contraseña
    if ($hashed_password && password_verify($clave, $hashed_password)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['role'] = $role;
        header("Location: dashboard.php"); // Redirige al dashboard
        exit();
    } else {
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-20px);
            opacity: 0;
            animation: fadeInUp 0.7s forwards;
        }
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .form-floating {
            margin-bottom: 1.5rem;
        }
        .login-btn {
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            background-color: #667eea;
            border: none;
            color: #fff;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .login-btn:hover {
            background-color: #5563d9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .alert-danger {
            margin-top: 1rem;
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-floating">
                <input type="email" class="form-control" id="correo" name="correo" placeholder="Correo" required>
                <label for="correo">Correo electrónicoooooooooooooooooooooo</label>
            </div>
            <div class="form-floating">
                <input type="password" class="form-control" id="clave" name="clave" placeholder="Contraseña" required>
                <label for="clave">Contraseña</label>
            </div>
            <button type="submit" class="btn login-btn">Iniciar Sesión</button>
        </form>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
