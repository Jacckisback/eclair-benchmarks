<p align="center"><b>English</b> · <a href="README.fr.md">Français</a> · <a href="README.es.md">Español</a></p>

<p align="center">
  <img src="results/2026-11-08-apache-20/charts/summary.svg" alt="Éclair CMS vs WordPress: 1.6× faster first byte than WordPress with a page cache plugin, 22.5× faster page build, 60.1× smaller install" width="100%">
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/scripts-MIT-22c55e?style=flat-square" alt="MIT"></a>
  <img src="https://img.shields.io/badge/Éclair-2.0.0-6250d6?style=flat-square" alt="Éclair 2.0.0">
  <img src="https://img.shields.io/badge/WordPress-7.1.4-21759b?style=flat-square" alt="WordPress 7.1.4">
  <img src="https://img.shields.io/badge/Kirby-5.6.3-eda100?style=flat-square" alt="Kirby 5.6.3">
  <img src="https://img.shields.io/badge/Apache_|_Nginx-PHP_8.3_FPM-777bb4?style=flat-square" alt="Apache or Nginx, PHP 8.3 FPM">
  <img src="https://img.shields.io/badge/Lighthouse-real_Chromium-f44b21?style=flat-square" alt="Lighthouse in a real Chromium">
  <img src="https://img.shields.io/badge/reproducible-one_command-0b0b0b?style=flat-square" alt="Reproducible with one command">
</p>

# Éclair CMS benchmarks

