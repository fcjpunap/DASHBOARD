<?php
require_once 'admin/db.php';
header('Content-Type: text/plain');

$anio = 2020;
$query = "SELECT tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general, SUM(cantidad) as total 
          FROM sidpol_hechos 
          WHERE anio = ? AND (sub_tipo_delito LIKE '%ABIGEATO%' OR tipo_delito LIKE '%ABIGEATO%' OR modalidad_delito LIKE '%ABIGEATO%')
          GROUP BY tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general";

$stmt = $pdo->prepare($query);
$stmt->execute([$anio]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Buscando ABIGEATO en 2020:\n";
if (empty($results)) {
    echo "No se encontraron registros de ABIGEATO para 2020.\n";

    echo "\nSub-tipos de Delito disponibles en 2020 (Top 50):\n";
    $stmt2 = $pdo->query("SELECT sub_tipo_delito, SUM(cantidad) as total FROM sidpol_hechos WHERE anio = 2020 GROUP BY sub_tipo_delito ORDER BY total DESC LIMIT 50");
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$r['sub_tipo_delito']} ({$r['total']})\n";
    }
} else {
    foreach ($results as $r) {
        echo "Tipo: {$r['tipo_delito']} | Subtipo: {$r['sub_tipo_delito']} | Mod: {$r['modalidad_delito']} | Gen: {$r['es_delito_general']} | Total: {$r['total']}\n";
    }
}
