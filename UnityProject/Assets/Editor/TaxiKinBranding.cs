using UnityEditor;
using UnityEngine;

namespace TaxiKin.EditorTools
{
    /// <summary>Applique l'icône de l'application (Assets/Branding/icon_1024.png) à toutes les plateformes.</summary>
    public static class TaxiKinBranding
    {
        [MenuItem("Taxi Kin/Appliquer l'icône de l'application")]
        public static void ApplyIcon()
        {
            var icon = AssetDatabase.LoadAssetAtPath<Texture2D>("Assets/Branding/icon_1024.png");
            if (icon == null) { Debug.LogWarning("[Taxi Kin] Icône introuvable : Assets/Branding/icon_1024.png"); return; }
            // Icône par défaut : Unity la redimensionne pour chaque plateforme
            PlayerSettings.SetIcons(UnityEditor.Build.NamedBuildTarget.Unknown, new[] { icon }, IconKind.Application);
            AssetDatabase.SaveAssets();
            Debug.Log("[Taxi Kin] Icône appliquée.");
        }
    }
}
