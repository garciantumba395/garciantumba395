import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { Photo, Pill } from './common';
import { Phone } from '../Phone';
import { EASE, bezier, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 5 (660–756 i) « Son ancien iPhone ? Échangé : c'est le Troc iPhone. »
// Scène vendeur-client (image générée, illustrative) ; deux téléphones qui s'échangent à « Échangé » (f36) ; pastille « Troc iPhone » à f65.
export const S5Troc: React.FC = () => {
  const f = useCurrentFrame();
  const swap = ramp(f, 36, 26, EASE.smooth);
  const troc = ramp(f, 62, 16, EASE.overshoot);
  const q = ramp(f, 4, 14, EASE.out);
  const arc = Math.sin(swap * Math.PI);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <div style={{ position: 'absolute', inset: 0, overflow: 'hidden' }}>
        <Img src={staticFile('img/troc.jpg')} style={{ position: 'absolute', width: '100%', height: '100%', objectFit: 'cover', filter: 'blur(36px) brightness(0.35) saturate(1.2)', transform: 'scale(1.3)' }} />
      </div>
      <div style={{ position: 'absolute', left: 0, top: 400, width: 1080, height: 608, overflow: 'hidden', borderTop: '2px solid rgba(160,195,255,0.5)', borderBottom: '2px solid rgba(160,195,255,0.5)', boxShadow: '0 30px 90px rgba(0,4,16,0.7)', opacity: ramp(f, 0, 12, EASE.out) }}>
        <Img src={staticFile('img/troc.jpg')} style={{ width: 1080, height: 608, objectFit: 'cover', transform: `scale(${mix(1.0, 1.12, ramp(f, 0, 96, EASE.swift))})`, transformOrigin: '50% 55%' }} />
      </div>
      <div style={{ position: 'absolute', top: 1030, left: 0, right: 0, textAlign: 'center', color: '#8fa6d4', fontWeight: 600, fontSize: 26 }}>Image illustrative</div>
      <div style={{ position: 'absolute', top: 190, left: 0, right: 0, textAlign: 'center', fontWeight: 800, fontSize: 70, color: '#f2f6ff', opacity: q, transform: `translateY(${(1 - q) * 20}px)`, textShadow: '0 4px 30px rgba(0,0,0,0.7)' }}>
        Votre ancien iPhone ?
      </div>
      {/* échange de deux téléphones */}
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1090, height: 400, opacity: ramp(f, 30, 8, EASE.out) }}>
        <div style={{ position: 'absolute', left: mix(260, 680, swap), top: -arc * 60, transform: `rotate(${mix(-12, 12, swap)}deg) scale(${1 - 0.1 * swap})`, filter: 'saturate(0.5) brightness(0.8)' }}><Phone kind="iphone" hue={1} width={140} /></div>
        <div style={{ position: 'absolute', left: mix(680, 260, swap), top: arc * 60, transform: `rotate(${mix(12, -12, swap)}deg) scale(${0.9 + 0.1 * swap})` }}><Phone kind="iphone" hue={0} width={140} glow={0.4 * swap} /></div>
        <svg width="1080" height="400" style={{ position: 'absolute', left: 0, top: 0, opacity: 0.8 }} fill="none" stroke="#a9c4ff" strokeWidth="6" strokeLinecap="round">
          <path d="M470 90 Q540 30 610 90" /><path d="M600 78l12 14-18 3" /><path d="M610 290 Q540 350 470 290" /><path d="M480 302l-12-14 18-3" />
        </svg>
      </div>
      <div style={{ position: 'absolute', top: 1560, left: 0, right: 0, textAlign: 'center', opacity: Math.min(1, troc * 1.5), transform: `scale(${0.8 + 0.2 * troc})` }}>
        <Pill style={{ fontSize: 66, background: 'linear-gradient(160deg,#ff8a1f,#ff6a00)', color: '#0b1220', border: '2px solid rgba(255,230,200,0.7)', padding: '22px 58px' }}>Troc iPhone</Pill>
      </div>
    </div>
  );
};
