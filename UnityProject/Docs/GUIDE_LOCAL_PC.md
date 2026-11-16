# Construire l'APK sur votre PC (Unity déjà installé et activé)

1. Unity Hub → **Installs** → votre Unity 2022.3 → **Add modules** → cochez **Android Build Support** (avec *OpenJDK* et *Android SDK & NDK Tools*) et **WebGL Build Support**.
2. Téléchargez le projet : page de la PR #10 sur GitHub → **Code → Download ZIP** (branche `ccr-0c5a5c15-u3h1h4`) et décompressez.
3. Unity Hub → **Add → Add project from disk** → dossier **UnityProject** → ouvrez avec 2022.3 (acceptez l'import, quelques minutes).
4. Si des erreurs rouges apparaissent dans **Console** : copiez-les ici, je les corrige.
5. Menu **Taxi Kin → Construire l'APK de test (tout en un)** : crée la scène, applique les réglages et produit `build/Android/TaxiKin.apk` dans le dossier du projet.
6. Copiez l'APK sur le téléphone et installez-le (autoriser les sources inconnues).
Version web : menu **Taxi Kin → Construire la version web (WebGL)** (résultat : `build/WebGL`).
