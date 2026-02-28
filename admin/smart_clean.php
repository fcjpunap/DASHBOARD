<?php
/**
 * SMART CLEAN v5.2 - PROCESADOR ROBUSTO (TRIM + CASE INSENSITIVE)
 * Limpia la base de datos eliminando traslapes incluso si hay espacios o diferencias de caja.
 */
ob_start();
require_once 'db.php';

// Intentar cargar la función writeProgress si existe
if (file_exists('procesar_excel.php')) {
    include_once 'procesar_excel.php';
}

if (!function_exists('writeProgress')) {
    function writeProgress($jobId, $percentage, $message, $full_log = '')
    {
        $file = __DIR__ . "/temp/import_$jobId.json";
        $data = ['progress' => $percentage, 'message' => $message, 'full_log' => $full_log, 'status' => ($percentage >= 100) ? 'completed' : 'running'];
        @file_put_contents($file, json_encode($data));
    }
}

set_time_limit(0);
ignore_user_abort(true);
$job_id = "smart_clean";

try {
    writeProgress($job_id, 10, "Iniciando análisis robusto...", "🔍 Buscando periodos para limpiar con normalización de nombres...");

    // 1. Obtener los años disponibles
    $stmt = $pdo->query("SELECT DISTINCT anio FROM sidpol_hechos ORDER BY anio DESC");
    $anios = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalRemoved = 0;
    $step = 0;
    $totalSteps = count($anios);

    foreach ($anios as $anio) {
        $step++;
        $percent = 10 + (($step / $totalSteps) * 80);
        writeProgress($job_id, $percent, "Limpiando año $anio (Normalizado)...", "🧹 Procesando $anio...");

        // QUERY ROBUSTO:
        // Usamos TRIM y UPPER para que 'JULIACA ' y 'juliaca' sean lo mismo.
        // Conservamos el registro con ID más alto (el más reciente).
        $sql = "
            DELETE T1 FROM sidpol_hechos T1
            LEFT JOIN (
                SELECT MAX(id) as keep_id
                FROM sidpol_hechos
                WHERE anio = ?
                GROUP BY 
                    fuente, 
                    anio, 
                    mes, 
                    UPPER(TRIM(dpto_hecho)), 
                    UPPER(TRIM(prov_hecho)), 
                    UPPER(TRIM(dist_hecho)), 
                    UPPER(TRIM(modalidad_delito))
            ) T2 ON T1.id = T2.keep_id
            WHERE T2.keep_id IS NULL AND T1.anio = ?
        ";

        $stmtDel = $pdo->prepare($sql);
        $stmtDel->execute([$anio, $anio]);
        $totalRemoved += $stmtDel->rowCount();

        usleep(200000);
    }

    $msgFinal = "Limpieza completada. Se eliminaron " . number_format($totalRemoved) . " registros redundantes tras normalizar nombres.";
    writeProgress($job_id, 100, "Éxito", "✅ $msgFinal");

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'deleted' => $totalRemoved, 'message' => $msgFinal]);

} catch (Exception $e) {
    writeProgress($job_id, 0, "Error", "❌ Error técnico: " . $e->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>