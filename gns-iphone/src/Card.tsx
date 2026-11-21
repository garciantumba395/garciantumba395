import React from 'react';
import { GNS, FONT_FAMILY } from './tokens';
import { Phone } from './Phone';
import type { Item } from './data';

export const fmtPrice = (n: number) => `${n.toLocaleString('fr-FR').replace(/ | /g, ' ')} $`;

// Carte produit : verre sombre, téléphone vectoriel, nom, prix (orange = seule couleur chaude du film).
export const PriceCard: React.FC<{
  item: Item; w?: number; h?: number; showPrice?: boolean; priceP?: number; glow?: number; style?: React.CSSProperties; priceOnly?: boolean;
}> = ({ item, w = 460, h = 640, showPrice = true, priceP = 1, glow = 0, style, priceOnly }) => {
  const phoneW = Math.min(w * 0.46, h * 0.27);
  return (
    <div style={{
      width: w, height: h, borderRadius: 40, position: 'relative', overflow: 'hidden', fontFamily: FONT_FAMILY,
      background: 'linear-gradient(165deg, rgba(24,45,92,0.96) 0%, rgba(11,24,52,0.98) 60%, rgba(6,14,32,1) 100%)',
      border: '1.5px solid rgba(150,185,255,0.22)',
      boxShadow: `0 40px 90px rgba(0,4,16,0.65), inset 0 1.5px 0 rgba(255,255,255,0.12), 0 0 ${60 * glow}px rgba(40,100,255,${0.55 * glow})`,
      ...style,
    }}>
      {!priceOnly && (
        <div style={{ position: 'absolute', top: h * 0.07, left: 0, right: 0, display: 'flex', justifyContent: 'center' }}>
          <Phone kind={item.kind} hue={item.hue} width={phoneW} />
        </div>
      )}
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: h * 0.07, textAlign: 'center', padding: '0 24px' }}>
        <div style={{ color: '#f2f6ff', fontWeight: 800, fontSize: Math.min(44, w * 0.088), letterSpacing: -0.5, lineHeight: 1.1 }}>{item.name}</div>
        {showPrice && (
          <div style={{ marginTop: 14, display: 'flex', alignItems: 'baseline', justifyContent: 'center', gap: 10, opacity: Math.min(1, priceP * 1.6), transform: `scale(${0.82 + 0.18 * priceP})`, filter: priceP < 1 ? `blur(${(1 - priceP) * 6}px)` : undefined }}>
            {item.from && <span style={{ color: '#a9b8d8', fontWeight: 600, fontSize: Math.min(28, w * 0.06) }}>dès</span>}
            <span style={{ color: GNS.orange, fontWeight: 900, fontSize: Math.min(68, w * 0.14), letterSpacing: -1.5 }}>{fmtPrice(item.price)}</span>
          </div>
        )}
      </div>
    </div>
  );
};
