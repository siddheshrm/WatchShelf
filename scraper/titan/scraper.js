// Titan product scraper
/* Extracts price and availability from Titan's GTM product data and purchase buttons.
Browser creation, queue retrieval, logging, and database updates are handled by the centralized scraper controller. */

async function scrapeProduct(page, url) {
  const response = await page.goto(url, {
    waitUntil: "domcontentloaded",
    timeout: 60000,
  });

  if (!response) {
    throw new Error("No response received while loading Titan product page.");
  }

  const httpStatus = response.status();

  if (httpStatus !== 200) {
    throw new Error(`Titan HTTP ${httpStatus} received.`);
  }

  // Locate Titan's GTM product data.
  const gtmData = page.locator("#view-item-gtm-data");

  await gtmData.waitFor({
    state: "attached",
    timeout: 30000,
  });

  const rawValue = await gtmData.inputValue();

  if (!rawValue || !rawValue.trim()) {
    throw new Error("Titan GTM data is empty.");
  }

  let parsed;

  try {
    parsed = JSON.parse(rawValue);
  } catch {
    throw new Error("Invalid JSON in Titan GTM product data.");
  }

  const item = parsed?.ecommerce?.items?.[0];

  if (!item) {
    throw new Error("Titan GTM data does not contain ecommerce.items[0].");
  }

  // Extract and validate price.
  const price = Number.parseFloat(item.price);

  if (!Number.isFinite(price) || price <= 0) {
    throw new Error(`Invalid Titan price: ${item.price}`);
  }

  // Identify the main purchase buttons.
  const mainAddToCart = page.locator(
    "button.add-cart:not(.similar-add-to-cart)",
  );

  const buyNow = page.locator("button.buy-now");

  const hasVisibleAddToCart =
    (await mainAddToCart.count()) > 0 &&
    (await mainAddToCart.first().isVisible()) &&
    (await mainAddToCart.first().isEnabled());

  const hasVisibleBuyNow =
    (await buyNow.count()) > 0 &&
    (await buyNow.first().isVisible()) &&
    (await buyNow.first().isEnabled());

  return {
    price,
    available: hasVisibleAddToCart || hasVisibleBuyNow,
    url,
  };
}

module.exports = {
  scrapeProduct,
};
