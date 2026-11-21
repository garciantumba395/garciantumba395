import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { FootL, LightBgL, LogoCard, POP, clamp01 } from '../ui';

// Plan 6 (16:9) — appel. Local = i − 1105. Maintien final > 4 s.
export const L6Final: React.FC = () => {
  const f = useCurrentFrame();
  const S = 1105;
  const logo = POP(clamp01((f - (T(40.04) - S)) / 16));
  const t = ramp(f, T(40.88) - S, 16, EASE.snappy);
  const site = POP(clamp01((f - (T(41.99) - S)) / 16));
  const tel = ramp(f, 100, 14, EASE.out), foot = ramp(f, 110, 16, EASE.out);
  const ring = f >= 90 ? 0.5 + 0.5 * Math.sin(((f - 90) / 45) * Math.PI * 2) : 0;
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBgL />
      <div style={{ position: 'absolute', left: 100, top: 80, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.85 + 0.15 * logo})`, transformOrigin: 'left top' }}><LogoCard w={600} /></div>
      <div style={{ position: 'absolute', left: 100, top: 430, width: 900, overflow: 'hidden', height: 250 }}>
        <div style={{ fontWeight: 900, fontSize: 108, lineHeight: 1.05, letterSpacing: -3, color: C.navy, transform: `translateY(${(1 - t) * 110}%)` }}>Parlons de<br /><span style={{ color: C.blue }}>votre projet.</span></div>
      </div>
      <div style={{ position: 'absolute', left: 1020, width: 800, top: 130, textAlign: 'center', opacity: f >= T(41.99) - S ? 1 : 0, transform: `scale(${mix(1.4, 1, site)})` }}>
        <div style={{ display: 'inline-block', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 180, letterSpacing: -5, padding: '10px 70px', borderRadius: 999, boxShadow: `0 14px 0 ${C.navy}, 0 0 ${40 + 30 * ring}px rgba(255,122,0,${0.35 + 0.2 * ring})` }}>hce.cd</div>
      </div>
      <div style={{ position: 'absolute', left: 1020, width: 800, top: 470, textAlign: 'center', opacity: tel, transform: `translateY(${(1 - tel) * 20}px)` }}>
        <div style={{ fontWeight: 600, fontSize: 38, color: C.ink2 }}>Appelez-nous</div>
        <div style={{ fontWeight: 900, fontSize: 90, color: C.navy, letterSpacing: -2, marginTop: 6 }}>+243 816 391 233</div>
        <div style={{ fontWeight: 700, fontSize: 46, color: C.blue, marginTop: 8 }}>contact@hce.cd</div>
      </div>
      <FootL p={foot} />
    </div>
  );
};
