<?php
include 'header.php';
// Verificar si el usuario está autenticado

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .card {
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }
        .icon-lg {
            font-size: 2.5rem;
            color: #4b4b4b;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1 class="mb-4 text-center">Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>
        
        <div class="row g-4">
            <!-- Generar Evento -->
            <div class="col-md-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="icon-lg mb-3"><i class="bi bi-calendar-plus"></i></div>
                        <h5 class="card-title">Generar Evento</h5>
                        <p class="card-text">Crear un nuevo evento con fecha y detalles.</p>
                        <a href="crear_evento" class="btn btn-primary">Crear Evento</a>
                    </div>
                </div>
            </div>

            <!-- Ver Eventos -->
            <div class="col-md-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="icon-lg mb-3"><i class="bi bi-calendar-check"></i></div>
                        <h5 class="card-title">Ver Eventos</h5>
                        <p class="card-text">Consultar todos los eventos creados.</p>
                        <a href="ver_eventos" class="btn btn-primary">Ver Eventos</a>
                    </div>
                </div>
            </div>

            <!-- Perfil -->
            <div class="col-md-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="icon-lg mb-3"><i class="bi bi-person-circle"></i></div>
                        <h5 class="card-title">Perfil</h5>
                        <p class="card-text">Ver y editar información de perfil.</p>
                        <a href="perfil/perfil.php" class="btn btn-primary">Editar Perfil</a>
                    </div>
                </div>
            </div>

            <!-- Documentos -->
            <div class="col-md-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="icon-lg mb-3"><i class="bi bi-folder"></i></div>
                        <h5 class="card-title">Documentos</h5>
                        <p class="card-text">Sube y gestiona tus documentos.</p>
                        <a href="docs/documentos.php" class="btn btn-primary">Ir a Documentos</a>
                    </div>
                </div>
            </div>

            <!-- Asistencia -->
            <div class="col-md-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="icon-lg mb-3"><i class="bi bi-check-circle"></i></div>
                        <h5 class="card-title">Asistencia</h5>
                        <p class="card-text">Gestiona la asistencia de los eventos.</p>
                        <a href="asistencia/asistencia" class="btn btn-primary">Gestionar Asistencia</a>
                    </div>
                </div>
            </div>

            <!-- Cerrar Sesión -->
            <div class="col-md-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="icon-lg mb-3"><i class="bi bi-box-arrow-right"></i></div>
                        <h5 class="card-title">Cerrar Sesión</h5>
                        <p class="card-text">Salir de la sesión actual.</p>
                        <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons y JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
