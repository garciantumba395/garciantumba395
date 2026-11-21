# Film promo GNS BUSINESS RDC — fiche de conception

Mode : **création partagée** (video-shotcraft). Source de vérité des prix : `src/data.ts`.

## 1. Brief validé
- Boutique : GNS BUSINESS RDC, boutique high-tech, Gallerie Pacha, local 11, Gombe, Kinshasa — https://www.gnsbusinessrdc.store
- Cible du film : smartphones — **Apple, Samsung Galaxy, Google Pixel, TECNO**. Ordinateurs, gaming, équipements IT exclus.
- Format : **9:16 (1080×1920), 45 s, 30 i/s**.
- Angle : « 4 grandes marques. Une seule boutique. »
- Affichés : prix en $ (indicatifs Kinshasa) et le numéro **+243 983 468 341**.
- Message livraison (demande client) : « Commandez. On expédie. Partout en RDC. Où que vous soyez en RDC, nous expédions votre colis. » (aucun délai chiffré annoncé)
- Appel final : appeler le numéro ; visiter la boutique **gnsbusinessrdc.store** pour voir tous les produits.
- Retrait : Troc iPhone (retiré pour laisser lire les prix), mention « Disponibilité à confirmer ».

## 2. Données (relevées le 2026-11-20 sur /marque/*, références « Disponible » uniquement)
| Marque | Modèle | Prix |
|---|---|---|
| Apple | iPhone 11 / 12 / 13 | dès 221 / 252 / 391 $ |
| Apple | iPhone 15 Pro Max (seul iPhone 15 du catalogue) | 945 $ |
| Samsung | Galaxy S22 / S23 / S24 | dès 276 / 384 / 516 $ |
| Pixel | Pixel 8 / 9 / 9 Pro | 324 / 516 / 720 $ |
| TECNO | POP 20 / Spark 50 | dès 130 / 174 $ |
| TECNO | Camon 50 / Camon 50 Pro | 320 / 380 $ |
Exclus : tout produit « Épuisé » ou « Disponibilité à confirmer » (ex. TECNO POP 20C). Aucune donnée de compte/panier capturée.

## 3. Direction visuelle « Nuit marine » (tokens extraits du site et du logo)
Police **Montserrat** (celle du site) · marine `#07152f` · bleu `#0057ff` / lumière `#1d5cff` · orange `#ff7a00` **réservé aux prix et à l'appel** · blanc `#f2f6ff`.
Images : **téléphones vectoriels génériques** (aucune photo tierce, aucun logo de marque de téléphone) — décision du client pour éviter les droits d'image. Logo GNS = fichier officiel du site (`/logo-gns.png`).

## 4. Rythme et musique
House Vibez (Lily J, Mixkit, licence gratuite usage commercial sans attribution) · ≈ 122,5 BPM (période 0,4896 s = 14,688 i) · origine de grille = f0 (`startFrom` 13 i).
Durées des plans en temps : 10 / 6 / 14 / 12 / 11 / 13 / 10 / 16 = 92 temps ≈ 45 s. Frontières : 147, 235, 441, 617, 779, 969, 1116.
Deux versions rendues depuis la même chronologie : avec musique / sans musique (`--props={"bgm":false}`).

## 5. Storyboard et fiches utilisées
| # | Images | Message | Fiche (style) |
|---|---|---|---|
| 1 | 0–147 | iPhone sous projecteur, « Le smartphone qu'il vous faut. » | `spotlight-hero-card` |
| 2 | 147–235 | « 4 grandes marques. Une seule boutique. » | `lead-word-zoom-assemble` |
| 3 | 235–441 | Apple : 4 prix | `deck-deal-flyin` |
| 4 | 441–617 | Samsung Galaxy : 3 prix | `neon-frame-orbit-drop` |
| 5 | 617–779 | Google Pixel : 3 prix | `spotlight-sweep-moves` / slide-spotlight-pan |
| 6 | 779–969 | TECNO : 4 prix | `card-flip-reveal` |
| 7 | 969–1116 | Commandez. On expédie à [ville] → toute la RDC | `pill-slot-cycle` |
| 8 | 1116–1350 | Logo, appel +243 983 468 341, visite gnsbusinessrdc.store, adresse | `logo-shrink-wordmark-lockup` (+ « photo de groupe » Q8) |
Bandeau permanent (plans 3–7) : « GNS BUSINESS RDC +243 983 468 341 » + « Prix indicatifs à Kinshasa, en dollars US ».

## 6. Écarts assumés par rapport aux fiches (à déclarer à la relecture)
1. Les démos du skill sont en 16:9 et reposent sur des captures de pages ; elles n'ont **pas été copiées**. Chaque plan réimplémente la grammaire de mouvement de sa fiche (courbes, durées, règles « commande ») en portrait avec des cartes vectorielles. Les fichiers TSX de démonstration n'ont pas été lus ; seules les fiches `.md` (paramètres et « déjà vu ») l'ont été.
2. Plan 4 : arc de vue +38° → −26° conservé, puis redressement à 0° (128→160) pour la lisibilité des prix (ajout).
3. Plan 3 : 4 cartes seulement (la fiche en prévoit 26) ; cadence de donne resserrée mais moins « mitraillette ».
4. Plan 7 : mots de 22 i (fiche : 21) ; villes citées : Kinshasa, Lubumbashi, Goma, Kisangani, puis « toute la RDC ».
5. Pas d'effets sonores (Freesound non accessible depuis l'environnement) : la musique porte seule le rythme.
6. Éclair blanc au verrouillage du logo (plan 8) ≈ 6 i, intensité 0,7.
