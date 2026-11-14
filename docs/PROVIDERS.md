# Écrire un adaptateur de fournisseur SMS

**État : fournisseur retenu = Infobip (décision D1), adaptateur écrit mais NON activé et non validé en sandbox ; vérificateur de webhook non écrit (les webhooks `infobip` restent refusés, fail-closed).** Ce document décrit comment en brancher un le moment venu ; l'infrastructure
(webhooks, accusés, désinscription par mot-clé, réconciliation, contrats de test) est déjà prête et validée avec le `FakeSmsProvider`.
Rien dans le dépôt ne connaît l'API d'un vrai fournisseur : le format de webhook du fournisseur fictif (en-têtes `X-Fake-*`) est
une invention de simulation, **pas** un modèle de format réel.

## 1. Les trois pièces d'un adaptateur

| Pièce | Interface | Où l'enregistrer |
|---|---|---|
| Envoi | `App\Domain\Sms\SmsProvider` (`name()`, `isLive()`, `send()`) | `SmsManager::extend('<nom>', fn () => new MonAdaptateur(...))` |
| Authentification et lecture des webhooks | `App\Domain\Sms\Webhooks\SmsWebhookVerifier` (`isConfigured()`, `verify()`, `parse()`) | `SmsWebhookRegistry::extend('<nom>', fn () => new MonVerificateur)` |
| Secrets et paramètres | `config/sms.php` + variables d'environnement | `.env` du serveur uniquement, jamais dans le dépôt |

Le `<nom>` est **le même** pour les deux : `SmsProvider::name()` (stocké dans `sms_messages.provider`) = segment `{provider}` de
`POST /webhooks/sms/{provider}`. Il doit respecter `^[a-z0-9_-]{1,30}$`. Enregistrement : dans `AppServiceProvider::boot()` (ou un
fournisseur de services dédié), jamais dans un fichier chargé conditionnellement par des données utilisateur.

## 2. `SmsProvider::send()` : règles

