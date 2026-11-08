# Results — 2026-11-08-apache-20

| | |
|---|---|
| Date | 2026-11-08T14:16Z |
| Machine | Intel(R) Xeon(R) Processor @ 2.10GHz, 2 vCPU, 7 GB RAM, Ubuntu 24.04. The load generator (wrk) runs on the same machine. |
| Web server | Apache 2.4 (mpm_event, .htaccess on) + PHP 8.3 FPM with OPcache, MariaDB for WordPress |
| Content | 20 articles in every site |
| Versions | Éclair 2.0.0 (sha256 `4ba43998712a357d…`) · WordPress 7.1.4 · Cache Enabler 1.8.16 · Kirby 5.6.3 |

Response time = time to first byte, median and 95th percentile of 300 sequential requests after 50 warm-up requests. Built from scratch = median of 30 requests with the page cache emptied before each one. Throughput = wrk, 20 s, 20 connections. Every request sends the headers of a real Chrome browser. Page weight = HTML + linked CSS, JavaScript and images, gzip as served (web fonts listed apart). List page = the page listing the latest articles (home page for Éclair and WordPress, `/notes` for Kirby).

## List page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 1.15 ms | 1.78 ms | 2.52 ms | 2,214 req/s | 16.4 ms | 7.6 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.90 ms | 2.23 ms | 75.94 ms | 1,115 req/s | 28.9 ms | 14.4 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 | 74.18 ms | 96.12 ms | 75.80 ms | 27 req/s | 993.9 ms | 14.4 KB | 2 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 1.83 ms | 2.29 ms | 5.11 ms | 1,211 req/s | 23.3 ms | 14.2 KB | 7 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 1.7× faster, built from scratch 30.1× faster, throughput 2.0× higher, page weight 1.9× lighter
- Éclair vs WordPress 7.1.4: first byte 64.5× faster, built from scratch 30.1× faster, throughput 82.0× higher, page weight 1.9× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 1.6× faster, built from scratch 2.0× faster, throughput 1.8× higher, page weight 1.9× lighter

## Article page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 1.11 ms | 1.51 ms | 3.37 ms | 1,997 req/s | 17.2 ms | 8.3 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.80 ms | 2.17 ms | 75.72 ms | 1,060 req/s | 31.8 ms | 18.2 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 | 75.64 ms | 98.72 ms | 75.91 ms | 31 req/s | 917.9 ms | 18.2 KB | 4 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 1.89 ms | 3.05 ms | 3.89 ms | 1,106 req/s | 26.7 ms | 14.6 KB | 8 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 1.6× faster, built from scratch 22.5× faster, throughput 1.9× higher, page weight 2.2× lighter
- Éclair vs WordPress 7.1.4: first byte 68.1× faster, built from scratch 22.5× faster, throughput 64.4× higher, page weight 2.2× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 1.7× faster, built from scratch 1.2× faster, throughput 1.8× higher, page weight 1.8× lighter

## Scaling (article page)

Requests per second and p99 latency as the number of simultaneous connections grows (wrk, 10 s per level).

| | 1 conn. | 10 conn. | 50 conn. | 100 conn. |
|---|---:|---:|---:|---:|
| Éclair 2.0.0 | 1,081 req/s · p99 1.7 ms | 2,139 req/s · p99 10.8 ms | 2,036 req/s · p99 34.3 ms | 1,873 req/s · p99 194.5 ms |
| WordPress 7.1.4 + Cache Enabler | 527 req/s · p99 3.0 ms | 1,092 req/s · p99 18.1 ms | 1,119 req/s · p99 61.7 ms | 1,093 req/s · p99 119.7 ms |
| WordPress 7.1.4 | 15 req/s · p99 105.8 ms | 33 req/s · p99 400.0 ms | 32 req/s · p99 1,690.0 ms | 29 req/s · p99 2,000.0 ms |
| Kirby 5.6.3 (pages cache) | 512 req/s · p99 3.2 ms | 1,127 req/s · p99 14.7 ms | 1,122 req/s · p99 58.6 ms | 1,091 req/s · p99 150.8 ms |

