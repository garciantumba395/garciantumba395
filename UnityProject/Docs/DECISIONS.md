# Taxi Kin — décisions de conception (réf. cahier des charges v1)

Le cahier des charges complet est la référence produit. Points arbitrés par rapport au plan en 7 étapes :

| Sujet | Constat | Décision proposée |
|---|---|---|
| Langues | Cahier : français **et lingala** | `LocalizationManager` + fichiers de textes dès l'Étape 2 (HUD), pas à l'Étape 7 |
| Zones V1 | 5 zones : Gare centrale, Matonge, Kintambo, Limete, N'djili | Missions 1-10 citent aussi Gombe, Ngaliema, Masina (zones « plus tard ») → à trancher |
| Nombre de missions | V1 = 10 ; plan = 14 + 2 | 10 missions en V1, les autres masquées/« bientôt » |
| Publicités | Cahier : récompensées uniquement, jamais en conduite | Suppression de l'interstitiel prévue dans `AdManager` |
| Bus 207 | 10 à 20 passagers | Capacité fixée à 20 |
| Trafic/météo V1 | « simple » | Voitures + motos + pluie ; feux, nids-de-poule, police après V1 |