**Open, reproducible performance tests of [Éclair CMS](https://github.com/Jacckisback/eclair-cms) against WordPress (with and without page cache) and Kirby.** Same server, same PHP, same content, official releases, default settings. One script installs every site, measures it from the server and in a real browser, and draws every chart on this page from the raw data.

> **Full disclosure.** Éclair is developed by the author of this repository. That is exactly why everything is public: the method, the scripts, the content, the raw JSON of every run, and the results where Éclair loses (they are in this README too). Do not trust these numbers, [run them yourself](#run-it-yourself). If you find a setting that favours Éclair, [open an issue](https://github.com/Jacckisback/eclair-benchmarks/issues): it will be fixed and a new run published, even if the result is worse for Éclair.

## Contents

- [Results at a glance](#results-at-a-glance)
- [1. Response time](#1-response-time) · [2. Building a page from scratch](#2-building-a-page-from-scratch) · [3. Throughput and scaling](#3-throughput-and-scaling)
- [4. In a real browser: Lighthouse](#4-in-a-real-browser-lighthouse)
- [What's inside one request](#whats-inside-one-request)
- [5. Page weight and installation size](#5-page-weight-and-installation-size)
- [6. Nginx, and WordPress behind an Nginx FastCGI cache](#6-nginx-and-wordpress-behind-an-nginx-fastcgi-cache)
- [7. Large sites: 1,000 and 2,900 articles](#7-large-sites-1000-and-2900-articles)
- [What it means in practice](#what-it-means-in-practice) · [How it is measured](#how-it-is-measured) · [Limits](#limits) · [Run it yourself](#run-it-yourself) · [Roadmap](#roadmap)
- Deep dives: [FAQ for WordPress professionals](docs/FAQ.md) · [Methodology](docs/METHODOLOGY.md) · [Architecture](docs/ARCHITECTURE.md)

## Results at a glance

Runs of **8 November 2026** · Éclair 2.0.0 · WordPress 7.1.4 (default theme Twenty Twenty-Five) · Cache Enabler 1.8.16 · Kirby 5.6.3 (Starterkit, pages cache on) · 2 vCPU cloud machine, Ubuntu 24.04, PHP 8.3 FPM with OPcache, MariaDB 10.11

| Run | Web server | Articles | Report |
|---|---|---:|---|
| Main run | Apache 2.4 | 20 | [results/2026-11-08-apache-20](results/2026-11-08-apache-20/) |
| Nginx run (adds WordPress + Nginx FastCGI cache) | Nginx 1.24 | 20 | [results/2026-11-08-nginx-20](results/2026-11-08-nginx-20/) |
| Large sites | Apache 2.4 | 1,000 · 2,900 | [results/large-sites](results/large-sites/) |

**Article page, Apache, 20 articles**

| | Response time<br><sub>median TTFB</sub> | Built from scratch<br><sub>cache emptied</sub> | Throughput<br><sub>20 connections</sub> | PHP files<br><sub>per page view</sub> | Lighthouse LCP<br><sub>mobile, slow 4G</sub> | Browser download<br><sub>Lighthouse</sub> |
|---|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | **1.11 ms** | **3.37 ms** | **1,997 req/s** | **1** | **0.95 s** | **10.8 KB** |
| WordPress 7.1.4 + Cache Enabler | 1.80 ms | 75.72 ms | 1,060 req/s | 27 | 1.50 s | 97.6 KB |
| WordPress 7.1.4, no plugin | 75.64 ms | 75.91 ms | 31 req/s | 496 (+32 SQL) | 1.50 s | 97.8 KB |
| Kirby 5.6.3, pages cache | 1.89 ms | 3.89 ms | 1,106 req/s | 131 | 1.14 s | 23.0 KB |

**In short:**

- Against **WordPress with a page cache plugin**, Éclair answers **1.6× faster**, serves **1.9× more visitors** on the same server, and its pages make the browser download **9× less**.
- When no cache can help (first view after an edit, uncacheable pages), Éclair builds a page **22 to 30× faster** than WordPress.
- Against **Kirby**, the closest flat-file CMS, Éclair is **1.6 to 1.7× faster** from cache and serves **1.8× more requests** per second.
- Where Éclair does **not** win: behind an **Nginx FastCGI cache**, WordPress serves **1.3× more requests per second** (Nginx answers without PHP), and on **large sites** Éclair's uncached page build slows down (31 ms at 2,900 articles). Both are detailed below.

## 1. Response time

<img src="results/2026-11-08-apache-20/charts/ttfb.svg" alt="Time to first byte, article page: Éclair 1.11 ms, WordPress + Cache Enabler 1.80 ms, WordPress 75.64 ms, Kirby 1.89 ms" width="100%">

Time to first byte, median of 300 requests after 50 warm-up requests, measured on the server (network excluded). It is what a visitor waits for before the browser can start drawing the page.

| | List page: median · p95 | Article: median · p95 |
|---|---:|---:|
| **Éclair** | **1.15 ms · 1.78 ms** | **1.11 ms · 1.51 ms** |
| WordPress + Cache Enabler | 1.90 ms · 2.23 ms | 1.80 ms · 2.17 ms |
| WordPress | 74.18 ms · 96.12 ms | 75.64 ms · 98.72 ms |
| Kirby (pages cache) | 1.83 ms · 2.29 ms | 1.89 ms · 3.05 ms |

## 2. Building a page from scratch

<img src="results/2026-11-08-apache-20/charts/build.svg" alt="Page built from scratch, article page: Éclair 3.37 ms, WordPress + Cache Enabler 75.72 ms, WordPress 75.91 ms, Kirby 3.89 ms" width="100%">

The page cache is emptied before each of 30 requests: this is the speed of the CMS engine, and what visitors get on **the first view after every edit** and on pages that **cannot be cached** (previews, search, logged-in users). A cache plugin cannot help WordPress here: Éclair is **22.5× faster on the article page and 30× faster on the list page**. Kirby, which also reads flat files, is close to Éclair at this size.

## 3. Throughput and scaling

<img src="results/2026-11-08-apache-20/charts/throughput.svg" alt="Throughput, article page: Éclair 1,997 req/s, WordPress + Cache Enabler 1,060 req/s, WordPress 31 req/s, Kirby 1,106 req/s" width="100%">

<img src="results/2026-11-08-apache-20/charts/scaling.svg" alt="Throughput as connections grow from 1 to 100" width="100%">

| Article page, Apache | 1 connection | 10 | 50 | 100 |
|---|---:|---:|---:|---:|
| **Éclair** | 1,081 req/s · p99 1.7 ms | 2,139 · p99 10.8 ms | 2,036 · p99 34.3 ms | 1,873 · p99 194.5 ms |
| WordPress + Cache Enabler | 527 · p99 3.0 ms | 1,092 · p99 18.1 ms | 1,119 · p99 61.7 ms | 1,093 · p99 119.7 ms |
| WordPress | 15 · p99 105.8 ms | 33 · p99 400 ms | 32 · p99 1,690 ms | 29 · p99 2,000 ms |
| Kirby (pages cache) | 512 · p99 3.2 ms | 1,127 · p99 14.7 ms | 1,122 · p99 58.6 ms | 1,091 · p99 150.8 ms |

Throughput flattens from 10 connections: on this 2 vCPU machine the CPU, shared with the load generator, is the limit. Éclair keeps about **1.7 to 2× the throughput** of WordPress + cache and of Kirby at every level.

**Reported as is:** at 100 connections on Apache, Éclair's p99 latency (195 ms) is higher than WordPress + cache (120 ms). On Nginx it is the opposite (Éclair 48 ms, WordPress + Cache Enabler 89 ms, see [section 6](#6-nginx-and-wordpress-behind-an-nginx-fastcgi-cache)), so this tail looks tied to Apache's queue in front of PHP-FPM on a small machine. To be confirmed with a separate load machine.

## 4. In a real browser: Lighthouse

<img src="results/2026-11-08-apache-20/charts/lighthouse.svg" alt="Lighthouse LCP, article page: Éclair 0.95 s, WordPress + Cache Enabler 1.50 s, WordPress 1.50 s, Kirby 1.14 s; all scores 100" width="100%">

<img src="results/2026-11-08-apache-20/charts/lighthouse-bytes.svg" alt="Bytes downloaded by the browser, article page: Éclair 10.8 KB in 3 requests, WordPress 97.8 KB in 10 requests, Kirby 23.0 KB in 9 requests" width="100%">

Lighthouse 12.8 in a real Chromium, with the settings of PageSpeed Insights (mobile, simulated slow 4G, 4× CPU slowdown), median of 5 runs.

| Article page | Performance | LCP | FCP | TBT | CLS | Transfer | Requests | Accessibility | Best practices | SEO |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair** | **100** | **0.95 s** | **0.72 s** | 0 ms | 0 | **10.8 KB** | **3** | **100** | **100** | **100** |
| WordPress + Cache Enabler | 100 | 1.50 s | 0.75 s | 18 ms | 0 | 97.6 KB | 10 | 100 | 100 | 92 |
| WordPress | 100 | 1.50 s | 0.75 s | 50 ms | 0 | 97.8 KB | 10 | 100 | 100 | 92 |
| Kirby (pages cache) | 100 | 1.14 s | 1.14 s | 0 ms | 0 | 23.0 KB | 9 | 94 | 100 | 90 |

All set-ups reach the maximum performance score on these small pages; the metrics underneath show the difference. Éclair's LCP is **0.55 s shorter** than WordPress's, the browser downloads **9× less**, and Éclair is the only one with **100 in SEO** (WordPress 92, Kirby 90). These are lab measurements; field Core Web Vitals (including INP) need real visitors.

## What's inside one request

<img src="results/2026-11-08-apache-20/charts/request-path.svg" alt="Request path: Éclair cached 1 PHP file; Éclair built 30 files; WordPress + Cache Enabler cached 27 files; WordPress built 496 files and 32 SQL queries" width="100%">

<img src="results/2026-11-08-apache-20/charts/php-files.svg" alt="PHP files loaded to serve one page: Éclair 1, WordPress + Cache Enabler 27, WordPress 496, Kirby 131" width="100%">

<img src="results/2026-11-08-apache-20/charts/memory.svg" alt="PHP memory per page: Éclair 0.37 MB, WordPress + Cache Enabler 0.58 MB, WordPress 6.14 MB, Kirby 0.57 MB" width="100%">

Measured in a separate profiling pass (an `auto_prepend_file` that logs `get_included_files()`, `memory_get_peak_usage()` and `$wpdb->num_queries` at shutdown), removed before any timing measurement.

- **Éclair answers with a single PHP file.** Its `index.php` checks the page cache *before the application exists*: it reads a few bytes of runtime settings, opens `cache/pages/<md5(path)>`, checks the cache generation, sends the stored headers with an `ETag` (answers `304 Not Modified` when it can), streams the HTML and exits. Invalidation is atomic: every edit publishes a new cache generation *before* deleting old pages, so a stale page is never served.
- **WordPress + Cache Enabler needs 27 files**: `advanced-cache.php` is loaded by `wp-settings.php`, after `wp-load.php` and `wp-config.php`.
- **Kirby's pages cache needs 131 files**: it is served by Kirby's router, after loading the Kirby core.
- **WordPress without cache needs ~500 files and ~30 SQL queries**: core, autoloaded options, the main query, the template hierarchy and the block theme.

Deep dive with code references: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## 5. Page weight and installation size

<img src="results/2026-11-08-apache-20/charts/weight.svg" alt="Page weight, article page: Éclair 8.3 KB in 2 files, WordPress 18.2 KB in 4 files plus 2 fonts, Kirby 14.6 KB in 8 files" width="100%">

<img src="results/2026-11-08-apache-20/charts/disk.svg" alt="Installation size: Éclair 2.5 MB, WordPress 150.2 MB, Kirby 20.1 MB" width="100%">

- **Page weight** (server-side count): the HTML plus every stylesheet, script and image it references, gzip as served. Web fonts are listed apart, because a browser only downloads the ones the text uses; Lighthouse's count above includes them.
- **Installation**: Éclair 2.5 MB, 155 PHP files, no database. Kirby 20.1 MB, 794 PHP files, no database. WordPress 150 MB, about 1,900 PHP files, plus a MySQL/MariaDB server to run, back up and secure.

## 6. Nginx, and WordPress behind an Nginx FastCGI cache

The same benchmark on **Nginx**, each CMS with its official Nginx rules, plus the set-up WordPress professionals usually put forward: **WordPress behind an Nginx FastCGI cache**, where Nginx answers cached pages without calling PHP. [Full report](results/2026-11-08-nginx-20/).

<img src="results/2026-11-08-nginx-20/charts/throughput.svg" alt="Throughput on Nginx, article page: Éclair 3,262 req/s, WordPress + Cache Enabler 1,762, WordPress + Nginx FastCGI cache 4,136, WordPress 33, Kirby 1,257" width="100%">

| Article page, Nginx | Response time | Built from scratch | Throughput | p99 at 100 connections | Browser download |
|---|---:|---:|---:|---:|---:|
| **Éclair** | **0.56 ms** | **3.00 ms** | 3,262 req/s | 47.8 ms | **11.6 KB** |
| WordPress + Nginx FastCGI cache | 0.57 ms | 59.20 ms | **4,136 req/s** | **38.8 ms** | 102.1 KB |
| WordPress + Cache Enabler | 1.20 ms | 60.58 ms | 1,762 req/s | 88.7 ms | 101.9 KB |
| WordPress | 60.02 ms | 58.07 ms | 33 req/s | 1,990 ms | 102.1 KB |
| Kirby (pages cache) | 1.63 ms | 3.46 ms | 1,257 req/s | 93.8 ms | 23.3 KB |

**What it shows.** Behind an Nginx FastCGI cache, WordPress matches Éclair's response time and serves **1.3× more requests per second**, because a cache hit never reaches PHP. That cache needs root access to Nginx and careful exclusions (carts, logged-in users, previews, purge on update), which most shared hosts do not offer, and **every miss still costs about 60 ms on WordPress against 3 ms on Éclair**. The same Nginx cache, Varnish or a CDN can be put in front of Éclair too: outside caches make every CMS equal on cached pages, and what remains is the engine underneath. Everywhere else, Nginx keeps the Apache picture: Éclair answers **2.1× faster** than WordPress + Cache Enabler and **2.9× faster** than Kirby, with **1.9× and 2.6× their throughput**.

## 7. Large sites: 1,000 and 2,900 articles

The same benchmark with more content (Apache). [Full comparison](results/large-sites/).

<img src="results/large-sites/charts/size-build-article.svg" alt="Article page built from scratch as the site grows: Éclair 3.4 ms, 9.6 ms, 31.0 ms at 20, 1,000, 2,900 articles; WordPress about 60 to 76 ms at every size" width="100%">

| Built from scratch | 20 articles | 1,000 | 2,900 |
|---|---:|---:|---:|
| **Éclair**, list page · article | 2.52 · 3.37 ms | 6.63 · 9.61 ms | 19.96 · 31.03 ms |
| WordPress + Cache Enabler | 75.94 · 75.72 ms | 70.86 · 67.14 ms | 70.83 · 71.94 ms |
| WordPress | 75.80 · 75.91 ms | 68.00 · 74.50 ms | 64.75 · 60.56 ms |
| Kirby (pages cache) | 5.11 · 3.89 ms | 45.36 · 12.22 ms | 125.39 · 20.76 ms |

| From cache, article page | 20 articles | 1,000 | 2,900 |
|---|---:|---:|---:|
| **Éclair** | 1.11 ms · 1,997 req/s | 1.30 ms · 2,184 req/s | 0.93 ms · 2,063 req/s |
| WordPress + Cache Enabler | 1.80 ms · 1,060 req/s | 1.72 ms · 1,144 req/s | 1.72 ms · 1,130 req/s |
| Kirby (pages cache) | 1.89 ms · 1,106 req/s | 8.01 ms · 302 req/s | 18.18 ms · 128 req/s |

**What it shows.**

- **From cache, size changes nothing for Éclair**: one file, under 1.5 ms, about 2,000 req/s at every size. Kirby's cached article pages slow down sharply (128 req/s at 2,900 articles).
- **Built from scratch, Éclair slows down as the site grows** (3 → 10 → 31 ms on the article page), because every uncached request reads the whole content index (about 12 MB of memory at 2,900 articles). It is still **2 to 3.5× faster than WordPress** at 2,900 articles, but WordPress, with its indexed database, stays flat. At that size Kirby builds an article page faster than Éclair (20.8 against 31.0 ms), and its list page much slower (125 against 20 ms).
- **Éclair 2.x caps a site at 3,000 contents** (articles + pages). 10,000-article sites cannot be compared yet.

## What it means in practice

| For… | What these numbers change |
|---|---|
| **Site owners** | Pages that start displaying sooner on every visit, including right after you publish, with no cache plugin to choose, configure or debug. A browser downloads about 9× less than with the default WordPress theme. |
| **Agencies and freelancers** | More sites per server, no database to maintain per client, updates that cannot break a plugin stack. 100 in Lighthouse SEO and accessibility from the default install. |
| **Hosts** | About 2× the visitors on the same CPU than WordPress + a cache plugin, 20 to 30× on uncached pages, and about 10× less PHP memory per uncached view on small sites. |
| **SEO** | Server response time is the first part of every page load, and a light page helps Core Web Vitals. Éclair starts with both. |

What this benchmark does **not** say: that Éclair can replace every WordPress site. WordPress has a huge plugin ecosystem, multisite, two decades of maturity, and stays flat on very large sites. Éclair is built for content sites (blogs, small business sites, showcase sites) up to a few thousand pages.

## How it is measured

| | |
|---|---|
| **Sites** | Éclair (official release archive, SHA-256 saved) · WordPress (official build repository, release tag, default theme) · WordPress + [Cache Enabler](https://github.com/keycdn/cache-enabler) (default settings) · WordPress + Nginx FastCGI cache (Nginx runs) · Kirby [Starterkit](https://github.com/getkirby/starterkit) (pages cache on) |
| **Content** | The same articles in every site ([content/](content/)); sample posts removed; 10 articles per list page everywhere |
| **Stack** | One web server (Apache 2.4 with `.htaccess`, or Nginx with each CMS's official rules), one PHP 8.3 FPM pool with OPcache, gzip for every site, Ubuntu defaults |
| **Requests** | Real Chrome headers on every request |
| **Response time** | `curl` TTFB, 50 warm-up + 300 sequential requests, median and p95 |
| **Built from scratch** | Page cache emptied before each of 30 requests |
| **Load** | [wrk](https://github.com/wg/wrk), 20 connections × 20 s; scaling at 1, 10, 50, 100 connections × 10 s |
| **Browser** | Lighthouse 12.8 in Chromium, PageSpeed Insights settings, median of 5 runs |
| **Inside one request** | Separate profiling pass with `auto_prepend_file`, removed before timings |
| **Order** | One site at a time, the others idle |

Every detail, and why each choice was made: [docs/METHODOLOGY.md](docs/METHODOLOGY.md).

## Limits

- **Small shared machine.** 2 vCPU, with the load generator on the same machine. Absolute numbers will be higher on a real server; compare the ratios. A separate load machine is supported (`BENCH_LOAD_SSH`) but has not been used for the published runs yet.
- **Default settings everywhere.** PHP-FPM keeps Ubuntu's 5 workers.
- **Different default designs.** Page weight and Lighthouse compare default installations, not identical pages.
- **Lab metrics** for Core Web Vitals; field data needs real visitors.
- **Not tested yet:** WP Rocket (paid plugin), LiteSpeed and LiteSpeed Cache (LiteSpeed server), Grav, Drupal, Statamic (Composer) and Ghost (Node.js). See the [roadmap](#roadmap).

## Run it yourself

You need a **fresh Ubuntu 24.04 server** (a small VPS is enough: 2 vCPU, 2 GB RAM). Do not use a production server: the script installs and reconfigures the web server, PHP-FPM and MariaDB.

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh                                        # Apache, 20 articles, Lighthouse: about 30 minutes
sudo BENCH_SERVER=nginx ./run.sh                     # Nginx, adds WordPress + Nginx FastCGI cache
sudo BENCH_ARTICLES=1000 BENCH_LIGHTHOUSE=0 ./run.sh # a larger site
```

The report is printed at the end and saved, with the raw JSON and the SVG charts, in `results/<date>-<server name>-<web server>-<articles>/`. To compare runs of different sizes: `php lib/sizes.php results/my-sizes results/<run-20> results/<run-1000> …`

| Variable | Default | Meaning |
|---|---|---|
| `BENCH_SERVER` | `apache` | `apache` or `nginx` |
| `BENCH_ARTICLES` | 20 | Number of articles in every site (Éclair accepts up to 2,990) |
| `BENCH_SITES` | all | Sites to test, among `eclair wordpress-cache wordpress-fastcgi wordpress kirby` (`wordpress-fastcgi` needs Nginx) |
| `BENCH_LIGHTHOUSE` | 1 | `0` to skip the browser measurements |
| `BENCH_LIGHTHOUSE_RUNS` | 5 | Lighthouse runs per page |
| `CHROME_PATH` | bundled Chromium | Use your own Chrome or Chromium for Lighthouse |
| `BENCH_LOAD_SSH` | (none) | `user@host` of a separate load machine (needs `wrk` and SSH key access); wrk then runs there |
| `BENCH_TARGET_HOST` | hostname | This server's address as seen from the load machine (ports 8081-8085) |
| `ECLAIR_VERSION`, `WP_VERSION` | latest releases | Versions to test |
| `BENCH_SAMPLES` | 300 | Sequential requests for the response time |
| `BENCH_WRK_SECONDS`, `BENCH_WRK_CONNECTIONS` | 20, 20 | Main load test |
| `BENCH_SWEEP`, `BENCH_SWEEP_SECONDS` | `1,10,50,100`, 10 | Scaling test |
| `BENCH_RUN_NAME` | automatic | Name of the results folder |

<details>
<summary><b>What the script does, step by step</b></summary>

1. Installs the web server, PHP 8.3 FPM, MariaDB, wrk (and Node.js for Lighthouse) from the Ubuntu archive.
2. Downloads Éclair (release archive, SHA-256 checked), WordPress (release tag), Cache Enabler and the Kirby Starterkit.
3. Configures one web server with one site per port (8081-8085) and one PHP-FPM pool with OPcache.
4. Installs every site (Éclair through its own web installer) and loads the same articles; checks every page answers 200.
5. Profiles one request inside PHP (files, memory, SQL), then removes the profiler.
6. Measures response time, build time, throughput, scaling, page weight and size, one site at a time.
7. Runs Lighthouse in Chromium on every site.
8. Writes `README.md`, the JSON files and the SVG charts of the run.

</details>

## Repository layout

```
run.sh                    the whole benchmark, one command
content/                  the shared articles and the script that generates them
lib/setup-*.php           installation of each CMS (Éclair, WordPress, Kirby)
lib/seed-eclair.php       loads the articles through Éclair's own code
lib/profile*.php          the profiling pass (PHP files, memory, SQL per request)
lib/measure.php           response time, build time, load, scaling, page weight, size
lib/lighthouse.mjs        Lighthouse in a real Chromium
lib/report.php            Markdown report of a run
lib/charts.php, svg.php   SVG charts of a run (light and dark mode)
lib/sizes.php             comparison of runs with different numbers of articles
results/<run>/            report, raw JSON and charts of each published run
docs/                     methodology, architecture, FAQ
```

## Roadmap

- [x] Lighthouse in a real browser (lab Core Web Vitals, SEO, accessibility)
- [x] Large sites: 1,000 and 2,900 articles (Éclair's current maximum is 3,000 contents)
- [x] Nginx + PHP-FPM
- [x] WordPress + Nginx FastCGI cache
- [x] Kirby
- [x] Support for a separate load machine (`BENCH_LOAD_SSH`)
- [ ] Published runs on a dedicated server with a separate load machine
- [ ] 10,000-article sites (needs Éclair to lift its 3,000-content limit)
- [ ] LiteSpeed + LiteSpeed Cache, WP Rocket
- [ ] Ghost, Grav, Drupal, Statamic
- [ ] Field Core Web Vitals on real sites

## Contributing

- **Found an unfair setting or a mistake?** [Open an issue](https://github.com/Jacckisback/eclair-benchmarks/issues). It will be fixed and a new run published.
- **Want another CMS or set-up?** Add its setup script in `lib/` and a case in `run.sh`, following the same rules (official release, default theme, same articles, same server), and open a pull request.
- **Ran it on your server?** Open a pull request with your `results/` folder: results from different machines make the comparison stronger.

## License

MIT for the scripts in this repository. Éclair CMS is AGPL-3.0; WordPress and Cache Enabler are GPL-2.0-or-later; Kirby is a commercial product (free to try locally).
