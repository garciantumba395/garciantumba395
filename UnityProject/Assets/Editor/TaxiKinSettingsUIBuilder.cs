using UnityEngine;
using UnityEngine.UI;
using UnityEditor.Events;
using TaxiKin.Core;
using TaxiKin.UI;
using UiButton = UnityEngine.UI.Button;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Construit l'écran « Réglages » sur deux colonnes (commandes / langue / progression à gauche ;
    /// graphismes / ombres / son / musique / vibrations / interface à droite), la confirmation de réinitialisation
    /// et la ligne de mesure FPS (développement uniquement).
    /// </summary>
    public static class TaxiKinSettingsUIBuilder
    {
        private static readonly Vector2 TL = new Vector2(0f, 1f);
        private static readonly Vector2 TR = new Vector2(1f, 1f);
        private static readonly Color Neutral = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Gold = new Color(1f, 0.83f, 0f);

        private static Text L(Transform p, string n, Vector2 a, Vector2 pos, Vector2 size, string t, int fs, TextAnchor al, Color c, Vector2 pv) =>
            TaxiKinCustomizationUIBuilder.Label(p, n, a, pos, size, t, fs, al, c, pv);

        private static UiButton S(Transform p, string n, string label, Vector2 a, Vector2 pos, Vector2 size, Color c, int fs) =>
            TaxiKinCustomizationUIBuilder.Std(p, n, label, a, pos, size, c, fs);

        public static void Build(Transform root, GameObject managers)
        {
            var ui = root.gameObject.AddComponent<SettingsUIManager>();
            var panel = TaxiKinUIBuilder.MissionPanel(root, "EcranReglages");

            L(panel, "Titre", TL, new Vector2(60f, -20f), new Vector2(700f, 80f), "Réglages", 54, TextAnchor.MiddleLeft, Gold, TL);
            var close = S(panel, "Fermer", "Fermer", TR, new Vector2(-30f, -20f), new Vector2(220f, 80f), TaxiKinUIBuilder.Gas, 32);
            UnityEventTools.AddPersistentListener(close.onClick, ui.Close);

            // =================== COLONNE GAUCHE (x = 60) ===================
            L(panel, "TitreCommandes", TL, new Vector2(60f, -115f), new Vector2(800f, 50f), "Commandes", 34, TextAnchor.MiddleLeft, Gold, TL);
            string[] modes = { "Volant", "Flèches", "Inclinaison" };
            var modeImages = new Image[3];
            for (int i = 0; i < 3; i++)
            {
                var b = S(panel, "Mode" + i, modes[i], TL, new Vector2(60f + i * 295f, -170f), new Vector2(285f, 75f), Neutral, 28);
                modeImages[i] = b.GetComponent<Image>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnMode, i);
            }

            L(panel, "TexteTaille", TL, new Vector2(60f, -270f), new Vector2(330f, 60f), "Taille des boutons", 26, TextAnchor.MiddleLeft, Color.white, TL);
            var scale = MakeSlider(panel, "CurseurTaille", new Vector2(400f, -270f), new Vector2(380f, 60f), 0.7f, 1.5f);
            var scaleText = L(panel, "ValeurTaille", TL, new Vector2(790f, -270f), new Vector2(150f, 60f), "", 26, TextAnchor.MiddleLeft, Color.white, TL);

            L(panel, "TexteSensibilite", TL, new Vector2(60f, -350f), new Vector2(330f, 60f), "Sensibilité du volant", 26, TextAnchor.MiddleLeft, Color.white, TL);
            var sens = MakeSlider(panel, "CurseurSensibilite", new Vector2(400f, -350f), new Vector2(380f, 60f), 0.5f, 2f);
            var sensText = L(panel, "ValeurSensibilite", TL, new Vector2(790f, -350f), new Vector2(150f, 60f), "", 26, TextAnchor.MiddleLeft, Color.white, TL);

            var invert = S(panel, "Inversion", "", TL, new Vector2(60f, -430f), new Vector2(880f, 75f), Neutral, 26);
            UnityEventTools.AddPersistentListener(invert.onClick, ui.OnInvert);
            UnityEventTools.AddPersistentListener(scale.onValueChanged, ui.OnScale);
            UnityEventTools.AddPersistentListener(sens.onValueChanged, ui.OnSensitivity);

            L(panel, "TitreLangue", TL, new Vector2(60f, -545f), new Vector2(800f, 50f), "Langue", 34, TextAnchor.MiddleLeft, Gold, TL);
            string[] langs = { "Français", "Lingala" };
            var langImages = new Image[2];
            for (int i = 0; i < 2; i++)
            {
                var b = S(panel, "Langue" + i, langs[i], TL, new Vector2(60f + i * 295f, -600f), new Vector2(285f, 75f), Neutral, 28);
                langImages[i] = b.GetComponent<Image>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnLanguage, i);
            }

            L(panel, "TitreProgression", TL, new Vector2(60f, -730f), new Vector2(800f, 50f), "Progression", 34, TextAnchor.MiddleLeft, Gold, TL);
            var reset = S(panel, "Reinitialiser", "Réinitialiser ma progression", TL, new Vector2(60f, -790f), new Vector2(640f, 90f), TaxiKinUIBuilder.Red, 28);
            UnityEventTools.AddPersistentListener(reset.onClick, ui.OnAskReset);

            // =================== COLONNE DROITE (x = 1000) ===================
            L(panel, "TitreGraphismes", TL, new Vector2(1000f, -115f), new Vector2(800f, 50f), "Qualité graphique", 34, TextAnchor.MiddleLeft, Gold, TL);
            string[] qualities = { "Auto", "Basse", "Moyenne", "Haute" };
            var qualityImages = new Image[4];
            for (int i = 0; i < 4; i++)
            {
                var b = S(panel, "Qualite" + i, qualities[i], TL, new Vector2(1000f + i * 220f, -170f), new Vector2(210f, 75f), Neutral, 26);
                qualityImages[i] = b.GetComponent<Image>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnQuality, i);
            }

            L(panel, "TitreOmbres", TL, new Vector2(1000f, -265f), new Vector2(800f, 50f), "Qualité des ombres", 30, TextAnchor.MiddleLeft, Color.white, TL);
            string[] shadows = { "Selon qualité", "Aucune", "Basses", "Hautes" };
            var shadowImages = new Image[4];
            for (int i = 0; i < 4; i++)
            {
                var b = S(panel, "Ombres" + i, shadows[i], TL, new Vector2(1000f + i * 220f, -315f), new Vector2(210f, 70f), Neutral, 24);
                shadowImages[i] = b.GetComponent<Image>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnShadows, i);
            }

            L(panel, "TexteSon", TL, new Vector2(1000f, -420f), new Vector2(240f, 60f), "Son", 26, TextAnchor.MiddleLeft, Color.white, TL);
            var sound = MakeSlider(panel, "CurseurSon", new Vector2(1250f, -420f), new Vector2(420f, 60f), 0f, 1f);
            var soundText = L(panel, "ValeurSon", TL, new Vector2(1690f, -420f), new Vector2(180f, 60f), "", 26, TextAnchor.MiddleLeft, Color.white, TL);

            L(panel, "TexteMusique", TL, new Vector2(1000f, -500f), new Vector2(240f, 60f), "Musique", 26, TextAnchor.MiddleLeft, Color.white, TL);
            var music = MakeSlider(panel, "CurseurMusique", new Vector2(1250f, -500f), new Vector2(420f, 60f), 0f, 1f);
            var musicText = L(panel, "ValeurMusique", TL, new Vector2(1690f, -500f), new Vector2(180f, 60f), "", 26, TextAnchor.MiddleLeft, Color.white, TL);

            var vibration = S(panel, "Vibrations", "", TL, new Vector2(1000f, -585f), new Vector2(870f, 75f), Neutral, 26);
            UnityEventTools.AddPersistentListener(vibration.onClick, ui.OnVibration);

            L(panel, "TexteInterface", TL, new Vector2(1000f, -685f), new Vector2(240f, 60f), "Interface", 26, TextAnchor.MiddleLeft, Color.white, TL);
            var uiScale = MakeSlider(panel, "CurseurInterface", new Vector2(1250f, -685f), new Vector2(420f, 60f), 0.85f, 1.4f);
            var uiScaleText = L(panel, "ValeurInterface", TL, new Vector2(1690f, -685f), new Vector2(180f, 60f), "", 26, TextAnchor.MiddleLeft, Color.white, TL);

            var info = L(panel, "InfoAppareil", TL, new Vector2(1000f, -790f), new Vector2(870f, 120f), "", 22, TextAnchor.UpperLeft, new Color(0.75f, 0.75f, 0.75f), TL);

            UnityEventTools.AddPersistentListener(sound.onValueChanged, ui.OnSound);
            UnityEventTools.AddPersistentListener(music.onValueChanged, ui.OnMusic);
            UnityEventTools.AddPersistentListener(uiScale.onValueChanged, ui.OnUiScale);

            // =================== Fenêtre de confirmation ===================
            var confirm = TaxiKinUIBuilder.MissionPanel(panel, "Confirmation");
            confirm.GetComponent<Image>().color = new Color(0.04f, 0.04f, 0.06f, 0.97f);
            L(confirm, "Titre", new Vector2(0.5f, 1f), new Vector2(0f, -120f), new Vector2(1500f, 90f), "Réinitialiser ma progression ?", 60,
              TextAnchor.MiddleCenter, new Color(1f, 0.5f, 0.4f), new Vector2(0.5f, 1f));
            L(confirm, "Texte", new Vector2(0.5f, 1f), new Vector2(0f, -260f), new Vector2(1400f, 460f),
              "Cette action est DÉFINITIVE.\n\nElle efface : ton avatar et ton nom, ton niveau et ton XP, tes Kin Coins et ton Kin Gold, " +
              "tes véhicules débloqués, tes améliorations et personnalisations, tes missions terminées.\n\n" +
              "Les achats faits avec de l'argent réel pourront être restaurés depuis le magasin, mais pas la monnaie déjà dépensée.",
              34, TextAnchor.UpperCenter, Color.white, new Vector2(0.5f, 1f));
            var cancel = S(confirm, "Annuler", "Non, garder ma progression", new Vector2(0.5f, 0f), new Vector2(-340f, 120f), new Vector2(620f, 110f), TaxiKinUIBuilder.Gas, 32);
            var yes = S(confirm, "Confirmer", "Oui, tout effacer", new Vector2(0.5f, 0f), new Vector2(340f, 120f), new Vector2(620f, 110f), TaxiKinUIBuilder.Red, 32);
            UnityEventTools.AddPersistentListener(cancel.onClick, ui.OnCancelReset);
            UnityEventTools.AddPersistentListener(yes.onClick, ui.OnConfirmReset);

            // =================== Câblage ===================
            TaxiKinSceneBuilder.Assign(ui, "panel", panel.gameObject);
            TaxiKinSceneBuilder.AssignArray(ui, "modeButtons", modeImages);
            TaxiKinSceneBuilder.AssignArray(ui, "languageButtons", langImages);
            TaxiKinSceneBuilder.Assign(ui, "scaleSlider", scale);
            TaxiKinSceneBuilder.Assign(ui, "sensitivitySlider", sens);
            TaxiKinSceneBuilder.Assign(ui, "scaleValue", scaleText);
            TaxiKinSceneBuilder.Assign(ui, "sensitivityValue", sensText);
            TaxiKinSceneBuilder.Assign(ui, "invertLabel", invert.GetComponentInChildren<Text>());
            TaxiKinSceneBuilder.AssignArray(ui, "qualityButtons", qualityImages);
            TaxiKinSceneBuilder.AssignArray(ui, "shadowButtons", shadowImages);
            TaxiKinSceneBuilder.Assign(ui, "soundSlider", sound);
            TaxiKinSceneBuilder.Assign(ui, "musicSlider", music);
            TaxiKinSceneBuilder.Assign(ui, "uiScaleSlider", uiScale);
            TaxiKinSceneBuilder.Assign(ui, "soundValue", soundText);
            TaxiKinSceneBuilder.Assign(ui, "musicValue", musicText);
            TaxiKinSceneBuilder.Assign(ui, "uiScaleValue", uiScaleText);
            TaxiKinSceneBuilder.Assign(ui, "vibrationLabel", vibration.GetComponentInChildren<Text>());
            TaxiKinSceneBuilder.Assign(ui, "deviceInfo", info);
            TaxiKinSceneBuilder.Assign(ui, "confirmPanel", confirm.gameObject);
            TaxiKinSceneBuilder.Assign(ui, "confirmButton", yes);
            TaxiKinSceneBuilder.Assign(ui, "confirmLabel", yes.GetComponentInChildren<Text>());
            confirm.gameObject.SetActive(false);
            panel.gameObject.SetActive(false);

            // Ligne de mesure FPS / mémoire (visible seulement en développement) : bas-gauche, au-dessus des commandes
            var fps = L(root, "MesureFPS", new Vector2(0.5f, 0f), new Vector2(0f, 8f), new Vector2(700f, 36f), "", 22,
                        TextAnchor.MiddleCenter, new Color(0.6f, 1f, 0.6f), new Vector2(0.5f, 0f));
            TaxiKinSceneBuilder.Assign(managers.GetComponent<PerformanceMonitor>(), "overlay", fps);

            // Bouton de TEST (haut-droite) : ouvre les réglages
            var open = TaxiKinUIBuilder.Button(root, "TestReglages", null, "Réglages", TR, new Vector2(-730f, -220f), new Vector2(130f, 60f), TaxiKinUIBuilder.Btn);
            TaxiKinUIBuilder.On(open, ui.Open);
        }

        /// <summary>Curseur standard (fond, remplissage, poignée).</summary>
        private static Slider MakeSlider(Transform parent, string name, Vector2 pos, Vector2 size, float min, float max)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Slider));
            go.transform.SetParent(parent, false);
            TaxiKinUIBuilder.Place((RectTransform)go.transform, TL, pos, size, TL);

            Part(go.transform, "Fond", new Vector2(0f, 0.3f), new Vector2(1f, 0.7f), Vector2.zero, Vector2.zero, new Color(0.25f, 0.25f, 0.3f, 1f));
            var fillArea = new GameObject("ZoneRemplissage", typeof(RectTransform));
            fillArea.transform.SetParent(go.transform, false);
            var far = (RectTransform)fillArea.transform;
            far.anchorMin = new Vector2(0f, 0.3f); far.anchorMax = new Vector2(1f, 0.7f); far.offsetMin = new Vector2(10f, 0f); far.offsetMax = new Vector2(-10f, 0f);
            var fill = Part(far, "Remplissage", Vector2.zero, Vector2.one, Vector2.zero, Vector2.zero, new Color(1f, 0.83f, 0f, 1f));

            var handleArea = new GameObject("ZonePoignee", typeof(RectTransform));
            handleArea.transform.SetParent(go.transform, false);
            var har = (RectTransform)handleArea.transform;
            har.anchorMin = Vector2.zero; har.anchorMax = Vector2.one; har.offsetMin = new Vector2(20f, 0f); har.offsetMax = new Vector2(-20f, 0f);
            var handle = Part(har, "Poignee", new Vector2(0f, 0f), new Vector2(0f, 1f), Vector2.zero, new Vector2(40f, 0f), Color.white);

            var s = go.GetComponent<Slider>();
            s.fillRect = fill;
            s.handleRect = handle;
            s.targetGraphic = handle.GetComponent<Image>();
            s.direction = Slider.Direction.LeftToRight;
            s.minValue = min; s.maxValue = max; s.value = min;
            return s;
        }

        private static RectTransform Part(Transform parent, string name, Vector2 aMin, Vector2 aMax, Vector2 pos, Vector2 size, Color color)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Image));
            go.transform.SetParent(parent, false);
            var rt = (RectTransform)go.transform;
            rt.anchorMin = aMin; rt.anchorMax = aMax;
            if (size == Vector2.zero) { rt.offsetMin = Vector2.zero; rt.offsetMax = Vector2.zero; }
            else { rt.anchoredPosition = pos; rt.sizeDelta = size; }
            go.GetComponent<Image>().color = color;
            return rt;
        }
    }
}
