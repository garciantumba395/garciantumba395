import React from 'react';
import { AbsoluteFill, Audio, Sequence, interpolate, staticFile } from 'remotion';
import { useFonts } from './fonts';
import { MiseEnScene, Wipe } from './ui';
import { S1Incident } from './shots/S1Incident';
import { S2Intro } from './shots/S2Intro';
import { S3Presentation } from './shots/S3Presentation';
import { S4Services } from './shots/S4Services';
import { S5Academy } from './shots/S5Academy';
import { S6Final } from './shots/S6Final';

// Publicité HCE — 45 s, 9:16, 1350 i. Voix (Gabriel, tempo ×1,10) à 0,6 s (f18). Frontières posées aux débuts de paragraphes de la voix.
export const SHOTS = { incident: [0, 263], intro: [263, 383], presentation: [383, 620], services: [620, 860], academy: [860, 1105], final: [1105, 1350] } as const;
export const TOTAL = 1350;
export const VOICE_FROM = 18;
const seq = (k: keyof typeof SHOTS, el: React.ReactNode) => <Sequence from={SHOTS[k][0]} durationInFrames={SHOTS[k][1] - SHOTS[k][0]}>{el}</Sequence>;

export const HceAd: React.FC<{ bgm?: boolean; voice?: boolean }> = ({ bgm = true, voice = true }) => {
  useFonts();
  return (
    <AbsoluteFill style={{ background: '#fff' }}>
      {bgm && <Audio src={staticFile('audio/bgm-tech-house.mp3')} volume={(f) => interpolate(f, [0, 20, TOTAL - 50, TOTAL - 1], [0, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) * (voice ? 0.16 : 0.7)} />}
      {voice && <Sequence from={VOICE_FROM}><Audio src={staticFile('audio/voix.mp3')} volume={1} /></Sequence>}
      {seq('incident', <S1Incident />)}
      {seq('intro', <S2Intro />)}
      {seq('presentation', <S3Presentation />)}
      {seq('services', <S4Services />)}
      {seq('academy', <S5Academy />)}
      {seq('final', <S6Final />)}
      <Sequence from={0} durationInFrames={SHOTS.intro[0]}><MiseEnScene dark /></Sequence>
      <Sequence from={SHOTS.intro[0]} durationInFrames={SHOTS.intro[1] - SHOTS.intro[0]}><MiseEnScene /></Sequence>
      {[SHOTS.intro[0], SHOTS.presentation[0], SHOTS.services[0], SHOTS.academy[0], SHOTS.final[0]].map((b) => <Wipe key={b} at={b} />)}
    </AbsoluteFill>
  );
};
