import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { NAVY, ORANGE, SLAM, Sticker, WHITE, clamp01 } from '../pop';

// Plan 3 (331–465 i) « iPhone, Samsung, Google Pixel, TECNO : les quatre grandes marques. »  Local = i − 331.
// iPhone f332 (1) · Samsung f346 (15) · Google Pixel f369 (38) · TECNO f393 (62) · « les quatre grandes marques » f422 (91).
const BRANDS = [{ n: 'iPHONE', at: 1, rot: -3 }, { n: 'SAMSUNG', at: 15, rot: 2.5 }, { n: 'GOOGLE PIXEL', at: 38, rot: -2 }, { n: 'TECNO', at: 62, rot: 3 }];
export const C3Marques: React.FC = () => {
  const f = useCurrentFrame();
  const ban = ramp(f, 91, 14, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      {BRANDS.map((b, i) => {
        const s = SLAM(clamp01((f - b.at) / 11));
        const side = i % 2 === 0 ? -1 : 1;
        return (
          <div key={b.n} style={{ position: 'absolute', left: 0, right: 0, top: 170 + i * 300, textAlign: 'center', transform: `translateX(${(1 - s) * side * 1200}px)`, opacity: f < b.at ? 0 : 1 }}>
            <Sticker rot={b.rot} style={{ fontSize: 110, letterSpacing: -2, padding: '20px 60px', position: 'relative' }}>
              {b.n}
              <span style={{ position: 'absolute', right: -70, top: -50, transform: 'rotate(12deg)', background: ORANGE, color: WHITE, borderRadius: 999, fontSize: 52, padding: '12px 26px', border: `6px solid ${WHITE}`, boxShadow: '0 8px 0 rgba(7,21,47,0.3)', textShadow: `3px 3px 0 ${NAVY}` }}>-5%</span>
            </Sticker>
          </div>
        );
      })}
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1450, textAlign: 'center', opacity: Math.min(1, ban * 1.6), transform: `scale(${0.8 + 0.2 * ban})` }}>
        <div style={{ display: 'inline-block', background: NAVY, color: WHITE, fontWeight: 900, fontSize: 62, letterSpacing: 1, padding: '18px 48px', borderRadius: 20, transform: 'rotate(-1.5deg)', boxShadow: '0 12px 0 rgba(255,122,0,0.9)' }}>LES 4 GRANDES MARQUES</div>
      </div>
    </div>
  );
};