- `isLive()` renvoie `true` pour tout adaptateur qui atteint un réseau réel. **Verrou d'activation** : `SmsManager` refuse d'instancier un
  fournisseur « live » tant que `SMS_SENDING_ENABLED=true` n'est pas posé en plus de `SMS_PROVIDER=<nom>`. Ne jamais contourner
  (pas de `new MonAdaptateur` dans le code métier : toujours `SmsManager::provider()` ou l'injection de `SmsProvider`).
- **Refus définitif** (numéro invalide, solde fournisseur, contenu refusé…) → `SmsResult::rejected($code, $message)` (code ≤ 50 caractères).
  Le répartiteur rembourse alors, une fois.
- **Erreur de transport / délai dépassé / réponse illisible / 5xx ambigu** → **lever une exception**. Le répartiteur marque `unknown`,
  **sans rembourser ni renvoyer** (le message a peut-être été envoyé). Ne jamais transformer une incertitude en `rejected` (risque de remboursement
  d'un SMS parti) ni en `accepted` (risque de facturer pour rien).
- Succès → `SmsResult::accepted($idFournisseur)` (≤ 100 caractères, **unique** par message : contrainte `UNIQUE (provider, provider_message_id)`).
- **Idempotence** : `SmsRequest::$reference` (`m{id du message}`) est unique par message de la plateforme. Si le fournisseur sait dédupliquer
  (clé d'idempotence, référence client), la lui transmettre : un nouvel essai ne doit pas envoyer deux SMS. Sinon, l'adaptateur **n'a pas le droit**
  de renvoyer lui-même (la décision de renvoi appartient à la réconciliation manuelle).
- Pas d'état global, pas de lecture de la base, pas d'appel au répartiteur : l'adaptateur ne connaît ni entreprises, ni crédits, ni consentement.

## 3. Ce qu'un adaptateur ne doit JAMAIS faire

- Envoyer un SMS hors de `SmsDispatcher` (consentement, crédits, garde-fous, idempotence y sont appliqués).
- Écrire une clé d'API, un secret de webhook ou un identifiant de compte dans le dépôt, dans un test, ou dans les journaux.
- Journaliser le corps des messages, les numéros complets ou le corps brut des webhooks (données personnelles).
- Réessayer automatiquement un envoi dont le résultat est inconnu (voir ci-dessus).
- Marquer comme « réel » (`isLive() === true`) un envoi simulé, ou l'inverse : les statistiques séparent strictement `simulated`.
- Appeler le réseau dans les tests : utiliser `Http::preventStrayRequests()` + `Http::fake()` ; jamais le sandbox du fournisseur depuis la CI.
- Lire ou modifier des données d'entreprise depuis le code du webhook : le traitement passe par `SmsWebhookProcessor`
  (accusés via `SmsDispatcher::recordDeliveryReport`, désinscription via `ConsentService::optOut` sous `CurrentCompany::runAs()`).

## 4. Vérificateur de webhook

Le contrôleur générique (`SmsWebhookController`) est **fail-closed** et fait tout sauf ce qui est propre au fournisseur :

| Ordre | Contrôle | Réponse |
|---|---|---|
| 1 | limitation de débit (`sms.webhooks.rate_limit_per_minute`, par IP et fournisseur) | 429 |
| 2 | aucun vérificateur enregistré pour `{provider}` | 404 |
| 3 | `isConfigured() === false` (secret absent/vide) | 401 |
| 4 | corps > `sms.webhooks.max_body_bytes` | 413 |
| 5 | `verify()` lève `InvalidWebhookSignatureException` | 401 |
| 6 | `parse()` lève `InvalidWebhookPayloadException` | 400 |
| 7 | événement `(provider, event_id)` déjà reçu | 200 `duplicate`, aucun effet |
| 8 | traitement dans la même transaction que l'enregistrement de l'événement | 200 `applied` / `optout` / `ignored` ; 500 si échec (l'enregistrement est annulé, le fournisseur rejouera) |

À implémenter dans le vérificateur :

- `verify($rawBody, $headers)` travaille sur le **corps brut** (jamais re-sérialisé) ; en-têtes en minuscules. Utiliser `HmacSignature::matches()`
  (comparaison **en temps constant** via `hash_equals`) et `HmacSignature::timestampWithinTolerance()` si le fournisseur signe un horodatage.
  Si le fournisseur ne fournit pas d'horodatage signé, la déduplication est la seule protection contre le rejeu : **à signaler lors de la décision D1**.
- Ne lever **que** `InvalidWebhookSignatureException` (pas de `TypeError`, pas de `RuntimeException`).
- `parse($rawBody)` traduit le format du fournisseur en `SmsWebhookEvent` : `deliveryReport()` (statut final `Delivered` ou `Failed`, jamais un statut
  intermédiaire) ou `inbound()` (numéro E.164 + texte). L'`event_id` doit être un identifiant **stable** de l'événement chez le fournisseur
  (si le fournisseur n'en donne pas : dériver un hachage déterministe de champs stables, pas un nombre aléatoire, sinon la déduplication est vaine).
  Événements non gérés : à ignorer proprement par une exception de payload ou à étendre `SmsWebhookEventType`.
- Le secret vient de `config('sms.webhooks.<nom>_secret')` ← variable d'environnement, **vide par défaut = tout refusé**.

Le vérificateur du fournisseur fictif (`FakeSmsWebhookVerifier`) est un exemple complet.

## 5. Contrats de test à réutiliser

Deux classes abstraites, déjà vérifiées contre le fournisseur fictif :

- `Tests\Contracts\SmsProviderContractTest` : nom stable et compatible avec la base et la route, `isLive` stable, envoi accepté/refusé, erreur de transport
  levée, double envoi de la même référence, pas de réseau pour un adaptateur non réel, verrou d'activation, intégration avec `SmsDispatcher`.
- `Tests\Contracts\SmsWebhookVerifierContractTest` : refus sans secret, signature valide/absente/falsifiée/mauvais secret, horodatage périmé ou futur,
  seules les exceptions prévues, corps illisible.

Exemple (voir `tests/Feature/Sms/FakeSmsProviderContractTest.php`) :

```php
class MonAdaptateurContractTest extends SmsProviderContractTest
{
    protected function makeProvider(): SmsProvider { return new MonAdaptateur(/* Http::fake() déjà actif */); }
    protected function rejectedRequest(): ?SmsRequest { /* numéro que le sandbox refuse */ }
    protected function transportErrorRequest(): ?SmsRequest { /* réponse simulée en erreur réseau */ }
}
```

Les tests d'un adaptateur réel simulent les réponses HTTP (`Http::fake([...])`) à partir de la **documentation officielle** du fournisseur : c'est
à ce moment-là, et pas avant, que les formats réels sont connus.

## 6. Liste de contrôle d'intégration en sandbox (à dérouler à l'étape 10 réelle)

1. Décisions D1, D3, D4, D13 tranchées (section 7).
2. Compte sandbox du fournisseur ; clés dans le `.env` du serveur de recette uniquement ; `SMS_SENDING_ENABLED=true` seulement là.
3. Les deux contrats de test passent pour l'adaptateur (réponses simulées) ; revue de sécurité du vérificateur.
4. Webhook exposé en HTTPS ; secret de signature posé ; essai d'une requête non signée → 401, signature périmée → 401.
5. Envoi vers un numéro de test du sandbox : statut `sent` puis accusé `delivered`/`failed` reçu ; rejeu du webhook → `duplicate`.
6. Refus définitif provoqué (numéro invalide) : statut `failed`, remboursement unique.
7. Erreur de transport provoquée : statut `unknown`, pas de remboursement, pas de renvoi ; `php artisan sms:reconcile` le signale (code de sortie 1).
8. SMS entrant « STOP » depuis un numéro de test : désinscription dans les seules entreprises concernées (fenêtre `SMS_INBOUND_WINDOW_DAYS`).
9. Supervision : `sms:reconcile` planifié et branché sur l'alerte (le code de sortie ≠ 0 suffit).
10. Tarifs réels saisis dans l'administration (jamais supposés) ; premier envoi réel contrôlé, volume minimal.

## 7. Décisions qui conditionnent l'étape réelle

- **D1** : choix du fournisseur (format des webhooks, signature, horodatage, identifiants d'événement, accusés intermédiaires).
- **D3** : Sender ID (enregistrement par opérateur) : dépend du fournisseur et du pays.
- **D4 / D14** : cadre légal : mots-clés de désinscription à reconnaître (`SMS_INBOUND_OPTOUT_KEYWORDS`, défaut minimal `STOP`), mention de désinscription dans les messages.
- **D13** : remboursement sur accusé « échec » tardif et résolution des messages `unknown` : **non implémenté**, la réconciliation est manuelle.
- D50 à D53 (`docs/DECISIONS.md`) : valeurs techniques de cette préparation, à valider.

## 8. Réconciliation manuelle des messages `sending` / `unknown`

`php artisan sms:reconcile [--minutes=60] [--company=ID] [--limit=50] [--include-simulated]` est en **lecture seule** : il liste, par entreprise,
les messages restés `sending` ou `unknown` depuis plus du seuil (numéros masqués, aucun contenu). Code de sortie : `0` rien à faire, `1` au moins un
message réel à traiter, `2` option invalide. À planifier (cron) et à brancher sur l'alerte d'exploitation.

Procédure manuelle (jusqu'à la décision D13) pour chaque message listé :
1. Retrouver le message côté fournisseur (tableau de bord ou API de statut, via `provider` et la référence `m{id}`).
2. Parti/délivré : l'accusé arrivera ou sera rejoué par le webhook ; sinon, constater et laisser le statut ; **ne pas** rembourser.
3. Jamais parti : à ce jour il n'existe **pas** d'outil de résolution ni de remboursement manuel automatisé ; la correction (statut `failed` + écriture de remboursement
   idempotente du grand livre) doit être faite par un opérateur habilité, avec traçabilité, après la décision D13. **Ne jamais renvoyer** le message tant que
   l'état réel n'est pas établi (risque de double SMS).
4. Un message `sending` ancien correspond le plus souvent à un worker interrompu après la prise en charge : même procédure.
