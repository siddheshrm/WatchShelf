const { chromium } = require("playwright");
const fs = require("fs");
const path = require("path");

require("dotenv").config({
  path: path.resolve(__dirname, "../.env"),
});

const SCRAPER_API_KEY = process.env.SCRAPER_API_KEY;

const API_BASE_URL = "https://watchshelf.in/api/scraper";

const QUEUE_URL = `${API_BASE_URL}/central_queue.php`;
const UPDATE_URL = `${API_BASE_URL}/central_update.php`;

const SCRAPERS = {
  casio: require("./casio/scraper_v2"),
  amazon: require("./amazon/scraper_v2"),
  titan: require("./titan/scraper_v2"),
};

/*
|--------------------------------------------------------------------------
| CLI arguments
|--------------------------------------------------------------------------
|
| Examples:
|
| node scraper/scraper.js casio
| node scraper/scraper.js casio 5
| node scraper/scraper.js casio 5,10,15
| node scraper/scraper.js casio 1-10
| node scraper/scraper.js casio 5 --dry-run
|
*/

const args = process.argv.slice(2);

const retailer = (args[0] || "").toLowerCase();

const dryRun = args.includes("--dry-run");

const filterArgs = args.slice(1).filter((arg) => arg !== "--dry-run");

const filter = filterArgs[0] ?? null;

if (
  !SCRAPER_API_KEY ||
  !Object.prototype.hasOwnProperty.call(SCRAPERS, retailer) ||
  filterArgs.length > 1 ||
  args.filter((arg) => arg === "--dry-run").length > 1 ||
  (filter !== null && (filter === "" || filter.startsWith("--")))
) {
  process.exit(1);
}

// Logging
const LOG_DIRECTORY = path.join(__dirname, retailer, "logs");

fs.mkdirSync(LOG_DIRECTORY, {
  recursive: true,
});

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

function getTimestamp() {
  const date = getIndiaDateParts();

  return (
    `${date.year}-${date.month}-${date.day} ` +
    `${date.hour}:${date.minute}:${date.second}`
  );
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
  const line = `[${getTimestamp()}] ${message}`;

  fs.appendFileSync(LOG_FILE, `${line}\n`, "utf8");
}

// Helper functions
function formatPrice(price) {
  if (price === null || price === undefined) {
    return "null";
  }

  return Number(price).toLocaleString("en-IN", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function availabilityText(available) {
  return available ? "In Stock" : "Out of Stock";
}

// Fetch queue
async function fetchQueue() {
  const url = new URL(QUEUE_URL);

  url.searchParams.set("retailer", retailer);

  if (filter !== null) {
    url.searchParams.set("filter", filter);
  }

  const response = await fetch(url, {
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

// Send updates
async function sendUpdates(results) {
  const response = await fetch(UPDATE_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Scraper-Key": SCRAPER_API_KEY,
    },
    body: JSON.stringify({
      retailer,
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

  if (
    typeof data.success !== "boolean" ||
    !Number.isInteger(data.updated) ||
    !Number.isInteger(data.unchanged) ||
    !Number.isInteger(data.errors_recorded) ||
    !Number.isInteger(data.failed) ||
    !Array.isArray(data.results)
  ) {
    throw new Error(`Invalid update response:\n${responseText}`);
  }

  return data;
}

// Main execution
async function main() {
  let browser;

  writeLog(`========== Starting ${retailer} scraper ==========`);

  writeLog(`Mode: ${dryRun ? "DRY RUN" : "LIVE"}`);

  if (filter !== null) {
    writeLog(`Filter: ${filter}`);
  }

  try {
    const variants = await fetchQueue();

    writeLog(`${retailer} variants queued: ${variants.length}`);

    if (variants.length === 0) {
      writeLog("No variants to scrape.");
      return;
    }

    browser = await chromium.launch({
      headless: false,
    });

    const page = await browser.newPage();

    const results = [];

    let scrapedSuccessfully = 0;
    let scrapeErrors = 0;

    for (const variant of variants) {
      writeLog("--------------------------------------------------");

      const productName =
        `${variant.brand} ${variant.model_name} ` + `(${variant.color_name})`;

      writeLog(
        `Starting scrape for ${productName} ` +
          `[Variant ID: ${variant.variant_id}]`,
      );

      writeLog(`URL: ${variant.url}`);

      try {
        const scraped = await SCRAPERS[retailer].scrapeProduct(
          page,
          variant.url,
        );

        const result = {
          ...scraped,
          variant_id: variant.variant_id,
          watch_id: variant.watch_id,
          success: true,
        };

        results.push(result);
        scrapedSuccessfully++;

        const oldPrice = variant.price === null ? null : Number(variant.price);

        const newPrice = scraped.price === null ? null : Number(scraped.price);

        const oldAvailable = Number(variant.is_available);
        const newAvailable = scraped.available ? 1 : 0;

        const oldStatus = variant.last_scrape_status;

        const newStatus = newAvailable ? "success" : "out_of_stock";

        const priceChanged =
          newPrice !== null && (oldPrice === null || oldPrice !== newPrice);

        const availabilityChanged = oldAvailable !== newAvailable;

        const statusChanged = oldStatus !== newStatus;

        if (priceChanged || availabilityChanged || statusChanged) {
          writeLog(`Changes detected for ${productName}:`);

          if (priceChanged) {
            writeLog(
              `Price: ${formatPrice(oldPrice)} → ` + `${formatPrice(newPrice)}`,
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
        scrapeErrors++;

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

    writeLog(
      `Scrape summary: ` +
        `total=${variants.length}, ` +
        `successful=${scrapedSuccessfully}, ` +
        `errors=${scrapeErrors}`,
    );

    if (dryRun) {
      writeLog("DRY RUN: Database updates skipped.");
      return;
    }

    writeLog(`Sending ${results.length} result(s) to WatchShelf.`);

    const updateResult = await sendUpdates(results);

    const processed =
      updateResult.updated +
      updateResult.unchanged +
      updateResult.errors_recorded +
      updateResult.failed;

    if (processed !== results.length) {
      writeLog(
        `Update count mismatch: sent=${results.length}, processed=${processed}`,
      );

      process.exitCode = 1;
    }

    writeLog(
      `WatchShelf update result: ` +
        `updated=${updateResult.updated}, ` +
        `unchanged=${updateResult.unchanged}, ` +
        `errors=${updateResult.errors_recorded}, ` +
        `failed=${updateResult.failed}`,
    );

    for (const item of updateResult.results) {
      if (!item.success) {
        writeLog(
          `Update failed for variant ${item.variant_id}: ` +
            `${item.error ?? "Unknown error"}`,
        );
      }
    }

    if (!updateResult.success || updateResult.failed > 0) {
      process.exitCode = 1;
    }
  } catch (error) {
    writeLog(`${retailer} scraper failed: ${error.stack || error.message}`);

    process.exitCode = 1;
  } finally {
    if (browser) {
      try {
        await browser.close();
      } catch (error) {
        writeLog(`Browser cleanup failed: ${error.stack || error.message}`);
        process.exitCode = 1;
      }
    }

    writeLog(`========== Finished ${retailer} scraper ==========`);

    writeLog("");
  }
}

main();
