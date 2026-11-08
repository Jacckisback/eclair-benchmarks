# Results — 2026-11-08-nginx-20

| | |
|---|---|
| Date | 2026-11-08T14:29Z |
| Machine | Intel(R) Xeon(R) Processor @ 2.10GHz, 2 vCPU, 7 GB RAM, Ubuntu 24.04. The load generator (wrk) runs on the same machine. |
| Web server | Nginx + PHP 8.3 FPM with OPcache, MariaDB for WordPress |
| Content | 20 articles in every site |
| Versions | Éclair 2.0.0 (sha256 `4ba43998712a357d…`) · WordPress 7.1.4 · Cache Enabler 1.8.16 · Kirby 5.6.3 |

Response time = time to first byte, median and 95th percentile of 300 sequential requests after 50 warm-up requests. Built from scratch = median of 30 requests with the page cache emptied before each one. Throughput = wrk, 20 s, 20 connections. Every request sends the headers of a real Chrome browser. Page weight = HTML + linked CSS, JavaScript and images, gzip as served (web fonts listed apart). List page = the page listing the latest articles (home page for Éclair and WordPress, `/notes` for Kirby).

## List page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.51 ms | 0.66 ms | 2.48 ms | 3,540 req/s | 10.9 ms | 8.5 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.17 ms | 1.41 ms | 60.96 ms | 1,726 req/s | 18.4 ms | 16.5 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 + Nginx FastCGI cache | 0.63 ms | 0.81 ms | 64.56 ms | 4,151 req/s | 9.3 ms | 16.5 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 | 65.25 ms | 91.29 ms | 60.58 ms | 31 req/s | 811.5 ms | 16.5 KB | 2 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 1.44 ms | 1.82 ms | 4.68 ms | 1,385 req/s | 21.1 ms | 15.1 KB | 7 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 2.3× faster, built from scratch 24.6× faster, throughput 2.1× higher, page weight 2.0× lighter
- Éclair vs WordPress 7.1.4 + Nginx FastCGI cache: first byte 1.2× faster, built from scratch 26.0× faster, throughput 1.2× lower, page weight 1.9× lighter
- Éclair vs WordPress 7.1.4: first byte 127.9× faster, built from scratch 24.4× faster, throughput 114.2× higher, page weight 1.9× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 2.8× faster, built from scratch 1.9× faster, throughput 2.6× higher, page weight 1.8× lighter

## Article page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.56 ms | 0.79 ms | 3.00 ms | 3,262 req/s | 12.2 ms | 9.2 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.20 ms | 1.45 ms | 60.58 ms | 1,762 req/s | 18.0 ms | 20.7 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 + Nginx FastCGI cache | 0.57 ms | 0.69 ms | 59.20 ms | 4,136 req/s | 9.4 ms | 20.7 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 | 60.02 ms | 87.89 ms | 58.07 ms | 33 req/s | 737.9 ms | 20.7 KB | 4 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 1.63 ms | 2.34 ms | 3.46 ms | 1,257 req/s | 25.0 ms | 15.4 KB | 8 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 2.1× faster, built from scratch 20.2× faster, throughput 1.9× higher, page weight 2.2× lighter
- Éclair vs WordPress 7.1.4 + Nginx FastCGI cache: first byte about the same, built from scratch 19.7× faster, throughput 1.3× lower, page weight 2.2× lighter
- Éclair vs WordPress 7.1.4: first byte 107.2× faster, built from scratch 19.4× faster, throughput 98.8× higher, page weight 2.2× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 2.9× faster, built from scratch 1.2× faster, throughput 2.6× higher, page weight 1.7× lighter

## Scaling (article page)

Requests per second and p99 latency as the number of simultaneous connections grows (wrk, 10 s per level).

| | 1 conn. | 10 conn. | 50 conn. | 100 conn. |
|---|---:|---:|---:|---:|
| Éclair 2.0.0 | 1,968 req/s · p99 1.3 ms | 3,535 req/s · p99 5.8 ms | 3,094 req/s · p99 28.6 ms | 3,000 req/s · p99 47.8 ms |
| WordPress 7.1.4 + Cache Enabler | 891 req/s · p99 2.2 ms | 1,900 req/s · p99 12.0 ms | 1,613 req/s · p99 48.3 ms | 1,495 req/s · p99 88.7 ms |
| WordPress 7.1.4 + Nginx FastCGI cache | 1,859 req/s · p99 1.0 ms | 4,006 req/s · p99 4.9 ms | 4,033 req/s · p99 22.3 ms | 3,996 req/s · p99 38.8 ms |
| WordPress 7.1.4 | 16 req/s · p99 95.9 ms | 34 req/s · p99 389.7 ms | 33 req/s · p99 1,620.0 ms | 28 req/s · p99 1,990.0 ms |
| Kirby 5.6.3 (pages cache) | 600 req/s · p99 2.9 ms | 1,281 req/s · p99 13.7 ms | 1,284 req/s · p99 49.9 ms | 1,319 req/s · p99 93.8 ms |

