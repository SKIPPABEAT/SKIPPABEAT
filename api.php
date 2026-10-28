<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST');

session_start();

// Database verbinding
$dbFile = __DIR__ . '/skippabeat_db.sqlite';
$db = new PDO("sqlite:" . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tabellen aanmaken indien nodig
$db->exec("
    CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        title TEXT,
        date TEXT,
        location TEXT,
        info TEXT
    );
    CREATE TABLE IF NOT EXISTS attendance (
        event_id INTEGER,
        member TEXT,
        status TEXT,
        PRIMARY KEY (event_id, member)
    );
    CREATE TABLE IF NOT EXISTS absences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member TEXT,
        date_from TEXT,
        date_until TEXT,
        reason TEXT
    );
");

$members = [
    "Mariska Kerst", "Michael Benjaminsz", "Erik De Ridder", "Dirk Kleemans",
    "Sander Martinet", "Hans Albers", "Tim Schrijver", "Renate de Jong",
    "Wouter van Bavel", "Leo van Mook", "Diederik Smits"
];

// Standaard repetitie toevoegen bij lege database
$stmt = $db->query("SELECT COUNT(*) FROM events");
if ($stmt->fetchColumn() == 0) {
    $db->exec("INSERT INTO events (type, title, date, location, info) VALUES ('Repetitie', 'Repetitie SKIPPABEAT', '2026-11-04T20:00', 'Repetitieruimte', 'Reguliere woensdagrepetitie')");
    $firstId = $db->lastInsertId();
    foreach ($members as $m) {
        $db->exec("INSERT INTO attendance (event_id, member, status) VALUES ($firstId, '$m', 'yes')");
    }
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

// GET: Alle data ophalen
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_data') {
    $events = $db->query("SELECT * FROM events ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
    $absences = $db->query("SELECT * FROM absences ORDER BY date_from ASC")->fetchAll(PDO::FETCH_ASSOC);
    $attendance = $db->query("SELECT * FROM attendance")->fetchAll(PDO::FETCH_ASSOC);

    // Groepeer aanwezigheid per event
    $attendanceByEvent = [];
    foreach ($attendance as $att) {
        $attendanceByEvent[$att['event_id']][$att['member']] = $att['status'];
    }

    echo json_encode([
        'events' => $events,
        'absences' => $absences,
        'attendance' => $attendanceByEvent,
        'members' => $members
    ]);
    exit;
}

// POST: Acties verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'set_status') {
        $stmt = $db->prepare("INSERT INTO attendance (event_id, member, status) VALUES (?, ?, ?) ON CONFLICT(event_id, member) DO UPDATE SET status=excluded.status");
        $stmt->execute([$input['event_id'], $input['member'], $input['status']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'add_event') {
        $stmt = $db->prepare("INSERT INTO events (type, title, date, location, info) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$input['type'], $input['title'], $input['date'], $input['location'], $input['info']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_event') {
        $db->prepare("DELETE FROM events WHERE id = ?")->execute([$input['event_id']]);
        $db->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$input['event_id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'add_absence') {
        $stmt = $db->prepare("INSERT INTO absences (member, date_from, date_until, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$input['member'], $input['date_from'], $input['date_until'], $input['reason']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_absence') {
        $db->prepare("DELETE FROM absences WHERE id = ? AND member = ?")->execute([$input['absence_id'], $input['member']]);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['error' => 'Ongeldige actie']);