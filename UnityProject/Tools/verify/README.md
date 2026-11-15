# Vérification de compilation sans Unity

`bash Tools/verify/check_compile.sh` compile tout le code C# contre l'API Unity 2021.3 (assemblages de référence NuGet non
officiels, téléchargés dans un dossier temporaire, jamais versionnés). `UiStubs.cs` et `EditorStubs.cs` remplacent Unity UI et
UnityEditor : ils reprennent les noms que le code utilise et **ne valent pas l'API réelle**.

`python3 Tools/verify/sim_ai.py` simule hors Unity la loi de pilotage des véhicules d'IA sur les vraies routes, au sec, sous pluie légère et sous pluie forte, avec le glissement latéral de `VehicleController` (écart à la route, vitesse moyenne) ; ses constantes doivent rester identiques à `VehicleAI.cs`.

C'est un filet de sécurité, pas un remplaçant : la compilation dans Unity 2022.3 reste la référence. Ces fichiers sont hors de
`Assets/` pour qu'Unity ne les compile jamais.
