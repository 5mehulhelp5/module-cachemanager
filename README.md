# Magento 2 Cache Manager

Panth Cache Manager adds two things to a Magento 2 store: an observer that cleans the cache tags of a product, category, CMS page or CMS block when it is saved, and a cache warmup that requests the store's home, category, product and CMS page URLs over HTTP so that the full page cache is populated before real visitors arrive. Warmup runs from a Magento cron job on a configurable schedule (every six hours by default) or on demand from a console command, and every request is recorded in a database table that is shown as a grid in the admin.

The module works at the cache and HTTP layer and ships no frontend templates or web assets, so it does not depend on the theme in use. It is intended for store administrators who want scheduled warmup with a request log, and for developers who want to run warmup from the command line.

Product page: [Magento 2 Cache Manager](https://kishansavaliya.com/magento-2-cachemanager.html)

## Features

- Cleans the cache tags of the saved product, category, CMS page or CMS block when that entity is saved, with separate on/off switches for products, categories and CMS content.
- Cron job `panth_cachemanager_warmup` that requests the selected page types of every active store view, each with its own base URL, on the configured schedule using `curl_multi`, with a configurable number of parallel requests (default 5).
- Page types available for warmup: "Home Page", "Category Pages", "Product Pages" and "CMS Pages".
- Console command `panth:cachemanager:warmup` that runs the same warmup synchronously and prints per-URL results and a summary.
- Database table `panth_cache_warmup_log` that stores URL, page type, HTTP status, result, response time and timestamp for every warmup request, pruned after a configurable number of days (default 30).
- Admin grid "Warmup Log" under "Panth Extensions" with keyword search (URL, page type, status), filters, sorting, column controls and bookmarks.
- All settings except "Warmup Schedule (Cron)" can be set at default, website and store view scope; the schedule is set at default scope only.
- Unit tests for the helper, cron and observer classes under `Test/Unit`.

## Compatibility

| Platform | Versions |
|---|---|
| Magento Open Source | 2.4.4 to 2.4.8 |
| Adobe Commerce | 2.4.4 to 2.4.8 |
| PHP | 8.1 or later (`php: >=8.1` in composer.json) |

Composer constraints on Magento packages: `magento/framework` ^103.0 or ^104.0, `magento/module-store` ^101.0 or ^102.0, `magento/module-catalog` ^104.0 or ^105.0, `magento/module-cms` ^104.0 or ^105.0.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 to 2.4.8.
- PHP 8.1 or later with the curl extension (`ext-curl` is a Composer requirement; warmup uses the `curl_multi` functions).
- `mage2kishan/module-core` ^1.0 (module `Panth_Core`). The configuration helper extends `Panth\Core\Helper\AbstractConfig`, and the admin menu, ACL and configuration tab hang off the "Panth Extensions" entries that `Panth_Core` provides. Composer installs it automatically.
- The module sequence also declares `Magento_Backend` and `Magento_PageCache`.
- Magento cron must be running for scheduled warmup. Warmup requests are plain HTTP GET requests to the store's base URL, so the store must be reachable from the server that runs cron. The module does not talk to Varnish or Redis directly.

## Installation

```bash
composer require mage2kishan/module-cachemanager
bin/magento module:enable Panth_Core Panth_CacheManager
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

`setup:di:compile` is only needed in production mode. The module ships no files under `view/*/web`, so `setup:static-content:deploy` is not required for it.

Check that the module is enabled:

```bash
bin/magento module:status Panth_CacheManager
```

After installation the module is switched off ("Enable Cache Manager" defaults to No), so nothing changes until it is enabled in the configuration.

## Configuration

Go to Stores > Configuration > Panth Extensions > Cache Manager. Every field except "Warmup Schedule (Cron)" can be set at default, website and store view scope. All config paths start with `panth_cachemanager/`.

### General Settings

| Setting | Default | What it does |
|---|---|---|
| Enable Cache Manager | No | Master switch. When set to No, the save observer does nothing and the warmup cron job does nothing. Path: `panth_cachemanager/general/enabled`. |

### Cache Warmup

Shown only when "Enable Cache Manager" is Yes; "Warmup Schedule (Cron)", "Pages to Warm Up", "Concurrent Requests" and "Log Redirect Responses As" are shown only when "Enable Cache Warmup" is Yes.

| Setting | Default | What it does |
|---|---|---|
| Enable Cache Warmup | No | Allows the cron job and the console command to run. Path: `panth_cachemanager/warmup/enabled`. |
| Warmup Schedule (Cron) | `0 */6 * * *` | Cron expression for the `panth_cachemanager_warmup` job, read through the `config_path` of the job in `etc/crontab.xml`. Default scope only. If it is empty, `0 */6 * * *` is used. The value is validated on save: it must have five fields (minute 0-59, hour 0-23, day of month 1-31, month 1-12 or jan-dec, day of week 0-6 or sun-sat) using `*`, numbers, ranges, lists and `/step`; anything else is rejected with an error. Path: `panth_cachemanager/warmup/warmup_schedule`. |
| Pages to Warm Up | Home Page, Category Pages, Product Pages, CMS Pages | Multiselect of page types to request. Path: `panth_cachemanager/warmup/warmup_pages`, stored as `home,catalog_category,catalog_product,cms`. |
| Concurrent Requests | 5 | Number of URLs requested in parallel per batch. Whole number from 1 to 50; stored values above 50 are capped at 50. Path: `panth_cachemanager/warmup/concurrent_requests`. |
| Log Redirect Responses As | Success | Status recorded for a URL that answers with a 3xx redirect: "Success", "Skipped" or "Failed". Redirects are never followed. Path: `panth_cachemanager/warmup/redirect_status`. |
| Warmup Log Retention (days) | 30 | Log rows older than this many days are deleted at the start of each warmup run (cron or console). 0 keeps all rows. Default scope only. Path: `panth_cachemanager/warmup/log_retention_days`. |

### Cache Invalidation

Shown only when "Enable Cache Manager" is Yes; the three per-entity fields are shown only when "Enable Smart Invalidation" is Yes.

| Setting | Default | What it does |
|---|---|---|
| Enable Smart Invalidation | Yes | Enables the save observer. Path: `panth_cachemanager/invalidation/smart_invalidation`. |
| Invalidate on Product Save | Yes | On `catalog_product_save_after`, cleans the cache tags of the saved product. Path: `panth_cachemanager/invalidation/invalidate_on_product_save`. |
| Invalidate on Category Save | Yes | On `catalog_category_save_after`, cleans the cache tags of the saved category. Path: `panth_cachemanager/invalidation/invalidate_on_category_save`. |
| Invalidate on CMS Save | Yes | On `cms_page_save_after` and `cms_block_save_after`, cleans the cache tags of the saved page or block. Path: `panth_cachemanager/invalidation/invalidate_on_cms_save`. |

### Admin menu

The admin sidebar gets a "Cache Manager" entry under "Panth Extensions" with two items: "Warmup Log", which opens the request log grid, and "Configuration", which opens the configuration section above. Both require the ACL resource `Panth_CacheManager::config`.

## Usage

### Smart invalidation

When the module and "Enable Smart Invalidation" are both on, saving a product, category, CMS page or CMS block runs `Observer\CacheInvalidate`. The observer checks the per-entity switch and then calls `Magento\Framework\App\CacheInterface::clean()` with the tags returned by the saved entity's `getIdentities()` (for example `cat_p_42` for product 42), so only entries belonging to that entity are removed. If the event carries no entity or the entity returns no identities, it falls back to the tag for the entity type (`catalog_product`, `catalog_category`, `cms_page` or `cms_block`). Each invalidation is written to the Magento log at info level with the event name and tags. Errors are logged at error level and do not interrupt the save.

### Scheduled warmup

Cron job `panth_cachemanager_warmup` (cron group `default`, schedule from "Warmup Schedule (Cron)", default `0 */6 * * *`) runs `Cron\WarmupCache::execute()`. It returns immediately unless both "Enable Cache Manager" and "Enable Cache Warmup" are Yes at default scope. Otherwise it deletes log rows older than "Warmup Log Retention (days)" and then, for every active store view where both settings are also Yes at store view scope:

1. Builds the URL list from that store view's selected page types, using the store view's own base URL: the home page (`/`), active categories with level greater than 1 (`<base_url>/<url_path><category_url_suffix>`), enabled products in that store's website with visibility "Catalog" or "Catalog, Search" (`<base_url>/<url_key><product_url_suffix>`), and active CMS pages of that store except `no-route` (`<base_url>/<identifier>`). The suffixes come from `catalog/seo/category_url_suffix` and `catalog/seo/product_url_suffix`. Duplicate URLs are removed, and only http or https URLs on the host and port of the store base URL are requested.
2. Splits the list into batches of that store view's "Concurrent Requests" URLs and requests each batch with `curl_multi`. Each request is limited to http and https, does not follow redirects, verifies the SSL certificate, uses a 10 second connect timeout and a 30 second total timeout, and sends the user agent `PanthCacheManager/1.0 (Warmup)`.
3. Records every request in `panth_cache_warmup_log` with the real HTTP status and a result: `failed` when the transfer itself failed (the per-handle result from `curl_multi_info_read()`, for example a refused connection, a timeout or a certificate error, recorded with HTTP status 0) or the status is 400 or higher; the "Log Redirect Responses As" value for 3xx; otherwise `success`.

Product URLs are built from `url_key` and do not include category paths. Any exception during the run is logged as `CacheManager Cron Error`.

### Console command

```bash
bin/magento panth:cachemanager:warmup
bin/magento panth:cachemanager:warmup --quiet-rows
```

Runs the same warmup synchronously and writes to the log table. If "Enable Cache Manager" or "Enable Cache Warmup" is No, the command prints a notice and exits with 0 without requesting anything. Without options it prints one line per URL (OK, SKIP or FAIL, HTTP status, response time, URL and the transfer error if any) as results arrive, followed by a summary line with success and failure counts (and the skipped count when there is one), average response time and total wall time. With `--quiet-rows` the per-URL lines are not printed and only the summary line is shown. The exit code is 0 when no request failed and 1 when at least one failed; skipped requests do not count as failures. If no URLs were collected the command prints a notice and exits with 0.

### Warmup Log grid

"Panth Extensions" > "Cache Manager" > "Warmup Log" (admin route `panth_cachemanager/warmup/index`, page title "Cache Warmup Log") lists every warmup request with the columns ID, URL, Page Type, HTTP Status, Status, Response Time (ms) and Warmed At. All columns can be filtered and sorted. Page Type is the page type the URL was collected for (Home Page, Category Page, Product Page or CMS Page) and has a select filter. Status is shown as a coloured label (Success green, Failed red, Skipped and Pending yellow) and has a select filter. HTTP Status shows "No response" when the request got no HTTP answer (for example DNS, connection or SSL errors). Rows older than "Warmup Log Retention (days)" are deleted at the start of each warmup run.

## Developer Notes

- Module name: `Panth_CacheManager`. Composer package: `mage2kishan/module-cachemanager`. PHP namespace: `Panth\CacheManager`.
- `Helper\Data` (extends `Panth\Core\Helper\AbstractConfig`): `isEnabled()`, `getCacheTtl()` (returns `panth_cachemanager/full_page/ttl`, default 86400; the module does not use it and has no admin field for it), `isWarmupEnabled()`, `getWarmupPages()`, `getWarmupSchedule()`, `getConcurrentRequests()`, `getRedirectStatus()`, `getLogRetentionDays()`, `isSmartInvalidationEnabled()`, `shouldInvalidateOnProductSave()`, `shouldInvalidateOnCategorySave()`, `shouldInvalidateOnCmsSave()`, `getProductUrlSuffix()`, `getCategoryUrlSuffix()`. All accept an optional store ID.
- `Cron\WarmupCache`: `execute()` for cron and `runWarmup(?callable $onResult = null): array`, which returns one row per URL (`url`, `page_type`, `status`, `http_code`, `response_time_ms`, `error`) and calls the optional callback with each row as it completes. This is the entry point for running warmup from custom code.
- `Console\Command\WarmupCommand`: registered in `etc/di.xml` on `Magento\Framework\Console\CommandList` as `panth_cachemanager_warmup`.
- `Observer\CacheInvalidate`: registered in `etc/events.xml` for `catalog_product_save_after`, `catalog_category_save_after`, `cms_page_save_after` and `cms_block_save_after`.
- `Controller\Adminhtml\Warmup\Index`: admin controller for the grid (`ADMIN_RESOURCE = Panth_CacheManager::config`); layout `panth_cachemanager_warmup_index.xml`; UI component `panth_cachemanager_warmup_listing` backed by `Model\ResourceModel\WarmupLog\Grid\Collection` (data source `panth_cachemanager_warmup_listing_data_source`).
- `Model\WarmupLog`, `Model\ResourceModel\WarmupLog` and `Model\ResourceModel\WarmupLog\Collection`: entity, resource model and collection for the log table.
- `Model\Config\Source\WarmupPages`: option source with the values `home`, `catalog_category`, `catalog_product`, `cms`. `Model\Config\Backend\Enabled`: backend model for the master switch (currently only calls the parent `beforeSave()`).
- ACL resources: `Panth_CacheManager::cachemanager` ("Cache Manager") and `Panth_CacheManager::config` ("Configuration"), both under `Panth_Core::panth_extensions`.
- Admin route: `panth_cachemanager` (front name `panth_cachemanager`). There are no frontend routes, plugins, preferences, web API endpoints or frontend view files.
- Database table `panth_cache_warmup_log` (from `etc/db_schema.xml`): `log_id` (primary key), `url`, `page_type`, `http_status`, `status` (default `pending`), `response_time` (decimal, milliseconds), `warmed_at` (timestamp, default current time). Indexes on `status` and `warmed_at`.

## Uninstallation

```bash
bin/magento module:disable Panth_CacheManager
composer remove mage2kishan/module-cachemanager
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The table `panth_cache_warmup_log` and the configuration values stored under `panth_cachemanager/` in `core_config_data` remain in the database after these commands; drop or delete them manually if they are no longer wanted.

## Support

- Product page: [Magento 2 Cache Manager](https://kishansavaliya.com/magento-2-cachemanager.html)
- Contact form: [kishansavaliya.com/contact](https://kishansavaliya.com/contact)
- Email: kishansavaliyakb@gmail.com
- Bug reports: [GitHub issues](https://github.com/mage2sk/module-cachemanager/issues)

## Documentation

[USER_GUIDE.md](USER_GUIDE.md) covers installation, each configuration group (General Settings, Cache Warmup, Cache Invalidation), the Warmup Log grid, a troubleshooting table and support contacts.

## License

Commercial software license. See [LICENSE.txt](LICENSE.txt) in this repository.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Links

- Website: [kishansavaliya.com](https://kishansavaliya.com)
- All extensions: [Magento extensions catalogue](https://kishansavaliya.com/magento-extensions.html)
- GitHub: [mage2sk/module-cachemanager](https://github.com/mage2sk/module-cachemanager)
- Packagist: [mage2kishan/module-cachemanager](https://packagist.org/packages/mage2kishan/module-cachemanager)
