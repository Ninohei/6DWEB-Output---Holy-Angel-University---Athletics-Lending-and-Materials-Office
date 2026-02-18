<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Get filters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$location_filter = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$availability_filter = isset($_GET['availability']) ? sanitize($_GET['availability']) : '';
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where_conditions = ["e.is_active = 1"];
$params = [];

if ($category_filter > 0) {
    $where_conditions[] = "e.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($location_filter)) {
    $where_conditions[] = "e.location = ?";
    $params[] = $location_filter;
}

if ($availability_filter === 'available') {
    $where_conditions[] = "e.quantity_available > 0";
} elseif ($availability_filter === 'unavailable') {
    $where_conditions[] = "e.quantity_available = 0";
}

if (!empty($search_query)) {
    $where_conditions[] = "(e.name LIKE ? OR e.code LIKE ? OR e.description LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get equipment
$stmt = $pdo->prepare("
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM favorites WHERE user_id = ? AND equipment_id = e.equipment_id) as is_favorited
    FROM equipment e
    JOIN categories c ON e.category_id = c.category_id
    WHERE {$where_clause}
    ORDER BY e.name ASC
");

$stmt->execute(array_merge([$user_id], $params));
$equipment_list = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// Get unique locations
$locations = $pdo->query("SELECT DISTINCT location FROM equipment WHERE is_active = 1 ORDER BY location")->fetchAll();

$page_title = 'Browse Equipment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a> / <?php echo $page_title; ?>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1 class="page-title">Browse Equipment</h1>
            <p class="page-subtitle">Find and request sports equipment for your activities</p>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="browse.php">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search equipment..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Location</label>
                        <select name="location" class="form-control">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location_filter == $loc['location'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['location']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Availability</label>
                        <select name="availability" class="form-control">
                            <option value="">All</option>
                            <option value="available" <?php echo $availability_filter == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo $availability_filter == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="display: flex; gap: 10px; align-items: end;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="browse.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Equipment Grid -->
        <?php if (empty($equipment_list)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <div class="empty-state-message">No equipment found</div>
                <div class="empty-state-description">Try adjusting your filters or search terms</div>
            </div>
        <?php else: ?>
            <div class="equipment-grid" id="equipment-grid">
                <?php foreach ($equipment_list as $item): 
                    $available = $item['quantity_available'] > 0;
                    $status_class = $available ? 'available' : 'unavailable';
                    $status_text = $available ? "Available: {$item['quantity_available']}/{$item['quantity_total']}" : 'Not Available';
                ?>
                    <div class="equipment-card">
                        <img src="../assets/images/equipment/<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="equipment-card-image"
                             onerror="this.src='../assets/images/default.png'">
                        
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="category"><?php echo $item['category_name']; ?></p>
                            <p class="location">📍 <?php echo htmlspecialchars($item['location']); ?></p>
                            
                            <div class="availability">
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </div>
                            
                            <?php if ($item['min_points_required'] > 0): ?>
                                <p style="font-size: 12px; color: #666; margin-top: 5px;">
                                    Requires: <?php echo $item['min_points_required']; ?> points
                                </p>
                            <?php endif; ?>
                            
                            <div style="margin-top: 12px; display: flex; gap: 8px;">
                                <a href="details.php?id=<?php echo $item['equipment_id']; ?>" class="btn btn-primary btn-sm" style="flex: 1;">
                                    View Details
                                </a>
                                <button class="btn btn-secondary btn-sm favorite-btn <?php echo $item['is_favorited'] ? 'favorited' : ''; ?>" 
                                        data-equipment-id="<?php echo $item['equipment_id']; ?>"
                                        onclick="event.stopPropagation();">
                                    <?php echo $item['is_favorited'] ? '❤️' : '🤍'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 20px; color: #666;">
                Showing <?php echo count($equipment_list); ?> item(s)
            </div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
