import { Config } from '@remotion/cli/config';

Config.setVideoImageFormat('jpeg');
Config.setOverwriteOutput(true);
Config.setConcurrency(3);
Config.setDelayRenderTimeoutInMilliseconds(180000);
// Chromium préinstallé (le téléchargement de Chrome Headless Shell est bloqué par le réseau).
Config.setBrowserExecutable('/opt/pw-browsers/chromium');
Config.setChromiumOpenGlRenderer('swangle');
