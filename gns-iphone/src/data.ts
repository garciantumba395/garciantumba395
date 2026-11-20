// Prix lus sur https://www.gnsbusinessrdc.store/marque/apple (prix indicatifs Kinshasa, 20 nov. 2026), références « Disponible ».
export type Kind = 'iphone' | 'galaxy' | 'pixel' | 'tecno';
export type Item = { name: string; price: number; from: boolean; kind: Kind; hue: number };
export const IPHONES: Item[] = [
  { name: 'iPhone XR', price: 183, from: true, kind: 'iphone', hue: 2 },
  { name: 'iPhone 12', price: 252, from: true, kind: 'iphone', hue: 0 },
  { name: 'iPhone 18 Pro Max', price: 2585, from: false, kind: 'iphone', hue: 3 },
];
export const PHONE = '+243 983 468 341';
export const SITE = 'gnsbusinessrdc.store';
export const ADDRESS = 'Gallerie Pacha, local 11 · Gombe, Kinshasa';
