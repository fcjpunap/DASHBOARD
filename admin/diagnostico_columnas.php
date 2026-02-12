<?php
/**
 * DIAGNÓSTICO DE CABECERAS V2 - Analiza las columnas reales de cada hoja
 * y muestra una muestra de datos para verificar el mapeo de modalidad.
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
require_once 'db.php';
ini_set('max_execution_time', 120);
ini_set('memory_limit', '512M');

echo "<h1>🔍 Diagnóstico de Cabeceras y Datos</h1>";

// 1. Primero veamos qué hay en la BD con "Otros"
echo "<h2>📊 Distribución actual de Modalidades en PUNO (Top 20)</h2>";
$stmt = $pdo->prepare("SELECT modalidad_delito, SUM(cantidad) as c FROM sidpol_hechos WHERE dpto_hecho='PUNO' AND fuente='SIDPOL' AND anio=2025 GROUP BY modalidad_delito ORDER BY c DESC LIMIT 20");
$stmt->execute();
echo "<table border='1' cellpadding='5'><tr><th>Modalidad</th><th>Cantidad</th></tr>";
while ($row = $stmt->fetch()) {
    $color = ($row['modalidad_delito'] == 'Otros') ? 'background:yellow' : '';
    echo "<tr style='$color'><td>{$row['modalidad_delito']}</td><td>{$row['c']}</td></tr>";
}
echo "</table>";

// 2. Mostrar muestra de registros con modalidad "Otros"
echo "<h2>🔎 Muestra de registros con modalidad 'Otros' en PUNO</h2>";
$stmt = $pdo->prepare("SELECT tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general FROM sidpol_hechos WHERE dpto_hecho='PUNO' AND fuente='SIDPOL' AND modalidad_delito='Otros' LIMIT 20");
$stmt->execute();
echo "<table border='1' cellpadding='5'><tr><th>Tipo</th><th>Sub-Tipo</th><th>Modalidad</th><th>General</th></tr>";
while ($row = $stmt->fetch()) {
    echo "<tr><td>{$row['tipo_delito']}</td><td>{$row['sub_tipo_delito']}</td><td>{$row['modalidad_delito']}</td><td>{$row['es_delito_general']}</td></tr>";
}
echo "</table>";

// 3. Descargar y analizar cabeceras del archivo SIDPOL actual
$urls = [
    'Base_datos_SIDPOL_Enero2026.xlsx' => 'https://observatorio.mininter.gob.pe/sites/default/files/proyecto/archivos/Base_datos_SIDPOL_Enero2026.xlsx',
    'Diccionario' => 'https://observatorio.mininter.gob.pe/sites/default/files/proyecto/archivos/Diccionario%20de%20variables%20-%20denuncias%20SIDPOL_reporte%20%284%29.xlsx'
];

foreach ($urls as $label => $url) {
    echo "<h2>📂 $label</h2>";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $data = curl_exec($ch);
    curl_close($ch);

    if (!$data) {
        echo "<p>Error descargando.</p>";
        continue;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'diag_');
    file_put_contents($tmp, $data);

    $zip = new ZipArchive;
    if ($zip->open($tmp) !== TRUE) {
        echo "<p>No es un ZIP válido.</p>";
        unlink($tmp);
        continue;
    }

    $sharedStrings = [];
    if ($zip->locateName('xl/sharedStrings.xml') !== false) {
        $xmlSS = new XMLReader();
        $xmlSS->xml($zip->getFromName('xl/sharedStrings.xml'));
        while ($xmlSS->read()) {
            if ($xmlSS->name === 't' && $xmlSS->nodeType === XMLReader::ELEMENT)
                $sharedStrings[] = $xmlSS->readString();
        }
        $xmlSS->close();
    }

    for ($s = 1; $s <= 7; $s++) {
        $sheetFile = "xl/worksheets/sheet$s.xml";
        if ($zip->locateName($sheetFile) === false)
            continue;
        echo "<h3>Hoja #$s</h3>";
        $xml = new XMLReader();
        $xml->xml($zip->getFromName($sheetFile));
        $rowNum = 0;
        $header = [];
        while ($xml->read()) {
            if ($xml->name === 'row' && $xml->nodeType === XMLReader::ELEMENT) {
                $rowNum++;
                if ($rowNum > 3)
                    break; // Solo cabecera + 2 filas de muestra
                $node = $xml->expand();
                $cells = $node->getElementsByTagName('c');
                $vals = [];
                foreach ($cells as $cell) {
                    $ref = $cell->getAttribute('r');
                    preg_match('/([A-Z]+)/', $ref, $m);
                    $type = $cell->getAttribute('t');
                    $vN = $cell->getElementsByTagName('v')->item(0);
                    $v = $vN ? $vN->nodeValue : '';
                    if ($type === 's' && isset($sharedStrings[$v]))
                        $v = $sharedStrings[$v];
                    $vals[$m[1]] = $v;
                }
                if ($rowNum === 1) {
                    $header = $vals;
                    echo "<p><b>Columnas (" . count($header) . "):</b></p><pre style='background:#eee;padding:10px;'>";
                    foreach ($header as $col => $name) {
                        echo "$col => $name\n";
                    }
                    echo "</pre>";
                } else {
                    echo "<p><b>Fila $rowNum (muestra):</b></p><pre style='background:#f5f5f5;padding:10px;font-size:11px;'>";
                    foreach ($header as $col => $name) {
                        $val = $vals[$col] ?? '---';
                        echo str_pad($name, 25) . " = $val\n";
                    }
                    echo "</pre>";
                }
            }
        }
        $xml->close();
    }
    $zip->close();
    unlink($tmp);
}
echo "<hr><p><a href='panel.php'>Volver</a></p>";
?>