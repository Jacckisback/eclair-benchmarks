<p align="center"><b>English</b> · <a href="README.fr.md">Français</a> · <a href="README.es.md">Español</a></p>

# Éclair CMS benchmarks

Open, reproducible speed tests of [Éclair CMS](https://github.com/Jacckisback/eclair-cms) against WordPress, on the same server, with the same content. One script installs everything and measures everything. **Anyone can run it and check the numbers.**

> Éclair is made by the author of this repository. That is exactly why the method, the scripts and the raw data are public: do not trust us, run it yourself. Corrections and new CMSs are welcome (see [Contributing](#contributing)).

## Latest results

Run of 8 November 2026 · Éclair 2.0.0 · WordPress 7.1.4 · 2 vCPU cloud machine, Ubuntu 24.04, Apache 2.4 + PHP 8.3 FPM (OPcache on), MariaDB 10.11 · [full report and raw data](results/2026-11-08-cloud-2vcpu/)

**Article page**

| | Response time (median TTFB) | Page built from scratch | Throughput | Page weight (gzip) |
|---|---:|---:|---:|---:|
| **Éclair 2.0.0** | **0.83 ms** | **2.50 ms** | **2 183 req/s** | **8.3 KB** |
| WordPress 7.1.4 + Cache Enabler | 2.14 ms | 65.62 ms | 1 154 req/s | 18.3 KB |
| WordPress 7.1.4 (no plugin) | 58.07 ms | 59.41 ms | 34 req/s | 18.2 KB |

In short, on this machine:

- **Against WordPress with a page cache plugin** (how most WordPress sites run): Éclair answers about **2× faster**, serves about **2× more visitors per second**, and sends a page about **2× lighter**.
- **When the page is not in cache** (first visit after an edit, or a site without cache plugin): Éclair builds the page in under **3 ms**, WordPress in about **60 ms** or more: more than **20× faster**.
- **Installation**: Éclair is **2.5 MB** with no database. WordPress is **150 MB** plus a MariaDB/MySQL database.
- **Web fonts**: Éclair uses the system fonts (nothing to download). The default WordPress theme declares 2 font files (156 KB); browsers download the ones the text uses. They are not counted in the page weight above.

The home page gives similar results: see the [full report](results/2026-11-08-cloud-2vcpu/).

## Run it yourself

You need a **fresh Ubuntu 24.04 server** (a small cloud VPS is enough, 2 vCPU / 2 GB RAM). Do not use a production server: the script installs and reconfigures Apache, PHP-FPM and MariaDB.

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh
```

It takes about 10 minutes. The report is printed at the end and saved in `results/<date>-<server name>/`, with the raw JSON data.

Options (environment variables):

| Variable | Default | Meaning |
|---|---|---|
| `ECLAIR_VERSION` | latest release | Éclair version to test (archive from `dist/` of the Éclair repository) |
| `WP_VERSION` | latest release | WordPress version (tag of the official build repository `WordPress/WordPress`) |
| `BENCH_SAMPLES` | 300 | Sequential requests for the response time |
| `BENCH_WRK_SECONDS` | 20 | Duration of each load test |
| `BENCH_WRK_CONNECTIONS` | 20 | Simultaneous connections during the load test |

Example: `sudo WP_VERSION=7.0.2 BENCH_WRK_SECONDS=60 ./run.sh`

## Method

**Same server, same settings.** The three sites run side by side on one machine, on one Apache (mpm_event) with one PHP-FPM pool, OPcache on, Ubuntu default settings. Each site is measured alone, one after the other.

**The three sites**

1. **Éclair**: the official release archive, unchanged (its SHA-256 is printed during the run), installed through its own installer (blog type, English).
2. **WordPress, no plugin**: the official release, default theme (Twenty Twenty-Five), pretty permalinks.
3. **WordPress + Cache Enabler**: the same, plus [Cache Enabler](https://github.com/keycdn/cache-enabler), a popular free page cache plugin, with its default settings. This is the fair comparison: almost nobody runs WordPress without a cache.

**Same content.** The same 20 articles (title, 5 paragraphs, short description) are loaded into each site ([content/articles.json](content/articles.json)). WordPress sample posts and Éclair starter posts are removed. Éclair articles are saved through Éclair's own save function, WordPress articles through `wp_insert_post()`.

**What is measured**, on the home page (list of articles) and on one article:

- **Response time**: time to first byte (TTFB), median and 95th percentile of 300 sequential requests, after 50 warm-up requests.
- **Page built from scratch**: the page cache is emptied before each of 30 requests. This shows the speed of the CMS itself, not of its cache.
- **Throughput and latency under load**: [wrk](https://github.com/wg/wrk), 20 connections for 20 seconds.
- **Page weight**: the HTML plus every CSS, JavaScript and image file it links to, gzip-compressed as served. Web fonts are listed separately (a browser only downloads the fonts the text uses, which cannot be known without a real browser).
- **Size on disk** (code and content, without uploads and cache) and **number of PHP files**.

Every request sends the headers of a real Chrome browser. This matters: some page caches (Cache Enabler included) skip requests that do not ask for HTML.

## Limits

We prefer to say them ourselves:

- **One machine, small and shared.** The load generator (wrk) runs on the same 2 vCPU machine as the sites, so throughput numbers are lower than on a real server. Compare the sites with each other, not with other benchmarks.
- **Default settings.** PHP-FPM keeps the Ubuntu defaults (5 workers). Tuning would help WordPress without cache, and Éclair too.
- **Not the same design.** Each CMS uses its own default theme. The page weight compares two default installs, not two identical pages.
- **No browser metrics yet.** Lighthouse / Core Web Vitals need a real browser and are not measured yet. Contributions welcome.
- **Only WordPress for now.** Ghost, Grav, Kirby, Statamic, Drupal… are on the list.

## Contributing

- Found a mistake or an unfair setting? Open an issue: we will fix it and publish a new run, even if it is less good for Éclair.
- Want another CMS in the test? Open a pull request with its setup script in `lib/`, following the same rules (official release, default theme, same 20 articles, same server).
- Ran the benchmark on your server? Open a pull request with your `results/` folder.

## License

MIT for the scripts in this repository. Éclair CMS is AGPL-3.0, WordPress is GPL-2.0-or-later, Cache Enabler is GPL-2.0-or-later.
