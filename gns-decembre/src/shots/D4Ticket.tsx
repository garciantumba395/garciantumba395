import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { NAVY, ORANGE, SLAM, Sticker, WHITE, clamp01, eur } from '../pop';

// Plan 4 (465–640 i) « Un iPhone 12 à 252 $ ? 239,40 $. La réduction s'applique automatiquement. »  Local = i − 465.
// Ticket (thermique) : ligne iPhone 12 f466 (1) · ligne Réduction f531 (66) · TOTAL f560 (95) · tampon AUTOMATIQUE f615 (150) ; autres exemples f580–f625.
const MONO = '"DejaVu Sans Mono", "Courier New", monospace';
const EX = [{ n: 'Galaxy S23', p: 384, at: 112 }, { n: 'Pixel 8', p: 324, at: 126 }, { n: 'Spark 50', p: 174, at: 140 }];
export const D4Ticket: React.FC = () => {
  const f = useCurrentFrame();
  const slide = ramp(f, 0, 16, EASE.out);
  const l1 = ramp(f, 1, 10, EASE.snappy), l2 = ramp(f, 66, 10, EASE.snappy), tot = SLAM(clamp01((f - 95) / 12));
  const stamp = SLAM(clamp01((f - 150) / 10));
  const price = (n: number) => n.toFixed(2).replace('.', ',') + ' $';
  const row = (a: string, b: string, o: number, bold = false, color = NAVY) => (
    <div style={{ display: 'flex', justifyContent: 'space-between', fontFamily: MONO, fontWeight: bold ? 800 : 600, fontSize: 36, color, opacity: o, transform: `translateX(${(1 - o) * 40}px)`, margin: '10px 0' }}><span>{a}</span><span>{b}</span></div>
  );
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <div style={{ position: 'absolute', left: 150, top: 150 - (1 - slide) * 1100, width: 780, transform: 'rotate(-1.2deg) scale(1.2)', transformOrigin: '50% 0' }}>
        <div style={{ background: '#fbfaf3', padding: '44px 46px 50px', boxShadow: '0 16px 0 rgba(7,21,47,0.25), 0 40px 80px rgba(0,10,60,0.5)', position: 'relative' }}>
          <div style={{ textAlign: 'center', fontFamily: MONO, fontWeight: 800, fontSize: 38, color: NAVY, letterSpacing: 2 }}>GNS BUSINESS RDC</div>
          <div style={{ textAlign: 'center', fontFamily: MONO, fontSize: 24, color: '#5a6a8c', marginBottom: 22 }}>Boutique en ligne · Décembre 2026</div>
          <div style={{ borderTop: `3px dashed ${NAVY}`, margin: '8px 0 18px' }} />
          {row('iPhone 12', price(252), l1)}
          {row('Réduction -5 %', '-' + price(12.6), l2, true, ORANGE)}
          <div style={{ borderTop: `3px dashed ${NAVY}`, margin: '18px 0 14px' }} />
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', fontFamily: MONO, fontWeight: 800, fontSize: 52, color: NAVY, opacity: f >= 95 ? 1 : 0, transform: `scale(${mix(1.5, 1, tot)})`, transformOrigin: 'right center' }}><span>TOTAL</span><span>{price(239.4)}</span></div>
          <div style={{ marginTop: 24, height: 70, background: `repeating-linear-gradient(90deg, ${NAVY} 0 4px, transparent 4px 9px, ${NAVY} 9px 11px, transparent 11px 18px)`, opacity: 0.85 }} />
          <div style={{ position: 'absolute', left: 0, right: 0, bottom: -20, height: 22, background: 'linear-gradient(135deg, #fbfaf3 50%, transparent 50%) 0 0/28px 22px, linear-gradient(225deg, #fbfaf3 50%, transparent 50%) 0 0/28px 22px' }} />
          <div style={{ position: 'absolute', right: 0, bottom: -120, opacity: f >= 150 ? 1 : 0, transform: `rotate(-14deg) scale(${mix(2.2, 1, stamp)})` }}>
            <div style={{ border: `8px solid ${ORANGE}`, color: ORANGE, fontWeight: 900, fontSize: 54, letterSpacing: 2, padding: '8px 26px', borderRadius: 14, background: 'rgba(255,255,255,0.85)' }}>AUTOMATIQUE</div>
          </div>
        </div>
      </div>
      {/* autres exemples */}
      <div style={{ position: 'absolute', left: 100, right: 100, top: 1060 }}>
        <div style={{ color: WHITE, fontWeight: 800, fontSize: 36, letterSpacing: 3, opacity: ramp(f, 104, 10, EASE.out), marginBottom: 14 }}>AUSSI AVEC -5 %</div>
        {EX.map((e) => {
          const p = SLAM(clamp01((f - e.at) / 10));
          return (
            <div key={e.n} style={{ opacity: f < e.at ? 0 : 1, transform: `translateX(${(1 - p) * 900}px)`, marginBottom: 16 }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: WHITE, borderRadius: 24, padding: '14px 34px', boxShadow: '0 10px 0 rgba(7,21,47,0.25)' }}>
                <span style={{ fontWeight: 900, fontSize: 46, color: NAVY }}>{e.n}</span>
                <span style={{ display: 'flex', alignItems: 'baseline', gap: 18 }}>
                  <span style={{ fontWeight: 700, fontSize: 32, color: '#6f84b3', textDecoration: 'line-through' }}>{e.p} $</span>
                  <span style={{ fontWeight: 900, fontSize: 56, color: ORANGE }}>{price(e.p * 0.95)}</span>
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
