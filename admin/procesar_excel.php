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

function normalize_str($v)
{
    if (!$v)
        return "";
    $v = mb_strtoupper(trim((string) $v), 'UTF-8');
    $a = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'];
    $b = ['A', 'E', 'I', 'O', 'U', 'U', 'N'];
    return str_replace($a, $b, $v);
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
            height: 400px;
            overflow-y: auto;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            margin-top: 10px;
        }

        .progress-container {
            width: 100%;
            background: #ddd;
            border-radius: 20px;
            height: 25px;
            margin: 20px 0;
            overflow: hidden;
            border: 1px solid #ccc;
        }

        #progressBar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #28a745, #218838);
            transition: width 0.3s ease;
            text-align: center;
            line-height: 25px;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        #statusText {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <h1>🚀 Importador Multi-Fuente v9.1</h1>

    <div id="statusText">Iniciando proceso...</div>
    <div class="progress-container">
        <div id="progressBar">0%</div>
    </div>

    <script>
        const logBox = document.getElementById('logBox');
        const progressBar = document.getElementById('progressBar');
        const statusText = document.getElementById('statusText');

        function updateProgress(percent, label) {
            percent = Math.min(100, Math.max(0, Math.round(percent)));
            progressBar.style.width = percent + '%';
            progressBar.innerText = percent + '%';
            if (label) statusText.innerText = label;
        }

        function scrollLog() {
            logBox.scrollTop = logBox.scrollHeight;
        }
    </script>

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
                            $general = '1.DELITOS';

                            // --- NORMALIZACIÓN MPFN ---
                            $tipo = normalize_str($tipo);
                            $subtipo = normalize_str($subtipo);
                            $mod = normalize_str($mod);
                            $dpto = normalize_str($dpto);
                            $prov = normalize_str($prov);
                            $dist = normalize_str($dist);
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
                            $general = '1.DELITOS';

                            // --- NORMALIZACIÓN SIDPOL CSV ---
                            $tipo = normalize_str($tipo);
                            $subtipo = normalize_str($subtipo);
                            $mod = normalize_str($mod);
                            $dpto = normalize_str($dpto);
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
                    // V9.2: Hojas a procesar (SKIP Hoja #4 - solo tiene PRINCIPALES_TIPOS sin modalidad,
                    // duplica datos de Hoja #5 y genera miles de "Otros")
                    // Hoja 1: Resumen nacional por Delitos/Faltas (ES_DELITO_X)
                    // Hoja 2: Resumen nacional por PRINCIPALES_TIPOS
                    // Hoja 3: Resumen nacional por PMODALIDADES
                    // Hoja 5: Detalle distrito + P_MODALIDADES (la buena!)
                    // Hoja 6: Detalle distrito + TIPO/SUB_TIPO/MODALIDAD (2025)
                    // Hoja 7: Detalle distrito + TIPO/SUB_TIPO/MODALIDAD (2026)
                    $permitidas = [1, 2, 3, 5, 6, 7];
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

                                    // --- AÑO ---
                                    $anioVal = $row['ANIO'] ?: ($row['AÑO'] ?: ($row['AÑO_DENUNCIA'] ?? '2025'));
                                    $anioVal = preg_replace('/[^0-9]/', '', (string) $anioVal);
                                    if (empty($anioVal) || (int) $anioVal < 2000)
                                        $anioVal = 2025;

                                    // --- DETECCIÓN DE VIOLENCIA / NIÑO (Basado en Cabeceras) ---
                                    $isViolenciaDoc = false;
                                    $allHeadersStr = implode(" ", array_keys($row));
                                    if (strpos($allHeadersStr, 'VIOLENCIA') !== false || strpos($allHeadersStr, 'AGRESOR') !== false || strpos($allHeadersStr, 'VICTIMA') !== false) {
                                        $isViolenciaDoc = true;
                                    }

                                    // --- V11.0: EVITAR DUPLICADOS Y CAPTURAR FALTAS/VIOLENCIA (Mejorado) ---
                                    if ($i == 5 && (int) $anioVal >= 2025) {
                                        // Detectar categoría usando TODO lo disponible (incluyendo la modalidad)
                                        $catRaw = strtoupper(($row['ES_DELITO_X'] ?? '') . ($row['ES_DELITO_GENERAL'] ?? '') . ($row['TIPO_GENERAL'] ?? '') . ($row['CATEGORIA'] ?? '') . " " . $tipo . " " . $mod);
                                        $esFalta = (strpos($catRaw, 'FALTA') !== false || strpos($catRaw, '2.') !== false);
                                        $esViol = (strpos($catRaw, 'VIOLENCIA') !== false || strpos($catRaw, '4.') !== false);

                                        // Para 2025+, Sheet 5 solo sirve para rescatar Faltas y Violencia.
                                        if (!$esFalta && !$esViol) {
                                            continue;
                                        }
                                    }

                                    // Hojas 1-3 son resúmenes nacionales: solo importar si tienen valor histórico
                                    if ($i <= 3 && (int) $anioVal >= 2025)
                                        continue;

                                    // --- FILTRO: Hojas con detalle geográfico necesitan al menos provincia ---
                                    if ($i >= 5 && empty($row['DIST_HECHO']) && empty($row['PROV_HECHO']))
                                        continue;

                                    // --- CANTIDAD: n_dist_ID_DGC es el conteo real de incidentes ---
                                    $cant = (int) ($row['N_DIST_ID_DGC'] ?: 1);
                                    if ($cant <= 0)
                                        $cant = 1;

                                    // --- MAPEO INTELIGENTE POR HOJA (V9.2) ---
                                    // Basado en diagnóstico de cabeceras reales del Excel SIDPOL
        
                                    // TIPO DE DELITO
                                    if (!empty($row['TIPO'])) {
                                        $tipo = $row['TIPO'];                    // Hojas 6,7
                                    } elseif (!empty($row['PRINCIPALES_TIPOS'])) {
                                        $tipo = $row['PRINCIPALES_TIPOS'];        // Hoja 2
                                    } elseif (!empty($row['PMODALIDADES'])) {
                                        $tipo = $row['PMODALIDADES'];             // Hoja 3
                                    } elseif (!empty($row['P_MODALIDADES'])) {
                                        $tipo = $row['P_MODALIDADES'];            // Hoja 5
                                    } elseif (!empty($row['ES_DELITO_X'])) {
                                        $tipo = $row['ES_DELITO_X'];              // Hoja 1
                                    } else {
                                        $tipo = 'Sin clasificar';
                                    }

                                    // Filtrar filas de totales
                                    if (strpos(strtoupper($tipo), 'TOTAL') !== false)
                                        continue;

                                    // SUB-TIPO
                                    $subtipo = $row['SUB_TIPO'] ?: '';

                                    // MODALIDAD (la clave del problema)
                                    if (!empty($row['MODALIDAD'])) {
                                        $mod = $row['MODALIDAD'];                 // Hojas 6,7 (detalle completo)
                                    } elseif (!empty($row['P_MODALIDADES'])) {
                                        $mod = $row['P_MODALIDADES'];             // Hoja 5 (ej: "Conducción en estado de ebriedad")
                                    } elseif (!empty($row['PMODALIDADES'])) {
                                        $mod = $row['PMODALIDADES'];              // Hoja 3
                                    } else {
                                        $mod = $tipo;  // Fallback: usar el tipo en vez de "Otros"
                                    }

                                    // --- MAPEADO DE CATEGORÍA GENERAL ROBUSTO ---
                                    $catText = strtoupper(($row['ES_DELITO_X'] ?? '') . " " . ($row['ES_DELITO_GENERAL'] ?? '') . " " . ($row['TIPO_GENERAL'] ?? '') . " " . ($row['CATEGORIA'] ?? '') . " " . $tipo . " " . $mod);

                                    if ($isViolenciaDoc || strpos($catText, 'VIOLENCIA') !== false || strpos($catText, '4.') !== false) {
                                        $general = '4.VIOLENCIA';
                                    } elseif (strpos($catText, 'FALTA') !== false || strpos($catText, '2.') !== false) {
                                        $general = '2.FALTAS';
                                    } elseif (strpos($catText, 'NIÑO') !== false || strpos($catText, '3.') !== false) {
                                        $general = '3. NIÑOS Y ADOLESCENTES';
                                    } elseif (strpos($catText, 'DELITO') !== false || strpos($catText, '1.') !== false) {
                                        $general = '1.DELITOS';
                                    } else {
                                        $general = 'OTROS';
                                    }

                                    // --- V10.0: NORMALIZACIÓN TOTAL ---
                                    $tipo = normalize_str($tipo);
                                    $subtipo = normalize_str($subtipo);
                                    $mod = normalize_str($mod);
                                    // $general ya está normalizado arriba
        
                                    // --- UBICACIÓN ---
                                    $mes = $row['MES'] ?: 1;
                                    $ubigeo = $row['UBIGEO_HECHO'] ?: '';
                                    $dpto = $row['DPTO_HECHO_NEW'] ?: ($row['DPTO_HECHO'] ?: '');
                                    $prov = $row['PROV_HECHO'] ?: '';
                                    $dist = $row['DIST_HECHO'] ?: '';

                                    // --- HASH (V10.0: SIN hoja para permitir deduplicación entre hojas) ---
                                    $hash = md5($fuente . "_" . $anioVal . "_" . $mes . "_" . $ubigeo . "_" . $dist . "_" . $tipo . "_" . $mod);

                                    $stmt->execute([
                                        $fuente,
                                        $anioVal,
                                        $mes,
                                        $ubigeo,
                                        $dpto,
                                        $prov,
                                        $dist,
                                        $tipo,
                                        $subtipo,
                                        $mod,
                                        $general,
                                        $cant,
                                        $hash
                                    ]);
                                    if ($stmt->rowCount() > 0)
                                        $count++;

                                    if ($rowNum % 500 == 0) {
                                        // Estimar progreso (basado en que las hojas suelen tener ~50-80k filas max)
                                        $baseProgress = (array_search($i, $permitidas) / count($permitidas)) * 100;
                                        $subProgress = min(15, ($rowNum / 2000));
                                        $totalProgress = $baseProgress + $subProgress;

                                        echo "<script>updateProgress($totalProgress, 'Procesando Hoja #$i - Fila $rowNum...'); scrollLog();</script>";
                                        flush();
                                    }
                                }
                            }
                        }
                        $xml->close();
                        echo " OK ($count)\n";
                        $totalGlobal += $count;
                    }
                    echo "<script>updateProgress(100, 'Importación Finalizada');</script>";
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