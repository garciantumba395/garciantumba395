import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { LogoCard, NAVY, ORANGE, SLAM, Sticker, WHITE, clamp01 } from '../pop';

// Plan 2 (114–331 i) « Du premier décembre au quinze janvier, GNS Business RDC applique moins cinq pour cent
// sur chaque achat effectué sur sa boutique en ligne. »  Local = i − 114.
// « premier décembre » f118 (loc 4) · « quinze janvier » f142 (28) · 46 jours · logo f171 (57) · -5 % f223 (109) · « chaque achat » f257 (143) · « en ligne » f293 (179).
const dayCell = (n: number | null, hl: boolean, p: number) => (
  <div style={{ width: 112, height: 70, display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative' }}>
    {n !== null && hl && <div style={{ position: 'absolute', width: 64 * p, height: 64 * p, borderRadius: 99, background: ORANGE }} />}
    {n !== null && <span style={{ position: 'relative', fontWeight: 800, fontSize: 36, color: hl && p > 0.5 ? '#fff' : NAVY }}>{n}</span>}
  </div>
);
const Month: React.FC<{ title: string; days: number; offset: number; hl: number; p: number; y: number; appear: number; rot: number }> = ({ title, days, offset, hl, p, y, appear, rot }) => {
  const cells: (number | null)[] = [...Array(offset).fill(null), ...Array.from({ length: days }, (_, i) => i + 1)];
  while (cells.length % 7) cells.push(null);
  return (
    <div style={{ position: 'absolute', left: 100, top: y + (1 - appear) * -500, width: 880, borderRadius: 34, background: WHITE, overflow: 'hidden', transform: `rotate(${rot}deg)`, boxShadow: '0 16px 0 rgba(7,21,47,0.25), 0 40px 80px rgba(0,10,60,0.5)', opacity: Math.min(1, appear * 3) }}>
      <div style={{ background: NAVY, color: '#fff', fontWeight: 900, fontSize: 48, letterSpacing: 4, padding: '14px 0 12px', textAlign: 'center' }}>{title}</div>
      <div style={{ display: 'flex', flexWrap: 'wrap', padding: '6px 48px 10px' }}>
        {['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((d, i) => <div key={i} style={{ width: 112, textAlign: 'center', fontWeight: 700, fontSize: 24, color: '#6f84b3', padding: '8px 0' }}>{d}</div>)}
        {cells.map((n, i) => <React.Fragment key={i}>{dayCell(n, n === hl, p)}</React.Fragment>)}
      </div>
    </div>
  );
};
export const B2Periode: React.FC = () => {
  const f = useCurrentFrame();
  const dec = SLAM(clamp01((f - 2) / 14)), jan = SLAM(clamp01((f - 26) / 14));
  const decHl = ramp(f, 8, 10, EASE.overshoot), janHl = ramp(f, 36, 10, EASE.overshoot);
  const days = Math.round(mix(0, 46, ramp(f, 34, 22, EASE.out)));
  const badge = ramp(f, 34, 14, EASE.overshoot);
  const logo = ramp(f, 57, 16, EASE.overshoot), slide = ramp(f, 109, 14, EASE.smooth);
  const stamp = SLAM(clamp01((f - 109) / 12));
  const chaque = ramp(f, 143, 14, EASE.snappy), enligne = ramp(f, 179, 14, EASE.overshoot);
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <Month title="DÉCEMBRE 2026" days={31} offset={1} hl={1} p={decHl} y={120} appear={dec} rot={-1.5} />
      <Month title="JANVIER 2027" days={31} offset={4} hl={15} p={janHl} y={620} appear={jan} rot={1.2} />
      <div style={{ position: 'absolute', right: 40, top: 560, opacity: Math.min(1, badge * 1.6), transform: `scale(${0.7 + 0.3 * badge}) rotate(6deg)` }}>
        <Sticker bg={ORANGE} color={NAVY} style={{ fontSize: 56, padding: '12px 34px' }}>{days} JOURS</Sticker>
      </div>
      <div style={{ position: 'absolute', left: mix(330, 60, slide), top: 1150, opacity: Math.min(1, logo * 1.5), transform: `scale(${(0.8 + 0.2 * logo) * mix(1, 0.72, slide)})`, transformOrigin: 'left top' }}>
        <LogoCard w={420} />
      </div>
      <div style={{ position: 'absolute', left: 600, top: 1120, width: 400, height: 400, opacity: f >= 109 ? 1 : 0, transform: `scale(${mix(2.2, 1, stamp)}) rotate(-8deg)` }}>
        <div style={{ width: 400, height: 400, borderRadius: 999, background: ORANGE, border: `10px solid ${WHITE}`, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 16px 0 rgba(7,21,47,0.3), 0 40px 80px rgba(0,10,60,0.5)' }}>
          <span style={{ fontWeight: 900, fontSize: 170, color: WHITE, letterSpacing: -9, textShadow: `8px 8px 0 ${NAVY}` }}>-5%</span>
        </div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1530, textAlign: 'center', overflow: 'hidden', height: 90 }}><div style={{ fontWeight: 900, fontSize: 74, lineHeight: '90px', color: WHITE, letterSpacing: -2, whiteSpace: 'nowrap', transform: `translateY(${(1 - chaque) * 110}%)` }}>SUR CHAQUE ACHAT</div></div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1640, textAlign: 'center', opacity: Math.min(1, enligne * 1.6), transform: `scale(${0.7 + 0.3 * enligne})` }}><Sticker bg={NAVY} color={WHITE} style={{ fontSize: 44, padding: '10px 34px', borderRadius: 18, whiteSpace: 'nowrap' }}>EN LIGNE UNIQUEMENT</Sticker></div>
    </div>
  );
};
