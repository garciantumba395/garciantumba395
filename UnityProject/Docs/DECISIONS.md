# Taxi Kin — décisions de conception (réf. cahier des charges v1)

Le cahier des charges complet est la référence produit. Points arbitrés par rapport au plan en 7 étapes :

| Sujet | Constat | Décision proposée |
|---|---|---|
| Langues | Cahier : français **et lingala** | `LocalizationManager` + fichiers de textes dès l'Étape 2 (HUD), pas à l'Étape 7 |
| Zones V1 | 5 zones : Gare centrale, Matonge, Kintambo, Limete, N'djili | Missions 1-10 citent aussi Gombe, Ngaliema, Masina (zones « plus tard ») → à trancher |
| Nombre de missions | V1 = 10 ; plan = 14 + 2 | 10 missions en V1, les autres masquées/« bientôt » |
| Publicités | Cahier : récompensées uniquement, jamais en conduite | Suppression de l'interstitiel prévue dans `AdManager` |
| Bus 207 | 10 à 20 passagers | Capacité fixée à 20 |
| Trafic/météo V1 | « simple » | Voitures + motos + pluie ; feux, nids-de-poule, police après V1 |

## Étape 2 — commandes (hypothèses par défaut, modifiables)
- Entrées : ancien `Input` + Canvas UI (Project Settings → Player → Active Input Handling = *Input Manager (Old)* ou *Both*).
- Unity 2022.3 LTS (compatible Unity 6 via `#if UNITY_6000_0_OR_NEWER`), URP recommandé.
- Canvas 1920×1080, paysage (Player Settings → Default Orientation = Landscape Left).
- Police UI : `LegacyRuntime.ttf`.
- Les boutons « Wewa / Taxi / 207 / Mode » en haut à droite sont des outils de TEST (à retirer avant la sortie).
- Wewa : pas de marche arrière (commandes simples) ; les 4 caméras sont disponibles sur tous les véhicules.
- Lingala : infrastructure prête (`Loc`), traductions volontairement quasi vides → à faire valider par un locuteur natif.
- Inclinaison : le signe (gauche/droite) est à calibrer sur un vrai appareil.

## Carte de Kinshasa (approche C : OSM + retouches manuelles)
- Centres des 5 zones géocodés (Nominatim), voir `Tools/zones.json` — à valider visuellement.
- L'accès réseau à Overpass depuis l'environnement cloud échoue (connexion coupée) : les données OSM doivent être exportées à la main (Tools/README.md).
- **Échelle** : Gare centrale → aéroport de N'djili ≈ 17 km à vol d'oiseau (≈ 12 min en Wewa à 90 km/h). Les missions chronométrées (ex. « avant 4 minutes ») sont impossibles à l'échelle réelle → décision requise : corridor compressé entre zones ou mission raccourcie.

## Étape 3 — missions
- **Carte (choix validé)** : zones à l'échelle réelle reliées par des corridors compressés (fonctionnalité `CorridorMap`, à construire).
- **Destinations hors zones V1** (révise le tableau précédent, plus fidèle à la géographie) : Gombe → zone *Gare centrale* (la gare est à Gombe selon OSM) ; Ngaliema → *Kintambo* (limitrophe) ; Masina → *Limete* (la plus proche).
- **14 missions en assets** (`Assets/Resources/Missions`, menu *Taxi Kin → Missions → Générer les 14 missions*). Un asset existant n'est jamais écrasé. 1 à 10 = V1 ; 11 à 14 (stationnement) = `availableInV1 = false`.
- **Verrouillage par fonctionnalités** : chaque mission déclare les systèmes dont elle dépend (IA de course, pluie, trafic dense, évitement, corridor, stationnement). `MissionManager.availableFeatures` liste ce qui existe ; `ignoreFeatureGating = true` (par défaut) permet de tout tester avec les points de repli. **À passer à `false` avant la sortie.**
- **Déblocages en jouant** : mission 5 débloque le Taxi, mission 7 débloque le Bus 207 (prêt du véhicule pendant la mission). Événement `OnUnlock` à brancher sur le garage (Étape 5) et la sauvegarde (Étape 6).
- **Récompenses** : première réussite = 100 % ; rejouer = 25 %.
- **Hors périmètre de cette étape** : IA des chauffeurs virtuels (missions 9, 10), détection des véhicules évités par le trafic, effets visuels de pluie, corridor entre zones, création des places de stationnement dans la carte.
- Les textes des missions ont des champs lingala (`titleLn`, `descriptionLn`) vides : à faire traduire.
- « Sans tomber » (mission 3) est approximé par un plafond de dommages (40 %), car les véhicules ne basculent pas.
