# FAQ for WordPress professionals

Questions we expect from people who build and host WordPress sites for a living. If yours is missing, [open an issue](https://github.com/Jacckisback/eclair-benchmarks/issues).

### "Nobody runs WordPress without a cache. The 60× number is meaningless."

Agreed, which is why the main comparison is **WordPress + a page cache plugin**, and why the README leads with that number (about 2× in favour of Éclair on response time and throughput). The "no cache" line is there for two real situations: the **first visit after an edit**, when every cache must rebuild the page, and pages that **cannot be cached** (logged-in users, carts, search results, previews). In those cases the speed of the engine itself is what visitors get, and that is the "page built from scratch" chart.

### "Use LiteSpeed Cache / WP Rocket / Nginx FastCGI cache / Varnish and the gap disappears."

For pages served from a full-page cache **outside PHP** (Nginx FastCGI cache, Varnish, LiteSpeed server cache, a CDN), the response time no longer depends on the CMS: both would be served by the same layer at the same speed. That is true for Éclair too: put Varnish or a CDN in front of it and you get the same result.

This benchmark measures what a site gets **on a standard host, out of the box**: Apache + PHP-FPM, the setup of most shared hosting. There, Éclair serves cached pages from PHP with a single file and no database, without any configuration, plugin or server rule.

Runs with other set-ups are welcome: add a site in `lib/` and open a pull request.

### "Cache Enabler can serve pages through `.htaccess` rewrites, without PHP."

Yes, with its "advanced configuration" (Apache rewrite rules copied by hand). It is not the default and most users never set it up, so the default configuration is tested. A run with the rewrite rules would be a good addition; the same option would be fair to test for Éclair if it offered one.

### "Twenty Twenty-Five is a heavy block theme. Use a lighter theme."

It is the theme WordPress installs by default, so it is what a new WordPress site gets, the same way Éclair is tested with its own default theme. The page weight is reported as "default install vs default install" for that reason. Note that the response-time and throughput numbers depend mostly on the PHP work, not on the HTML size: with a page cache, both CMSs only read a file.

### "WordPress does far more than Éclair."

True: plugins for almost anything, a huge ecosystem, multisite, 20 years of maturity. This benchmark does not claim Éclair replaces every WordPress site. It measures what a **content site** (blog, showcase site, small business site) costs to serve on each, which is what most WordPress installations are.

### "Why should I trust a benchmark made by the author of Éclair?"

You should not trust it, you should **run it**. The scripts install official releases, the content is in the repository, every request and setting is in the code, and each run publishes its raw JSON. If you find a setting that favours Éclair, open an issue: it will be fixed and a new run published, even if the result is worse for Éclair.

### "Why is Éclair so fast on a cached page?"

Because its front controller checks the page cache **before loading anything else**. When a cached copy exists and is still valid, `index.php` sends it and stops: one PHP file, no class loaded, no configuration parsed, no database. WordPress with Cache Enabler also serves a file, but only after `wp-load.php`, `wp-config.php` and the start of `wp-settings.php` (about 27 files). See the [request path diagram](../README.md#whats-inside-one-request) and [ARCHITECTURE.md](ARCHITECTURE.md).

### "And when the page is not cached?"

Éclair loads about 30 PHP files and reads its content from flat files (no database). WordPress loads about 500 PHP files and runs about 30 SQL queries for an article page. That is the main reason why Éclair builds an uncached page 15 to 25 times faster.

### "Is the 2 vCPU machine a fair test?"

It is a typical small VPS. A bigger machine raises every number; the ratios between the sites are what this benchmark is about. The load generator runs on the same machine, which compresses the gap between fast sites rather than exaggerating it.

### "What about Core Web Vitals and Lighthouse?"

Not measured yet: they need a real browser in the loop. It is the next item on the roadmap. Éclair's own repository reports a Lighthouse score of 100 on its default theme; this benchmark will verify it independently, alongside WordPress.

### "What about big sites?"

The published runs use 20 articles. Tests with thousands of articles are planned, for both CMSs.
