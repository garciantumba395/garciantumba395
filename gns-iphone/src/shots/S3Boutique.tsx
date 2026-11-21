import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { Photo, PhotoCard, Pill } from './common';
import { EASE, bezier, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 3 (266–468 i) « Samedi, elle pousse une dernière porte : GNS Business RDC. Et là, tout est disponible. »
// Éclair bleu + deux battants qui s'écartent (f14–34) sur la vraie boutique ; Peacemaker (photo 03) entre ; logo à « G-N-S » (f69) ; « Disponible » à f165.
const pop = bezier(0.2, 1.2, 0.3, 1);
export const S3Boutique: React.FC = () => {
  const f = useCurrentFrame();
  const door = ramp(f, 16, 24, EASE.smooth);
  const flash = f >= 12 && f < 36 ? Math.sin(((f - 12) / 24) * Math.PI) : 0;
  const peace = ramp(f, 40, 22, pop);
  const logo = ramp(f, 66, 18, EASE.overshoot);
  const ok = ramp(f, 160, 14, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <Photo src="boutique.jpg" frames={202} focus={[0.5, 0.35]} push={[1.0, 1.14]} dim={0.1} />
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg, rgba(3,10,26,0.72) 0%, rgba(3,10,26,0.1) 30%, rgba(3,10,26,0.05) 55%, rgba(3,10,26,0.8) 100%)' }} />
      {/* battants de la porte */}
      <div style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: 540, transform: `translateX(${-door * 560}px)`, background: 'linear-gradient(90deg,#07152f,#0e2a66)', borderRight: '4px solid rgba(160,195,255,0.6)', boxShadow: '0 0 80px rgba(60,120,255,0.6)' }} />
      <div style={{ position: 'absolute', right: 0, top: 0, bottom: 0, width: 540, transform: `translateX(${door * 560}px)`, background: 'linear-gradient(270deg,#07152f,#0e2a66)', borderLeft: '4px solid rgba(160,195,255,0.6)', boxShadow: '0 0 80px rgba(60,120,255,0.6)' }} />
      <div style={{ position: 'absolute', inset: 0, background: `radial-gradient(circle at 50% 50%, rgba(170,210,255,${0.85 * flash}), rgba(60,120,255,0) 60%)`, mixBlendMode: 'screen' }} />
      {/* logo GNS */}
      <div style={{ position: 'absolute', top: 150, left: 540 - 300, width: 600, opacity: Math.min(1, logo * 1.5), transform: `scale(${0.85 + 0.15 * logo})` }}>
        <div style={{ borderRadius: 44, background: '#fff', padding: 14, boxShadow: '0 30px 80px rgba(0,6,24,0.7), 0 0 80px rgba(40,100,255,0.55)' }}>
          <Img src={staticFile('logo-gns.png')} style={{ width: '100%', display: 'block' }} />
        </div>
      </div>
      {/* Peacemaker entre */}
      <div style={{ position: 'absolute', right: 40, top: 1000 + (1 - peace) * 400, opacity: Math.min(1, peace * 2) }}>
        <PhotoCard src="p03.jpg" w={440} h={660} focus={[0.5, 0.3]} glow={0.4} />
      </div>
      {/* Disponible */}
      <div style={{ position: 'absolute', top: 1300, left: 50, opacity: Math.min(1, ok * 1.5), transform: `scale(${0.8 + 0.2 * ok})`, transformOrigin: 'left center' }}>
        <Pill style={{ fontSize: 58, background: 'rgba(22,163,74,0.92)', border: '2px solid rgba(190,255,215,0.8)', padding: '20px 44px' }}>
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3.2" strokeLinecap="round"><path d="M4 12.5l5 5L20 6.5" /></svg>Disponible
        </Pill>
      </div>
    </div>
  );
};
