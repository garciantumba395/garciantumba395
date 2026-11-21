import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { LightBg, Photo, POP, clamp01 } from '../ui';

// Plan 5 (860–1105 i) — formations : « Et avec HCE Academy, vos équipes se forment : réseaux, cybersécurité, Odoo et Sage, développement web, support IT. Cent pour cent pratique. »  Local = i − 860.
const S = 860;
const PROG = [
  { t: 'Réseaux & systèmes', at: T(33.84) }, { t: 'Cybersécurité', at: T(34.46) }, { t: 'Odoo & Sage', at: T(35.61) },
  { t: 'Développement web', at: T(36.71) }, { t: 'Support IT', at: T(37.65) },
];
export const S5Academy: React.FC = () => {
  const f = useCurrentFrame();
  const head = POP(clamp01((f - (T(31.01) - S)) / 16));
  const photo = ramp(f, T(31.5) - S, 20, EASE.out);
  const sub = ramp(f, T(32.57) - S, 14, EASE.snappy);
  const pct = POP(clamp01((f - (T(38.62) - S)) / 12));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', top: 110, left: 0, right: 0, textAlign: 'center', opacity: Math.min(1, head * 1.5), transform: `scale(${0.85 + 0.15 * head})` }}>
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 22, background: C.blue, color: '#fff', fontWeight: 900, fontSize: 84, letterSpacing: -2, padding: '20px 56px', borderRadius: 30, boxShadow: `0 14px 0 ${C.orange}` }}>
          HCE <span style={{ fontWeight: 600, letterSpacing: 0 }}>Academy</span>
        </div>
      </div>
      <div style={{ position: 'absolute', left: 60, top: 330, opacity: Math.min(1, photo * 1.5), transform: `translateY(${(1 - photo) * 60}px)` }}>
        <Photo src="hce-training-workshop.png" w={960} h={440} pos="50% 40%" frames={245} />
      </div>
      <div style={{ position: 'absolute', top: 800, left: 0, right: 0, textAlign: 'center', fontWeight: 800, fontSize: 52, color: C.navy, opacity: sub, transform: `translateY(${(1 - sub) * 16}px)` }}>Vos équipes se forment</div>
      <div style={{ position: 'absolute', left: 60, right: 60, top: 900, display: 'flex', flexDirection: 'column', gap: 26 }}>
        {PROG.map((p, i) => {
          const q = POP(clamp01((f - (p.at - S)) / 12));
          return (
            <div key={p.t} style={{ display: 'flex', alignItems: 'center', gap: 24, background: '#fff', borderRadius: 26, padding: '26px 32px', borderLeft: `9px solid ${i % 2 ? C.orange : C.blue}`, boxShadow: '0 14px 36px rgba(10,30,63,0.1)', opacity: f < p.at - S ? 0 : 1, transform: `translateX(${(1 - q) * 700}px)` }}>
              <div style={{ width: 54, height: 54, borderRadius: 99, background: C.blue, color: '#fff', fontWeight: 900, fontSize: 30, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{i + 1}</div>
              <div style={{ fontWeight: 800, fontSize: 48, color: C.navy }}>{p.t}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1790, textAlign: 'center', opacity: f >= T(38.62) - S ? 1 : 0, transform: `scale(${mix(1.8, 1, pct)}) rotate(-3deg)` }}>
        <div style={{ display: 'inline-block', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 66, letterSpacing: 1, padding: '10px 44px', borderRadius: 18, boxShadow: '0 12px 0 rgba(10,30,63,0.2)' }}>100 % PRATIQUE</div>
      </div>
    </div>
  );
};
