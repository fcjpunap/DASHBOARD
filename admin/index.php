<?php
session_start();
require_once 'db.php';

// CREDENCIALES FIJAS (Hardcoded)
$USUARIO_FIJO = 'admin';
$CLAVE_FIJA = 'dasboard_2025';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Verificación Directa
    if ($username === $USUARIO_FIJO && $password === $CLAVE_FIJA) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: panel.php");
        exit;
    } else {
        $error = "Credenciales incorrectas (Verifique mayúsculas/minúsculas)";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Acceso Admin Dashboard</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
        }

        h2 {
            margin-top: 0;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>🔐 Admin Dashboard</h2>
        <?php if (isset($error)) {
            echo "<p class='error'>$error</p>";
        } ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Usuario" required autofocus>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>
        <p style="font-size:12px; color:#888; margin-top:20px">Sistema de Importación v3.0</p>
    </div>
</body>

</html>