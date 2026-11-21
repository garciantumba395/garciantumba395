const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const b = await chromium.launch();
  for (const [scale, out] of [[1, 'hce-services-1080.png'], [4000 / 1080, 'hce-services-4000.png']]) {
    const c = await b.newContext({ viewport: { width: 1080, height: 1080 }, deviceScaleFactor: scale });
    const p = await c.newPage();
    await p.goto('file://' + path.resolve('post.html'));
    await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(600);
    await p.screenshot({ path: out });
    await c.close();
  }
  await b.close();
})();
