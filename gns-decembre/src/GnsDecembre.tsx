import React from 'react';
import { AbsoluteFill, Audio, Sequence, interpolate, staticFile } from 'remotion';
import { useFonts } from './fonts';
import { Legal, PopBg, Wipe } from './pop';
import { A1Accroche } from './shots/A1Accroche';
import { B2Periode } from './shots/B2Periode';
import { C3Marques } from './shots/C3Marques';
import { D4Ticket } from './shots/D4Ticket';
import { E5Livraison } from './shots/E5Livraison';
import { F6Final } from './shots/F6Final';

// Spot promo -5 % (9:16, 960 i = 32 s). La voix démarre à 0,5 s (f15) ; frontières posées entre les phrases (transcription Scribe).
export const SHOTS = {
  accroche: { from: 0, dur: 114 },
  periode: { from: 114, dur: 217 },
  marques: { from: 331, dur: 134 },
  ticket: { from: 465, dur: 175 },
  livraison: { from: 640, dur: 63 },
  final: { from: 703, dur: 257 },
} as const;
export const VOICE_FROM = 15;
export const TOTAL = 960;

export const GnsDecembre: React.FC<{ bgm?: boolean; voice?: boolean }> = ({ bgm = true, voice = true }) => {
  useFonts();
  return (
    <AbsoluteFill style={{ background: '#0a2fb8' }}>
      {bgm && <Audio src={staticFile('audio/house-vibez.mp3')} startFrom={13} volume={(f) => interpolate(f, [0, 10, TOTAL - 40, TOTAL - 1], [0.1, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) * (voice ? 0.22 : 0.75)} />}
      {voice && <Sequence from={VOICE_FROM}><Audio src={staticFile('audio/voix-take1.mp3')} volume={1} /></Sequence>}
      <PopBg />
      <Sequence from={SHOTS.accroche.from} durationInFrames={SHOTS.accroche.dur}><A1Accroche /></Sequence>
      <Sequence from={SHOTS.periode.from} durationInFrames={SHOTS.periode.dur}><B2Periode /></Sequence>
      <Sequence from={SHOTS.marques.from} durationInFrames={SHOTS.marques.dur}><C3Marques /></Sequence>
      <Sequence from={SHOTS.ticket.from} durationInFrames={SHOTS.ticket.dur}><D4Ticket /></Sequence>
      <Sequence from={SHOTS.livraison.from} durationInFrames={SHOTS.livraison.dur}><E5Livraison /></Sequence>
      <Sequence from={SHOTS.final.from} durationInFrames={SHOTS.final.dur}><F6Final /></Sequence>
      <Sequence from={SHOTS.periode.from} durationInFrames={TOTAL - SHOTS.periode.from}><Legal /></Sequence>
      {[SHOTS.periode.from, SHOTS.marques.from, SHOTS.ticket.from, SHOTS.livraison.from, SHOTS.final.from].map((b) => <Wipe key={b} at={b} />)}
    </AbsoluteFill>
  );
};
