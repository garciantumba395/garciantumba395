import { continueRender, delayRender, staticFile } from 'remotion';

// Montserrat (police du site) servie depuis public/fonts — pas de requête réseau au rendu.
const WEIGHTS = [500, 600, 700, 800, 900];
let started = false;
export const loadFonts = () => {
  if (started || typeof document === 'undefined') return;
  started = true;
  const handle = delayRender('Chargement Montserrat');
  Promise.all(
    WEIGHTS.map((w) => {
      const face = new FontFace('Montserrat', `url(${staticFile(`fonts/montserrat-latin-${w}-normal.woff2`)}) format('woff2')`, {
        weight: String(w),
        style: 'normal',
      });
      return face.load().then((f) => document.fonts.add(f));
    }),
  )
    .then(() => continueRender(handle))
    .catch((e) => {
      console.error(e);
      continueRender(handle);
    });
};
