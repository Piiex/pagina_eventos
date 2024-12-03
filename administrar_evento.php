<?php

include 'db.php';
include 'header.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nombre_tabla = filter_var($_GET['evento'], FILTER_SANITIZE_STRING);

// Verificar evento
$stmt = $conn->prepare("SELECT * FROM configuracion_eventos WHERE tabla = ? AND user_id = ?");
$stmt->bind_param("si", $nombre_tabla, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("El evento no existe o no está vinculado a su cuenta.");
}

$evento_config = $result->fetch_assoc();
$banner = $evento_config['banner'] ?? '';
$logo = $evento_config['logo'] ?? '';
$descripccion = $evento_config['descripccion'] ?? '';

// Obtener columnas existentes
$columnas = $conn->query("DESCRIBE $nombre_tabla");
$campos = [];
while ($columna = $columnas->fetch_assoc()) {
    if ($columna['Field'] !== 'id' && $columna['Field'] !== 'created_at') {
        $campos[] = $columna;
    }
}

// Función para determinar el tipo real de campo
function getDatabaseFieldType($fieldType) {
    $fieldType = strtolower($fieldType);
    if (strpos($fieldType, 'varchar') !== false) return 'VARCHAR(255)';
    if (strpos($fieldType, 'int') !== false) return 'INT';
    if (strpos($fieldType, 'text') !== false) return 'TEXT';
    if (strpos($fieldType, 'date') !== false) return 'DATE';
    if (strpos($fieldType, 'email') !== false) return 'EMAIL';
    return 'VARCHAR(255)';
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'actualizar_info_evento':
                $nuevo_nombre_tabla = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($_POST['nombre_tabla']));
                $descripccion = filter_var($_POST['descripccion'], FILTER_SANITIZE_STRING);

                // Actualizar nombre de tabla y descripción
                if (!empty($nuevo_nombre_tabla) && $nuevo_nombre_tabla !== $nombre_tabla) {
                    $conn->query("RENAME TABLE `$nombre_tabla` TO `$nuevo_nombre_tabla`");
                    $stmt = $conn->prepare("UPDATE configuracion_eventos SET tabla = ?, descripccion = ? WHERE tabla = ? AND user_id = ?");
                    $stmt->bind_param("sssi", $nuevo_nombre_tabla, $descripccion, $nombre_tabla, $user_id);
                    $stmt->execute();
                    $nombre_tabla = $nuevo_nombre_tabla;
                } else {
                    $stmt = $conn->prepare("UPDATE configuracion_eventos SET descripccion = ? WHERE tabla = ? AND user_id = ?");
                    $stmt->bind_param("ssi", $descripccion, $nombre_tabla, $user_id);
                    $stmt->execute();
                }
                break;

            case 'eliminar_campo':
                if (!empty($_POST['campo_nombre'])) {
                    $campo = filter_var($_POST['campo_nombre'], FILTER_SANITIZE_STRING);
                    if (!empty($campo)) {
                        $conn->query("ALTER TABLE `$nombre_tabla` DROP COLUMN `$campo`");
                    }
                    header("Location: administrar_evento.php?evento=" . urlencode($nombre_tabla) . "&success=campo_eliminado");
                    exit();
                }
                break;

            case 'actualizar_campos':
                if (isset($_POST['campos'])) {
                    foreach ($_POST['campos'] as $campo) {
                        $nombre_original = filter_var($campo['nombre_original'], FILTER_SANITIZE_STRING);
                        $nombre_nuevo = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($campo['nombre']));
                        $tipo = strtoupper($campo['tipo']);
                        
                        // Nuevo campo
                        if (empty($nombre_original)) {
                            if (!empty($nombre_nuevo) && !empty($tipo)) {
                                $conn->query("ALTER TABLE `$nombre_tabla` ADD COLUMN `$nombre_nuevo` $tipo");
                            }
                        }
                        // Campo existente
                        else {
                            if ($nombre_original !== $nombre_nuevo) {
                                $conn->query("ALTER TABLE `$nombre_tabla` CHANGE `$nombre_original` `$nombre_nuevo` $tipo");
                            } else {
                                $conn->query("ALTER TABLE `$nombre_tabla` MODIFY `$nombre_nuevo` $tipo");
                            }
                        }
                    }
                }
                
                break;

                case 'actualizar_archivos':
                    // Verificar si se subió un banner
                    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = procesarArchivo($_FILES['banner'], 'banners/', $conn, $nombre_tabla, $user_id, 'banner');
                        if ($upload_result['success']) {
                            $banner = $upload_result['path']; // Actualizar el valor del banner
                        } else {
                            error_log("Error al procesar el banner: " . $upload_result['error']);
                        }
                    }
                
                    // Verificar si se subió un logo
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = procesarArchivo($_FILES['logo'], 'logos/', $conn, $nombre_tabla, $user_id, 'logo');
                        if ($upload_result['success']) {
                            $logo = $upload_result['path']; // Actualizar el valor del logo
                        } else {
                            error_log("Error al procesar el logo: " . $upload_result['error']);
                        }
                    }
                    break;
                
                case 'actualizar_smtp':
                    $smtp_host = filter_var($_POST['smtp_host'], FILTER_SANITIZE_STRING);
                    $smtp_port = filter_var($_POST['smtp_port'], FILTER_SANITIZE_NUMBER_INT);
                    $smtp_user = filter_var($_POST['smtp_user'], FILTER_SANITIZE_STRING);
                    $smtp_password = filter_var($_POST['smtp_password'], FILTER_SANITIZE_STRING);
                
                    $stmt = $conn->prepare("UPDATE configuracion_eventos SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_password = ? WHERE tabla = ? AND user_id = ?");
                    $stmt->bind_param("sisssi", $smtp_host, $smtp_port, $smtp_user, $smtp_password, $nombre_tabla, $user_id);
                    $stmt->execute();
                    break;
        }
        
    }
    

    header("Location: administrar_evento.php?evento=" . urlencode($nombre_tabla) . "&success=cambios_guardados");
    exit();
}

