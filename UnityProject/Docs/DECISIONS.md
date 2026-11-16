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

## Étape 4 — personnage et personnalisation
- **Chargement à la demande** : `ClothingItem` ne contient qu'un chemin Resources (`prefabPath`). `AvatarCatalog` charge uniquement les fiches ; le prefab d'un vêtement n'est chargé qu'à l'équipement (`AvatarCustomizer`). Aucun catalogue 3D en mémoire.
- **Contenu de test** : le menu *Taxi Kin → Avatar → Générer le contenu de test* crée 18 articles (visage, cheveux, haut, bas, chaussures, accessoires) et le prefab `Avatar` en formes simples. Les vrais modèles remplaceront les prefabs sans changer le code. Un seul accessoire à la fois pour l'instant.
- **Aperçu 3D** : petite scène à (1000, 0, 1000) filmée vers une texture 512×768 ; la caméra n'est active que pendant l'écran « Mon chauffeur ». Rotation au doigt (`AvatarPreviewDrag`).
- **Premier lancement** : `CharacterCreatorUI` s'ouvre si `ProfileManager.HasProfile` est faux ; l'annulation est alors désactivée.
- **Avatar partout** : `AvatarDisplay` (menu, garage, missions, courses, victoire, classements) suit le profil. Seule l'instance du monde est en place ; les écrans de menu/garage/victoire/classements l'utiliseront quand ils existeront.
- **Véhicules** : couleur de carrosserie et de toit (5 jaunes en tête de palette + 7 autres), 3 jantes, 3 autocollants (dont 1 exclusif), plaque (3 à 10 caractères, filtrée), 4 améliorations niveaux 0-3 (moteur, freins, pneus, carrosserie). Taxi jaune #FFD400 par défaut ; Wewa rouge, bus bleu.
- **Améliorations** : bonus de +8 % d'accélération et +5 % de vitesse max par niveau de moteur, +10 % de freinage, +5 % de maniabilité, −12 % de dégâts par niveau. Valeurs d'essai à équilibrer. **Gratuites tant que la boutique n'existe pas (Étape 5).**
- **Sauvegarde** : le profil, l'avatar, les objets débloqués et la personnalisation de chaque véhicule passent par `SaveManager` (chargement paresseux, sauvegarde à chaque changement). L'Étape 6 complètera (missions terminées, paramètres, langue, réinitialisation).
- **Quêtes** (`CustomizationQuests`) : « Crée ton identité » se termine à l'enregistrement du profil (500 Kin Coins + tenue jaune de départ) ; « Le jaune de Kin » exige un choix de jaune pour le taxi dans le garage puis une course réussie (1 000 Kin Coins + autocollant exclusif).
- **Limite connue** : les libellés des écrans « Mon chauffeur » et « Garage » sont en français uniquement (lingala à l'Étape 7). Le garage actuel personnalise le véhicule actif ; l'aperçu 3D de showroom viendra avec la scène garage (Étape 5).
- Les missions de personnalisation ne sont jamais proposées comme courses (`IsPlayable` les exclut).

## Étape 5 — économie, garage, boutique
**Principe** : aucun achat n'est obligatoire. Le taxi et le 207 se débloquent en jouant (missions 5 et 7, `OnUnlock` → `ShopManager`). Les fonds manquants sont signalés avec le montant manquant ; aucun écran ne bloque la progression.

**Prix du cahier des charges (repris tels quels)** : Wewa de base gratuit ; Wewa amélioré 5 000 KC / 1,99 USD ; Taxi 15 000 KC / 2,99 USD ; Taxi premium 35 000 KC / 4,99 USD ; Bus 207 50 000 KC / 5,99 USD ; Pack complet 9,99 USD ; Sans publicités 2,99 USD.

**Prix PROVISOIRES à valider/équilibrer** (non fournis dans le cahier des charges) :
| Article | Prix d'essai |
|---|---|
| Vehicules en Kin Gold | Wewa amélioré 40 · Taxi 120 · Taxi premium 250 · Bus 207 350 |
| Skins (Kin Gold) | Taxi Or 150 · Wewa Néon 80 · 207 Carnaval 120 |
| Sans publicités (Kin Gold) | 300 |
| Packs de Kin Gold (consommables) | 100 → 0,99 USD · 550 → 4,99 USD · 1 200 → 9,99 USD |
| Pack de 3 pleins (consommable) | 0,99 USD ou 30 Kin Gold |
| Bonus « pièces x2 » (1 mission) | 40 Kin Gold |
| Couleurs non jaunes | 500 KC (les 5 jaunes sont gratuits) |
| Jantes chrome / or | 800 / 1 500 KC |
| Autocollants Kin / rayures | 300 / 500 KC (l'exclusif est une récompense de mission) |
| Plaque personnalisée | 200 KC par changement |
| Améliorations (par niveau 1→3) | 1 000 / 2 500 / 5 000 KC |
| Carburant | 20 KC par litre ; plein gratuit avec un jeton |
| Réparation | 15 KC par % de dommages (× 0,6 Wewa, × 1 taxi, × 1,8 bus) |
| Vêtements | Veste 800 · Bottines 600 · Tresses 300 · Casquette 300 · Foulard 400 KC |
Le Pack complet = tous les véhicules + sans publicités + autocollant exclusif (pas de Kin Gold : un non consommable restauré ne doit pas redonner de la monnaie).

**Véhicules et variantes** : 5 entrées de garage (Wewa, Wewa amélioré, Taxi, Taxi premium, Bus 207). Les variantes « amélioré » et « premium » ajoutent +1 niveau gratuit à chaque amélioration (plafonné à 3). Un seul prefab d'aperçu est chargé à la fois (`Resources/Vehicles/Preview`).

**Achats réels (Unity IAP)** :
- Non consommables : véhicules, sans publicités, pack complet. Consommables : Kin Gold, pack de carburant.
- `UnityIapBackend` (compilé seulement si `UNITY_PURCHASING`, c.-à-d. paquet Unity IAP installé) cible l'API classique 4.x et **n'a pas été testé** ; avec IAP 5.x, seul ce fichier est à adapter.
- Sans le paquet : boutique SIMULÉE uniquement dans l'éditeur / version de développement. Une version de production sans IAP refuse les achats.
- À faire avant la sortie : validation des reçus côté serveur, « Restaurer les achats » (iOS), identifiants de produits à créer dans Google Play Console.
- Les prix du magasin remplacent automatiquement les prix indicatifs en USD quand ils sont connus.

**Publicités** : l'interstitiel est supprimé ; il ne reste que la publicité récompensée facultative (stub, SDK à choisir). « Sans publicités » est sauvegardé.

**Confirmation anti-erreur** : tout achat en Kin Coins / Kin Gold demande une seconde touche dans les 4-5 s.

**Sauvegarde** : soldes, véhicules débloqués, véhicule choisi, jetons de carburant, bonus et état (carburant, dommages) de chaque véhicule sont sauvegardés (fin de mission, achat, mise en pause). L'Étape 6 couvrira le reste (missions terminées, paramètres, réinitialisation).

**Limites connues** : aucune mission ne donne de Kin Gold pour l'instant (« récompenses rares ») ; libellés des écrans en français uniquement ; l'aperçu garage utilise des cubes.

## Étape 6 — sauvegarde automatique
**Contenu sauvegardé** (`taxikin_save.json`, `Application.persistentDataPath`) : nom du joueur et avatar, niveau, XP, Kin Coins, Kin Gold, véhicules débloqués, véhicule sélectionné, améliorations et couleurs/jantes/autocollants/plaque de chaque véhicule, carburant et dommages, objets débloqués, jetons et bonus, missions terminées, paramètres de contrôle (mode, taille des boutons, sensibilité, inversion), langue, suppression des publicités.

**Quand** : après chaque mission (réussie ou non), chaque achat, chaque amélioration, chaque changement de personnalisation, à la fermeture des réglages, à la mise en pause (le plus fiable sur Android) et à la fermeture de l'appli. Les réglages à curseur passent par `SaveSoon()` : au plus une écriture par seconde.

**Robustesse** : écriture atomique (fichier `.tmp` puis remplacement) ; si l'appli est coupée en plein remplacement, le `.tmp` complet est repris au démarrage ; un fichier illisible est copié en `.corrupt` avant de repartir de zéro (jamais écrasé en silence).

**Réinitialisation** : bouton « Réinitialiser ma progression » dans les réglages → fenêtre de confirmation explicite listant ce qui est effacé, bouton rouge bloqué 3 s, bouton « Non, garder ma progression » mis en avant. `SaveManager.ResetProgress()` vide la sauvegarde puis déclenche `SaveManager.ProgressReset` : chaque manager (économie, profil, missions, véhicules, garage, quêtes) se remet à zéro en mémoire **sans recharger la scène**, puis l'écran « Mon chauffeur » se rouvre comme au premier lancement. Les achats en argent réel (non consommables) restent récupérables via le magasin ; la monnaie déjà dépensée ne l'est pas (indiqué dans la confirmation).

**Migration** : `PlayerPrefs` n'est plus utilisé (réglages et langue sont dans la sauvegarde). Aucune migration : le jeu n'a pas encore été publié.

**Limites** : les réglages d'Étape 7 (qualité graphique, son, musique, vibrations, ombres) ne sont pas encore dans `SettingsData` ; pas de sauvegarde dans le cloud ; pas de chiffrement (le fichier est modifiable par un joueur sur appareil rooté : sans importance tant qu'il n'y a pas de classement en ligne).

## Étape 7 — optimisation Android
Voir `Docs/PERFORMANCE.md` (budgets, ce qui est en place, procédure de test sur téléphone d'entrée de gamme).
- **Rien n'a été mesuré sur appareil** ; les objectifs 30/60 FPS, < 300 Mo, mémoire et batterie restent à vérifier.
- Réglages ajoutés : qualité (Auto/Basse/Moyenne/Haute), ombres (selon qualité/aucune/basses/hautes), son, musique, vibrations,
  taille de l'interface, en plus des commandes et de la langue.
- La qualité par défaut est choisie selon la RAM et les cœurs. En mode « Auto », elle peut baisser toute seule (jamais monter)
  si la cadence reste sous 80 % de la cible pendant 8 s ; le niveau atteint est mémorisé. Si le joueur choisit Basse, Moyenne
  ou Haute à la main, la qualité reste fixe (aucune baisse automatique).
- Aucun fichier audio fourni : les clips (klaxon, musique) restent à ajouter.

## Vérification de compilation (hors Unity)
`bash Tools/verify/check_compile.sh` compile les 78 fichiers C# en C# 9 contre l'API Unity 2021.3 (assemblages de référence NuGet non officiels, téléchargés dans un dossier temporaire, non versionnés) avec des substituts pour Unity UI et UnityEditor.
- **Résultat : 0 erreur, 0 avertissement.** Une erreur volontaire est bien détectée (test de sensibilité).
- **Défauts trouvés et corrigés grâce à cet outil** : ambiguïté `UnityEventTools` (UnityEngine.Events / UnityEditor.Events) dans 4 fichiers Editor ; champ inutilisé dans `MissionManager`.
- **Ce que ça ne prouve pas** : compatibilité exacte avec l'API d'Unity 2022.3 (la référence est 2021.3) ; exactitude de mes substituts Unity UI / UnityEditor (écrits de mémoire) ; code sous `#if UNITY_6000_0_OR_NEWER` et `#if UNITY_PURCHASING` ; tout le comportement à l'exécution (le jeu n'a jamais été lancé).
- La compilation dans Unity 2022.3 reste la vraie référence : ouvrir le projet et relever les erreurs de la console.

## Menu principal (itération 2)
- Écrans : menu (Jouer, Missions, Garage, Boutique, Mon chauffeur, Réglages, Reprendre), liste des missions (terminée / jouable / « Bientôt »), bouton « Menu » du HUD, bouton « Menu » de l'écran de fin de mission.
- L'avatar du joueur s'affiche en 3D dans le menu (même aperçu que « Mon chauffeur », caméra active seulement quand un de ces écrans est ouvert).
- Ouvrir le menu en pleine mission met le jeu en pause ; « Reprendre » restaure l'état exact d'avant. Premier lancement : « Mon chauffeur » d'abord, puis le menu.
- Les boutons de test (véhicule, mode de contrôle) sont rangés dans `OutilsDev`, **désactivé dans les versions de production** (`DevOnly`).
- L'attribution OpenStreetMap figure en bas du menu.
- Ordre d'affichage : le menu est sous les écrans qu'il ouvre (créateur, garage, boutique, réglages) ; le générateur de scène doit l'appeler en dernier.

## Données de carte (itération 3)
- Les 5 zones sont importées avec `Tools/fetch_zone.py` (API officielle OpenStreetMap). Fichiers dans `Assets/Resources/Zones` (~700 Ko au total).
- Géométrie vérifiée hors Unity : tracé de la Gare centrale contrôlé visuellement (trame de rues, ronds-points, gare à l'origine, fleuve au nord) ; triangulation des toits testée sur les 6 262 immeubles (6 252 réussis).
- **Conformité** : les noms d'entreprises (hôtels, stations-service, arrêts, gares routières) sont retirés à la conversion ; on garde seulement les noms de gares, aéroports et marchés.
- **Hypothèse à vérifier** : l'aéroport de N'djili est un repère posé à la main (position de l'aérodrome donnée par Nominatim) car OpenStreetMap le décrit comme une zone et non un point. Même chose possible pour d'autres repères : tous les points « gare » dans OSM sont des points, mais la gare « Kinshasa Est » est celle retenue comme « Gare centrale ».
- Les missions dont la destination est « le centre d'une zone » (sans type de repère) utilisent encore le point de repli : à corriger avec les corridors entre zones.

## Corridors entre les zones (itération 4)
- **Principe (choix du client)** : zones à l'échelle réelle, posées dans un repère global « compressé » (directions réelles conservées), reliées par des corridors de 900 à 1 200 m. Disposition calculée par `Tools/build_world.py` → `Resources/Zones/world.json`. Liens : Gare–Matonge, Gare–Kintambo, Matonge–Limete, Limete–N'djili (arbre ; pas de boucle).
- **Raccord** : le corridor part de l'extrémité d'une route principale (primaire/secondaire) d'une zone et arrive à l'extrémité d'une route principale de l'autre : la circulation est continue. Longueur allongée automatiquement si deux zones se chevauchent.
- **Chargement** : `WorldManager` charge une zone à < 450 m, la décharge à > 700 m. Les repères (gare, aéroport, hôtels, arrêts) existent pour toutes les zones, même non chargées ; une mission peut donc viser une zone lointaine. Au départ d'une mission, la zone de départ est chargée (sol prêt) avant de poser le véhicule.
- **Limites du monde** : sol seulement sous les zones et les corridors ; murs invisibles et glissières le long des corridors ; `OutOfBoundsGuard` remet le véhicule sur la dernière position sûre s'il tombe sous -8 m.
- **Guidage** : la flèche et la « distance restante » suivent le vrai chemin (zone → corridor → zone), plus à vol d'oiseau. L'algorithme a été porté en Python et simulé sur 5 trajets : distance restante jamais croissante, arrivée à 0, aucun point hors zone/corridor.
- **Conséquence importante sur les missions — décision à valider par le client** : à cette échelle, les chronos d'origine du cahier des charges sont **impossibles** (ex. mission 4 : 6,5 km en 4 min exige 27 m/s de moyenne, au-dessus de la vitesse max du Wewa, 25 m/s). Durées révisées (durée conseillée = longueur / 60 % de la vitesse max) : mission 2 : 2 min 30 → **4 min** ; mission 4 : 4 min → **7 min 30** ; mission 6 : 8 min → **12 min 30** ; mission 10 : 10 min → **12 min 30** ; mission 7 : 15 min (inchangée). Alternative : raccourcir les zones (rayon 600 m) ou les corridors, au prix d'une ville plus petite.
- **Mission 8 (ligne de 207)** : aller-retours Gare ↔ centre de Matonge (≈ 3,5 km par trajet) : à surveiller pour la durée de la mission.
- Les assets de mission générés ne sont jamais écrasés : pour appliquer les nouvelles durées sur des assets existants, les supprimer puis relancer *Taxi Kin → Missions → Générer les missions*.

## Trafic et IA de course (itération 5)
**Réseau routier** : `RoadGraph` est construit au démarrage à partir des fichiers de zone et des corridors (6 846 nœuds, 97 % dans une seule composante connexe ; tous les repères de mission y sont reliés ; les corridors s'accrochent aux routes à 0,0 m). Un carrefour = deux routes qui partagent le même point (arrondi à 10 cm). `build_world.py` recale désormais les repères et le centre de chaque zone sur la route la plus proche (84 repères déplacés) : un départ ou une destination hors route n'était pas atteignable en voiture (l'aéroport était à 73 m de toute route).

**Trafic** (`TrafficManager` + `VehicleAI`) : voitures (60 %, beaucoup de taxis jaunes), motos (25 %), camions (15 %). Promenade aléatoire à droite, vitesse limitée par la classe de route (4 à 14 m/s), ralentissement derrière un obstacle, blocage détecté (recyclage). Apparition à 100-250 m du joueur sur un sol existant, jamais à vue juste devant ; disparition à > 300 m. Plafond 6 / 12 / 20 selon la qualité, 30 demandés par une mission « trafic dense » (le plafond l'emporte). **Évité** = passer à moins de 6 m à plus de 12 km/h sans choc (mission 2).

**IA de course** (`RaceManager`) : compte à rebours 3-2-1-PARTEZ (joueur et coureurs immobilisés), coureurs sur le plus court chemin routier (A*). **Un coureur loin du joueur n'est pas simulé physiquement** : sa progression est calculée, il devient un vrai véhicule à < 160 m du joueur et redevient virtuel à > 280 m. Résultat : pas besoin de charger toutes les zones du parcours, aucun véhicule ne roule sur un sol absent, coût constant. Classement en direct dans le HUD ; la place du joueur est fixée à son arrivée.

**Validation hors Unity** (`Tools/verify/sim_ai.py`, mêmes constantes que `VehicleAI.cs`) sur les vrais itinéraires : écart maximal à la route **4,0 m**, **0 %** du temps hors route, pour Wewa et taxi, en course et en trafic (trafic ≈ 28 km/h de moyenne). Vitesse moyenne réelle = 88 à 91 % de vmax x skill (d'où `VirtualSpeedFactor` = 0,89, pour que coureurs virtuels et physiques avancent pareil).

**Niveau des adversaires** (`raceSkill`, ±0,08 selon le coureur) : mission 9 et 10 = 0,70. Le meilleur adversaire de la mission 9 (Wewa, 3,7 km) finit en ≈ 214 s (62 km/h de moyenne = 69 % de la vitesse max : exigeant) ; celui de la mission 10 (taxi, 10,3 km) en ≈ 688 s (53 km/h), pour un chrono de 840 s. **À régler avec de vrais joueurs.**

**Missions** : durées recalculées sur les distances routières réelles (mission 2 : 4 min 30 ; 4 : 8 min ; 6 et 10 : 14 min ; 7 : 15 min). Mission 9 a maintenant un vrai trajet (Gare centrale → Matonge). `availableFeatures` inclut TrafficAvoidance, DenseTraffic, RaceAI, CorridorMap : seules la pluie (mission 3) et le stationnement restent « Bientôt » quand le verrouillage est actif.

**Limites** : pas de dépassement actif (un coureur ralentit derrière un véhicule), pas de feux ni de piétons ni de nids-de-poule ni de zones inondées ni de contrôles de police (V1 : « trafic simple »), sens uniques ignorés, modèles en cubes, aucun test en jeu. Le coût CPU de 20 véhicules physiques + IA est à mesurer sur le téléphone de test.

## Pluie et tutoriel (itération 6)
**Pluie** (`WeatherManager`, `RainEffect`) : soleil, pluie légère, pluie forte, avec transition douce de 4 s.
- **Adhérence** 1,0 / 0,8 / 0,6 : sous la pluie forte, accélération −20 %, freinage −40 %, direction −12 %, adhérence latérale ÷ 2,8 (la voiture dérape davantage). Formules dans `VehicleController`.
- **Visibilité** : brouillard exponentiel² (≈ 90 % à 150 m par pluie forte) et soleil à 55 % ; aucune lumière dynamique ajoutée.
- **Pluie visible** : un seul système de particules (traînées) qui suit la caméra, 900 particules max, matériau transparent sans éclairage (`Mobile/Particles/Alpha Blended`), densité 40 / 70 / 100 % selon la qualité. Créé par code.
- **IA** : trafic et coureurs ralentissent de 10 % (pluie légère) à 20 % (pluie forte).
- **Validation hors Unity** (`Tools/verify/sim_ai.py` étendu au glissement latéral) : sur 18 simulations (2 trajets × 3 véhicules/modes × 3 météos), écart maximal à la route **3,8 m**, **0 %** hors route ; trajets +10 % de durée (légère) et +20 % (forte).
- Missions : la météo d'une mission s'applique au départ avec un message (« Pluie forte : la route est glissante ! ») et le beau temps revient à la fin. La mission 3 est maintenant jouable (`Rain` ajouté aux fonctionnalités disponibles).
- **Pas encore** : nuit, zones inondées, essuie-glaces visibles (le bouton agit sur l'état, sans effet visuel), bruit de pluie. Le shader de la pluie (`Mobile/Particles/Alpha Blended`) est à vérifier visuellement sous URP.

**Tutoriel** (`TutorialManager`) : 8 étapes en conduite libre qui détectent la vraie action (direction, accélération ≥ 20 km/h, arrêt après ≥ 15 km/h, klaxon, changement de caméra, client à bord), anneau pulsant sur la commande, texte adapté au mode de contrôle (volant / flèches / inclinaison). Proposé une seule fois après la création du chauffeur (ou au démarrage pour un profil existant) ; « Passer » est définitif ; « Revoir le tutoriel » dans les Réglages. Démarrer une mission l'interrompt. Sauvegardé (`tutorialDone`), remis à zéro par la réinitialisation. Textes dans `Loc` (français ; lingala à traduire).

**Défaut trouvé en préparant le tutoriel et corrigé** : le Wewa n'avait qu'un bouton « Passager » qui ne faisait que **prendre** le client, jamais le déposer : la mission 1 était impossible. Le bouton alterne maintenant prendre/déposer (libellé dynamique) et affiche un message si on tente de déposer avant la destination. Le vérificateur de compilation a aussi trouvé une ambiguïté `QualityLevel` (type existant aussi dans Unity), levée par un alias.
