import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { ORANGE, SLAM, WHITE, NAVY, Sticker } from '../pop';

// Plan 1 (0–114 i) « Et si votre prochain smartphone coûtait cinq pour cent de moins ? »
// Mots qui montent à l'énoncé (Et f15, si f22, votre f26, prochain f37, smartphone f49, coûtait f61) ; « -5 % » claque à « cinq pour cent » (f73) ; « de moins ? » f92.
const WORDS = [['ET SI VOTRE', 15], ['PROCHAIN', 37], ['SMARTPHONE', 49], ['COÛTAIT', 61]] as const;
export const A1Accroche: React.FC = () => {
  const f = useCurrentFrame();
  const slam = SLAM(Math.min(1, Math.max(0, (f - 73) / 12)));
  const shake = f >= 73 && f < 83 ? Math.sin(f * 3.1) * (83 - f) * 1.4 : 0;
  const moins = ramp(f, 92, 12, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <div style={{ position: 'absolute', left: 70, top: 170 }}>
        {WORDS.map(([w, at], i) => {
          const p = ramp(f, at, 12, EASE.snappy);
          return <div key={w} style={{ overflow: 'hidden', height: 112 }}><div style={{ fontWeight: 900, fontSize: 104, lineHeight: '112px', color: i === 1 ? WHITE : WHITE, letterSpacing: -3, transform: `translateY(${(1 - p) * 120}%)` }}>{w}</div></div>;
        })}
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 640, textAlign: 'center', opacity: f >= 73 ? 1 : 0, transform: `translate(${shake}px, ${-shake * 0.5}px) scale(${mix(2.4, 1, slam)})`, transformOrigin: '50% 50%' }}>
        <div style={{ fontWeight: 900, fontSize: 560, lineHeight: 1, letterSpacing: -26, color: WHITE, textShadow: `18px 18px 0 ${ORANGE}, 0 40px 80px rgba(0,10,60,0.5)` }}>-5%</div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1380, textAlign: 'center', opacity: Math.min(1, moins * 1.6), transform: `scale(${0.8 + 0.2 * moins})` }}>
        <Sticker rot={-3} style={{ fontSize: 110, padding: '18px 54px' }}>DE MOINS ?</Sticker>
      </div>
    </div>
  );
};
