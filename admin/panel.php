<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Importador SIDPOL v3.0</title>
    <style>
        body {
            font-family: sans-serif;
            background: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 40px 0;
        }

        .panel-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 600px;
        }

        /* Estilos para las Notas Técnicas (Collapsible) */
        details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-top: 20px;
            overflow: hidden;
        }

        summary {
            padding: 12px 15px;
            background: #eef2f5;
            cursor: pointer;
            font-weight: bold;
            color: #0056b3;
            outline: none;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        summary:hover {
            background: #e2e6ea;
        }

        .notes-content {
            padding: 15px;
            font-size: 13px;
            color: #444;
            line-height: 1.5;
            border-top: 1px solid #dee2e6;
        }

        .notes-content b {
            color: #2c3e50;
        }

        h2 {
            color: #0056b3;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        hr {
            border: 0;
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input[type="url"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background-color: #218838;
        }

        .note {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .or-divider {
            text-align: center;
            font-weight: bold;
            color: #888;
            margin: 20px 0;
            position: relative;
        }

        .or-divider::before,
        .or-divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background: #ddd;
        }

        .or-divider::before {
            left: 0;
        }

        .or-divider::after {
            right: 0;
        }

        .logout {
            float: right;
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="panel-box">
        <a href="logout.php" class="logout">Cerrar Sesión</a>
        <h2>📥 Importador SIDPOL v3.0</h2>
        <p>Actualiza la base de datos de delitos desde el Mininter.</p>
        <hr>

        <form id="importForm" action="procesar_excel.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="job_id" id="job_id">

            <!-- OPCIÓN A: URL -->
            <label for="url_excel">Opción A: Desde URL (Mininter o Ministerio Público)</label>
            <input type="url" name="url_excel" id="url_excel" placeholder="https://.../dataset.xlsx o .csv">
            <p class="note">Soporte para: <b>Mininter (SIDPOL .xlsx)</b>, <b>MPFN (.csv)</b> y <b>CSV Violencia
                    Mujer/IGF</b>.</p>

            <div class="or-divider">O</div>

            <!-- OPCIÓN B: SUBIDA -->
            <label for="archivo_excel">Opción B: Subir Archivo (.xlsx o .csv)</label>
            <input type="file" name="archivo_excel" id="archivo_excel" accept=".xlsx,.csv">
            <p class="note">Recomendado para archivos grandes. Límite:
                <?= ini_get('upload_max_filesize') ?>
            </p>

            <button type="submit" class="btn-submit" id="btnSubmit">🚀 Procesar Datos</button>
            <button type="button" id="btnLimpiar"
                style="width: 100%; padding: 10px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-top: 10px; font-weight: bold;">🧹
                Limpiar Base de Datos</button>
            <button type="button" id="btnSmartClean"
                style="width: 100%; padding: 10px; background-color: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-top: 10px; font-weight: bold;">🧽
                Optimizar y Limpiar Traslapes</button>
        </form>

        <!-- UI DE PROGRESO (INICIALMENTE OCULTA) -->
        <div id="progressSection"
            style="display: none; margin-top: 30px; border-top: 2px solid #007bff; padding-top: 20px;">
            <h3 id="statusTitle">Iniciando proceso...</h3>
            <div
                style="width: 100%; background: #eee; border-radius: 10px; height: 30px; margin: 15px 0; overflow: hidden; border: 1px solid #ccc;">
                <div id="progressBar"
                    style="width: 0%; height: 100%; background: linear-gradient(90deg, #28a745, #218838); transition: width 0.3s; text-align: center; color: white; line-height: 30px; font-weight: bold;">
                    0%</div>
            </div>
            <div id="logConsole"
                style="background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 5px; height: 200px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; margin-top: 10px; white-space: pre-wrap;">
            </div>
        </div>

        <hr>
        <h3>Instrucciones:</h3>
        <ul style="font-size: 14px; color: #555; padding-left: 20px;">
            <li><b>SIDPOL (Policía):</b> Sube el excel (.xlsx) oficial del Mininter (Denuncias o Violencia).</li>
            <li><b>MPFN (Fiscalía):</b> Pega la URL del CSV de Datos Abiertos.</li>
            <li>El sistema detectará la fuente y el tipo de datos (Delitos/Violencia) automáticamente.</li>
            <li>Se ignorarán registros duplicados mediante el hash de contenido.</li>
        </ul>

        <details>
            <summary>📘 Notas Técnicas Importantes</summary>
            <div class="notes-content">
                <p><strong>1. ¿Debería crecer la cifra total al importar el CSV de Violencia Mujer/IGF tras el
                        Excel?</strong><br>
                    <b>No, no debería crecer.</b> El archivo de Excel grande (Hoja 1) ya contiene las cifras generales
                    de violencia. Si se sumara a la cifra al importar el CSV, estarías duplicando los casos. La
                    diferencia es el detalle: el Excel agrupa por "Departamento", pero el CSV está desglosado por
                    "Provincia" y "Distrito". Al importar el CSV, el sistema detecta y borra automáticamente los
                    registros genéricos de violencia previos provenientes del Excel para sustituirlos limpiamente por el
                    detalle enriquecido del CSV.
                </p>

                <p><strong>2. El Diagnóstico: La 'Trampa' de las Hojas Temporales en Excel SIDPOL</strong><br>
                    El archivo Excel <code>Base_datos_SIDPOL...xlsx</code> está estructurado con varias hojas que
                    contienen datos solapados:<br>
                    • <b>Hojas 1 a 5 (Temp2-Temp5):</b> Contienen un resumen histórico de todos los años (2018-2025),
                    pero con menos detalle.<br>
                    • <b>Hoja 6 (Temp6):</b> Contiene el detalle máximo pero solo de 2024.<br>
                    • <b>Hoja 7 (Temp7):</b> Contiene el detalle máximo pero solo de 2025.<br>
                    <i>El importador inicial leía las hojas 1, 5, 6 y 7 a la vez. Esto provocaba que los datos de 2025
                        se sumaran tres veces, inflando las métricas. Aunque la limpieza de BD borraba duplicados
                        técnicos, los nombres de delitos a veces varían ligeramente entre hojas (ej. "EBRIEDAD" vs
                        "EBRIEDAD O DROGADICCION"), lo que impedía que se borraran limpiamente provocando desfases
                        numéricos.</i>
                </p>

                <p style="margin-bottom: 0;"><strong>🛠️ La Solución implementada: Estrategia Multi-Hoja Inteligente
                        (v14.1/v2.0)</strong><br>
                    • <b>Paso 1 - Prioridad Hoja 7:</b> Solo importa delitos con detalle geográfico a partir de 2025
                    (fuente de verdad más rica y actual).<br>
                    • <b>Paso 1 - Prioridad Hoja 6:</b> Solo importa delitos del 2024.<br>
                    • <b>Paso 1 - Prioridad Hoja 5:</b> Solo importa datos históricos (2018 a 2023), ignorando años
                    presentes en Hoja 6/7 para evitar duplicaciones.<br>
                    • <b>Paso 2 - Habilitación Automática (Hoja 1):</b> Importación inteligente dedicada que extrae las
                    categorías exclusivas (Faltas, Violencia, Niños y Adolescentes, y Otros), integrando automáticamente
                    el panorama analítico sin saturar el servidor y sin duplicar registros.</p>
            </div>
        </details>
    </div>

    <script>
        document.getElementById('importForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const form = e.target;
            const btn = document.getElementById('btnSubmit');
            const prog = document.getElementById('progressSection');
            const bar = document.getElementById('progressBar');
            const title = document.getElementById('statusTitle');
            const consoleLog = document.getElementById('logConsole');
            const jobId = 'job_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

            document.getElementById('job_id').value = jobId;

            // UI State
            btn.disabled = true;
            btn.innerText = "⏳ Procesando...";
            prog.style.display = 'block';
            consoleLog.innerHTML = "Iniciando comunicación con el servidor...\n";

            const formData = new FormData(form);

            // Iniciar proceso en "segundo plano" (vía fetch)
            // No esperamos el JSON de respuesta de inmediato porque procesar_excel.php 
            // es un stream de texto/html, no un API JSON pura. 
            // Simplemente disparamos y polleamos el archivo de progreso.

            // Detectar si es XLSX para activar Paso 2 automático
            const urlVal = document.getElementById('url_excel').value.trim().toLowerCase();
            const fileInput = document.getElementById('archivo_excel');
            const fileName = fileInput.files.length > 0 ? fileInput.files[0].name.toLowerCase() : '';
            const isXlsx = urlVal.endsWith('.xlsx') || fileName.endsWith('.xlsx');

            let finished = false;

            // ── PASO 1: Importación principal ──────────────────────────────
            fetch('procesar_excel.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                finished = true;
                return response.text();
            }).then(async () => {
                console.log("Paso 1 finalizado.");

                // ── PASO 2 (solo XLSX): Importar Faltas/Violencia/Niños/Otros ──
                if (isXlsx) {
                    title.innerText = "⏳ Paso 2/2: Complementando categorías (Faltas, Violencia, Niños...)";
                    bar.style.width = '95%';
                    bar.innerText = '95%';
                    consoleLog.innerText += "\n\n📋 [PASO 2] Importando Faltas, Violencia, Niños y Otros desde Hoja 1...\n";
                    consoleLog.scrollTop = consoleLog.scrollHeight;

                    try {
                        const res2 = await fetch('import_sheet1_categories.php?auto=1&v=' + Date.now());
                        const txt2 = await res2.text();
                        consoleLog.innerText += txt2;
                        consoleLog.scrollTop = consoleLog.scrollHeight;
                    } catch (e2) {
                        consoleLog.innerText += "\n⚠️ Paso 2 no se pudo verificar: " + e2.message;
                    }
                }

                bar.style.width = '100%';
                bar.innerText = '100% - Completado';
                title.innerText = isXlsx ? "✅ Importación Finalizada (Delitos + Faltas + Violencia + Niños)" : "✅ Importación Finalizada";
                btn.disabled = false;
                btn.innerText = "🚀 Procesar Nuevo Archivo";
                btn.style.backgroundColor = "#007bff";

            }).catch(err => {
                console.error("Error en fetch:", err);
                finished = true;
                btn.disabled = false;
                document.getElementById('url_excel').disabled = false;
                document.getElementById('archivo_excel').disabled = false;
                btn.innerText = "🚀 Reintentar";
            });

            // Polling de progreso (mientras dura el Paso 1)
            const pollInterval = setInterval(async () => {
                try {
                    const res = await fetch(`api_progress.php?job_id=${jobId}`);
                    const data = await res.json();

                    if (data.progress !== undefined) {
                        // Solo actualizar barra si no estamos en paso 2 (max 90%)
                        if (!finished) {
                            bar.style.width = Math.min(data.progress, 90) + '%';
                            bar.innerText = Math.min(data.progress, 90) + '%';
                        }
                        title.innerText = data.message || "Procesando...";

                        if (data.full_log) {
                            const cleanLog = data.full_log.replace(/<br\s*\/?>/gi, "\n").replace(/<[^>]+>/g, "");
                            consoleLog.innerText = cleanLog;
                            consoleLog.scrollTop = consoleLog.scrollHeight;
                        }
                    }

                    if (data.status === 'completed' || finished) {
                        clearInterval(pollInterval);
                    }
                } catch (e) {
                    if (finished) clearInterval(pollInterval);
                }
            }, 1000);
        });

        document.getElementById('btnLimpiar').addEventListener('click', async function () {
            if (!confirm('¿Estás seguro de que deseas ELIMINAR TODOS los datos de la base de datos? Esta acción no se puede deshacer.')) return;

            const btn = document.getElementById('btnSubmit');
            const btnL = document.getElementById('btnLimpiar');
            const prog = document.getElementById('progressSection');
            const bar = document.getElementById('progressBar');
            const title = document.getElementById('statusTitle');
            const consoleLog = document.getElementById('logConsole');
            const jobId = 'clean_' + Date.now();

            btn.disabled = btnL.disabled = true;
            prog.style.display = 'block';
            consoleLog.innerHTML = "Iniciando limpieza de base de datos...\n";

            let finished = false;
            fetch(`limpiar_bd.php?job_id=${jobId}`).then(() => finished = true).catch(() => finished = true);

            const pollInterval = setInterval(async () => {
                try {
                    const res = await fetch(`api_progress.php?job_id=${jobId}`);
                    const data = await res.json();
                    if (data.progress !== undefined) {
                        bar.style.width = data.progress + '%';
                        bar.innerText = data.progress + '%';
                        title.innerText = data.message;
                        if (data.full_log) {
                            const cleanLog = data.full_log.replace(/<br\s*\/?>/gi, "\n").replace(/<[^>]+>/g, "");
                            consoleLog.innerText = cleanLog;
                            consoleLog.scrollTop = consoleLog.scrollHeight;
                        }
                    }
                    if (data.status === 'completed' || finished) {
                        clearInterval(pollInterval);
                        btn.disabled = btnL.disabled = false;
                        title.innerText = "✅ Base de datos vacía";
                    }
                } catch (e) { if (finished) clearInterval(pollInterval); }
            }, 1000);
        });
        document.getElementById('btnSmartClean').addEventListener('click', async function () {
            if (!confirm('¿Deseas iniciar la limpieza de traslapes en la base de datos? Esto eliminará filas duplicadas y optimizará las cifras.')) return;

            const btn = document.getElementById('btnSubmit');
            const btnL = document.getElementById('btnLimpiar');
            const btnS = document.getElementById('btnSmartClean');
            const prog = document.getElementById('progressSection');
            const bar = document.getElementById('progressBar');
            const title = document.getElementById('statusTitle');
            const consoleLog = document.getElementById('logConsole');

            btn.disabled = btnL.disabled = btnS.disabled = true;
            prog.style.display = 'block';
            consoleLog.innerHTML = "Iniciando análisis y limpieza inteligente (Traslapes)...\nPor favor espera, este proceso puede tomar unos segundos o minutos dependiendo del tamaño de los datos...\n";
            bar.style.width = '50%';
            bar.innerText = 'Analizando...';
            title.innerText = "Limpiando traslapes...";

            try {
                // Iniciar proceso en segundo plano
                fetch('smart_clean.php');

                // Iniciar monitoreo de progreso
                const pollInterval = setInterval(async () => {
                    try {
                        const res = await fetch('api_progress.php?job_id=smart_clean');
                        const data = await res.json();

                        bar.style.width = data.progress + '%';
                        bar.innerText = data.progress + '%';
                        title.innerText = data.message || "Procesando...";

                        if (data.full_log) {
                            consoleLog.innerText = data.full_log;
                            consoleLog.scrollTop = consoleLog.scrollHeight;
                        }

                        if (data.status === 'completed' || data.progress >= 100) {
                            clearInterval(pollInterval);
                            btn.disabled = btnL.disabled = btnS.disabled = false;
                            title.innerText = "✅ Optimización finalizada";
                        }
                    } catch (e) {
                        console.error("Error polling progress:", e);
                    }
                }, 1500);

            } catch (e) {
                title.innerText = "⚠️ Error inesperado";
                consoleLog.innerText += "\nExcepción: " + e;
                btn.disabled = btnL.disabled = btnS.disabled = false;
            }
        });
    </script>
</body>

</html>