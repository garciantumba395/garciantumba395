import React from 'react';
import { Composition } from 'remotion';
import { HceAd, TOTAL } from './HceAd';
import { HceAdLand } from './HceAdLand';
import { FPS, H, W } from './tokens';

export const RemotionRoot: React.FC = () => (
  <>
  <Composition id="HceAd" component={HceAd} durationInFrames={TOTAL} fps={FPS} width={W} height={H} defaultProps={{ bgm: true, voice: true }} />
  <Composition id="HceAdLand" component={HceAdLand} durationInFrames={TOTAL} fps={FPS} width={1920} height={1080} defaultProps={{ bgm: true, voice: true }} />
  </>
);
