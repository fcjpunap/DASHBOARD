<?php
/**
 * LIMPIADOR DE BASE DE DATOS - V2 AJAX
 * Borra todo el contenido de 'sidpol_hechos' para una importación limpia.
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
require_once 'db.php';

function writeProgress($jobId, $percentage, $message, $full_log = '')
{
    $file = __DIR__ . "/temp/import_$jobId.json";

    $currentData = [];
    if (file_exists($file)) {
        $currentData = json_decode(file_get_contents($file), true) ?: [];
    }

    $fullLog = $currentData['full_log'] ?? "";
    if ($full_log) {
        $fullLog .= $full_log . "\n";
    }

    $data = [
        'progress' => $percentage,
        'message' => $message,
        'full_log' => $fullLog,
        'status' => ($percentage >= 100) ? 'completed' : 'running'
    ];
    @file_put_contents($file, json_encode($data));
}

function logMsg($msg)
{
    // Only used locally in this file if not using writeProgress full log
}

$job_id = $_GET['job_id'] ?? null;

try {
    if ($job_id) {
        writeProgress($job_id, 10, "Iniciando limpieza...", "🧹 Iniciando vaciado de tabla 'sidpol_hechos'...");
    } else {
        echo "<h1>🧹 Limpiando Base de Datos...</h1>";
    }

    // TRUNCATE es más rápido y resetea los IDs autoincrementales
    $pdo->exec("TRUNCATE TABLE sidpol_hechos");

    // Asegurar índice único por si acaso
    try {
        $pdo->exec("ALTER TABLE sidpol_hechos ADD UNIQUE INDEX idx_hash (hash_unico)");
    } catch (Exception $e) { /* Ya existe */
    }

    if ($job_id) {
        logMsg("✅ Tabla 'sidpol_hechos' vaciada con éxito.");
        writeProgress($job_id, 100, "Limpieza completada", "✅ La base de datos está lista para recibir nuevos datos.");
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Base de datos limpiada']);
    } else {
        echo "<h2 style='color:green'>✅ Tabla 'sidpol_hechos' vaciada con éxito.</h2>";
        echo "<p>La base de datos está lista para recibir nuevos datos.</p>";
        echo "<p><a href='panel.php' style='font-size:20px; font-weight:bold'>[ Ir al Importador ]</a></p>";
        echo "<hr><p style='color:red'>⚠️ IMPORTANTE: Borra este archivo después de usarlo.</p>";
    }

} catch (PDOException $e) {
    if ($job_id) {
        writeProgress($job_id, 0, "Error en limpieza", "❌ Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        die("Error: " . $e->getMessage());
    }
}
?>