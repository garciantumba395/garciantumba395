const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ proxy: { server: process.env.HTTPS_PROXY } });
  const c = await b.newContext({ viewport: { width: 540, height: 960 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true });
  const p = await c.newPage();
  await p.goto('https://www.gnsbusinessrdc.store/marque/apple', { waitUntil: 'networkidle', timeout: 60000 });
  await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(1500);
  await p.screenshot({ path: 'out/qa/probe-apple-top.png' });
  const info = await p.evaluate(() => {
    const cards = [...document.querySelectorAll('a[href*="/produit"], a[href*="/product"], article, [class*="card"]')].slice(0, 5)
      .map(e => ({ tag: e.tagName, cls: String(e.className).slice(0, 80), href: e.getAttribute('href'), h: e.getBoundingClientRect().height, txt: e.innerText.slice(0, 60) }));
    return { h: document.documentElement.scrollHeight, imgs: document.images.length, cards };
  });
  console.log(JSON.stringify(info, null, 1));
  await b.close();
})();
