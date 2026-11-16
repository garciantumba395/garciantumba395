using UnityEditor;
using UnityEngine;
using UnityEngine.Rendering;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Crée les matériaux du décor de la ville dans Assets/Resources/WorldMats (chargés à la demande par ZoneLoader) :
    /// devantures commerçantes A et B, palette de toits, poteaux/troncs, feuillages. Mis à jour à chaque création de scène.
    /// </summary>
    public static class TaxiKinWorldMaterials
    {
        public static void Build()
        {
            foreach (var f in new[] { "Assets/Resources", "Assets/Resources/WorldMats" })
                if (!AssetDatabase.IsValidFolder(f)) AssetDatabase.CreateFolder(f.Substring(0, f.LastIndexOf('/')), f.Substring(f.LastIndexOf('/') + 1));

            // La palette de toits : couleurs franches, sans mipmaps (elles se mélangeraient au loin) et sans répétition
            var pal = AssetImporter.GetAtPath("Assets/Textures/roof_palette.png") as TextureImporter;
            if (pal != null && (pal.mipmapEnabled || pal.wrapMode != TextureWrapMode.Clamp))
            {
                pal.mipmapEnabled = false; pal.wrapMode = TextureWrapMode.Clamp; pal.filterMode = FilterMode.Bilinear;
                pal.SaveAndReimport();
            }

            Make("Mat_ShopA", "shop_a", Color.white);
            Make("Mat_ShopB", "shop_b", Color.white);
            Make("Mat_Roof", "roof_palette", Color.white);
            Make("Mat_Pole", null, new Color(0.2f, 0.2f, 0.22f));
            Make("Mat_Leaf", null, new Color(0.26f, 0.5f, 0.22f));
            AssetDatabase.SaveAssets();
        }

        private static void Make(string name, string textureName, Color color)
        {
            string path = $"Assets/Resources/WorldMats/{name}.mat";
            var mat = AssetDatabase.LoadAssetAtPath<Material>(path);
            if (mat == null)
            {
                Shader shader = null;
                if (GraphicsSettings.currentRenderPipeline != null) shader = Shader.Find("Universal Render Pipeline/Simple Lit");
                if (shader == null) shader = Shader.Find("Standard");
                mat = new Material(shader) { enableInstancing = true };
                AssetDatabase.CreateAsset(mat, path);
            }
            mat.color = color;
            if (textureName != null)
            {
                var tex = AssetDatabase.LoadAssetAtPath<Texture2D>($"Assets/Textures/{textureName}.png");
                if (tex != null) mat.mainTexture = tex; else Debug.LogWarning("[Taxi Kin] Texture introuvable : " + textureName);
            }
            if (mat.HasProperty("_Glossiness")) mat.SetFloat("_Glossiness", 0f);   // mat, pas de reflets (Standard)
            EditorUtility.SetDirty(mat);
        }
    }
}