## Lighthouse (real Chromium)

Lighthouse 12.8.2, Chrome/153.0.0.0, default settings: mobile emulation, simulated slow 4G and 4× CPU slowdown (the settings of PageSpeed Insights). Run reported = the median of 5 runs by performance score. These are lab measurements; field Core Web Vitals (including INP) need real visitors.

| | Page | Performance | LCP | FCP | TBT | CLS | Speed Index | Transfer | Requests | Accessibility | Best practices | SEO |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | list | 100 | 0.99 s | 0.66 s | 36 ms | 0 | 0.66 s | 10.8 KB | 3 | 100 | 100 | 100 |
| Éclair 2.0.0 | article | 100 | 0.84 s | 0.76 s | 0 ms | 0 | 0.76 s | 11.6 KB | 3 | 100 | 100 | 100 |
| WordPress 7.1.4 + Cache Enabler | list | 100 | 1.35 s | 1.05 s | 0 ms | 0 | 1.05 s | 97.0 KB | 8 | 100 | 100 | 91 |
| WordPress 7.1.4 + Cache Enabler | article | 100 | 1.66 s | 0.91 s | 0 ms | 0 | 0.91 s | 101.9 KB | 10 | 100 | 100 | 92 |
| WordPress 7.1.4 + Nginx FastCGI cache | list | 100 | 1.65 s | 1.05 s | 0 ms | 0 | 1.05 s | 97.1 KB | 8 | 100 | 100 | 91 |
| WordPress 7.1.4 + Nginx FastCGI cache | article | 100 | 1.65 s | 1.35 s | 19 ms | 0 | 1.35 s | 102.1 KB | 10 | 100 | 100 | 92 |
| WordPress 7.1.4 | list | 100 | 1.35 s | 1.05 s | 0 ms | 0 | 1.05 s | 97.0 KB | 8 | 100 | 100 | 91 |
| WordPress 7.1.4 | article | 100 | 1.65 s | 0.90 s | 0 ms | 0 | 0.90 s | 102.1 KB | 10 | 100 | 100 | 92 |
| Kirby 5.6.3 (pages cache) | list | 100 | 1.13 s | 1.13 s | 0 ms | 0 | 1.13 s | 22.6 KB | 8 | 94 | 100 | 90 |
| Kirby 5.6.3 (pages cache) | article | 100 | 1.13 s | 1.13 s | 0 ms | 0 | 1.13 s | 23.3 KB | 9 | 94 | 100 | 90 |

## Inside one request

Separate profiling pass (a PHP `auto_prepend_file` that logs at shutdown), removed before any timing measurement. Median of 7 requests. "Built" = page cache emptied first; "from cache" = page cache warm. WordPress without plugin has no page cache; a hit in the Nginx FastCGI cache never reaches PHP (0 PHP files).

| | Page | Built: PHP files | Built: peak memory | Built: SQL | From cache: PHP files | From cache: peak memory | From cache: SQL |
|---|---|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | list | 26 | 542 KB | 0 | 1 | 377 KB | 0 |
| Éclair 2.0.0 | article | 30 | 550 KB | 0 | 1 | 377 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | list | 501 | 6,238 KB | 26 | 27 | 613 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | article | 503 | 6,373 KB | 33 | 27 | 593 KB | 0 |
| WordPress 7.1.4 + Nginx FastCGI cache | list | 494 | 6,163 KB | 25 | 0 | 0 KB | 0 |
| WordPress 7.1.4 + Nginx FastCGI cache | article | 496 | 6,298 KB | 32 | 0 | 0 KB | 0 |
| WordPress 7.1.4 | list | 494 | 6,151 KB | 25 | n/a | n/a | n/a |
| WordPress 7.1.4 | article | 496 | 6,286 KB | 32 | n/a | n/a | n/a |
| Kirby 5.6.3 (pages cache) | list | 174 | 781 KB | 0 | 131 | 557 KB | 0 |
| Kirby 5.6.3 (pages cache) | article | 171 | 683 KB | 0 | 131 | 584 KB | 0 |

## Installation

| | Size on disk (code + content) | PHP files | Database server |
|---|---:|---:|---|
| Éclair 2.0.0 | 2.5 MB | 155 | none |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 1,910 | MariaDB / MySQL |
| WordPress 7.1.4 + Nginx FastCGI cache | 150.2 MB | 1,901 | MariaDB / MySQL |
| WordPress 7.1.4 | 150.2 MB | 1,901 | MariaDB / MySQL |
| Kirby 5.6.3 (pages cache) | 20.1 MB | 794 | none |

Raw data: the `.json` files in this folder. Charts: `charts/`.
