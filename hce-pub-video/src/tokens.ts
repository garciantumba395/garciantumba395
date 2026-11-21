export const W = 1080;
export const H = 1920;
export const FPS = 30;
// Charte HCE (affiches) : bleu #0057D8, orange #FF7A00, marine, blanc, gris très clair.
export const C = { blue: '#0057D8', navy: '#0A1E3F', orange: '#FF7A00', white: '#FFFFFF', gray: '#F4F6F9', red: '#E5384F', ink2: '#4a5d82' };
// Instants de la voix (secondes de la prise, transcription Scribe) → image du film : tempo ×1,16 et amorce 0,6 s.
export const TEMPO = 1.2;
// Insertion de « Systèmes photovoltaïques » (voix générée à part) dans la prise à 25,46 s : tout ce qui suit est décalé de DELTA.
export const SPLICE_AT = 25.46;
export const DELTA = 1.6676;
export const T = (t: number) => Math.round(30 * (0.6 + (t >= SPLICE_AT ? t + DELTA : t) / TEMPO));
// Instant du mot « photovoltaïques » (début du fragment inséré)
export const T_PV = Math.round(30 * (0.6 + (SPLICE_AT + 0.10) / TEMPO));
