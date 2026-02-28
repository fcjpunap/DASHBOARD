<?php
session_start();
header('Content-Type: application/json');

$jobId = $_GET['job_id'] ?? '';
if (!$jobId) {
    echo json_encode(['error' => 'No job_id provided']);
    exit;
}

$file = "temp/import_$jobId.json";
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(['status' => 'waiting', 'progress' => 0, 'message' => 'Esperando inicio...']);
}
