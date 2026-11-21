import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';

// Photo plein cadre (cover) avec poussée lente. focus = point d'intérêt (0–1).
export const Photo: React.FC<{ src: string; push?: [number, number]; frames: number; focus?: [number, number]; dim?: number; tint?: number; style?: React.CSSProperties; shiftX?: [number, number] }> = ({
  src, push = [1, 1.08], frames, focus = [0.5, 0.5], dim = 0, tint = 0, style, shiftX = [0, 0],
}) => {
  const f = useCurrentFrame();
  const t = ramp(f, 0, frames, EASE.swift);
  return (
    <div style={{ position: 'absolute', inset: 0, overflow: 'hidden', ...style }}>
      <Img src={staticFile(`img/${src}`)} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', objectPosition: `${focus[0] * 100}% ${focus[1] * 100}%`, transform: `translateX(${mix(shiftX[0], shiftX[1], t)}px) scale(${mix(push[0], push[1], t)})`, filter: dim ? `brightness(${1 - dim})` : undefined }} />
      {tint > 0 && <div style={{ position: 'absolute', inset: 0, background: `linear-gradient(180deg, rgba(7,21,47,${tint}) 0%, rgba(7,21,47,${tint * 0.8}) 100%)` }} />}
    </div>
  );
};

// Carte photo à coins arrondis (cadre fin lumineux).
export const PhotoCard: React.FC<{ src: string; w: number; h: number; focus?: [number, number]; style?: React.CSSProperties; glow?: number }> = ({ src, w, h, focus = [0.5, 0.3], style, glow = 0.5 }) => (
  <div style={{ width: w, height: h, borderRadius: 36, overflow: 'hidden', position: 'relative', border: '2px solid rgba(160,195,255,0.55)', boxShadow: `0 40px 90px rgba(0,4,16,0.7), 0 0 ${70 * glow}px rgba(40,100,255,${0.55 * glow})`, ...style }}>
    <Img src={staticFile(`img/${src}`)} style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: `${focus[0] * 100}% ${focus[1] * 100}%` }} />
  </div>
);

export const Pill: React.FC<{ children: React.ReactNode; style?: React.CSSProperties }> = ({ children, style }) => (
  <div style={{ display: 'inline-flex', alignItems: 'center', gap: 14, padding: '14px 34px', borderRadius: 999, fontFamily: 'Montserrat', fontWeight: 800, color: '#f2f6ff', background: 'rgba(8,20,48,0.82)', border: '1.5px solid rgba(130,170,255,0.45)', ...style }}>{children}</div>
);

export const MiseEnScene: React.FC = () => (
  <div style={{ position: 'absolute', left: 36, bottom: 54, fontFamily: 'Montserrat', fontWeight: 600, fontSize: 24, letterSpacing: 2, color: 'rgba(230,238,255,0.8)', padding: '8px 18px', borderRadius: 99, background: 'rgba(5,12,30,0.55)', border: '1px solid rgba(160,190,255,0.3)' }}>
    Mise en scène
  </div>
);
