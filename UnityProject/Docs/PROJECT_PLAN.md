# Taxi Kin: Wewa, Taxi & 207 — Plan de projet

*Mis à jour le 2026-11-15 (itération 6). Chef de projet : Claude. Propriétaire du produit : le client.*

## 1. Statut en une phrase
Les **fondations des 7 étapes sont écrites** (commandes, missions, personnage, économie, boutique, sauvegarde, optimisation) et
**vérifiées par compilation hors Unity (0 erreur sur 78 fichiers)**, mais **le jeu n'a jamais été ouvert dans Unity, jamais lancé,
jamais testé sur téléphone**. La ville est vide (aucune donnée OpenStreetMap importée). Tout « fait » ci-dessous veut dire
« écrit et vérifié statiquement », pas « validé en jeu ».

## 2. Jalons
| Jalon | Objectif | Contenu | Critère de sortie | Dépend de |
|---|---|---|---|---|
| **M0 Fondations** | Socle technique | Étapes 1 à 7, vérification de compilation | ✅ Code complet, 0 erreur de compilation hors Unity | — |
| **M1 Première partie jouable** | Boucle complète sur un PC puis un téléphone | Ouverture dans Unity sans erreur, menu principal, zone pilote (Gare centrale), missions 1 et 2 de bout en bout | Un testeur joue 10 min sans blocage ; 30 FPS sur le téléphone de test | Retour console Unity, export OSM Gare centrale |
| **M2 Contenu V1** | Cahier des charges V1 | 5 zones, corridors compressés, trafic et IA de course, pluie, 10 missions + 2 quêtes, lingala relu | Les 10 missions sont jouables et terminables ; zones chargées < 5 s | M1, relecteur lingala |
| **M3 Qualité** | Prêt à être testé par des inconnus | Tests de performance (11 tests), équilibrage des prix et récompenses, accessibilité, tutoriel | Tableau de tests rempli, aucun bloquant ouvert | M2, téléphone d'entrée de gamme |
| **M4 Bêta fermée** | Retours de vrais joueurs | Google Play (test interne puis fermé), produits d'achat créés, politique de confidentialité, formulaire de sécurité des données, mesure anonyme, bouton « Donner mon avis », SDK publicitaire | 20+ testeurs, retours collectés, plantages < 1 % des sessions | M3, compte Play Console |
| **M5 Lancement V1** | Publication | Corrections de la bêta, fiche du magasin, classification d'âge | Validation Google Play | M4 |

