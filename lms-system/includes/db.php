<?php
$host = 'localhost';
$db   = 'odensaker_comoden70';
$user = 'odensaker_comoden70';
$pass = 'LeonaBiancaTheodor192123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Databasanslutning misslyckades: " . $e->getMessage());
}
?>