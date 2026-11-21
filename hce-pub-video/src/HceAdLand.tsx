import React from 'react';
import { AbsoluteFill, Audio, Sequence, interpolate, staticFile } from 'remotion';
import { useFonts } from './fonts';
import { MiseEnScene, WipeL } from './ui';
import { SHOTS, TOTAL, VOICE_FROM } from './HceAd';
import { L1Incident } from './land/L1Incident';
import { L2Intro } from './land/L2Intro';
import { L3Presentation } from './land/L3Presentation';
import { L4Services } from './land/L4Services';
import { L5Academy } from './land/L5Academy';
import { L6Final } from './land/L6Final';

// Version YouTube 16:9 (1920×1080), mêmes durées, même voix : mises en page repensées pour l'écran horizontal.
const seq = (k: keyof typeof SHOTS, el: React.ReactNode) => <Sequence from={SHOTS[k][0]} durationInFrames={SHOTS[k][1] - SHOTS[k][0]}>{el}</Sequence>;
export const HceAdLand: React.FC<{ bgm?: boolean; voice?: boolean }> = ({ bgm = true, voice = true }) => {
  useFonts();
  return (
    <AbsoluteFill style={{ background: '#fff' }}>
      {bgm && <Audio src={staticFile('audio/bgm-tech-house.mp3')} volume={(f) => interpolate(f, [0, 20, TOTAL - 50, TOTAL - 1], [0, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) * (voice ? 0.16 : 0.7)} />}
      {voice && <Sequence from={VOICE_FROM}><Audio src={staticFile('audio/voix.mp3')} volume={1} /></Sequence>}
      {seq('incident', <L1Incident />)}
      {seq('intro', <L2Intro />)}
      {seq('presentation', <L3Presentation />)}
      {seq('services', <L4Services />)}
      {seq('academy', <L5Academy />)}
      {seq('final', <L6Final />)}
      <Sequence from={0} durationInFrames={SHOTS.intro[0]}><MiseEnScene dark /></Sequence>
      <Sequence from={SHOTS.intro[0]} durationInFrames={SHOTS.intro[1] - SHOTS.intro[0]}><MiseEnScene /></Sequence>
      {[SHOTS.intro[0], SHOTS.presentation[0], SHOTS.services[0], SHOTS.academy[0], SHOTS.final[0]].map((b) => <WipeL key={b} at={b} />)}
    </AbsoluteFill>
  );
};