function procesarArchivo($file, $directory, $conn, $nombre_tabla, $user_id, $tipo) {
    // Crear directorio si no existe
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0777, true)) {
            return ['success' => false, 'error' => "No se pudo crear el directorio $directory."];
        }
    }

    // Obtener la imagen anterior de la base de datos
    $stmt = $conn->prepare("SELECT $tipo FROM configuracion_eventos WHERE tabla = ? AND user_id = ?");
    $stmt->bind_param("si", $nombre_tabla, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $archivo_anterior = $row[$tipo];

        // Eliminar el archivo anterior si existe
        if (!empty($archivo_anterior) && file_exists($archivo_anterior)) {
            unlink($archivo_anterior);
        }
    }

    // Generar un nombre único para el archivo
    $fileName = basename($file['name']);
    $filePath = $directory . uniqid() . '_' . $fileName;

    // Mover el archivo al directorio
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'error' => "Error al mover el archivo al directorio $directory."];
    }

    // Actualizar la base de datos con la nueva ruta del archivo
    $stmt = $conn->prepare("UPDATE configuracion_eventos SET $tipo = ? WHERE tabla = ? AND user_id = ?");
    $stmt->bind_param("ssi", $filePath, $nombre_tabla, $user_id);

    if ($stmt->execute()) {
        return ['success' => true, 'path' => $filePath];
    }

    return ['success' => false, 'error' => "Error al actualizar la base de datos."];
}



