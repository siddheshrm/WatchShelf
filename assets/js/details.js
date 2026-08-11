let currentImage = 0;

const buyingOptions = document.getElementById("buying-options-content");
const mainImage = document.getElementById("main-image");
const nextButton = document.querySelector(".gallery-next");
const prevButton = document.querySelector(".gallery-prev");
const colorButtons = document.querySelectorAll(".color-item");

updateNavigation();

// Image Navigation
if (nextButton && prevButton) {
  nextButton.addEventListener("click", () => {
    if (images.length <= 1) return;

    currentImage = (currentImage + 1) % images.length;
    mainImage.src = images[currentImage];
  });

  prevButton.addEventListener("click", () => {
    if (images.length <= 1) return;

    currentImage = (currentImage - 1 + images.length) % images.length;
    mainImage.src = images[currentImage];
  });
}

function updateNavigation() {
  if (!prevButton || !nextButton) return;

  const hasMultipleImages = images.length > 1;

  prevButton.style.display = hasMultipleImages ? "" : "none";
  nextButton.style.display = hasMultipleImages ? "" : "none";
}

// Buying Options
function renderBuyingOptions(retailers) {
  if (!retailers || retailers.length === 0) {
    buyingOptions.innerHTML =
      '<p class="no-buy-options">This watch is currently unavailable to buy online.</p>';
    return;
  }

  // Find the cheapest available retailer
  const availableRetailers = retailers.filter(
    (r) => r.is_available && r.price !== null,
  );

  if (availableRetailers.length === 0) {
    buyingOptions.innerHTML =
      '<p class="no-buy-options">This watch is currently unavailable to buy online.</p>';
    return;
  }

  let bestRetailer = availableRetailers[0];

  availableRetailers.forEach((retailer) => {
    if (parseFloat(retailer.price) < parseFloat(bestRetailer.price)) {
      bestRetailer = retailer;
    }
  });

  const discountAmount = mrp - parseFloat(bestRetailer.price);
  let discountPercent = 0;

  if (discountAmount > 0) {
    discountPercent = Math.max(1, Math.round((discountAmount / mrp) * 100));
  }

  let html = `
  <div class="best-offer">
    <p class="best-offer-text">
      Available at
      <a href="${bestRetailer.affiliate_url || bestRetailer.base_url}"
        target="_blank"
        rel="noopener noreferrer"
        class="retailer-link">
        ${bestRetailer.retailer_name}
      </a>
      for
      <strong>${formatPrice(bestRetailer.price)}</strong>
`;

  if (discountAmount > 0) {
    html += `
      <span class="mrp"><del>${formatPrice(mrp)}</del></span>
      <span class="discount-badge">${discountPercent}% off</span>
  `;
  }

  html += `
    </p>

    <span class="retailer-last-checked">
      ${formatTimeAgo(bestRetailer.last_checked)}
    </span>
  </div>
`;

  if (availableRetailers.length > 1) {
    html += `
      <div class="other-retailers">
        <h4>Other Buying Options</h4>
    `;

    const otherRetailers = availableRetailers
      .filter((retailer) => retailer.id !== bestRetailer.id)
      .sort((a, b) => parseFloat(a.price) - parseFloat(b.price));

    otherRetailers.forEach((retailer) => {
      if (!retailer.affiliate_url && !retailer.base_url) {
        return;
      }

      const retailerUrl = retailer.affiliate_url || retailer.base_url;

      html += `
        <div class="retailer-row">
          <div class="retailer-info">
            <a href="${retailerUrl}" target="_blank" rel="noopener noreferrer" class="retailer-link">
              ${retailer.retailer_name}
            </a>
            <span class="retailer-last-checked">${formatTimeAgo(retailer.last_checked)}</span>
          </div>

          <span class="retailer-price">${formatPrice(retailer.price)}</span>
        </div>
      `;
    });

    html += `</div>`;
  }

  buyingOptions.innerHTML = html;
}

// Time Ago Function
function formatTimeAgo(timestamp) {
  if (!timestamp) {
    return "Last checked over a week ago";
  }

  // Convert timestamp to a IST date object
  const checkedAt = new Date(timestamp.replace(" ", "T") + "Z");

  if (Number.isNaN(checkedAt.getTime())) {
    return "Last checked over a week ago";
  }

  const now = new Date();

  const seconds = Math.max(0, Math.floor((now - checkedAt) / 1000));

  if (seconds < 60) {
    return "Last checked just now";
  }

  const minutes = Math.floor(seconds / 60);

  if (minutes < 60) {
    return `Last checked ${minutes} minute${minutes !== 1 ? "s" : ""} ago`;
  }

  const hours = Math.floor(minutes / 60);

  if (hours < 24) {
    return `Last checked ${hours} hour${hours !== 1 ? "s" : ""} ago`;
  }

  const days = Math.floor(hours / 24);

  if (days < 7) {
    return `Last checked ${days} day${days !== 1 ? "s" : ""} ago`;
  }

  return "Last checked over a week ago";
}

// Helper Functions
function updateVariantImages(variantId) {
  images = variantImages[variantId];
  currentImage = 0;

  mainImage.src = images[0];

  updateNavigation();
}

function setActiveColor(button) {
  colorButtons.forEach((btn) => btn.classList.remove("active"));
  button.classList.add("active");
}

function formatPrice(price) {
  return `₹${Number(price).toLocaleString("en-IN")}`;
}

// Initial Buying Options
if (selectedColor && variantsByColor[selectedColor]) {
  renderBuyingOptions(variantsByColor[selectedColor]);
}

// Event Listeners
colorButtons.forEach((button) => {
  button.addEventListener("click", function () {
    const variantId = this.dataset.variantId;
    const color = this.dataset.color;
    const retailers = variantsByColor[color];

    updateVariantImages(variantId);

    // Update buying options
    renderBuyingOptions(retailers);

    setActiveColor(this);
  });
});
