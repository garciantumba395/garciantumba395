import React from 'react';
import { useCurrentFrame } from 'remotion';
import { Phone } from '../Phone';
import { fmtPrice } from '../Card';
import { BrandTag } from '../util';
import { IPHONES } from '../data';
import { EASE, bezier, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 4 (468–660 i) « iPhone XR dès 183 $. iPhone 12 dès 252. Jusqu'à l'iPhone 18 Pro Max. »
// Montée en gamme : trois cartes entrent depuis la droite au moment où la voix cite chaque modèle ; prix à l'énoncé.
const enter = bezier(0.2, 1.1, 0.3, 1);
const ROWS = [{ at: 6, price: 34 }, { at: 74, price: 104 }, { at: 146, price: 160 }];
export const S4Gamme: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <BrandTag label="iPhone" sub="du plus accessible au plus puissant" p={ramp(f, 0, 16, EASE.out)} />
      {IPHONES.map((it, i) => {
        const e = enter(Math.min(1, Math.max(0, (f - ROWS[i].at) / 16)));
        const pp = ramp(f, ROWS[i].price, 10, EASE.overshoot);
        const big = i === 2;
        const top = 400 + i * 420;
        return (
          <div key={it.name} style={{ position: 'absolute', left: 70, top, width: 940, height: 380, transform: `translateX(${(1 - e) * 1100}px)`, opacity: Math.min(1, e * 2) }}>
            <div style={{ position: 'absolute', inset: 0, borderRadius: 44, background: 'linear-gradient(165deg, rgba(24,45,92,0.97), rgba(8,18,40,0.99))', border: `2px solid ${big ? 'rgba(255,122,0,0.7)' : 'rgba(150,185,255,0.3)'}`, boxShadow: `0 30px 80px rgba(0,4,16,0.6), ${big ? '0 0 70px rgba(255,122,0,0.35),' : ''} inset 0 1.5px 0 rgba(255,255,255,0.12)` }} />
            <div style={{ position: 'absolute', left: 56, top: 28 }}><Phone kind="iphone" hue={it.hue} width={150} /></div>
            <div style={{ position: 'absolute', left: 280, right: 30, top: 70 }}>
              <div style={{ color: '#f2f6ff', fontWeight: 800, fontSize: big ? 62 : 70, letterSpacing: -1, lineHeight: 1.05 }}>{it.name}</div>
              <div style={{ marginTop: 22, display: 'flex', alignItems: 'baseline', gap: 14, opacity: Math.min(1, pp * 1.6), transform: `scale(${0.85 + 0.15 * pp})`, transformOrigin: 'left center' }}>
                {it.from && <span style={{ color: '#a9b8d8', fontWeight: 600, fontSize: 36 }}>dès</span>}
                <span style={{ color: GNS.orange, fontWeight: 900, fontSize: big ? 100 : 108, letterSpacing: -2 }}>{fmtPrice(it.price)}</span>
              </div>
            </div>
          </div>
        );
      })}
      <div style={{ position: 'absolute', bottom: 130, left: 0, right: 0, textAlign: 'center', color: '#6f84b3', fontWeight: 600, fontSize: 26 }}>Prix indicatifs à Kinshasa, en dollars US</div>
    </div>
  );
};
