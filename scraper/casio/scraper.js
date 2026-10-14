// Casio product scraper
/* Extracts price and availability from Casio's JSON-LD Product schema.
Browser creation, queue retrieval, logging, and database updates are handled by the centralized scraper controller. */

async function scrapeProduct(page, url) {
  const response = await page.goto(url, {
    waitUntil: "domcontentloaded",
    timeout: 60000,
  });

  if (!response) {
    throw new Error("No response received while loading product page.");
  }

  const httpStatus = response.status();

  if (httpStatus !== 200) {
    throw new Error(`HTTP ${httpStatus} received.`);
  }

  // Find all JSON-LD blocks.
  const scripts = page.locator('script[type="application/ld+json"]');

  const count = await scripts.count();

  if (count === 0) {
    throw new Error("No JSON-LD found.");
  }

  // Search JSON-LD blocks for the Product schema.
  for (let i = 0; i < count; i++) {
    const rawJson = await scripts.nth(i).textContent();

    if (!rawJson || !rawJson.trim()) {
      continue;
    }

    let json;

    try {
      json = JSON.parse(rawJson);
    } catch {
      continue;
    }

    if (!Array.isArray(json?.["@graph"])) {
      continue;
    }

    for (const item of json["@graph"]) {
      if (item?.["@type"] !== "Product") {
        continue;
      }

      const offers = item.offers;

      if (
        !offers ||
        offers.price === undefined ||
        offers.availability === undefined
      ) {
        continue;
      }

      const price = Number.parseFloat(offers.price);

      if (!Number.isFinite(price) || price <= 0) {
        throw new Error(`Invalid Casio price: ${offers.price}`);
      }

      // Availability validation
      const availability = offers.availability;

      let available;

      if (availability === "https://schema.org/InStock") {
        available = true;
      } else if (availability === "https://schema.org/OutOfStock") {
        available = false;
      } else {
        throw new Error(`Unsupported Casio availability: ${availability}`);
      }

      return {
        price,
        available,
        url,
      };
    }
  }

  throw new Error("Product data not found in JSON-LD.");
}

module.exports = {
  scrapeProduct,
};
