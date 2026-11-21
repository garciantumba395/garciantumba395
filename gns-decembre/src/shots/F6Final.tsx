import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { LogoCard, NAVY, ORANGE, SLAM, Sticker, WHITE, clamp01 } from '../pop';

// Plan 6 (703–960 i) « Rendez-vous sur gnsbusinessrdc.store. Offre valable en ligne, jusqu'au quinze janvier. »  Local = i − 703.
// Site f706 (3) · « offre valable en ligne » f783 (80) · « jusqu'au 15 janvier » f805 (102) · fin de voix f855 (152) puis maintien 3,5 s.
export const F6Final: React.FC = () => {
  const f = useCurrentFrame();
  const logo = ramp(f, 0, 14, EASE.overshoot), site = SLAM(clamp01((f - 3) / 14)), big = SLAM(clamp01((f - 10) / 14));
  const offre = ramp(f, 80, 12, EASE.snappy), jusq = SLAM(clamp01((f - 102) / 12));
  const ring = f >= 40 ? 0.5 + 0.5 * Math.sin(((f - 40) / 40) * Math.PI * 2) : 0;
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <div style={{ position: 'absolute', top: 120, left: 540 - 230, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.85 + 0.15 * logo})` }}><LogoCard w={460} /></div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 520, textAlign: 'center', opacity: f >= 10 ? 1 : 0, transform: `scale(${mix(1.6, 1, big)})` }}>
        <div style={{ fontWeight: 900, fontSize: 380, lineHeight: 1, letterSpacing: -18, color: WHITE, textShadow: `14px 14px 0 ${ORANGE}, 0 30px 60px rgba(0,10,60,0.5)` }}>-5%</div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1000, textAlign: 'center', opacity: f >= 3 ? 1 : 0, transform: `scale(${mix(1.3, 1, site)})` }}>
        <div style={{ display: 'inline-block', background: NAVY, color: WHITE, fontWeight: 900, fontSize: 70, letterSpacing: -1, padding: '22px 56px', borderRadius: 999, border: `5px solid ${WHITE}`, boxShadow: `0 12px 0 rgba(255,122,0,0.95), 0 0 ${40 + 30 * ring}px rgba(255,255,255,${0.25 + 0.2 * ring})` }}>gnsbusinessrdc.store</div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1200, textAlign: 'center', fontWeight: 800, fontSize: 50, color: WHITE, letterSpacing: 1, opacity: offre, transform: `translateY(${(1 - offre) * 20}px)` }}>OFFRE VALABLE EN LIGNE</div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1290, textAlign: 'center', opacity: f >= 102 ? 1 : 0, transform: `scale(${mix(1.5, 1, jusq)}) rotate(-2deg)` }}>
        <Sticker bg={ORANGE} color={WHITE} style={{ fontSize: 68, padding: '16px 46px', textShadow: `4px 4px 0 ${NAVY}` }}>JUSQU’AU 15 JANVIER</Sticker>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1450, textAlign: 'center', color: 'rgba(235,242,255,0.9)', fontWeight: 700, fontSize: 36, opacity: ramp(f, 120, 14, EASE.out) }}>Réduction automatique · aucun code</div>
    </div>
  );
};
