# Carte de Kinshasa — import OpenStreetMap

Données © contributeurs OpenStreetMap (licence ODbL) : afficher « © contributeurs OpenStreetMap » dans l'écran « À propos » du jeu.

## Obtenir les données d'une zone
1. Ouvrir https://www.openstreetmap.org/export, cadrer la zone (≈ 2 km × 2 km autour du centre de `zones.json`), puis « Exporter » → fichier `.osm`.
   (Alternative : Overpass Turbo → Exporter → « données brutes (.osm) » ou GeoJSON non supporté ici : utiliser .osm ou Overpass JSON.)
2. Convertir :
   `python3 Tools/osm_to_zone.py --input gare.osm --zone gare_centrale --zones Tools/zones.json --out Assets/Resources/Zones/gare_centrale.json`
3. Dans Unity : menu **Taxi Kin → Carte → Ajouter une zone à la scène**, puis Play.

Zones : gare_centrale, matonge, kintambo, limete, ndjili. Plafond de 1 500 immeubles par zone (option `--max-buildings`).
