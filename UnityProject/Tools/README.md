# Carte de Kinshasa — import OpenStreetMap

Données © contributeurs OpenStreetMap (licence ODbL). L'attribution figure dans le menu principal et dans chaque fichier de zone.

## Télécharger et convertir une zone (automatique)
```
python3 Tools/fetch_zone.py --zone gare_centrale     # une zone de zones.json
python3 Tools/fetch_zone.py --all                    # les 5 zones
```
Le script interroge l'API officielle `api.openstreetmap.org` (une requête par seconde au plus ; une zone trop dense est découpée en
quatre), convertit avec `osm_to_zone.py` et écrit `Assets/Resources/Zones/<zone>.json`. Aucune clé ni compte nécessaire.

## Si le téléchargement automatique est impossible : export à la main (sans compte)
1. Ouvrir cette adresse dans un navigateur (zone de la Gare centrale, ~2 km × 2 km) : 
   `https://api.openstreetmap.org/api/0.6/map?bbox=15.3087,-4.3101,15.3271,-4.2921`
2. Le navigateur affiche ou télécharge un fichier XML : l'enregistrer sous `gare.osm`.
3. Convertir : `python3 Tools/osm_to_zone.py --input gare.osm --zone gare_centrale --zones Tools/zones.json --out Assets/Resources/Zones/gare_centrale.json`

(Sur openstreetmap.org, le bouton « Exporter » ne propose qu'une petite zone ; l'adresse ci-dessus est plus simple. Si le navigateur
répond « trop de points », réduire la zone en rapprochant les quatre nombres de `bbox=ouest,sud,est,nord`.)

## Règles appliquées à la conversion
- Routes carrossables uniquement (pas de chemins piétons) ; immeubles de plus de 15 m², plafond de 1 500 par zone (`--max-buildings`).
- **Aucun nom d'entreprise** : le nom d'un point d'intérêt n'est conservé que pour les gares, aéroports et marchés ; hôtels,
  stations-service, hôpitaux, arrêts et gares routières sont anonymes (cahier des charges : pas de marques ni de noms d'entreprises).
- `extra_pois` (dans `zones.json`) permet de poser à la main un repère absent des données (ex. l'aéroport de N'djili, cartographié
  comme une zone). **Position à vérifier visuellement.**
- Un fichier de zone ne contient que de la géométrie en mètres, autour du centre de la zone, nord en haut (+z).
