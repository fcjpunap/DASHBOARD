<?php
/**
 * API AJAX para selectores geográficos y catálogos
 * Responde con JSON para poblar los selectores dinámicamente
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'provincias':
        $dpto = $_GET['dpto'] ?? '';
        if (empty($dpto) || $dpto === 'TOTAL PERU') {
            echo json_encode([]);
            break;
        }
        $stmt = $pdo->prepare("SELECT DISTINCT prov_hecho FROM sidpol_hechos WHERE dpto_hecho = ? AND prov_hecho != '' ORDER BY prov_hecho");
        $stmt->execute([$dpto]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        break;

    case 'distritos':
        $dpto = $_GET['dpto'] ?? '';
        $prov = $_GET['prov'] ?? '';
        if (empty($dpto) || $dpto === 'TOTAL PERU') {
            echo json_encode([]);
            break;
        }
        $sql = "SELECT DISTINCT dist_hecho FROM sidpol_hechos WHERE dpto_hecho = ? AND dist_hecho != ''";
        $params = [$dpto];
        if (!empty($prov) && $prov !== 'todos') {
            $sql .= " AND prov_hecho = ?";
            $params[] = $prov;
        }
        $sql .= " ORDER BY dist_hecho";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
