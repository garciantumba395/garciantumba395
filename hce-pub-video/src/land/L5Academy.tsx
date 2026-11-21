import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBgL, Photo, POP, clamp01 } from '../ui';

// Plan 5 (16:9) — formations, avec le logo HCE Academy. Local = i − 860.
const S = 860;
const PROG = [
  { t: 'Réseaux & systèmes', at: T(33.84) }, { t: 'Cybersécurité', at: T(34.46) }, { t: 'Odoo & Sage', at: T(35.61) },
  { t: 'Développement web', at: T(36.71) }, { t: 'Support IT', at: T(37.65) },
];
export const L5Academy: React.FC = () => {
  const f = useCurrentFrame();
  const head = POP(clamp01((f - (T(31.01) - S)) / 16));
  const photo = ramp(f, T(31.5) - S, 20, EASE.out);
  const sub = ramp(f, T(32.57) - S, 14, EASE.snappy);
  const pct = POP(clamp01((f - (T(38.62) - S)) / 12));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBgL />
      <div style={{ position: 'absolute', left: 100, top: 50, opacity: Math.min(1, head * 1.5), transform: `scale(${0.85 + 0.15 * head})`, transformOrigin: 'left top' }}>
        <div style={{ width: 520, background: '#fff', borderRadius: 34, padding: '22px 30px', boxShadow: '0 30px 70px rgba(10,30,63,0.28)', border: '1.5px solid rgba(10,30,63,0.08)' }}>
          <Img src={staticFile('img/hce-academy-logo.png')} style={{ width: '100%', display: 'block' }} />
        </div>
      </div>
      <div style={{ position: 'absolute', left: 660, top: 130, fontWeight: 800, fontSize: 52, lineHeight: 1.1, color: C.navy, width: 300, opacity: sub, transform: `translateY(${(1 - sub) * 16}px)` }}>Vos équipes se forment</div>
      <div style={{ position: 'absolute', left: 100, top: 400, width: 860, display: 'flex', flexDirection: 'column', gap: 16 }}>
        {PROG.map((p, i) => {
          const q = POP(clamp01((f - (p.at - S)) / 12));
          return (
            <div key={p.t} style={{ display: 'flex', alignItems: 'center', gap: 22, background: '#fff', borderRadius: 24, padding: '18px 30px', borderLeft: `9px solid ${i % 2 ? C.orange : C.blue}`, boxShadow: '0 14px 36px rgba(10,30,63,0.1)', opacity: f < p.at - S ? 0 : 1, transform: `translateX(${(1 - q) * -700}px)` }}>
              <div style={{ width: 52, height: 52, borderRadius: 99, background: C.blue, color: '#fff', fontWeight: 900, fontSize: 28, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{i + 1}</div>
              <div style={{ fontWeight: 800, fontSize: 48, color: C.navy }}>{p.t}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: 'absolute', left: 1020, top: 70, opacity: Math.min(1, photo * 1.5), transform: `translateX(${(1 - photo) * 80}px)` }}>
        <Photo src="hce-training-workshop.png" w={800} h={720} pos="52% 40%" frames={245} />
      </div>
      <div style={{ position: 'absolute', left: 1020, width: 800, top: 880, textAlign: 'center', opacity: f >= T(38.62) - S ? 1 : 0, transform: `scale(${mix(1.8, 1, pct)}) rotate(-3deg)` }}>
        <div style={{ display: 'inline-block', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 70, letterSpacing: 1, padding: '10px 48px', borderRadius: 18, boxShadow: '0 12px 0 rgba(10,30,63,0.2)' }}>100 % PRATIQUE</div>
      </div>
    </div>
  );
};
