<?php
/*
 * DASHBOARD SIDPOL - SQL INTEGRADO (VERSIÓN COMPLETA)
 * Autor: Michael Espinoza Coila
 * Fecha: 2026-02-11
 */

// --- 1. CONFIGURACIÓN Y CONEXIÓN ---
ini_set('display_errors', 0);
error_reporting(E_ALL);
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish');
date_default_timezone_set('America/Lima');
require_once 'admin/db.php';

function format_num($num)
{
    return number_format($num, 0, ',', '.');
}

// --- 2. OBTENER OPCIONES PARA SELECTORES (Desde BD) ---
try {
    // Años
    $anios = $pdo->query("SELECT DISTINCT anio FROM sidpol_hechos ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
    // Departamentos
    $dptos = $pdo->query("SELECT DISTINCT dpto_hecho FROM sidpol_hechos WHERE dpto_hecho != '' ORDER BY dpto_hecho")->fetchAll(PDO::FETCH_COLUMN);
    // Tipos Generales
    $tipos_generales = $pdo->query("SELECT DISTINCT es_delito_general FROM sidpol_hechos ORDER BY es_delito_general")->fetchAll(PDO::FETCH_COLUMN);
    // Fuentes (SIDPOL vs MPFN)
    $fuentes = $pdo->query("SELECT DISTINCT fuente FROM sidpol_hechos ORDER BY fuente")->fetchAll(PDO::FETCH_COLUMN);

    // PARA LOS SELECTORES DEPENDIENTES (Tipo > Subtipo > Modalidad)
    // Traemos todo el catálogo DISTINTO para armar el mapa JS.
    // OJO: Si son muchos datos, esto debería ser AJAX, pero para mantener la funcionalidad original:
    $sql_catalogo = "SELECT DISTINCT tipo_delito, sub_tipo_delito, modalidad_delito FROM sidpol_hechos WHERE tipo_delito != ''";
    $catalogo = $pdo->query($sql_catalogo)->fetchAll();

    $mapa_delitos_js = [];
    foreach ($catalogo as $row) {
        $t = $row['tipo_delito'];
        $s = $row['sub_tipo_delito'];
        $m = $row['modalidad_delito'];
        if ($t && $s && $m) {
            $mapa_delitos_js[$t][$s][] = $m;
        }
    }
    // Limpiar duplicados y ordenar arrays finales del mapa
    foreach ($mapa_delitos_js as $t => &$subtipos) {
        foreach ($subtipos as $s => &$mods) {
            $mods = array_values(array_unique($mods));
            sort($mods);
        }
    }

} catch (PDOException $e) {
    die("Error cargando filtros: " . $e->getMessage());
}

// --- 3. PROCESAR FILTROS RECIBIDOS ---
$filtros = [
    'anio' => $_GET['filtro_anio'] ?? ($anios[0] ?? date('Y')),
    'mes' => $_GET['filtro_mes'] ?? 'todos',
    'dpto' => $_GET['filtro_dpto'] ?? 'PUNO',
    'prov' => $_GET['filtro_prov'] ?? 'todos',
    'dist' => $_GET['filtro_dist'] ?? 'todos',
    'tipo_general' => $_GET['filtro_tipo_general'] ?? 'todos',
    'comparar' => $_GET['filtro_comparar'] ?? 'ninguno',
    'anio_comp' => $_GET['filtro_anio_comp'] ?? 'ninguno',
    'tipo_delito' => $_GET['filtro_tipo_delito'] ?? 'todos',
    'subtipo_delito' => $_GET['filtro_subtipo_delito'] ?? 'todos',
    'modalidad_delito' => $_GET['filtro_modalidad_delito'] ?? 'todos',
    'fuente' => $_GET['filtro_fuente'] ?? 'SIDPOL',
    'comp_prov' => $_GET['filtro_comp_prov'] ?? 'ninguno',
    'comp_dist' => $_GET['filtro_comp_dist'] ?? 'ninguno',
];
$target_dpto = $filtros['dpto'];
$meses_disponibles = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

// --- 4. CONSULTA SQL PRINCIPAL ---
$sql = "SELECT * FROM sidpol_hechos WHERE 1=1";
$params = [];

// Aplicar filtros
if ($filtros['anio'] != 'todos') {
    $sql .= " AND anio = :anio";
    $params[':anio'] = $filtros['anio'];
}
if ($filtros['mes'] != 'todos') {
    $sql .= " AND mes = :mes";
    $params[':mes'] = $filtros['mes'];
}
if ($target_dpto != 'TOTAL PERU') {
    $sql .= " AND dpto_hecho = :dpto";
    $params[':dpto'] = $target_dpto;
}
if ($filtros['prov'] != 'todos') {
    $sql .= " AND prov_hecho = :prov";
    $params[':prov'] = $filtros['prov'];
}
if ($filtros['dist'] != 'todos') {
    $sql .= " AND dist_hecho = :dist";
    $params[':dist'] = $filtros['dist'];
}
if ($filtros['fuente'] != 'todos') {
    $sql .= " AND fuente = :fnt";
    $params[':fnt'] = $filtros['fuente'];
}

// Filtros de Tipo/Subtipo/Modalidad (Solo aplican si NO es KPI Secundario general, o si se selecciona explícitamente)
// La lógica original separaba 'raw_delitos' de 'kpi_secundario'. Aquí está todo en una tabla.
// Si seleccionamos un Tipo Delito específico, restringimos toda la consulta.
if ($filtros['tipo_general'] != 'todos') {
    $sql .= " AND es_delito_general = :gen";
    $params[':gen'] = $filtros['tipo_general'];
}
if ($filtros['tipo_delito'] != 'todos') {
    $sql .= " AND tipo_delito = :td";
    $params[':td'] = $filtros['tipo_delito'];
}
if ($filtros['subtipo_delito'] != 'todos') {
    $sql .= " AND sub_tipo_delito = :std";
    $params[':std'] = $filtros['subtipo_delito'];
}
if ($filtros['modalidad_delito'] != 'todos') {
    $sql .= " AND modalidad_delito = :mod";
    $params[':mod'] = $filtros['modalidad_delito'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// --- 5. CALCULAR ESTADÍSTICAS (PHP) ---
$stats = [
    'total_general' => 0,
    'total_delitos' => 0,
    'total_violencia' => 0,
    'total_faltas' => 0,
    'evolucion' => [],
    'tendencia' => ['texto' => 'Estable', 'icono' => '▬', 'clase' => 'azul']
];
$composicion = [];
$modalidades = [];

foreach ($data as $row) {
    $stats['total_general'] += $row['cantidad'];
    if ($row['es_delito_general'] == '1.DELITOS')
        $stats['total_delitos'] += $row['cantidad'];
    elseif ($row['es_delito_general'] == '2.FALTAS')
        $stats['total_faltas'] += $row['cantidad'];
    elseif ($row['es_delito_general'] == '4.VIOLENCIA')
        $stats['total_violencia'] += $row['cantidad'];
    elseif ($row['es_delito_general'] == '3. NIÑOS Y ADOLESCENTES' || $row['es_delito_general'] == 'OTROS')
        @$stats['total_otros_cat'] += $row['cantidad'];

    // Evolución mensual
    $k = $row['anio'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
    @$stats['evolucion'][$k] += $row['cantidad'];

    // Composición
    @$composicion[$row['es_delito_general']] += $row['cantidad'];

    // Modalidades
    if ($row['modalidad_delito'])
        @$modalidades[$row['modalidad_delito']] += $row['cantidad'];
}
ksort($stats['evolucion']);
arsort($composicion);
arsort($modalidades);
$top_modalidades = array_slice($modalidades, 0, 10);
$total_gral = array_sum($composicion); // Total registros filtrados

// Tendencia y Mes Pico
if (count($stats['evolucion']) > 0) {
    $max_v = max($stats['evolucion']);
    $mes_k = array_search($max_v, $stats['evolucion']);
    $stats['mes_pico'] = ucfirst(strftime('%B %Y', strtotime($mes_k . '-01'))) . " (" . format_num($max_v) . ")";

    if (count($stats['evolucion']) >= 2) {
        $vals = array_values(array_slice($stats['evolucion'], -2));
        if ($vals[1] > $vals[0] * 1.1)
            $stats['tendencia'] = ['texto' => 'En Aumento', 'icono' => '▲', 'clase' => 'rojo'];
        elseif ($vals[1] < $vals[0] * 0.9)
            $stats['tendencia'] = ['texto' => 'En Disminución', 'icono' => '▼', 'clase' => 'verde'];
    }
}

// --- 6. RANKING Y COMPARATIVAS (Consultas Extra) ---
$ranking = [];
$puno_rank = 0;
if ($target_dpto != 'TOTAL PERU') {
    $sql_r = "SELECT dpto_hecho, SUM(cantidad) as c FROM sidpol_hechos WHERE es_delito_general='1.DELITOS' AND anio=:a GROUP BY dpto_hecho ORDER BY c DESC";
    $stmt_r = $pdo->prepare($sql_r);
    $stmt_r->execute([':a' => $filtros['anio']]);
    $ranking = $stmt_r->fetchAll(PDO::FETCH_KEY_PAIR);
    $i = 1;
    foreach ($ranking as $d => $c) {
        if ($d == $target_dpto) {
            $puno_rank = $i;
            break;
        }
        $i++;
    }
}

$comp_dpto_val = 0;
if ($filtros['comparar'] != 'ninguno') {
    $sql_c = "SELECT SUM(cantidad) FROM sidpol_hechos WHERE anio=:a AND dpto_hecho=:d AND es_delito_general='1.DELITOS'";
    $stmt_c = $pdo->prepare($sql_c);
    $stmt_c->execute([':a' => $filtros['anio'], ':d' => $filtros['comparar']]);
    $comp_dpto_val = $stmt_c->fetchColumn();
}

$comp_anio_val = 0;
if ($filtros['anio_comp'] != 'ninguno') {
    $sql_ca = "SELECT SUM(cantidad) FROM sidpol_hechos WHERE anio=:a";
    $p_ca = [':a' => $filtros['anio_comp']];
    if ($target_dpto != 'TOTAL PERU') {
        $sql_ca .= " AND dpto_hecho=:d";
        $p_ca[':d'] = $target_dpto;
    }
    $sql_ca .= " AND es_delito_general='1.DELITOS'"; // Comparar solo delitos por defecto
    $stmt_ca = $pdo->prepare($sql_ca);
    $stmt_ca->execute($p_ca);
    $comp_anio_val = $stmt_ca->fetchColumn();
}


// --- 7. VARIABLES VISUALES ---
// Maximos para barras
$max_evol = $stats['evolucion'] ? max($stats['evolucion']) : 0;
$max_mod = $top_modalidades ? max($top_modalidades) : 0;
$max_rank = $ranking ? max($ranking) : 0;
$max_comp_dpto = max($stats['total_delitos'], $comp_dpto_val);
$max_comp_anio = max($stats['total_delitos'], $comp_anio_val);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SIDPOL</title>
    <style>
        body {
            font-family: -apple-system, system-ui, sans-serif;
            background: #f0f2f5;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: auto;
        }

        h1 {
            color: #004a99;
            margin-bottom: 20px;
        }

        /* Panel Filtros Grid */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filters label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            margin-bottom: 5px;
            color: #555;
        }

        .filters select,
        .filters button,
        .btn-limpiar {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .filters button {
            background: #007bff;
            color: white;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .filters button:hover {
            background: #0056b3;
        }

        .btn-limpiar {
            background: #6c757d;
            color: white;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        /* KPIs */
        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .card h2 {
            font-size: 2.5em;
            margin: 10px 0;
            color: #333;
        }

        .card p {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            font-weight: 600;
            margin: 0;
        }

        /* Bordes de color para KPIs */
        .k-delito {
            border-bottom: 4px solid #dc3545;
        }

        .k-delito h2 {
            color: #dc3545;
        }

        .k-violencia {
            border-bottom: 4px solid #ffc107;
        }

        .k-violencia h2 {
            color: #e9a300;
        }

        .k-falta {
            border-bottom: 4px solid #17a2b8;
        }

        .k-falta h2 {
            color: #17a2b8;
        }

        /* Gráficos Grid */
        .charts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .chart-box h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            font-size: 1.1em;
            color: #444;
        }

        /* Barras */
        ul.bar-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 300px;
            overflow-y: auto;
        }

        ul.bar-list li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lbl {
            flex: 0 0 140px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bar-bg {
            flex: 1;
            background: #f1f1f1;
            height: 18px;
            border-radius: 4px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: #007bff;
            color: white;
            font-size: 11px;
            line-height: 18px;
            text-align: right;
            padding-right: 5px;
            white-space: nowrap;
        }

        @media (max-width: 1000px) {
            .charts {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        // Catálogo de delitos (relativamente pequeño, OK inline)
        const mapa = <?= json_encode($mapa_delitos_js) ?>;
        const sel = {
            t: "<?= $filtros['tipo_delito'] ?>",
            s: "<?= $filtros['subtipo_delito'] ?>",
            m: "<?= $filtros['modalidad_delito'] ?>",
            prov: "<?= $filtros['prov'] ?>",
            dist: "<?= $filtros['dist'] ?>",
            comp_dpto: "<?= $filtros['comparar'] ?>",
            comp_prov: "<?= $filtros['comp_prov'] ?>",
            comp_dist: "<?= $filtros['comp_dist'] ?>"
        };

        function updateSelect(id, options, selected, defaultLabel = 'Todos', defaultVal = 'todos') {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = `<option value="${defaultVal}">${defaultLabel}</option>`;
            options.forEach(opt => {
                const o = document.createElement('option');
                o.value = o.text = opt;
                if (opt === selected) o.selected = true;
                el.appendChild(o);
            });
        }

        // --- Selectores de Tipo/Subtipo/Modalidad (inline, catálogo pequeño) ---
        function updateSubs() {
            const t = document.getElementById('filtro_tipo_delito')?.value || 'todos';
            let subs = [];
            if (t === 'todos') {
                Object.values(mapa).forEach(s_map => subs.push(...Object.keys(s_map)));
            } else if (mapa[t]) {
                subs = Object.keys(mapa[t]);
            }
            subs = [...new Set(subs)].sort();
            updateSelect('filtro_subtipo_delito', subs, sel.s);
            updateMods();
        }

        function updateMods() {
            const t = document.getElementById('filtro_tipo_delito')?.value || 'todos';
            const s = document.getElementById('filtro_subtipo_delito')?.value || 'todos';
            let mods = [];
            if (t === 'todos' && s === 'todos') {
                Object.values(mapa).forEach(sm => Object.values(sm).forEach(ms => mods.push(...ms)));
            } else if (t !== 'todos' && s === 'todos' && mapa[t]) {
                Object.values(mapa[t]).forEach(ms => mods.push(...ms));
            } else if (mapa[t] && mapa[t][s]) {
                mods = mapa[t][s];
            }
            mods = [...new Set(mods)].sort();
            updateSelect('filtro_modalidad_delito', mods, sel.m);
        }

        // --- Selectores Geográficos vía AJAX (rápido, sin cargar todo el mapa) ---
        async function fetchJSON(url) {
            try {
                const res = await fetch(url);
                return await res.json();
            } catch (e) { return []; }
        }

        async function updateProvs(triggerDists = true) {
            const dpto = document.getElementById('filtro_dpto')?.value || '';
            if (!dpto || dpto === 'TOTAL PERU') {
                updateSelect('filtro_prov', [], sel.prov);
                if (triggerDists) updateDists();
                return;
            }
            const provs = await fetchJSON(`admin/api_geo.php?action=provincias&dpto=${encodeURIComponent(dpto)}`);
            updateSelect('filtro_prov', provs, sel.prov);
            if (triggerDists) await updateDists();
        }

        async function updateDists() {
            const dpto = document.getElementById('filtro_dpto')?.value || '';
            const prov = document.getElementById('filtro_prov')?.value || 'todos';
            if (!dpto || dpto === 'TOTAL PERU') {
                updateSelect('filtro_dist', [], sel.dist);
                return;
            }
            let url = `admin/api_geo.php?action=distritos&dpto=${encodeURIComponent(dpto)}`;
            if (prov !== 'todos') url += `&prov=${encodeURIComponent(prov)}`;
            const dists = await fetchJSON(url);
            updateSelect('filtro_dist', dists, sel.dist);
        }

        // --- Selectores Geográficos de COMPARACIÓN (usan el dpto de comparación) ---
        async function updateCompProvs(triggerDists = true) {
            const dptoComp = document.getElementById('filtro_comparar')?.value || 'ninguno';
            if (dptoComp === 'ninguno' || dptoComp === 'TOTAL PERU') {
                updateSelect('filtro_comp_prov', [], sel.comp_prov, 'Ninguno', 'ninguno');
                if (triggerDists) updateCompDists();
                return;
            }
            const provs = await fetchJSON(`admin/api_geo.php?action=provincias&dpto=${encodeURIComponent(dptoComp)}`);
            // Cambiamos "Ninguno" por "Todo el Departamento" para mayor claridad
            updateSelect('filtro_comp_prov', provs, sel.comp_prov, 'Todo el Departamento', 'ninguno');
            if (triggerDists) await updateCompDists();
        }

        async function updateCompDists() {
            const dptoComp = document.getElementById('filtro_comparar')?.value || 'ninguno';
            const provComp = document.getElementById('filtro_comp_prov')?.value || 'todos';
            if (dptoComp === 'ninguno' || dptoComp === 'TOTAL PERU') {
                updateSelect('filtro_comp_dist', [], sel.comp_dist, 'Ninguno', 'ninguno');
                return;
            }
            let url = `admin/api_geo.php?action=distritos&dpto=${encodeURIComponent(dptoComp)}`;
            if (provComp !== 'todos' && provComp !== 'ninguno') url += `&prov=${encodeURIComponent(provComp)}`;
            const dists = await fetchJSON(url);
            // Cambiamos "Ninguno" por "Toda la Provincia"
            updateSelect('filtro_comp_dist', dists, sel.comp_dist, 'Toda la Provincia', 'ninguno');
        }

        // --- Inicialización: SOLO después de que el DOM esté listo ---
        document.addEventListener('DOMContentLoaded', async () => {
            // Tipo/Subtipo/Modalidad
            updateSelect('filtro_tipo_delito', Object.keys(mapa).sort(), sel.t);
            updateSubs();

            // Geográficos Base y Comparación
            await updateProvs();
            await updateCompProvs();

            // Event Listeners Base
            document.getElementById('filtro_tipo_delito')?.addEventListener('change', () => {
                sel.s = 'todos'; sel.m = 'todos'; updateSubs();
            });
            document.getElementById('filtro_subtipo_delito')?.addEventListener('change', () => {
                sel.m = 'todos'; updateMods();
            });
            document.getElementById('filtro_prov')?.addEventListener('change', () => {
                sel.dist = 'todos'; updateDists();
            });

            // Event Listeners Comparación
            document.getElementById('filtro_comparar')?.addEventListener('change', () => {
                sel.comp_prov = 'ninguno'; sel.comp_dist = 'ninguno'; updateCompProvs();
            });
            document.getElementById('filtro_comp_prov')?.addEventListener('change', () => {
                sel.comp_dist = 'ninguno'; updateCompDists();
            });
        });
    </script>
</head>

<body>

    <div class="container">
        <?php
        $titulo_ubicacion = $target_dpto;
        if ($filtros['prov'] != 'todos')
            $titulo_ubicacion .= ' / ' . $filtros['prov'];
        if ($filtros['dist'] != 'todos')
            $titulo_ubicacion .= ' / ' . $filtros['dist'];
        ?>
        <h1>📈 Dashboard sobre denuncias <small>Estadísticas de <?= $titulo_ubicacion ?></small></h1>

        <div class="filters">
            <form method="GET">
                <div>
                    <label>Departamento / Región:</label>
                    <select name="filtro_dpto" id="filtro_dpto" onchange="this.form.submit()">
                        <option value="TOTAL PERU" <?= $target_dpto == 'TOTAL PERU' ? 'selected' : '' ?>>TOTAL PERU
                        </option>
                        <?php foreach ($dptos as $d): ?>
                            <option value="<?= $d ?>" <?= $target_dpto == $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Provincia:</label>
                    <select name="filtro_prov" id="filtro_prov"></select>
                </div>
                <div>
                    <label>Distrito:</label>
                    <select name="filtro_dist" id="filtro_dist"></select>
                </div>

                <div>
                    <label>Año Base:</label>
                    <select name="filtro_anio">
                        <?php foreach ($anios as $a): ?>
                            <option value="<?= $a ?>" <?= $filtros['anio'] == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Comparar Año con:</label>
                    <select name="filtro_anio_comp">
                        <option value="ninguno">Ninguno</option>
                        <?php foreach ($anios as $a):
                            if ($a == $filtros['anio'])
                                continue; ?>
                            <option value="<?= $a ?>" <?= $filtros['anio_comp'] == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Mes:</label>
                    <select name="filtro_mes">
                        <option value="todos">Todos</option>
                        <?php foreach ($meses_disponibles as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $filtros['mes'] == $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Tipo General:</label>
                    <select name="filtro_tipo_general">
                        <option value="todos">Todos</option>
                        <?php foreach ($tipos_generales as $t): ?>
                            <option value="<?= $t ?>" <?= $filtros['tipo_general'] == $t ? 'selected' : '' ?>><?= $t ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label title="Suma de datos SIDPOL y MPFN sin duplicados">Fuente (Institución) <sup
                            style="color:#007bff; cursor:help;">(?)</sup>:</label>
                    <select name="filtro_fuente">
                        <option value="todos"
                            title="Consolidado: Suma aritmética de denuncias de todas las fuentes disponibles (SIDPOL + MPFN)">
                            Todas (Consolidado)</option>
                        <?php foreach ($fuentes as $f): ?>
                            <option value="<?= $f ?>" <?= $filtros['fuente'] == $f ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display:block; font-size:10px; color:#888; margin-top:4px;">
                        * <b>Consolidado</b>: Suma de denuncias policiales (SIDPOL) y registros fiscales (MPFN).
                    </small>
                </div>

                <div>
                    <label>Comparar Dpto con:</label>
                    <select name="filtro_comparar" id="filtro_comparar" <?= $target_dpto == 'TOTAL PERU' ? 'disabled' : '' ?>>
                        <option value="ninguno">Ninguno</option>
                        <?php foreach ($dptos as $d):
                            if ($d == $target_dpto)
                                continue; ?>
                            <option value="<?= $d ?>" <?= $filtros['comparar'] == $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Comparar Prov con:</label>
                    <select name="filtro_comp_prov" id="filtro_comp_prov"></select>
                </div>
                <div>
                    <label>Comparar Dist con:</label>
                    <select name="filtro_comp_dist" id="filtro_comp_dist"></select>
                </div>

                <!-- SELECTORES DEPENDIENTES JS -->
                <div><label>Tipo de Delito:</label><select name="filtro_tipo_delito" id="filtro_tipo_delito"></select>
                </div>
                <div><label>Sub-Tipo:</label><select name="filtro_subtipo_delito" id="filtro_subtipo_delito"></select>
                </div>
                <div><label>Modalidad:</label><select name="filtro_modalidad_delito"
                        id="filtro_modalidad_delito"></select></div>

                <div>
                    <label>&nbsp;</label>
                    <button type="submit">Filtrar</button>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <a href="index.php" class="btn-limpiar">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- KPIS -->
        <div class="kpis">
            <div class="card k-delito">
                <h2><?php
                $mostrar_total = ($filtros['tipo_general'] == 'todos') ? $stats['total_general'] : $stats['total_delitos'];
                echo format_num($mostrar_total);
                ?></h2>
                <p>Total Delitos</p>
            </div>
            <div class="card k-violencia">
                <h2><?= format_num($stats['total_violencia']) ?></h2>
                <p>Violencia</p>
            </div>
            <div class="card k-falta">
                <h2><?= format_num($stats['total_faltas']) ?></h2>
                <p>Faltas</p>
            </div>
            <div class="card k-tendencia">
                <h2 style="font-size:1.8em" class="<?= $stats['tendencia']['clase'] ?>">
                    <?= $stats['tendencia']['icono'] ?> <?= $stats['tendencia']['texto'] ?>
                </h2>
                <p>Tendencia</p>
            </div>
        </div>

        <div class="charts">
            <div class="chart-box">
                <h3>Composición</h3>
                <ul class="bar-list">
                    <?php foreach ($composicion as $k => $v):
                        $pct = $total_gral ? ($v / $total_gral) * 100 : 0; ?>
                        <li>
                            <span class="lbl"><?= $k ?></span>
                            <div class="bar-bg">
                                <div class="bar" style="width:<?= $pct ?>%; background-color: #6c757d;">
                                    <?= format_num($v) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="chart-box">
                <h3>Top Modalidades</h3>
                <ul class="bar-list">
                    <?php foreach ($top_modalidades as $k => $v):
                        $pct = $max_mod ? ($v / $max_mod) * 100 : 0; ?>
                        <li>
                            <span class="lbl" title="<?= $k ?>"><?= $k ?></span>
                            <div class="bar-bg">
                                <div class="bar" style="width:<?= $pct ?>%"><?= format_num($v) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="chart-box">
                <h3>Ranking Nacional</h3>
                <?php if ($target_dpto == 'TOTAL PERU'): ?>
                    <p style="text-align:center;color:#888;margin-top:40px;">No aplica</p>
                <?php else: ?>
                    <h2 style="text-align:center;color:#004a99;font-size:3em;margin:0 0 10px 0;"><?= $puno_rank ?></h2>
                    <ul class="bar-list">
                        <?php
                        $top5 = array_slice($ranking, 0, 5, true);
                        foreach ($top5 as $d => $v):
                            $pct = $max_rank ? ($v / $max_rank) * 100 : 0;
                            $color = ($d == $target_dpto) ? '#007bff' : '#dc3545';
                            ?>
                            <li>
                                <span class="lbl"
                                    style="<?= $d == $target_dpto ? 'font-weight:bold;color:#007bff' : '' ?>"><?= $d ?></span>
                                <div class="bar-bg">
                                    <div class="bar" style="width:<?= $pct ?>%; background:<?= $color ?>"><?= format_num($v) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($filtros['comparar'] != 'ninguno'): ?>
                <div class="chart-box">
                    <h3>Vs <?= $filtros['comparar'] ?></h3>
                    <ul class="bar-list">
                        <li><span class="lbl"><?= $target_dpto ?></span>
                            <div class="bar-bg">
                                <div class="bar"
                                    style="width:<?= $max_comp_dpto ? ($stats['total_delitos'] / $max_comp_dpto) * 100 : 0 ?>%">
                                    <?= format_num($stats['total_delitos']) ?>
                                </div>
                            </div>
                        </li>
                        <li><span class="lbl"><?= $filtros['comparar'] ?></span>
                            <div class="bar-bg">
                                <div class="bar"
                                    style="width:<?= $max_comp_dpto ? ($comp_dpto_val / $max_comp_dpto) * 100 : 0 ?>%; background:#dc3545">
                                    <?= format_num($comp_dpto_val) ?>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($filtros['anio_comp'] != 'ninguno'): ?>
                <div class="chart-box">
                    <h3>Vs Año <?= $filtros['anio_comp'] ?></h3>
                    <ul class="bar-list">
                        <li><span class="lbl"><?= $filtros['anio'] ?></span>
                            <div class="bar-bg">
                                <div class="bar"
                                    style="width:<?= $max_comp_anio ? ($stats['total_delitos'] / $max_comp_anio) * 100 : 0 ?>%">
                                    <?= format_num($stats['total_delitos']) ?>
                                </div>
                            </div>
                        </li>
                        <li><span class="lbl"><?= $filtros['anio_comp'] ?></span>
                            <div class="bar-bg">
                                <div class="bar"
                                    style="width:<?= $max_comp_anio ? ($comp_anio_val / $max_comp_anio) * 100 : 0 ?>%; background:#ffc107">
                                    <?= format_num($comp_anio_val) ?>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="chart-box" style="grid-column: 1 / -1;">
                <h3>Evolución Mensual</h3>
                <ul class="bar-list">
                    <?php foreach ($stats['evolucion'] as $mes => $v):
                        $pct = $max_evol ? ($v / $max_evol) * 100 : 0;
                        $lbl = strftime('%b %Y', strtotime($mes . '-01'));
                        ?>
                        <li>
                            <span class="lbl" style="flex:0 0 80px"><?= $lbl ?></span>
                            <div class="bar-bg">
                                <div class="bar"
                                    style="width:<?= $pct ?>%; background:#28a745; text-align:left; padding-left:5px;">
                                    <?= format_num($v) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <footer
        style="margin-top: 50px; padding: 40px 20px; color: #444; background: #fff; border-top: 1px solid #ddd; border-radius: 8px 8px 0 0;">
        <div
            style="max-width: 1100px; margin: 0 auto; text-align: left; display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">

            <!-- Columna 1: Disclaimer -->
            <div style="font-size: 13px; line-height: 1.6;">
                <h3 style="margin-top:0; color: #d9534f;">⚠️ Disclaimer (Descargo de Responsabilidad)</h3>
                <p><strong>Aclaración sobre el uso de la información:</strong></p>
                <ol style="padding-left: 20px;">
                    <li><strong>Naturaleza de los Datos:</strong> La precisión, integridad y actualidad dependen
                        exclusivamente de las fuentes originales (SIDPOL y MPFN).</li>
                    <li><strong>Uso No Oficial:</strong> Este sistema no es un canal oficial de comunicación del
                        Ministerio del Interior ni del Ministerio Público. Los reportes son referenciales.</li>
                    <li><strong>Responsabilidad:</strong> El desarrollador no se hace responsable por interpretaciones
                        erróneas o decisiones basadas en el uso de esta herramienta.</li>
                    <li><strong>Privacidad:</strong> Se procesan datos estadísticos anonimizados; no contiene
                        información que identifique personas naturales.</li>
                </ol>
            </div>

            <!-- Columna 2: Notas Técnicas / Ayuda -->
            <div
                style="font-size: 13px; line-height: 1.6; background: #f8f9fa; padding: 20px; border-left: 4px solid #0056b3;">
                <h3 style="margin-top:0; color: #0056b3;">💡 Notas de Uso / Ayuda</h3>
                <p><strong>¿Cifras en cero?</strong> Si un indicador muestra "0" para un año o delito específico:</p>
                <ul style="padding-left: 20px;">
                    <li>Cambie la <b>Fuente (Institución)</b> a <i>"MPFN"</i> o <i>"CONSOLIDADO"</i>. Algunos años
                        antiguos (como 2020) o categorías nuevas pueden no estar catalogados bajo SIDPOL.</li>
                    <li>Verifique que no haya filtros contradictorios seleccionados simultáneamente.</li>
                    <li>Utilice la opción <i>"Limpiar"</i> para restablecer los filtros predeterminados.</li>
                </ul>
            </div>

        </div>

        <div
            style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888;">
            <p><strong>Fuentes oficiales:</strong>
                <a href="https://observatorio.mininter.gob.pe/" target="_blank" style="color: #666;">SIDPOL
                    (Mininter)</a> |
                <a href="https://www.datosabiertos.gob.pe/dataset/mpfn-delitos-denunciados" target="_blank"
                    style="color: #666;">MPFN (Ministerio Público)</a>
            </p>
            <p>© 2026 Michael Espinoza Coila - Asistido por Inteligencia Artificial (Antigravity & Claude 4.5 Opus).</p>
        </div>
    </footer>

</body>

</html>