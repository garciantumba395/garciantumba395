const { chromium } = require('playwright');
const pages = ['apple','samsung-galaxy','google-pixel','tecno-mobile'];
const want = [/iPhone 11( |$)/, /iPhone 12( |$)/, /iPhone 13( |$)/, /iPhone 15 Pro Max/, /Galaxy S22 /, /Galaxy S23 /, /Galaxy S24 /, /Pixel 8 /, /Pixel 9 /, /Pixel 9 Pro /, /POP 20 /, /Spark 50 /, /Camon 50 /, /Camon 50 Pro /];
(async () => {
  const b = await chromium.launch({ proxy: { server: process.env.HTTPS_PROXY } });
  const c = await b.newContext({ viewport: { width: 540, height: 960 }, ignoreHTTPSErrors: true });
  const out = [];
  for (const s of pages) {
    const p = await c.newPage();
    await p.goto('https://www.gnsbusinessrdc.store/marque/' + s, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await p.waitForTimeout(2500);
    const items = await p.evaluate(() => [...document.querySelectorAll('article')].map(a => {
      const l = a.querySelector('a[href*="/produit"]'); const u = l ? new URL(l.href, location.href) : null;
      return { text: a.innerText.replace(/\s+/g,' ').trim(), name: u?.searchParams.get('name'), model: u?.searchParams.get('model'), price: u?.searchParams.get('price'), image: u?.searchParams.get('image') };
    }));
    out.push(...items.map(i => ({ ...i, brand: s })));
    await p.close();
  }
  const sel = out.filter(i => i.name && want.some(r => r.test((i.name + ' '))) && !/Épuisé|confirmer/.test(i.text));
  const hosts = {}; sel.forEach(i => { try { const h = new URL(i.image).host; hosts[h] = (hosts[h]||0)+1 } catch { hosts['(none)'] = (hosts['(none)']||0)+1 } });
  console.log(JSON.stringify(hosts)); 
  sel.forEach(i => console.log(i.brand, '|', i.name, '|', i.model, '|', i.price, '|', (i.image||'').slice(0,110)));
  require('fs').writeFileSync('scripts/products.json', JSON.stringify(sel, null, 1));
  await b.close();
})();
