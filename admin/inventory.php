<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$stmt = $pdo->query("
    SELECT e.*, c.name as category_name
    FROM equipment e
    JOIN categories c ON e.category_id = c.category_id
    WHERE e.is_active = 1
    ORDER BY e.name ASC
");
$equipment = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Equipment Inventory</h1>
            <button class="btn btn-primary" onclick="alert('Add equipment feature: Create form to insert into equipment table')">➕ Add Equipment</button>
        </div>
        
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Location</th><th>Quantity</th><th>Condition</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($equipment as $e): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($e['code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($e['name']); ?><br><small><?php echo htmlspecialchars($e['brand']); ?></small></td>
                            <td><?php echo $e['category_name']; ?></td>
                            <td><?php echo htmlspecialchars($e['location']); ?></td>
                            <td><?php echo $e['quantity_available']; ?> / <?php echo $e['quantity_total']; ?></td>
                            <td><span class="badge-<?php echo $e['condition_status']=='excellent'?'success':'secondary'; ?>"><?php echo ucfirst($e['condition_status']); ?></span></td>
                            <td><button class="btn btn-sm btn-secondary" onclick="alert('Edit equipment ID: <?php echo $e['equipment_id']; ?>')">Edit</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
