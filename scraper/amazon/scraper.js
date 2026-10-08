const { chromium } = require("playwright");
const fs = require("fs");
const path = require("path");

require("dotenv").config({
  path: path.resolve(__dirname, "../../.env"),
});

const SCRAPER_API_KEY = process.env.SCRAPER_API_KEY;

if (!SCRAPER_API_KEY) {
  throw new Error("SCRAPER_API_KEY is not configured in .env.");
}

// WatchShelf scraper API

/*
const QUEUE_URL =
  "http://localhost/WatchShelf/api/scraper/amazon/queue.php";

const UPDATE_URL =
  "http://localhost/WatchShelf/api/scraper/amazon/update.php";
*/

// Production
const QUEUE_URL = "https://watchshelf.in/api/scraper/amazon/queue.php";

const UPDATE_URL = "https://watchshelf.in/api/scraper/amazon/update.php";

// Logging
const LOG_DIRECTORY = path.join(__dirname, "logs");

if (!fs.existsSync(LOG_DIRECTORY)) {
  fs.mkdirSync(LOG_DIRECTORY, {
    recursive: true,
  });
}

function getIndiaDateParts() {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Kolkata",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23",
  }).formatToParts(new Date());

  const values = {};

  for (const part of parts) {
    if (part.type !== "literal") {
      values[part.type] = part.value;
    }
  }

  return values;
}

function getLogFilename() {
  const date = getIndiaDateParts();

  return (
    `${date.year}-${date.month}-${date.day}_` +
    `${date.hour}-${date.minute}-${date.second}.log`
  );
}

const LOG_FILE = path.join(LOG_DIRECTORY, getLogFilename());

function writeLog(message) {
  const date = getIndiaDateParts();

  const timestamp =
    `${date.year}-${date.month}-${date.day} ` +
    `${date.hour}:${date.minute}:${date.second}`;

  fs.appendFileSync(LOG_FILE, `[${timestamp}] ${message}\n`, "utf8");
}

function availabilityText(available) {
  return available ? "In Stock" : "Out of Stock";
}

