import React from 'react';
import { Composition } from 'remotion';
import { GnsPromo } from './GnsPromo';
import { FPS, H, W } from './tokens';

export const RemotionRoot: React.FC = () => (
  <Composition id="GnsPromo" component={GnsPromo} durationInFrames={1350} fps={FPS} width={W} height={H} defaultProps={{ bgm: true }} />
);
