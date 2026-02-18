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
            height: 100vh;
            margin: 0;
        }

        .panel-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 600px;
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

        <form action="procesar_excel.php" method="POST" enctype="multipart/form-data">

            <!-- OPCIÓN A: URL -->
            <label for="url_excel">Opción A: Desde URL (Mininter o Ministerio Público)</label>
            <input type="url" name="url_excel" id="url_excel" placeholder="https://.../dataset.xlsx o .csv">
            <p class="note">Support for: <b>Mininter (SIDPOL .xlsx)</b> and <b>MPFN (Ministerio Público .csv)</b>.</p>

            <div class="or-divider">O</div>

            <!-- OPCIÓN B: SUBIDA -->
            <label for="archivo_excel">Opción B: Subir Archivo (.xlsx o .csv)</label>
            <input type="file" name="archivo_excel" id="archivo_excel" accept=".xlsx,.csv">
            <p class="note">Recomendado para archivos grandes. Límite: <?= ini_get('upload_max_filesize') ?></p>

            <button type="submit" class="btn-submit">🚀 Procesar Datos</button>
        </form>

        <hr>
        <h3>Instrucciones:</h3>
        <ul style="font-size: 14px; color: #555; padding-left: 20px;">
            <li><b>SIDPOL (Policía):</b> Sube el excel (.xlsx) oficial del Mininter (Denuncias o Violencia).</li>
            <li><b>MPFN (Fiscalía):</b> Pega la URL del CSV de Datos Abiertos.</li>
            <li>El sistema detectará la fuente y el tipo de datos (Delitos/Violencia) automáticamente.</li>
            <li>Se ignorarán registros duplicados mediante el hash de contenido.</li>
        </ul>
    </div>

</body>

</html>