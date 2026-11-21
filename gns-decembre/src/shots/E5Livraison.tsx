import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { NAVY, ORANGE, SLAM, Sticker, WHITE, clamp01 } from '../pop';

// Plan 5 (640–703 i) « Et nous expédions partout en RDC. »  Local = i − 640. Colis qui file à travers l'écran ; « PARTOUT EN RDC » claque à f662 (22).
export const E5Livraison: React.FC = () => {
  const f = useCurrentFrame();
  const a = ramp(f, 0, 12, EASE.snappy), b = SLAM(clamp01((f - 22) / 12));
  const x = mix(-300, 1380, ramp(f, 2, 50, EASE.smooth));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <div style={{ position: 'absolute', left: 70, top: 380, overflow: 'hidden', height: 130, whiteSpace: 'nowrap' }}><div style={{ fontWeight: 900, fontSize: 104, lineHeight: '130px', color: WHITE, letterSpacing: -3, whiteSpace: 'nowrap', transform: `translateY(${(1 - a) * 120}%)` }}>NOUS EXPÉDIONS</div></div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 700, textAlign: 'center', opacity: f >= 22 ? 1 : 0, transform: `scale(${mix(2, 1, b)}) rotate(-3deg)` }}>
        <Sticker bg={ORANGE} color={WHITE} style={{ fontSize: 150, letterSpacing: -4, padding: '30px 60px', textShadow: `7px 7px 0 ${NAVY}` }}>PARTOUT<br />EN RDC</Sticker>
      </div>
      <div style={{ position: 'absolute', left: x, top: 1250 }}>
        <svg width="260" height="230" viewBox="0 0 26 23"><path d="M2 7l11-5 11 5v11l-11 5-11-5z" fill="#d9a35b" stroke={NAVY} strokeWidth="0.8" /><path d="M2 7l11 5 11-5M13 12v11" fill="none" stroke={NAVY} strokeWidth="0.8" /><path d="M7.5 4.6l11 5" stroke="#fff" strokeWidth="1.2" /></svg>
        {[0, 1, 2].map((i) => <div key={i} style={{ position: 'absolute', left: -240 - i * 70, top: 60 + i * 38, width: 200 - i * 40, height: 10, borderRadius: 8, background: 'rgba(255,255,255,0.7)' }} />)}
      </div>
    </div>
  );
};
