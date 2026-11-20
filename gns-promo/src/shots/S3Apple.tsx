import React from 'react';
import { useCurrentFrame } from 'remotion';
import { PriceCard } from '../Card';
import { BrandTag, clamp01 } from '../util';
import { EASE, bezier, mix, ramp } from '../Polish';
import { APPLE } from '../data';

// Plan 3 — deck-deal-flyin (adapté portrait) : pile de cartes en gros plan, préparation (la pile s'écrase),
// puis distribution à cadence qui s'accélère ; chaque carte : vol 9f (bezier .3,0,.2,1, arc z +6 %),
// settle 4f à dépassement (y1>1), press 0.996→1 ; la caméra recule pendant la donne ; maintien final > 0,5 s.
const CW = 456, CH = 600;
// centres des emplacements (x,y = centre)
const CENTERS = [{ x: 292, y: 630 }, { x: 788, y: 630 }, { x: 292, y: 1266 }, { x: 788, y: 1266 }];
const CUES = [46, 61, 68, 75]; // atterrissages : temps 4, 5, +½, +½ (58,8 / 73,5 / 80,8 / 88,2)
const FLY = 9, SETTLE = 4;
const flyE = bezier(0.3, 0, 0.2, 1), settleE = bezier(0.3, 0, 0.25, 1.15);
const PILE = { x: 540, y: 1380 };
const jitter = (k: number) => ({ px: (((k * 7) % 9) - 4) * 2, pz: (((k * 5) % 7) - 3) * 1.2 });

export const S3Apple: React.FC = () => {
  const f = useCurrentFrame();
  const cam = mix(1.34, 1, ramp(f, 38, 58, EASE.smooth));
  const drift = 1 + 0.03 * ramp(f, 110, 100, EASE.swift);
  const tag = ramp(f, 4, 18, EASE.out);
  const prep = f >= 30 && f < CUES[0] ? ramp(f, 30, 14, EASE.out) : f >= CUES[0] ? Math.max(0, 1 - (f - CUES[0]) / 4) : 0;
  return (
    <div style={{ position: 'absolute', inset: 0, transform: `scale(${cam * drift})`, transformOrigin: `540px ${mix(1330, 1000, ramp(f, 38, 58, EASE.smooth))}px` }}>
      <BrandTag label="Apple" sub="iPhone" p={tag} />
      {APPLE.map((item, k) => {
        const { px, pz } = jitter(k);
        const cue = CUES[k];
        const t = clamp01((f - cue) / FLY);
        const fly = flyE(t);
        const settle = f > cue + FLY ? settleE(clamp01((f - cue - FLY) / SETTLE)) : 0;
        const land = cue + FLY + SETTLE;
        const press = f >= land && f < land + 3 ? 0.996 : 1;
        const stackY = PILE.y - (APPLE.length - 1 - k) * 0 - (3 - k) * 6 + 24 * prep * (k === 0 ? 0.5 : 1);
        const pileX = PILE.x + px + (k === 0 ? -30 * prep : 0);
        const x = mix(pileX, CENTERS[k].x, f <= cue + FLY ? fly : 1);
        const y = mix(stackY, CENTERS[k].y, f <= cue + FLY ? fly : 1);
        const arc = Math.sin(Math.min(1, t) * Math.PI);
        const sc = mix(0.8, 1, f <= cue + FLY ? fly : 1) * (1 + 0.06 * arc) * (f > cue + FLY ? mix(1.0, 1, settle) : 1) * press;
        const rot = mix(pz, 0, f <= cue + FLY ? fly : 1);
        const landed = f >= land;
        const pop = ramp(f, cue + FLY + 1, 8, EASE.overshoot);
        const flash = landed ? Math.max(0, 1 - (f - land) / 14) : 0;
        const ghost = f > cue && f < cue + FLY + 2 ? (1 - t) * 0.25 : 0;
        return (
          <React.Fragment key={item.name}>
            {ghost > 0 && (
              <div style={{ position: 'absolute', left: x - CW / 2, top: y - CH / 2, opacity: ghost, filter: 'blur(8px)', transform: `scale(${sc}) translate(${(1 - fly) * -40}px, ${(1 - fly) * 40}px)` }}>
                <PriceCard item={item} w={CW} h={CH} showPrice={false} />
              </div>
            )}
            <div style={{ position: 'absolute', left: x - CW / 2, top: y - CH / 2, transform: `rotate(${rot}deg) scale(${sc})`, zIndex: 10 - k }}>
              <PriceCard item={item} w={CW} h={CH} priceP={landed ? pop : 0} glow={flash * 0.9} />
            </div>
          </React.Fragment>
        );
      })}
    </div>
  );
};
