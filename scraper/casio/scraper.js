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
const QUEUE_URL = "http://localhost/WatchShelf/api/scraper/casio/queue.php";
const UPDATE_URL = "http://localhost/WatchShelf/api/scraper/casio/update.php";
*/

// Production
const QUEUE_URL = "https://watchshelf.in/api/scraper/casio/queue.php";
const UPDATE_URL = "https://watchshelf.in/api/scraper/casio/update.php";

// Logging
const LOG_DIRECTORY = path.join(__dirname, "logs");

if (!fs.existsSync(LOG_DIRECTORY)) {
  fs.mkdirSync(LOG_DIRECTORY, {
    recursive: true,
  });
}

// Get Indian date/time
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

// Generate a unique log filename
function getLogFilename() {
  const date = getIndiaDateParts();

  return `${date.year}-${date.month}-${date.day}_${date.hour}-${date.minute}-${date.second}.log`;
}

const LOG_FILE = path.join(LOG_DIRECTORY, getLogFilename());

// Logging helper
function writeLog(message) {
  const date = getIndiaDateParts();

  const timestamp =
    `${date.year}-${date.month}-${date.day} ` +
    `${date.hour}:${date.minute}:${date.second}`;

  const logMessage = `[${timestamp}] ${message}\n`;

  fs.appendFileSync(LOG_FILE, logMessage, "utf8");
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

// Casio scraping
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

  // Find all JSON-LD blocks
  const scripts = page.locator('script[type="application/ld+json"]');

  const count = await scripts.count();

  if (count === 0) {
    throw new Error("No JSON-LD found.");
  }

  // Search all JSON-LD blocks for Product schema
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

    // Casio product data is inside @graph
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

      const available = offers.availability === "https://schema.org/InStock";

      return {
        price,
        available,
        url,
      };
    }
  }

  throw new Error("Product data not found in JSON-LD.");
}

// Fetch Casio queue
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

// Send batch results back to WatchShelf
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

// Main execution
(async () => {
  writeLog("========== Starting casio scraper ==========");

  let browser;

  try {
    const variants = await fetchQueue();

    writeLog(`Casio variants queued: ${variants.length}`);

    // One Chromium instance for the entire Casio queue
    browser = await chromium.launch({
      headless: false, // Set to true for headless mode
    });

    const page = await browser.newPage();

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

        const newPrice = Number(scraped.price);

        const oldAvailable = Number(variant.is_available);
        const newAvailable = scraped.available ? 1 : 0;

        const oldStatus = variant.last_scrape_status;
        const newStatus = newAvailable ? "success" : "out_of_stock";

        const priceChanged = oldPrice === null || oldPrice !== newPrice;

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
              `Availability: ` +
                `${availabilityText(oldAvailable)} → ` +
                `${availabilityText(newAvailable)}`,
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
    console.error("Casio scraper failed:");
    console.error(error);

    writeLog(`Casio scraper failed: ${error.message}`);

    process.exitCode = 1;
  } finally {
    if (browser) {
      await browser.close();
    }

    writeLog("========== Finished casio scraper ==========");
  }
})();
