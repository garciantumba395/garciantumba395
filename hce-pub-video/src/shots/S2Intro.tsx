import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBg, LogoCard, POP, clamp01 } from '../ui';

// Plan 2 (235–329 i) — « Chez HCE, notre métier, c'est que ça n'arrive jamais. »  Local = i − 235.
// Chez HCE 8,72 · notre métier 9,6 · que ça n'arrive jamais 10,6.
export const S2Intro: React.FC = () => {
  const f = useCurrentFrame();
  const S = 235;
  const reveal = ramp(f, 0, 20, EASE.smooth);
  const logo = POP(clamp01((f - (T(8.72) - S)) / 16));
  const t1 = ramp(f, T(9.6) - S, 14, EASE.snappy);
  const t2 = ramp(f, T(10.6) - S, 14, EASE.snappy);
  const check = ramp(f, T(11.4) - S, 10, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', inset: 0, background: C.navy, clipPath: `circle(${mix(1700, 0, reveal)}px at 50% 50%)` }} />
      <div style={{ position: 'absolute', left: 540 - 300, top: 330, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.8 + 0.2 * logo})` }}><LogoCard w={600} /></div>
      <div style={{ position: 'absolute', left: 70, right: 70, top: 760, overflow: 'hidden', height: 140, textAlign: 'center' }}>
        <div style={{ fontWeight: 900, fontSize: 96, letterSpacing: -3, color: C.navy, transform: `translateY(${(1 - t1) * 110}%)` }}>Notre métier :</div>
      </div>
      <div style={{ position: 'absolute', left: 70, right: 70, top: 900, overflow: 'hidden', height: 300, textAlign: 'center' }}>
        <div style={{ fontWeight: 900, fontSize: 96, lineHeight: 1.05, letterSpacing: -3, color: C.orange, transform: `translateY(${(1 - t2) * 110}%)` }}>que ça n’arrive<br />jamais.</div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1300, display: 'flex', justifyContent: 'center', opacity: Math.min(1, check * 1.5), transform: `scale(${0.7 + 0.3 * check})` }}>
        <svg width="210" height="210" viewBox="0 0 48 48" fill="none" stroke={C.blue} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M24 5l15 6v11c0 10-6 17-15 21C15 39 9 32 9 22V11z" fill="rgba(0,87,216,0.08)" /><path d="M17 24l5 5 9-10" stroke={C.orange} strokeWidth="3" /></svg>
      </div>
    </div>
  );
};
