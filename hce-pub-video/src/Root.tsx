import React from 'react';
import { Composition } from 'remotion';
import { HceAd, TOTAL } from './HceAd';
import { FPS, H, W } from './tokens';

export const RemotionRoot: React.FC = () => (
  <Composition id="HceAd" component={HceAd} durationInFrames={TOTAL} fps={FPS} width={W} height={H} defaultProps={{ bgm: true, voice: true }} />
);