?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Evento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
</head>
<link rel="stylesheet" href="css/estilos_evento.css">
<body>
    <div class="container">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            $message = match($_GET['success']) {
                'campo_eliminado' => 'Campo eliminado exitosamente.',
                'cambios_guardados' => 'Los cambios han sido guardados exitosamente.',
                default => 'Operación completada con éxito.'
            };
            echo $message;
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            
            <div class="mb-4">
            <div class="container">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="mb-0">Administrar Evento: <?= htmlspecialchars(str_replace('_', ' ', $nombre_tabla)) ?></h2>
            <a href="registro?evento=<?= urlencode($nombre_tabla) ?>" target="_blank" class="btn btn-light">
                <i class="fas fa-external-link-alt"></i> Ver Formulario
            </a>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Información del Evento
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="actualizar_info_evento">
                    <div class="mb-3">
                        <label for="nombre_tabla" class="form-label">Nombre del Evento</label>
                        <input type="text" class="form-control" id="nombre_tabla" name="nombre_tabla" 
                               value="<?= htmlspecialchars($nombre_tabla) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripccion" name="descripccion" rows="3" required>
                            <?= htmlspecialchars($descripccion) ?>
                        </textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-action">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </form>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <div class="section-title">
                        <i class="fas fa-list-alt"></i> Campos del Formulario
                    </div>
                    
                    <form id="camposForm" method="post">
                        <input type="hidden" name="action" value="actualizar_campos">
                        <div id="campos-container">
                            <?php foreach ($campos as $index => $campo): ?>
                            <div class="campo d-flex align-items-center">
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <input type="hidden" name="campos[<?= $index ?>][nombre_original]" value="<?= htmlspecialchars($campo['Field']) ?>">
                                <div class="row flex-grow-1">
                                    <div class="col-md-5">
                                        <input type="text" name="campos[<?= $index ?>][nombre]" class="form-control" value="<?= htmlspecialchars($campo['Field']) ?>" required placeholder="Nombre del campo">
                                    </div>
                                    <!--<div class="col-md-5">
                                      <select name="campos[<?= $index ?>][tipo]" class="form-control">
                                            <?php
                                            $currentType = getDatabaseFieldType($campo['Type']);
                                            $availableTypes = [
                                                'VARCHAR(255)' => 'Texto',
                                                'INT' => 'Número',
                                                'DATE' => 'Fecha',
                                                'TEXT' => 'Texto Largo',
                                                'EMAIL' => 'Correo Electrónico'
                                            ];
                                            
                                            foreach ($availableTypes as $value => $label):
                                                $selected = ($currentType === $value) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>-->
                                    <div class="col-md-2 text-end">
                                        <button type="button" class="btn btn-danger btn-action" onclick="eliminarCampo(this, '<?= htmlspecialchars($campo['Field']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-secondary btn-action" onclick="agregarCampo()">
                                <i class="fas fa-plus"></i> Agregar Campo
                            </button>
                            <button type="submit" class="btn btn-primary btn-action">
                                <i class="fas fa-save"></i> Guardar Campos
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mb-4">
                    <div class="section-title">
                        <i class="fas fa-images"></i> Imágenes del Evento
                    </div>
                    
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="actualizar_archivos">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>Banner del Evento</h5>
                                        <input type="file" name="banner" class="form-control" accept="image/*">
                                        <?php if ($banner): ?>
                                            <img src="<?= htmlspecialchars($banner) ?>" alt="Banner" class="preview-image">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>Logo del Evento</h5>
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                        <?php if ($logo): ?>
                                            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" class="preview-image">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-action">
                                <i class="fas fa-upload"></i> Actualizar Imágenes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="mb-4">
    <div class="section-title">
        <i class="fas fa-envelope"></i> Configuración SMTP
    </div>
    <form method="post">
        <input type="hidden" name="action" value="actualizar_smtp">
        <div class="mb-3">
            <label for="smtp_host" class="form-label">Servidor SMTP</label>
            <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($evento_config['smtp_host'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="smtp_port" class="form-label">Puerto SMTP</label>
            <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($evento_config['smtp_port'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="smtp_user" class="form-label">Usuario SMTP</label>
            <input type="text" class="form-control" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars($evento_config['smtp_user'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="smtp_password" class="form-label">Contraseña SMTP</label>
            <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?= htmlspecialchars($evento_config['smtp_password'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-action">
            <i class="fas fa-save"></i> Guardar Configuración SMTP
        </button>
    </form>
</div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function eliminarCampo(button, nombreCampo) {
            if (confirm('¿Está seguro de que desea eliminar este campo? Esta acción no se puede deshacer.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'eliminar_campo';

                const campoInput = document.createElement('input');
                campoInput.type = 'hidden';
                campoInput.name = 'campo_nombre';
                campoInput.value = nombreCampo;

                form.appendChild(actionInput);
                form.appendChild(campoInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function agregarCampo() {
            const container = document.getElementById('campos-container');
            const index = container.children.length;
            
            const campoDiv = document.createElement('div');
            campoDiv.className = 'campo d-flex align-items-center';
            
            const handleIcon = document.createElement('i');
            handleIcon.className = 'fas fa-grip-vertical drag-handle';
            
            const rowDiv = document.createElement('div');
            rowDiv.className = 'row flex-grow-1';
            
            const html = `
                <input type="hidden" name="campos[${index}][nombre_original]" value="">
                <div class="col-md-5">
                    <input type="text" name="campos[${index}][nombre]" class="form-control" required placeholder="Nombre del campo">
                </div>
                <div class="col-md-5">
                    <select name="campos[${index}][tipo]" class="form-control">
                        <option value="VARCHAR(255)">Texto</option>
                        <option value="INT">Número</option>
                        <option value="DATE">Fecha</option>
                        <option value="TEXT">Texto Largo</option>
                        <option value="EMAIL">Correo Electrónico</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger btn-action" onclick="this.closest('.campo').remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            rowDiv.innerHTML = html;
            campoDiv.appendChild(handleIcon);
            campoDiv.appendChild(rowDiv);
            container.appendChild(campoDiv);
        }

        // Función para validar el formulario antes de enviar
        document.getElementById('camposForm').addEventListener('submit', function(e) {
            const campos = document.querySelectorAll('input[name$="[nombre]"]');
            const nombresUsados = new Set();
            let error = false;

            campos.forEach(campo => {
                const nombre = campo.value.trim().toLowerCase();
                
                // Verificar campo vacío
                if (!nombre) {
                    alert('Todos los campos deben tener un nombre.');
                    e.preventDefault();
                    error = true;
                    return;
                }

                // Verificar duplicados
                if (nombresUsados.has(nombre)) {
                    alert('No puede haber nombres de campos duplicados.');
                    e.preventDefault();
                    error = true;
                    return;
                }

                // Verificar formato válido
                if (!/^[a-z][a-z0-9_]*$/.test(nombre)) {
                    alert('Los nombres de los campos deben comenzar con una letra y solo pueden contener letras, números y guiones bajos.');
                    e.preventDefault();
                    error = true;
                    return;
                }

                nombresUsados.add(nombre);
            });

            if (!error && campos.length === 0) {
                alert('Debe haber al menos un campo en el formulario.');
                e.preventDefault();
            }
        });

        // Prevenir envío accidental del formulario con Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
            }
        });

        // Mostrar previa de imágenes al seleccionarlas
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    const preview = this.parentElement.querySelector('.preview-image') || 
                                  document.createElement('img');
                    
                    if (!preview.classList.contains('preview-image')) {
                        preview.className = 'preview-image';
                        this.parentElement.appendChild(preview);
                    }

                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    };

                    reader.readAsDataURL(file);
                }
            });
        });
    </script>