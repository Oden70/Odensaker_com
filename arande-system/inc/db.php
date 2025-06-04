<?php
// Enkel PDO-anslutning, byt till egna värden
$dsn = 'mysql:host=localhost;dbname=odensaker_comoden70;charset=utf8mb4';
$user = 'odensaker_comoden70';
$pass = 'LeonaBiancaTheodor192123';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$pdo = new PDO($dsn, $user, $pass, $options);
