import React from 'react';
import { Img, staticFile } from 'remotion';
import { C } from './tokens';

// Logos des technologies. Source : /images/technologies/*.svg du site hce.cd (fichiers de l'entreprise).
// Logos vidéosurveillance (Axis, Hikvision, Dahua, Wisenet) : à fournir par HCE — tant qu'un fichier est absent, on affiche le nom en texte (jamais un logo redessiné).
export const CCTV: { key: string; name: string; file: string | null }[] = [
  { key: 'axis', name: 'Axis Communications', file: null },
  { key: 'hikvision', name: 'Hikvision', file: null },
  { key: 'dahua', name: 'Dahua', file: null },
  { key: 'wisenet', name: 'Wisenet', file: null },
];

export const TechLogo: React.FC<{ file: string; h?: number }> = ({ file, h = 34 }) => (
  <Img src={staticFile(`img/logos/${file}`)} style={{ height: h, width: 'auto', maxWidth: h * 3.2, objectFit: 'contain', display: 'block' }} />
);

// Tuile marque : logo si fourni, sinon nom en texte
export const BrandTile: React.FC<{ name: string; file: string | null; w: number; h: number; fs?: number }> = ({ name, file, w, h, fs = 34 }) => (
  <div style={{ width: w, height: h, background: '#fff', borderRadius: 22, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 14, boxShadow: '0 10px 26px rgba(10,30,63,0.18)' }}>
    {file ? <Img src={staticFile(`img/logos/${file}`)} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} /> : <span style={{ fontFamily: 'Montserrat', fontWeight: 900, fontSize: fs, color: C.navy, letterSpacing: -0.5, textAlign: 'center', lineHeight: 1.05 }}>{name}</span>}
  </div>
);
