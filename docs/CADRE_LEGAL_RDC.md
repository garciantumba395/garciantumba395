# Cadre légal RDC — ce qui est établi et ce qui est appliqué (décision D4)

> **Ce document n'est pas un avis juridique.** Il rapporte ce que le texte lu dit et comment le produit en tient compte. À faire valider par un
> juriste congolais avant mise en production. Texte lu le 2026-11-14 : **Ordonnance-loi n° 23/010 du 13 mars 2023 portant Code du numérique**
> (publiée au Journal officiel le 11 avril 2023, en vigueur depuis le 13 mars 2023), via le PDF de 170 pages publié par Droit-Numérique.cd
> (`droitnumerique.cd/wp-content/uploads/2025/01/Code-du-numerique-I-RD-Congo.pdf`). **Ce PDF est une reproduction non officielle : vérifier chaque
> article au Journal officiel.** La loi n° 20/017 du 25 novembre 2020 (télécommunications) n'a pas été lue : elle reste à examiner (chevauchements signalés).

## 1. Règles applicables à la prospection par SMS (texte lu, citations abrégées)

| Art. | Ce que dit le texte | Application dans le produit |
|---|---|---|
| **2** | « Prospection directe : envoi de message destiné à promouvoir, directement ou indirectement, des biens, des services ou l'image d'une personne vendant des biens ou fournissant des services » ; « Consentement : manifestation de volonté expresse et non équivoque » | Toute campagne est traitée comme de la prospection directe |
| **66** | Toute publicité par communications électroniques « doit être clairement identifiée comme telle dès sa réception » ; elle « rend clairement identifiable son expéditeur » (nom, adresse géographique, coordonnées **y compris adresse courrier électronique**, « éventuellement » RCCM, numéro d'impôt, acte autorisant l'activité) ; à défaut, mention « **publicité** » claire, lisible, apparente | Pied de message automatique : mention « Publicité » + nom de l'expéditeur ; page publique du lien de désabonnement qui affiche l'**identité complète** ; identité légale **obligatoire** avant tout envoi réel |
| **68** | « Est interdite la prospection directe [...] de SMS [...] utilisant les données à caractère personnel d'un utilisateur qui n'a pas préalablement exprimé son consentement » ; « L'absence de réponse ne peut pas être considérée comme un consentement » ; « La charge de la preuve du consentement [...] incombe à la personne [...] à l'origine de la prospection » | Déjà en place : consentement préalable **avec preuve** (source, date, texte), revérifié à chaque envoi ; jamais de consentement implicite |
| **69** | Exception sans consentement préalable pour une **personne physique** si les coordonnées ont été recueillies lors d'une vente ou prestation, pour des produits **analogues** du même fournisseur, avec possibilité de s'opposer sans frais à chaque message ; personnes morales : coordonnées **impersonnelles** | **Non utilisée** : le produit exige toujours un consentement (choix plus strict). À rediscuter avec un juriste |
| **70** | Toute personne peut notifier sans frais ni justification qu'elle ne veut plus de prospection ; le fournisseur doit **(1) accuser réception sans délai, (2) agir dans un délai raisonnable, (3) tenir à jour la liste** des personnes opposées | Liste de désinscription par numéro (déjà) ; **lien public de désabonnement** avec accusé de réception affiché immédiatement. Accusé **par SMS** : non fait (nécessite un fournisseur réel, voir §3) |
| **71** | Exceptions interprétées plus strictement pour enfants, personnes âgées, malades, vulnérables | Pas de contrôle automatisable ; rappel dans l'aide au consentement |
| **72** | Interdit d'émettre des SMS de prospection « sans indiquer les moyens et les coordonnées valables auxquels le destinataire transmet une demande tendant à obtenir **sans frais**, que ces communications cessent » ; interdit de **dissimuler l'identité** (identité d'un tiers, informations falsifiées, objet sans rapport, incitation à visiter des sites tiers) | Pied de message avec **lien de désabonnement gratuit** ; l'expéditeur affiché est toujours l'entreprise réelle (aucun expéditeur libre) |
| **196** | Le responsable doit **pouvoir démontrer** le consentement ; demande de consentement **distincte** des autres questions, claire et simple ; **retrait à tout moment « à travers le même moyen utilisé pour le donner »** | Preuve conservée ; retrait par lien web (et par « STOP » avec un fournisseur) |
| **213** | Droit de s'opposer gratuitement ; une fois l'opposition admise, le responsable « n'utilise ni ne traite plus » les données | Suppression effective **immédiatement** et prioritaire sur tout consentement |
| **220** | À la collecte : identité et coordonnées du responsable, finalités, catégories, destinataires, droit de ne plus figurer au fichier, droit d'opposition gratuit… | Aide à la saisie de la preuve de consentement (contenu à conserver) |
| **348** | « Tout message électronique non sollicité envoyé sur la base de la collecte de données à caractère personnel doit contenir **un lien** pouvant permettre au bénéficiaire de se désabonner. » Amende de **500 000 à 2 000 000 FC** | **Lien de désabonnement dans chaque message** (pied automatique, non désactivable en production) |
| **350** | Transfert de données sans information préalable ou malgré l'opposition : 6 mois à 2 ans de servitude pénale et 2 à 5 millions FC | Aucun transfert à des tiers par le produit ; opposition appliquée immédiatement |

## 2. Formalités et obligations **hors code** (à traiter par le propriétaire du produit)

- **Art. 186** : le traitement de données personnelles est soumis à une **déclaration préalable** auprès de l'Autorité de protection des données (APD), qui délivre un récépissé.
- **Art. 187, point 3** : **autorisation préalable** de l'APD pour « le traitement portant sur un numéro national d'identification ou tout autre identifiant de même nature, **notamment les numéros de téléphone** ». Une plateforme de SMS marketing traite des numéros de téléphone : **à examiner d'urgence avec un juriste**.
- **Art. 189, point 4** : dispense de déclaration préalable lorsque le responsable a désigné un **délégué à la protection des données** (sauf transfert vers un pays tiers) : option à discuter ; ne dispense pas d'une éventuelle autorisation de l'art. 187.
- **Autorité** : les articles 262 à 270 créent l'APD ; elle n'est pas encore constituée, ses missions sont attribuées **provisoirement à l'ARPTIC** (arrêté ministériel du 17 août 2024) ; plusieurs décrets d'application restent à prendre.
- **Qui est responsable de traitement** : le client de la plateforme (qui collecte le consentement) ou la plateforme ? Conditionne les contrats et la politique de confidentialité (art. 220 : information des personnes).
- **Art. 193, point 3** : conservation « pendant une durée n'excédant pas celle nécessaire à la réalisation des finalités » : le texte **ne fixe pas de durée chiffrée** ; à définir (décision D9). Attention : la charge de la preuve du consentement (art. 68) plaide pour conserver les preuves tant qu'un litige est possible.

## 3. Ce qui n'est PAS dans le texte lu (donc non encodé)

- **Aucune plage horaire** d'envoi : le mot « horaire » n'apparaît pas dans le Code du numérique. Aucune restriction horaire n'est appliquée (un réglage commercial reste possible).
- **Durée de conservation chiffrée** : non fixée (voir §2).
- **Enregistrement du sender ID auprès des opérateurs** et règles propres aux opérateurs/ARPTIC pour les SMS en masse : à vérifier (décision D3), hors du Code du numérique.
- **Loi n° 20/017** (télécommunications, art. 126 à 133 sur la vie privée) : non lue.
- **Accusé de désinscription par SMS** (art. 70-1) : un accusé est affiché sur la page web ; l'envoi d'un SMS d'accusé exige un fournisseur réel et une règle particulière (il s'adresse à un numéro désinscrit) : à concevoir avec D1.

