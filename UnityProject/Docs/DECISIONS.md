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
