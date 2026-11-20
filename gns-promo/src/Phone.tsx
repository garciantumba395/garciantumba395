import React from 'react';
import { GNS } from './tokens';
import type { Kind } from './data';

// Téléphone vectoriel générique (aucune photo tierce, aucun logo de marque) :
// corps métal, lunette noire, écran verrouillé aux couleurs GNS, découpe caméra selon la famille.
const WALL: [string, string, string][] = [
  ['#1d5cff', '#0b2f9e', '#06122e'],
  ['#ff7a00', '#c2410c', '#1a0f08'],
  ['#18a5a5', '#0b4a66', '#06122e'],
  ['#7c5cff', '#2a1d80', '#0a0620'],
];
const RIM: Record<Kind, [string, string]> = {
  iphone: ['#e9edf5', '#7d8799'],
  galaxy: ['#d9dde6', '#5f6779'],
  pixel: ['#cfd8e6', '#6f7c94'],
  tecno: ['#e6d9ff', '#6a5b9a'],
};

export const Phone: React.FC<{ kind: Kind; hue?: number; width?: number; time?: string; glow?: number; style?: React.CSSProperties }> = ({
  kind, hue = 0, width = 300, time = '9:41', glow = 0, style,
}) => {
  const w = width;
  const h = Math.round(w * 2.08);
  const r = w * 0.17;
  const bez = w * 0.026;
  const [c1, c2, c3] = WALL[hue % WALL.length];
  const [r1, r2] = RIM[kind];
  const sr = r - bez;
  return (
    <div style={{ position: 'relative', width: w, height: h, ...style }}>
      <div style={{
        position: 'absolute', inset: 0, borderRadius: r,
        background: `linear-gradient(150deg, ${r1} 0%, ${r2} 38%, ${r1} 62%, ${r2} 100%)`,
        boxShadow: `0 ${w * 0.04}px ${w * 0.14}px rgba(0,0,0,0.55), 0 0 ${w * 0.5 * glow}px rgba(60,120,255,${0.55 * glow}), inset 0 0 0 ${w * 0.006}px rgba(255,255,255,0.35)`,
      }} />
      {/* boutons latéraux */}
      <div style={{ position: 'absolute', right: -w * 0.012, top: h * 0.2, width: w * 0.014, height: h * 0.09, borderRadius: 4, background: r2 }} />
      <div style={{ position: 'absolute', left: -w * 0.012, top: h * 0.17, width: w * 0.014, height: h * 0.05, borderRadius: 4, background: r2 }} />
      <div style={{ position: 'absolute', left: -w * 0.012, top: h * 0.24, width: w * 0.014, height: h * 0.07, borderRadius: 4, background: r2 }} />
      {/* lunette + écran */}
      <div style={{ position: 'absolute', inset: w * 0.012, borderRadius: r - w * 0.012, background: '#02040a' }} />
      <div style={{
        position: 'absolute', inset: bez + w * 0.01, borderRadius: sr, overflow: 'hidden',
        background: `radial-gradient(120% 70% at 30% 18%, ${c1} 0%, ${c2} 46%, ${c3} 100%)`,
      }}>
        {/* ondes du fond d'écran */}
        <div style={{ position: 'absolute', left: '-30%', right: '-30%', top: '48%', height: '70%', borderRadius: '50%', background: `radial-gradient(closest-side, rgba(255,255,255,0.18), rgba(255,255,255,0) 70%)`, transform: 'rotate(-12deg)' }} />
        <div style={{ position: 'absolute', left: '-20%', right: '-20%', top: '66%', height: '60%', borderRadius: '50%', border: `${w * 0.006}px solid rgba(255,255,255,0.16)`, transform: 'rotate(8deg)' }} />
        {/* heure */}
        <div style={{ position: 'absolute', top: '16%', width: '100%', textAlign: 'center', fontFamily: 'Montserrat, sans-serif', fontWeight: 700, color: 'rgba(255,255,255,0.95)', fontSize: w * 0.25, letterSpacing: -w * 0.01, lineHeight: 1 }}>{time}</div>
        <div style={{ position: 'absolute', top: '10.5%', width: '100%', textAlign: 'center', fontFamily: 'Montserrat, sans-serif', fontWeight: 600, color: 'rgba(255,255,255,0.7)', fontSize: w * 0.052 }}>Kinshasa</div>
        {/* barre de geste */}
        <div style={{ position: 'absolute', bottom: '2.2%', left: '34%', width: '32%', height: w * 0.014, borderRadius: 99, background: 'rgba(255,255,255,0.7)' }} />
        {/* reflet de verre */}
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(118deg, rgba(255,255,255,0.20) 0%, rgba(255,255,255,0.04) 28%, rgba(255,255,255,0) 40%)' }} />
        {/* découpe caméra */}
        {kind === 'iphone' && <div style={{ position: 'absolute', top: '1.7%', left: '50%', width: w * 0.3, height: w * 0.085, transform: 'translateX(-50%)', borderRadius: 99, background: '#000' }} />}
        {kind === 'galaxy' && <div style={{ position: 'absolute', top: '1.9%', left: '50%', width: w * 0.05, height: w * 0.05, transform: 'translateX(-50%)', borderRadius: 99, background: '#000', boxShadow: `0 0 0 ${w * 0.006}px #111` }} />}
        {kind === 'pixel' && <div style={{ position: 'absolute', top: '1.9%', left: '50%', width: w * 0.05, height: w * 0.05, transform: 'translateX(-50%)', borderRadius: 99, background: '#000', boxShadow: `0 0 0 ${w * 0.01}px #0a0a0a` }} />}
        {kind === 'tecno' && <div style={{ position: 'absolute', top: '1.7%', left: '50%', width: w * 0.12, height: w * 0.055, transform: 'translateX(-50%)', borderRadius: 99, background: '#000' }} />}
      </div>
      {/* liseré lumineux haut */}
      <div style={{ position: 'absolute', inset: 0, borderRadius: r, boxShadow: `inset 0 ${w * 0.006}px 0 rgba(255,255,255,0.45)`, pointerEvents: 'none' }} />
    </div>
  );
};
