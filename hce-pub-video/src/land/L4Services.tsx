import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, ramp } from '../Polish';
import { C, T } from '../tokens';
import { ICONS, LightBgL, POP, clamp01 } from '../ui';
import { BrandTile, CCTV } from '../logos';

// Plan 4 (16:9) — services + sécurité électronique en vedette.  Local = i − 556.
const S = 556;
const SERV = [
  { k: 'reseau', t: 'Réseaux', at: T(20.88), d: 'LAN, WAN, Wi-Fi, VPN' },
  { k: 'cyber', t: 'Cybersécurité', at: T(21.52), d: 'Audit et protection' },
  { k: 'virtu', t: 'Virtualisation', at: T(22.8), d: 'Serveurs et stockage' },
  { k: 'cloud', t: 'Cloud', at: T(23.96), d: 'Hébergement, VM, sauvegardes' },
  { k: 'dev', t: 'Développement web', at: T(24.48), d: 'Sites et applications' },
];
export const L4Services: React.FC = () => {
  const f = useCurrentFrame();
  const head = ramp(f, 2, 14, EASE.snappy);
  const sec = POP(clamp01((f - (T(25.48) - S)) / 16));
  const c1 = POP(clamp01((f - (T(27.07) - S)) / 12)), c2 = POP(clamp01((f - (T(28.2) - S)) / 12));
  const lab = ramp(f, T(29.16) - S, 12, EASE.out);
  const one = POP(clamp01((f - (T(33.24) - S)) / 14));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBgL />
      <div style={{ position: 'absolute', top: 40, left: 100, width: 920, opacity: head, transform: `translateY(${(1 - head) * -20}px)` }}>
        <div style={{ fontWeight: 800, fontSize: 28, letterSpacing: 8, color: C.orange }}>NOS SERVICES</div>
        <div style={{ fontWeight: 900, fontSize: 56, letterSpacing: -2, color: C.navy, lineHeight: 1.05, marginTop: 4 }}>Une expertise <span style={{ color: C.blue }}>de bout en bout</span></div>
      </div>
      <div style={{ position: 'absolute', left: 100, top: 190, width: 920, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
        {SERV.map((s, i) => {
          const p = POP(clamp01((f - (s.at - S)) / 12));
          return (
            <div key={s.k} style={{ gridColumn: i === 4 ? '1 / span 2' : undefined, height: 200, background: '#fff', borderRadius: 24, padding: '18px 22px', borderLeft: `8px solid ${i % 3 === 2 ? C.orange : C.blue}`, boxShadow: '0 14px 34px rgba(10,30,63,0.12)', opacity: f < s.at - S ? 0 : 1, transform: `translateY(${(1 - p) * 40}px) scale(${0.92 + 0.08 * p})` }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                <div style={{ width: 58, height: 58, flex: '0 0 58px' }}>{ICONS[s.k]}</div>
                <div style={{ fontWeight: 900, fontSize: 34, color: C.navy, lineHeight: 1.05, textTransform: 'uppercase' }}>{s.t}</div>
              </div>
              <div style={{ fontWeight: 500, fontSize: 30, color: C.ink2, marginTop: 16 }}>{s.d}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: 'absolute', left: 1060, top: 60, width: 760, height: 930, borderRadius: 38, background: `linear-gradient(160deg, ${C.navy} 0%, #123a78 100%)`, boxShadow: '0 30px 80px rgba(10,30,63,0.4)', borderLeft: `12px solid ${C.orange}`, padding: '32px 38px', opacity: f < T(25.48) - S ? 0 : 1, transform: `translateX(${(1 - sec) * 160}px) scale(${0.95 + 0.05 * sec})` }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 22 }}>
          <div style={{ width: 96, height: 96, borderRadius: 24, background: '#fff', padding: 15 }}>{ICONS.cctv}</div>
          <div style={{ fontWeight: 900, fontSize: 56, color: '#fff', lineHeight: 1.02, textTransform: 'uppercase' }}>Sécurité<br />électronique</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, marginTop: 30, alignItems: 'flex-start' }}>
          <div style={{ opacity: f < T(27.07) - S ? 0 : 1, transform: `scale(${0.8 + 0.2 * c1})`, transformOrigin: 'left center', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 40, padding: '12px 28px', borderRadius: 999 }}>VIDÉOSURVEILLANCE</div>
          <div style={{ opacity: f < T(28.2) - S ? 0 : 1, transform: `scale(${0.8 + 0.2 * c2})`, transformOrigin: 'left center', background: '#fff', color: C.navy, fontWeight: 900, fontSize: 40, padding: '12px 28px', borderRadius: 999 }}>CONTRÔLE D’ACCÈS</div>
        </div>
        <div style={{ marginTop: 34, fontWeight: 700, fontSize: 28, letterSpacing: 5, color: '#9fc0ff', opacity: lab }}>TECHNOLOGIES MAÎTRISÉES</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18, marginTop: 16 }}>
          {CCTV.map((b, i) => {
            const at = [T(30.08), T(30.8), T(31.56), T(32.32)][i] - S;
            const p = POP(clamp01((f - at) / 12));
            return <div key={b.key} style={{ opacity: f < at ? 0 : 1, transform: `scale(${0.8 + 0.2 * p})` }}><BrandTile name={b.name} file={b.file} w={335} h={185} fs={32} /></div>;
          })}
        </div>
      </div>
      <div style={{ position: 'absolute', left: 100, width: 920, top: 940, textAlign: 'center', opacity: Math.min(1, one * 1.5), transform: `scale(${0.85 + 0.15 * one})` }}>
        <div style={{ display: 'inline-block', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 52, letterSpacing: 1, padding: '14px 56px', borderRadius: 999, boxShadow: '0 10px 0 rgba(10,30,63,0.25)' }}>UN SEUL PARTENAIRE</div>
      </div>
    </div>
  );
};
