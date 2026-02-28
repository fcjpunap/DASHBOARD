<?php
require_once 'db.php';

// Importar SOLO la Hoja 1 (Faltas, Violencia, Niños, Otros) del Excel SIDPOL
// Esto complementa lo que ya fue importado de Hoja 7 (Delitos 2025)

set_time_limit(600);
ini_set('max_execution_time', 600);

function clean_val_s1($v)
{
    return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $v));
}
function normalize_str_s1($s)
{
    return strtoupper(trim(preg_replace('/\s+/', ' ', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s))));
}

$url = 'https://observatorio.mininter.gob.pe/sites/default/files/proyecto/archivos/Base_datos_SIDPOL_diciembre_2025.xlsx';
echo "📥 Descargando Excel...\n";
flush();

$tmpFile = tempnam(sys_get_temp_dir(), 'sheet1_');
$fp = fopen($tmpFile, 'w+');
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 300, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Mozilla/5.0']);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

echo "HTTP: $httpCode, Tamaño: " . number_format(filesize($tmpFile)) . " bytes\n";

$zip = new ZipArchive;
if (!$zip->open($tmpFile)) {
    echo "Error abriendo ZIP\n";
    unlink($tmpFile);
    die();
}

// Load SharedStrings
$ss = [];
$xmlSS = new XMLReader();
$xmlSS->xml($zip->getFromName('xl/sharedStrings.xml'));
while ($xmlSS->read())
    if ($xmlSS->localName === 't' && $xmlSS->nodeType === XMLReader::ELEMENT)
        $ss[] = $xmlSS->readString();
$xmlSS->close();
echo "SharedStrings cargados: " . count($ss) . "\n";

// Prepare DB
$sql = "INSERT INTO sidpol_hechos (fuente, anio, mes, dia_semana, hora, ubigeo_hecho, dpto_hecho, prov_hecho, dist_hecho, tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general, cantidad, hash_unico)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)";
$stmt = $pdo->prepare($sql);

// Process Sheet 1 ONLY - skip 1.Delitos, import Faltas/Violencia/Niños/Otros
$xml = new XMLReader();
$xml->xml($zip->getFromName('xl/worksheets/sheet1.xml'));
$rowNum = 0;
$count = 0;
$skipped = 0;
$header = [];

echo "📄 Procesando Hoja 1...\n";
flush();

while ($xml->read()) {
    if ($xml->localName === 'row' && $xml->nodeType === XMLReader::ELEMENT) {
        $rowNum++;
        $rowData = [];
        $depth = $xml->depth;
        while ($xml->read() && $xml->depth > $depth) {
            if ($xml->localName === 'c' && $xml->nodeType === XMLReader::ELEMENT) {
                $ref = $xml->getAttribute('r');
                preg_match('/([A-Z]+)/', $ref, $m);
                $col = $m[1];
                $type = $xml->getAttribute('t');
                $v = '';
                $cD = $xml->depth;
                while ($xml->read() && $xml->depth > $cD) {
                    if ($xml->localName === 'v' && $xml->nodeType === XMLReader::ELEMENT) {
                        $v = $xml->readString();
                        if ($type === 's')
                            $v = $ss[(int) $v] ?? $v;
                        break;
                    }
                }
                $rowData[$col] = clean_val_s1($v);
            }
        }

        if ($rowNum === 1) {
            foreach ($rowData as $k => $v)
                $header[$k] = strtoupper($v);
            echo "Header: " . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n";
            flush();
            continue;
        }

        $row = [];
        foreach ($header as $k => $v)
            $row[$v] = $rowData[$k] ?? '';

        $catRaw = strtolower(trim($row['ES_DELITO_X'] ?? ''));

        // SKIP delitos - they come from Sheet 7 with more detail
        if ($catRaw === '' || strpos($catRaw, '1.') === 0 || $catRaw === '1.delitos') {
            $skipped++;
            continue;
        }

        // Map category
        if (strpos($catRaw, '2.') === 0 || strpos($catRaw, 'falta') !== false)
            $cat_general = '2.FALTAS';
        elseif (strpos($catRaw, '4.') === 0 || strpos($catRaw, 'violencia') !== false)
            $cat_general = '4.VIOLENCIA';
        elseif (strpos($catRaw, '3.') === 0 || strpos($catRaw, 'ni') !== false || strpos($catRaw, 'adolesc') !== false)
            $cat_general = '3.NIÑOS Y ADOLESCENTES';
        else
            $cat_general = 'OTROS';

        $anioVal = (int) preg_replace('/[^0-9]/', '', (string) ($row['ANIO'] ?? ''));
        if (!$anioVal)
            $anioVal = (int) date('Y'); // Fallback
        $cant = (int) ($row['N_DIST_ID_DGC'] ?: ($row['CANTIDAD'] ?: 1));
        $dpto = normalize_str_s1($row['DPTO_HECHO_NEW'] ?: ($row['DPTO_HECHO'] ?: ''));
        $mes = (int) ($row['MES'] ?: 1);
        // Hash sin cantidad para permitir actualizaciones de conteo
        $hash = md5('SIDPOL_SHEET1_' . $anioVal . '_' . $mes . '_' . $dpto . '_' . $cat_general);

        try {
            $stmt->execute(['SIDPOL', $anioVal, $mes, '', null, '', $dpto, '', '', substr($cat_general, 0, 100), '', substr($cat_general, 0, 100), $cat_general, $cant, $hash]);
            if ($stmt->rowCount() > 0)
                $count++;
        } catch (PDOException $e) {
        }

        if ($rowNum % 2000 == 0) {
            echo "Fila $rowNum... ($count insertados)\n";
            flush();
        }
    }
}
$xml->close();
$zip->close();
unlink($tmpFile);

echo "\n✅ COMPLETADO: $count registros insertados, $skipped delitos saltados, $rowNum total filas.\n";

// Verify
$verify = $pdo->query("SELECT es_delito_general, SUM(cantidad) as total FROM sidpol_hechos WHERE anio=2025 GROUP BY es_delito_general ORDER BY es_delito_general")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== BD 2025 por Categoría ===\n";
$grand = 0;
foreach ($verify as $r) {
    echo "  " . $r['es_delito_general'] . ": " . number_format($r['total'], 0, ',', '.') . "\n";
    $grand += $r['total'];
}
echo "  TOTAL: " . number_format($grand, 0, ',', '.') . "\n";
?>