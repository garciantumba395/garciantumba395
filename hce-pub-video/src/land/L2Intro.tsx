import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBgL, LogoCard, POP, clamp01 } from '../ui';

// Plan 2 (16:9) — « Chez HCE, notre métier, c'est que ça n'arrive jamais. »  Local = i − 235.
export const L2Intro: React.FC = () => {
  const f = useCurrentFrame();
  const S = 235;
  const reveal = ramp(f, 0, 20, EASE.smooth);
  const logo = POP(clamp01((f - (T(8.72) - S)) / 16));
  const t1 = ramp(f, T(9.6) - S, 14, EASE.snappy);
  const t2 = ramp(f, T(10.6) - S, 14, EASE.snappy);
  const check = ramp(f, T(11.4) - S, 10, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBgL />
      <div style={{ position: 'absolute', inset: 0, background: C.navy, clipPath: `circle(${mix(1300, 0, reveal)}px at 50% 50%)` }} />
      <div style={{ position: 'absolute', left: 100, top: 90, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.8 + 0.2 * logo})`, transformOrigin: 'left top' }}><LogoCard w={640} /></div>
      <div style={{ position: 'absolute', left: 100, top: 470, width: 1400, overflow: 'hidden', height: 150 }}>
        <div style={{ fontWeight: 900, fontSize: 112, letterSpacing: -3, color: C.navy, transform: `translateY(${(1 - t1) * 110}%)` }}>Notre métier :</div>
      </div>
      <div style={{ position: 'absolute', left: 100, top: 620, width: 1500, overflow: 'hidden', height: 170 }}>
        <div style={{ fontWeight: 900, fontSize: 112, letterSpacing: -3, color: C.orange, transform: `translateY(${(1 - t2) * 110}%)` }}>que ça n’arrive jamais.</div>
      </div>
      <div style={{ position: 'absolute', left: 1540, top: 120, opacity: Math.min(1, check * 1.5), transform: `scale(${0.7 + 0.3 * check})` }}>
        <svg width="250" height="250" viewBox="0 0 48 48" fill="none" stroke={C.blue} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M24 5l15 6v11c0 10-6 17-15 21C15 39 9 32 9 22V11z" fill="rgba(0,87,216,0.08)" /><path d="M17 24l5 5 9-10" stroke={C.orange} strokeWidth="3" /></svg>
      </div>
    </div>
  );
};
