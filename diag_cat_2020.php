<?php
require_once 'admin/db.php';
header('Content-Type: text/plain');

echo "Verificación categorías SIDPOL 2020:\n";

$q = "SELECT es_delito_general, COUNT(*) as n_filas, SUM(cantidad) as total_hechos 
      FROM sidpol_hechos 
      WHERE fuente = 'SIDPOL' AND anio = 2020 
      GROUP BY es_delito_general";

$st = $pdo->query($q);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    echo "Categoría: [{$r['es_delito_general']}] | Filas: {$r['n_filas']} | Total Hechos: {$r['total_hechos']}\n";
}
