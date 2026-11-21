// Prix lus sur https://www.gnsbusinessrdc.store/marque/* (prix indicatifs Kinshasa), 20 nov. 2026.
// Seules les références « Disponible » sont retenues ; « dès » = version la moins chère.
export type Kind = 'iphone' | 'galaxy' | 'pixel' | 'tecno';
export type Item = { name: string; price: number; from: boolean; kind: Kind; hue: number };

export const APPLE: Item[] = [
  { name: 'iPhone 11', price: 221, from: true, kind: 'iphone', hue: 0 },
  { name: 'iPhone 12', price: 252, from: true, kind: 'iphone', hue: 1 },
  { name: 'iPhone 13', price: 391, from: true, kind: 'iphone', hue: 2 },
  { name: 'iPhone 15 Pro Max', price: 945, from: false, kind: 'iphone', hue: 3 },
];
export const SAMSUNG: Item[] = [
  { name: 'Galaxy S22', price: 276, from: true, kind: 'galaxy', hue: 0 },
  { name: 'Galaxy S23', price: 384, from: true, kind: 'galaxy', hue: 1 },
  { name: 'Galaxy S24', price: 516, from: true, kind: 'galaxy', hue: 2 },
];
export const PIXEL: Item[] = [
  { name: 'Pixel 8', price: 324, from: false, kind: 'pixel', hue: 0 },
  { name: 'Pixel 9', price: 516, from: false, kind: 'pixel', hue: 1 },
  { name: 'Pixel 9 Pro', price: 720, from: false, kind: 'pixel', hue: 2 },
];
export const TECNO: Item[] = [
  { name: 'POP 20', price: 130, from: true, kind: 'tecno', hue: 0 },
  { name: 'Spark 50', price: 174, from: true, kind: 'tecno', hue: 1 },
  { name: 'Camon 50', price: 320, from: false, kind: 'tecno', hue: 2 },
  { name: 'Camon 50 Pro', price: 380, from: false, kind: 'tecno', hue: 3 },
];
export const CITIES = ['Kinshasa', 'Lubumbashi', 'Goma', 'Kisangani', 'Bukavu', 'Mbuji-Mayi'];
export const PHONE = '+243 983 468 341';
export const SITE = 'gnsbusinessrdc.store';
export const ADDRESS = 'Gallerie Pacha, local 11 · Gombe, Kinshasa';
