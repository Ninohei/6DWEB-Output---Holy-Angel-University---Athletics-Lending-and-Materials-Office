<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$equipment_id = (int)($_POST['equipment_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($equipment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid equipment']);
    exit;
}

try {
    // Check if already favorited
    $stmt = $pdo->prepare("SELECT favorite_id FROM favorites WHERE user_id = ? AND equipment_id = ?");
    $stmt->execute([$user_id, $equipment_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Remove favorite
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND equipment_id = ?");
        $stmt->execute([$user_id, $equipment_id]);
        
        echo json_encode([
            'success' => true,
            'is_favorited' => false,
            'message' => 'Removed from favorites'
        ]);
    } else {
        // Add favorite
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, equipment_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $equipment_id]);
        
        echo json_encode([
            'success' => true,
            'is_favorited' => true,
            'message' => 'Added to favorites'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Favorite toggle error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Action failed']);
}
?>
