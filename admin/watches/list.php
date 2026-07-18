<?php
require_once '../../includes/auth.php';
require_once '../../config/connection.php';
require_once '../../config/app.php';

/* Fetch all watches along with their associated retailers.
Retailer details are combined into a single string using GROUP_CONCAT,
allowing each watch to be displayed as a single row in the listing. */

$sql = "SELECT w.*, GROUP_CONCAT(CONCAT(r.retailer_name, '|', COALESCE(r.base_url, ''), '|', COALESCE(r.affiliate_url, ''))
            ORDER BY r.display_order SEPARATOR '~~')
            AS retailers
            FROM watches w
            LEFT JOIN watch_retailers r
            ON w.id = r.watch_id
            GROUP BY w.id
            ORDER BY w.brand, w.model_name;";

$result = $conn->query($sql);
if (!$result) {
    die($conn->error);
}

$totalWatches = $result ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Watches | WatchShelf Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
</head>

<body>
    <h1>Manage Watches</h1>

    <p>
        <a href="../dashboard.php">Dashboard</a> |
        <a href="add.php">Add Watch</a>
    </p>

    <hr>

    <p>
        <strong>Total Watches:</strong> <?= $totalWatches; ?>
    </p>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>#</th>
            <th>Image</th>
            <th>Brand & Model</th>
            <th>MRP</th>
            <th>Gender</th>
            <th>Featured</th>
            <th>Active</th>
            <th>Base URL</th>
            <th>Affiliated URL</th>
            <th>Actions</th>
        </tr>

        <?php if ($totalWatches > 0): ?>
            <?php $srNo = 1; ?>

            <?php while ($watch = $result->fetch_assoc()): ?>
                <tr>
                    <!-- Sr. No. (#) -->
                    <td><?= $srNo++; ?></td>

                    <!-- Image -->
                    <?php
                    $imageFile = WATCH_IMAGE_DIR . '/' . $watch['image_folder'] . '/1.webp';
                    $imageUrl = WATCH_IMAGE_URL . '/' . $watch['image_folder'] . '/1.webp';
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
                        <?= htmlspecialchars($watch['brand']); ?>
                        <?= htmlspecialchars($watch['model_name']); ?>
                    </td>

                    <!-- MRP -->
                    <td>₹<?= number_format($watch['mrp'], 2); ?></td>

                    <!-- Gender -->
                    <td><?= ucfirst($watch['gender']); ?></td>

                    <!-- Featured and Active status are displayed as 'Yes' or 'No' based on their boolean values -->
                    <td><?= $watch['is_featured'] ? 'Yes' : 'No'; ?></td>
                    <td><?= $watch['is_active'] ? 'Yes' : 'No'; ?></td>

                    <!-- Base URL -->
                    <td>
                        <?php
                        if (!empty($watch['retailers'])) {
                            $retailers = explode('~~', $watch['retailers']);

                            foreach ($retailers as $retailer) {
                                $data = explode('|', $retailer);

                                $name = $data[0];
                                $baseUrl = $data[1];
                                $affiliateUrl = $data[2];

                                if (!empty($baseUrl)) {
                                    echo '<a href="' . htmlspecialchars($baseUrl) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($name) . '</a>';
                                } else {
                                    echo htmlspecialchars($name);
                                }

                                echo '<br>';
                            }
                        } else {
                            echo "0";
                        } ?>
                    </td>

                    <!-- Affiliate URL -->
                    <td>
                        <?php
                        if (!empty($watch['retailers'])) {
                            $retailers = explode('~~', $watch['retailers']);

                            foreach ($retailers as $retailer) {
                                $data = explode('|', $retailer);

                                $name = $data[0];
                                $baseUrl = $data[1];
                                $affiliateUrl = $data[2];

                                if (!empty($affiliateUrl)) {
                                    echo '<a href="' . htmlspecialchars($affiliateUrl) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($name) . '</a>';
                                } else {
                                    echo htmlspecialchars($name);
                                }

                                echo '<br>';
                            }
                        } else {
                            echo "0";
                        }
                        ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <a href="edit.php?id=<?= $watch['id']; ?>">Edit</a> |
                        <a href="delete.php?id=<?= $watch['id']; ?>" onclick="return confirm('Delete this watch?');">Delete</a>
                    </td>
                </tr>

            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="10">
                    No watches found.
                </td>
            </tr>
        <?php endif; ?>

    </table>
</body>

</html>

<?php
$conn->close();
?>