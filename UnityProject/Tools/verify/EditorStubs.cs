// SUBSTITUTS DE VÉRIFICATION UNIQUEMENT (hors dépôt) : signatures de UnityEditor telles que je les connais (2022.3)
using System;
using UnityEngine;
using UnityEngine.Events;
using UnityEngine.Rendering;
using UnityEngine.SceneManagement;
namespace UnityEditor
{
    [AttributeUsage(AttributeTargets.Method)] public class MenuItem : Attribute { public MenuItem(string path) { } }
    public static class AssetDatabase
    {
        public static bool IsValidFolder(string p) => false; public static string CreateFolder(string parent, string name) => "";
        public static T LoadAssetAtPath<T>(string p) where T : UnityEngine.Object => null;
        public static void CreateAsset(UnityEngine.Object o, string p) { }
        public static void SaveAssets() { } public static void Refresh() { }
        public static T GetBuiltinExtraResource<T>(string p) where T : UnityEngine.Object => null;
    }
    public static class PrefabUtility
    {
        public static GameObject SaveAsPrefabAsset(GameObject g, string p) => null;
        public static UnityEngine.Object InstantiatePrefab(UnityEngine.Object o) => null;
    }
    public static class EditorUtility { public static void SetDirty(UnityEngine.Object o) { } public static bool DisplayDialog(string t, string m, string ok) => true; }
    public static class Selection { public static GameObject activeGameObject; }
    public class SerializedObject
    {
        public SerializedObject(UnityEngine.Object o) { }
        public SerializedProperty FindProperty(string n) => null; public bool ApplyModifiedPropertiesWithoutUndo() => true;
    }
    public class SerializedProperty
    {
        public UnityEngine.Object objectReferenceValue; public int arraySize; public int enumValueIndex; public string stringValue;
        public float floatValue; public int intValue; public bool boolValue;
        public SerializedProperty GetArrayElementAtIndex(int i) => null; public SerializedProperty FindPropertyRelative(string n) => null;
    }
    public enum UIOrientation { Portrait, PortraitUpsideDown, LandscapeRight, LandscapeLeft, AutoRotation }
    public enum ScriptingImplementation { Mono2x, IL2CPP }
    public enum BuildTargetGroup { Unknown, Android }
    public enum BuildTarget { Android, WebGL }
    public enum ManagedStrippingLevel { Disabled, Low, Medium, High }
    public enum AndroidSdkVersions { AndroidApiLevelAuto = 0, AndroidApiLevel24 = 24 }
    [Flags] public enum AndroidArchitecture { None = 0, ARMv7 = 1, ARM64 = 2 }
    public static class EditorUserBuildSettings { public static bool buildAppBundle; }
    public static class PlayerSettings
    {
        public static class Android
        {
            public static AndroidArchitecture targetArchitectures; public static AndroidSdkVersions minSdkVersion, targetSdkVersion;
            public static bool minifyRelease, optimizedFramePacing;
        }
        public static void SetScriptingBackend(BuildTargetGroup g, ScriptingImplementation i) { }
        public static void SetManagedStrippingLevel(BuildTargetGroup g, ManagedStrippingLevel l) { }
        public static bool stripEngineCode, gcIncremental, allowedAutorotateToPortrait, allowedAutorotateToPortraitUpsideDown, allowedAutorotateToLandscapeLeft, allowedAutorotateToLandscapeRight;
        public static void SetUseDefaultGraphicsAPIs(BuildTarget t, bool b) { }
        public static void SetGraphicsAPIs(BuildTarget t, GraphicsDeviceType[] a) { }
        public static class WebGL { public static WebGLCompressionFormat compressionFormat; public static bool decompressionFallback; }
        public static void SetIcons(UnityEditor.Build.NamedBuildTarget t, Texture2D[] icons, IconKind k) { }
        public static string productName, companyName; public static void SetApplicationIdentifier(BuildTargetGroup g, string id) { }
        public static ColorSpace colorSpace; public static UIOrientation defaultInterfaceOrientation;
        public static void SetStackTraceLogType(LogType l, StackTraceLogType s) { }
    }
}
namespace UnityEditor.Events
{
    public static class UnityEventTools
    {
        public static void AddPersistentListener(UnityEvent e, UnityAction a) { }
        public static void AddPersistentListener<T>(UnityEvent<T> e, UnityAction<T> a) { }
        public static void AddIntPersistentListener(UnityEvent e, UnityAction<int> a, int v) { }
    }
}
namespace UnityEditor.SceneManagement
{
    public enum NewSceneSetup { EmptyScene, DefaultGameObjects }
    public enum NewSceneMode { Single, Additive }
    public static class EditorSceneManager
    {
        public static Scene NewScene(NewSceneSetup s, NewSceneMode m) => default;
        public static bool SaveScene(Scene s, string p) => true;
    }
}
namespace UnityEditor
{
    public class EditorBuildSettingsScene { public EditorBuildSettingsScene(string p, bool e) { } }
    public static class EditorBuildSettings { public static EditorBuildSettingsScene[] scenes; }
}
namespace UnityEditor
{
    public struct BuildPlayerOptions { public string[] scenes; public string locationPathName; public BuildTarget target; public BuildOptions options; }
    public enum BuildOptions { None }
    public static class BuildPipeline { public static UnityEditor.Build.Reporting.BuildReport BuildPlayer(BuildPlayerOptions o) => null; }
    public static class EditorApplication { public static void Exit(int code) { } public static bool isPlaying; }
}
namespace UnityEditor.Build.Reporting
{
    public enum BuildResult { Unknown, Succeeded, Failed, Cancelled }
    public class BuildSummary { public BuildResult result; public ulong totalSize; }
    public class BuildReport { public BuildSummary summary; }
}
namespace UnityEditor
{
    public enum WebGLCompressionFormat { Disabled, Gzip, Brotli }
    public static partial class PlayerSettingsWebGLHolder { }
}
namespace UnityEditor
{
    public enum IconKind { Any, Application, Settings, Notification, Spotlight, Store }
    public static partial class PlayerSettingsIconHolder { }
}
namespace UnityEditor.Build
{
    public struct NamedBuildTarget { public static NamedBuildTarget Unknown; }
}
