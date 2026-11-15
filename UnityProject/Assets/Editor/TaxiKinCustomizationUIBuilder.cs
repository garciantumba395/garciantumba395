using UnityEditor;
using UnityEditor.Events;
using UnityEngine;
using UnityEngine.Events;
using UnityEngine.UI;
using TaxiKin.Player;
using TaxiKin.UI;
using TaxiKin.Vehicles;
using UiButton = UnityEngine.UI.Button;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Construit les écrans « Mon chauffeur » (création d'avatar) et « Garage » (personnalisation du véhicule),
    /// plus le bandeau de messages. Les libellés sont en français pour l'instant (traduction lingala à l'Étape 7).
    /// </summary>
    public static class TaxiKinCustomizationUIBuilder
    {
        private static readonly Vector2 TL = new Vector2(0f, 1f);
        private static readonly Vector2 TR = new Vector2(1f, 1f);
        private static readonly Vector2 BR = new Vector2(1f, 0f);
        private static readonly Color Neutral = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Gold = new Color(1f, 0.83f, 0f);

        public static void Build(Transform root, GameObject managers, PlayerController player, AvatarPreviewStage stage)
        {
            var font = TaxiKinUIBuilder.font;

            // ---------- Bandeau de messages ----------
            var toastText = Label(root, "Bandeau", new Vector2(0.5f, 1f), new Vector2(0f, -30f), new Vector2(1300f, 70f),
                                  "", 34, TextAnchor.MiddleCenter, Gold, new Vector2(0.5f, 1f));
            toastText.gameObject.AddComponent<Shadow>();
            var toast = root.gameObject.AddComponent<ToastManager>();
            TaxiKinSceneBuilder.Assign(toast, "label", toastText);
            toastText.gameObject.SetActive(false);

            // ---------- Écran « Mon chauffeur » ----------
            var creatorUi = root.gameObject.AddComponent<CharacterCreatorUI>();
            var panel = TaxiKinUIBuilder.MissionPanel(root, "EcranMonChauffeur");
            Label(panel, "Titre", TL, new Vector2(60f, -25f), new Vector2(700f, 80f), "Mon chauffeur", 54, TextAnchor.MiddleLeft, Gold, TL);

            // Aperçu 3D (glisser pour tourner)
            var previewGo = new GameObject("Apercu3D", typeof(RectTransform), typeof(RawImage), typeof(AvatarPreviewDrag));
            previewGo.transform.SetParent(panel, false);
            TaxiKinUIBuilder.Place((RectTransform)previewGo.transform, new Vector2(0f, 0.5f), new Vector2(60f, -20f), new Vector2(560f, 840f), new Vector2(0f, 0.5f));
            TaxiKinSceneBuilder.Assign(stage, "target", previewGo.GetComponent<RawImage>());
            TaxiKinSceneBuilder.Assign(previewGo.GetComponent<AvatarPreviewDrag>(), "stage", stage);

            // Onglets
            string[] tabs = { "Visage", "Cheveux", "Haut", "Bas", "Chaussures", "Accessoires" };
            for (int i = 0; i < tabs.Length; i++)
            {
                var b = Std(panel, "Onglet" + tabs[i], tabs[i], TL, new Vector2(700f + i * 190f, -130f), new Vector2(180f, 70f), Neutral, 28);
                UnityEventTools.AddIntPersistentListener(b.onClick, creatorUi.OnTab, i);
            }

            // Liste des articles (grille 4 colonnes) + modèle de bouton
            var grid = new GameObject("Articles", typeof(RectTransform), typeof(GridLayoutGroup));
            grid.transform.SetParent(panel, false);
            TaxiKinUIBuilder.Place((RectTransform)grid.transform, TL, new Vector2(700f, -230f), new Vector2(1150f, 370f), TL);
            var g = grid.GetComponent<GridLayoutGroup>();
            g.cellSize = new Vector2(270f, 80f);
            g.spacing = new Vector2(10f, 10f);
            g.constraint = GridLayoutGroup.Constraint.FixedColumnCount;
            g.constraintCount = 4;
            var template = Std(panel, "ModeleArticle", "Article", TL, new Vector2(700f, -230f), new Vector2(270f, 80f), Neutral, 26);
            template.gameObject.SetActive(false);

            // Couleur de peau
            Label(panel, "TextePeau", TL, new Vector2(700f, -620f), new Vector2(110f, 60f), "Peau", 30, TextAnchor.MiddleLeft, Color.white, TL);
            for (int i = 0; i < AvatarCatalog.SkinTones.Length; i++)
            {
                var c = AvatarCatalog.SkinTones[i];
                var b = Std(panel, "Peau" + i, "", TL, new Vector2(820f + i * 80f, -620f), new Vector2(70f, 60f), c, 20);
                UnityEventTools.AddIntPersistentListener(b.onClick, creatorUi.OnSkin, i);
            }

            // Couleur / Tenues / Style
            var bColor = Std(panel, "Couleur", "Couleur", TL, new Vector2(700f, -700f), new Vector2(210f, 80f), Neutral, 30);
            var bOutfit = Std(panel, "Tenues", "Tenues", TL, new Vector2(930f, -700f), new Vector2(210f, 80f), Neutral, 30);
            var bStyle = Std(panel, "Style", "Style", TL, new Vector2(1160f, -700f), new Vector2(210f, 80f), Neutral, 30);
            UnityEventTools.AddPersistentListener(bColor.onClick, creatorUi.OnColor);
            UnityEventTools.AddPersistentListener(bOutfit.onClick, creatorUi.OnOutfit);
            UnityEventTools.AddPersistentListener(bStyle.onClick, creatorUi.OnStyle);

            // Nom du chauffeur
            var input = MakeInput(panel, "NomChauffeur", "Nom du chauffeur", TL, new Vector2(700f, -810f), new Vector2(700f, 80f), 16);
            var message = Label(panel, "Message", TL, new Vector2(700f, -900f), new Vector2(1150f, 70f), "", 28, TextAnchor.UpperLeft, new Color(1f, 0.6f, 0.4f), TL);

            // Enregistrer / Annuler
            var save = Std(panel, "Enregistrer", "Enregistrer", BR, new Vector2(-60f, 40f), new Vector2(320f, 100f), TaxiKinUIBuilder.Gas, 34);
            var cancel = Std(panel, "Annuler", "Annuler", BR, new Vector2(-410f, 40f), new Vector2(300f, 100f), TaxiKinUIBuilder.Red, 34);
            UnityEventTools.AddPersistentListener(save.onClick, creatorUi.OnSave);
            UnityEventTools.AddPersistentListener(cancel.onClick, creatorUi.OnCancel);

            TaxiKinSceneBuilder.Assign(creatorUi, "creator", managers.GetComponent<CharacterCreatorManager>());
            TaxiKinSceneBuilder.Assign(creatorUi, "stage", stage);
            TaxiKinSceneBuilder.Assign(creatorUi, "panel", panel.gameObject);
            TaxiKinSceneBuilder.Assign(creatorUi, "itemsContainer", (RectTransform)grid.transform);
            TaxiKinSceneBuilder.Assign(creatorUi, "itemTemplate", template);
            TaxiKinSceneBuilder.Assign(creatorUi, "nameField", input);
            TaxiKinSceneBuilder.Assign(creatorUi, "messageText", message);
            TaxiKinSceneBuilder.Assign(creatorUi, "cancelButton", cancel.gameObject);
            panel.gameObject.SetActive(false);

            // ---------- Panneau « Garage » (personnalisation du véhicule) ----------
            var garageUi = managers.GetComponent<GarageUIManager>();
            var colors = managers.GetComponent<VehicleColorManager>();
            var gp = new GameObject("PanneauGarage", typeof(RectTransform), typeof(Image));
            gp.transform.SetParent(root, false);
            var grt = (RectTransform)gp.transform;
            grt.anchorMin = new Vector2(1f, 0f); grt.anchorMax = new Vector2(1f, 1f); grt.pivot = new Vector2(1f, 0.5f);
            grt.sizeDelta = new Vector2(1000f, 0f); grt.anchoredPosition = Vector2.zero;
            gp.GetComponent<Image>().color = new Color(0.05f, 0.06f, 0.08f, 0.93f);

            // Colonne gauche : carrosserie, toit, jantes, autocollants
            Label(grt, "TitreCarrosserie", TL, new Vector2(20f, -15f), new Vector2(450f, 45f), "Carrosserie", 30, TextAnchor.MiddleLeft, Gold, TL);
            Label(grt, "TitreToit", TL, new Vector2(20f, -230f), new Vector2(450f, 45f), "Toit", 30, TextAnchor.MiddleLeft, Gold, TL);
            for (int i = 0; i < colors.Palette.Count; i++)
            {
                Color col = colors.Palette[i].ToColor();
                float x = 20f + (i % 6) * 70f, row = i / 6 * 70f;
                var body = Std(grt, "Carrosserie" + i, "", TL, new Vector2(x, -65f - row), new Vector2(60f, 60f), col, 18);
                var roof = Std(grt, "Toit" + i, "", TL, new Vector2(x, -280f - row), new Vector2(60f, 60f), col, 18);
                UnityEventTools.AddIntPersistentListener(body.onClick, garageUi.OnBodyColor, i);
                UnityEventTools.AddIntPersistentListener(roof.onClick, garageUi.OnRoofColor, i);
            }
            Label(grt, "NoteJaune", TL, new Vector2(20f, -200f), new Vector2(450f, 28f), "5 jaunes Taxi Kin en tête de palette", 20, TextAnchor.MiddleLeft, new Color(0.8f, 0.8f, 0.8f), TL);

            Label(grt, "TitreJantes", TL, new Vector2(20f, -440f), new Vector2(450f, 45f), "Jantes", 30, TextAnchor.MiddleLeft, Gold, TL);
            string[] rimNames = { "Standard", "Chrome", "Or" };
            for (int i = 0; i < 3; i++)
            {
                var b = Std(grt, "Jantes" + i, rimNames[i], TL, new Vector2(20f + i * 150f, -490f), new Vector2(140f, 60f), Neutral, 24);
                UnityEventTools.AddIntPersistentListener(b.onClick, garageUi.OnRims, i);
            }

            Label(grt, "TitreAutocollants", TL, new Vector2(20f, -580f), new Vector2(450f, 45f), "Autocollants", 30, TextAnchor.MiddleLeft, Gold, TL);
            string[] stickerNames = { "Aucun", "Kin", "Rayures", "Exclusif" };
            for (int i = 0; i < 4; i++)
            {
                var b = Std(grt, "Autocollant" + i, stickerNames[i], TL, new Vector2(20f + (i % 2) * 220f, -630f - (i / 2) * 70f), new Vector2(210f, 60f), Neutral, 24);
                UnityEventTools.AddIntPersistentListener(b.onClick, garageUi.OnSticker, i);
            }

            // Colonne droite : plaque et améliorations
            Label(grt, "TitrePlaque", TL, new Vector2(520f, -15f), new Vector2(450f, 45f), "Plaque personnalisée", 30, TextAnchor.MiddleLeft, Gold, TL);
            var plateInput = MakeInput(grt, "ChampPlaque", "KIN 001", TL, new Vector2(520f, -65f), new Vector2(300f, 70f), 10);
            var plateOk = Std(grt, "ValiderPlaque", "OK", TL, new Vector2(830f, -65f), new Vector2(120f, 70f), TaxiKinUIBuilder.Gas, 28);
            UnityEventTools.AddPersistentListener(plateOk.onClick, garageUi.OnApplyPlate);
            var plateMsg = Label(grt, "MessagePlaque", TL, new Vector2(520f, -145f), new Vector2(450f, 90f), "", 22, TextAnchor.UpperLeft, new Color(1f, 0.8f, 0.5f), TL);

            Label(grt, "TitreAmeliorations", TL, new Vector2(520f, -290f), new Vector2(450f, 45f), "Améliorations", 30, TextAnchor.MiddleLeft, Gold, TL);
            var upgradeLabels = new Text[4];
            for (int i = 0; i < 4; i++)
            {
                upgradeLabels[i] = Label(grt, "Amelioration" + i, TL, new Vector2(520f, -340f - i * 90f), new Vector2(240f, 70f), "", 26, TextAnchor.MiddleLeft, Color.white, TL);
                var minus = Std(grt, "Moins" + i, "-", TL, new Vector2(770f, -340f - i * 90f), new Vector2(80f, 70f), Neutral, 36);
                var plus = Std(grt, "Plus" + i, "+", TL, new Vector2(860f, -340f - i * 90f), new Vector2(80f, 70f), Neutral, 36);
                UnityEventTools.AddIntPersistentListener(minus.onClick, garageUi.OnUpgradeDown, i);
                UnityEventTools.AddIntPersistentListener(plus.onClick, garageUi.OnUpgradeUp, i);
            }
            Label(grt, "NoteTest", TL, new Vector2(520f, -720f), new Vector2(450f, 120f),
                  "Mode test : changements gratuits. Les prix et la boutique arrivent à l'étape suivante.", 22, TextAnchor.UpperLeft, new Color(0.7f, 0.7f, 0.7f), TL);

            var close = Std(grt, "Fermer", "Fermer", BR, new Vector2(-40f, 40f), new Vector2(300f, 90f), TaxiKinUIBuilder.Red, 32);
            UnityEventTools.AddPersistentListener(close.onClick, garageUi.Close);

            TaxiKinSceneBuilder.Assign(garageUi, "panel", gp);
            TaxiKinSceneBuilder.Assign(garageUi, "player", player);
            TaxiKinSceneBuilder.Assign(garageUi, "colors", colors);
            TaxiKinSceneBuilder.Assign(garageUi, "plateField", plateInput);
            TaxiKinSceneBuilder.Assign(garageUi, "plateMessage", plateMsg);
            TaxiKinSceneBuilder.AssignArray(garageUi, "upgradeLabels", upgradeLabels);
            gp.SetActive(false);

            // ---------- Boutons de TEST (haut-droite) : ouvrent les deux écrans ----------
            var bChar = TaxiKinUIBuilder.Button(root, "TestChauffeur", null, "Chauffeur", TR, new Vector2(-310f, -220f), new Vector2(130f, 60f), TaxiKinUIBuilder.Btn);
            var bGarage = TaxiKinUIBuilder.Button(root, "TestGarage", null, "Garage", TR, new Vector2(-450f, -220f), new Vector2(130f, 60f), TaxiKinUIBuilder.Btn);
            TaxiKinUIBuilder.On(bChar, creatorUi.OpenFromMenu);
            TaxiKinUIBuilder.On(bGarage, garageUi.Open);
        }

        // ---------- Aides de construction ----------

        private static UiButton Std(Transform parent, string name, string label, Vector2 anchor, Vector2 pos, Vector2 size, Color color, int fontSize)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Image), typeof(UiButton));
            go.transform.SetParent(parent, false);
            TaxiKinUIBuilder.Place((RectTransform)go.transform, anchor, pos, size, anchor);
            go.GetComponent<Image>().color = color;

            var t = new GameObject("Libelle", typeof(RectTransform), typeof(Text));
            t.transform.SetParent(go.transform, false);
            var rt = (RectTransform)t.transform;
            rt.anchorMin = Vector2.zero; rt.anchorMax = Vector2.one; rt.offsetMin = rt.offsetMax = Vector2.zero;
            var text = t.GetComponent<Text>();
            text.font = TaxiKinUIBuilder.font;
            text.fontSize = fontSize;
            text.alignment = TextAnchor.MiddleCenter;
            text.color = Color.white;
            text.raycastTarget = false;
            text.resizeTextForBestFit = true;
            text.resizeTextMinSize = 14;
            text.resizeTextMaxSize = fontSize;
            text.text = label;
            return go.GetComponent<UiButton>();
        }

        private static Text Label(Transform parent, string name, Vector2 anchor, Vector2 pos, Vector2 size, string text,
                                  int fontSize, TextAnchor align, Color color, Vector2 pivot)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Text));
            go.transform.SetParent(parent, false);
            TaxiKinUIBuilder.Place((RectTransform)go.transform, anchor, pos, size, pivot);
            var t = go.GetComponent<Text>();
            t.font = TaxiKinUIBuilder.font;
            t.fontSize = fontSize; t.alignment = align; t.color = color; t.raycastTarget = false;
            t.horizontalOverflow = HorizontalWrapMode.Wrap;
            t.text = text;
            return t;
        }

        private static InputField MakeInput(Transform parent, string name, string placeholder, Vector2 anchor, Vector2 pos, Vector2 size, int maxChars)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Image), typeof(InputField));
            go.transform.SetParent(parent, false);
            TaxiKinUIBuilder.Place((RectTransform)go.transform, anchor, pos, size, anchor);
            go.GetComponent<Image>().color = new Color(0.95f, 0.95f, 0.95f, 1f);

            var textGo = new GameObject("Texte", typeof(RectTransform), typeof(Text));
            textGo.transform.SetParent(go.transform, false);
            Stretch((RectTransform)textGo.transform, 14f);
            var text = textGo.GetComponent<Text>();
            text.font = TaxiKinUIBuilder.font; text.fontSize = 34; text.color = Color.black; text.alignment = TextAnchor.MiddleLeft;
            text.supportRichText = false;

            var phGo = new GameObject("Indication", typeof(RectTransform), typeof(Text));
            phGo.transform.SetParent(go.transform, false);
            Stretch((RectTransform)phGo.transform, 14f);
            var ph = phGo.GetComponent<Text>();
            ph.font = TaxiKinUIBuilder.font; ph.fontSize = 34; ph.color = new Color(0.4f, 0.4f, 0.4f); ph.alignment = TextAnchor.MiddleLeft;
            ph.fontStyle = FontStyle.Italic; ph.text = placeholder; ph.raycastTarget = false;

            var input = go.GetComponent<InputField>();
            input.textComponent = text;
            input.placeholder = ph;
            input.characterLimit = maxChars;
            return input;
        }

        private static void Stretch(RectTransform rt, float margin)
        {
            rt.anchorMin = Vector2.zero; rt.anchorMax = Vector2.one;
            rt.offsetMin = new Vector2(margin, 4f); rt.offsetMax = new Vector2(-margin, -4f);
        }
    }
}
