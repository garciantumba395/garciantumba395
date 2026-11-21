import React from 'react';
import { Composition } from 'remotion';
import { GnsDecembre, TOTAL } from './GnsDecembre';
import { FPS, H, W } from './tokens';

export const RemotionRoot: React.FC = () => (
  <Composition id="GnsDecembre" component={GnsDecembre} durationInFrames={TOTAL} fps={FPS} width={W} height={H} defaultProps={{ bgm: true, voice: true }} />
);
