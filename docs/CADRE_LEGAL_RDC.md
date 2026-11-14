# Cadre légal RDC — état des vérifications (décision D4)

> **Ce document n'est pas un avis juridique.** Le propriétaire du produit a demandé d'appliquer le cadre légal de la RDC (D4). Aucune règle n'est
> encodée dans le produit tant qu'elle n'a pas été **lue dans le texte officiel** : les recherches ci-dessous n'ont donné que le cadre général, et
> les pages juridiques n'ont pas pu être ouvertes (hôtes bloqués par la politique réseau de la session de développement, le 2026-11-14).

## 1. Ce que les recherches indiquent (résultats de recherche, **non relus à la source**)

| Élément | Indication | Statut |
|---|---|---|
| Code du numérique | Ordonnance-loi n° 23/010 du 13 mars 2023, publiée au Journal officiel le 11 avril 2023 ; la protection des données personnelles y figure (un titre dédié) | à relire dans le texte |
| Loi sur les télécommunications | Loi n° 20/017 du 25 novembre 2020 ; les articles 126 à 133 traiteraient de la vie privée et des données personnelles dans les télécommunications/TIC | à relire dans le texte |
| Consentement | Présenté comme **central** dans le dispositif de protection des données (source universitaire) ; conditions exactes non vérifiées | à relire dans le texte |
| Autorité | Fonctions de l'autorité de protection des données confiées **provisoirement** à l'ARPTIC (décision ministérielle du 17 août 2024), en attendant l'autorité dédiée | à confirmer |
| Textes d'application | Un rapport de consultation note l'**absence de nombreuses mesures d'application** de la loi 20/017 et des **chevauchements** avec le Code du numérique | à confirmer |

Sources consultées (à ouvrir directement) : [Code du numérique 2023 (PDF, leganet.cd)](https://www.leganet.cd/Doctrine.textes/DroitPenal/Code.numerique.2023.pdf) ·
[Data Protection in the DRC (DLA Piper)](https://www.dlapiperdataprotection.com/?c=CD&t=law) · [Droit-Numérique.cd](https://droitnumerique.cd) ·
[ungovr.org — RDC](https://law.ungovr.org/ai/cd).

## 2. Ce qui n'est PAS établi (à ne pas supposer)

Aucune source lue n'a confirmé les points suivants, qui conditionnent le produit :

1. Les conditions exactes du **consentement** pour un SMS promotionnel (préalable ? écrit ? preuve ? forme ?).
2. Le droit d'**opposition / désabonnement** : délai, gratuité, mention obligatoire dans chaque message.
3. Une **plage horaire** d'envoi autorisée pour la prospection.
4. La **durée de conservation** des preuves de consentement et des données de contact.
5. L'obligation d'**identifier l'expéditeur** (nom, sender ID) et d'éventuelles **formalités préalables** (déclaration à l'autorité, enregistrement du sender ID auprès des opérateurs).
6. Les **sanctions** et la responsabilité du prestataire (la plateforme) vis-à-vis de ses clients.
7. Les règles propres aux **opérateurs** (Airtel, Vodacom, Orange, Africell…) pour les SMS en masse.

## 3. Ce que le produit fait déjà (indépendamment du texte exact)

- Consentement **enregistré avec preuve** (source, date, texte), révocable ; aucun envoi sans consentement valide ; **revérifié à chaque envoi**.
- **Désinscription** prioritaire, liée au numéro, effective immédiatement ; « STOP » entrant traité (fournisseur réel requis).
- Paramètres déjà **configurables** (`config/consent.php`) : preuve obligatoire, durée de validité d'un consentement.
- Journal d'audit, export et effacement des données d'un contact possibles techniquement (suppression d'un contact = suppression de ses consentements).

## 4. Pour appliquer le cadre légal sans rien inventer

Deux options, au choix du propriétaire :

- **A. Autoriser la lecture des textes** : ajouter `www.leganet.cd`, `droitnumerique.cd` et `www.dlapiperdataprotection.com` aux hôtes autorisés de l'environnement
  de développement (accès en lecture seule). Je relis alors les articles, **cite chaque règle avec son numéro d'article**, et n'encode que ce qui est écrit.
- **B. Fournir les règles** : le propriétaire ou son conseil juridique transmet les articles/avis applicables (texte ou références précises).

Dans les deux cas, la mise en œuvre prévue (une fois les règles connues) est de la **configuration, pas du code spéculatif** :
plage horaire d'envoi (pause automatique des campagnes hors plage), mention de désinscription ajoutée aux messages, durées de conservation et purge,
champs obligatoires de preuve de consentement. Chaque règle sera accompagnée de sa référence légale dans `config/` et dans les tests.

## 4bis. Questions à poser au conseil juridique local / à l'ARPTC

Voir la liste du § 2 ; ajouter : *« Un consentement obtenu par un formulaire papier ou oral enregistré est-il admis ? »*, *« Le sender ID doit-il être déclaré ? »*,
*« Qui est responsable du traitement : le client de la plateforme ou la plateforme ? »* (conditionne les clauses contractuelles et la politique de confidentialité).
