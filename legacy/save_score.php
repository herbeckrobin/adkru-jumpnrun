<?php
header('Content-Type: application/json; charset=utf-8');

// Datenbankverbindung
$host = 'localhost';
$db = 'highscore_game';
$user = 'root';
$pass = 'root';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB-Verbindung fehlgeschlagen']);
    exit;
}

// Eingaben prüfen
$name  = trim($_POST['name'] ?? '');
$score = intval($_POST['score'] ?? 0);

if ($name === '' || $score <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Ungültige Eingabe']);
    exit;
}

// Score speichern (nur wenn besser oder neuer Spieler)
$stmt = $conn->prepare("
    INSERT INTO highscores (name, score) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE score = GREATEST(score, ?)
");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'DB-Fehler beim Vorbereiten']);
    exit;
}

$stmt->bind_param("sii", $name, $score, $score);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok', 'message' => 'Score gespeichert']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Fehler beim Speichern']);
}

$stmt->close();
$conn->close();
?>
