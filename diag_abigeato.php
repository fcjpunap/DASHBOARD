<?php
require_once 'admin/db.php';
header('Content-Type: text/plain');

echo "Diagnóstico profundo ABIGEATO 2020:\n";

$query = "SELECT tipo_delito, sub_tipo_delito, modalidad_delito, cantidad, HEX(tipo_delito) as tipo_hex, HEX(sub_tipo_delito) as sub_hex 
          FROM sidpol_hechos 
          WHERE anio = 2020 AND (sub_tipo_delito LIKE '%ABIGEATO%' OR tipo_delito LIKE '%ABIGEATO%')
          LIMIT 5";

$stmt = $pdo->query($query);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "-------------------\n";
    echo "Tipo: [{$r['tipo_delito']}] (Hex: {$r['tipo_hex']})\n";
    echo "Subtipo: [{$r['sub_tipo_delito']}] (Hex: {$r['sub_hex']})\n";
    echo "Mod: {$r['modalidad_delito']}\n";
    echo "Cant: {$r['cantidad']}\n";
}

echo "\nChequeo de filtros específicos:\n";
$stmt2 = $pdo->prepare("SELECT SUM(cantidad) FROM sidpol_hechos WHERE anio = 2020 AND tipo_delito = ? AND sub_tipo_delito = ?");
$stmt2->execute(['CONTRA EL PATRIMONIO', 'ABIGEATO']);
echo "Búsqueda exacta (CONTRA EL PATRIMONIO + ABIGEATO): " . ($stmt2->fetchColumn() ?: 0) . "\n";

$stmt3 = $pdo->prepare("SELECT SUM(cantidad) FROM sidpol_hechos WHERE anio = 2020 AND tipo_delito LIKE ? AND sub_tipo_delito LIKE ?");
$stmt3->execute(['%PATRIMONIO%', '%ABIGEATO%']);
echo "Búsqueda parcial (%PATRIMONIO% + %ABIGEATO%): " . ($stmt3->fetchColumn() ?: 0) . "\n";
