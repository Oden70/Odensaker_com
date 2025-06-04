<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Utloggad</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* Centrerar hela login-container på sidan */
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f7fa;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Du har loggats ut</h2>
        <p style="text-align:center; margin-bottom:2em;">Du är nu utloggad från systemet.</p>
        <a href="login.php" style="display:block; text-align:center; margin-top:1.5em;">
            <button type="button" style="width:100%;">Logga in igen</button>
        </a>
    </div>
</body>
</html>
