<?php
require_once 'db.php';

try {
    echo "<h1>🛠️ Actualizando Base de Datos...</h1>";
    
    // Agregar columna 'fuente' si no existe
    $stmt = $pdo->query("SHOW COLUMNS FROM sidpol_hechos LIKE 'fuente'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE sidpol_hechos ADD COLUMN fuente VARCHAR(20) DEFAULT 'SIDPOL' AFTER id");
        $pdo->exec("CREATE INDEX idx_fuente ON sidpol_hechos(fuente)");
        echo "✅ Columna 'fuente' agregada con éxito.<br>";
    } else {
        echo "ℹ️ La columna 'fuente' ya existe.<br>";
    }

    echo "<h2 style='color:green'>🎉 Base de datos actualizada correctamente.</h2>";
    echo "<p><a href='panel.php'>[ Volver al Panel ]</a></p>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
