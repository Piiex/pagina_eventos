<?php
// Archivo: db.php

$host = "localhost";    // Cambia por el nombre del servidor en Hostinger
$user = "root";   // Tu usuario de base de datos u934185700_case
$password = ""; // Tu clave de base de datos  Runaway11._
$database = "eventos";     // El nombre de la base de datos 

// Crear la conexión
$conn = new mysqli($host, $user, $password, $database);

// Verificar si la conexión fue exitosa
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
