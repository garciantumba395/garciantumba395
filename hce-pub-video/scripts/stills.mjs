import { bundle } from '@remotion/bundler';
import { renderStill, selectComposition } from '@remotion/renderer';
import path from 'node:path';
const [tag, ...frames] = process.argv.slice(2);
const entry = path.resolve('src/index.ts');
const serveUrl = await bundle({ entryPoint: entry, webpackOverride: (c) => c });
const opts = { browserExecutable: '/opt/pw-browsers/chromium', chromiumOptions: { gl: 'swangle' }, logLevel: 'error' };
const comp = await selectComposition({ serveUrl, id: process.env.COMP || 'HceAd', ...opts });
for (const fr of frames) {
  await renderStill({ composition: comp, serveUrl, frame: Number(fr), output: `out/qa/${tag}-f${fr}.png`, ...opts });
}
console.log('ok', frames.length);
