<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$host = 'localhost';
$db = 'highscore_game';
$user = 'root';
$pass = 'root'; // MAMP Standard

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Datenbankverbindung fehlgeschlagen: " . $conn->connect_error]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['playerName']) || !isset($data['playerScore'])) {
    echo json_encode(["success" => false, "error" => "Ungültige Daten"]);
    exit;
}

$name = $conn->real_escape_string($data['playerName']);
$score = intval($data['playerScore']);

$sql = "INSERT INTO highscores (name, score)
        VALUES ('$name', $score)
        ON DUPLICATE KEY UPDATE
        score = GREATEST(score, $score)";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Fehler beim Einfügen: " . $conn->error,
        "sql" => $sql
    ]);
}

$conn->close();
