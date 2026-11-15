using UnityEditor;
using UnityEngine;
using UnityEngine.Rendering;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Menu « Taxi Kin → Android → Appliquer les réglages d'optimisation » : règle le projet pour des téléphones modestes
    /// (IL2CPP, ARM64 + ARMv7, Android 7+, OpenGL ES 3, stripping élevé, App Bundle, paysage, GC incrémental, cadence stable).
    /// Cela modifie ProjectSettings : à relire dans le gestionnaire de versions avant de valider.
    /// </summary>
    public static class TaxiKinAndroidBuildSetup
    {
        [MenuItem("Taxi Kin/Android/Appliquer les réglages d'optimisation")]
        public static void Apply()
        {
            // Code natif rapide, 64 bits + 32 bits (beaucoup de téléphones d'entrée de gamme sont encore en 32 bits)
            PlayerSettings.SetScriptingBackend(BuildTargetGroup.Android, ScriptingImplementation.IL2CPP);
            PlayerSettings.Android.targetArchitectures = AndroidArchitecture.ARMv7 | AndroidArchitecture.ARM64;
            PlayerSettings.Android.minSdkVersion = AndroidSdkVersions.AndroidApiLevel24;      // Android 7.0
            PlayerSettings.Android.targetSdkVersion = AndroidSdkVersions.AndroidApiLevelAuto;

            // Taille : retire le code inutilisé (le fichier Assets/link.xml protège ce qui est appelé par réflexion)
            PlayerSettings.SetManagedStrippingLevel(BuildTargetGroup.Android, ManagedStrippingLevel.High);
            PlayerSettings.stripEngineCode = true;
            PlayerSettings.Android.minifyRelease = true;
            EditorUserBuildSettings.buildAppBundle = true;                                    // AAB : téléchargement plus léger sur Google Play

            // OpenGL ES 3 seul : Vulkan est instable sur plusieurs GPU d'entrée de gamme
            PlayerSettings.SetUseDefaultGraphicsAPIs(BuildTarget.Android, false);
            PlayerSettings.SetGraphicsAPIs(BuildTarget.Android, new[] { GraphicsDeviceType.OpenGLES3 });

            // Cadence régulière, moins de saccades de ramasse-miettes
            PlayerSettings.gcIncremental = true;
            PlayerSettings.Android.optimizedFramePacing = true;

            // Espace colorimétrique « Gamma » : plus léger sur mobile et fidèle aux couleurs de marque (jaune #FFD400)
            PlayerSettings.colorSpace = ColorSpace.Gamma;

            // Paysage uniquement (les commandes sont conçues pour deux pouces)
            PlayerSettings.defaultInterfaceOrientation = UIOrientation.AutoRotation;
            PlayerSettings.allowedAutorotateToPortrait = false;
            PlayerSettings.allowedAutorotateToPortraitUpsideDown = false;
            PlayerSettings.allowedAutorotateToLandscapeLeft = true;
            PlayerSettings.allowedAutorotateToLandscapeRight = true;

            // Pas de traces d'appels dans les journaux en production : moins de CPU et de mémoire
            PlayerSettings.SetStackTraceLogType(LogType.Log, StackTraceLogType.None);
            PlayerSettings.SetStackTraceLogType(LogType.Warning, StackTraceLogType.None);

            AssetDatabase.SaveAssets();
            Debug.Log("[Taxi Kin] Réglages Android appliqués : IL2CPP, ARMv7+ARM64, Android 7+, OpenGL ES 3, stripping élevé, AAB, paysage, Gamma.\n" +
                      "À faire à la main : installer le paquet URP, créer l'asset de pipeline « Mobile » (voir Docs/PERFORMANCE.md), " +
                      "puis profiler sur un vrai téléphone d'entrée de gamme.");
        }
    }
}
