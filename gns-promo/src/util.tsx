import React, { useEffect, useState } from 'react';
import { continueRender, delayRender, useCurrentFrame, useVideoConfig } from 'remotion';
import { EASE } from './Polish';

export const clamp01 = (x: number) => Math.min(1, Math.max(0, x));

// Largeurs de texte mesurées une fois les polices chargées (canvas) — rendu déterministe.
export const useTextWidths = (font: string, texts: string[]) => {
  const [widths, setWidths] = useState<number[] | null>(null);
  const [handle] = useState(() => delayRender('mesure texte'));
  useEffect(() => {
    let alive = true;
    (document as any).fonts.load(font, 'Mesure').then(() => (document as any).fonts.ready).then(() => {
      const c = document.createElement('canvas').getContext('2d')!;
      c.font = font;
      if (alive) setWidths(texts.map((t) => c.measureText(t).width));
      continueRender(handle);
    });
    return () => { alive = false; };
  }, []);
  return widths;
};

// Coque de plan : fin de plan en « crash » (poussée + flou + atténuation), jamais de coupe sèche.
export const Shot: React.FC<{ children: React.ReactNode; crash?: number; crashScale?: number }> = ({ children, crash = 10, crashScale = 1.12 }) => {
  const f = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const t = clamp01((f - (durationInFrames - crash)) / crash);
  const e = EASE.exit(t);
  return (
    <div style={{
      position: 'absolute', inset: 0,
      transform: `scale(${1 + (crashScale - 1) * e})`,
      filter: e > 0.01 ? `blur(${(e * 9).toFixed(2)}px)` : undefined,
      opacity: 1 - e * 0.65,
    }}>{children}</div>
  );
};

// Étiquette de marque (haut d'écran)
export const BrandTag: React.FC<{ label: string; p: number; sub?: string }> = ({ label, p, sub }) => (
  <div style={{
    position: 'absolute', top: 150, left: 0, right: 0, textAlign: 'center', fontFamily: 'Montserrat, sans-serif',
    opacity: p, transform: `translateY(${(1 - p) * 24}px)`, filter: `blur(${(1 - p) * 8}px)`,
  }}>
    <div style={{ display: 'inline-flex', alignItems: 'center', gap: 16, padding: '14px 34px', borderRadius: 99, background: 'rgba(29,92,255,0.18)', border: '1.5px solid rgba(120,165,255,0.45)', color: '#eaf1ff', fontWeight: 800, fontSize: 44, letterSpacing: 6, textTransform: 'uppercase' }}>
      {label}
    </div>
    {sub && <div style={{ marginTop: 18, color: '#a9b8d8', fontWeight: 600, fontSize: 32 }}>{sub}</div>}
  </div>
);
