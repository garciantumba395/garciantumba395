import React from 'react';
import { Composition } from 'remotion';
import { GnsIphone } from './GnsIphone';
import { FPS, H, W } from './tokens';

export const RemotionRoot: React.FC = () => (
  <Composition id="GnsIphone" component={GnsIphone} durationInFrames={1200} fps={FPS} width={W} height={H} defaultProps={{ bgm: true, voice: true }} />
);
