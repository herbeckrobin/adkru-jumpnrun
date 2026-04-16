<?php
// db_connect.php
// Verbindet sich mit der MySQL-Datenbank

$host = 'localhost';
$db   = 'highscore_game';
$user = 'root';
$pass = 'root'; // MAMP: meist "root" / "root"

// Verbindung aufbauen
$conn = new mysqli($host, $user, $pass, $db);

// Verbindung prüfen
if ($conn->connect_error) {
    die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen: ' . $conn->connect_error]));
}

// Zeichensatz setzen (wichtig für Umlaute)
$conn->set_charset('utf8mb4');
?>