## 3. Backlog priorisé
Effort : S (≤ 1 itération), M (2-3), L (4+). Risque : B/M/H.
| # | Tâche | Valeur | Effort | Risque | Statut / bloqué par |
|---|---|---|---|---|---|
| 1 | Ouvrir dans Unity 2022.3, corriger les erreurs de la console | Critique | S | M | **Client** : coller la console |
| 2 | Menu principal et enchaînement des écrans (remplace les boutons de test) | Critique | M | B | ✅ Écrit et vérifié par compilation (itération 2) ; à tester en jeu |
| 3 | Import OSM de la Gare centrale + vérification visuelle | Critique | M | M | ✅ Données importées (itération 3), géométrie vérifiée hors Unity ; à voir dans Unity |
| 4 | Test sur téléphone d'entrée de gamme (11 tests de `PERFORMANCE.md`) | Critique | S | H | **Client** : téléphone + build |
| 5 | Corridors compressés entre zones + chargement de la zone suivante | Haute | L | M | ✅ Écrit et vérifié (compilation + logique de guidage simulée, itération 4) ; à tester en jeu |
| 6 | Trafic : IA de circulation et détection des véhicules évités | Haute | L | M | ✅ Écrit et validé hors Unity (itération 5) ; à tester en jeu. Reste : piétons, feux, nids-de-poule, zones inondées, contrôles de police |
| 7 | Chauffeurs virtuels (courses, missions 9 et 10) | Haute | L | H | ✅ Écrit et validé hors Unity (itération 5) ; à tester en jeu |
| 8 | Pluie, route glissante, nuit | Moyenne | M | B | ✅ Pluie et route glissante écrites et validées hors Unity (itération 6) ; reste la **nuit** et les zones inondées |
| 9 | Autres zones (Matonge, Kintambo, Limete, N'djili) | Haute | M | M | ✅ Données importées (itération 3) ; reste à les voir dans Unity et à vérifier les repères |
| 10 | Tutoriel de première partie | Haute | M | B | ✅ Écrit (itération 6) ; à tester en jeu, textes lingala à traduire |
| 11 | Relecture du lingala par un locuteur natif + traduction des écrans | Haute | S | M | **Client** : relecteur |
| 12 | Retours joueurs : bouton « Donner mon avis », mesure anonyme avec consentement | Haute | M | M | **Client** : adresse de contact, accord sur la mesure |
| 13 | Équilibrage prix / récompenses / difficulté | Haute | M | M | Après 4 et 12 |
| 14 | Sons : klaxon, moteur, musique | Moyenne | M | B | **Client** : fichiers audio ou accord pour en générer |
| 15 | Vrais modèles 3D (véhicules, bâtiments, avatar) + LOD | Moyenne | L | M | Après 4 (mesurer d'abord) |
| 16 | SDK publicitaire récompensée + produits Google Play + reçus côté serveur | Haute | M | H | M4 ; **Client** : compte Play Console |
| 17 | Classements locaux (avatar affiché) | Basse | S | B | Après 2 |
| 18 | Missions de stationnement (11 à 14) | Basse | M | M | Après la V1 |
| 19 | iOS | Basse | L | M | Après la V1 |

## 4. Décisions prises par le chef de projet
| # | Décision | Raison |
|---|---|---|
| D1 | Unity 2022.3 LTS, URP, ancien système d'entrée | Stable, léger sur mobile ; le code vérifié cible C# 9 |
| D2 | Carte : zones à l'échelle réelle + corridors compressés | Choix du client ; chronos de missions tenables |
| D3 | V1 = 10 missions + 2 quêtes ; stationnement après la V1 | Le stationnement exige une physique de précision, risque élevé |
| D4 | Publicités : récompensées et facultatives uniquement | Cahier des charges ; jamais pendant la conduite |
| D5 | Prix en Kin Gold et paquets = **provisoires**, gelés pour la bêta puis ajustés avec les retours | Aucune donnée réelle avant la bêta |
| D6 | Vérification de compilation hors Unity à chaque modification (`Tools/verify`) | Seul filet disponible sans Unity |
| D7 | ~~Ne pas ajouter de nouveau gros système avant M1~~ **Levée le 2026-11-15 sur demande du client** (corridors) ; chaque ajout reste vérifié par compilation et simulation | Le client préfère avancer ; le risque est assumé |
| D8 | Retours joueurs : bouton dans le jeu + avis du magasin + mesure anonyme **sur consentement** | Boucle d'amélioration demandée, sans collecte cachée |
| D9 | Wewa gratuit ; taxi et 207 débloquables en jouant (missions 5 et 7) | Cahier des charges |

## 5. Registre des risques
| # | Risque | Prob. | Impact | Parade | Responsable |
|---|---|---|---|---|---|
| R1 | Le projet ne compile pas ou ne se lance pas dans Unity (jamais ouvert) | Moyenne | Élevé | Script de compilation (fait) ; corriger dès le retour de la console | Claude |
| R2 | Données OSM de Kinshasa incomplètes ou fausses | Moyenne | Élevé | Contrôle visuel zone par zone ; retouches manuelles des repères | Claude + client |
| R3 | Performance insuffisante sur téléphones d'entrée de gamme | Moyenne | Élevé | Qualité automatique, plafonds, tests sur appareil dès M1 | Claude + client |
| R4 | Unity IAP non testé (API 4.x vs 5.x) | Moyenne | Moyen | Fichier unique à adapter ; test en bêta interne | Claude |
| R5 | Lingala approximatif | Élevée | Moyen | Relecteur natif avant M4 | Client |
| R6 | Équilibrage de l'économie mal calibré (frustration ou inflation) | Élevée | Moyen | Bêta + mesure ; prix modifiables en un seul fichier | Claude |
| R7 | Rejet par Google Play (politique de données, achats, âge) | Moyenne | Élevé | Liste de conformité (§7) ; relecture avant M4 | Client + Claude |
| R8 | Trop de périmètre pour une V1 (IA de course, météo, 5 zones) | Élevée | Élevé | Jalons ; couper d'abord missions 9-10 si besoin | Claude |
| R9 | Données OSM / marques : attribution oubliée ou marque réelle utilisée | Basse | Moyen | Mention OSM dans « À propos » ; aucune marque (cahier des charges) | Claude |
| R10 | Sauvegarde corrompue ou perdue | Basse | Élevé | Écriture atomique + copie `.corrupt` (fait) | Claude |

## 6. Ce dont j'ai besoin du client (par ordre d'utilité)
1. Ouvrir le projet dans Unity 2022.3 (URP) et **coller les erreurs de la console** (même « aucune »).
2. ~~Exporter les données OpenStreetMap~~ **Fait par le chef de projet** : les 5 zones sont importées (`Tools/fetch_zone.py`).
3. Un **téléphone Android d'entrée de gamme** pour les tests de `PERFORMANCE.md`.
4. Un **relecteur lingala natif**.
5. Une **adresse de contact** pour le bouton « Donner mon avis » et la politique de confidentialité.
6. Un **compte Google Play Console** (frais uniques) pour la bêta fermée.

## 7. Liste de conformité (à valider avant la bêta ; ce n'est pas un avis juridique)
- [ ] Politique de confidentialité publiée (URL) ; formulaire « Sécurité des données » rempli
- [ ] Mention « © contributeurs OpenStreetMap » dans l'écran « À propos »
- [ ] Achats : produits créés, descriptions claires, prix visibles, pas d'obligation d'achat (déjà respecté)
- [ ] Classification de contenu (accidents, conduite : sans violence)
- [ ] Mesure anonyme uniquement après consentement ; pas de donnée personnelle superflue
- [x] Noms d'entreprises retirés des données de carte (hôtels, stations-service, arrêts…) ; aucun logo
- [ ] Noms de véhicules génériques (« Wewa », « 207 » à vérifier côté marque)
- [ ] Si le public inclut des enfants : règles « familles » de Google Play

## 8. Boucle d'amélioration d'après les retours des joueurs
1. **Collecter** : bouton « Donner mon avis » (envoie un message avec la version et l'appareil), avis du magasin, mesure anonyme sur consentement (missions abandonnées, qualité baissée automatiquement, achats refusés faute de fonds).
2. **Trier** chaque semaine : bloquant / gênant / souhait ; regrouper par thème (commandes, difficulté, prix, performance).
3. **Décider** : tout changement de valeur (sensibilité, prix, récompenses, chronos) se fait dans un seul endroit (`ShopCatalog`, `MissionData`, `QualityManager`) et se note dans `Docs/DECISIONS.md`.
4. **Livrer** une mise à jour, **vérifier** que le retour concerné disparaît.

## 9. Définition de « terminé » (pour toute fonctionnalité)
Compile sans erreur (`Tools/verify/check_compile.sh`) · testée en jeu dans Unity · testée sur le téléphone de test si elle touche la
performance ou les commandes · textes en français et lingala · sauvegarde vérifiée (fermer et rouvrir) · notée dans `DECISIONS.md`.
