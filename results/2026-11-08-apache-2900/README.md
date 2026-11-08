# Results — 2026-11-08-apache-2900

| | |
|---|---|
| Date | 2026-11-08T14:09Z |
| Machine | Intel(R) Xeon(R) Processor @ 2.10GHz, 2 vCPU, 7 GB RAM, Ubuntu 24.04. The load generator (wrk) runs on the same machine. |
| Web server | Apache 2.4 (mpm_event, .htaccess on) + PHP 8.3 FPM with OPcache, MariaDB for WordPress |
| Content | 2,900 articles in every site |
| Versions | Éclair 2.0.0 (sha256 `4ba43998712a357d…`) · WordPress 7.1.4 · Cache Enabler 1.8.16 · Kirby 5.6.3 |

Response time = time to first byte, median and 95th percentile of 300 sequential requests after 50 warm-up requests. Built from scratch = median of 30 requests with the page cache emptied before each one. Throughput = wrk, 20 s, 20 connections. Every request sends the headers of a real Chrome browser. Page weight = HTML + linked CSS, JavaScript and images, gzip as served (web fonts listed apart). List page = the page listing the latest articles (home page for Éclair and WordPress, `/notes` for Kirby).

## List page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.91 ms | 1.40 ms | 19.96 ms | 2,228 req/s | 14.9 ms | 7.6 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.75 ms | 2.28 ms | 70.83 ms | 1,135 req/s | 28.6 ms | 14.5 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 | 67.55 ms | 99.02 ms | 64.75 ms | 31 req/s | 811.6 ms | 14.4 KB | 2 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 2.12 ms | 2.50 ms | 125.39 ms | 1,176 req/s | 25.6 ms | 14.2 KB | 7 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 1.9× faster, built from scratch 3.5× faster, throughput 2.0× higher, page weight 1.9× lighter
- Éclair vs WordPress 7.1.4: first byte 74.2× faster, built from scratch 3.2× faster, throughput 71.9× higher, page weight 1.9× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 2.3× faster, built from scratch 6.3× faster, throughput 1.9× higher, page weight 1.9× lighter

## Article page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.93 ms | 1.26 ms | 31.03 ms | 2,063 req/s | 16.5 ms | 8.3 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.72 ms | 2.33 ms | 71.94 ms | 1,130 req/s | 29.1 ms | 18.3 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 | 64.29 ms | 88.75 ms | 60.56 ms | 32 req/s | 861.3 ms | 18.2 KB | 4 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 18.18 ms | 24.79 ms | 20.76 ms | 128 req/s | 208.1 ms | 14.6 KB | 8 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 1.8× faster, built from scratch 2.3× faster, throughput 1.8× higher, page weight 2.2× lighter
- Éclair vs WordPress 7.1.4: first byte 69.1× faster, built from scratch 2.0× faster, throughput 64.5× higher, page weight 2.2× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 19.5× faster, built from scratch 1.5× slower, throughput 16.1× higher, page weight 1.7× lighter

## Scaling (article page)

Requests per second and p99 latency as the number of simultaneous connections grows (wrk, 10 s per level).

| | 1 conn. | 10 conn. | 50 conn. | 100 conn. |
|---|---:|---:|---:|---:|
| Éclair 2.0.0 | 1,012 req/s · p99 1.9 ms | 2,120 req/s · p99 11.4 ms | 2,077 req/s · p99 33.4 ms | 2,044 req/s · p99 202.6 ms |
| WordPress 7.1.4 + Cache Enabler | 570 req/s · p99 2.8 ms | 1,169 req/s · p99 18.7 ms | 1,115 req/s · p99 61.3 ms | 1,119 req/s · p99 488.1 ms |
| WordPress 7.1.4 | 16 req/s · p99 88.5 ms | 32 req/s · p99 411.4 ms | 31 req/s · p99 1,770.0 ms | 27 req/s · p99 1,970.0 ms |
| Kirby 5.6.3 (pages cache) | 56 req/s · p99 26.8 ms | 126 req/s · p99 108.6 ms | 127 req/s · p99 447.9 ms | 118 req/s · p99 1,590.0 ms |

## Inside one request

Separate profiling pass (a PHP `auto_prepend_file` that logs at shutdown), removed before any timing measurement. Median of 7 requests. "Built" = page cache emptied first; "from cache" = page cache warm. WordPress without plugin has no page cache; a hit in the Nginx FastCGI cache never reaches PHP (0 PHP files).

| | Page | Built: PHP files | Built: peak memory | Built: SQL | From cache: PHP files | From cache: peak memory | From cache: SQL |
|---|---|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | list | 26 | 12,252 KB | 0 | 1 | 379 KB | 0 |
| Éclair 2.0.0 | article | 30 | 12,252 KB | 0 | 1 | 379 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | list | 501 | 6,245 KB | 26 | 27 | 614 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | article | 503 | 6,377 KB | 33 | 27 | 596 KB | 0 |
| WordPress 7.1.4 | list | 494 | 6,159 KB | 25 | n/a | n/a | n/a |
| WordPress 7.1.4 | article | 496 | 6,290 KB | 32 | n/a | n/a | n/a |
| Kirby 5.6.3 (pages cache) | list | 174 | 15,474 KB | 0 | 131 | 561 KB | 0 |
| Kirby 5.6.3 (pages cache) | article | 171 | 5,520 KB | 0 | 131 | 5,136 KB | 0 |

## Installation

| | Size on disk (code + content) | PHP files | Database server |
|---|---:|---:|---|
| Éclair 2.0.0 | 15.7 MB | 3,035 | none |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 1,910 | MariaDB / MySQL |
| WordPress 7.1.4 | 150.2 MB | 1,901 | MariaDB / MySQL |
| Kirby 5.6.3 (pages cache) | 42.8 MB | 794 | none |

Raw data: the `.json` files in this folder. Charts: `charts/`.
