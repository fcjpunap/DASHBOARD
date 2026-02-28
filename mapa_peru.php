<?php
/**
 * MAPA INTERACTIVO DE PERÚ POR DEPARTAMENTOS
 */
require_once 'admin/db.php';

$anio = $_GET['anio'] ?? 2024;
$fuente = $_GET['fuente'] ?? 'SIDPOL';

// Obtener datos por departamento
$sql = "SELECT dpto_hecho, SUM(cantidad) as total FROM sidpol_hechos WHERE anio=$anio AND fuente='$fuente' AND dpto_hecho != 'TOTAL PERU' GROUP BY dpto_hecho";
$res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

// Normalizar nombres para el mapa
$map_data = [];
foreach ($res as $d => $v) {
    $d_norm = strtoupper(str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'N'], $d));
    $map_data[$d_norm] = (int) $v;
}

$max_val = count($map_data) > 0 ? max($map_data) : 1;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mapa de Criminalidad - Perú
        <?= $anio ?>
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f0f4f8;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            color: #004a99;
            margin: 0;
            font-size: 28px;
        }

        .legend {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 14px;
        }

        .map-container {
            display: flex;
            gap: 40px;
            align-items: center;
        }

        svg {
            width: 500px;
            height: 700px;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.1));
        }

        path {
            fill: #e0e0e0;
            stroke: #fff;
            stroke-width: 1;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        path:hover {
            stroke-width: 2;
            filter: brightness(0.9);
        }

        .tooltip {
            position: fixed;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 14px;
            z-index: 1000;
        }

        .btn-back {
            background: #1a1a1a;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .dpto-list {
            flex: 1;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 15px;
        }

        .dpto-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .dpto-item:hover {
            background: #f8f9fa;
        }
    </style>
</head>

<body>

    <div class="tooltip" id="tooltip"></div>

    <div class="container">
        <div class="header">
            <h1>🗺️ Mapa de Criminalidad del Perú (
                <?= $anio ?>)
            </h1>
            <a href="index.php?filtro_anio=<?= $anio ?>&filtro_fuente=<?= $fuente ?>" class="btn-back">← Volver</a>
        </div>

        <div class="map-container">
            <!-- SVG SIMPLIFICADO DE PERÚ (Solo estructura para propósitos visuales) -->
            <!-- En un entorno real se usaría un JSON de GeoData o un SVG completo de departamentos -->
            <svg viewBox="0 0 400 600">
                <!-- Representación esquemática de los departamentos -->
                <path d="M120,50 L150,40 L180,60 L160,100 L110,90 Z" data-name="AMAZONAS" />
                <path d="M100,20 L120,50 L110,90 L70,80 L60,40 Z" data-name="TUMBES" />
                <path d="M60,40 L100,20 L120,50 L110,90 L70,120 L40,100 Z" data-name="PIURA" />
                <path d="M70,120 L110,90 L130,120 L100,160 L60,150 Z" data-name="LAMBAYEQUE" />
                <path d="M130,120 L160,100 L200,120 L180,180 L140,190 Z" data-name="SAN MARTIN" />
                <path d="M100,160 L130,120 L140,190 L120,220 L80,200 Z" data-name="CAJAMARCA" />
                <path d="M60,150 L100,160 L120,220 L70,230 L50,190 Z" data-name="LA LIBERTAD" />
                <path d="M70,230 L120,220 L130,270 L80,290 Z" data-name="ANCASH" />
                <path d="M130,270 L180,180 L220,220 L190,300 L150,320 Z" data-name="HUANUCO" />
                <path d="M190,300 L220,220 L280,250 L250,320 Z" data-name="UCAYALI" />
                <path d="M250,320 L280,250 L350,280 L320,350 Z" data-name="MADRE DE DIOS" />
                <path d="M80,290 L130,270 L150,380 L100,400 Z" data-name="LIMA" />
                <path d="M150,320 L190,300 L210,350 L170,380 Z" data-name="PASCO" />
                <path d="M170,380 L210,350 L250,380 L220,420 Z" data-name="JUNIN" />
                <path d="M150,380 L170,380 L190,430 L160,450 Z" data-name="HUANCAVELICA" />
                <path d="M190,430 L220,420 L260,450 L230,480 Z" data-name="AYACUCHO" />
                <path d="M260,450 L220,420 L250,380 L300,420 L320,480 Z" data-name="CUSCO" />
                <path d="M260,450 L320,480 L350,550 L300,560 Z" data-name="PUNO" />
                <path d="M100,400 L150,380 L160,450 L130,480 Z" data-name="ICA" />
                <path d="M160,450 L190,430 L230,480 L200,510 Z" data-name="APURIMAC" />
                <path d="M200,510 L230,480 L260,450 L320,480 L300,560 L240,580 Z" data-name="AREQUIPA" />
                <path d="M240,580 L300,560 L320,590 L280,595 Z" data-name="MOQUEGUA" />
                <path d="M280,595 L320,590 L330,600 L290,600 Z" data-name="TACNA" />
                <path d="M150,40 L250,30 L350,80 L300,200 L200,120 L150,40 Z" data-name="LORETO" />
            </svg>

            <div class="dpto-list">
                <h3>Totales por Departamento</h3>
                <?php
                arsort($map_data);
                foreach ($map_data as $d => $v): ?>
                    <div class="dpto-item">
                        <span>
                            <?= $d ?>
                        </span>
                        <span class="val">
                            <?= number_format($v, 0, ',', '.') ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="legend">
            <b>💡 Instrucción:</b> Pasa el cursor sobre el mapa para ver los totales. Los colores se intensifican según
            el nivel de delincuencia.
            <i>(Mapa esquemático para demostración de arquitectura).</i>
        </div>
    </div>

    <script>
        const data = <?= json_encode($map_data) ?>;
        const maxVal = <?= $max_val ?>;
        const tooltip = document.getElementById('tooltip');

        document.querySelectorAll('path').forEach(path => {
            const name = path.getAttribute('data-name');
            const val = data[name] || 0;

            // Color basado en intensidad
            const intensity = maxVal > 0 ? (val / maxVal) : 0;
            // Gradiente de Azul (#e3f2fd) a Rojo (#c62828)
            const r = Math.round(227 - intensity * (227 - 198));
            const g = Math.round(242 - intensity * (242 - 40));
            const b = Math.round(253 - intensity * (253 - 40));

            path.style.fill = `rgb(${r},${g},${b})`;

            path.addEventListener('mousemove', (e) => {
                tooltip.style.opacity = 1;
                tooltip.innerHTML = `<b>${name}</b><br>Total: ${val.toLocaleString()}`;
                tooltip.style.left = (e.clientX + 10) + 'px';
                tooltip.style.top = (e.clientY + 10) + 'px';
            });

            path.addEventListener('mouseout', () => {
                tooltip.style.opacity = 0;
            });
        });
    </script>

</body>

</html>