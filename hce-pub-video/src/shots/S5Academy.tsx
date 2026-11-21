import React from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { TechLogo } from '../logos';
import { LightBg, Photo, POP, clamp01 } from '../ui';

// Plan 5 (860–1105 i) — formations : « Et avec HCE Academy, vos équipes se forment : réseaux, cybersécurité, Odoo et Sage, développement web, support IT. Cent pour cent pratique. »  Local = i − 860.
const S = 911;
const PROG: { t: string; at: number; logos?: string[] }[] = [
  { t: 'Réseaux & systèmes', at: T(37.55) }, { t: 'Cybersécurité', at: T(38.16) }, { t: 'Odoo & Sage', at: T(39.4), logos: ['odoo.svg', 'sage.svg'] },
  { t: 'Développement web', at: T(40.48) }, { t: 'Support IT', at: T(41.52) },
];
export const S5Academy: React.FC = () => {
  const f = useCurrentFrame();
  const head = POP(clamp01((f - (T(34.76) - S)) / 16));
  const photo = ramp(f, T(35.2) - S, 20, EASE.out);
  const sub = ramp(f, T(36.16) - S, 14, EASE.snappy);
  const pct = POP(clamp01((f - (T(42.56) - S)) / 12));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', top: 70, left: 0, right: 0, display: 'flex', justifyContent: 'center', opacity: Math.min(1, head * 1.5), transform: `scale(${0.85 + 0.15 * head})` }}>
        <div style={{ width: 640, background: '#fff', borderRadius: 40, padding: '28px 36px', boxShadow: '0 30px 70px rgba(10,30,63,0.28)', border: '1.5px solid rgba(10,30,63,0.08)' }}>
          <Img src={staticFile('img/hce-academy-logo.png')} style={{ width: '100%', display: 'block' }} />
        </div>
      </div>
      <div style={{ position: 'absolute', left: 60, top: 470, opacity: Math.min(1, photo * 1.5), transform: `translateY(${(1 - photo) * 60}px)` }}>
        <Photo src="hce-training-workshop.png" w={960} h={380} pos="50% 40%" frames={245} />
      </div>
      <div style={{ position: 'absolute', top: 880, left: 0, right: 0, textAlign: 'center', fontWeight: 800, fontSize: 50, color: C.navy, opacity: sub, transform: `translateY(${(1 - sub) * 16}px)` }}>Vos équipes se forment</div>
      <div style={{ position: 'absolute', left: 60, right: 60, top: 970, display: 'flex', flexDirection: 'column', gap: 24 }}>
        {PROG.map((p, i) => {
          const q = POP(clamp01((f - (p.at - S)) / 12));
          return (
            <div key={p.t} style={{ display: 'flex', alignItems: 'center', gap: 24, background: '#fff', borderRadius: 26, padding: '28px 32px', borderLeft: `9px solid ${i % 2 ? C.orange : C.blue}`, boxShadow: '0 14px 36px rgba(10,30,63,0.1)', opacity: f < p.at - S ? 0 : 1, transform: `translateX(${(1 - q) * 700}px)` }}>
              <div style={{ width: 54, height: 54, borderRadius: 99, background: C.blue, color: '#fff', fontWeight: 900, fontSize: 30, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{i + 1}</div>
              <div style={{ fontWeight: 800, fontSize: 52, color: C.navy }}>{p.t}</div>
              {p.logos && <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 22 }}>{p.logos.map((l) => <TechLogo key={l} file={l} h={58} />)}</div>}
            </div>
          );
        })}
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1745, textAlign: 'center', opacity: f >= T(42.56) - S ? 1 : 0, transform: `scale(${mix(1.8, 1, pct)}) rotate(-3deg)` }}>
        <div style={{ display: 'inline-block', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 66, letterSpacing: 1, padding: '10px 44px', borderRadius: 18, boxShadow: '0 12px 0 rgba(10,30,63,0.2)' }}>100 % PRATIQUE</div>
      </div>
    </div>
  );
};
