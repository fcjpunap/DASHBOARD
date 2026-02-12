<?php
/**
 * LIMPIADOR DE BASE DE DATOS
 * Borra todo el contenido de 'sidpol_hechos' para una importación limpia.
 */

require_once 'db.php';

try {
    echo "<h1>🧹 Limpiando Base de Datos...</h1>";

    // TRUNCATE es más rápido y resetea los IDs autoincrementales
    $pdo->exec("TRUNCATE TABLE sidpol_hechos");

    // Asegurar índice único por si acaso
    try {
        $pdo->exec("ALTER TABLE sidpol_hechos ADD UNIQUE INDEX idx_hash (hash_unico)");
    } catch (Exception $e) { /* Ya existe */
    }

    echo "<h2 style='color:green'>✅ Tabla 'sidpol_hechos' vaciada con éxito.</h2>";
    echo "<p>La base de datos está lista recibir nuevos datos.</p>";
    echo "<p><a href='panel.php' style='font-size:20px; font-weight:bold'>[ Ir al Importador ]</a></p>";

    echo "<hr><p style='color:red'>⚠️ IMPORTANTE: Borra este archivo después de usarlo.</p>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>