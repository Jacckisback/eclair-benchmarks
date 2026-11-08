# Site size: 20, 1,000 and 2,900 articles

The same benchmark run with more content. Runs: [20 articles](../2026-11-08-apache-20/), [1000 articles](../2026-11-08-apache-1000/), [2900 articles](../2026-11-08-apache-2900/).

Éclair 2.x accepts at most 3,000 contents (articles + pages), so the largest run uses 2,900 articles.

## List page (10 latest articles)

| | 20 articles: from cache / built | 1,000 articles: from cache / built | 2,900 articles: from cache / built |
|---|---:|---:|---:|
| Éclair 2.0.0 | 1.15 ms / 2.52 ms | 0.83 ms / 6.63 ms | 0.91 ms / 19.96 ms |
| WordPress 7.1.4 + Cache Enabler | 1.90 ms / 75.94 ms | 1.97 ms / 70.86 ms | 1.75 ms / 70.83 ms |
| WordPress 7.1.4 | 74.18 ms / 75.80 ms | 68.67 ms / 68.00 ms | 67.55 ms / 64.75 ms |
| Kirby 5.6.3 (pages cache) | 1.83 ms / 5.11 ms | 1.92 ms / 45.36 ms | 2.12 ms / 125.39 ms |

## Article page

| | 20 articles: from cache / built | 1,000 articles: from cache / built | 2,900 articles: from cache / built |
|---|---:|---:|---:|
| Éclair 2.0.0 | 1.11 ms / 3.37 ms | 1.30 ms / 9.61 ms | 0.93 ms / 31.03 ms |
| WordPress 7.1.4 + Cache Enabler | 1.80 ms / 75.72 ms | 1.72 ms / 67.14 ms | 1.72 ms / 71.94 ms |
| WordPress 7.1.4 | 75.64 ms / 75.91 ms | 67.04 ms / 74.50 ms | 64.29 ms / 60.56 ms |
| Kirby 5.6.3 (pages cache) | 1.89 ms / 3.89 ms | 8.01 ms / 12.22 ms | 18.18 ms / 20.76 ms |

## Throughput, article page (req/s, 20 connections)

| | 20 | 1,000 | 2,900 |
|---|---:|---:|---:|
| Éclair 2.0.0 | 1,997 | 2,184 | 2,063 |
| WordPress 7.1.4 + Cache Enabler | 1,060 | 1,144 | 1,130 |
| WordPress 7.1.4 | 31 | 29 | 32 |
| Kirby 5.6.3 (pages cache) | 1,106 | 302 | 128 |

## Size on disk (code + content)

| | 20 | 1,000 | 2,900 |
|---|---:|---:|---:|
| Éclair 2.0.0 | 2.5 MB | 7.0 MB | 15.7 MB |
| WordPress 7.1.4 + Cache Enabler | 150.2 MB | 150.2 MB | 150.2 MB |
| WordPress 7.1.4 | 150.2 MB | 150.2 MB | 150.2 MB |
| Kirby 5.6.3 (pages cache) | 20.1 MB | 27.8 MB | 42.8 MB |

WordPress content lives in MariaDB and is not counted in its size on disk.
