# Taxi Kin — performance Android (Étape 7)

> **Important** : rien n'a pu être mesuré sur un téléphone ni même dans Unity pendant le développement de cette étape.
> Ce document donne les budgets visés, ce qui est en place dans le code, et la procédure de test à faire sur un vrai
> téléphone d'entrée de gamme (ex. 2-3 Go de RAM, Android 8-10, GPU Mali-G52 / Adreno 506 / PowerVR).

## Objectifs
| Objectif | Valeur visée |
|---|---|
| Cadence | 30 images/s minimum (qualité Basse), 60 si possible (Moyenne/Haute) |
| Taille initiale (AAB) | < 300 Mo (cible réaliste : < 100 Mo tant qu'il n'y a que des formes simples) |
| Chargement d'une zone | < 5 s sur l'appareil de test, sans gel de l'écran |
| Mémoire | < 700 Mo au total (PSS) en jeu |
| Batterie | ≤ 12 % par 30 min de conduite, appareil à 100 % luminosité moyenne |

## Budgets de contenu (par vue)
| Élément | Basse | Moyenne | Haute |
|---|---|---|---|
| Appels de dessin (draw calls) | ≤ 100 | ≤ 150 | ≤ 250 |
| Triangles visibles | ≤ 80 000 | ≤ 150 000 | ≤ 250 000 |
| Lumières dynamiques | 1 (soleil) | 1 | 1 + 1 |
| Ombres temps réel | aucune | dures, 25 m, 1 cascade | douces, 40 m, 2 cascades |
| Véhicules de trafic actifs | 6 | 12 | 20 |
| Distance d'affichage de la carte | 220 m | 350 m | 550 m |
| Échelle de rendu (URP) | 70 % | 85 % | 100 % |
| Textures | mip réduit d'un niveau | pleine | pleine |

Règles pour les futurs modèles 3D : textures ≤ 1024×1024 (≤ 512 pour les objets secondaires), compression **ASTC 6×6**,
un matériau partagé par groupe d'objets, atlas pour les éléments d'interface, pas de transparence sur de grandes surfaces
(« overdraw »), LOD0 ≤ 3 000 triangles par véhicule, LOD1 ≤ 1 200, LOD2 ≤ 400 (le Wewa : 1 500 / 600 / 200).

## Géométrie mesurée sur les vraies données (calcul hors Unity, pas une mesure sur appareil)
| Zone | Immeubles | Triangles d'immeubles (zone entière) | Routes (triangles) | Pire cas : 9 blocs voisins |
|---|---|---|---|---|
| Gare centrale | 624 | 9 975 | 2 542 | 4 773 |
| Matonge | 1 500 | 17 359 | 4 692 | 12 694 |
| Kintambo | 1 500 | 17 244 | 5 464 | 9 936 |
| Limete | 1 500 | 22 552 | 3 632 | 11 978 |
| N'djili | 1 138 | 13 585 | 2 030 | 12 159 |
Même zone entière affichée, on reste sous 25 000 triangles (budget Basse : 80 000). **Le plafond de 1 500 immeubles est donc très prudent**
pour le nombre de triangles : on pourra le relever (à tester : temps de chargement et cuisson des collisions). Sur 6 262 immeubles,
6 252 toits sont triangulés ; 10 tracés dégénérés n'ont pas de toit (les murs restent).

## Ce qui est en place dans le code
- **Qualité Basse / Moyenne / Haute** (`QualityManager`) : cadence cible, échelle de rendu, MSAA, ombres, lumières par pixel,
  `lodBias`, mips de texture, distance de vue, plafond de trafic, fréquence de la physique (30 Hz en Basse). Détection
  automatique au premier lancement (RAM < 3 Go ou ≤ 4 cœurs → Basse ; < 6 Go → Moyenne ; sinon Haute).
- **Baisse automatique** (`PerformanceMonitor`) : si la cadence reste sous 80 % de la cible pendant 8 s de conduite, la
  qualité baisse d'un cran (jamais de hausse automatique). Désactivable en choisissant une qualité à la main.
- **Carte par blocs de 250 m** (`ZoneLoader`) : blocs lointains non dessinés, collision des immeubles créée à la demande
  (une par passage) près du joueur, chargement étalé (6 ms par image), mémoire libérée au déchargement d'une zone.
  Plafond de 1 500 immeubles par zone (`osm_to_zone.py`). Aucune ombre projetée par les immeubles.
