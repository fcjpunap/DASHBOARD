<?php
require_once 'admin/db.php';
header('Content-Type: text/plain');

$anio = 2025;
$query = "SELECT tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general, SUM(cantidad) as total 
          FROM sidpol_hechos 
          WHERE anio = ? AND (sub_tipo_delito LIKE '%EXTORSION%' OR tipo_delito LIKE '%EXTORSION%')
          GROUP BY tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general";

$stmt = $pdo->prepare($query);
$stmt->execute([$anio]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Buscando EXTORSION en 2025:\n";
echo "\nTipos de Delito disponibles en 2025:\n";
$stmt2 = $pdo->query("SELECT tipo_delito, SUM(cantidad) as total FROM sidpol_hechos WHERE anio = 2025 GROUP BY tipo_delito ORDER BY total DESC");
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$r['tipo_delito']} ({$r['total']})\n";
}
