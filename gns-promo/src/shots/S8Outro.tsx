import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { Phone } from '../Phone';
import { clamp01 } from '../util';
import { EASE, bezier, mix, ramp } from '../Polish';
import { ADDRESS, PHONE, SITE } from '../data';
import { GNS } from '../tokens';
import type { Kind } from '../data';

// Plan 8 — logo-shrink-wordmark-lockup + « photo de groupe » (Q8) : les 4 familles de téléphones arrivent de quatre côtés
// autour du logo, éclair blanc au verrouillage du logo (white-flash-logo-simplify-cut), puis appel (téléphone) et invitation
// (site). Tout est posé ≥ 70 f avant la fin : maintien final de 2,3 s (R1 : ≥ 1 s).
const LOGO_W = 720, LOGO_H = Math.round(720 * 1086 / 1450);
const LOGO_Y = 270;
const GROUP: { kind: Kind; hue: number; from: [number, number]; to: [number, number]; rot: number; delay: number }[] = [
  { kind: 'iphone', hue: 0, from: [-300, 200], to: [120, 440], rot: -14, delay: 0 },
  { kind: 'galaxy', hue: 2, from: [1380, 160], to: [960, 440], rot: 14, delay: 4 },
  { kind: 'pixel', hue: 1, from: [-300, 800], to: [235, 610], rot: -7, delay: 8 },
  { kind: 'tecno', hue: 3, from: [1380, 800], to: [845, 610], rot: 7, delay: 12 },
];
const flyIn = bezier(0.2, 0.9, 0.25, 1.06);

export const S8Outro: React.FC = () => {
  const f = useCurrentFrame();
  const logoT = EASE.overshoot(clamp01((f - 29) / 20));
  const logoO = clamp01((f - 29) / 6);
  const flash = f >= 29 && f < 35 ? Math.sin(((f - 29) / 6) * Math.PI) * 0.7 : 0;
  const call = ramp(f, 59, 20, EASE.out);
  const callNum = ramp(f, 73, 20, EASE.out);
  const visit = ramp(f, 118, 20, EASE.out);
  const site = ramp(f, 132, 20, EASE.overshoot);
  const sub = ramp(f, 147, 18, EASE.out);
  const addr = ramp(f, 170, 18, EASE.out);
  const ring = f >= 140 ? 0.5 + 0.5 * Math.sin(((f - 140) / 40) * Math.PI * 2) : 0;
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      {/* photo de groupe : 4 téléphones derrière le logo */}
      {GROUP.map((g, i) => {
        const t = flyIn(clamp01((f - g.delay) / 26));
        const x = mix(g.from[0], g.to[0], t), y = mix(g.from[1], g.to[1], t);
        return (
          <div key={i} style={{ position: 'absolute', left: x - 95, top: y - 198, transform: `rotate(${mix(g.rot * 3, g.rot, t)}deg)`, opacity: clamp01((f - g.delay) / 6) }}>
            <Phone kind={g.kind} hue={g.hue} width={190} glow={0.25} />
          </div>
        );
      })}
      {/* logo (carte claire, comme l'en-tête du site) */}
      <div style={{ position: 'absolute', left: 540 - LOGO_W / 2, top: LOGO_Y, width: LOGO_W, height: LOGO_H, opacity: logoO, transform: `scale(${mix(1.35, 1, logoT)})`, filter: logoT < 1 ? `blur(${(1 - clamp01(logoT)) * 12}px)` : undefined }}>
        <div style={{ position: 'absolute', inset: 0, borderRadius: 56, background: '#ffffff', boxShadow: '0 40px 100px rgba(0,6,24,0.7), 0 0 90px rgba(40,100,255,0.45)' }} />
        <Img src={staticFile('logo-gns.png')} style={{ position: 'absolute', inset: 10, width: LOGO_W - 20, height: LOGO_H - 20, objectFit: 'contain' }} />
      </div>
      {flash > 0 && <div style={{ position: 'absolute', inset: 0, background: `rgba(255,255,255,${0.55 * flash})`, mixBlendMode: 'screen' }} />}

      {/* appel */}
      <div style={{ position: 'absolute', top: 930, left: 0, right: 0, textAlign: 'center' }}>
        <div style={{ color: '#a9b8d8', fontWeight: 700, fontSize: 40, opacity: call, transform: `translateY(${(1 - call) * 20}px)` }}>Intéressé(e) ? Appelez-nous</div>
        <div style={{ marginTop: 22, display: 'inline-flex', alignItems: 'center', gap: 24, padding: '18px 46px', borderRadius: 999, background: 'linear-gradient(160deg, #ff8a1f, #ff6a00)', color: '#0b1220', fontWeight: 900, fontSize: 70, letterSpacing: -1.5, opacity: callNum, transform: `scale(${0.9 + 0.1 * callNum})`, boxShadow: '0 20px 60px rgba(255,122,0,0.45)' }}>
          <svg width="62" height="62" viewBox="0 0 24 24" fill="#0b1220"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.6a1 1 0 0 1-.25 1z" /></svg>
          {PHONE}
        </div>
      </div>
      {/* invitation */}
      <div style={{ position: 'absolute', top: 1230, left: 0, right: 0, textAlign: 'center' }}>
        <div style={{ color: '#f2f6ff', fontWeight: 800, fontSize: 60, letterSpacing: -1, opacity: visit, transform: `translateY(${(1 - visit) * 24}px)` }}>Visitez la boutique</div>
        <div style={{ marginTop: 26, display: 'inline-block', padding: '20px 54px', borderRadius: 999, background: 'linear-gradient(160deg, #1d5cff, #0b3ad1)', color: '#fff', fontWeight: 800, fontSize: 58, letterSpacing: -1, opacity: Math.min(1, site * 1.5), transform: `scale(${0.88 + 0.12 * site})`, border: '2px solid rgba(160,195,255,0.6)', boxShadow: `0 0 ${50 + 30 * ring}px rgba(40,100,255,${0.45 + 0.2 * ring})` }}>
          {SITE}
        </div>
        <div style={{ marginTop: 30, color: '#a9b8d8', fontWeight: 600, fontSize: 38, opacity: sub, transform: `translateY(${(1 - sub) * 16}px)` }}>et découvrez tous nos produits</div>
      </div>
      <div style={{ position: 'absolute', bottom: 150, left: 0, right: 0, textAlign: 'center', color: '#7f93bf', fontWeight: 600, fontSize: 30, letterSpacing: 1, opacity: addr, transform: `translateY(${(1 - addr) * 12}px)` }}>
        {ADDRESS}
      </div>
    </div>
  );
};
