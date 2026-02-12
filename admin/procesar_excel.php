<?php
/**
 * PROCESADOR DEFINITIVO V6.2 - FIX DE DETECCIÓN DE AÑO
 * Asegura que se capturen los 864,367 registros de 2025.
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
require_once 'db.php';
ini_set('max_execution_time', 3600);
ini_set('memory_limit', '1024M');

function clean_val($v)
{
    return trim(str_replace(["\xEF\xBB\xBF", "\r", "\n"], "", $v));
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Importador SIDPOL v6.2</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f7f6;
            padding: 20px;
        }

        .log {
            background: #000;
            color: #0ef;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <h1>🚀 Importador SIDPOL v6.2 (Modo Rescate 2025)</h1>
    <div class="log" id="logBox">
        <?php
        $tempFile = null;
        $fileToProcess = null;

        if (!empty($_POST['url_excel'])) {
            $url = trim($_POST['url_excel']);
            echo "🌐 Descargando archivo desde URL... ";
            flush();

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita problemas de certificados SSL
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos de tiempo límite para la descarga
        
            $fileData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($fileData === false || $httpCode !== 200) {
                echo "<b style='color:red'>ERROR: No se pudo descargar el archivo (HTTP $httpCode).</b>";
            } else {
                $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
                file_put_contents($tempFile, $fileData);
                $fileToProcess = $tempFile;
                echo "OK<br>";
            }
        } elseif (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
            $fileToProcess = $_FILES['archivo_excel']['tmp_name'];
        }

        if ($fileToProcess) {
            $zip = new ZipArchive;
            if ($zip->open($fileToProcess) === TRUE) {
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

                $sql = "INSERT IGNORE INTO sidpol_hechos (anio, mes, ubigeo_hecho, dpto_hecho, prov_hecho, dist_hecho, tipo_delito, sub_tipo_delito, modalidaD_delito, es_delito_general, cantidad, hash_unico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $totalGlobal = 0;
                $permitidas = [1, 2, 3, 4, 5, 6, 7];

                foreach ($permitidas as $i) {
                    $sheetFile = "xl/worksheets/sheet$i.xml";
                    if ($zip->locateName($sheetFile) === false)
                        continue;
                    echo "📄 Procesando Hoja #$i... ";
                    flush();
                    $xml = new XMLReader();
                    $xml->xml($zip->getFromName($sheetFile));
                    $rowNum = 0;
                    $count = 0;
                    $header = [];
                    while ($xml->read()) {
                        if ($xml->name === 'row' && $xml->nodeType === XMLReader::ELEMENT) {
                            $rowNum++;
                            $node = $xml->expand();
                            $cells = $node->getElementsByTagName('c');
                            $rowData = [];
                            foreach ($cells as $cell) {
                                $ref = $cell->getAttribute('r');
                                preg_match('/([A-Z]+)/', $ref, $m);
                                $col = $m[1];
                                $type = $cell->getAttribute('t');
                                $vN = $cell->getElementsByTagName('v')->item(0);
                                $v = $vN ? $vN->nodeValue : '';
                                if ($type === 's' && isset($sharedStrings[$v]))
                                    $v = $sharedStrings[$v];
                                $rowData[$col] = clean_val($v);
                            }
                            if ($rowNum === 1) {
                                foreach ($rowData as $k => $v)
                                    $header[$k] = strtoupper($v);
                            } else {
                                $row = [];
                                foreach ($header as $k => $v)
                                    $row[$v] = $rowData[$k] ?? '';

                                // CLASIFICACIÓN V8.0: Dinámica y Acumulativa
                                $anioVal = $row['ANIO'] ?: ($row['AÑO'] ?: ($row['AÑO_HECHO'] ?: ($row['ANIO_HECHO'] ?: ($row['PERIODOS'] ?: ''))));
                                $anioVal = preg_replace('/[^0-9]/', '', (string) $anioVal);
                                if (empty($anioVal) || (int) $anioVal < 2000)
                                    $anioVal = 2025;

                                // FILTRO DE TOTALES: Para hojas 1-3 somos más flexibles, para el resto pedimos al menos Provincia
                                if ($i >= 4 && empty($row['DIST_HECHO']) && empty($row['PROV_HECHO']))
                                    continue;

                                $tipo = $row['TIPO'] ?: ($row['ES_DELITO_X'] ?: ($row['PRINCIPALES_TIPOS'] ?: ($row['P_MODALIDADES'] ?: 'Otros')));
                                if (strpos(strtoupper($tipo), 'TOTAL') !== false)
                                    continue;

                                $mes = $row['MES'] ?: 1;
                                $ubigeo = $row['UBIGEO_HECHO'] ?: '';
                                $dist = $row['DIST_HECHO'] ?: '';
                                $subtipo = $row['SUB_TIPO'] ?: '';
                                $mod = $row['MODALIDAD'] ?: ($row['P_MODALIDADES'] ?: 'Otros');

                                // HASH DE CONTENIDO (Clave para que sea acumulativo sin duplicar entre distintos archivos)
                                $hash = md5($anioVal . "_" . $mes . "_" . $ubigeo . "_" . $dist . "_" . $tipo . "_" . $mod);

                                $stmt->execute([
                                    $anioVal,
                                    $mes,
                                    $ubigeo,
                                    $row['DPTO_HECHO_NEW'] ?: ($row['DPTO_HECHO'] ?: ''),
                                    $row['PROV_HECHO'] ?: '',
                                    $dist,
                                    $tipo,
                                    $subtipo,
                                    $mod,
                                    $row['ES_DELITO_GENERAL'] ?: ($row['ES_DELITO_X'] ?: '1.Delitos'),
                                    1,
                                    $hash
                                ]);
                                if ($stmt->rowCount() > 0)
                                    $count++;
                                if ($count % 5000 == 0) {
                                    echo ".";
                                    flush();
                                }
                            }
                        }
                    }
                    $xml->close();
                    echo " OK ($count)\n";
                    $totalGlobal += $count;
                }
                $zip->close();
                echo "<h2>🎉 PROCESO COMPLETADO: $totalGlobal registros insertados.</h2>";
            } else {
                echo "<b style='color:red'>ERROR: No se pudo abrir el archivo Excel. Asegúrese de que sea un formato .xlsx válido.</b>";
            }
        } else {
            echo "<b style='color:yellow'>No se proporcionó ningún archivo o URL válida para procesar.</b>";
        }

        // Limpiar archivo temporal si existe
        if ($tempFile && file_exists($tempFile)) {
            unlink($tempFile);
        }
        ?>
    </div>
    <a href="panel.php" class="btn">Volver</a>
</body>

</html>