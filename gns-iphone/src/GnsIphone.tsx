import React from 'react';
import { AbsoluteFill, Audio, Sequence, interpolate, staticFile } from 'remotion';
import { Stage } from './Look';
import { NUIT_MARINE } from './tokens';
import { useFonts } from './fonts';
import { Shot } from './util';
import { MiseEnScene } from './shots/common';
import { S1Gombe } from './shots/S1Gombe';
import { S2Prix } from './shots/S2Prix';
import { S3Boutique } from './shots/S3Boutique';
import { S4Gamme } from './shots/S4Gamme';
import { S5Troc } from './shots/S5Troc';
import { S6Rdc } from './shots/S6Rdc';
import { S7Appel } from './shots/S7Appel';

// Chronologie (30 i/s, 1200 i = 40 s). La voix démarre à 0,6 s (f18) ; frontières posées entre les phrases (transcription Scribe).
export const SHOTS = {
  gombe: { from: 0, dur: 152 },
  prix: { from: 152, dur: 114 },
  boutique: { from: 266, dur: 202 },
  gamme: { from: 468, dur: 192 },
  troc: { from: 660, dur: 96 },
  rdc: { from: 756, dur: 99 },
  appel: { from: 855, dur: 345 },
} as const;
export const VOICE_FROM = 18;

export const GnsIphone: React.FC<{ bgm?: boolean; voice?: boolean }> = ({ bgm = true, voice = true }) => {
  useFonts();
  return (
    <AbsoluteFill style={{ background: '#030a1a' }}>
      {bgm && <Audio src={staticFile('audio/house-vibez.mp3')} startFrom={13} volume={(f) => interpolate(f, [0, 10, 1150, 1199], [0.1, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) * (voice ? 0.2 : 0.7)} />}
      {voice && <Sequence from={VOICE_FROM}><Audio src={staticFile('audio/voix-take1.mp3')} volume={1} /></Sequence>}
      <Stage look={NUIT_MARINE} keyLight={{ x: 0.5, y: 0.12 }} fill={{ x: 0.85, y: 0.92 }} breathe={1} />
      <Sequence from={SHOTS.gombe.from} durationInFrames={SHOTS.gombe.dur}><Shot><S1Gombe /></Shot></Sequence>
      <Sequence from={SHOTS.prix.from} durationInFrames={SHOTS.prix.dur}><Shot><S2Prix /></Shot></Sequence>
      <Sequence from={SHOTS.boutique.from} durationInFrames={SHOTS.boutique.dur}><Shot><S3Boutique /></Shot></Sequence>
      <Sequence from={SHOTS.gamme.from} durationInFrames={SHOTS.gamme.dur}><Shot><S4Gamme /></Shot></Sequence>
      <Sequence from={SHOTS.troc.from} durationInFrames={SHOTS.troc.dur}><Shot><S5Troc /></Shot></Sequence>
      <Sequence from={SHOTS.rdc.from} durationInFrames={SHOTS.rdc.dur}><Shot><S6Rdc /></Shot></Sequence>
      <Sequence from={SHOTS.appel.from} durationInFrames={SHOTS.appel.dur}><Shot crash={0}><S7Appel /></Shot></Sequence>
      <Sequence from={0} durationInFrames={SHOTS.rdc.from + SHOTS.rdc.dur}><MiseEnScene /></Sequence>
    </AbsoluteFill>
  );
};
