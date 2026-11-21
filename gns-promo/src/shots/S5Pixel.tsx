import React from 'react';
import { useCurrentFrame } from 'remotion';
import { Phone } from '../Phone';
import { fmtPrice } from '../Card';
import { BrandTag, clamp01 } from '../util';
import { EASE, mix, ramp } from '../Polish';
import { PIXEL } from '../data';
import { GNS } from '../tokens';

// Plan 5 — spotlight-sweep-moves / slide-spotlight-pan (adapté portrait) : un projecteur glisse de gauche à droite,
// chaque téléphone « se réveille » à son passage (le réveil reste, pas de retour au sommeil), faisceau volumétrique
// depuis le haut ; en fin de plan la lumière s'élargit sur les trois.
const XS = [190, 540, 890];
const ARRIVE = [29, 59, 88]; // temps 2, 4, 6
const PW = 280, PH = Math.round(PW * 2.08), CY = 940;

export const S5Pixel: React.FC = () => {
  const f = useCurrentFrame();
  // abscisse du projecteur : pauses sur chaque téléphone, glissement lissé entre
  const pts = [{ t: 6, x: -80 }, { t: ARRIVE[0], x: XS[0] }, { t: ARRIVE[0] + 12, x: XS[0] }, { t: ARRIVE[1], x: XS[1] }, { t: ARRIVE[1] + 12, x: XS[1] }, { t: ARRIVE[2], x: XS[2] }, { t: ARRIVE[2] + 14, x: XS[2] }];
  let px = pts[0].x;
  for (let i = 0; i < pts.length - 1; i++) {
    if (f >= pts[i].t && f <= pts[i + 1].t) px = mix(pts[i].x, pts[i + 1].x, EASE.smooth(clamp01((f - pts[i].t) / (pts[i + 1].t - pts[i].t))));
  }
  if (f > pts[pts.length - 1].t) px = pts[pts.length - 1].x;
  const wide = ramp(f, 100, 24, EASE.smooth);
  const poolOn = clamp01((f - 4) / 8) * (1 - wide);
  const tag = ramp(f, 4, 18, EASE.out);
  return (
    <div style={{ position: 'absolute', inset: 0 }}>
      <BrandTag label="Google Pixel" p={tag} />
      {/* faisceau + flaque */}
      <div style={{ position: 'absolute', inset: 0, opacity: poolOn, mixBlendMode: 'screen', pointerEvents: 'none' }}>
        <div style={{ position: 'absolute', left: 0, top: 0, width: 1080, height: 1920, clipPath: `polygon(${px - 40}px 0, ${px + 40}px 0, ${px + 300}px ${CY + 420}px, ${px - 300}px ${CY + 420}px)`, background: 'linear-gradient(180deg, rgba(120,165,255,0.34), rgba(120,165,255,0.0) 85%)', filter: 'blur(16px)' }} />
        <div style={{ position: 'absolute', left: px - 330, top: CY + 330, width: 660, height: 190, borderRadius: '50%', background: 'radial-gradient(closest-side, rgba(90,140,255,0.55), rgba(90,140,255,0))' }} />
      </div>
      {PIXEL.map((it, k) => {
        const wake = Math.max(ramp(f, ARRIVE[k] - 4, 14, EASE.out), wide);
        const pop = ramp(f, ARRIVE[k] + 4, 10, EASE.overshoot);
        const lift = 24 * wake;
        return (
          <div key={it.name} style={{ position: 'absolute', left: XS[k] - PW / 2, top: CY - PH / 2 - lift, textAlign: 'center' }}>
            <div style={{ filter: `brightness(${0.1 + 0.9 * wake}) saturate(${0.5 + 0.5 * wake})` }}>
              <Phone kind="pixel" hue={it.hue} width={PW} glow={0.35 * wake} />
            </div>
            <div style={{ position: 'absolute', top: PH + 56 + lift, left: -50, right: -50, fontFamily: 'Montserrat', opacity: Math.min(1, wake * 1.4) }}>
              <div style={{ color: '#f2f6ff', fontWeight: 800, fontSize: 46, letterSpacing: -0.5, lineHeight: 1.1 }}>{it.name}</div>
              <div style={{ marginTop: 10, color: GNS.orange, fontWeight: 900, fontSize: 64, letterSpacing: -1.5, opacity: Math.min(1, pop * 1.6), transform: `scale(${0.8 + 0.2 * pop})`, filter: pop < 1 ? `blur(${(1 - pop) * 6}px)` : undefined }}>{fmtPrice(it.price)}</div>
            </div>
          </div>
        );
      })}
    </div>
  );
};
