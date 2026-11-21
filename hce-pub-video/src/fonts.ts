import { useEffect, useState } from 'react';
import { continueRender, delayRender, staticFile } from 'remotion';

// Montserrat (police du site) servie depuis public/fonts — aucune requête externe au rendu.
// Robuste au rendu multi-onglets : si la police est déjà dans le document, on ne recharge rien ;
// sortie de secours après 8 s pour ne jamais bloquer le rendu (la police système de repli resterait propre).
const WEIGHTS = [500, 600, 700, 800, 900];

export const useFonts = () => {
  const [handle] = useState(() => delayRender('Chargement Montserrat', { timeoutInMilliseconds: 120000 }));
  useEffect(() => {
    let done = false;
    const finish = () => { if (!done) { done = true; continueRender(handle); } };
    const safety = setTimeout(finish, 8000);
    const doc = document as any;
    if (WEIGHTS.every((w) => doc.fonts.check(`${w} 20px Montserrat`) && Array.from(doc.fonts).some((f: any) => f.family.replace(/"/g, '') === 'Montserrat' && String(f.weight) === String(w) && f.status === 'loaded'))) {
      finish();
    } else {
      Promise.all(
        WEIGHTS.map((w) => {
          const face = new FontFace('Montserrat', `url(${staticFile(`fonts/montserrat-latin-${w}-normal.woff2`)}) format('woff2')`, { weight: String(w), style: 'normal' });
          return face.load().then((f) => { doc.fonts.add(f); });
        }),
      ).then(finish).catch((e) => { console.error(e); finish(); });
    }
    return () => clearTimeout(safety);
  }, []);
};