- **Monde en plusieurs zones** (`WorldManager`) : une zone se charge à moins de 450 m de son sol et se décharge au-delà de 700 m (hystérésis). Une zone en général ; deux au milieu d'un corridor (budget mémoire à vérifier sur appareil). Les corridors sont construits une seule fois (3 maillages et 3 collisions chacun).
- **Chargement à la demande** : vêtements (prefab chargé à l'équipement), véhicule d'aperçu du garage (un seul à la fois),
  fichier de zone libéré après lecture.
- **Mémoire** : trafic en réserve d'objets (pas d'Instantiate/Destroy en jeu), HUD rafraîchi 5 fois/s, contrôle des
  missions sans allocation, caméras d'aperçu (avatar, garage, minimap 256×256 rendue 1 image sur 3) éteintes hors usage.
- **Batterie** : plafond 30 FPS sous 15 % de batterie hors charge ; écran autorisé à s'éteindre hors conduite ; vibrations
  espacées de 1,5 s ; physique à 30 Hz en Basse.
- **Interface** : zone sûre (encoche), échelle d'interface automatique selon la diagonale physique (agrandie sous 6,4"),
  réglable dans les réglages.
- **Build** : menu *Taxi Kin → Android → Appliquer les réglages d'optimisation* (IL2CPP, ARMv7+ARM64, Android 7+, OpenGL ES 3,
  stripping élevé, AAB, paysage, GC incrémental, cadence stable, couleur Gamma). `Assets/link.xml` protège l'asset URP.

## À faire à la main dans Unity (non automatisable ici)
1. Installer **Universal RP** et créer un asset « Mobile » : *HDR désactivé, MSAA désactivé, Soft Shadows désactivées,
   Depth/Opaque Texture désactivées, SRP Batcher activé, Additional Lights : désactivées ou 1 par pixel*. Le menu de génération
   de scène utilise « Simple Lit » avec instanciation GPU.
2. Dans *Project Settings → Quality*, ne garder qu'un niveau Android (les préréglages de `QualityManager` font le reste).
3. Importer les futurs modèles avec *Mesh Compression : Medium*, *Read/Write désactivé*, LOD Group aux seuils 50 % / 20 % / 8 %.
4. Remplacer les cubes par de vrais modèles seulement après avoir mesuré sur appareil.

## Procédure de test sur téléphone d'entrée de gamme
Construire en **Development Build** (la mesure FPS/mémoire s'affiche en bas de l'écran) puis, pour chaque ligne, noter le résultat.
| # | Test | Comment | Critère |
|---|---|---|---|
| 1 | Démarrage à froid | Chronométrer lancement → écran « Mon chauffeur » | < 8 s |
| 2 | Conduite 5 min, qualité Basse | Zone Gare centrale, Wewa, trafic dense | ≥ 30 FPS stables, pas de saccade > 100 ms |
| 3 | Conduite 5 min, qualité Moyenne/Haute | idem | ≥ 45 FPS (idéal 60) |
| 4 | Baisse automatique | Forcer Haute sur un appareil lent | Passe à Moyenne puis Basse en < 30 s, avec message |
| 5 | Chargement de zone | Changer de zone | Pas de gel > 200 ms ; mémoire redescend après déchargement |
| 6 | Mémoire | `adb shell dumpsys meminfo <paquet>` après 15 min | PSS total < 700 Mo, sans croissance continue |
| 7 | Batterie | 30 min de conduite, 100 % de batterie de départ | ≤ 12 % consommés, appareil non brûlant |
| 8 | Lisibilité | Écran 5" : lire tous les textes sans plisser | Réglage « Interface » suffisant ; noter les textes trop petits |
| 9 | Zone sûre | Appareil avec encoche | Aucun bouton masqué |
| 10 | Rotation / reprise | Verrouiller, déverrouiller, appel entrant | Sauvegarde conservée, pas de plantage |
| 11 | Taille | Taille de l'AAB / du bundle téléchargé | < 300 Mo |

Outils : Unity Profiler (connexion `adb`), Android GPU Inspector, `adb shell dumpsys gfxinfo <paquet> framestats`,
`adb shell dumpsys batterystats`, Android Studio Profiler.

## Limites connues
- Les textes secondaires (descriptions, notes) font 20 à 24 px de référence (sur 1 920) : probablement trop petits sur un écran de 5". À agrandir après le test n° 8.
- Tant qu'il n'y a pas de vrai menu principal, l'état « menu » sert de conduite libre : l'écran reste donc allumé aussi dans cet état.
- Aucune mesure réelle ; les préréglages sont des points de départ à ajuster après les tests ci-dessus.
- Les LOD ne sont pas encore utilisés : les véhicules sont des cubes (inutile). À ajouter avec les vrais modèles.
- Pas de streaming continu de plusieurs zones : une zone à la fois (le corridor entre zones n'existe pas encore).
- `QualityManager` ne modifie pas l'asset URP dans l'éditeur (pour ne pas l'altérer) : l'échelle de rendu, le MSAA et la
  distance des ombres URP ne se vérifient donc que sur une build Android.
