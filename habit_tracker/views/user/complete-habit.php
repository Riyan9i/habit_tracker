<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Habit.php';

$input = json_decode(file_get_contents('php://input'), true);
$habit_id = $input['habit_id'] ?? null;

if ($habit_id) {
    $habit = new Habit($conn);
    $success = $habit->markAsCompleted($habit_id);
    echo json_encode(['success' => $success]);
    exit();
}

echo json_encode(['success' => false]);
