import React from 'react';
import { Img, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { EASE, bezier, mix, ramp } from './Polish';
import { C } from './tokens';

export const clamp01 = (x: number) => Math.min(1, Math.max(0, x));
export const POP = bezier(0.2, 1.25, 0.3, 1);

export const LogoCard: React.FC<{ w: number; style?: React.CSSProperties }> = ({ w, style }) => (
  <div style={{ width: w, borderRadius: w * 0.07, background: '#fff', padding: w * 0.04, boxShadow: '0 30px 70px rgba(10,30,63,0.28)', border: '1.5px solid rgba(10,30,63,0.08)', ...style }}>
    <Img src={staticFile('img/hce-logo-transparent.png')} style={{ width: '100%', display: 'block' }} />
  </div>
);

export const Photo: React.FC<{ src: string; w: number; h: number; pos?: string; radius?: number; zoom?: [number, number]; frames: number; style?: React.CSSProperties }> = ({ src, w, h, pos = '50% 50%', radius = 36, zoom = [1, 1.08], frames, style }) => {
  const f = useCurrentFrame();
  const z = mix(zoom[0], zoom[1], ramp(f, 0, frames, EASE.swift));
  return (
    <div style={{ width: w, height: h, borderRadius: radius, overflow: 'hidden', position: 'relative', boxShadow: '0 30px 70px rgba(10,30,63,0.35)', border: `3px solid ${C.white}`, ...style }}>
      <Img src={staticFile(`img/${src}`)} style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: pos, transform: `scale(${z})` }} />
    </div>
  );
};

export const Tag: React.FC<{ children: React.ReactNode; bg?: string; color?: string; style?: React.CSSProperties }> = ({ children, bg = C.navy, color = C.white, style }) => (
  <div style={{ display: 'inline-block', fontFamily: 'Montserrat', fontWeight: 800, background: bg, color, borderRadius: 999, padding: '12px 32px', ...style }}>{children}</div>
);

export const MiseEnScene: React.FC<{ dark?: boolean }> = ({ dark }) => (
  <div style={{ position: 'absolute', left: 36, bottom: 48, fontFamily: 'Montserrat', fontWeight: 600, fontSize: 24, letterSpacing: 2, color: dark ? 'rgba(230,238,255,0.8)' : C.ink2, padding: '8px 18px', borderRadius: 99, background: dark ? 'rgba(5,12,30,0.55)' : 'rgba(255,255,255,0.8)', border: `1px solid ${dark ? 'rgba(160,190,255,0.3)' : 'rgba(10,30,63,0.2)'}` }}>
    Mise en scène
  </div>
);

// Icônes de services (mêmes que la publication)
export const ICONS: Record<string, React.ReactNode> = {
  reseau: <svg viewBox="0 0 48 48" fill="none" stroke={C.navy} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><rect x="17" y="6" width="14" height="10" rx="2" /><rect x="5" y="32" width="14" height="10" rx="2" /><rect x="29" y="32" width="14" height="10" rx="2" /><path d="M24 16v8M12 32v-8h24v8" /><circle cx="24" cy="11" r="1.6" fill={C.orange} stroke="none" /></svg>,
  cyber: <svg viewBox="0 0 48 48" fill="none" stroke={C.navy} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><path d="M24 5l15 6v11c0 10-6 17-15 21C15 39 9 32 9 22V11z" /><path d="M17 24l5 5 9-10" stroke={C.orange} /></svg>,
  virtu: <svg viewBox="0 0 48 48" fill="none" stroke={C.navy} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><rect x="7" y="7" width="34" height="12" rx="3" /><rect x="7" y="23" width="34" height="12" rx="3" /><path d="M24 35v6M16 41h16M33 13h6M33 29h6" /><circle cx="13" cy="13" r="1.6" fill={C.orange} stroke="none" /></svg>,
  cloud: <svg viewBox="0 0 48 48" fill="none" stroke={C.navy} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><path d="M14 36a9 9 0 1 1 2-17.7A11 11 0 0 1 37 21a7.5 7.5 0 0 1-1 15z" /><path d="M24 24v13M19 32l5 5 5-5" stroke={C.orange} /></svg>,
  cctv: <svg viewBox="0 0 48 48" fill="none" stroke={C.navy} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><path d="M6 17l22-8 4 9-22 8z" /><path d="M26 25l-4 14M18 39h12" /><circle cx="36" cy="16" r="2" fill={C.orange} stroke="none" /><path d="M38 24l6 3" /></svg>,
  dev: <svg viewBox="0 0 48 48" fill="none" stroke={C.navy} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><path d="M17 15L7 24l10 9M31 15l10 9-10 9" /><path d="M27 10l-6 28" stroke={C.orange} /></svg>,
};

