using UnityEngine;
using UnityEngine.UI;
using UnityEditor.Events;
using UnityEventTools = UnityEditor.Events.UnityEventTools;   // évite l'ambiguïté avec UnityEngine.Events
using TaxiKin.Missions;
using TaxiKin.Player;
using TaxiKin.UI;
using UiButton = UnityEngine.UI.Button;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Construit l'écran de proposition du tutoriel, le bandeau d'instructions et l'anneau de mise en évidence.
    /// À appeler EN DERNIER : ces éléments doivent être au-dessus de tous les autres.
    /// </summary>
    public static class TaxiKinTutorialUIBuilder
    {
        private static readonly Vector2 TL = new Vector2(0f, 1f);
        private static readonly Vector2 TR = new Vector2(1f, 1f);
        private static readonly Vector2 BR = new Vector2(1f, 0f);
        private static readonly Color Gold = new Color(1f, 0.83f, 0f);
        private static readonly Color Neutral = new Color(0.2f, 0.2f, 0.24f, 0.95f);

        private static Text L(Transform p, string n, Vector2 a, Vector2 pos, Vector2 size, string t, int fs, TextAnchor al, Color c, Vector2 pv) =>
            TaxiKinCustomizationUIBuilder.Label(p, n, a, pos, size, t, fs, al, c, pv);

        private static UiButton S(Transform p, string n, string key, Vector2 a, Vector2 pos, Vector2 size, Color c, int fs)
        {
            var b = TaxiKinCustomizationUIBuilder.Std(p, n, "", a, pos, size, c, fs);
            b.GetComponentInChildren<Text>().gameObject.AddComponent<LocalizedText>().Key = key;     // texte suivant la langue
            return b;
        }

        public static void Build(Transform root, GameObject managers, PlayerController player, CameraRig rig)
        {
            var tm = root.gameObject.AddComponent<TutorialManager>();

            // =================== Proposition ===================
            var offer = TaxiKinUIBuilder.MissionPanel(root, "EcranTutoriel");
            offer.GetComponent<Image>().color = new Color(0.03f, 0.04f, 0.06f, 0.92f);
            L(offer, "Titre", new Vector2(0.5f, 1f), new Vector2(0f, -170f), new Vector2(1200f, 100f), "", 72, TextAnchor.MiddleCenter, Gold, new Vector2(0.5f, 1f))
                .gameObject.AddComponent<LocalizedText>().Key = "tuto_title";
            L(offer, "Texte", new Vector2(0.5f, 1f), new Vector2(0f, -320f), new Vector2(1200f, 220f), "", 40, TextAnchor.UpperCenter, Color.white, new Vector2(0.5f, 1f))
                .gameObject.AddComponent<LocalizedText>().Key = "tuto_offer";
            var accept = S(offer, "Commencer", "tuto_start", new Vector2(0.5f, 0f), new Vector2(-330f, 160f), new Vector2(560f, 120f), TaxiKinUIBuilder.Gas, 42);
            var skip = S(offer, "Passer", "tuto_skip", new Vector2(0.5f, 0f), new Vector2(330f, 160f), new Vector2(560f, 120f), Neutral, 42);
            UnityEventTools.AddPersistentListener(accept.onClick, tm.OnAccept);
            UnityEventTools.AddPersistentListener(skip.onClick, tm.OnSkipOffer);
            offer.gameObject.SetActive(false);

            // =================== Bandeau d'instructions ===================
            var bannerGo = new GameObject("BandeauTutoriel", typeof(RectTransform), typeof(Image));
            bannerGo.transform.SetParent(root, false);
            TaxiKinUIBuilder.Place((RectTransform)bannerGo.transform, new Vector2(0.5f, 1f), new Vector2(0f, -230f), new Vector2(1100f, 150f), new Vector2(0.5f, 1f));
            bannerGo.GetComponent<Image>().color = new Color(0f, 0f, 0f, 0.88f);
            var stepText = L(bannerGo.transform, "Etape", TL, new Vector2(20f, -10f), new Vector2(200f, 40f), "", 26, TextAnchor.MiddleLeft, Gold, TL);
            var instruction = L(bannerGo.transform, "Consigne", new Vector2(0.5f, 0.5f), new Vector2(0f, 0f), new Vector2(940f, 110f), "", 34, TextAnchor.MiddleCenter, Color.white, new Vector2(0.5f, 0.5f));
            var next = S(bannerGo.transform, "Suivant", "tuto_next", BR, new Vector2(-15f, 15f), new Vector2(200f, 60f), TaxiKinUIBuilder.Gas, 28);
            var skipBanner = S(bannerGo.transform, "PasserTutoriel", "tuto_skip", TR, new Vector2(-15f, -10f), new Vector2(170f, 46f), Neutral, 22);
            UnityEventTools.AddPersistentListener(next.onClick, tm.OnNext);
            UnityEventTools.AddPersistentListener(skipBanner.onClick, tm.OnSkip);
            bannerGo.SetActive(false);

            // =================== Anneau de mise en évidence ===================
            var ring = new GameObject("AnneauTutoriel", typeof(RectTransform), typeof(Image));
            ring.transform.SetParent(root, false);
            var ringImg = ring.GetComponent<Image>();
            ringImg.sprite = UnityEditor.AssetDatabase.GetBuiltinExtraResource<Sprite>("UI/Skin/Knob.psd");
            ringImg.color = new Color(1f, 0.83f, 0f, 0.35f);
            ringImg.raycastTarget = false;                          // ne bloque jamais les touches
            var ringRt = (RectTransform)ring.transform;
            ringRt.anchorMin = ringRt.anchorMax = new Vector2(0.5f, 0.5f); ringRt.pivot = new Vector2(0.5f, 0.5f);
            ringRt.sizeDelta = new Vector2(160f, 160f);
            ring.SetActive(false);

            // =================== Câblage ===================
            TaxiKinSceneBuilder.Assign(tm, "player", player);
            TaxiKinSceneBuilder.Assign(tm, "missions", managers.GetComponent<MissionManager>());
            TaxiKinSceneBuilder.Assign(tm, "passengers", managers.GetComponent<PassengerManager>());
            if (rig != null) TaxiKinSceneBuilder.Assign(tm, "cameraRig", rig);
            TaxiKinSceneBuilder.Assign(tm, "controls", root.parent.GetComponent<ControlsLayout>());
            var menuUi = root.GetComponent<MenuUIManager>();
            TaxiKinSceneBuilder.Assign(tm, "menu", menuUi);
            var menuButton = root.Find("BoutonMenu");
            if (menuButton != null) TaxiKinSceneBuilder.Assign(tm, "menuButton", (RectTransform)menuButton);
            TaxiKinSceneBuilder.Assign(tm, "offerPanel", offer.gameObject);
            TaxiKinSceneBuilder.Assign(tm, "banner", bannerGo);
            TaxiKinSceneBuilder.Assign(tm, "stepText", stepText);
            TaxiKinSceneBuilder.Assign(tm, "instructionText", instruction);
            TaxiKinSceneBuilder.Assign(tm, "nextButton", next.gameObject);
            TaxiKinSceneBuilder.Assign(tm, "highlight", ringRt);

            var settings = root.GetComponent<SettingsUIManager>();
            if (settings != null)
            {
                TaxiKinSceneBuilder.Assign(settings, "tutorial", tm);
                TaxiKinSceneBuilder.Assign(settings, "menu", menuUi);
            }

            // Tout au-dessus : anneau et bandeau sous la proposition, la proposition tout en haut
            bannerGo.transform.SetAsLastSibling();
            ring.transform.SetAsLastSibling();
            offer.SetAsLastSibling();
        }
    }
}