function formatPrice(price) {
  return Number(price).toLocaleString("en-IN", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/*
|--------------------------------------------------------------------------
| Normalize Amazon URL
|--------------------------------------------------------------------------
*/

function normalizeAmazonUrl(url) {
  const match = url.match(/\/dp\/([A-Z0-9]{10})/i);

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

/*
|--------------------------------------------------------------------------
| Detect Amazon challenge
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Parse price
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Scrape Amazon product
|--------------------------------------------------------------------------
*/

async function scrapeProduct(page, originalUrl) {
  const normalized = normalizeAmazonUrl(originalUrl);

  writeLog(`Normalized URL: ${normalized.url}`);

  if (normalized.asin) {
    writeLog(`ASIN: ${normalized.asin}`);
  }

  const response = await page.goto(normalized.url, {
    waitUntil: "domcontentloaded",
    timeout: 60000,
  });

  if (!response) {
    throw new Error("Amazon navigation returned no response.");
  }

  writeLog(`HTTP status: ${response.status()}`);

  /*
   * Give Amazon's initial JavaScript a short,
   * deterministic period to populate the page.
   */
  await page.waitForTimeout(1500);

  const challenge = await detectChallenge(page);

  if (challenge) {
    throw new Error(`Amazon challenge page detected (${challenge}).`);
  }

  /*
  |--------------------------------------------------------------------------
  | Validate requested product
  |--------------------------------------------------------------------------
  */

  const titleLocator = page.locator("#productTitle");

  if ((await titleLocator.count()) === 0) {
    throw new Error("Amazon response is not a valid product page.");
  }

  const title = (await titleLocator.first().textContent())?.trim();

  if (!title) {
    throw new Error("Amazon product title is empty.");
  }

  writeLog(`Product title: ${title}`);

  /*
  |--------------------------------------------------------------------------
  | Availability
  |--------------------------------------------------------------------------
  */

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

  /*
  |--------------------------------------------------------------------------
  | Confirmed OOS
  |--------------------------------------------------------------------------
  |
  | Do not scrape a generic Amazon price after OOS is confirmed.
  |
  */

  if (isOutOfStock) {
    writeLog("Availability: OUT OF STOCK");

    return {
      price: null,
      available: false,
      url: normalized.url,
    };
  }

  /*
  |--------------------------------------------------------------------------
  | Price
  |--------------------------------------------------------------------------
  */

  const price = await extractPrice(page);

  if (price === null) {
    throw new Error("Product price not found.");
  }

  writeLog(`Availability: IN STOCK`);

  writeLog(`Price: ₹${formatPrice(price)}`);

  return {
    price,
    available: true,
    url: normalized.url,
  };
}

/*
|--------------------------------------------------------------------------
| WatchShelf API
|--------------------------------------------------------------------------
*/

async function fetchQueue() {
  const response = await fetch(QUEUE_URL, {
    headers: {
      "X-Scraper-Key": SCRAPER_API_KEY,
    },
  });

  const responseText = await response.text();

  if (!response.ok) {
    throw new Error(
      `Queue request failed: HTTP ${response.status}\n${responseText}`,
    );
  }

  let data;

  try {
    data = JSON.parse(responseText);
  } catch {
    throw new Error(`Queue returned invalid JSON:\n${responseText}`);
  }

  if (!data.success || !Array.isArray(data.variants)) {
    throw new Error(`Invalid queue response:\n${responseText}`);
  }

  return data.variants;
}

async function sendUpdates(results) {
  const response = await fetch(UPDATE_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Scraper-Key": SCRAPER_API_KEY,
    },
    body: JSON.stringify({
      results,
    }),
  });

  const responseText = await response.text();

  if (!response.ok) {
    throw new Error(
      `Update request failed: HTTP ${response.status}\n${responseText}`,
    );
  }

  let data;

  try {
    data = JSON.parse(responseText);
  } catch {
    throw new Error(`Update returned invalid JSON:\n${responseText}`);
  }

  if (!data.success && data.failed === undefined) {
    throw new Error(data.error || "Invalid update response.");
  }

  return data;
}

/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

(async () => {
  writeLog("========== Starting amazon scraper ==========");

  const browser = await chromium.launch({
    headless: false,
  });

  const context = await browser.newContext();

  const page = await context.newPage();

  try {
    const variants = await fetchQueue();

    writeLog(`Amazon variants queued: ${variants.length}`);

    const results = [];

    for (const variant of variants) {
      writeLog("--------------------------------------------------");

      const productName =
        `${variant.brand} ` +
        `${variant.model_name} ` +
        `(${variant.color_name})`;

      writeLog(
        `Starting scrape for ${productName} ` +
          `[Variant ID: ${variant.variant_id}]`,
      );

      writeLog(`URL: ${variant.url}`);

      try {
        const scraped = await scrapeProduct(page, variant.url);

        const result = {
          variant_id: variant.variant_id,
          watch_id: variant.watch_id,
          success: true,
          ...scraped,
        };

        results.push(result);

        const oldPrice = variant.price === null ? null : Number(variant.price);

        const newPrice =
          scraped.price === null ? oldPrice : Number(scraped.price);

        const oldAvailable = Number(variant.is_available);

        const newAvailable = scraped.available ? 1 : 0;

        const oldStatus = variant.last_scrape_status;

        const newStatus = newAvailable ? "success" : "out_of_stock";

        const priceChanged =
          scraped.price !== null &&
          (oldPrice === null || oldPrice !== newPrice);

        const availabilityChanged = oldAvailable !== newAvailable;

        const statusChanged = oldStatus !== newStatus;

        if (priceChanged || availabilityChanged || statusChanged) {
          writeLog(`Changes detected for ${productName}:`);

          if (priceChanged) {
            writeLog(
              `Price: ${
                oldPrice === null ? "null" : formatPrice(oldPrice)
              } → ${formatPrice(newPrice)}`,
            );
          }

          if (availabilityChanged) {
            writeLog(
              `Availability: ${availabilityText(
                oldAvailable,
              )} → ${availabilityText(newAvailable)}`,
            );
          }

          if (statusChanged) {
            writeLog(`Status: ${oldStatus ?? "null"} → ${newStatus}`);
          }
        } else {
          writeLog("No changes detected.");
        }

        writeLog(`Finished scraping ${productName}`);
      } catch (error) {
        results.push({
          variant_id: variant.variant_id,
          watch_id: variant.watch_id,
          success: false,
          url: variant.url,
          error: error.message,
        });

        writeLog(`Scrape failed for ${productName}: ${error.message}`);
      }
    }

    writeLog("--------------------------------------------------");

    writeLog(`Sending ${results.length} scrape result(s) to WatchShelf.`);

    const updateResult = await sendUpdates(results);

    writeLog(
      `WatchShelf update result: ` +
        `updated=${updateResult.updated ?? 0}, ` +
        `unchanged=${updateResult.unchanged ?? 0}, ` +
        `errors=${updateResult.errors_recorded ?? 0}, ` +
        `failed=${updateResult.failed ?? 0}`,
    );
  } catch (error) {
    console.error("Amazon scraper failed:");

    console.error(error);

    writeLog(`Amazon scraper failed: ${error.message}`);

    process.exitCode = 1;
  } finally {
    await browser.close();

    writeLog("========== Finished amazon scraper ==========");
  }
})();
