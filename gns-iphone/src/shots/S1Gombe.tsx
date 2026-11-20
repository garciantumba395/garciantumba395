import React from 'react';
import { useCurrentFrame } from 'remotion';
import { Photo, PhotoCard, Pill } from './common';
import { EASE, bezier, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 1 (0–152 i) « Samedi, Gombe. Peacemaker cherche un iPhone depuis trois semaines. »
// Rue de Gombe (photo client, enseignes tierces floutées) assombrie ; badges samedi / Gombe ; deux amis traversent ;
// Peacemaker entre à « Peacemaker » (≈ f87) ; compteur « 3 semaines » à « trois semaines » (≈ f110).
const pop = bezier(0.2, 1.2, 0.3, 1);
export const S1Gombe: React.FC = () => {
  const f = useCurrentFrame();
  const sat = ramp(f, 4, 14, EASE.out), pin = ramp(f, 14, 14, EASE.out);
  const men = ramp(f, 8, 96, EASE.smooth);
  const menX = mix(1160, -520, men);
  const peace = ramp(f, 82, 22, pop), peaceTag = ramp(f, 96, 14, EASE.out);
  const sem = ramp(f, 112, 16, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <Photo src="rue.jpg" frames={152} focus={[0.57, 0.5]} push={[1.05, 1.22]} shiftX={[70, -70]} tint={0.5} />
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg, rgba(3,10,26,0.55) 0%, rgba(3,10,26,0) 30%, rgba(3,10,26,0) 60%, rgba(3,10,26,0.7) 100%)' }} />
      {/* calendrier + repère */}
      <div style={{ position: 'absolute', top: 150, left: 56, opacity: sat, transform: `translateY(${(1 - sat) * -30}px)` }}>
        <Pill style={{ fontSize: 46, letterSpacing: 6, padding: '16px 40px' }}>
          <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#a9c4ff" strokeWidth="2"><rect x="3" y="5" width="18" height="16" rx="3" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>SAMEDI
        </Pill>
      </div>
      <div style={{ position: 'absolute', top: 262, left: 56, opacity: pin, transform: `translateY(${(1 - pin) * -20}px)` }}>
        <Pill style={{ fontSize: 36, color: '#dbe7ff' }}>
          <svg width="38" height="38" viewBox="0 0 24 24" fill={GNS.orange}><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z" /></svg>Gombe, Kinshasa
        </Pill>
      </div>
      {/* deux amis qui traversent */}
      <div style={{ position: 'absolute', left: menX, top: 1010, transform: 'rotate(-2deg)', opacity: Math.min(1, ramp(f, 8, 6, EASE.out)) }}>
        <PhotoCard src="hommes.jpg" w={400} h={600} focus={[0.5, 0.4]} glow={0.25} />
      </div>
      {/* Peacemaker */}
      <div style={{ position: 'absolute', left: 540 - 300, top: 560 + (1 - peace) * 260, opacity: Math.min(1, peace * 2), transform: `scale(${0.88 + 0.12 * peace})` }}>
        <PhotoCard src="p05.jpg" w={600} h={800} focus={[0.5, 0.28]} />
      </div>
      <div style={{ position: 'absolute', top: 1400, left: 0, right: 0, textAlign: 'center', opacity: peaceTag, transform: `translateY(${(1 - peaceTag) * 20}px)` }}>
        <Pill style={{ fontSize: 52, background: 'rgba(29,92,255,0.85)', border: '2px solid rgba(190,215,255,0.8)' }}>Peacemaker</Pill>
      </div>
      {/* compteur */}
      <div style={{ position: 'absolute', top: 1520, left: 0, right: 0, textAlign: 'center', opacity: sem, transform: `scale(${0.8 + 0.2 * sem})` }}>
        <span style={{ fontWeight: 900, fontSize: 150, color: GNS.orange, letterSpacing: -4 }}>3</span>
        <span style={{ fontWeight: 800, fontSize: 64, color: '#f2f6ff', marginLeft: 22 }}>semaines</span>
      </div>
    </div>
  );
};
