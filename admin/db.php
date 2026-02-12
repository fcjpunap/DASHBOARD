<?php
// db.php - CONEXIÓN CENTRALIZADA A LA BASE DE DATOS
// Usa las credenciales del usuario:
$db_host = 'localhost';
$db_name = 'fcjp_dashboard';
$db_user = 'fcjp_dashboard';
$db_pass = 'zuhnam-dagFam-9qacwo';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>