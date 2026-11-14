# Versions installables : PWA, Android (APK), Windows (.exe)

L'application est un site Laravel/Livewire hébergé : les trois versions sont des **coquilles** qui affichent la même application web.
Aucune donnée n'est stockée dans l'application installée, et aucune logique métier n'y est dupliquée. **Elles ont besoin de l'URL
publique HTTPS du serveur de production** (hébergement non encore choisi, décision D9).

| Version | État | Où |
|---|---|---|
| **PWA** (installable depuis Chrome/Edge/Safari) | ✅ Prête et vérifiée en navigateur (service worker enregistré, page hors connexion, aucune page ni donnée mise en cache) | `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/` |
| **Android (.apk)** | 🟡 Projet Capacitor + workflow prêts, **non construit** ici (le SDK Android n'est pas téléchargeable depuis l'environnement de développement) | `packaging/android/`, `.github/workflows/build-apps.yml` |
| **Windows (.exe)** | 🟡 Projet Electron + workflow prêts, **non construit** ici (le binaire Electron et l'exécution Windows ne sont pas disponibles) | `packaging/windows/`, même workflow |

## Construire l'APK et le .exe

1. Héberger l'application en HTTPS (obligatoire : service worker, cookies `Secure`, 2FA).
2. Dans GitHub : *Actions > Build installable apps > Run workflow*, saisir l'URL publique (ex. `https://sms.votre-domaine.cd`).
3. Télécharger les artefacts `sms-marketing-android-apk` et `sms-marketing-windows-exe`.

Le workflow est **manuel**, sans secret, et ne déploie rien.

## Points à connaître

- **Signature** : l'APK est signé avec la clé de débogage (installation hors Play Store après autorisation des sources inconnues). Pour le Play Store : créer une clé de production (à conserver hors dépôt) et publier un AAB. L'installateur Windows n'est pas signé : Windows SmartScreen affichera un avertissement tant qu'un certificat de signature de code n'est pas acheté et configuré.
- **Identifiants** : `com.example.smsmarketing` est un identifiant provisoire à remplacer avant publication.
- **Icônes** : une icône provisoire (bulle de message) est fournie ; à remplacer par l'identité visuelle.
- **Sécurité** : la coquille Windows désactive l'intégration Node, active le bac à sable et ne laisse naviguer que sur l'origine de l'application ; les liens externes s'ouvrent dans le navigateur (https uniquement).
- **Mise à jour** : l'application web se met à jour côté serveur sans réinstaller ; seule la coquille nécessite une nouvelle version.
- **PWA et données** : le service worker ne conserve jamais de page HTML ni de réponse Livewire (confidentialité des contacts et messages).
