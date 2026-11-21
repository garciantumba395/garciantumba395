import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBgL, LogoCard, Photo, POP, clamp01 } from '../ui';

// Plan 3 (16:9) — présentation. Local = i − 383.
const STATS = [
  { v: '2018', l: 'lancement', at: T(17.28) }, { v: '24/7', l: 'supervision & support', at: T(19.4) },
  { v: '30+', l: 'projets réalisés', at: T(20.0) }, { v: '1 500+', l: 'postes utilisateurs', at: T(20.3) },
];
export const L3Presentation: React.FC = () => {
  const f = useCurrentFrame();
  const S = 319;
  const logo = POP(clamp01((f - (T(12.12) - S)) / 16));
  const photo = ramp(f, T(13.0) - S, 20, EASE.out);
  const tag = ramp(f, T(15.15) - S, 14, EASE.snappy);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBgL />
      <div style={{ position: 'absolute', left: 100, top: 70, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.85 + 0.15 * logo})`, transformOrigin: 'left top' }}><LogoCard w={560} /></div>
      <div style={{ position: 'absolute', left: 100, top: 330, opacity: tag, transform: `translateY(${(1 - tag) * 16}px)` }}>
        <div style={{ display: 'inline-block', fontWeight: 800, fontSize: 28, letterSpacing: 3, color: C.blue, border: '3px solid rgba(0,87,216,0.4)', borderRadius: 999, padding: '10px 28px', whiteSpace: 'nowrap' }}>PARTENAIRE TECHNOLOGIQUE · KINSHASA</div>
      </div>
      <div style={{ position: 'absolute', left: 100, top: 420, width: 860, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 22 }}>
        {STATS.map((s) => {
          const p = POP(clamp01((f - (s.at - S)) / 12));
          return (
            <div key={s.v} style={{ background: C.navy, borderRadius: 26, padding: '30px 16px', textAlign: 'center', opacity: f < s.at - S ? 0 : 1, transform: `scale(${0.8 + 0.2 * p})`, boxShadow: '0 20px 50px rgba(10,30,63,0.3)' }}>
              <div style={{ fontWeight: 900, fontSize: 100, lineHeight: 1, color: C.orange, letterSpacing: -2 }}>{s.v}</div>
              <div style={{ fontWeight: 600, fontSize: 30, color: '#dbe7ff', marginTop: 10 }}>{s.l}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: 'absolute', left: 1020, top: 70, opacity: Math.min(1, photo * 1.5), transform: `translateX(${(1 - photo) * 80}px)` }}>
        <Photo src="hce-hero-operations.png" w={800} h={940} pos="70% 50%" frames={237} />
      </div>
    </div>
  );
};
