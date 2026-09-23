<?php
require_once '../../includes/auth.php';
require_once '../../config/connection.php';
require_once '../../config/app.php';

// Pagination settings
$perPage = 20;

$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);

// Get total number of watches
$countSql = "SELECT COUNT(*) AS total FROM watches";
$countResult = $conn->query($countSql);

if (!$countResult) {
    die($conn->error);
}

$totalWatches = (int) $countResult->fetch_assoc()['total'];
$totalPages = (int) ceil($totalWatches / $perPage);

if ($totalPages > 0) {
    $currentPage = min($currentPage, $totalPages);
}

$offset = ($currentPage - 1) * $perPage;

/* Fetch all watches with aggregated variant information.
   - Retrieves the default variant's image folder and color.
   - Counts the number of color options and retailer records.
   - Aggregates retailer details with GROUP_CONCAT so each watch is returned as a single row. */

$sql = "SELECT w.*, MAX(CASE WHEN wv.is_default = 1 THEN wv.image_folder END) AS default_image_folder, COALESCE (MAX(CASE WHEN wv.is_default = 1 THEN wv.color_name END), 'Not Available') AS default_color, COUNT(DISTINCT wv.color_name) AS total_colors, COUNT(wv.id) AS total_retailers, GROUP_CONCAT(CONCAT(wv.retailer_name, '|', COALESCE(wv.base_url, ''), '|', COALESCE(wv.affiliate_url, '')) ORDER BY wv.display_order SEPARATOR '~~') AS retailers
            FROM watches w
            LEFT JOIN watch_variants wv
            ON w.id = wv.watch_id
            GROUP BY w.id
            ORDER BY w.brand, w.model_name
            LIMIT $perPage OFFSET $offset";

$result = $conn->query($sql);
if (!$result) {
    die($conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Watches | WatchShelf Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/list.css">
</head>

<body>
    <div class="page">
        <h1>Manage Watches</h1>

        <div class="top-links">
            <a href="../dashboard.php">Dashboard</a>
            <span>|</span>
            <a href="add.php" class="btn-primary">Add New Watch</a>
        </div>

        <div class="summary">
            <strong>Total Watches:</strong> <?= $totalWatches; ?>
        </div>

        <table class="watch-table">
            <tr>
                <th>#</th>
                <th>Image</th>
                <th>Watch</th>
                <th>Owned?</th>
                <th>MRP</th>
                <th>Gender</th>
                <th>Case Size</th>
                <th>Available Colors</th>
                <!--<th>Retailer Records</th>-->
                <th>Featured?</th>
                <!-- <th>Active?</th> -->
                <th>Actions</th>
            </tr>

            <?php if ($totalWatches > 0): ?>
                <?php $srNo = $offset + 1; ?>

                <?php while ($watch = $result->fetch_assoc()): ?>
                    <tr>
                        <!-- Sr. No. (#) -->
                        <td><?= $srNo++; ?></td>

                        <!-- Image -->
                        <?php
                        $imageFile = WATCH_IMAGE_DIR . '/' . $watch['default_image_folder'] . '/1.webp';
                        $imageUrl = WATCH_IMAGE_URL . '/' . $watch['default_image_folder'] . '/1.webp';
                        ?>

                        <td>
                            <?php if (file_exists($imageFile)): ?>
                                <img src="<?= htmlspecialchars($imageUrl); ?>"
                                    alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']); ?>" width="80">
                            <?php else: ?>
                                <img src="<?= DEFAULT_WATCH_IMAGE; ?>" alt="No Image" width="80" height="80">
                            <?php endif; ?>
                        </td>

                        <!-- Brand & Model -->
                        <td>
                            <a
                                href="https://watchshelf.in/watch/details.php?id=<?= (int) $watch['id']; ?>"
                                target="_blank"
                                title="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']); ?>">
                                <?= htmlspecialchars($watch['brand']); ?>
                                <?= htmlspecialchars($watch['model_name']); ?>
                            </a>
                        </td>

                        <!-- Owner Status -->
                        <?php $status = $watch['owner_status']; ?>
                        <td class="owner-<?= $status ?>"><?= ucfirst($status) ?></td>

                        <!-- MRP -->
                        <td>₹<?= number_format($watch['mrp'], 2); ?></td>

                        <!-- Gender -->
                        <td><?= ucfirst($watch['gender']); ?></td>

                        <!-- Gender -->
                        <td><?= ucfirst($watch['case_diameter_mm']); ?> mm</td>

                        <!-- Colors Variants -->
                        <td>
                            <?= htmlspecialchars($watch['default_color']) ?>

                            <?php if ($watch['total_colors'] > 1): ?>
                                <small>+<?= $watch['total_colors'] - 1 ?> more</small>
                            <?php endif; ?>
                        </td>

                        <!-- Retailer Records -->

                        <!-- Featured and Active status are displayed as 'Yes' or 'No' based on their boolean values -->
                        <td class="<?= $watch['is_featured'] ? 'status-yes' : 'status-no' ?>">
                            <?= $watch['is_featured'] ? 'Yes' : 'No' ?>
                        </td>

                        <!-- Actions -->
                        <td>
                            <a class="action-btn action-edit" href="edit.php?id=<?= $watch['id'] ?>">Edit</a>

                            <a class="action-btn action-delete" href="delete.php?id=<?= $watch['id'] ?>"
                                onclick="return confirm('Delete this watch?');">Delete</a>
                        </td>
                    </tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11">No watches found.</td>
                </tr>
            <?php endif; ?>

        </table>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1; ?>">Previous</a>
                <?php endif; ?>

                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <a
                        href="?page=<?= $page; ?>"
                        class="<?= $page === $currentPage ? 'active' : ''; ?>">
                        <?= $page; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1; ?>">Next</a>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </div>
</body>

</html>

<?php
$conn->close();
?>