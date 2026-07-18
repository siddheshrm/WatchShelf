<?php
require_once '../../includes/auth.php';
require_once '../../config/connection.php';
require_once '../../config/app.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['admin_id'];

    $brand = trim($_POST['brand']);
    $modelName = trim($_POST['model_name']);
    $mrp = $_POST['mrp'];
    $gender = $_POST['gender'];
    $available_colors = trim($_POST['available_colors']);

    $caseMaterial = trim($_POST['case_material']);
    $caseDiameter = !empty($_POST['case_diameter_mm']) ? $_POST['case_diameter_mm'] : null;
    $bandMaterial = trim($_POST['band_material']);
    $movementType = !empty($_POST['movement_type']) ? $_POST['movement_type'] : null;
    $waterResistance = !empty($_POST['water_resistance_atm']) ? $_POST['water_resistance_atm'] : null;
    $displayType = !empty($_POST['display_type']) ? $_POST['display_type'] : null;
    $crystalType = trim($_POST['crystal_type']);
    $tags = trim($_POST['tags']);
    $warrantyYears = !empty($_POST['warranty_years']) ? $_POST['warranty_years'] : null;

    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $ownerStatus = $_POST['owner_status'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $imageFolder = strtolower($brand . '-' . $modelName);
    $imageFolder = preg_replace('/[^a-z0-9]+/', '-', $imageFolder);
    $imageFolder = trim($imageFolder, '-');

    if (empty($message)) {
        $sql = "INSERT INTO watches (admin_id, brand, model_name, mrp, gender, available_colors, case_material, case_diameter_mm, band_material, movement_type, water_resistance_atm, display_type, crystal_type, tags, warranty_years, image_folder, is_featured, owner_status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // Check if the preparation was successful
        if (!$stmt) {
            die($conn->error);
        }

        $stmt->bind_param("ississsdssisssisisi", $adminId, $brand, $modelName, $mrp, $gender, $available_colors, $caseMaterial, $caseDiameter, $bandMaterial, $movementType, $waterResistance, $displayType, $crystalType, $tags, $warrantyYears, $imageFolder, $isFeatured, $ownerStatus, $isActive);

        if ($stmt->execute()) {
            $watchId = $conn->insert_id;

            // Create the watch image folder if it doesn't already exist
            $imagePath = WATCH_IMAGE_DIR . '/' . $imageFolder;

            if (!is_dir($imagePath) && !mkdir($imagePath, 0755, true)) {
                error_log("Failed to create image folder: " . $imagePath);
            }

            $retailerSql = "INSERT INTO watch_retailers (watch_id, retailer_name, retailer_type, base_url, affiliate_url, price, currency, is_available, display_order)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $retailerStmt = $conn->prepare($retailerSql);
            // Check if the preparation was successful
            if (!$retailerStmt) {
                die($conn->error);
            }

            foreach ($_POST['retailer_name'] as $i => $retailerName) {
                $retailerName = trim($retailerName);

                // Skip empty retailer blocks
                if ($retailerName === '') {
                    continue;
                }

                $retailerType = $_POST['retailer_type'][$i];
                $baseUrl = trim($_POST['base_url'][$i]);
                $affiliateUrl = trim($_POST['affiliate_url'][$i]);

                $price = $_POST['price'][$i] !== '' ? $_POST['price'][$i] : null;

                $currency = trim($_POST['currency'][$i]);
                $isAvailable = $_POST['is_available'][$i];
                $displayOrder = $_POST['display_order'][$i];

                $retailerStmt->bind_param("issssdsii", $watchId, $retailerName, $retailerType, $baseUrl, $affiliateUrl, $price, $currency, $isAvailable, $displayOrder);

                $retailerStmt->execute();
            }

            $retailerStmt->close();
            header("Location: ../dashboard.php");
            // $message = "Watch added successfully.";
            exit();
        } else {
            $message = "Failed to add watch.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Watch | WatchShelf Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
</head>

<body>
    <h1>Add Watch</h1>

    <p>
        <a href="../dashboard.php">Dashboard</a> |
        <a href="list.php">Manage Watches</a>
    </p>

    <hr>

    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <fieldset>
            <legend>Basic Information</legend>

            <p>
                <label for="brand">Brand *</label><br>
                <input type="text" id="brand" name="brand" required>
            </p>

            <p>
                <label for="model_name">Model *</label><br>
                <input type="text" id="model_name" name="model_name" required>
            </p>

            <p>
                <label for="mrp">MRP *</label><br>
                <input type="number" step="0.01" id="mrp" name="mrp" required>
            </p>

            <p>
                <label for="gender">Gender</label><br>
                <select id="gender" name="gender">
                    <option value="men">Men</option>
                    <option value="women">Women</option>
                    <option value="unisex" selected>Unisex</option>
                </select>
            </p>

            <p>
                <label for="available_colors">Available Colors</label><br>
                <input type="text" id="available_colors" name="available_colors" placeholder="Enter colors separated by commas">
            </p>

            <p>
                <label>
                    <input type="checkbox" name="is_featured" value="1">Featured Watch</label>
            </p>

            <p>
                <label for="owner_status">Owner Status</label><br>
                <select id="owner_status" name="owner_status">
                    <option value="none" selected>None</option>
                    <option value="owned">Owned</option>
                    <option value="interested">Interested</option>
                </select>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="is_active" value="1" checked>Active</label>
            </p>
        </fieldset>

        <br>

        <fieldset>
            <legend>Specifications</legend>

            <p>
                <label for="case_material">Case Material</label><br>
                <input type="text" id="case_material" name="case_material">
            </p>

            <p>
                <label for="case_diameter_mm">Case Diameter (mm)</label><br>
                <input type="number" step="0.1" id="case_diameter_mm" name="case_diameter_mm">
            </p>

            <p>
                <label for="band_material">Band Material</label><br>
                <input type="text" id="band_material" name="band_material">
            </p>

            <p>
                <label for="movement_type">Movement Type</label><br>
                <select id="movement_type" name="movement_type">
                    <option value="">Select</option>
                    <option value="quartz">Quartz</option>
                    <option value="automatic">Automatic</option>
                    <option value="manual">Manual</option>
                    <option value="mechanical">Mechanical</option>
                    <option value="solar">Solar</option>
                    <option value="kinetic">Kinetic</option>
                </select>
            </p>

            <p>
                <label for="display_type">Display Type</label><br>
                <select id="display_type" name="display_type">
                    <option value="">Select</option>
                    <option value="analog">Analog</option>
                    <option value="digital">Digital</option>
                    <option value="ana-digi">Ana-Digi</option>
                </select>
            </p>

            <p>
                <label for="crystal_type">Crystal Type</label><br>
                <input type="text" id="crystal_type" name="crystal_type">
            </p>

            <p>
                <label for="water_resistance_atm">Water Resistance (ATM)</label><br>
                <input type="number" id="water_resistance_atm" name="water_resistance_atm">
            </p>

            <p>
                <label for="warranty_years">Warranty (Years)</label><br>
                <input type="number" id="warranty_years" name="warranty_years">
            </p>

            <p>
                <label for="tags">Tags</label><br>
                <input type="text" id="tags" name="tags" placeholder="Enter tags separated by commas">
            </p>
        </fieldset>

        <br>

        <h2>Retailers</h2>

        <table cellpadding="10">
            <tr>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <td valign="top">
                        <fieldset>
                            <legend>Retailer <?= $i; ?></legend>

                            <p>
                                <label>Retailer Name</label><br>
                                <input type="text" name="retailer_name[]">
                            </p>

                            <p>
                                <label>Retailer Type</label><br>
                                <select name="retailer_type[]">
                                    <option value="ecommerce" selected>E-commerce</option>
                                    <option value="brand_website">Brand Website</option>
                                    <option value="offline">Offline</option>
                                </select>
                            </p>

                            <p>
                                <label>Base URL</label><br>
                                <input type="url" name="base_url[]">
                            </p>

                            <p>
                                <label>Affiliate URL</label><br>
                                <input type="url" name="affiliate_url[]">
                            </p>

                            <p>
                                <label>Price</label><br>
                                <input type="number" step="0.01" name="price[]">
                            </p>

                            <p>
                                <label>Currency</label><br>
                                <input type="text" name="currency[]" value="INR" readonly>
                            </p>

                            <p>
                                <label>Available</label><br>
                                <select name="is_available[]">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </p>

                            <input type="hidden" name="display_order[]" value="<?= $i; ?>">
                        </fieldset>
                    </td>
                <?php endfor; ?>
            </tr>
        </table>

        <br>

        <button type="submit">Save Watch</button>
    </form>
</body>

</html>