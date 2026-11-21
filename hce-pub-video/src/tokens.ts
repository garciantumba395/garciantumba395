export const W = 1080;
export const H = 1920;
export const FPS = 30;
// Charte HCE (affiches) : bleu #0057D8, orange #FF7A00, marine, blanc, gris très clair.
export const C = { blue: '#0057D8', navy: '#0A1E3F', orange: '#FF7A00', white: '#FFFFFF', gray: '#F4F6F9', red: '#E5384F', ink2: '#4a5d82' };
// Instants de la voix (secondes de la prise) → image du film : tempo ×1,10 et amorce 0,6 s.
export const T = (t: number) => Math.round(30 * (0.6 + t / 1.1));
