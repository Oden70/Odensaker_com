<?php
// ...existing code...
?>
<form method="post" enctype="multipart/form-data" class="login-form">
    <!-- ...existing code... -->
    <label><?= t('phone') ?>
        <input type="text" name="phone">
    </label>
    <label><?= t('address') ?>
        <input type="text" name="address">
    </label>
    <label><?= t('zip_code') ?>
        <input type="text" name="zip_code">
    </label>
    <label><?= t('city') ?>
        <input type="text" name="city">
    </label>
    <label><?= t('country') ?>
        <input type="text" name="country">
    </label>
    <label><?= t('personal_number') ?>
        <input type="text" name="personal_number">
    </label>
    <label><?= t('avatar') ?>
        <input type="file" name="avatar" accept="image/*">
    </label>
    <!-- ...existing code... -->
</form>