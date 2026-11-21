import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBg, LogoCard, Photo, POP, clamp01 } from '../ui';

// Plan 3 (383–620 i) — présentation : « HCE, Hightech Computing Evolution : partenaire technologique à Kinshasa depuis 2018. Supervision et support 24/7. »  Local = i − 383.
const STATS = [
  { v: '2018', l: 'lancement', at: T(17.28) }, { v: '24/7', l: 'supervision & support', at: T(19.4) },
  { v: '30+', l: 'projets réalisés', at: T(20.0) }, { v: '1 500+', l: 'postes utilisateurs', at: T(20.3) },
];
export const S3Presentation: React.FC = () => {
  const f = useCurrentFrame();
  const S = 319;
  const logo = POP(clamp01((f - (T(12.12) - S)) / 16));
  const photo = ramp(f, T(13.0) - S, 20, EASE.out);
  const tag = ramp(f, T(15.15) - S, 14, EASE.snappy);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', left: 540 - 270, top: 130, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.85 + 0.15 * logo})` }}><LogoCard w={540} /></div>
      <div style={{ position: 'absolute', left: 60, top: 420, opacity: Math.min(1, photo * 1.5), transform: `translateY(${(1 - photo) * 80}px)` }}>
        <Photo src="hce-hero-operations.png" w={960} h={600} pos="68% 50%" frames={237} />
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1060, textAlign: 'center', opacity: tag, transform: `translateY(${(1 - tag) * 20}px)` }}>
        <div style={{ display: 'inline-block', fontWeight: 800, fontSize: 31, letterSpacing: 3, color: C.blue, border: `3px solid rgba(0,87,216,0.4)`, borderRadius: 999, padding: '12px 30px', whiteSpace: 'nowrap' }}>PARTENAIRE TECHNOLOGIQUE · KINSHASA</div>
      </div>
      <div style={{ position: 'absolute', left: 60, right: 60, top: 1170, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
        {STATS.map((s) => {
          const p = POP(clamp01((f - (s.at - S)) / 12));
          return (
            <div key={s.v} style={{ background: C.navy, borderRadius: 30, padding: '44px 20px', textAlign: 'center', opacity: f < s.at - S ? 0 : 1, transform: `scale(${0.8 + 0.2 * p})`, boxShadow: '0 20px 50px rgba(10,30,63,0.3)' }}>
              <div style={{ fontWeight: 900, fontSize: 108, lineHeight: 1, color: C.orange, letterSpacing: -2 }}>{s.v}</div>
              <div style={{ fontWeight: 600, fontSize: 34, color: '#dbe7ff', marginTop: 12 }}>{s.l}</div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
