<?php
require_once 'admin/db.php';
header('Content-Type: text/plain');

echo "Verificación general SIDPOL 2020:\n";

$q = "SELECT COUNT(*) as total, SUM(cantidad) as cantidad_total FROM sidpol_hechos WHERE fuente = 'SIDPOL' AND anio = 2020";
$r = $pdo->query($q)->fetch(PDO::FETCH_ASSOC);

echo "Total registros SIDPOL 2020: " . $r['total'] . "\n";
echo "Cantidad total SIDPOL 2020: " . ($r['cantidad_total'] ?: 0) . "\n";

if ($r['total'] > 0) {
    echo "\nTop 10 Sub-tipos SIDPOL 2020:\n";
    $q2 = "SELECT sub_tipo_delito, SUM(cantidad) as total FROM sidpol_hechos WHERE fuente = 'SIDPOL' AND anio = 2020 GROUP BY sub_tipo_delito ORDER BY total DESC LIMIT 10";
    $st = $pdo->query($q2);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['sub_tipo_delito']} ({$row['total']})\n";
    }
} else {
    echo "\n¡ATENCIÓN! No hay ningún registro de la fuente SIDPOL para el año 2020.\n";
}
