# Results — 2026-11-08-cloud-2vcpu

Machine: Intel(R) Xeon(R) Processor @ 2.10GHz, 2 vCPU, 7.8 GB RAM, Ubuntu 24.04.5 LTS. Apache/2.4.58 (Ubuntu) (mpm_event) + PHP 8.3 FPM with OPcache, MariaDB 10.11.14-MariaDB. Load generator (wrk) runs on the same machine.
Response time = time to first byte, median and 95th percentile of 300 sequential requests after 50 warm-up requests. Page built from scratch = median of 30 requests with the page cache emptied before each one (WordPress without plugin has no page cache). Throughput = wrk, 20 s, 20 connections. Every request sends the headers of a real browser (Chrome). Page weight = HTML plus the CSS, JavaScript and images it links to, gzip as served. Web fonts are listed separately and not counted, because a browser only downloads the ones the text actually uses.

## Home page (list of articles)

| | Response time (median) | Response time (p95) | Page built from scratch (cache emptied) | Throughput | Latency under load (p99) | Page weight (HTML + CSS + JS + images, gzip) | Requests | Web fonts declared (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 1.02 ms | 1.25 ms | 2.58 ms | 2 273 req/s | 15.9 ms | 7.6 KB | 2 | none |
| WordPress 7.1.4 | 61.73 ms | 82.14 ms | 60.84 ms | 33 req/s | 704.9 ms | 14.4 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 + Cache Enabler | 1.83 ms | 2.21 ms | 61.57 ms | 1 220 req/s | 25.6 ms | 14.5 KB | 2 | 2 (156.0 KB) |

- Éclair vs WordPress: first byte 60.5× faster, throughput 68.9× higher, page weight 1.9× lighter
- Éclair vs WordPress + Cache Enabler: first byte 1.8× faster, throughput 1.9× higher, page weight 1.9× lighter

## Article page

| | Response time (median) | Response time (p95) | Page built from scratch (cache emptied) | Throughput | Latency under load (p99) | Page weight (HTML + CSS + JS + images, gzip) | Requests | Web fonts declared (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.81 ms | 0.95 ms | 3.91 ms | 2 128 req/s | 17.0 ms | 8.3 KB | 2 | none |
| WordPress 7.1.4 | 57.83 ms | 79.47 ms | 59.27 ms | 36 req/s | 674.8 ms | 18.2 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 + Cache Enabler | 1.84 ms | 2.69 ms | 63.42 ms | 1 192 req/s | 26.3 ms | 18.3 KB | 4 | 2 (156.0 KB) |

- Éclair vs WordPress: first byte 71.4× faster, throughput 59.1× higher, page weight 2.2× lighter
- Éclair vs WordPress + Cache Enabler: first byte 2.3× faster, throughput 1.8× higher, page weight 2.2× lighter

## Scaling (article page)

Requests per second and p99 latency as the number of simultaneous connections grows (wrk).

| | 1 conn. | 10 conn. | 50 conn. | 100 conn. |
|---|---:|---:|---:|---:|
| Éclair 2.0.0 | 1 068 req/s · p99 1.6 ms | 2 167 req/s · p99 12.2 ms | 2 095 req/s · p99 34.0 ms | 2 084 req/s · p99 284.7 ms |
| WordPress 7.1.4 | 17 req/s · p99 79.9 ms | 35 req/s · p99 367.8 ms | 35 req/s · p99 1 550.0 ms | 30 req/s · p99 1 990.0 ms |
| WordPress 7.1.4 + Cache Enabler | 607 req/s · p99 2.7 ms | 1 233 req/s · p99 15.9 ms | 1 171 req/s · p99 57.4 ms | 1 149 req/s · p99 107.9 ms |

## Inside one request

Measured in a separate profiling pass (a PHP `auto_prepend_file` that logs at shutdown), removed before any timing measurement. Median of 7 requests. "Built" = page cache emptied first; "from cache" = page cache warm. WordPress without plugin has no page cache.

| | Page | Built: PHP files | Built: peak memory | Built: SQL queries | From cache: PHP files | From cache: peak memory | From cache: SQL queries |
|---|---|---:|---:|---:|---:|---:|---:|
| Éclair 2.0.0 | home | 26 | 542 KB | 0 | 1 | 379 KB | 0 |
| Éclair 2.0.0 | article | 30 | 552 KB | 0 | 1 | 379 KB | 0 |
| WordPress 7.1.4 | home | 494 | 6 152 KB | 25 | n/a | n/a | n/a |
| WordPress 7.1.4 | article | 496 | 6 289 KB | 32 | n/a | n/a | n/a |
| WordPress 7.1.4 + Cache Enabler | home | 501 | 6 239 KB | 26 | 27 | 614 KB | 0 |
| WordPress 7.1.4 + Cache Enabler | article | 503 | 6 376 KB | 33 | 27 | 596 KB | 0 |

## Installation

| | Size on disk (code + content) | PHP files | Needs a database |
|---|---:|---:|---|
| Éclair 2.0.0 | 2.5 MB | 155 | No |
| WordPress 7.1.4 | 150.2 MB | 1 901 | Yes (MariaDB) |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 1 910 | Yes (MariaDB) |

Raw data: `eclair.json`, `wordpress.json`, `wordpress-cache.json` in this folder.