// Fond clair HCE : blanc / gris très clair + circuits discrets
export const LightBg: React.FC = () => (
  <div style={{ position: 'absolute', inset: 0, background: `linear-gradient(165deg, #ffffff 0%, ${C.gray} 100%)`, overflow: 'hidden' }}>
    <svg width="1080" height="1920" viewBox="0 0 1080 1920" fill="none" stroke={C.blue} strokeWidth="1.6" opacity="0.14">
      <path d="M0 320h140l50 50h220l40-40h200" /><path d="M0 880h110l60-60h200" /><path d="M1080 600h-160l-50 50h-180" /><path d="M1080 1300h-120l-60-60h-220" /><path d="M0 1560h190l50 50h260" />
      {[[140, 320], [410, 370], [650, 330], [370, 820], [740, 650], [840, 1240], [240, 1610]].map(([x, y], i) => <circle key={i} cx={x} cy={y} r="6" />)}
    </svg>
    <div style={{ position: 'absolute', left: -200, top: 1500, width: 1500, height: 120, background: 'rgba(0,87,216,0.05)', transform: 'rotate(-12deg)' }} />
    <div style={{ position: 'absolute', left: -200, top: 1660, width: 1500, height: 40, background: 'rgba(255,122,0,0.10)', transform: 'rotate(-12deg)' }} />
  </div>
);

export const Foot: React.FC<{ p: number }> = ({ p }) => (
  <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, height: 236, background: C.navy, fontFamily: 'Montserrat', color: '#fff', padding: '36px 60px 0', opacity: p, transform: `translateY(${(1 - p) * 60}px)` }}>
    <div style={{ position: 'absolute', left: 0, right: 0, top: 0, height: 8, background: `linear-gradient(90deg, ${C.blue}, ${C.orange})` }} />
    <div style={{ fontWeight: 900, fontSize: 36 }}>HCE — <span style={{ color: C.orange }}>Hightech Computing Evolution</span></div>
    <div style={{ fontWeight: 600, fontSize: 25, lineHeight: 1.5, marginTop: 8 }}>Tél. : <b style={{ color: C.orange }}>+243 816 391 233</b> · <b style={{ color: C.orange }}>contact@hce.cd</b> · <b style={{ color: C.orange }}>support@hce.cd</b><br />107, rue de la Victoire · Kasa-Vubu · Imm. La CONCEM, 1er niveau — Réf. Rond-Point Kimpwanza, Kinshasa</div>
  </div>
);

// Pastille de lueur sur la frontière entre deux plans (balayage bleu)
export const Wipe: React.FC<{ at: number; color?: string }> = ({ at, color = C.blue }) => {
  const f = useCurrentFrame();
  const t = (f - (at - 4)) / 9;
  if (t < 0 || t > 1) return null;
  const x = t < 0.5 ? mix(-1200, 0, EASE.exit(t * 2)) : mix(0, 1200, EASE.out((t - 0.5) * 2));
  return <div style={{ position: 'absolute', top: 0, bottom: 0, left: -60, width: 1200, transform: `translateX(${x}px) skewX(-8deg)`, background: `linear-gradient(90deg, ${color}, ${C.orange})` }} />;
};
