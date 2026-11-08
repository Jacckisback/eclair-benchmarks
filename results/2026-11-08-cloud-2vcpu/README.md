# Results — 2026-11-08-cloud-2vcpu

Machine: Intel(R) Xeon(R) Processor @ 2.10GHz, 2 vCPU, 7.8 GB RAM, Ubuntu 24.04.5 LTS. Apache/2.4.58 (Ubuntu) (mpm_event) + PHP 8.3 FPM with OPcache, MariaDB 10.11.14-MariaDB. Load generator (wrk) runs on the same machine.
Response time = time to first byte, median and 95th percentile of 300 sequential requests after 50 warm-up requests. Page built from scratch = median of 30 requests with the page cache emptied before each one (WordPress without plugin has no page cache). Throughput = wrk, 20 s, 20 connections. Every request sends the headers of a real browser (Chrome). Page weight = HTML plus the CSS, JavaScript and images it links to, gzip as served. Web fonts are listed separately and not counted, because a browser only downloads the ones the text actually uses.

## Home page (list of articles)

| | Response time (median) | Response time (p95) | Page built from scratch (cache emptied) | Throughput | Latency under load (p99) | Page weight (HTML + CSS + JS + images, gzip) | Requests | Web fonts declared (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 1.04 ms | 1.48 ms | 2.83 ms | 2 332 req/s | 15.3 ms | 7.6 KB | 2 | none |
| WordPress 7.1.4 | 59.07 ms | 79.17 ms | 59.39 ms | 32 req/s | 829.0 ms | 14.4 KB | 2 | 2 (156.0 KB) |
| WordPress 7.1.4 + Cache Enabler | 1.77 ms | 2.26 ms | 79.81 ms | 1 084 req/s | 30.2 ms | 14.5 KB | 2 | 2 (156.0 KB) |

- Éclair vs WordPress: first byte 56.8× faster, throughput 72.9× higher, page weight 1.9× lighter
- Éclair vs WordPress + Cache Enabler: first byte 1.7× faster, throughput 2.2× higher, page weight 1.9× lighter

## Article page

| | Response time (median) | Response time (p95) | Page built from scratch (cache emptied) | Throughput | Latency under load (p99) | Page weight (HTML + CSS + JS + images, gzip) | Requests | Web fonts declared (not counted) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | 0.83 ms | 0.95 ms | 2.50 ms | 2 183 req/s | 16.5 ms | 8.3 KB | 2 | none |
| WordPress 7.1.4 | 58.07 ms | 75.56 ms | 59.41 ms | 34 req/s | 769.4 ms | 18.2 KB | 4 | 2 (156.0 KB) |
| WordPress 7.1.4 + Cache Enabler | 2.14 ms | 2.60 ms | 65.62 ms | 1 154 req/s | 29.1 ms | 18.3 KB | 4 | 2 (156.0 KB) |

- Éclair vs WordPress: first byte 70.0× faster, throughput 64.2× higher, page weight 2.2× lighter
- Éclair vs WordPress + Cache Enabler: first byte 2.6× faster, throughput 1.9× higher, page weight 2.2× lighter

## Installation

| | Size on disk (code + content) | PHP files | Needs a database |
|---|---:|---:|---|
| Éclair 2.0.0 | 2.5 MB | 155 | No |
| WordPress 7.1.4 | 150.2 MB | 1 901 | Yes (MariaDB) |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 1 910 | Yes (MariaDB) |

Raw data: `eclair.json`, `wordpress.json`, `wordpress-cache.json` in this folder.
