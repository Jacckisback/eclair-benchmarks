# FAQ for WordPress professionals

Questions we expect from people who build and host WordPress sites for a living. If yours is missing, [open an issue](https://github.com/Jacckisback/eclair-benchmarks/issues). Figures below come from the runs of 8 November 2026 in [`results/`](../results/).

### "Nobody runs WordPress without a cache. The 60× number is meaningless."

Agreed, which is why the main comparison is **WordPress + a page cache**, and why the README leads with it. The "no cache" line is there for two real situations: the **first view after an edit**, when every cache must rebuild the page, and pages that **cannot be cached** (logged-in users, carts, search, previews). There, the speed of the engine itself is what visitors get: the "built from scratch" numbers.

### "Use an Nginx FastCGI cache and WordPress is as fast as Éclair."

We tested it ([Nginx run](../results/2026-11-08-nginx-20/)), and **you are right on cached pages**. With an Nginx FastCGI cache, Nginx answers without calling PHP at all:

| Article page, Nginx | Response time | Throughput (20 conn.) | Built from scratch |
|---|---:|---:|---:|
| WordPress + Nginx FastCGI cache | 0.57 ms | **4,136 req/s** | 59.20 ms |
| Éclair (its own cache, through PHP) | **0.56 ms** | 3,262 req/s | **3.00 ms** |
| WordPress + Cache Enabler | 1.20 ms | 1,762 req/s | 60.58 ms |

Same response time, and about 1.3× more throughput for the FastCGI cache, since it skips PHP. Three things to weigh against it:

- It needs **root access to Nginx** and a careful configuration (exclusions for carts, logged-in users, previews, purge on update). Most shared hosts do not offer it; Éclair's cache works out of the box on any host.
- **Every cache miss** costs about 60 ms on WordPress and about 3 ms on Éclair: 20× more CPU after each edit, for every uncached page and every visitor that cannot be served from cache.
- Nothing prevents putting the same Nginx cache, Varnish or a CDN in front of Éclair. Outside caches make every CMS equal on cached pages; what remains is the engine underneath.

### "What about WP Rocket and LiteSpeed Cache?"

Not tested yet. WP Rocket is a paid plugin without a public download, and LiteSpeed Cache needs a LiteSpeed web server. Both are welcome as contributions (a setup script in `lib/`). Like the Nginx FastCGI cache, LiteSpeed's server-level cache would answer without PHP on cached pages.

### "Cache Enabler can serve pages through `.htaccess` rewrites, without PHP."

Yes, with its "advanced configuration" (rewrite rules copied by hand). It is not the default and most users never set it up, so the default configuration is tested.

### "Twenty Twenty-Five is a heavy block theme. Use a lighter theme."

It is the theme WordPress installs by default, so it is what a new WordPress site gets, just as Éclair and Kirby are tested with their own defaults. Page weight compares default installations, not identical pages. Lighthouse shows the full picture as a browser sees it: on the article page a visitor downloads **10.8 KB with Éclair, 23.0 KB with Kirby and 97.8 KB with WordPress** (web fonts included). Response time and throughput depend mostly on the PHP work, not on the theme.

### "All three get 100 in Lighthouse. So who cares?"

Lighthouse caps the performance score at 100, and all three set-ups reach it on these small pages. The metrics underneath still differ: on the article page (Apache run), LCP is **0.95 s for Éclair**, 1.14 s for Kirby and 1.50 s for WordPress, under simulated slow 4G. On real pages, with images and plugins, these margins are what keeps a site in the "good" range. Éclair also gets **100 in SEO**, where the default WordPress theme gets 91-92 and the Kirby Starterkit 90.

### "How does it compare with another flat-file CMS?"

Kirby (Starterkit, with its pages cache on) is the closest architecture. With 20 articles, Kirby is close behind: Éclair answers about 1.6 to 2.9× faster from cache and serves about 1.8 to 2.6× more requests per second; built from scratch, the two are within 1.2 to 2× of each other. With 2,900 articles the picture is mixed. From cache, Kirby slows down a lot on article pages (18 ms, 128 req/s), most likely because its router reads the content folders before it can use the cache, while Éclair stays under 1 ms. Built from scratch, Kirby is faster than Éclair on the article page (20.8 ms against 31.0 ms) and much slower on the list page (125 ms against 20 ms). See the [site-size results](../results/large-sites/).

### "And with a big site?"

[Measured with 1,000 and 2,900 articles](../results/large-sites/). From cache, nothing changes for Éclair: one file, under 1.5 ms. **Built from scratch, Éclair slows down as the site grows** (about 3 ms with 20 articles, 7 to 10 ms with 1,000, 20 to 31 ms with 2,900), because every uncached request reads the whole content index. It is still 2 to 3.5× faster than WordPress at 2,900 articles, but WordPress, with its indexed database, stays flat at 60 to 75 ms. Éclair 2.x also caps a site at 3,000 contents. For large editorial sites, this is the main point to improve.

### "WordPress does far more than Éclair."

True: plugins for almost anything, a huge ecosystem, multisite, two decades of maturity. This benchmark does not claim Éclair replaces every WordPress site. It measures what a **content site** (blog, showcase site, small business site) costs to serve on each.

### "Why should I trust a benchmark made by the author of Éclair?"

You should not trust it, you should **run it**. The scripts install official releases, the content is in the repository, every setting is in the code, and each run publishes its raw JSON. This FAQ also reports where Éclair loses (Nginx FastCGI cache throughput, large sites). If you find a setting that favours Éclair, open an issue: it will be fixed and a new run published.

### "Why is Éclair so fast on a cached page?"

Its front controller checks the page cache **before loading anything else**: one PHP file, no class, no configuration, no database. WordPress with Cache Enabler also serves a file, but after `wp-load.php`, `wp-config.php` and the start of `wp-settings.php` (27 files). Kirby's pages cache is served by its router, after loading the Kirby core (131 files). Details: [ARCHITECTURE.md](ARCHITECTURE.md).

### "At 100 connections, Éclair's p99 latency was worse than WordPress + cache."

On Apache, yes: 195 ms against 120 ms in the latest run, while Éclair's throughput stays about 1.7× higher. On Nginx the tail is short (48 ms against 89 ms for WordPress + Cache Enabler), so the long tail looks tied to Apache's handling of the PHP-FPM queue on this 2 vCPU machine rather than to Éclair itself. To be confirmed on a bigger machine with a separate load generator.

### "Is a 2 vCPU machine a fair test?"

It is a typical small VPS. A bigger machine raises every number; the ratios between set-ups are what this benchmark is about. The load generator runs on the same machine in the published runs; the script can run it on a separate machine (`BENCH_LOAD_SSH`).
