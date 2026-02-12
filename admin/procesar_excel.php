<?php
/**
 * PROCESADOR MULTI-FUENTE V9.0 (SIDPOL + MPFN)
 * Soporta archivos .xlsx (SIDPOL) y .csv (MPFN) vía URL o subida.
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
    <title>Importador Multi-Fuente v9.0</title>
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
            white-space: pre-wrap;
        }
    </style>
</head>

<body>
    <h1>🚀 Importador Multi-Fuente v9.0 (SIDPOL & MPFN)</h1>
    <div class="log" id="logBox">
        <?php
        $tempFile = null;
        $fileToProcess = null;
        $originalName = '';

        if (!empty($_POST['url_excel'])) {
            $url = trim($_POST['url_excel']);
            $originalName = basename($url);
            echo "🌐 Descargando desde URL: $originalName... ";
            flush();

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            $fileData = curl_exec($ch);
            curl_close($ch);

            if ($fileData === false) {
                echo "<b style='color:red'>ERROR: Fallo en descarga.</b>";
            } else {
                $tempFile = tempnam(sys_get_temp_dir(), 'import_');
                file_put_contents($tempFile, $fileData);
                $fileToProcess = $tempFile;
                echo "OK<br>";
            }
        } elseif (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
            $fileToProcess = $_FILES['archivo_excel']['tmp_name'];
            $originalName = $_FILES['archivo_excel']['name'];
        }

        if ($fileToProcess) {
            $isCSV = (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'csv');

            // --- INSERT SQL DINÁMICO (Con Fuente) ---
            $sql = "INSERT IGNORE INTO sidpol_hechos (fuente, anio, mes, ubigeo_hecho, dpto_hecho, prov_hecho, dist_hecho, tipo_delito, sub_tipo_delito, modalidaD_delito, es_delito_general, cantidad, hash_unico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $totalGlobal = 0;

            if ($isCSV) {
                echo "📄 Procesando archivo CSV... \n";
                flush();
                if (($handle = fopen($fileToProcess, "r")) !== FALSE) {
                    $header = fgetcsv($handle, 0, ",");
                    // Limpiar BOM y espacios de cabeceras
                    $header = array_map(function ($h) {
                        return trim(str_replace("\xEF\xBB\xBF", "", $h));
                    }, $header);

                    // Detectar si es MPFN (Ministerio Público)
                    $isMPFN = in_array('anio_denuncia', $header) || in_array('distrito_fiscal', $header);
                    $fuente = $isMPFN ? 'MPFN' : 'SIDPOL';
                    echo "🔍 Fuente detectada: $fuente\n";

                    $count = 0;
                    while (($rowData = fgetcsv($handle, 0, ",")) !== FALSE) {
                        $row = array_combine($header, $rowData);

                        if ($isMPFN) {
                            $anioVal = (int) $row['anio_denuncia'];
                            $mes = 1; // El CSV de MPFN suele ser anual consolidado o mensual si se extrae de 'periodo'
                            // Intentar extraer mes de 'periodo_denuncia' si es formato 'ENERO', 'FEBRERO', etc.
                            $periodo = strtoupper($row['periodo_denuncia'] ?? '');
                            if (strpos($periodo, 'ENERO') !== false && strpos($periodo, 'DICIEMBRE') !== false) {
                                $mes = 0; // Representa todo el año
                            }

                            $ubigeo = $row['ubigeo_pjfs'] ?? '';
                            $dpto = $row['dpto_pjfs'] ?? '';
                            $prov = $row['prov_pjfs'] ?? '';
                            $dist = $row['dist_pjfs'] ?? '';
                            $tipo = $row['generico'] ?? '';
                            $subtipo = $row['subgenerico'] ?? '';
                            $mod = $row['des_articulo'] ?? '';
                            $cant = (int) ($row['cantidad'] ?? 1);
                            $general = '1.Delitos';
                        } else {
                            // Lógica genérica para CSV de SIDPOL (v8.0 adaptada)
                            $anioVal = $row['anio'] ?? 2025;
                            $mes = $row['mes'] ?? 1;
                            $ubigeo = $row['ubigeo_hecho'] ?? '';
                            $dpto = $row['dpto_hecho'] ?? '';
                            $prov = $row['prov_hecho'] ?? '';
                            $dist = $row['dist_hecho'] ?? '';
                            $tipo = $row['tipo_delito'] ?? '';
                            $subtipo = $row['sub_tipo_delito'] ?? '';
                            $mod = $row['modalidad_delito'] ?? '';
                            $cant = (int) ($row['cantidad'] ?? 1);
                            $general = $row['es_delito_general'] ?? '1.Delitos';
                        }

                        $hash = md5($fuente . "_" . $anioVal . "_" . $mes . "_" . $ubigeo . "_" . $tipo . "_" . $mod . "_" . $cant);
                        $stmt->execute([$fuente, $anioVal, $mes, $ubigeo, $dpto, $prov, $dist, $tipo, $subtipo, $mod, $general, $cant, $hash]);

                        if ($stmt->rowCount() > 0)
                            $count++;
                        if ($count % 1000 == 0) {
                            echo ".";
                            flush();
                        }
                    }
                    fclose($handle);
                    echo " OK ($count)\n";
                    $totalGlobal = $count;
                }
            } else {
                // --- PROCESADOR EXCEL (SIDPOL) ---
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

                    $fuente = 'SIDPOL';
                    $permitidas = [1, 2, 3, 4, 5, 6, 7];
                    foreach ($permitidas as $i) {
                        $sheetFile = "xl/worksheets/sheet$i.xml";
                        if ($zip->locateName($sheetFile) === false)
                            continue;
                        echo "📄 Procesando Hoja Excel #$i... ";
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

                                    $anioVal = $row['ANIO'] ?: ($row['AÑO'] ?: ($row['AÑO_HECHO'] ?: ($row['ANIO_HECHO'] ?: ($row['PERIODOS'] ?: '2025'))));
                                    $anioVal = preg_replace('/[^0-9]/', '', (string) $anioVal);
                                    if (empty($anioVal) || (int) $anioVal < 2000)
                                        $anioVal = 2025;

                                    if ($i >= 4 && empty($row['DIST_HECHO']) && empty($row['PROV_HECHO']))
                                        continue;

                                    // Lógica de Mapeo Reforzada (V9.1) para evitar el exceso de "Otros"
                                    $tipo = $row['TIPO_DELITO'] ?: ($row['TIPO'] ?: ($row['ES_DELITO_X'] ?: ($row['PRINCIPALES_TIPOS'] ?: ($row['P_MODALIDADES'] ?: 'Otros'))));
                                    if (strpos(strtoupper($tipo), 'TOTAL') !== false)
                                        continue;

                                    $mes = $row['MES'] ?: 1;
                                    $ubigeo = $row['UBIGEO_HECHO'] ?: '';
                                    $dist = $row['DIST_HECHO'] ?: '';
                                    $mod = $row['MODALIDAD_DELITO'] ?: ($row['MODALIDAD'] ?: ($row['MODALIDADES'] ?: ($row['P_MODALIDADES'] ?: 'Otros')));

                                    $hash = md5($fuente . "_" . $anioVal . "_" . $mes . "_" . $ubigeo . "_" . $dist . "_" . $tipo . "_" . $mod);

                                    $stmt->execute([
                                        $fuente,
                                        $anioVal,
                                        $mes,
                                        $ubigeo,
                                        $row['DPTO_HECHO_NEW'] ?: ($row['DPTO_HECHO'] ?: ''),
                                        $row['PROV_HECHO'] ?: '',
                                        $dist,
                                        $tipo,
                                        ($row['SUB_TIPO'] ?: ''),
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
                }
            }
            echo "<h2>🎉 PROCESO COMPLETADO: $totalGlobal registros insertados.</h2>";
        } else {
            echo "<b style='color:yellow'>No se proporcionó ningún archivo o URL válida.</b>";
        }

        if ($tempFile && file_exists($tempFile))
            unlink($tempFile);
        ?>
    </div>
    <div style="margin-top:20px;">
        <a href="panel.php"
            style="padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;">Volver
            al Panel</a>
    </div>
</body>

</html>