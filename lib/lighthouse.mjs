// Lighthouse in a real Chromium: lab Core Web Vitals and scores for each site.
// Usage: node lib/lighthouse.mjs <out.json> <key>|<base-url>|<home-path>|<article-path> ...
// Default Lighthouse settings (mobile, simulated slow 4G and CPU), the same as PageSpeed Insights.
// Chrome: CHROME_PATH if set, otherwise the Chromium shipped by @sparticuz/chromium (works on any Linux x64).
import fs from 'node:fs';
import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';

const [out, ...specs] = process.argv.slice(2);
const runs = Number(process.env.BENCH_LIGHTHOUSE_RUNS || 5);

let chromePath = process.env.CHROME_PATH;
const chromeFlags = ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-zygote'];
if (!chromePath) {
  const chromium = (await import('@sparticuz/chromium')).default;
  chromePath = await chromium.executablePath();
}

const median = (values) => {
  const v = [...values].sort((a, b) => a - b);
  const m = Math.floor(v.length / 2);
  return v.length % 2 ? v[m] : (v[m - 1] + v[m]) / 2;
};

async function audit(url) {
  const chrome = await chromeLauncher.launch({
    chromePath,
    chromeFlags,
  });
  try {
    const result = await lighthouse(url, {
      port: chrome.port, output: 'json', logLevel: 'error',
      onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
    });
    const lhr = result.lhr;
    const a = lhr.audits;
    const metrics = ['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index', 'total-byte-weight'];
    if (lhr.runtimeError || lhr.categories.performance.score === null || metrics.some((m) => typeof a[m]?.numericValue !== 'number')) {
      throw new Error(`incomplete Lighthouse run on ${url}: ${lhr.runtimeError?.code || 'missing metric'}`);
    }
    return {
      performance: Math.round(lhr.categories.performance.score * 100),
      accessibility: Math.round(lhr.categories.accessibility.score * 100),
      best_practices: Math.round(lhr.categories['best-practices'].score * 100),
      seo: Math.round(lhr.categories.seo.score * 100),
      fcp_ms: Math.round(a['first-contentful-paint'].numericValue),
      lcp_ms: Math.round(a['largest-contentful-paint'].numericValue),
      tbt_ms: Math.round(a['total-blocking-time'].numericValue),
      cls: Number(a['cumulative-layout-shift'].numericValue.toFixed(3)),
      speed_index_ms: Math.round(a['speed-index'].numericValue),
      total_bytes: Math.round(a['total-byte-weight'].numericValue),
      requests: (a['network-requests'].details?.items || []).length,
      lighthouse: lhr.lighthouseVersion,
      chrome: lhr.environment.hostUserAgent.match(/Chrome\/[\d.]+/)?.[0] || '',
    };
  } finally {
    await chrome.kill();
  }
}

const result = {};
for (const spec of specs) {
  const [key, base, home, article] = spec.split('|');
  result[key] = {};
  for (const [page, path] of [['home', home], ['article', article]]) {
    const samples = [];
    // A run can fail on a busy machine (Chrome timeout): it is retried, up to 8 failures per page.
    let failures = 0;
    while (samples.length < runs) {
      try {
        samples.push(await audit(base + path));
      } catch (e) {
        if (++failures > 8) throw e;
        await new Promise((r) => setTimeout(r, 2000));
        console.log(`  ${key} ${page}: ${e.message}, retrying`);
      }
    }
    // The run with the median performance score is reported whole (its metrics belong together).
    const sorted = [...samples].sort((x, y) => x.performance - y.performance || y.lcp_ms - x.lcp_ms);
    const pick = sorted[Math.floor(sorted.length / 2)];
    result[key][page] = { ...pick, runs, failed_runs: failures, performance_all: samples.map((s) => s.performance), lcp_median_ms: median(samples.map((s) => s.lcp_ms)) };
    console.log(`  ${key.padEnd(18)} ${page.padEnd(8)} performance ${pick.performance} · LCP ${pick.lcp_ms} ms · TBT ${pick.tbt_ms} ms · CLS ${pick.cls} · SEO ${pick.seo} · accessibility ${pick.accessibility}`);
  }
}
fs.writeFileSync(out, JSON.stringify(result, null, 2) + '\n');
