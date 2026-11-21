import React from 'react';
import { useCurrentFrame } from 'remotion';
import { PriceCard, fmtPrice } from '../Card';
import { BrandTag, clamp01 } from '../util';
import { EASE, mix, ramp } from '../Polish';
import { TECNO } from '../data';
import { GNS } from '../tokens';

// Plan 6 — card-flip-reveal (adapté portrait) : quatre cartes « face téléphone » se retournent l'une après l'autre
// pour révéler le prix ; léger lever à mi-rotation, décalage entre cartes, puis maintien.
const CW = 456, CH = 600;
const CENTERS = [{ x: 292, y: 630 }, { x: 788, y: 630 }, { x: 292, y: 1266 }, { x: 788, y: 1266 }];
const FLIP0 = 29, GAP = 29.4, FLIPD = 26; // un retournement tous les 2 temps

export const S6Tecno: React.FC = () => {
  const f = useCurrentFrame();
  const tag = ramp(f, 2, 18, EASE.out);
  const drift = 1 + 0.025 * ramp(f, 120, 70, EASE.swift);
  return (
    <div style={{ position: 'absolute', inset: 0, transform: `scale(${drift})`, transformOrigin: '540px 960px' }}>
      <BrandTag label="TECNO" p={tag} />
      <div style={{ position: 'absolute', inset: 0, perspective: 2200 }}>
        {TECNO.map((it, k) => {
          const enter = ramp(f, 4 + k * 4, 16, EASE.out);
          const t = ramp(f, FLIP0 + k * GAP, FLIPD, EASE.smooth);
          const rot = 180 * t;
          const lift = Math.sin(t * Math.PI);
          const done = ramp(f, FLIP0 + k * GAP + FLIPD - 2, 10, EASE.overshoot);
          const flash = f > FLIP0 + k * GAP + FLIPD ? Math.max(0, 1 - (f - FLIP0 - k * GAP - FLIPD) / 14) : 0;
          return (
            <div key={it.name} style={{ position: 'absolute', left: CENTERS[k].x - CW / 2, top: CENTERS[k].y - CH / 2 + (1 - enter) * 120, width: CW, height: CH, opacity: enter, filter: enter < 1 ? `blur(${(1 - enter) * 8}px)` : undefined, transform: `translateZ(${lift * 90}px) rotateY(${rot}deg) scale(${1 + 0.05 * lift})`, transformStyle: 'preserve-3d' }}>
              <div style={{ position: 'absolute', inset: 0, backfaceVisibility: 'hidden' }}>
                <PriceCard item={it} w={CW} h={CH} showPrice={false} />
              </div>
              <div style={{ position: 'absolute', inset: 0, backfaceVisibility: 'hidden', transform: 'rotateY(180deg)', borderRadius: 40, overflow: 'hidden', fontFamily: 'Montserrat', background: 'linear-gradient(165deg, #1b3c86 0%, #0e2250 55%, #07142e 100%)', border: '1.5px solid rgba(150,185,255,0.3)', boxShadow: `0 40px 90px rgba(0,4,16,0.65), inset 0 1.5px 0 rgba(255,255,255,0.16), 0 0 ${70 * flash}px rgba(255,122,0,${0.5 * flash})`, textAlign: 'center' }}>
                <div style={{ position: 'absolute', top: 70, left: 0, right: 0, color: '#a9b8d8', fontWeight: 700, fontSize: 30, letterSpacing: 5, textTransform: 'uppercase' }}>TECNO</div>
                <div style={{ position: 'absolute', top: 120, left: 20, right: 20, color: '#f2f6ff', fontWeight: 800, fontSize: 50, letterSpacing: -0.5 }}>{it.name}</div>
                <div style={{ position: 'absolute', top: 270, left: 0, right: 0, opacity: Math.min(1, done * 1.5), transform: `scale(${0.85 + 0.15 * done})` }}>
                  {it.from && <div style={{ color: '#a9b8d8', fontWeight: 600, fontSize: 36 }}>dès</div>}
                  <div style={{ marginTop: it.from ? 0 : 36, color: GNS.orange, fontWeight: 900, fontSize: 112, letterSpacing: -3, lineHeight: 1.05 }}>{fmtPrice(it.price)}</div>
                </div>
                <div style={{ position: 'absolute', bottom: 56, left: 0, right: 0, color: '#7f93bf', fontWeight: 600, fontSize: 26 }}>Prix indicatif · Kinshasa</div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
