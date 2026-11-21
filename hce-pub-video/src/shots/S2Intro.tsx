import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBg, LogoCard, POP, clamp01 } from '../ui';

// Plan 2 (263–383 i) — intro : « Cette histoire, on la connaît. Chez HCE, notre métier, c'est qu'elle n'arrive jamais. »  Local = i − 263.
export const S2Intro: React.FC = () => {
  const f = useCurrentFrame();
  const S = 263;
  const reveal = ramp(f, 0, 22, EASE.smooth);
  const t1 = ramp(f, T(9.07) - S, 16, EASE.snappy);
  const logo = POP(clamp01((f - (T(11.01) - S)) / 16));
  const t2 = ramp(f, T(11.86) - S, 16, EASE.snappy);
  const check = ramp(f, T(13.0) - S, 12, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', inset: 0, background: C.navy, clipPath: `circle(${mix(1700, 0, reveal)}px at 50% 50%)` }} />
      <div style={{ position: 'absolute', left: 70, right: 70, top: 300, overflow: 'hidden', height: 300 }}>
        <div style={{ fontWeight: 900, fontSize: 108, lineHeight: 1.04, letterSpacing: -3, color: C.navy, transform: `translateY(${(1 - t1) * 110}%)` }}>Cette histoire,<br />on la <span style={{ color: C.blue }}>connaît.</span></div>
      </div>
      <div style={{ position: 'absolute', left: 540 - 290, top: 700, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.8 + 0.2 * logo})` }}><LogoCard w={580} /></div>
      <div style={{ position: 'absolute', left: 70, right: 70, top: 1130, overflow: 'hidden', height: 330 }}>
        <div style={{ fontWeight: 800, fontSize: 68, lineHeight: 1.1, letterSpacing: -1.5, color: C.navy, transform: `translateY(${(1 - t2) * 110}%)` }}>Notre métier : qu’elle <span style={{ color: C.orange }}>n’arrive jamais.</span></div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1480, display: 'flex', justifyContent: 'center', opacity: Math.min(1, check * 1.5), transform: `scale(${0.7 + 0.3 * check})` }}>
        <svg width="190" height="190" viewBox="0 0 48 48" fill="none" stroke={C.blue} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M24 5l15 6v11c0 10-6 17-15 21C15 39 9 32 9 22V11z" fill="rgba(0,87,216,0.08)" /><path d="M17 24l5 5 9-10" stroke={C.orange} strokeWidth="3" /></svg>
      </div>
    </div>
  );
};
