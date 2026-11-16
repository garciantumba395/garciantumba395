# Obtenir la version d'essai (APK) — votre part : une seule fois, ~10 minutes

Le code est construit dans le cloud par GitHub (GameCI). Unity exige une licence liée à **votre** compte Unity (gratuit : Personal).

## Étape unique (une fois)
1. Dépôt GitHub → onglet **Actions** → ouvrir l'exécution « Taxi Kin - fichier d'activation Unity » → télécharger l'artefact **Manual-Activation-File** (fichier `.alf`).
2. Aller sur https://license.unity3d.com/manual, envoyer le `.alf`, choisir « Unity Personal », télécharger le fichier `.ulf`.
3. Dépôt → **Settings → Secrets and variables → Actions → New repository secret**, créer :
   - `UNITY_LICENSE` : coller tout le contenu du `.ulf`
   - `UNITY_EMAIL` : l'e-mail de votre compte Unity
   - `UNITY_PASSWORD` : le mot de passe de votre compte Unity
   (à saisir uniquement dans GitHub, **jamais dans le chat**.)
4. Me dire « secrets ajoutés ».

## Ensuite (automatique)
Je lance la construction, je lis les erreurs et je les corrige. Vous téléchargez **TaxiKin-APK** (onglet Actions → l'exécution « Taxi Kin - APK Android » → Artifacts) et l'installez sur un téléphone Android (autoriser les sources inconnues).

## Limites à connaître
- Jamais compilé dans Unity : des erreurs sont probables au premier essai, je les traite en boucle.
- Projet sans URP (matériaux Standard) : rendu simple, c'est voulu pour la première version.
- Achats intégrés et lingala non actifs dans l'APK d'essai.
