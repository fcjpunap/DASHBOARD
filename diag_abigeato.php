<?php
require_once 'admin/db.php';
header('Content-Type: text/plain');

echo "Diagnóstico PROFUNDO ABIGEATO 2020 (v2):\n";

$query = "SELECT fuente, es_delito_general, tipo_delito, sub_tipo_delito, modalidad_delito, SUM(cantidad) as total 
          FROM sidpol_hechos 
          WHERE anio = 2020 AND sub_tipo_delito = 'ABIGEATO'
          GROUP BY fuente, es_delito_general, tipo_delito, sub_tipo_delito, modalidad_delito";

$stmt = $pdo->query($query);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "No hay registros con subtipo EXACTO 'ABIGEATO' en 2020.\n";
} else {
    foreach ($results as $r) {
        echo "Fuente: [{$r['fuente']}] | Gen: [{$r['es_delito_general']}] | Tipo: [{$r['tipo_delito']}] | Sub: [{$r['sub_tipo_delito']}] | Total: {$r['total']}\n";
    }
}

echo "\nConteo con filtros de Dashboard:\n";
$f_fuente = 'SIDPOL';
$f_anio = 2020;
$f_gen = '1.DELITOS';
$f_tipo = 'CONTRA EL PATRIMONIO';
$f_sub = 'ABIGEATO';

$q2 = "SELECT SUM(cantidad) FROM sidpol_hechos 
       WHERE fuente = ? AND anio = ? AND es_delito_general = ? AND tipo_delito = ? AND sub_tipo_delito = ?";
$st2 = $pdo->prepare($q2);
$st2->execute([$f_fuente, $f_anio, $f_gen, $f_tipo, $f_sub]);
echo "Resultado con filtros exactos: " . ($st2->fetchColumn() ?: 0) . "\n";
