import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, ramp } from '../Polish';
import { C, T, T_PV } from '../tokens';
import { ICONS, LightBg, POP, clamp01 } from '../ui';
import { BrandTile, CCTV } from '../logos';

// Plan 4 (556–911 i) — services + sécurité électronique en vedette.  Local = i − 556.
// Réseaux 20,88 · Cyber 21,52 · Virtu 22,8 · Cloud 23,96 · Dév web 24,48 · Sécurité électronique 25,48 · vidéosurveillance 27,07 · contrôle d'accès 28,2
// Axis 30,08 · Hikvision 30,8 · Dahua 31,56 · Wisenet 32,32 · « Un seul partenaire » 33,24.
const S = 538;
const SERV = [
  { k: 'reseau', t: 'Réseaux', at: T(20.88), d: 'LAN, WAN, Wi-Fi, VPN' },
  { k: 'cyber', t: 'Cybersécurité', at: T(21.52), d: 'Audit et protection' },
  { k: 'virtu', t: 'Virtualisation', at: T(22.8), d: 'Serveurs et stockage' },
  { k: 'cloud', t: 'Cloud', at: T(23.96), d: 'Hébergement, VM, sauvegardes' },
  { k: 'dev', t: 'Développement web', at: T(24.48), d: 'Sites et applications' },
  { k: 'pv', t: 'Photovoltaïque', at: T_PV, d: 'Énergie solaire' },
];
export const S4Services: React.FC = () => {
  const f = useCurrentFrame();
  const head = ramp(f, 2, 14, EASE.snappy);
  const sec = POP(clamp01((f - (T(25.48) - S)) / 16));
  const c1 = POP(clamp01((f - (T(27.07) - S)) / 12)), c2 = POP(clamp01((f - (T(28.2) - S)) / 12));
  const lab = ramp(f, T(29.16) - S, 12, EASE.out);
  const one = POP(clamp01((f - (T(33.24) - S)) / 14));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', top: 90, left: 0, right: 0, textAlign: 'center', opacity: head, transform: `translateY(${(1 - head) * -20}px)` }}>
        <div style={{ fontWeight: 800, fontSize: 32, letterSpacing: 8, color: C.orange }}>NOS SERVICES</div>
        <div style={{ fontWeight: 900, fontSize: 58, letterSpacing: -1.5, color: C.navy, lineHeight: 1.05, marginTop: 6, whiteSpace: 'nowrap' }}>Une expertise <span style={{ color: C.blue }}>de bout en bout</span></div>
      </div>
      <div style={{ position: 'absolute', left: 50, right: 50, top: 290, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
        {SERV.map((s, i) => {
          const p = POP(clamp01((f - (s.at - S)) / 12));
          return (
            <div key={s.k} style={{ height: 215, background: '#fff', borderRadius: 26, padding: '20px 24px', borderLeft: `9px solid ${i % 3 === 2 ? C.orange : C.blue}`, boxShadow: '0 14px 34px rgba(10,30,63,0.12)', opacity: f < s.at - S ? 0 : 1, transform: `translateY(${(1 - p) * 40}px) scale(${0.92 + 0.08 * p})` }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                <div style={{ width: 64, height: 64, flex: '0 0 64px' }}>{ICONS[s.k]}</div>
                <div style={{ fontWeight: 900, fontSize: 38, color: C.navy, lineHeight: 1.05, textTransform: 'uppercase' }}>{s.t}</div>
              </div>
              <div style={{ fontWeight: 500, fontSize: 30, color: C.ink2, marginTop: 16 }}>{s.d}</div>
            </div>
          );
        })}
      </div>
      {/* sécurité électronique en vedette */}
      <div style={{ position: 'absolute', left: 50, right: 50, top: 1030, height: 700, borderRadius: 40, background: `linear-gradient(160deg, ${C.navy} 0%, #123a78 100%)`, boxShadow: '0 30px 80px rgba(10,30,63,0.4)', borderLeft: `12px solid ${C.orange}`, padding: '34px 40px', opacity: f < T(25.48) - S ? 0 : 1, transform: `translateY(${(1 - sec) * 120}px) scale(${0.95 + 0.05 * sec})` }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 22 }}>
          <div style={{ width: 90, height: 90, borderRadius: 24, background: '#fff', padding: 14 }}>{ICONS.cctv}</div>
          <div style={{ fontWeight: 900, fontSize: 56, color: '#fff', lineHeight: 1.02, textTransform: 'uppercase' }}>Sécurité<br />électronique</div>
        </div>
        <div style={{ display: 'flex', gap: 16, marginTop: 30 }}>
          <div style={{ opacity: f < T(27.07) - S ? 0 : 1, transform: `scale(${0.8 + 0.2 * c1})`, background: C.orange, color: '#fff', fontWeight: 900, fontSize: 31, padding: '14px 24px', borderRadius: 999, whiteSpace: 'nowrap' }}>VIDÉOSURVEILLANCE</div>
          <div style={{ opacity: f < T(28.2) - S ? 0 : 1, transform: `scale(${0.8 + 0.2 * c2})`, background: '#fff', color: C.navy, fontWeight: 900, fontSize: 31, padding: '14px 24px', borderRadius: 999, whiteSpace: 'nowrap' }}>CONTRÔLE D’ACCÈS</div>
        </div>
        <div style={{ marginTop: 34, fontWeight: 700, fontSize: 30, letterSpacing: 5, color: '#9fc0ff', opacity: lab }}>TECHNOLOGIES MAÎTRISÉES</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18, marginTop: 16 }}>
          {CCTV.map((b, i) => {
            const at = [T(30.08), T(30.8), T(31.56), T(32.32)][i] - S;
            const p = POP(clamp01((f - at) / 12));
            return <div key={b.key} style={{ opacity: f < at ? 0 : 1, transform: `scale(${0.8 + 0.2 * p})` }}><BrandTile name={b.name} file={b.file} w={440} h={160} /></div>;
          })}
        </div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1770, textAlign: 'center', opacity: Math.min(1, one * 1.5), transform: `scale(${0.85 + 0.15 * one})` }}>
        <div style={{ display: 'inline-block', background: C.orange, color: '#fff', fontWeight: 900, fontSize: 52, letterSpacing: 1, padding: '14px 52px', borderRadius: 999, boxShadow: '0 10px 0 rgba(10,30,63,0.25)' }}>UN SEUL PARTENAIRE</div>
      </div>
    </div>
  );
};
