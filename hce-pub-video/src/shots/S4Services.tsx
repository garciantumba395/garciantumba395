import React from 'react';
import { useCurrentFrame } from 'remotion';
import { EASE, mix, ramp } from '../Polish';
import { C, T } from '../tokens';
import { ICONS, LightBg, LogoCard, POP, clamp01 } from '../ui';

// Plan 4 (620–860 i) — services : « Réseaux, cybersécurité, virtualisation, cloud, sécurité électronique, développement web : un seul partenaire… »  Local = i − 620.
const S = 620;
const SERV = [
  { k: 'reseau', t: 'Réseaux', d: 'LAN, WAN, Wi-Fi, VPN', at: T(22.30) }, { k: 'cyber', t: 'Cybersécurité', d: 'Audit et protection', at: T(22.94) },
  { k: 'virtu', t: 'Virtualisation', d: 'Serveurs et stockage', at: T(23.9) }, { k: 'cloud', t: 'Cloud', d: 'Hébergement, VM, sauvegardes', at: T(24.95) },
  { k: 'cctv', t: 'Sécurité électronique', d: 'Vidéo, accès, alarmes', at: T(25.73) }, { k: 'dev', t: 'Développement web', d: 'Sites et applications', at: T(27.02) },
];
export const S4Services: React.FC = () => {
  const f = useCurrentFrame();
  const head = ramp(f, 2, 14, EASE.snappy);
  const one = POP(clamp01((f - (T(28.10) - S)) / 14));
  return (
    <div style={{ position: 'absolute', inset: 0, fontFamily: 'Montserrat' }}>
      <LightBg />
      <div style={{ position: 'absolute', top: 130, left: 0, right: 0, textAlign: 'center', opacity: head, transform: `translateY(${(1 - head) * -20}px)` }}>
        <div style={{ fontWeight: 800, fontSize: 36, letterSpacing: 8, color: C.orange }}>NOS SERVICES</div>
        <div style={{ fontWeight: 900, fontSize: 84, letterSpacing: -2, color: C.navy, lineHeight: 1.05, marginTop: 8 }}>Une expertise<br /><span style={{ color: C.blue }}>de bout en bout</span></div>
      </div>
      <div style={{ position: 'absolute', left: 50, right: 50, top: 470, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 22 }}>
        {SERV.map((s, i) => {
          const p = POP(clamp01((f - (s.at - S)) / 12));
          const hl = f >= T(28.10) - S;
          return (
            <div key={s.k} style={{ height: 270, background: '#fff', borderRadius: 30, padding: '26px 26px', borderLeft: `9px solid ${i % 3 === 2 ? C.orange : C.blue}`, boxShadow: `0 16px 40px rgba(10,30,63,${hl ? 0.2 : 0.1})`, opacity: f < s.at - S ? 0 : 1, transform: `translateY(${(1 - p) * 50}px) scale(${0.92 + 0.08 * p})` }}>
              <div style={{ width: 92, height: 92 }}>{ICONS[s.k]}</div>
              <div style={{ fontWeight: 900, fontSize: 40, color: C.navy, marginTop: 12, lineHeight: 1.08, textTransform: 'uppercase', letterSpacing: 0.3 }}>{s.t}</div>
              <div style={{ fontWeight: 500, fontSize: 28, color: C.ink2, marginTop: 6 }}>{s.d}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1530, textAlign: 'center', opacity: Math.min(1, one * 1.5), transform: `scale(${0.85 + 0.15 * one})` }}>
        <div style={{ display: 'inline-block', background: C.navy, color: '#fff', fontWeight: 900, fontSize: 60, letterSpacing: 1, padding: '24px 56px', borderRadius: 999, boxShadow: `0 14px 0 ${C.orange}` }}>UN SEUL PARTENAIRE</div>
      </div>
    </div>
  );
};
