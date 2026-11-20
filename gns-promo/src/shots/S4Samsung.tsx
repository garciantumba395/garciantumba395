import React from 'react';
import { useCurrentFrame } from 'remotion';
import { Phone } from '../Phone';
import { fmtPrice } from '../Card';
import { BrandTag, clamp01 } from '../util';
import { EASE, bezier, mix, ramp } from '../Polish';
import { SAMSUNG } from '../data';
import { GNS } from '../tokens';

// Plan 4 — neon-frame-orbit-drop (adapté portrait) : le cadre néon se trace (~14f), puis la vue tourne en arc
// (rotateY +38° → −26°) pendant que TOUS les éléments descendent EN MÊME TEMPS (zéro décalage = commande de la fiche),
// ombres douces qui convergent ; ensuite la vue se redresse pour la lecture (ajout : 128→160f).
const PW = 940, PH = 1500, PX = 70, PY = 230;
const dropE = bezier(0.3, 0, 0.25, 1.08);
const LAND0 = 44, LAND1 = 88, ROT0 = 8, ROT1 = 128; // chute de 3 temps, fin sur le temps 6

export const S4Samsung: React.FC = () => {
  const f = useCurrentFrame();
  const frame = ramp(f, 0, 14, EASE.snappy);
  const arc = mix(38, -26, ramp(f, ROT0, ROT1 - ROT0, EASE.smooth));
  const straight = ramp(f, ROT1, 32, EASE.swift);
  const rotY = arc * (1 - straight) + 0 * straight;
  const drop = dropE(clamp01((f - LAND0) / (LAND1 - LAND0)));
  const height = 170 * (1 - drop); // px au-dessus du plan
  const shadow = 1 - drop;
  const appear = ramp(f, 10, 14, EASE.out);
  const priceP = ramp(f, LAND1 - 2, 10, EASE.overshoot);
  const tag = ramp(f, 14, 16, EASE.out);
  return (
    <div style={{ position: 'absolute', inset: 0, perspective: 1700, perspectiveOrigin: `${mix(30, 64, ramp(f, ROT0, ROT1 - ROT0, EASE.smooth))}% 50%` }}>
      <div style={{ position: 'absolute', left: PX, top: PY, width: PW, height: PH, transform: `rotateY(${rotY}deg)`, transformStyle: 'preserve-3d' }}>
        {/* cadre néon */}
        <svg width={PW} height={PH} style={{ position: 'absolute', inset: 0, overflow: 'visible' }}>
          <defs>
            <linearGradient id="neon" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#5b8cff" /><stop offset="0.6" stopColor="#9fc0ff" /><stop offset="1" stopColor="#ff7a00" />
            </linearGradient>
          </defs>
          <rect x={3} y={3} width={PW - 6} height={PH - 6} rx={60} fill="rgba(13,28,59,0.55)" opacity={ramp(f, 8, 16, EASE.out)} />
          <rect x={3} y={3} width={PW - 6} height={PH - 6} rx={60} fill="none" stroke="url(#neon)" strokeWidth={6} pathLength={1} strokeDasharray={`${frame} 1`} style={{ filter: 'drop-shadow(0 0 18px rgba(80,140,255,0.95)) drop-shadow(0 0 40px rgba(60,110,255,0.6))' }} />
        </svg>
        <div style={{ position: 'absolute', top: 40, left: 0, right: 0, textAlign: 'center', opacity: tag }}>
          <div style={{ display: 'inline-block', padding: '12px 32px', borderRadius: 99, background: 'rgba(29,92,255,0.2)', border: '1.5px solid rgba(120,165,255,0.5)', color: '#eaf1ff', fontWeight: 800, fontSize: 44, letterSpacing: 6, textTransform: 'uppercase', fontFamily: 'Montserrat' }}>Samsung Galaxy</div>
        </div>
        {SAMSUNG.map((it, k) => {
          const top = 160 + k * 440;
          return (
            <div key={it.name} style={{ position: 'absolute', left: 36, right: 36, top, height: 400, opacity: appear, transform: `translateZ(${height}px)` }}>
              <div style={{ position: 'absolute', inset: 0, borderRadius: 44, background: 'linear-gradient(165deg, rgba(24,45,92,0.97), rgba(8,18,40,0.99))', border: '1.5px solid rgba(150,185,255,0.25)', boxShadow: `0 ${20 + 60 * shadow}px ${40 + 80 * shadow}px rgba(0,4,16,${0.45 + 0.25 * shadow}), inset 0 1.5px 0 rgba(255,255,255,0.12)` }} />
              <div style={{ position: 'absolute', left: 60, top: 36 }}><Phone kind="galaxy" hue={it.hue} width={156} /></div>
              <div style={{ position: 'absolute', left: 300, right: 30, top: 96, fontFamily: 'Montserrat' }}>
                <div style={{ color: '#f2f6ff', fontWeight: 800, fontSize: 60, letterSpacing: -1, lineHeight: 1.05 }}>{it.name}</div>
                <div style={{ marginTop: 26, display: 'flex', alignItems: 'baseline', gap: 14, opacity: Math.min(1, priceP * 1.6), transform: `scale(${0.85 + 0.15 * priceP})`, transformOrigin: 'left center' }}>
                  {it.from && <span style={{ color: '#a9b8d8', fontWeight: 600, fontSize: 34 }}>dès</span>}
                  <span style={{ color: GNS.orange, fontWeight: 900, fontSize: 96, letterSpacing: -2 }}>{fmtPrice(it.price)}</span>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
