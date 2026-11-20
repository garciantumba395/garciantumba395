import React from 'react';
import { useCurrentFrame, useVideoConfig } from 'remotion';
import { EASE, ramp } from './Polish';
import { PHONE } from './data';

// Bandeau permanent des plans prix : contact + mention légale de prix (« indicatifs »).
export const Footer: React.FC = () => {
  const f = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const o = ramp(f, 0, 14, EASE.out) * (1 - ramp(f, durationInFrames - 10, 10, EASE.exit));
  return (
    <div style={{ position: 'absolute', left: 0, right: 0, bottom: 84, textAlign: 'center', fontFamily: 'Montserrat', opacity: o, transform: `translateY(${(1 - o) * 14}px)` }}>
      <div style={{ display: 'inline-flex', alignItems: 'center', gap: 22, padding: '16px 38px', borderRadius: 999, background: 'rgba(8,20,48,0.78)', border: '1.5px solid rgba(130,170,255,0.35)', color: '#f2f6ff', fontWeight: 800, fontSize: 36, letterSpacing: 1 }}>
        <span style={{ color: '#a9b8d8', fontWeight: 700, fontSize: 28, letterSpacing: 4 }}>GNS BUSINESS RDC</span>
        <span style={{ color: '#ff7a00' }}>{PHONE}</span>
      </div>
      <div style={{ marginTop: 16, color: '#6f84b3', fontWeight: 600, fontSize: 24 }}>Prix indicatifs à Kinshasa, en dollars US</div>
    </div>
  );
};
