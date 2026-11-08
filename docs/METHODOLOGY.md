# Methodology

This page explains exactly how the numbers are produced, so that anyone can check them, criticise them, or reproduce them. Everything described here is in [`run.sh`](../run.sh) and [`lib/`](../lib).

## Principles

1. **Official releases, unchanged.** Éclair comes from its release archive (`dist/eclair-cms-<version>.zip`, SHA-256 saved in `meta.json`). WordPress comes from the official build repository [`WordPress/WordPress`](https://github.com/WordPress/WordPress) at the release tag. Cache Enabler comes from [`keycdn/cache-enabler`](https://github.com/keycdn/cache-enabler), Kirby from its official [Starterkit](https://github.com/getkirby/starterkit).
2. **Default settings.** Each CMS keeps its default theme and default options. The only changes are the ones a normal site owner makes on day one (site name, language, pretty permalinks for WordPress) and the few documented below, each applied to make the comparison fairer, never to favour Éclair.
3. **Same machine, same stack, same content.** All sites live on one server, behind one web server, one PHP-FPM pool, one OPcache configuration. They contain the same articles.
4. **One site at a time.** Each site is measured alone; the others are idle.
5. **Real browser requests.** Every request sends the headers of a recent Chrome (`Accept: text/html…`, `User-Agent`, `Accept-Language`, `Accept-Encoding: gzip`).
6. **Raw data published.** Each run saves its JSON files next to its report. Charts and tables are generated from these files by script; no number is typed by hand.

## Server stack

| Layer | Setting |
|---|---|
| OS | Ubuntu 24.04 LTS, packages from the Ubuntu archive |
| Web server, `BENCH_SERVER=apache` (default) | Apache 2.4, `mpm_event`, `mod_rewrite`, `mod_headers`, `mod_expires`, `mod_deflate` (Ubuntu defaults), `AllowOverride All` so each CMS uses its own `.htaccess` |
| Web server, `BENCH_SERVER=nginx` | Nginx 1.24 (Ubuntu), each CMS with its **official Nginx rules** (Éclair: its guide; WordPress: the official recipe; Kirby: its documentation). gzip on for the same text types as Apache's `mod_deflate` defaults. |
| PHP | PHP 8.3 FPM, OPcache on (`opcache.memory_consumption=128`), `memory_limit=256M`, Ubuntu pool defaults (`pm = dynamic`, `pm.max_children = 5`) |
| Database | MariaDB 10.11, Ubuntu defaults (WordPress only; Éclair and Kirby have no database) |
| Compression | gzip for every site. Brotli is not installed, so no site gets it. |

## The sites

| Key | What it is | Page cache |
|---|---|---|
| `eclair` | Éclair release archive, installed through its own web installer (blog type, English) | Built in, on by default. `index.php` serves cached pages before loading the application. |
| `wordpress-cache` | WordPress, default theme (Twenty Twenty-Five), `/%postname%/` permalinks, plus Cache Enabler with default settings | Cache Enabler: files served by `advanced-cache.php`, early in `wp-settings.php` |
| `wordpress-fastcgi` (Nginx only) | Same WordPress, no plugin, behind an **Nginx FastCGI cache** with the usual exclusions (POST, query strings, `/wp-admin/`, logged-in and comment cookies) | Outside PHP: Nginx answers from its cache without calling PHP |
| `wordpress` | Same WordPress, no plugin | None |
| `kirby` | Kirby Starterkit (official demo site), with `debug` off and Kirby's built-in **pages cache** on (`cache.pages.active`), 10 notes per list page instead of 6 | Kirby pages cache, served by Kirby's router |

**Why Cache Enabler?** It is free, popular, simple, and works on any host with default settings: the typical "WordPress with a page cache" set-up. **Why Nginx FastCGI cache?** It is the fastest common way to cache WordPress on a VPS, and the one WordPress professionals usually put forward. **Why Kirby?** It is the best-known flat-file PHP CMS, Éclair's closest architecture. Kirby needs a licence for production sites; local testing is allowed.

**Not tested here, and why.** WP Rocket is a paid plugin (no public download). LiteSpeed Cache needs a LiteSpeed web server. Grav, Drupal and Statamic install through Composer, and Ghost through its own CLI on Node.js; these can be added as setup scripts in `lib/` (contributions welcome), with the same rules.

## Content

[`content/make-articles.php`](../content/make-articles.php) generates N articles (default 20, `BENCH_ARTICLES`): title, short description, five paragraphs, one article per hour. The same file feeds every CMS:

- **Éclair**: through `Content::save()`, the function the editor uses ([`lib/seed-eclair.php`](../lib/seed-eclair.php)). For more than 50 articles, each item is still built and validated by `Content::build()`, written as Éclair writes it, and the index is rebuilt once at the end (calling `save()` thousands of times would rewrite the whole index each time).
- **WordPress**: through `wp_insert_post()`, as block-editor paragraphs ([`lib/setup-wordpress.php`](../lib/setup-wordpress.php)).
- **Kirby**: as Starterkit "notes", one folder and one `note.txt` per article, with a text block ([`lib/setup-kirby.php`](../lib/setup-kirby.php)).

Sample content is removed (WordPress "Hello world", Éclair starter posts, Starterkit notes); other pages created by the installers are kept. Every list page shows the 10 latest articles (Éclair's default of 9 and Kirby Starterkit's 6 are set to 10 to match WordPress).

The **list page** is the page that lists the latest articles: the home page for Éclair and WordPress, `/notes` for Kirby (the Kirby Starterkit home page is a photo gallery).

**Éclair's limit:** Éclair 2.x accepts at most 3,000 contents (articles + pages). The large-site runs therefore use 1,000 and 2,900 articles.

## Measurements

Timing measurements run on the server itself, against `127.0.0.1`, so that the network does not hide the speed of the software (see "separate load machine" below for the alternative).

### Response time (TTFB)

`curl` time to first byte (`CURLINFO_STARTTRANSFER_TIME`) on the list page and on one article: 50 warm-up requests, then **300 sequential requests**. Reported: **median** and **95th percentile**.

### Page built from scratch

The same measurement, with the page cache emptied **before each of 30 requests**: the speed of the CMS engine itself (reading content, running the theme, producing the HTML). For the Nginx FastCGI cache, Nginx's cache folder is emptied.

### Throughput and latency under load

[wrk](https://github.com/wg/wrk) with the same browser headers:

- **Main test**: 20 connections, 20 seconds, on each page. Reported: requests per second, p50 and p99 latency, non-2xx answers (0 in every published run).
- **Scaling test**: article page at 1, 10, 50 and 100 connections, 10 seconds each.

### Page weight

What a browser downloads to display the page, **gzip-compressed as served**: the HTML and every stylesheet, script and image it references. **Web fonts are listed apart and not added**, because a browser only downloads the fonts the text actually uses, which a script cannot know without a browser engine. Lighthouse (below) gives the browser's own count.

### Lighthouse (real browser)

[Lighthouse](https://github.com/GoogleChrome/lighthouse) in a real headless Chromium, with its default settings: mobile emulation, simulated slow 4G and 4× CPU slowdown, the settings of PageSpeed Insights. Five runs per page; the run with the median performance score is reported whole. Metrics: performance score, LCP, FCP, TBT, CLS, Speed Index, transfer size, number of requests, and the accessibility, best-practices and SEO scores.

A run that Lighthouse cannot complete (for example `NO_NAVSTART`) is discarded and retried, up to 8 times per page; the number of discarded runs is saved in `lighthouse.json` (`failed_runs`). In the published runs this only happened on Éclair pages: Éclair sends a `Cross-Origin-Opener-Policy: same-origin` security header, which makes Chromium switch processes during the navigation, and Lighthouse sometimes loses the start of its trace. Visitors are not affected.

These are **lab** Core Web Vitals. Field data (CrUX, including INP) needs real visitors on real sites. Chromium comes from `CHROME_PATH` if set, otherwise from the [`@sparticuz/chromium`](https://github.com/Sparticuz/chromium) package (any Linux x64 machine).

### Inside one request (profiling pass)

A separate pass, **before** the timing measurements, loads a tiny file in every PHP request through `auto_prepend_file` ([`lib/profile-prepend.php`](../lib/profile-prepend.php)). At shutdown it records the PHP files loaded (`get_included_files()`), the peak memory (`memory_get_peak_usage()`) and, for WordPress, the SQL queries (`$wpdb->num_queries`). Median of 7 requests, with the page cache emptied ("built") and warm ("from cache"). The profiler is then removed and PHP-FPM restarted, so it never influences the timings. A hit in the Nginx FastCGI cache never reaches PHP: 0 PHP file.

### Size on disk

`du` of the site folder without uploads and cache folders, and the number of `.php` files. For WordPress, the database server is not included.

### Separate load machine (optional)

`BENCH_LOAD_SSH=user@loadgen` runs every wrk test on another machine over SSH, against `BENCH_TARGET_HOST` (this server's address as seen from the load machine; ports 8081-8085 must be reachable). The load machine needs `wrk` and SSH key access. Response times (TTFB) and Lighthouse stay on the server.

## Known limits

- **Small machine, load generator on the same machine** in the published runs. It competes with the sites for CPU: absolute throughput is lower than on a dedicated set-up.
- **Default themes are different designs.** Page weight and Lighthouse compare default installations, not identical pages.
- **Lab metrics only** for Core Web Vitals.
- **Éclair is capped at 3,000 contents**, so very large sites (10,000+ articles) cannot be compared yet.

## Reproduce

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh                                   # Apache, 20 articles, with Lighthouse
sudo BENCH_SERVER=nginx ./run.sh                # Nginx, adds WordPress + FastCGI cache
sudo BENCH_ARTICLES=1000 BENCH_LIGHTHOUSE=0 ./run.sh
```

Use a fresh Ubuntu 24.04 server: the script installs and reconfigures the web server, PHP-FPM and MariaDB. Options are listed in the [README](../README.md#run-it-yourself).
