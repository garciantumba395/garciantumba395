import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { POP, clamp01 } from '../ui';

// Plan 1 (16:9) — anecdote. Mêmes instants que la version verticale.
const NODES = [
  { n: 'Bureaux', x: 1000, y: 300 }, { n: 'Caisse', x: 1730, y: 300 }, { n: 'Serveur', x: 990, y: 660 },
  { n: 'Wi-Fi', x: 1740, y: 660 }, { n: 'Courriels', x: 1365, y: 170 }, { n: 'Sauvegarde', x: 1365, y: 830 },
];
const HUB = { x: 1365, y: 490 };
export const L1Incident: React.FC = () => {
  const f = useCurrentFrame();
  const down = T(3.44);
  const clock = ramp(f, T(0), 14, EASE.snappy);
  const flick = f >= down && f < down + 10 ? (f % 3 === 0 ? 0.4 : 1) : 1;
  const redAmt = ramp(f, down, 30, EASE.out);
  const card = (label: string, sub: string, at: number, y: number, warn = false) => {
    const p = POP(clamp01((f - at) / 12));
    return (
      <div style={{ position: 'absolute', left: 100, width: 780, top: y, opacity: f < at ? 0 : 1, transform: `translateX(${(1 - p) * -900}px)` }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 22, background: 'rgba(229,56,79,0.14)', border: `2px solid ${C.red}`, borderRadius: 22, padding: '18px 30px', boxShadow: '0 0 40px rgba(229,56,79,0.25)' }}>
          <div style={{ width: 20, height: 20, borderRadius: 99, background: C.red, boxShadow: `0 0 20px ${C.red}` }} />
          <div style={{ fontWeight: 800, fontSize: 42, color: '#fff' }}>{label}</div>
          <div style={{ marginLeft: 'auto', fontWeight: 700, fontSize: 32, color: warn ? C.orange : '#ff9aa8' }}>{sub}</div>
        </div>
      </div>
    );
  };
  const stamp = POP(clamp01((f - T(7.28)) / 10));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat', background: `radial-gradient(ellipse at 65% 45%, #13305f 0%, ${C.navy} 55%, #050d1f 100%)`, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', inset: 0, background: `radial-gradient(ellipse at 50% 50%, rgba(229,56,79,0) 40%, rgba(229,56,79,${0.35 * redAmt}) 100%)` }} />
      <div style={{ position: 'absolute', inset: 0, backgroundImage: 'repeating-linear-gradient(0deg, rgba(255,255,255,0.035) 0 2px, transparent 2px 6px)' }} />
      <div style={{ position: 'absolute', top: 70, left: 100, opacity: clock, transform: `translateX(${(1 - clock) * -40}px)` }}>
        <div style={{ fontWeight: 700, fontSize: 38, letterSpacing: 10, color: '#8fb0ee' }}>LUNDI</div>
        <div style={{ fontWeight: 900, fontSize: 230, letterSpacing: 4, lineHeight: 1, color: '#fff', fontFamily: '"DejaVu Sans Mono", monospace', textShadow: `0 0 40px rgba(80,140,255,${0.6 - 0.4 * redAmt})` }}>08:00</div>
      </div>
      <svg width="1920" height="1080" viewBox="0 0 1920 1080" style={{ position: 'absolute', inset: 0 }}>
        {NODES.map((nd, i) => {
          const appear = ramp(f, 40 + i * 4, 12, EASE.out);
          const dead = f >= down + i * 5;
          const col = dead ? C.red : '#4d8dff';
          return (
            <g key={nd.n} opacity={appear * (dead ? flick : 1)}>
              <line x1={HUB.x} y1={HUB.y} x2={nd.x} y2={nd.y} stroke={col} strokeWidth={5} strokeDasharray={dead ? '14 14' : undefined} style={{ filter: `drop-shadow(0 0 10px ${col})` }} />
              <circle cx={nd.x} cy={nd.y} r={42} fill="#0b1f45" stroke={col} strokeWidth={5} style={{ filter: `drop-shadow(0 0 16px ${col})` }} />
              {dead ? <path d={`M${nd.x - 15} ${nd.y - 15}l30 30M${nd.x + 15} ${nd.y - 15}l-30 30`} stroke={C.red} strokeWidth={7} strokeLinecap="round" /> : <circle cx={nd.x} cy={nd.y} r={12} fill="#9fc0ff" />}
              <text x={nd.x} y={nd.y + 78} textAnchor="middle" fontFamily="Montserrat" fontWeight={700} fontSize={30} fill={dead ? '#ff9aa8' : '#cfe0ff'}>{nd.n}</text>
            </g>
          );
        })}
        <g opacity={ramp(f, 36, 12, EASE.out)}>
          <circle cx={HUB.x} cy={HUB.y} r={64} fill="#0b1f45" stroke={redAmt > 0.5 ? C.red : '#4d8dff'} strokeWidth={6} style={{ filter: `drop-shadow(0 0 22px ${redAmt > 0.5 ? C.red : '#4d8dff'})` }} />
          <text x={HUB.x} y={HUB.y + 12} textAnchor="middle" fontFamily="Montserrat" fontWeight={900} fontSize={32} fill="#fff">RÉSEAU</text>
        </g>
      </svg>
      <div style={{ position: 'absolute', left: 1000, width: 730, top: 940, textAlign: 'center', opacity: f >= down + 8 ? flick : 0 }}>
        <div style={{ display: 'inline-block', background: C.red, color: '#fff', fontWeight: 900, fontSize: 48, letterSpacing: 4, padding: '8px 32px', borderRadius: 12, boxShadow: `0 0 40px ${C.red}` }}>RÉSEAU HORS LIGNE</div>
      </div>
      {card('Factures', 'erreur', T(4.44), 460)}
      {card('Courriels', 'hors ligne', T(5.36), 590)}
      {card('Sauvegarde', '?', T(6.4), 720, true)}
      <div style={{ position: 'absolute', left: 100, top: 850, opacity: f >= T(7.28) ? 1 : 0, transform: `scale(${mix(2.2, 1, stamp)}) rotate(-4deg)`, transformOrigin: 'left center' }}>
        <div style={{ display: 'inline-block', border: `8px solid ${C.orange}`, color: C.orange, fontWeight: 900, fontSize: 74, letterSpacing: 3, padding: '4px 36px', borderRadius: 16, background: 'rgba(10,30,63,0.9)' }}>JAMAIS TESTÉE</div>
      </div>
    </div>
  );
};
