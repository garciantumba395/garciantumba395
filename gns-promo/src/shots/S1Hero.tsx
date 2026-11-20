import React from 'react';
import { useCurrentFrame } from 'remotion';
import { Phone } from '../Phone';
import { EASE, bezier, mix, ramp } from '../Polish';
import { clamp01 } from '../util';
import { GNS } from '../tokens';

// Plan 1 — spotlight-hero-card (adapté portrait) : projecteur qui cherche puis verrouille un seul héros,
// montée + lévitation, deux tours de faisceau de contour, retour posé. Temps de la fiche : lock f34, rise 10f,
// bob 4px/période 40f, tour 1 f68→82, tour 2 f88→108, reseat 18f.
const LOCK = 29, RISE = 10, BOB_END = 103, LAP1 = [63, 14], LAP2 = [83, 20], RESEAT = 18; // verrouillage sur le temps 2
const riseEase = bezier(0.2, 1.25, 0.3, 1);
const PW = 400, PH = Math.round(PW * 2.08), CX = 540, CY = 1010;

export const S1Hero: React.FC = () => {
  const f = useCurrentFrame();
  // projecteur : 3 étapes intermédiaires (4→28f) puis verrouillage sur le héros
  const stops = [{ x: 220, y: 1300 }, { x: 860, y: 1180 }, { x: 330, y: 820 }, { x: CX, y: CY }];
  const seg = (f: number) => {
    const times = [0, 8, 16, LOCK - 4]; // 3 tronçons : 3 étapes de recherche puis verrouillage sur le héros
    for (let i = 0; i < 3; i++) {
      if (f <= times[i + 1]) {
        const e = EASE.smooth(clamp01((f - times[i] - 2) / (times[i + 1] - times[i] - 2)));
        return { x: mix(stops[i].x, stops[i + 1].x, e), y: mix(stops[i].y, stops[i + 1].y, e) };
      }
    }
    return stops[3];
  };
  const pool = seg(f);
  const lockPulse = f >= LOCK - 4 && f < LOCK + 6 ? Math.sin(((f - (LOCK - 4)) / 10) * Math.PI) * 0.06 : 0;
  const lit = clamp01((f - 2) / 6);
  const radius = mix(520, 330, ramp(f, 19, 14, EASE.smooth));
  const lock = ramp(f, LOCK - 4, 10, EASE.smooth);

  // élévation
  const rise = riseEase(clamp01((f - LOCK) / RISE));
  const reseat = EASE.overshoot(clamp01((f - BOB_END) / RESEAT));
  const hover = f < BOB_END ? rise : 1 - reseat + 0; // 1 = levé
  const bob = f > LOCK + RISE && f < BOB_END ? Math.sin(((f - LOCK - RISE) / 40) * Math.PI * 2) * 4 : 0;
  const lift = clamp01(f < BOB_END ? rise : 1 - ramp(f, BOB_END, RESEAT, EASE.swift));
  const press = f >= BOB_END + RESEAT - 2 && f < BOB_END + RESEAT + 4 ? 0.997 : 1;
  const push = mix(1, 1.18, ramp(f, LOCK - 4, 18, EASE.snappy));

  // faisceau de contour
  const lap = (start: number, dur: number) => clamp01((f - start) / dur);
  const l1 = lap(LAP1[0], LAP1[1]), l2 = lap(LAP2[0], LAP2[1]);
  const r = PW * 0.17 + 6;
  const bw = PW + 12, bh = PH + 12;
  const landFlash = f >= BOB_END + RESEAT - 3 ? Math.max(0, 1 - (f - (BOB_END + RESEAT - 3)) / 16) : 0;

  const phoneStyle: React.CSSProperties = {
    position: 'absolute', left: CX - PW / 2, top: CY - PH / 2,
    transform: `translateY(${-70 * lift + bob}px) scale(${(1 + 0.07 * lift) * press})`,
  };
  const title = ramp(f, 50, 26, EASE.out);
  const mark = ramp(f, 62, 12, EASE.smooth);
  const tag = ramp(f, 100, 16, EASE.out);
  const maskPool = `radial-gradient(circle at ${pool.x}px ${pool.y}px, #000 0%, #000 ${radius * 0.35}px, rgba(0,0,0,0.5) ${radius * 0.7}px, transparent ${radius}px)`;
  const maskLock = `radial-gradient(ellipse ${PW * 0.95}px ${PH * 0.62}px at ${CX}px ${CY}px, #000 0%, #000 55%, rgba(0,0,0,0.35) 85%, transparent 100%)`;

  return (
    <div style={{ position: 'absolute', inset: 0, transform: `scale(${push})`, transformOrigin: `${CX}px ${CY - 80}px` }}>
      {/* faisceau volumétrique */}
      <div style={{
        position: 'absolute', left: 0, top: -200, width: 1080, height: 2300, opacity: 0.55 * lit * (1 - 0.45 * lock),
        background: `linear-gradient(180deg, rgba(120,160,255,0.36) 0%, rgba(120,160,255,0) 80%)`,
        clipPath: `polygon(${pool.x + 380}px 0, ${pool.x + 560}px 0, ${pool.x + radius * 0.5}px ${pool.y + 200}px, ${pool.x - radius * 0.5}px ${pool.y + 200}px)`,
        filter: 'blur(22px)', mixBlendMode: 'screen',
      }} />
      {/* héros éteint */}
      <div style={phoneStyle}><div style={{ filter: 'brightness(0.12) saturate(0.6)' }}><Phone kind="iphone" hue={0} width={PW} /></div></div>
      {/* héros éclairé (masque en coordonnées écran = flaque de lumière, puis lumière « en forme de téléphone ») */}
      <div style={{ position: 'absolute', inset: 0, WebkitMaskImage: lock < 0.99 ? maskPool : maskLock, maskImage: lock < 0.99 ? maskPool : maskLock, opacity: lit }}>
        <div style={phoneStyle}>
          <Phone kind="iphone" hue={0} width={PW} glow={0.35 * lift + lockPulse * 3} />
        </div>
      </div>
      {/* sol : reflet et ombre qui suit la hauteur */}
      <div style={{
        position: 'absolute', left: CX - PW * 0.62, top: CY + PH / 2 - 18, width: PW * 1.24, height: 70,
        borderRadius: '50%', background: 'radial-gradient(closest-side, rgba(0,0,0,0.75), rgba(0,0,0,0))',
        transform: `scale(${1 - 0.18 * lift}, ${1 - 0.1 * lift})`, opacity: lit * (0.9 - 0.3 * lift),
      }} />
      <div style={{ position: 'absolute', left: CX - PW * 0.7, top: CY + PH / 2 - 40, width: PW * 1.4, height: 120, borderRadius: '50%', background: 'radial-gradient(closest-side, rgba(40,100,255,0.45), rgba(40,100,255,0))', opacity: lock * (0.8 - 0.35 * lift) }} />
      {/* faisceaux de contour */}
      <svg width={1080} height={1920} viewBox="0 0 1080 1920" style={{ position: 'absolute', inset: 0, overflow: 'visible', transform: `translateY(${-70 * lift + bob}px) scale(${1 + 0.07 * lift})`, transformOrigin: `${CX}px ${CY}px` }}>
        <g fill="none" strokeLinecap="round">
          {[{ p: l1, w1: 7, w2: 3, o: 1 }, { p: l2, w1: 5, w2: 2.5, o: 0.62 }].map((L, i) => (
            L.p > 0 && L.p < 1 ? (
              <g key={i} opacity={L.o * Math.sin(Math.min(1, L.p * 1.15) * Math.PI) ** 0.5}>
                <rect x={CX - bw / 2} y={CY - bh / 2} width={bw} height={bh} rx={r} pathLength={1} stroke="#5b8cff" strokeWidth={L.w1} strokeDasharray="0.16 1" strokeDashoffset={-(L.p * 1.16 - 0.16)} style={{ filter: 'drop-shadow(0 0 14px rgba(70,130,255,0.9))' }} />
                <rect x={CX - bw / 2} y={CY - bh / 2} width={bw} height={bh} rx={r} pathLength={1} stroke="#ffffff" strokeWidth={L.w2} strokeDasharray="0.16 1" strokeDashoffset={-(L.p * 1.16 - 0.16)} />
              </g>
            ) : null
          ))}
          {landFlash > 0 && <rect x={CX - bw / 2} y={CY - bh / 2} width={bw} height={bh} rx={r} stroke="#cfe0ff" strokeWidth={3} opacity={landFlash * 0.8} />}
        </g>
      </svg>
      {/* annonce : le smartphone qu'il vous faut */}
      <div style={{ position: 'absolute', top: 250, left: 0, right: 0, textAlign: 'center', fontFamily: 'Montserrat, sans-serif', fontWeight: 800, color: '#f2f6ff', fontSize: 84, lineHeight: 1.12, letterSpacing: -2, opacity: title, transform: `translateY(${(1 - title) * 40}px)`, filter: `blur(${(1 - title) * 10}px)` }}>
        Le smartphone<br />
        qu’il{' '}
        <span style={{ position: 'relative', display: 'inline-block', color: mark > 0.5 ? '#07152f' : '#f2f6ff' }}>
          <span style={{ position: 'absolute', left: -14, right: -14, top: 4, bottom: -2, borderRadius: 14, background: GNS.orange, transformOrigin: 'left', transform: `scaleX(${mark})` }} />
          <span style={{ position: 'relative' }}>vous faut.</span>
        </span>
      </div>
      <div style={{ position: 'absolute', bottom: 190, left: 0, right: 0, textAlign: 'center', fontFamily: 'Montserrat, sans-serif', fontWeight: 700, color: '#a9b8d8', fontSize: 34, letterSpacing: 8, textTransform: 'uppercase', opacity: tag, transform: `translateY(${(1 - tag) * 18}px)` }}>
        GNS BUSINESS RDC · Kinshasa
      </div>
    </div>
  );
};