## 4. Ce que le produit applique (code)

1. **Pied de message légal automatique** sur chaque campagne : `Publicité - {nom de l'expéditeur}. Désabonnement : {lien}` (art. 66, 72, 348). Inclus dans le calcul des segments et des coûts (il consomme des caractères : un message de 100 caractères passe souvent à 2 segments).
2. **Lien public de désabonnement** court (`/d/{code}`), sans compte : la page affiche l'identité de l'expéditeur (art. 66), demande une **confirmation** (un simple chargement de la page ne désinscrit jamais : les robots de prévisualisation des messageries ouvrent les liens), enregistre la désinscription **immédiatement** et accuse réception (art. 70, 213). Limitation de débit, codes aléatoires non devinables.
3. **Identité légale de l'expéditeur** (nom, adresse, e-mail ; RCCM, numéro d'impôt, acte : facultatifs « éventuellement ») : obligatoire pour tout **envoi réel** ; la simulation n'est pas concernée. Campagne mise en pause avec un message clair si incomplète.
4. **Consentement et preuve** (art. 68, 196) : inchangés ; aide à la saisie de la preuve.
5. `LEGAL_ENFORCE` (défaut `true`) : `security:check` signale comme **bloquant** un déploiement en production où le pied légal est désactivé.

## 5. Limites et à valider par un juriste

- Le pied de message est une **interprétation minimale** des art. 66, 72 et 348 pour un SMS (espace restreint) : mention « Publicité », nom, lien ; les autres éléments d'identité (adresse, e-mail, RCCM…) sont publiés sur la page du lien. À confirmer.
- Le texte lu est une **reproduction non officielle** ; les numéros d'articles et leur contenu sont à vérifier au Journal officiel.
- Les **montants d'amende** cités (art. 348, 350) sont ceux du texte lu, en francs congolais.
