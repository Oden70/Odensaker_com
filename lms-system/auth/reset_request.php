<?php require_once '../includes/lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= t('reset_title') ?></title>
</head>
<body>
  <h2><?= t('reset_title') ?></h2>
  <p><?= t('reset_intro') ?></p>
  <form action="send_reset.php" method="POST">
    <label for="email"><?= t('email_label') ?></label><br>
    <input type="email" name="email" required><br><br>
    <button type="submit"><?= t('send_button') ?></button>
  </form>
</body>
</html>
