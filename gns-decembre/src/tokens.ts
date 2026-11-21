// Tokens GNS BUSINESS RDC — extraits du site (CSS computed) et du logo.
import type { Look } from './Look';

export const W = 1080;
export const H = 1920;
export const FPS = 30;

export const GNS = {
  blue: '#0057ff',
  blueSoft: '#5b8cff',
  navy: '#07152f',
  navy2: '#0b1730',
  ink: '#0b1220',
  orange: '#ff7a00',
  green: '#16a34a',
  paper: '#f4f6fa',
  white: '#ffffff',
};

// « Nuit marine » : fond marine du site, lumière bleue #0057ff, orange réservé aux prix et à l'appel final.
export const NUIT_MARINE: Look = {
  name: 'midnight',
  dark: true,
  bg: ['#0b1a38', '#07152f', '#030a1a'],
  light: '#1d5cff',
  surface: '#0d1c3b',
  surface2: '#13264d',
  line: 'rgba(150,185,255,0.16)',
  ink: '#f2f6ff',
  ink2: '#a9b8d8',
  ink3: '#60729a',
  accent: GNS.blueSoft,
  accent2: GNS.orange,
  onAccent: '#030a1a',
  shadow: '#010510',
};

export const FONT_FAMILY = 'Montserrat, "Helvetica Neue", Arial, sans-serif';
