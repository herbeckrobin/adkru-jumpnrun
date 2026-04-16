<?php
// get_highscores.php
header('Content-Type: application/json; charset=utf-8');

// Datenbankverbindung laden
require_once 'db_connect.php';

// Highscores abrufen (hier: Top 10 – kannst du anpassen)
$query = "SELECT name, score FROM highscores ORDER BY score DESC LIMIT 10";
$result = $conn->query($query);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Fehler beim Abrufen der Highscores']);
    exit;
}

$highscores = [];
while ($row = $result->fetch_assoc()) {
    $highscores[] = $row;
}

echo json_encode(['status' => 'ok', 'data' => $highscores]);

$conn->close();
?>
