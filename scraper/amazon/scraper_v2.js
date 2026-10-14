// Amazon product scraper
/* Extracts price and availability from Amazon product pages.
Browser creation, queue retrieval, logging, and database updates are handled by the centralized scraper controller. */

function normalizeAmazonUrl(url) {
  const match = url.match(/\/(?:dp|gp\/product)\/([A-Z0-9]{10})(?=[/?#]|$)/i);

  if (!match) {
    return {
      url,
      asin: null,
    };
  }

  const asin = match[1].toUpperCase();

  return {
    url: `https://www.amazon.in/dp/${asin}`,
    asin,
  };
}

// Detect Amazon CAPTCHA or robot checks
async function detectChallenge(page) {
  const html = await page.content();

  const signatures = [
    "validateCaptcha",
    "api-services-support@amazon.com",
    "Enter the characters you see below",
    "Sorry, we just need to make sure you're not a robot",
    "Type the characters you see in this image",
    "Robot Check",
  ];

  for (const signature of signatures) {
    if (html.toLowerCase().includes(signature.toLowerCase())) {
      return signature;
    }
  }

  return null;
}

// Extract product price
async function extractPrice(page) {
  const selectors = [
    "#corePrice_feature_div .a-price-whole",
    "#apex_desktop .a-price-whole",
    ".priceToPay .a-price-whole",
    "#priceblock_ourprice",
    "#priceblock_dealprice",
    "#priceblock_saleprice",
  ];

  for (const selector of selectors) {
    const locator = page.locator(selector);

    if ((await locator.count()) === 0) {
      continue;
    }

    const rawPrice = await locator.first().textContent();

    if (!rawPrice) {
      continue;
    }

    const cleaned = rawPrice.replace(/[^\d.,]/g, "").replace(/,/g, "");

    const price = Number.parseFloat(cleaned);

    if (Number.isFinite(price) && price > 0) {
      return price;
    }
  }

  return null;
}

// Scrape Amazon product
async function scrapeProduct(page, originalUrl) {
  const normalized = normalizeAmazonUrl(originalUrl);

  if (!normalized.asin) {
    throw new Error("Invalid Amazon product URL: ASIN not found.");
  }

  const response = await page.goto(normalized.url, {
    waitUntil: "domcontentloaded",
    timeout: 60000,
  });

  if (!response) {
    throw new Error("Amazon navigation returned no response.");
  }

  // Allow initial page JavaScript to populate content.
  await page.waitForTimeout(1500);

  const challenge = await detectChallenge(page);

  if (challenge) {
    throw new Error(`Amazon challenge page detected (${challenge}).`);
  }

  const httpStatus = response.status();

  if (httpStatus !== 200) {
    throw new Error(`Amazon HTTP ${httpStatus} received.`);
  }

  // Validate product page
  const titleLocator = page.locator("#productTitle");

  if ((await titleLocator.count()) === 0) {
    throw new Error("Amazon response is not a valid product page.");
  }

  const title = (await titleLocator.first().textContent())?.trim();

  if (!title) {
    throw new Error("Amazon product title is empty.");
  }

  // Check availability
  const availabilityLocator = page.locator("#availability");

  let availabilityTextValue = "";

  if ((await availabilityLocator.count()) > 0) {
    availabilityTextValue =
      (await availabilityLocator.first().textContent())
        ?.replace(/\s+/g, " ")
        .trim()
        .toLowerCase() || "";
  }

  const outOfStockPhrases = [
    "currently unavailable",
    "out of stock",
    "temporarily out of stock",
    "we don't know when or if this item will be back in stock",
  ];

  const isOutOfStock = outOfStockPhrases.some((phrase) =>
    availabilityTextValue.includes(phrase),
  );

  // Confirmed out of stock
  if (isOutOfStock) {
    return {
      price: null,
      available: false,
      url: normalized.url,
    };
  }

  // Extract price
  const price = await extractPrice(page);

  if (price === null) {
    throw new Error("Product price not found.");
  }

  return {
    price,
    available: true,
    url: normalized.url,
  };
}

module.exports = {
  scrapeProduct,
};
