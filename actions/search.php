<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

try {
    $search_param = "%{$query}%";
    
    $stmt = $pdo->prepare("
        SELECT e.*, c.name as category_name
        FROM equipment e
        JOIN categories c ON e.category_id = c.category_id
        WHERE e.is_active = 1 
          AND (e.name LIKE ? OR e.code LIKE ? OR e.description LIKE ? OR c.name LIKE ?)
        ORDER BY e.name ASC
        LIMIT 20
    ");
    
    $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Search failed']);
}
?>
