using System;
using System.IO;
using UnityEditor;
using UnityEngine;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Point d'entrée de la construction automatique (GitHub Actions / GameCI) :
    /// génère la scène, applique les réglages Android, puis produit un APK de test installable (signature de débogage).
    /// Appel : -executeMethod TaxiKin.EditorTools.TaxiKinCiBuild.BuildAndroid [-customBuildPath chemin.apk]
    /// </summary>
    public static class TaxiKinCiBuild
    {
        /// <summary>Version web (WebGL) : qualité réduite, compression Gzip, sans achats ni publicités.</summary>
        [MenuItem("Taxi Kin/Construire la version web (WebGL)")]
        public static void BuildWebGL()
        {
            TaxiKinSceneBuilder.BuildMainScene();
            TaxiKinBranding.ApplyIcon();
            PlayerSettings.productName = "Taxi Kin";
            PlayerSettings.WebGL.compressionFormat = WebGLCompressionFormat.Gzip;
            PlayerSettings.WebGL.decompressionFallback = true;   // fonctionne même si l'hébergeur ne sert pas l'en-tête de compression
            string output = "build/WebGL";
            string[] args = Environment.GetCommandLineArgs();
            for (int i = 0; i < args.Length - 1; i++)
                if (args[i] == "-customBuildPath") output = args[i + 1];
            var report = BuildPipeline.BuildPlayer(new BuildPlayerOptions
            {
                scenes = new[] { "Assets/Scenes/Main.unity" },
                locationPathName = output,
                target = BuildTarget.WebGL,
                options = BuildOptions.None
            });
            if (report.summary.result != UnityEditor.Build.Reporting.BuildResult.Succeeded)
            {
                Debug.LogError("[Taxi Kin] Échec WebGL : " + report.summary.result);
                EditorApplication.Exit(1);
            }
        }

        [MenuItem("Taxi Kin/Construire l'APK de test (tout en un)")]
        public static void BuildAndroid()
        {
            TaxiKinSceneBuilder.BuildMainScene();
            TaxiKinBranding.ApplyIcon();
            TaxiKinAndroidBuildSetup.Apply();

            // APK (et non AAB) pour l'essai : installable directement sur un téléphone
            EditorUserBuildSettings.buildAppBundle = false;
            PlayerSettings.productName = "Taxi Kin";
            PlayerSettings.companyName = "TaxiKin";
            PlayerSettings.SetApplicationIdentifier(BuildTargetGroup.Android, "com.taxikin.wewa");

            string output = "build/Android/TaxiKin.apk";
            string[] args = Environment.GetCommandLineArgs();
            for (int i = 0; i < args.Length - 1; i++)
                if (args[i] == "-customBuildPath") output = args[i + 1];
            if (!output.EndsWith(".apk", StringComparison.OrdinalIgnoreCase))
                output = Path.Combine(output, "TaxiKin.apk");
            Directory.CreateDirectory(Path.GetDirectoryName(output) ?? ".");

            var options = new BuildPlayerOptions
            {
                scenes = new[] { "Assets/Scenes/Main.unity" },
                locationPathName = output,
                target = BuildTarget.Android,
                options = BuildOptions.None
            };
            var report = BuildPipeline.BuildPlayer(options);
            if (report.summary.result != UnityEditor.Build.Reporting.BuildResult.Succeeded)
            {
                Debug.LogError("[Taxi Kin] Échec de la construction : " + report.summary.result);
                EditorApplication.Exit(1);
            }
            Debug.Log("[Taxi Kin] APK construit : " + output + " (" + report.summary.totalSize / (1024 * 1024) + " Mo)");
        }
    }
}
