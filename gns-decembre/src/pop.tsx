import React from 'react';
import { Img, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { EASE, bezier, mix, ramp } from './Polish';

// Concept « Affiche pop » : bleu GNS vif, typographie géante, autocollants, orange réservé à la réduction et aux prix.
export const BLUE = '#0057ff', DEEP = '#0a2fb8', NAVY = '#07152f', ORANGE = '#ff7a00', WHITE = '#ffffff';
export const SLAM = bezier(0.2, 1.35, 0.3, 1);
export const eur = (n: number) => `${n.toFixed(2).replace('.', ',')} $`;
export const clamp01 = (x: number) => Math.min(1, Math.max(0, x));

export const PopBg: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <div style={{ position: 'absolute', inset: 0, background: `linear-gradient(165deg, #1a6bff 0%, ${BLUE} 38%, ${DEEP} 100%)`, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', inset: -60, backgroundImage: 'radial-gradient(rgba(255,255,255,0.16) 2.4px, transparent 3px)', backgroundSize: '30px 30px', backgroundPosition: `${f * 0.4}px ${f * 0.4}px`, WebkitMaskImage: 'linear-gradient(180deg, #000 0%, rgba(0,0,0,0.25) 70%, transparent 100%)', maskImage: 'linear-gradient(180deg, #000 0%, rgba(0,0,0,0.25) 70%, transparent 100%)' }} />
      <div style={{ position: 'absolute', left: -200, top: 1180, width: 1500, height: 260, background: 'rgba(255,255,255,0.06)', transform: 'rotate(-14deg)' }} />
      <div style={{ position: 'absolute', left: -200, top: 1450, width: 1500, height: 90, background: 'rgba(255,122,0,0.18)', transform: 'rotate(-14deg)' }} />
    </div>
  );
};

// Autocollant blanc incliné
export const Sticker: React.FC<{ children: React.ReactNode; rot?: number; bg?: string; color?: string; style?: React.CSSProperties }> = ({ children, rot = 0, bg = WHITE, color = NAVY, style }) => (
  <div style={{ display: 'inline-block', fontFamily: 'Montserrat', fontWeight: 900, background: bg, color, borderRadius: 26, padding: '14px 36px', transform: `rotate(${rot}deg)`, boxShadow: '0 14px 0 rgba(7,21,47,0.28), 0 30px 60px rgba(0,10,60,0.45)', ...style }}>{children}</div>
);

// Balayage orange entre deux plans (6 i) centré sur la frontière.
export const Wipe: React.FC<{ at: number }> = ({ at }) => {
  const f = useCurrentFrame();
  const t = (f - (at - 4)) / 8;
  if (t < 0 || t > 1) return null;
  const x = t < 0.5 ? mix(-1100, 0, EASE.exit(t * 2)) : mix(0, 1100, EASE.out((t - 0.5) * 2));
  return <div style={{ position: 'absolute', inset: 0, transform: `translateX(${x}px) skewX(-8deg)`, background: `linear-gradient(90deg, ${ORANGE}, #ffa040)`, width: 1180, left: -50 }} />;
};

export const Legal: React.FC = () => {
  const f = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const o = ramp(f, 0, 12, EASE.out) * (1 - ramp(f, durationInFrames - 6, 6, EASE.exit));
  return (
    <div style={{ position: 'absolute', left: 60, right: 60, bottom: 36, textAlign: 'center', fontFamily: 'Montserrat', fontWeight: 600, fontSize: 21, lineHeight: 1.35, color: 'rgba(235,242,255,0.92)', opacity: o }}>
      Offre valable du 1<sup style={{ fontSize: 13 }}>er</sup> décembre 2026 au 15 janvier 2027 uniquement sur la boutique en ligne gnsbusinessrdc.store (iPhone, Samsung Galaxy, Google Pixel, TECNO). Réduction de 5 % appliquée automatiquement au paiement. Prix indicatifs en $ US.
    </div>
  );
};

export const LogoCard: React.FC<{ w: number; style?: React.CSSProperties }> = ({ w, style }) => (
  <div style={{ width: w, borderRadius: w * 0.09, background: '#fff', padding: w * 0.03, boxShadow: '0 14px 0 rgba(7,21,47,0.25), 0 30px 70px rgba(0,10,60,0.5)', ...style }}>
    <Img src={staticFile('logo-gns.png')} style={{ width: '100%', display: 'block' }} />
  </div>
);
