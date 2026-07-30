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
    buyingOptions.innerHTML = '<p class="no-buy-options">This watch is currently unavailable to buy online.</p>';
    return;
  }

  // Find the cheapest available retailer
  const availableRetailers = retailers.filter(
    (r) => r.is_available && r.price !== null,
  );

  if (availableRetailers.length === 0) {
    buyingOptions.innerHTML = '<p class="no-buy-options">This watch is currently unavailable to buy online.</p>';
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
      <p class="best-offer-text">Available at
        <a href="${bestRetailer.affiliate_url || bestRetailer.base_url}" target="_blank" rel="noopener noreferrer" class="retailer-link">${bestRetailer.retailer_name}</a>
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
          <a href="${retailerUrl}" target="_blank" rel="noopener noreferrer" class="retailer-link">${retailer.retailer_name}</a>
          <span class="retailer-price">${formatPrice(retailer.price)}</span>
        </div>
        `;
    });

    html += `</div>`;
  }

  buyingOptions.innerHTML = html;
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
