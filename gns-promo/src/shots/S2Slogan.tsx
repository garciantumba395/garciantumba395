import React, { useLayoutEffect, useRef, useState } from 'react';
import { continueRender, delayRender, useCurrentFrame } from 'remotion';
import { EASE, bezier, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 2 — lead-word-zoom-assemble (adapté portrait) : « 4 » occupe l'écran à 2,3×, puis UNE courbe
// ramène la taille et glisse la ligne en place ; les mots suivants sont poussés dans leur emplacement.
// Courbes de la fiche : PUSH (.25,1,.5,1), ZOOM (.5,0,.05,1) ; recul 12f, glissement 24f, mot 12f/décalage 4f/poussée 0.5em.
const PUSH = bezier(0.25, 1, 0.5, 1), ZOOM = bezier(0.5, 0, 0.05, 1), WORD = bezier(0.22, 0.8, 0.36, 1);
const FS = 84;
const WORDS = ['4', 'grandes', 'marques.'];

export const S2Slogan: React.FC = () => {
  const f = useCurrentFrame();
  const rowRef = useRef<HTMLDivElement>(null);
  const leadRef = useRef<HTMLSpanElement>(null);
  const ruler = useRef<HTMLSpanElement>(null);
  const [m, setM] = useState<{ w: number; lead: number; base: number } | null>(null);
  const [handle] = useState(() => delayRender('mesure ligne'));
  useLayoutEffect(() => {
    let done = false;
    const finish = () => { if (!done) { done = true; continueRender(handle); } };
    setTimeout(finish, 8000);
    (document as any).fonts.load(`800 ${FS}px Montserrat`, 'grandes 4').then(() => (document as any).fonts.ready).then(() => {
      const row = rowRef.current!, lead = leadRef.current!, rl = ruler.current!;
      setM({ w: row.offsetWidth, lead: (lead.offsetLeft + lead.offsetWidth / 2) / row.offsetWidth, base: rl.offsetTop });
      finish();
    }).catch(finish);
  }, []);
  const S0 = 3.3, PUSHV = 1.06; // portrait : 3,3× pour que le « 4 » remplisse l'écran étroit
  const scale = mix(S0, S0 * PUSHV, PUSH(Math.min(1, f / 12))) + (1 - S0 * PUSHV) * ZOOM(Math.max(0, Math.min(1, (f - 12) / 12)));
  const slide = m ? m.w * (0.5 - m.lead) * (1 - ZOOM(Math.max(0, Math.min(1, (f - 12) / 24)))) : 0;
  const up = ramp(f, 32, 14, EASE.out);
  const sub = ramp(f, 34, 14, EASE.out);
  const lead = ramp(f, 0, 6, EASE.out);
  return (
    <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'Montserrat, sans-serif' }}>
      <div style={{ position: 'relative', transform: `translateY(${-56 * up}px)` }}>
        <div ref={rowRef} style={{
          display: 'flex', alignItems: 'baseline', whiteSpace: 'pre', fontSize: FS, fontWeight: 800, letterSpacing: -0.03 * FS, color: '#f2f6ff', opacity: m ? 1 : 0,
          transformOrigin: m ? `${m.lead * 100}% ${m.base}px` : '50% 50%',
          transform: `translateX(${slide}px) scale(${scale})`,
        }}>
          <span ref={ruler} style={{ display: 'inline-block', width: 0, height: 0 }} />
          {WORDS.map((w, i) => {
            const t = i === 0 ? 1 : WORD(Math.max(0, Math.min(1, (f - (18 + (i - 1) * 4)) / 12)));
            const o = i === 0 ? lead : Math.min(1, Math.max(0, (f - (18 + (i - 1) * 4)) / 2));
            return (
              <React.Fragment key={w}>
                <span ref={i === 0 ? leadRef : undefined} style={{ display: 'inline-block', opacity: o, transform: `translateX(${(1 - t) * 0.5 * FS}px)`, color: i === 0 ? GNS.blueSoft : '#f2f6ff', filter: i === 0 && f < 6 ? `blur(${(1 - f / 6) * 8}px)` : undefined }}>{w}</span>
                {i < WORDS.length - 1 && <span> </span>}
              </React.Fragment>
            );
          })}
        </div>
        <div style={{ position: 'absolute', left: 0, right: 0, top: FS * 1.35, textAlign: 'center', opacity: sub, transform: `translateY(${(1 - sub) * 16}px)`, fontWeight: 700, fontSize: 64, color: GNS.orange, letterSpacing: -1, whiteSpace: 'nowrap' }}>
          Une seule boutique.
        </div>
      </div>
    </div>
  );
};
