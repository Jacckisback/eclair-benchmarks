# Methodology

This page explains exactly how the numbers are produced, so that anyone can check them, criticise them, or reproduce them. Everything described here is in [`run.sh`](../run.sh) and [`lib/`](../lib).

## Principles

1. **Official releases, unchanged.** Éclair comes from its release archive (`dist/eclair-cms-<version>.zip`, SHA-256 printed and saved in `meta.json`). WordPress comes from the official build repository [`WordPress/WordPress`](https://github.com/WordPress/WordPress) at the release tag. Cache Enabler comes from its official repository [`keycdn/cache-enabler`](https://github.com/keycdn/cache-enabler).
2. **Default settings.** Each CMS keeps its default theme and default options. The only changes are the ones a normal site owner makes on day one: site name, language, pretty permalinks for WordPress.
3. **Same machine, same stack, same content.** The three sites live on one server, behind one Apache, one PHP-FPM pool, one OPcache configuration. They contain the same 20 articles.
4. **One site at a time.** Each site is measured alone; the others are idle.
5. **Real browser requests.** Every request sends the headers of a recent Chrome (`Accept: text/html…`, `User-Agent`, `Accept-Language`, `Accept-Encoding: gzip`).
6. **Raw data published.** Each run saves its JSON files next to the report. Charts and tables are generated from these files by script; no number is typed by hand.

## Server stack

| Layer | Setting |
|---|---|
| OS | Ubuntu 24.04 LTS, packages from the Ubuntu archive |
| Web server | Apache 2.4, `mpm_event`, `mod_rewrite`, `mod_headers`, `mod_expires`, `mod_deflate` (Ubuntu defaults), `AllowOverride All` so each CMS uses its own `.htaccess` |
| PHP | PHP 8.3 FPM through `proxy_fcgi`, OPcache on (`opcache.memory_consumption=128`), `memory_limit=256M`, Ubuntu pool defaults (`pm = dynamic`, `pm.max_children = 5`) |
| Database | MariaDB 10.11, Ubuntu defaults (used by WordPress only; Éclair has no database) |
| Compression | gzip by `mod_deflate` for every site. Brotli is not installed, so no site gets it. |

Why Apache + PHP-FPM? It is the most common setup on shared hosting and managed VPS, where most WordPress sites and most Éclair sites run. Nginx and LiteSpeed results are welcome as separate runs.

## The three sites

| Site | What it is | Page cache |
|---|---|---|
| **Éclair** | Release archive, installed through its own web installer (blog type, English) | Built in, on by default. Pages are saved as files; `index.php` serves them before loading the application. |
| **WordPress** | Official release, default theme (Twenty Twenty-Five), `/%postname%/` permalinks | None |
| **WordPress + Cache Enabler** | Same as above, plus Cache Enabler with default settings (`WP_CACHE` on, `advanced-cache.php` drop-in) | Pages saved as files, served by `advanced-cache.php` early in `wp-settings.php` |

**Why Cache Enabler?** It is free, popular, simple, and works on any host with default settings. It represents the typical "WordPress with a page cache" set-up honestly. Other set-ups (WP Rocket, LiteSpeed Cache on LiteSpeed, Nginx FastCGI cache, Varnish, a CDN) can be faster or slower and are discussed in the [FAQ](FAQ.md).

## Content

[`content/make-articles.php`](../content/make-articles.php) generates 20 articles (title, short description, five paragraphs, a date). The same file feeds every CMS:

- **Éclair**: through `Content::save()`, the same function the editor uses ([`lib/seed-eclair.php`](../lib/seed-eclair.php)).
- **WordPress**: through `wp_insert_post()`, as block-editor paragraphs ([`lib/setup-wordpress.php`](../lib/setup-wordpress.php)).

Sample content is removed (WordPress "Hello world", Éclair starter posts). Pages created by the installers (About, Contact, legal pages…) are kept. Both home pages list the 10 latest articles (Éclair's default of 9 is set to 10 to match WordPress).

## Measurements

All measurements run on the server itself, against `127.0.0.1`, so that the network does not hide the speed of the software.

### Response time (TTFB)

`curl` time to first byte (`CURLINFO_STARTTRANSFER_TIME`), measured on the home page and on one article.

- 50 warm-up requests (fills OPcache and page caches), then **300 sequential requests**.
- Reported: **median** and **95th percentile**.

### Page built from scratch

The same measurement, but the page cache is emptied **before each of 30 requests**. This isolates the speed of the CMS engine itself: reading content, running the theme, producing the HTML. For WordPress without plugin, it is the same as the normal response time, since nothing is cached.

### Throughput and latency under load

[wrk](https://github.com/wg/wrk) with the same browser headers:

- **Main test**: 20 connections, 2 threads, 20 seconds, on each page. Reported: requests per second, p50 and p99 latency, number of non-2xx answers (0 in every published run).
- **Scaling test**: article page at 1, 10, 50 and 100 connections, 10 seconds each.

### Page weight

What a browser downloads to display the page, **gzip-compressed as served**:

- the HTML;
- every stylesheet (`<link rel="stylesheet">` and `rel="preload"`), script (`<script src>`) and image (`<img src>`) it references.

**Web fonts are listed separately and not added.** A browser only downloads the fonts the text actually uses, and a script cannot know that without a real browser engine. Counting every declared font would be unfair to WordPress; ignoring them silently would be unfair to Éclair (which uses system fonts and declares none). So they are shown in their own column.

### Inside one request (profiling pass)

A separate pass, **before** the timing measurements, loads a tiny file in every PHP request through `auto_prepend_file` ([`lib/profile-prepend.php`](../lib/profile-prepend.php)). At shutdown it records:

- `count(get_included_files())`: PHP files loaded by the request (the profiler itself excluded);
- `memory_get_peak_usage()`: peak PHP memory;
- `$wpdb->num_queries`: SQL queries (WordPress).

Median of 7 requests, with the page cache emptied ("built") and warm ("from cache"). The profiler is then removed and PHP-FPM restarted, so it never influences the timings.

### Size on disk

`du` of the site folder without `uploads` and cache folders, and the number of `.php` files. For WordPress, the database server is not included in the size.

## Known limits

- **The load generator runs on the same machine.** It competes with the sites for CPU, so the absolute throughput is lower than on a dedicated set-up, and the gap between very fast sites is compressed rather than exaggerated.
- **Small machine.** The published run uses a 2 vCPU cloud machine. Results on your server will differ in absolute value; the ratios are what matter.
- **Default themes are different designs.** Page weight compares two default installations, not two identical pages.
- **No browser metrics yet.** Lighthouse and Core Web Vitals need a real browser. Planned.
- **20 articles.** Behaviour with 10,000 articles is not measured yet. Planned.

## Reproduce

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh
```

Use a fresh Ubuntu 24.04 server: the script installs and reconfigures Apache, PHP-FPM and MariaDB. Options are listed in the [README](../README.md#run-it-yourself).
