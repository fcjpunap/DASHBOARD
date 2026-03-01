<?php
/**
 * PROCESADOR MULTI-FUENTE V14.1 (SIDPOL + MPFN)
 * Soporta archivos .xlsx (SIDPOL) y .csv (MPFN) vía URL o subida.
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
require_once 'db.php';
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '1024M');

// --- DESACTIVAR BUFFERING PARA BARRA DE PROGRESO REAL ---
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level())
    ob_end_flush();
ob_implicit_flush(true);

function clean_val($v)
{
    return trim(str_replace(["\xEF\xBB\xBF", "\r", "\n"], "", $v));
}

function normalize_str($v)
{
    $v = mb_strtoupper(trim((string) $v), 'UTF-8');
    $a = ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'];
    $b = ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'];
    $v = str_replace($a, $b, $v);
    return preg_replace('/[^A-Z0-9\s(),.]/', '', $v);
}

function map_crime_type($tipo)
{
    $tipo = strtoupper(trim($tipo));
    $mappings = [
        'PATRIMONIO (DELITO)' => 'CONTRA EL PATRIMONIO',
        'LIBERTAD (DELITO)' => 'CONTRA LA LIBERTAD',
        'VIDA, EL CUERPO Y LA SALUD (DELITO)' => 'CONTRA LA VIDA EL CUERPO Y LA SALUD',
        'VIDA EL CUERPO Y LA SALUD (DELITO)' => 'CONTRA LA VIDA EL CUERPO Y LA SALUD',
        'SEGURIDAD PUBLICA (DELITO)' => 'CONTRA LA SEGURIDAD PUBLICA',
        'FAMILIA (DELITO)' => 'CONTRA LA FAMILIA',
        'ADMINISTRACION PUBLICA (DELITO)' => 'CONTRA LA ADMINISTRACION PUBLICA',
        'FE PUBLICA (DELITO)' => 'CONTRA LA FE PUBLICA',
        'TRANQUILIDAD PUBLICA (DELITO)' => 'CONTRA LA TRANQUILIDAD PUBLICA',
        'AMBIENTALES(DELITO)' => 'CONTRA EL MEDIO AMBIENTE',
        'AMBIENTALES (DELITO)' => 'CONTRA EL MEDIO AMBIENTE',
        'HONOR (DELITO)' => 'CONTRA EL HONOR',
        'DERECHOS INTELECTUALES (DELITO)' => 'CONTRA LOS DERECHOS INTELECTUALES',
        'ORDEN FINANCIERO Y MONETARIO (DELITO)' => 'CONTRA EL ORDEN FINANCIERO Y MONETARIO',
        'TRIBUTARIOS (DELITO)' => 'DELITOS TRIBUTARIOS',
        'PATRIMONIO CULTURAL (DELITO)' => 'CONTRA EL PATRIMONIO CULTURAL',
        'CONFIANZA Y LA BUENA FE EN LOS NEGOCIOS (DELITO)' => 'CONTRA LA CONFIANZA Y LA BUENA FE EN LOS NEGOCIOS',
        'ORDEN ECONOMICO (DELITO)' => 'CONTRA EL ORDEN ECONOMICO',
        'ESTADO Y LA DEFENSA NACIONAL (DELITO)' => 'CONTRA EL ESTADO Y LA DEFENSA NACIONAL',
        'VOLUNTAD POPULAR (DELITO)' => 'CONTRA LA VOLUNTAD POPULAR',
        'HUMANIDAD (DELITO)' => 'CONTRA LA HUMANIDAD',
        'PODERES DEL ESTADO Y EL ORDEN CONSTITUCIONAL (DELITO)' => 'CONTRA LOS PODERES DEL ESTADO Y EL ORDEN CONSTITUCIONAL'
    ];

    foreach ($mappings as $key => $val) {
        if (strpos($tipo, $key) !== false || $tipo == $key) {
            return $val;
        }
    }
    return $tipo;
}

// --- FUNCIÓN PARA SEGUIMIENTO DE PROGRESO AJAX ---
$job_id = $_POST['job_id'] ?? ($_GET['job_id'] ?? null);

function writeProgress($jobId, $percent, $msg, $log = null)
{
    if (!$jobId)
        return;
    $file = __DIR__ . "/temp/import_$jobId.json";

    // Leer estado actual para no perder logs anteriores
    $currentData = [];
    if (file_exists($file)) {
        $currentData = json_decode(file_get_contents($file), true) ?: [];
    }

    $fullLog = $currentData['full_log'] ?? "";
    if ($log) {
        $fullLog .= $log . "\n";
    }

    $data = [
        'progress' => (int) $percent,
        'message' => $msg,
        'last_log' => $log,
        'full_log' => $fullLog,
        'timestamp' => time(),
        'status' => ($percent >= 100) ? 'completed' : 'processing'
    ];
    file_put_contents($file, json_encode($data));
}

function logMsg($msg, $percent = null)
{
    global $job_id;
    // Output directo para vista tradicional
    echo $msg . "<br>";
    flush();
    @ob_flush();

    // Output para AJAX
    if ($job_id) {
        static $currentPercent = 0;
        if ($percent !== null)
            $currentPercent = $percent;
        writeProgress($job_id, $currentPercent, $msg, $msg);
    }
}

// Liberar sesión para no bloquear el polling AJAX
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Importador Multi-Fuente v14.1</title>
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
    <h1>🚀 Importador Multi-Fuente v14.1</h1>
    <div id="statusText">Iniciando proceso...</div>
    <div class="progress-container">
        <div id="progressBar">0%</div>
    </div>

    <script>
        function updateProgress(percent, label) {
            percent = Math.min(100, Math.max(0, Math.round(percent)));
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressBar').innerText = percent + '%';
            if (label) document.getElementById('statusText').innerText = label;
        }
        function scrollLog() {
            var logBox = document.getElementById('logBox');
            if (logBox) logBox.scrollTop = logBox.scrollHeight;
        }
    </script>

    <div class="log" id="logBox">
        <?php
        echo "<!-- " . str_repeat("-", 4096) . " -->\n";
        flush();
        @ob_flush();
        $tempFile = null;
        $fileToProcess = null;
        $originalName = '';

        if (!empty($_POST['url_excel'])) {
            $url = trim($_POST['url_excel']);
            $originalName = basename($url);
            logMsg("🌐 Descargando desde URL: $originalName...", 0);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $dltotal, $dlnow, $ultotal, $ulnow) {
                static $lastPercent = 0;
                if ($dltotal > 0) {
                    $percent = ($dlnow / $dltotal) * 100 * 0.4;
                    if (round($percent) > $lastPercent) {
                        $mb = round($dlnow / 1024 / 1024, 1);
                        $msg = "Descargando... ($mb MB)";
                        echo "<script>updateProgress($percent, '$msg'); scrollLog();</script>";
                        writeProgress($GLOBALS['job_id'], $percent, $msg);
                        flush();
                        @ob_flush();
                        $lastPercent = round($percent);
                    }
                }
            });
            $fileData = curl_exec($ch);
            curl_close($ch);
            if ($fileData === false) {
                echo "<b style='color:red'>ERROR: Fallo en descarga.</b>";
            } else {
                $tempFile = tempnam(sys_get_temp_dir(), 'import_');
                file_put_contents($tempFile, $fileData);
                $fileToProcess = $tempFile;
                logMsg("✅ Descarga completada.");
            }
        } elseif (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
            $fileToProcess = $_FILES['archivo_excel']['tmp_name'];
            $originalName = $_FILES['archivo_excel']['name'];
        }

        if ($fileToProcess) {
            $isCSV = (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'csv');
            $sql = "INSERT INTO sidpol_hechos (fuente, anio, mes, dia_semana, hora, ubigeo_hecho, dpto_hecho, prov_hecho, dist_hecho, tipo_delito, sub_tipo_delito, modalidad_delito, es_delito_general, cantidad, hash_unico) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)";
            $stmt = $pdo->prepare($sql);
            $totalGlobal = 0;
            $startProgress = !empty($_POST['url_excel']) ? 40 : 0;
            $remainingRange = 100 - $startProgress;

            $modo_historico = $_POST['modo_historico'] ?? 'smart';

            // Estadísticas previas para "Smart Mode": identificar años que ya tienen data considerable (ej. > 50,000)
            $stmt_stats = $pdo->query("SELECT anio, SUM(cantidad) as total FROM sidpol_hechos WHERE fuente='SIDPOL' GROUP BY anio");
            $db_years = [];
            while ($row = $stmt_stats->fetch()) {
                $db_years[$row['anio']] = (int) $row['total'];
            }

            if ($isCSV) {
                logMsg("📄 Procesando archivo CSV...", $startProgress);
                if (($handle = fopen($fileToProcess, "r")) !== FALSE) {
                    $header = fgetcsv($handle, 0, ",");
                    $header = array_map(function ($h) {
                        return trim(str_replace("\xEF\xBB\xBF", "", $h));
                    }, $header);
                    $isMPFN = in_array('anio_denuncia', $header) || in_array('distrito_fiscal', $header);
                    // CSV Violencia Mujer IGF: tiene AÑO (con tilde), DPTO_HECHO, PROV_HECHO, DIST_HECHO, UBIGEO_HECHO, MES, CANTIDAD
                    // y NO tiene tipo_delito, modalidad_delito, etc.
                    // Normalizar header para detectar columnas con o sin tilde
                    $headerNorm = array_map(fn($h) => strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $h)), $header);
                    $isVifDoc = (!$isMPFN && in_array('ANO', $headerNorm) && in_array('DPTO_HECHO', $headerNorm) && !in_array('TIPO_DELITO', $headerNorm) && !in_array('ANIO', $headerNorm));
                    $fuente = $isMPFN ? 'MPFN' : 'SIDPOL';
                    // Mapa de columnas reales (con tilde) para CSV VIF
                    $headerMap = array_combine($headerNorm, $header) + array_combine($header, $header);
                    $count = 0;
                    $rowCountTotal = 0;
                    $isViolenciaDoc = false;
                    $allHeadersStr = implode(" ", $header);
                    if ($isVifDoc || strpos($allHeadersStr, 'VIOLENCIA') !== false || strpos($allHeadersStr, 'AGRESOR') !== false || strpos($allHeadersStr, 'VICTIMA') !== false) {
                        $isViolenciaDoc = true;
                    }

                    // PRIMER BARRIDO RÁPIDO: Identificar el año más reciente en el CSV y los años que lo componen
                    $latestYearInCsv = 0;
                    $distinct_years = [];
                    while (($scanRow = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (!is_array($scanRow) || count($scanRow) === 0)
                            continue;
                        $scanRowComb = @array_combine($header, $scanRow);
                        if (!$scanRowComb)
                            continue;
                        $rUpper = array_change_key_case($scanRowComb, CASE_UPPER);
                        $y = (int) ($rUpper['AÑO'] ?? $rUpper['ANO'] ?? $rUpper['ANIO'] ?? $rUpper['AÑO_HECHO'] ?? $rUpper['ANIO_DENUNCIA'] ?? date('Y'));
                        if ($y > $latestYearInCsv) {
                            $latestYearInCsv = $y;
                        }
                        $distinct_years[$y] = true;
                    }
                    rewind($handle);
                    fgetcsv($handle, 0, ","); // saltar header
        
                    // Filtrar años para importar usando lógica Smart
                    // No filtrar años en CSV: Queremos que procese el archivo completo 
                    // ya que el insert usa ON DUPLICATE KEY UPDATE y actualiza adecuadamente las cifras.
                    $years_to_import = array_keys($distinct_years);
                    $years_to_import = array_combine($years_to_import, array_fill(0, count($years_to_import), true));

                    if ($isVifDoc) {
                        logMsg("📋 Detectado: CSV Violencia Mujer/IGF - Limpiando base para actualización...", $startProgress);
                        // Eliminar los registros de 4.VIOLENCIA que provienen del Excel genérico SOLO de los años a importar
                        $deleted = 0;
                        if (count($years_to_import) > 0) {
                            $yList = implode(",", array_keys($years_to_import));
                            // Only delete where fuente='SIDPOL' and prov_hecho='' (the generic ones imported from Sheet 1)
                            $deleted = $pdo->exec("DELETE FROM sidpol_hechos WHERE fuente='SIDPOL' AND es_delito_general='4.VIOLENCIA' AND prov_hecho='' AND anio IN ($yList)");
                        }
                        if ($deleted > 0) {
                            logMsg("🧹 Se limpiaron " . number_format($deleted) . " registros genéricos previos de violencia para sustituirlos por el detalle del CSV.");
                        } else {
                            logMsg("✅ La matriz base de violencia para los años seleccionados está lista o pre-limpiada.");
                        }
                    }

                    while (($rowData = fgetcsv($handle, 0, ",")) !== FALSE) {
                        $row = @array_combine($header, $rowData);
                        if (!$row)
                            continue;

                        // Parseo case-insensitive y sin acentos manual para no depender del caso de la cabecera
                        $rowUpper = array_change_key_case($row, CASE_UPPER);

                        // Encontrar AÑO dinámicamente buscando variantes comunes de la columna:
                        $anioVal = (int) ($rowUpper['AÑO'] ?? $rowUpper['ANO'] ?? $rowUpper['ANIO'] ?? $rowUpper['AÑO_HECHO'] ?? $rowUpper['ANIO_DENUNCIA'] ?? date('Y'));

                        if (!isset($years_to_import[$anioVal])) {
                            continue; // Saltar fila si pertenece a un año histórico (según Modo Inteligente)
                        }

                        $rowCountTotal++;

                        if ($isMPFN) {
                            $mes = 1;
                            $periodo = strtoupper($rowUpper['PERIODO_DENUNCIA'] ?? '');
                            if (strpos($periodo, 'ENERO') !== false && strpos($periodo, 'DICIEMBRE') !== false)
                                $mes = 0;
                            $ubigeo = $rowUpper['UBIGEO_PJFS'] ?? '';
                            $dpto = normalize_str($rowUpper['DPTO_PJFS'] ?? '');
                            $prov = normalize_str($rowUpper['PROV_PJFS'] ?? '');
                            $dist = normalize_str($rowUpper['DIST_PJFS'] ?? '');
                            $tipo = normalize_str(map_crime_type($rowUpper['GENERICO'] ?? ''));
                            $subtipo = normalize_str($rowUpper['SUBGENERICO'] ?? '');
                            $mod = normalize_str($rowUpper['DES_ARTICULO'] ?? '');
                            $cant = (int) ($rowUpper['CANTIDAD'] ?? 1);
                            $general = '1.DELITOS';
                        } elseif ($isVifDoc) {
                            $mes = (int) ($rowUpper['MES'] ?? 1);
                            $ubigeo = $rowUpper['UBIGEO_HECHO'] ?? '';
                            $dpto = normalize_str($rowUpper['DPTO_HECHO'] ?? '');
                            $prov = normalize_str($rowUpper['PROV_HECHO'] ?? '');
                            $dist = normalize_str($rowUpper['DIST_HECHO'] ?? '');
                            $tipo = 'VIOLENCIA CONTRA LA MUJER E INTEGRANTES DEL GRUPO FAMILIAR';
                            $subtipo = '';
                            $mod = 'VIOLENCIA CONTRA LA MUJER E IGF';
                            $cant = (int) ($rowUpper['CANTIDAD'] ?? 1);
                            $general = '4.VIOLENCIA';
                        } else {
                            $mes = (int) ($rowUpper['MES'] ?? 1);
                            $ubigeo = $rowUpper['UBIGEO_HECHO'] ?? '';
                            $dpto = normalize_str($rowUpper['DPTO_HECHO'] ?? '');
                            $prov = normalize_str($rowUpper['PROV_HECHO'] ?? '');
                            $dist = normalize_str($rowUpper['DIST_HECHO'] ?? '');
                            $tipo = normalize_str(map_crime_type($rowUpper['TIPO_DELITO'] ?? ''));
                            $subtipo = normalize_str($rowUpper['SUB_TIPO_DELITO'] ?? '');
                            $mod = normalize_str($rowUpper['MODALIDAD_DELITO'] ?? '');
                            $cant = (int) ($rowUpper['CANTIDAD'] ?? 1);
                            $general = '1.DELITOS';
                        }

                        $catText = strtoupper(($row['ES_DELITO_X'] ?? '') . " " . ($row['ES_DELITO_GENERAL'] ?? '') . " " . ($row['TIPO_GENERAL'] ?? '') . " " . ($row['CATEGORIA'] ?? '') . " " . $tipo . " " . $mod);
                        if (!$isVifDoc && ($isViolenciaDoc || strpos($catText, 'VIOLENCIA') !== false || strpos($catText, '4.') !== false)) {
                            $general = '4.VIOLENCIA';
                        } elseif (!$isVifDoc && !$isMPFN) {
                            $general = $row['es_delito_general'] ?? '1.DELITOS';
                        }

                        $tipo_db = substr($tipo, 0, 100);
                        $subtipo_db = substr($subtipo, 0, 100);
                        $mod_db = substr($mod, 0, 100);
                        // Hash sin cantidad para permitir actualizaciones de conteo
                        $hash = md5($fuente . "_" . $anioVal . "_" . $mes . "_" . $ubigeo . "_" . $tipo_db . "_" . $mod_db . "_" . ($row['dia_semana'] ?? '') . "_" . ($row['hora'] ?? ''));
                        try {
                            $stmt->execute([$fuente, $anioVal, $mes, ($row['dia_semana'] ?? ''), ($row['hora'] ?? null), $ubigeo, $dpto, $prov, $dist, $tipo_db, $subtipo_db, $mod_db, $general, $cant, $hash]);
                            if ($stmt->rowCount() > 0)
                                $count++;
                        } catch (PDOException $e) {
                        }
                        if ($rowCountTotal % 2000 == 0) {
                            $totalProg = $startProgress + min($remainingRange - 2, ($rowCountTotal / 80000) * $remainingRange);
                            echo "<script>updateProgress($totalProg, '🧵 Procesando CSV...'); scrollLog();</script>";
                            logMsg("Procesando CSV - Fila $rowCountTotal...", $totalProg);
                        }
                    }
                    fclose($handle);
                    $totalGlobal += $count;
                }
                logMsg("✅ Importación CSV Finalizada.", 100);
            } else {
                $zip = new ZipArchive;
                if ($zip->open($fileToProcess) === TRUE) {
                    logMsg("📦 Archivo Excel abierto correctamente.");
                    $sharedStrings = [];
                    if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                        $xmlSS = new XMLReader();
                        $xmlSS->xml($zip->getFromName('xl/sharedStrings.xml'));
                        while ($xmlSS->read()) {
                            if ($xmlSS->localName === 't' && $xmlSS->nodeType === XMLReader::ELEMENT)
                                $sharedStrings[] = $xmlSS->readString();
                        }
                        $xmlSS->close();
                    }
                    $fuente = 'SIDPOL';
                    // Procesar Hoja 7 primero (el año actual) para que $latestYearInFile se defina inmediatamente
                    $permitidas = [7, 6, 5, 1];
                    $latestYearInFile = 0;
                    foreach ($permitidas as $i) {
                        $sheetFile = "xl/worksheets/sheet$i.xml";
                        if ($zip->locateName($sheetFile) === false)
                            continue;
                        logMsg("📄 Procesando Hoja Excel #$i...");
                        $xml = new XMLReader();
                        $xml->xml($zip->getFromName($sheetFile));
                        $rowNum = 0;
                        $count = 0;
                        $header = [];
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
                                        $cDepth = $xml->depth;
                                        while ($xml->read() && $xml->depth > $cDepth) {
                                            if ($xml->localName === 'v' && $xml->nodeType === XMLReader::ELEMENT) {
                                                $v = $xml->readString();
                                                if ($type === 's')
                                                    $v = $sharedStrings[$v] ?? $v;
                                                break;
                                            }
                                        }
                                        $rowData[$col] = clean_val($v);
                                    }
                                }
                                if ($rowNum === 1) {
                                    foreach ($rowData as $k => $v)
                                        $header[$k] = strtoupper($v);
                                } else {
                                    $row = [];
                                    foreach ($header as $k => $v)
                                        $row[$v] = $rowData[$k] ?? '';
                                    $anioVal = (int) preg_replace('/[^0-9]/', '', (string) ($row['ANIO'] ?: ($row['AÑO'] ?: '2025')));
                                    if ($anioVal > $latestYearInFile)
                                        $latestYearInFile = $anioVal;

                                    // Lógica Smart: Si estamos en modo inteligente (por defecto) y esta fila es de un año anterior
                                    // Y verificamos que la BD ya tiene al menos 50,000 registros para dicho año histórico,
                                    // omitimos la importación de este año para EVITAR INFLAR DATOS CON DUPLICADOS INVISIBLES.
                                    if ($modo_historico === 'smart' && $latestYearInFile > 0 && $anioVal < $latestYearInFile) {
                                        if (($db_years[$anioVal] ?? 0) > 50000) {
                                            continue;
                                        }
                                    }

                                    // ============================================================
                                    // REGLA DE HOJAS INTELIGENTE v16.0 (con categorías completas)
                                    // ============================================================
        
                                    if ($i == 1) {
                                        // HOJA 1: Contiene TODAS las categorías (Delitos, Faltas, Violencia, Niños)
                                        // Solo importamos las categorías NO-DELITOS, ya que los delitos
                                        // vienen con más detalle de Hojas 5, 6 y 7.
                                        $catRaw = strtolower(trim($row['ES_DELITO_X'] ?? ($row['ES_DELITO_GENERAL'] ?? '')));
                                        if (strpos($catRaw, '1.') === 0 || $catRaw == '' || strpos($catRaw, 'delito') !== false)
                                            continue; // Saltamos delitos: vienen de hojas 5/6/7
        
                                        // Mapear categoría al formato normalizado de la BD
                                        if (strpos($catRaw, '2.') === 0 || strpos($catRaw, 'falta') !== false)
                                            $cat_general = '2.FALTAS';
                                        elseif (strpos($catRaw, '4.') === 0 || strpos($catRaw, 'violencia') !== false)
                                            $cat_general = '4.VIOLENCIA';
                                        elseif (strpos($catRaw, '3.') === 0 || strpos($catRaw, 'ni') !== false || strpos($catRaw, 'adolesc') !== false)
                                            $cat_general = '3.NIÑOS Y ADOLESCENTES';
                                        else
                                            $cat_general = 'OTROS';

                                        $cant = (int) ($row['N_DIST_ID_DGC'] ?: ($row['CANTIDAD'] ?: 1));
                                        $dpto = normalize_str($row['DPTO_HECHO_NEW'] ?: ($row['DPTO_HECHO'] ?: ''));
                                        $prov = normalize_str($row['PROV_HECHO'] ?? '');
                                        $dist = normalize_str($row['DIST_HECHO'] ?? '');
                                        $tipo = normalize_str($cat_general);
                                        $mod = $cat_general;
                                        // Hash sin cantidad para permitir actualizaciones de conteo
                                        $hash = md5('SIDPOL_SHEET1_' . $anioVal . '_' . ($row['MES'] ?: 1) . '_' . $dpto . '_' . $cat_general);
                                        try {
                                            $stmt->execute([$fuente, $anioVal, ($row['MES'] ?: 1), '', null, '', $dpto, $prov, $dist, substr($cat_general, 0, 100), '', substr($cat_general, 0, 100), $cat_general, $cant, $hash]);
                                            if ($stmt->rowCount() > 0)
                                                $count++;
                                        } catch (PDOException $e) {
                                        }
                                        if ($rowNum % 500 == 0)
                                            logMsg("Hoja #1 - Fila $rowNum...", $startProgress);
                                        continue; // Ya procesada, pasar a siguiente fila
                                    }

                                    // Hojas 5, 6, 7: Solo Delitos con detalle geográfico completo
                                    // REGLA DINÁMICA: Sheet 7=Current, Sheet 6=Previous, Sheet 5=Rest
                                    $refYear = ($latestYearInFile > 0) ? $latestYearInFile : 2025;

                                    if ($i == 7 && $anioVal < $refYear)
                                        continue;
                                    if ($i == 6 && $anioVal != ($refYear - 1))
                                        continue;
                                    if ($i == 5 && $anioVal >= ($refYear - 1))
                                        continue;

                                    $tipo_raw = strtoupper($row['TIPO'] ?? ($row['P_MODALIDADES'] ?? ''));
                                    if (strpos($tipo_raw, 'TOTAL') !== false || strpos($tipo_raw, '1.') === 0 || strpos($tipo_raw, '2.') === 0)
                                        continue;
                                    $cant = (int) ($row['N_DIST_ID_DGC'] ?: ($row['CANTIDAD'] ?: 1));
                                    if (!empty($row['TIPO']))
                                        $tipo = $row['TIPO'];
                                    elseif (!empty($row['P_MODALIDADES']))
                                        $tipo = $row['P_MODALIDADES'];
                                    else
                                        $tipo = 'Otros';
                                    $mod = !empty($row['MODALIDAD']) ? $row['MODALIDAD'] : $tipo;
                                    $tipo = normalize_str(map_crime_type($tipo));
                                    $mod = normalize_str($mod);
                                    $subtipo = normalize_str($row['SUB_TIPO'] ?? '');
                                    $dpto = normalize_str($row['DPTO_HECHO_NEW'] ?: ($row['DPTO_HECHO'] ?: ''));
                                    $prov = normalize_str($row['PROV_HECHO'] ?? '');
                                    $dist = normalize_str($row['DIST_HECHO'] ?? '');
                                    // Hash sin cantidad para permitir actualizaciones de conteo
                                    $hash = md5($fuente . '_' . $anioVal . '_' . ($row['MES'] ?: 1) . '_' . ($row['UBIGEO_HECHO'] ?: '') . '_' . $tipo . '_' . $mod . '_' . ($row['DIA_SEMANA'] ?? '') . '_' . ($row['HORA'] ?? ''));
                                    try {
                                        $stmt->execute([$fuente, $anioVal, ($row['MES'] ?: 1), ($row['DIA_SEMANA'] ?? ''), ($row['HORA'] ?? null), ($row['UBIGEO_HECHO'] ?: ''), $dpto, $prov, $dist, substr($tipo, 0, 100), substr($subtipo, 0, 100), substr($mod, 0, 100), '1.DELITOS', $cant, $hash]);
                                        if ($stmt->rowCount() > 0)
                                            $count++;
                                    } catch (PDOException $e) {
                                    }
                                    if ($rowNum % 500 == 0)
                                        logMsg("Hoja #$i - Fila $rowNum...", $startProgress + ($rowNum / 10000) * 10);
                                }
                            }
                        }
                        $xml->close();
                        logMsg("✅ Hoja #$i completada ($count).");
                        $totalGlobal += $count;
                    }
                }
                logMsg("🎉 PROCESO COMPLETADO: $totalGlobal registros.", 100);
                echo "<h2>🎉 PROCESO COMPLETADO: $totalGlobal registros insertados.</h2>";
            }
        } else {
            echo "<b>No se proporcionó archivo.</b>";
        }
        if ($tempFile && file_exists($tempFile))
            unlink($tempFile);
        ?>
    </div>
    <div style="margin-top:20px;"><a href="panel.php"
            style="padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;">Volver
            al Panel</a></div>
</body>

</html>