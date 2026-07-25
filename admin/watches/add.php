<?php
require_once '../../includes/auth.php';
require_once '../../config/connection.php';
require_once '../../config/app.php';
$COLOR_MAP = require_once '../../config/colormap.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['admin_id'];

    $brand = trim($_POST['brand']);
    $modelName = trim($_POST['model_name']);
    $mrp = $_POST['mrp'];
    $gender = $_POST['gender'];

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

    if (empty($message)) {
        $sql = "INSERT INTO watches (admin_id, brand, model_name, mrp, gender, case_material, case_diameter_mm, band_material, movement_type, water_resistance_atm, display_type, crystal_type, tags, warranty_years, is_featured, owner_status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // Check if the preparation was successful
        if (!$stmt) {
            die($conn->error);
        }

        $stmt->bind_param("ississdssisssiisi", $adminId, $brand, $modelName, $mrp, $gender, $caseMaterial, $caseDiameter, $bandMaterial, $movementType, $waterResistance, $displayType, $crystalType, $tags, $warrantyYears, $isFeatured, $ownerStatus, $isActive);

        if ($stmt->execute()) {
            $watchId = $conn->insert_id;

            $retailerSql = "INSERT INTO watch_variants (watch_id, color_name, image_folder, is_default, retailer_name, retailer_type, base_url, affiliate_url, price, currency, is_available)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $retailerStmt = $conn->prepare($retailerSql);

            if (!$retailerStmt) {
                die($conn->error);
            }

            // Get the index of the selected default color (-1 if none selected)
            $defaultColor = isset($_POST['default_color']) ? (int) $_POST['default_color'] : -1;

            foreach ($_POST['colors'] as $colorIndex => $color) {
                $colorName = trim($color['color_name']);

                // Skip empty color blocks
                if ($colorName === '') {
                    continue;
                }

                $isDefault = ($colorIndex == $defaultColor) ? 1 : 0;

                // Generate parent watch folder
                $watchFolder = strtolower($brand . '-' . $modelName);
                $watchFolder = preg_replace('/[^a-z0-9]+/', '-', $watchFolder);
                $watchFolder = trim($watchFolder, '-');

                $watchFolderPath = WATCH_IMAGE_DIR . '/' . $watchFolder;

                if (!is_dir($watchFolderPath)) {
                    mkdir($watchFolderPath, 0755, true);
                }

                // Generate color folder
                $colorFolder = strtolower($colorName);
                $colorFolder = preg_replace('/[^a-z0-9]+/', '-', $colorFolder);
                $colorFolder = trim($colorFolder, '-');

                $imageFolder = $watchFolder . '/' . $colorFolder;

                $imagePath = WATCH_IMAGE_DIR . '/' . $imageFolder;

                if (!is_dir($imagePath)) {
                    mkdir($imagePath, 0755, true);
                }

                // Insert retailers
                foreach ($color['retailers'] as $retailer) {
                    $retailerName = trim($retailer['retailer_name']);

                    if ($retailerName === '') {
                        continue;
                    }

                    $retailerType = $retailer['retailer_type'];
                    $baseUrl = trim($retailer['base_url']);
                    $affiliateUrl = trim($retailer['affiliate_url']);

                    $price = ($retailer['price'] !== '') ? $retailer['price'] : null;

                    $currency = trim($retailer['currency']);
                    $isAvailable = $retailer['is_available'];

                    $retailerStmt->bind_param("ississssdsi", $watchId, $colorName, $imageFolder, $isDefault, $retailerName, $retailerType, $baseUrl, $affiliateUrl, $price, $currency, $isAvailable);

                    $retailerStmt->execute();
                }
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
                <label><input type="checkbox" name="is_featured" value="1">Featured Watch</label>
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
                <label><input type="checkbox" name="is_active" value="1" checked>Active</label>
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

        <h2>Color Options</h2>

        <div id="colorsContainer">
            <p><button type="button" id="addColorBtn">+ Add Color</button></p>

            <template id="colorTemplate">
                <details open class="color-block">
                    <summary>
                        <strong>New Color</strong>
                    </summary>

                    <br>

                    <p>
                        <label>Color</label><br>
                        <select class="color-select">
                            <option value="">Select Color</option>

                            <?php foreach (array_keys($COLOR_MAP) as $colorName): ?>
                                <option value="<?= htmlspecialchars($colorName); ?>">
                                    <?= htmlspecialchars($colorName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label>
                            <input type="radio" name="default_color" class="default-color">Is Default Color?
                        </label>
                    </p>

                    <table cellpadding="10">
                        <tr>

                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <td valign="top">

                                    <fieldset class="retailer-block">
                                        <legend>Retailer <?= $i; ?></legend>

                                        <p>
                                            <label>Retailer Name</label><br>
                                            <input type="text" class="retailer-name">
                                        </p>

                                        <p>
                                            <label>Retailer Type</label><br>
                                            <select class="retailer-type">
                                                <option value="ecommerce" selected>E-commerce</option>
                                                <option value="brand_website">Brand Website</option>
                                                <option value="offline">Offline</option>
                                            </select>
                                        </p>

                                        <p>
                                            <label>Base URL</label><br>
                                            <input type="url" class="base-url">
                                        </p>

                                        <p>
                                            <label>Affiliate URL</label><br>
                                            <input type="url" class="affiliate-url">
                                        </p>

                                        <p>
                                            <label>Price</label><br>
                                            <input type="number" step="0.01" class="price">
                                        </p>

                                        <p>
                                            <label>Currency</label><br>
                                            <input type="text" class="currency" value="INR" readonly>
                                        </p>

                                        <p>
                                            <label>Available</label><br>
                                            <select class="is-available">
                                                <option value="1" selected>Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </p>
                                    </fieldset>
                                </td>

                            <?php endfor; ?>
                        </tr>
                    </table>
                </details>

                <br>

            </template>

            <br>
        </div>

        <br>

        <button type="submit">Save Watch</button>
    </form>

    <script src="../../assets/js/add.js"></script>
</body>

</html>