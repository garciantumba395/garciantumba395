import React from 'react';
import { useCurrentFrame } from 'remotion';
import { Photo } from './common';
import { EASE, bezier, mix, ramp } from '../Polish';
import { GNS } from '../tokens';

// Plan 2 (152–266 i) « Trois boutiques, trois prix, et toujours : « repassez demain ». »
// Photo 04 ; trois étiquettes de prix (valeurs volontairement masquées « ??? ») tombent et se barrent ; bulle « Repassez demain » (≈ f78).
const drop = bezier(0.3, 0, 0.25, 1.15);
const TAGS = [{ x: 90, y: 1120, r: -9, at: 5 }, { x: 410, y: 1260, r: 6, at: 22 }, { x: 690, y: 1100, r: -4, at: 36 }];
export const S2Prix: React.FC = () => {
  const f = useCurrentFrame();
  const bub = ramp(f, 76, 14, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <Photo src="p04.jpg" frames={114} focus={[0.5, 0.35]} push={[1.02, 1.12]} />
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg, rgba(3,10,26,0.2) 0%, rgba(3,10,26,0) 35%, rgba(3,10,26,0.88) 85%)' }} />
      {TAGS.map((t, i) => {
        const d = drop(Math.min(1, Math.max(0, (f - t.at) / 12)));
        const strike = ramp(f, t.at + 14, 10, EASE.out);
        return (
          <div key={i} style={{ position: 'absolute', left: t.x, top: mix(t.y - 520, t.y, d), transform: `rotate(${t.r}deg)`, opacity: f < t.at ? 0 : 1 }}>
            <div style={{ width: 300, height: 150, borderRadius: 26, background: 'linear-gradient(160deg,#ffffff,#dfe8ff)', boxShadow: '0 30px 60px rgba(0,4,16,0.6)', display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative' }}>
              <span style={{ fontWeight: 900, fontSize: 78, color: '#0b1730', letterSpacing: -2 }}>??? $</span>
              <div style={{ position: 'absolute', left: 14, top: 74, height: 8, width: `${strike * 272}px`, background: '#e7000b', borderRadius: 8, transform: 'rotate(-6deg)', transformOrigin: 'left' }} />
            </div>
          </div>
        );
      })}
      <div style={{ position: 'absolute', top: 1480, left: 0, right: 0, textAlign: 'center', opacity: bub, transform: `scale(${0.85 + 0.15 * bub})` }}>
        <div style={{ display: 'inline-block', position: 'relative', padding: '26px 54px', borderRadius: 44, background: '#ffffff', color: '#0b1730', fontWeight: 900, fontSize: 66, letterSpacing: -1.5, boxShadow: '0 30px 70px rgba(0,4,16,0.6)' }}>
          « Repassez demain »
          <div style={{ position: 'absolute', left: 120, top: -28, width: 0, height: 0, borderLeft: '26px solid transparent', borderRight: '26px solid transparent', borderBottom: '34px solid #ffffff' }} />
        </div>
      </div>
      <div style={{ position: 'absolute', top: 190, left: 0, right: 0, textAlign: 'center', color: '#f2f6ff', fontWeight: 800, fontSize: 56, letterSpacing: 1, opacity: ramp(f, 2, 14, EASE.out), textShadow: '0 4px 30px rgba(0,0,0,0.7)' }}>
        <span style={{ color: GNS.orange }}>3</span> boutiques · <span style={{ color: GNS.orange }}>3</span> prix
      </div>
    </div>
  );
};