## Lighthouse (real Chromium)

Lighthouse 12.8.2, Chrome/153.0.0.0, default settings: mobile emulation, simulated slow 4G and 4× CPU slowdown (the settings of PageSpeed Insights). Run reported = the median of 5 runs by performance score. These are lab measurements; field Core Web Vitals (including INP) need real visitors.

| | Page | Performance | LCP | FCP | TBT | CLS | Speed Index | Transfer | Requests | Accessibility | Best practices | SEO |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | list | 100 | 0.90 s | 0.75 s | 66 ms | 0 | 0.75 s | 10.1 KB | 3 | 100 | 100 | 100 |
| Éclair 2.0.0 | article | 100 | 0.95 s | 0.72 s | 0 ms | 0 | 0.72 s | 10.8 KB | 3 | 100 | 100 | 100 |
| WordPress 7.1.4 + Cache Enabler | list | 100 | 1.35 s | 1.05 s | 11 ms | 0 | 1.05 s | 93.2 KB | 8 | 100 | 100 | 91 |
| WordPress 7.1.4 + Cache Enabler | article | 100 | 1.50 s | 0.75 s | 18 ms | 0 | 0.75 s | 97.6 KB | 10 | 100 | 100 | 92 |
| WordPress 7.1.4 | list | 100 | 1.35 s | 0.64 s | 9 ms | 0 | 0.75 s | 93.1 KB | 8 | 100 | 100 | 91 |
| WordPress 7.1.4 | article | 100 | 1.50 s | 0.75 s | 50 ms | 0 | 0.75 s | 97.8 KB | 10 | 100 | 100 | 92 |
| Kirby 5.6.3 (pages cache) | list | 100 | 1.12 s | 1.12 s | 0 ms | 0 | 1.12 s | 22.3 KB | 8 | 94 | 100 | 90 |
| Kirby 5.6.3 (pages cache) | article | 100 | 1.14 s | 1.14 s | 0 ms | 0 | 1.14 s | 23.0 KB | 9 | 94 | 100 | 90 |

## Inside one request

Separate profiling pass (a PHP `auto_prepend_file` that logs at shutdown), removed before any timing measurement. Median of 7 requests. "Built" = page cache emptied first; "from cache" = page cache warm. WordPress without plugin has no page cache; a hit in the Nginx FastCGI cache never reaches PHP (0 PHP files).

| | Page | Built: PHP files | Built: peak memory | Built: SQL | From cache: PHP files | From cache: peak memory | From cache: SQL |
|---|---|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | list | 26 | 542 KB | 0 | 1 | 379 KB | 0 |
| Éclair 2.0.0 | article | 30 | 552 KB | 0 | 1 | 379 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | list | 501 | 6,239 KB | 26 | 27 | 614 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | article | 503 | 6,376 KB | 33 | 27 | 596 KB | 0 |
| WordPress 7.1.4 | list | 494 | 6,152 KB | 25 | n/a | n/a | n/a |
| WordPress 7.1.4 | article | 496 | 6,289 KB | 32 | n/a | n/a | n/a |
| Kirby 5.6.3 (pages cache) | list | 174 | 785 KB | 0 | 131 | 561 KB | 0 |
| Kirby 5.6.3 (pages cache) | article | 171 | 687 KB | 0 | 131 | 588 KB | 0 |

## Installation

| | Size on disk (code + content) | PHP files | Database server |
|---|---:|---:|---|
| Éclair 2.0.0 | 2.5 MB | 155 | none |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 1,910 | MariaDB / MySQL |
| WordPress 7.1.4 | 150.2 MB | 1,901 | MariaDB / MySQL |
| Kirby 5.6.3 (pages cache) | 20.1 MB | 794 | none |

Raw data: the `.json` files in this folder. Charts: `charts/`.
