import React from 'react';
import { useCurrentFrame } from 'remotion';
import { useTextWidths, clamp01 } from '../util';
import { EASE, mix, ramp } from '../Polish';
import { CITIES } from '../data';
import { GNS } from '../tokens';

// Plan 7 — pill-slot-cycle (adapté portrait) : « On expédie à [ville] » ; la pastille change de mot (roulement vertical + flou)
// et épouse la largeur du mot ; chaque mot 21f (valeur de la fiche) ; la dernière valeur « toute la RDC » se fige en orange.
const FS = 74;
const WORDS = [...CITIES.slice(0, 4), 'toute la RDC'];
const START = 15, EACH = 22;

export const S7Deliver: React.FC = () => {
  const f = useCurrentFrame();
  const widths = useTextWidths(`800 ${FS}px Montserrat`, WORDS);
  const idx = Math.min(WORDS.length - 1, Math.max(0, Math.floor((f - START) / EACH)));
  const local = (f - START) - idx * EACH;
  const prev = Math.max(0, idx - 1);
  const swap = f >= START + EACH ? EASE.smooth(clamp01(local / 9)) : 1;
  const w = widths ? (idx === 0 ? widths[0] : mix(widths[prev], widths[idx], swap)) + 80 : 400;
  const enter = (a: number, d = 16) => ramp(f, a, d, EASE.out);
  const l1 = enter(0), l2 = enter(14), pillIn = enter(START - 8, 12);
  const final = idx === WORDS.length - 1;
  const l3 = enter(START + EACH * 4 + 10, 16);
  const finalPop = final ? ramp(f, START + EACH * 4 + 2, 10, EASE.overshoot) : 0;
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat', textAlign: 'center' }}>
      <div style={{ position: 'absolute', top: 560, left: 0, right: 0, fontWeight: 900, fontSize: 132, color: '#f2f6ff', letterSpacing: -4, opacity: l1, transform: `translateY(${(1 - l1) * 60}px)`, filter: `blur(${(1 - l1) * 10}px)` }}>Commandez.</div>
      <div style={{ position: 'absolute', top: 760, left: 0, right: 0, fontWeight: 700, fontSize: FS, color: '#a9b8d8', letterSpacing: -1, opacity: l2, transform: `translateY(${(1 - l2) * 40}px)` }}>On expédie à</div>
      <div style={{ position: 'absolute', top: 880, left: 540 - w / 2, width: w, height: FS * 1.7, borderRadius: 999, overflow: 'hidden', opacity: pillIn,
        background: final ? GNS.orange : 'linear-gradient(160deg, rgba(40,100,255,0.55), rgba(20,60,170,0.55))',
        border: final ? '2px solid rgba(255,255,255,0.5)' : '2px solid rgba(130,170,255,0.6)',
        boxShadow: final ? `0 0 ${60 * (1 - finalPop * 0.4)}px rgba(255,122,0,0.55)` : '0 0 50px rgba(40,100,255,0.35)', transform: `scale(${final ? 1 + 0.05 * Math.sin(finalPop * Math.PI) : 1})` }}>
        {WORDS.map((word, i) => {
          const rel = i - idx;
          const t = rel === 0 ? swap : 0;
          let y = 0, o = 0, b = 0;
          if (i === idx) { y = idx === 0 && f < START + 2 ? (1 - clamp01((f - START + 6) / 8)) * 60 : (1 - swap) * 70; o = idx === 0 ? clamp01((f - START + 6) / 8) : swap; b = (1 - swap) * 8; }
          else if (i === idx - 1) { y = -swap * 70; o = 1 - swap; b = swap * 8; }
          return <div key={word} style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: FS, letterSpacing: -1.5, color: final ? '#07152f' : '#f2f6ff', opacity: o, transform: `translateY(${y}px)`, filter: b > 0.2 ? `blur(${b}px)` : undefined, whiteSpace: 'nowrap' }}>{word}</div>;
        })}
      </div>
      <div style={{ position: 'absolute', top: 1110, left: 0, right: 0, fontWeight: 600, fontSize: 46, lineHeight: 1.35, color: '#a9b8d8', opacity: l3, transform: `translateY(${(1 - l3) * 24}px)` }}>
        Où que vous soyez en RDC,<br />nous expédions votre colis.
      </div>
    </div>
  );
};
