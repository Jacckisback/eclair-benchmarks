# Results — 2026-11-08-apache-1000

| | |
|---|---|
| Date | 2026-11-08T14:01Z |
| Machine | Intel(R) Xeon(R) Processor @ 2.10GHz, 2 vCPU, 7 GB RAM, Ubuntu 24.04. The load generator (wrk) runs on the same machine. |
| Web server | Apache 2.4 (mpm_event, .htaccess on) + PHP 8.3 FPM with OPcache, MariaDB for WordPress |
| Content | 1,000 articles in every site |
| Versions | Éclair 2.0.0 (sha256 `4ba43998712a357d…`) · WordPress 7.1.4 · Cache Enabler 1.8.16 · Kirby 5.6.3 |

Response time = time to first byte, median and 95th percentile of 300 sequential requests after 50 warm-up requests. Built from scratch = median of 30 requests with the page cache emptied before each one. Throughput = wrk, 20 s, 20 connections. Every request sends the headers of a real Chrome browser. Page weight = HTML + linked CSS, JavaScript and images, gzip as served (web fonts listed apart). List page = the page listing the latest articles (home page for Éclair and WordPress, `/notes` for Kirby).

## List page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.83 ms | 0.98 ms | 6.63 ms | 2,191 req/s | 16.5 ms | 7.6 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.97 ms | 2.42 ms | 70.86 ms | 1,106 req/s | 30.1 ms | 14.5 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 | 68.67 ms | 86.68 ms | 68.00 ms | 30 req/s | 846.3 ms | 14.4 KB | 2 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 1.92 ms | 2.30 ms | 45.36 ms | 1,103 req/s | 25.9 ms | 14.2 KB | 7 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 2.4× faster, built from scratch 10.7× faster, throughput 2.0× higher, page weight 1.9× lighter
- Éclair vs WordPress 7.1.4: first byte 82.7× faster, built from scratch 10.3× faster, throughput 73.0× higher, page weight 1.9× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 2.3× faster, built from scratch 6.8× faster, throughput 2.0× higher, page weight 1.9× lighter

## Article page

| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 1.30 ms | 1.53 ms | 9.61 ms | 2,184 req/s | 16.7 ms | 8.3 KB | 2 | none |
| WordPress 7.1.4 + Cache Enabler | 1.72 ms | 2.12 ms | 67.14 ms | 1,144 req/s | 28.9 ms | 18.3 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 | 67.04 ms | 87.27 ms | 74.50 ms | 29 req/s | 944.8 ms | 18.2 KB | 4 | 2 (156.0 KB) |
| Kirby 5.6.3 (pages cache) | 8.01 ms | 11.54 ms | 12.22 ms | 302 req/s | 93.8 ms | 14.6 KB | 8 | none |

- Éclair vs WordPress 7.1.4 + Cache Enabler: first byte 1.3× faster, built from scratch 7.0× faster, throughput 1.9× higher, page weight 2.2× lighter
- Éclair vs WordPress 7.1.4: first byte 51.6× faster, built from scratch 7.8× faster, throughput 75.3× higher, page weight 2.2× lighter
- Éclair vs Kirby 5.6.3 (pages cache): first byte 6.2× faster, built from scratch 1.3× faster, throughput 7.2× higher, page weight 1.7× lighter

## Scaling (article page)

Requests per second and p99 latency as the number of simultaneous connections grows (wrk, 10 s per level).

| | 1 conn. | 10 conn. | 50 conn. | 100 conn. |
|---|---:|---:|---:|---:|
| Éclair 2.0.0 | 1,229 req/s · p99 1.5 ms | 2,216 req/s · p99 11.2 ms | 2,066 req/s · p99 34.5 ms | 1,896 req/s · p99 69.2 ms |
| WordPress 7.1.4 + Cache Enabler | 558 req/s · p99 3.0 ms | 1,157 req/s · p99 18.6 ms | 1,070 req/s · p99 65.6 ms | 1,108 req/s · p99 176.7 ms |
| WordPress 7.1.4 | 14 req/s · p99 100.2 ms | 31 req/s · p99 441.7 ms | 30 req/s · p99 1,900.0 ms | 28 req/s · p99 1,950.0 ms |
| Kirby 5.6.3 (pages cache) | 126 req/s · p99 11.8 ms | 271 req/s · p99 58.2 ms | 315 req/s · p99 192.0 ms | 290 req/s · p99 432.8 ms |

## Inside one request

Separate profiling pass (a PHP `auto_prepend_file` that logs at shutdown), removed before any timing measurement. Median of 7 requests. "Built" = page cache emptied first; "from cache" = page cache warm. WordPress without plugin has no page cache; a hit in the Nginx FastCGI cache never reaches PHP (0 PHP files).

| | Page | Built: PHP files | Built: peak memory | Built: SQL | From cache: PHP files | From cache: peak memory | From cache: SQL |
|---|---|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | list | 26 | 4,465 KB | 0 | 1 | 379 KB | 0 |
| Éclair 2.0.0 | article | 30 | 4,465 KB | 0 | 1 | 379 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | list | 501 | 6,245 KB | 26 | 27 | 614 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | article | 503 | 6,377 KB | 33 | 27 | 596 KB | 0 |
| WordPress 7.1.4 | list | 494 | 6,159 KB | 25 | n/a | n/a | n/a |
| WordPress 7.1.4 | article | 496 | 6,290 KB | 32 | n/a | n/a | n/a |
| Kirby 5.6.3 (pages cache) | list | 174 | 5,574 KB | 0 | 131 | 561 KB | 0 |
| Kirby 5.6.3 (pages cache) | article | 171 | 2,257 KB | 0 | 131 | 2,113 KB | 0 |

## Installation

| | Size on disk (code + content) | PHP files | Database server |
|---|---:|---:|---|
| Éclair 2.0.0 | 7.0 MB | 1,135 | none |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 1,910 | MariaDB / MySQL |
| WordPress 7.1.4 | 150.2 MB | 1,901 | MariaDB / MySQL |
| Kirby 5.6.3 (pages cache) | 27.8 MB | 794 | none |

Raw data: the `.json` files in this folder. Charts: `charts/`.
