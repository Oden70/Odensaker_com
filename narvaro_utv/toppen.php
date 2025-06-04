<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!--<link href="mystyle.css" rel="stylesheet">-->
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4" style="padding-left:0;padding-right:0;">
    <div class="container" style="max-width: 900px;">
        <?php if (isset($_SESSION['authenticated'])): ?>
            <a class="navbar-brand" href="dashboard.php">Konferenssystem</a>
        <?php else: ?>
            <a class="navbar-brand">Konferenssystem</a>
        <?php endif; ?>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['authenticated'])): ?>
                        <li class="nav-item"><a class="nav-link" href="profile.php">Min profil</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logga ut</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Logga in</a></li>
                    <?php endif; ?>
                </ul>
             </div>
    </div>
</nav>
<div class="container">
