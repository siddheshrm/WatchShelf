<aside class="filter-sidebar">
    <h2 class="filter-title">Filters</h2>

    <form action="index.php" method="GET" class="filter-form">
        <!-- Gender -->
        <div class="filter-group filter-group-collapsible">
            <h3 class="filter-heading">Gender</h3>

            <div class="filter-options">
                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="gender[]" value="men" <?= in_array('men', $selectedGenders, true) ? 'checked' : '' ?>>Men
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="gender[]" value="women" <?= in_array('women', $selectedGenders, true) ? 'checked' : '' ?>>Women
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="gender[]" value="unisex" <?= in_array('unisex', $selectedGenders, true) ? 'checked' : '' ?>>Unisex
                </label>
            </div>
        </div>

        <!-- Brand -->
        <div class="filter-group filter-group-collapsible">
            <h3 class="filter-heading">Brands</h3>

            <div class="filter-options">
                <?php foreach ($brands as $brand): ?>

                    <label class="filter-option">
                        <input class="filter-price" type="checkbox" name="brand[]"
                            value="<?= htmlspecialchars($brand['brand']) ?>" <?= in_array($brand['brand'], $selectedBrands, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($brand['brand']) ?>
                    </label>

                <?php endforeach; ?>
            </div>

            <button type="button" class="filter-toggle" hidden>Show more</button>
        </div>

        <!-- Retailers -->
        <div class="filter-group filter-group-collapsible">
            <h3 class="filter-heading">Retailers</h3>

            <div class="filter-options">
                <?php foreach ($retailers as $retailer): ?>

                    <label class="filter-option">
                        <input class="filter-price" type="checkbox" name="retailer[]"
                            value="<?= htmlspecialchars($retailer['retailer_name']) ?>"
                            <?= in_array($retailer['retailer_name'], $selectedRetailers, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($retailer['retailer_name']) ?>
                    </label>

                <?php endforeach; ?>
            </div>

            <button type="button" class="filter-toggle" hidden>Show more</button>
        </div>

        <!-- Dial Colours -->
        <div class="filter-group filter-group-collapsible">
            <h3 class="filter-heading">Dial Colours</h3>

            <div class="filter-options">
                <?php foreach ($colors as $color): ?>
                    <label class="filter-option">
                        <input class="filter-price" type="checkbox" name="color[]"
                            value="<?= htmlspecialchars($color['color_name']) ?>" <?= in_array($color['color_name'], $selectedColors, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($color['color_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="button" class="filter-toggle" hidden>Show more</button>
        </div>

        <!-- Movement -->
        <div class="filter-group filter-group-collapsible">
            <h3 class="filter-heading">Movement</h3>

            <div class="filter-options">
                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="movement[]" value="quartz" <?= in_array('quartz', $selectedMovement, true) ? 'checked' : '' ?>>Quartz
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="movement[]" value="automatic"
                        <?= in_array('automatic', $selectedMovement, true) ? 'checked' : '' ?>>Automatic
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="movement[]" value="mechanical"
                        <?= in_array('mechanical', $selectedMovement, true) ? 'checked' : '' ?>>Mechanical
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="movement[]" value="manual" <?= in_array('manual', $selectedMovement, true) ? 'checked' : '' ?>>Manual
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="movement[]" value="solar" <?= in_array('solar', $selectedMovement, true) ? 'checked' : '' ?>>Solar
                </label>

                <label class="filter-option">
                    <input class="filter-price" type="checkbox" name="movement[]" value="kinetic" <?= in_array('kinetic', $selectedMovement, true) ? 'checked' : '' ?>>Kinetic
                </label>
            </div>

            <button type="button" class="filter-toggle" hidden>Show more</button>
        </div>

        <!-- Availability -->
        <div class="filter-group">
            <h3 class="filter-heading">Availability</h3>

            <label class="filter-option">
                <input class="filter-price" type="checkbox" name="include_out_of_stock" value="1"
                    <?= isset($_GET['include_out_of_stock']) ? 'checked' : '' ?>>Include Out of Stock Products
            </label>
        </div>

        <!-- Maximum Price -->
        <div class="filter-group">
            <h3 class="filter-heading">Maximum Price</h3>
            <input class="filter-price" type="number" name="max_price" min="0" placeholder="₹ 5000"
                value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
        </div>

        <!-- Buttons -->
        <div class="filter-actions">
            <button class="filter-button" type="submit" name="filter_submit" value="1">Apply Filters</button>
            <a class="clear-filters" href="index.php">Clear Filters</a>
        </div>
    </form>
</aside>

<script src="<?= JS_URL ?>/sidebar.js"></script>