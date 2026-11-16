#!/usr/bin/env bash
# Vérification de compilation SANS Unity : compile tout Assets/**/*.cs en C# 9 contre l'API Unity 2021.3
# (assemblages de référence NuGet « UnityEngine.Modules », non officiels, téléchargés à chaque exécution
# dans un dossier temporaire et JAMAIS ajoutés au dépôt) + substituts d'Unity UI et d'UnityEditor.
#
# Ce que ça détecte : fautes de syntaxe, fonctionnalités de C# au-delà de la version 9 (limite d'Unity 2022.3),
# types / méthodes / paramètres inexistants, ambiguïtés de noms.
# Ce que ça NE détecte PAS : les écarts entre l'API réelle d'Unity 2022.3 et 2021.3 ou mes substituts Unity UI / UnityEditor,
# le code sous #if UNITY_6000_0_OR_NEWER ou UNITY_PURCHASING, ni aucun comportement à l'exécution.
# La compilation réelle dans Unity reste la référence.
#
# Prérequis : SDK .NET 8, curl, python3.   Usage : bash Tools/verify/check_compile.sh
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PROJECT="$(cd "$HERE/../.." && pwd)"
WORK="${TMPDIR:-/tmp}/taxikin-verify"
PKG_VERSION="2021.3.33"
mkdir -p "$WORK"

# --- Compilateur Roslyn et assemblages .NET de référence ---
SDK_LINE="$(dotnet --list-sdks | grep -E "^8\." | tail -1)"; [ -n "$SDK_LINE" ] || SDK_LINE="$(dotnet --list-sdks | tail -1)"
SDK_VERSION="$(echo "$SDK_LINE" | awk '{print $1}')"
SDK_ROOT="$(echo "$SDK_LINE" | sed -E 's/^[^[]*\[(.*)\]$/\1/')"
CSC="$SDK_ROOT/$SDK_VERSION/Roslyn/bincore/csc.dll"
NET_REF="$(find "$(dirname "$SDK_ROOT")/packs/Microsoft.NETCore.App.Ref" -type d -path '*/ref/net8.0' | head -1)"
[ -f "$CSC" ] && [ -d "$NET_REF" ] || { echo "SDK .NET 8 introuvable"; exit 2; }

# --- Assemblages de référence Unity (téléchargés une seule fois) ---
if [ ! -d "$WORK/refs" ]; then
  echo "Téléchargement des assemblages de référence Unity $PKG_VERSION (NuGet)…"
  curl -fsS -o "$WORK/um.nupkg" "https://api.nuget.org/v3-flatcontainer/unityengine.modules/$PKG_VERSION/unityengine.modules.$PKG_VERSION.nupkg"
  python3 -I - "$WORK" <<'PY'
import sys, zipfile, os
work = sys.argv[1]
z = zipfile.ZipFile(os.path.join(work, 'um.nupkg'))
os.makedirs(os.path.join(work, 'refs'), exist_ok=True)
for name in z.namelist():
    if name.startswith('lib/net35/') and name.endswith('.dll'):
        # extraction à plat, nom de fichier seul : on ne fait jamais confiance aux chemins de l'archive
        with open(os.path.join(work, 'refs', os.path.basename(name)), 'wb') as f:
            f.write(z.read(name))
PY
fi

REFS=""
for d in "$NET_REF"/*.dll "$WORK"/refs/*.dll; do REFS="$REFS -r:$d"; done
find "$PROJECT/Assets" -name '*.cs' > "$WORK/files.txt"
echo "Compilation de $(wc -l < "$WORK/files.txt") fichiers C# (langage C# 9)…"

# shellcheck disable=SC2086
if dotnet "$CSC" -nologo -noconfig -nostdlib -langversion:9 -nowarn:1701,1702 -target:library \
     -out:"$WORK/probe.dll" $REFS @"$WORK/files.txt" "$HERE/UiStubs.cs" "$HERE/EditorStubs.cs" > "$WORK/out.txt" 2>&1; then
  # Les « champs jamais assignés » (CS0649) sont normaux : Unity les remplit via l'Inspector
  grep -v "CS0649" "$WORK/out.txt" || true
  echo "OK : aucune erreur de compilation détectée."
else
  grep -v "CS0649" "$WORK/out.txt" | sed "s#$PROJECT/##"
  echo "ÉCHEC : voir les erreurs ci-dessus."
  exit 1
fi
