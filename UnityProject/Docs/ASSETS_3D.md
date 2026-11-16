# Assets 3D : organisation, crédits, procédure

## Organisation des dossiers
| Dossier | Contenu | Règle |
|---|---|---|
| `Assets/ThirdParty/Kenney/CarKit/` | Fichiers ORIGINAUX du fournisseur : `Models/taxi.fbx`, `Models/Textures/colormap.png`, `License.txt` | Jamais modifiés à la main |
| `Assets/TaxiKin/Vehicles/Taxi/` | Adaptations Taxi Kin GÉNÉRÉES par l'outil (prefab `TaxiVisual_Kenney`, maillages séparés) | Régénérés à volonté, ne pas éditer |
| `Assets/Materials/Mat_Kenney_*` | Matériaux : détails (palette du kit), peinture (jaune #FFD400), jante | Créés par l'outil |
| `Assets/Scripts/Vehicles/WheelVisuals.cs` | Rotation et braquage des roues visuelles | Purement visuel |
| `Assets/Scripts/Vehicles/VehicleVisualSwitch.cs` | Bascule modèle 3D / modèle provisoire | |

Seul le taxi-voiture du CAR KIT est intégré pour l'instant (`taxi.fbx`). Wewa et bus 207 : inchangés, en attente de validation du taxi.
Rien n'a été supprimé : l'ancien modèle provisoire est conservé (groupe « VisuelProvisoire »).

## Crédits et licence
Car Kit 3.1 — Kenney (www.kenney.nl), licence CC0 (domaine public ; crédit non obligatoire mais prévu dans les crédits du jeu).
Texte de licence d'origine : `Assets/ThirdParty/Kenney/CarKit/License.txt`.

## Ce que fait l'intégration (conception)
- La racine physique du taxi (Rigidbody, BoxCollider, TaxiController, caméras, passagers) n'est pas modifiée.
- Le nouveau modèle est un enfant visuel « VisuelKenney » posé sur le bas du collider.
- Orientation : détectée automatiquement (roues « front » / « back »). Échelle : longueur visuelle 3,7 m (collider 4,2 m, inchangé).
- Roues visuelles : pivot de braquage (avant) + noeud de rotation (4 roues), vitesse lue sur le déplacement réel. Les colliders ne sont pas liés aux roues visuelles (le véhicule n'a pas de WheelCollider).
- Couleur : la carrosserie est séparée en « Peinture » (matériau uni, teinté par la personnalisation ; défaut #FFD400) et « détails » (vitres, phares, plastique, intérieur, pneus : jamais teintés). Les jantes sont un enfant « Jante » repeint par le réglage de jantes.
- Séparation peinture/détails : la couleur de palette qui couvre la plus grande surface de la carrosserie est la peinture. Heuristique à vérifier visuellement.

## Revenir à l'ancien modèle
- Menu `Taxi Kin → Véhicules → Taxi : modèle 3D Kenney (activer / désactiver)` puis recréer la scène ; ou
- Sur l'objet « Taxi » de la scène : composant `VehicleVisualSwitch` → menu ⋮ → « Revenir au modèle provisoire ».

## Limites connues (volontaires, à valider)
- La couleur de TOIT séparée n'existe pas sur le modèle 3D (une seule peinture).
- Les aperçus du garage, le taxi de course IA et le trafic gardent le modèle provisoire.
- Plaque et autocollants sont replacés par calcul, pas par un point d'attache du modèle : à vérifier.

## Validation à faire dans Unity (rien de ceci n'a été exécuté par l'auteur du code)
| Test | Attendu |
|---|---|
| Orientation | Le taxi roule en avant, phares côté avant |
| Sol | Les roues touchent la route, pas de flottement ni d'enfoncement |
| Échelle | Le taxi a la taille des voitures du trafic (~3,7 m) |
| Accélération / freinage / marche arrière | Comme avant (conduite inchangée) |
| Direction | Les roues avant braquent ; les 4 roues tournent dans le bon sens, plus vite quand on accélère |
| Collisions | Comme avant (collider d'origine) |
| Caméras | Les 4 vues restent bien placées |
| Passagers | Prise / dépose fonctionnent |
| Couleur | Le changement de couleur teinte la carrosserie seulement (pas vitres, pneus, phares) ; jaune #FFD400 par défaut |
| Sauvegarde | Couleur, jantes et plaque conservées après redémarrage |
| Retour arrière | « Revenir au modèle provisoire » rétablit l'ancien visuel |
