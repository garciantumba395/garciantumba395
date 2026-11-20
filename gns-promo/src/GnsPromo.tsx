import React from 'react';
import { AbsoluteFill, Audio, Sequence, interpolate, staticFile } from 'remotion';
import { Stage } from './Look';
import { NUIT_MARINE } from './tokens';
import { loadFonts } from './fonts';
import { Shot } from './util';
import { Footer } from './Footer';
import { S1Hero } from './shots/S1Hero';
import { S2Slogan } from './shots/S2Slogan';
import { S3Apple } from './shots/S3Apple';
import { S4Samsung } from './shots/S4Samsung';
import { S5Pixel } from './shots/S5Pixel';
import { S6Tecno } from './shots/S6Tecno';
import { S7Deliver } from './shots/S7Deliver';
import { S8Outro } from './shots/S8Outro';

loadFonts();

// Chronologie (30 i/s, 1350 i = 45 s). Source unique : SHOTS.
export const SHOTS = {
  // frontières = n × 14,688 f (122,55 BPM) : 10 / 16 / 30 / 42 / 53 / 66 / 76 temps
  hero: { from: 0, dur: 147 },
  slogan: { from: 147, dur: 88 },
  apple: { from: 235, dur: 206 },
  samsung: { from: 441, dur: 176 },
  pixel: { from: 617, dur: 162 },
  tecno: { from: 779, dur: 190 },
  deliver: { from: 969, dur: 147 },
  outro: { from: 1116, dur: 234 },
} as const;

export const BGM_START_FRAMES = 13; // 0,433 s : premier temps du morceau → f0 du film
export const GnsPromo: React.FC<{ bgm?: boolean }> = ({ bgm = true }) => {
  return (
    <AbsoluteFill style={{ background: '#030a1a' }}>
      {bgm && (
        <Audio
          src={staticFile('audio/house-vibez.mp3')}
          startFrom={BGM_START_FRAMES}
          volume={(f) => interpolate(f, [0, 8, 1290, 1349], [0.35, 0.72, 0.72, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })}
        />
      )}
      <Stage look={NUIT_MARINE} keyLight={{ x: 0.5, y: 0.12 }} fill={{ x: 0.85, y: 0.92 }} breathe={1} />
      <Sequence from={SHOTS.hero.from} durationInFrames={SHOTS.hero.dur}><Shot><S1Hero /></Shot></Sequence>
      <Sequence from={SHOTS.slogan.from} durationInFrames={SHOTS.slogan.dur}><Shot><S2Slogan /></Shot></Sequence>
      <Sequence from={SHOTS.apple.from} durationInFrames={SHOTS.apple.dur}><Shot><S3Apple /></Shot></Sequence>
      <Sequence from={SHOTS.samsung.from} durationInFrames={SHOTS.samsung.dur}><Shot><S4Samsung /></Shot></Sequence>
      <Sequence from={SHOTS.pixel.from} durationInFrames={SHOTS.pixel.dur}><Shot><S5Pixel /></Shot></Sequence>
      <Sequence from={SHOTS.tecno.from} durationInFrames={SHOTS.tecno.dur}><Shot><S6Tecno /></Shot></Sequence>
      <Sequence from={SHOTS.deliver.from} durationInFrames={SHOTS.deliver.dur}><Shot><S7Deliver /></Shot></Sequence>
      <Sequence from={SHOTS.apple.from + 6} durationInFrames={SHOTS.deliver.from + SHOTS.deliver.dur - SHOTS.apple.from - 6}><Footer /></Sequence>
      <Sequence from={SHOTS.outro.from} durationInFrames={SHOTS.outro.dur}><Shot crash={0}><S8Outro /></Shot></Sequence>
    </AbsoluteFill>
  );
};
