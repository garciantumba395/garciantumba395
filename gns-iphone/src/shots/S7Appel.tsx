import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { ADDRESS, PHONE, SITE } from '../data';
import { GNS } from '../tokens';

// Plan 7 (855–1200 i) « Appelez le +243 983 468 341. Ou visitez gnsbusinessrdc.store. Votre iPhone vous attend. »
// Photo 02 (fond bleu + lueur orange = charte) en haut ; logo ; numéro (f6) ; site (f125) ; adresse (f150) ; chute (f213) tenue jusqu'à la fin (≥ 3 s).
export const S7Appel: React.FC = () => {
  const f = useCurrentFrame();
  const ph = ramp(f, 0, 20, EASE.out), push = mix(1, 1.05, ramp(f, 0, 345, EASE.swift));
  const logo = ramp(f, 4, 18, EASE.overshoot), call = ramp(f, 8, 16, EASE.out), num = ramp(f, 14, 18, EASE.overshoot);
  const visit = ramp(f, 118, 16, EASE.out), site = ramp(f, 126, 18, EASE.overshoot), addr = ramp(f, 152, 16, EASE.out), fin = ramp(f, 212, 22, EASE.overshoot);
  const ring = f >= 140 ? 0.5 + 0.5 * Math.sin(((f - 140) / 45) * Math.PI * 2) : 0;
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <div style={{ position: 'absolute', left: 0, top: 0, width: 1080, height: 1080, overflow: 'hidden', opacity: ph }}>
        <Img src={staticFile('img/p02.jpg')} style={{ width: 1080, height: 1080, objectFit: 'cover', transform: `scale(${push})` }} />
        <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, height: 360, background: 'linear-gradient(180deg, rgba(7,21,47,0), #07152f 96%)' }} />
      </div>
      <div style={{ position: 'absolute', top: 905, left: 540 - 230, width: 460, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.85 + 0.15 * logo})` }}>
        <div style={{ borderRadius: 36, background: '#fff', padding: 10, boxShadow: '0 30px 80px rgba(0,6,24,0.7), 0 0 80px rgba(40,100,255,0.5)' }}>
          <Img src={staticFile('logo-gns.png')} style={{ width: '100%', display: 'block' }} />
        </div>
      </div>
      <div style={{ position: 'absolute', top: 1285, left: 0, right: 0, textAlign: 'center' }}>
        <div style={{ color: '#a9b8d8', fontWeight: 700, fontSize: 40, opacity: call }}>Appelez-nous</div>
        <div style={{ marginTop: 16, display: 'inline-flex', alignItems: 'center', gap: 22, padding: '16px 46px', borderRadius: 999, background: 'linear-gradient(160deg,#ff8a1f,#ff6a00)', color: '#0b1220', fontWeight: 900, fontSize: 68, letterSpacing: -1.5, opacity: Math.min(1, num * 1.5), transform: `scale(${0.9 + 0.1 * num})`, boxShadow: '0 20px 60px rgba(255,122,0,0.45)' }}>
          <svg width="60" height="60" viewBox="0 0 24 24" fill="#0b1220"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.6a1 1 0 0 1-.25 1z" /></svg>{PHONE}
        </div>
      </div>
      <div style={{ position: 'absolute', top: 1500, left: 0, right: 0, textAlign: 'center' }}>
        <div style={{ color: '#f2f6ff', fontWeight: 800, fontSize: 52, opacity: visit, transform: `translateY(${(1 - visit) * 16}px)` }}>Visitez la boutique</div>
        <div style={{ marginTop: 14, display: 'inline-block', padding: '16px 48px', borderRadius: 999, background: 'linear-gradient(160deg,#1d5cff,#0b3ad1)', color: '#fff', fontWeight: 800, fontSize: 54, letterSpacing: -1, border: '2px solid rgba(160,195,255,0.6)', opacity: Math.min(1, site * 1.5), transform: `scale(${0.88 + 0.12 * site})`, boxShadow: `0 0 ${40 + 30 * ring}px rgba(40,100,255,${0.45 + 0.2 * ring})` }}>{SITE}</div>
      </div>
      <div style={{ position: 'absolute', top: 1710, left: 0, right: 0, textAlign: 'center', color: '#7f93bf', fontWeight: 600, fontSize: 28, opacity: addr }}>{ADDRESS}</div>
      <div style={{ position: 'absolute', top: 1770, left: 0, right: 0, textAlign: 'center', fontWeight: 900, fontSize: 70, letterSpacing: -1.5, color: '#f2f6ff', opacity: Math.min(1, fin * 1.5), transform: `translateY(${(1 - fin) * 20}px) scale(${0.95 + 0.05 * fin})`, textShadow: '0 0 40px rgba(80,140,255,0.6)' }}>
        Votre iPhone vous attend.
      </div>
    </div>
  );
};
