import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 6 (756–855 i) « Vous n'êtes pas à Kinshasa ? Nous expédions partout en RDC. »
// Réseau de villes (positions schématiques, pas un fond de carte) : lignes qui partent de Kinshasa à « Nous expédions » (f38).
const HUB = { x: 230, y: 1180 };
const CITIES = [
  { n: 'Kisangani', x: 640, y: 820, d: 40 }, { n: 'Goma', x: 880, y: 960, d: 46 }, { n: 'Bukavu', x: 860, y: 1130, d: 52 },
  { n: 'Mbuji-Mayi', x: 620, y: 1290, d: 58 }, { n: 'Lubumbashi', x: 800, y: 1500, d: 64 },
];
const path = (c: { x: number; y: number }) => `M${HUB.x} ${HUB.y} Q${(HUB.x + c.x) / 2} ${Math.min(HUB.y, c.y) - 140} ${c.x} ${c.y}`;
export const S6Rdc: React.FC = () => {
  const f = useCurrentFrame();
  const q = ramp(f, 0, 14, EASE.out), hub = ramp(f, 14, 14, EASE.overshoot), title = ramp(f, 60, 16, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <svg width="1080" height="1920" viewBox="0 0 1080 1920" style={{ position: 'absolute', inset: 0 }}>
        {Array.from({ length: 22 }).map((_, r) => Array.from({ length: 12 }).map((__, c) => <circle key={`${r}-${c}`} cx={60 + c * 90} cy={560 + r * 62} r={2.4} fill="rgba(150,185,255,0.18)" />))}
        {CITIES.map((c) => {
          const p = ramp(f, c.d, 22, EASE.smooth);
          return <path key={c.n} d={path(c)} fill="none" stroke="#5b8cff" strokeWidth={5} strokeLinecap="round" pathLength={1} strokeDasharray={`${p} 1`} style={{ filter: 'drop-shadow(0 0 12px rgba(80,140,255,0.9))' }} />;
        })}
        {CITIES.map((c) => {
          const a = ramp(f, c.d + 18, 10, EASE.overshoot);
          return (
            <g key={c.n + 'd'} opacity={Math.min(1, a * 1.5)}>
              <circle cx={c.x} cy={c.y} r={22 * a} fill={GNS.orange} /><circle cx={c.x} cy={c.y} r={40 * a} fill="none" stroke="rgba(255,122,0,0.5)" strokeWidth={4} />
              <text x={c.x} y={c.y - 46} textAnchor="middle" fontFamily="Montserrat" fontWeight={800} fontSize={38} fill="#f2f6ff">{c.n}</text>
            </g>
          );
        })}
        <g opacity={Math.min(1, hub * 1.5)}>
          <circle cx={HUB.x} cy={HUB.y} r={34 * hub} fill="#1d5cff" stroke="#fff" strokeWidth={5} />
          <text x={HUB.x} y={HUB.y + 90} textAnchor="middle" fontFamily="Montserrat" fontWeight={900} fontSize={46} fill="#f2f6ff">Kinshasa</text>
        </g>
      </svg>
      <div style={{ position: 'absolute', top: 210, left: 0, right: 0, textAlign: 'center', fontWeight: 800, fontSize: 64, color: '#f2f6ff', opacity: q, transform: `translateY(${(1 - q) * 20}px)` }}>Vous n’êtes pas à Kinshasa ?</div>
      <div style={{ position: 'absolute', top: 360, left: 0, right: 0, textAlign: 'center', opacity: Math.min(1, title * 1.5), transform: `scale(${0.85 + 0.15 * title})` }}>
        <span style={{ display: 'inline-block', padding: '18px 54px', borderRadius: 999, background: GNS.orange, color: '#0b1220', fontWeight: 900, fontSize: 84, letterSpacing: -2, boxShadow: '0 0 70px rgba(255,122,0,0.5)' }}>Partout en RDC</span>
      </div>
    </div>
  );
};
