using UnityEngine;
using UnityEngine.UI;
using UnityEditor.Events;
using UnityEventTools = UnityEditor.Events.UnityEventTools;   // évite l'ambiguïté avec UnityEngine.Events
using TaxiKin.Economy;
using TaxiKin.Player;
using TaxiKin.Purchases;
using TaxiKin.UI;
using TaxiKin.Vehicles;
using UiButton = UnityEngine.UI.Button;

namespace TaxiKin.EditorTools
{
    /// <summary>Construit les écrans « Garage » (aperçu 3D, réparation, carburant, personnalisation) et « Boutique ».</summary>
    public static class TaxiKinGarageShopUIBuilder
    {
        private static readonly Vector2 TL = new Vector2(0f, 1f);
        private static readonly Vector2 TR = new Vector2(1f, 1f);
        private static readonly Color Neutral = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Gold = new Color(1f, 0.83f, 0f);
        private static readonly Color Soft = new Color(0.8f, 0.8f, 0.8f);

        public static void Build(Transform root, GameObject managers, PlayerController player, VehiclePreviewStage stage)
        {
            var garageUi = managers.GetComponent<GarageUIManager>();
            var garage = managers.GetComponent<GarageManager>();
            var colors = managers.GetComponent<VehicleColorManager>();
            var shop = managers.GetComponent<ShopManager>();
            var shopUi = root.gameObject.AddComponent<ShopUIManager>();

            BuildGarage(root, garageUi, garage, colors, stage, shopUi);
            BuildShop(root, shopUi, shop, managers.GetComponent<PurchaseManager>(), garageUi);

            // Boutons de TEST (haut-droite)
            var bGarage = TaxiKinUIBuilder.Button(root, "TestGarage", null, "Garage", TR, new Vector2(-450f, -220f), new Vector2(130f, 60f), TaxiKinUIBuilder.Btn);
            var bShop = TaxiKinUIBuilder.Button(root, "TestBoutique", null, "Boutique", TR, new Vector2(-590f, -220f), new Vector2(130f, 60f), TaxiKinUIBuilder.Btn);
            TaxiKinUIBuilder.On(bGarage, garageUi.Open);
            TaxiKinUIBuilder.On(bShop, shopUi.Open);
        }


        // Raccourcis (C# 9 : pas de « var f = Méthode » sur un groupe de méthodes)
        private static Text L(Transform parent, string name, Vector2 anchor, Vector2 pos, Vector2 size, string text,
                              int fontSize, TextAnchor align, Color color, Vector2 pivot) =>
            TaxiKinCustomizationUIBuilder.Label(parent, name, anchor, pos, size, text, fontSize, align, color, pivot);

        private static UiButton S(Transform parent, string name, string label, Vector2 anchor, Vector2 pos, Vector2 size, Color color, int fontSize) =>
            TaxiKinCustomizationUIBuilder.Std(parent, name, label, anchor, pos, size, color, fontSize);

        // =====================================================================================
        // GARAGE
        // =====================================================================================
        private static void BuildGarage(Transform root, GarageUIManager ui, GarageManager garage, VehicleColorManager colors,
                                        VehiclePreviewStage stage, ShopUIManager shopUi)
        {
            var panel = TaxiKinUIBuilder.MissionPanel(root, "EcranGarage");

            L(panel, "Titre", TL, new Vector2(40f, -15f), new Vector2(500f, 70f), "Garage", 50, TextAnchor.MiddleLeft, Gold, TL);

            // --- Colonne gauche : aperçu, choix, état ---
            var preview = new GameObject("Apercu3D", typeof(RectTransform), typeof(RawImage), typeof(VehiclePreviewDrag));
            preview.transform.SetParent(panel, false);
            TaxiKinUIBuilder.Place((RectTransform)preview.transform, TL, new Vector2(40f, -90f), new Vector2(760f, 475f), TL);
            TaxiKinSceneBuilder.Assign(stage, "target", preview.GetComponent<RawImage>());
            TaxiKinSceneBuilder.Assign(preview.GetComponent<VehiclePreviewDrag>(), "stage", stage);

            var prev = S(panel, "Precedent", "<", TL, new Vector2(40f, -580f), new Vector2(90f, 80f), Neutral, 40);
            var next = S(panel, "Suivant", ">", TL, new Vector2(140f, -580f), new Vector2(90f, 80f), Neutral, 40);
            var vName = L(panel, "NomVehicule", TL, new Vector2(250f, -580f), new Vector2(550f, 80f), "", 38, TextAnchor.MiddleLeft, Color.white, TL);
            var vStatus = L(panel, "Statut", TL, new Vector2(40f, -670f), new Vector2(760f, 50f), "", 24, TextAnchor.MiddleLeft, Soft, TL);
            var use = S(panel, "Utiliser", "Utiliser", TL, new Vector2(40f, -730f), new Vector2(250f, 80f), TaxiKinUIBuilder.Gas, 30);
            var shopBtn = S(panel, "Boutique", "Boutique", TL, new Vector2(310f, -730f), new Vector2(250f, 80f), Neutral, 30);
            var cond = L(panel, "Etat", TL, new Vector2(40f, -830f), new Vector2(760f, 50f), "", 28, TextAnchor.MiddleLeft, Color.white, TL);
            var repair = S(panel, "Reparer", "Réparer", TL, new Vector2(40f, -890f), new Vector2(370f, 90f), TaxiKinUIBuilder.Red, 28);
            var refuel = S(panel, "Carburant", "Plein", TL, new Vector2(430f, -890f), new Vector2(370f, 90f), Neutral, 28);

            UnityEventTools.AddPersistentListener(prev.onClick, ui.OnPrevious);
            UnityEventTools.AddPersistentListener(next.onClick, ui.OnNext);
            UnityEventTools.AddPersistentListener(use.onClick, ui.OnUse);
            UnityEventTools.AddPersistentListener(shopBtn.onClick, ui.OnOpenShop);
            UnityEventTools.AddPersistentListener(repair.onClick, ui.OnRepair);
            UnityEventTools.AddPersistentListener(refuel.onClick, ui.OnRefuel);

            // --- Colonne droite : couleurs, jantes, autocollants, plaque, améliorations ---
            L(panel, "TitreCarrosserie", TL, new Vector2(860f, -20f), new Vector2(500f, 40f), "Carrosserie (les prix s'affichent sur les couleurs verrouillées)", 24, TextAnchor.MiddleLeft, Gold, TL);
            L(panel, "TitreToit", TL, new Vector2(860f, -230f), new Vector2(500f, 40f), "Toit", 24, TextAnchor.MiddleLeft, Gold, TL);
            int n = colors.Palette.Count;
            var bodyLabels = new Text[n]; var roofLabels = new Text[n];
            for (int i = 0; i < n; i++)
            {
                Color c = colors.Palette[i].ToColor();
                float x = 860f + (i % 6) * 100f, row = (i / 6) * 70f;
                var body = S(panel, "Carrosserie" + i, "", TL, new Vector2(x, -65f - row), new Vector2(90f, 60f), c, 22);
                var roof = S(panel, "Toit" + i, "", TL, new Vector2(x, -275f - row), new Vector2(90f, 60f), c, 22);
                bodyLabels[i] = body.GetComponentInChildren<Text>(); roofLabels[i] = roof.GetComponentInChildren<Text>();
                bodyLabels[i].color = roofLabels[i].color = Color.black;
                UnityEventTools.AddIntPersistentListener(body.onClick, ui.OnBodyColor, i);
                UnityEventTools.AddIntPersistentListener(roof.onClick, ui.OnRoofColor, i);
            }

            L(panel, "TitreJantes", TL, new Vector2(860f, -430f), new Vector2(500f, 40f), "Jantes", 24, TextAnchor.MiddleLeft, Gold, TL);
            var rimLabels = new Text[3];
            for (int i = 0; i < 3; i++)
            {
                var b = S(panel, "Jantes" + i, "", TL, new Vector2(860f + i * 270f, -475f), new Vector2(260f, 65f), Neutral, 24);
                rimLabels[i] = b.GetComponentInChildren<Text>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnRims, i);
            }

            L(panel, "TitreAutocollants", TL, new Vector2(860f, -560f), new Vector2(500f, 40f), "Autocollants", 24, TextAnchor.MiddleLeft, Gold, TL);
            var stickerLabels = new Text[4];
            for (int i = 0; i < 4; i++)
            {
                var b = S(panel, "Autocollant" + i, "", TL, new Vector2(860f + i * 255f, -605f), new Vector2(245f, 65f), Neutral, 22);
                stickerLabels[i] = b.GetComponentInChildren<Text>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnSticker, i);
            }

            L(panel, "TitrePlaque", TL, new Vector2(860f, -695f), new Vector2(500f, 40f), "Plaque personnalisée", 24, TextAnchor.MiddleLeft, Gold, TL);
            var plateInput = TaxiKinCustomizationUIBuilder.MakeInput(panel, "ChampPlaque", "KIN 001", TL, new Vector2(860f, -740f), new Vector2(420f, 65f), 10);
            var plateOk = S(panel, "ValiderPlaque", "OK", TL, new Vector2(1300f, -740f), new Vector2(260f, 65f), TaxiKinUIBuilder.Gas, 24);

            L(panel, "TitreAmeliorations", TL, new Vector2(860f, -830f), new Vector2(500f, 40f), "Améliorations (Kin Coins)", 24, TextAnchor.MiddleLeft, Gold, TL);
            var upgradeLabels = new Text[4];
            for (int i = 0; i < 4; i++)
            {
                var b = S(panel, "Amelioration" + i, "", TL, new Vector2(860f + i * 255f, -875f), new Vector2(245f, 80f), Neutral, 22);
                upgradeLabels[i] = b.GetComponentInChildren<Text>();
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnUpgrade, i);
            }

            var msg = L(panel, "Message", TL, new Vector2(860f, -970f), new Vector2(1020f, 90f), "", 24, TextAnchor.UpperLeft, new Color(1f, 0.85f, 0.5f), TL);
            var close = S(panel, "Fermer", "Fermer", TR, new Vector2(-30f, -15f), new Vector2(200f, 70f), TaxiKinUIBuilder.Red, 30);
            UnityEventTools.AddPersistentListener(close.onClick, ui.Close);
            UnityEventTools.AddPersistentListener(plateOk.onClick, ui.OnApplyPlate);

            // --- Câblage ---
            TaxiKinSceneBuilder.Assign(ui, "panel", panel.gameObject);
            TaxiKinSceneBuilder.Assign(ui, "garage", garage);
            TaxiKinSceneBuilder.Assign(ui, "colors", colors);
            TaxiKinSceneBuilder.Assign(ui, "shopUi", shopUi);
            TaxiKinSceneBuilder.Assign(ui, "vehicleName", vName);
            TaxiKinSceneBuilder.Assign(ui, "vehicleStatus", vStatus);
            TaxiKinSceneBuilder.Assign(ui, "conditionText", cond);
            TaxiKinSceneBuilder.Assign(ui, "repairLabel", repair.GetComponentInChildren<Text>());
            TaxiKinSceneBuilder.Assign(ui, "refuelLabel", refuel.GetComponentInChildren<Text>());
            TaxiKinSceneBuilder.Assign(ui, "useLabel", use.GetComponentInChildren<Text>());
            TaxiKinSceneBuilder.Assign(ui, "messageText", msg);
            TaxiKinSceneBuilder.Assign(ui, "plateLabel", plateOk.GetComponentInChildren<Text>());
            TaxiKinSceneBuilder.Assign(ui, "plateField", plateInput);
            TaxiKinSceneBuilder.AssignArray(ui, "bodyLabels", bodyLabels);
            TaxiKinSceneBuilder.AssignArray(ui, "roofLabels", roofLabels);
            TaxiKinSceneBuilder.AssignArray(ui, "rimLabels", rimLabels);
            TaxiKinSceneBuilder.AssignArray(ui, "stickerLabels", stickerLabels);
            TaxiKinSceneBuilder.AssignArray(ui, "upgradeLabels", upgradeLabels);
            panel.gameObject.SetActive(false);
        }

        // =====================================================================================
        // BOUTIQUE
        // =====================================================================================
        private static void BuildShop(Transform root, ShopUIManager ui, ShopManager shop, PurchaseManager purchases, GarageUIManager garageUi)
        {
            var panel = TaxiKinUIBuilder.MissionPanel(root, "EcranBoutique");

            L(panel, "Titre", TL, new Vector2(40f, -15f), new Vector2(500f, 70f), "Boutique", 50, TextAnchor.MiddleLeft, Gold, TL);
            var coins = L(panel, "SoldePieces", TL, new Vector2(620f, -25f), new Vector2(480f, 50f), "", 34, TextAnchor.MiddleLeft, Color.white, TL);
            var gold = L(panel, "SoldeGold", TL, new Vector2(1120f, -25f), new Vector2(480f, 50f), "", 34, TextAnchor.MiddleLeft, Gold, TL);
            var close = S(panel, "Fermer", "Fermer", TR, new Vector2(-30f, -15f), new Vector2(200f, 70f), TaxiKinUIBuilder.Red, 30);
            UnityEventTools.AddPersistentListener(close.onClick, ui.Close);

            // Onglets (ordre = énumération ShopCategory)
            string[] tabs = { "Véhicules", "Skins", "Couleurs", "Jantes", "Autocollants", "Plaques", "Carburant", "Réparations",
                              "Améliorations", "Sans pub", "Packs", "Kin Gold", "Vêtements" };
            for (int i = 0; i < tabs.Length; i++)
            {
                var b = S(panel, "Onglet" + i, tabs[i], TL, new Vector2(40f + (i % 7) * 262f, -100f - (i / 7) * 70f), new Vector2(252f, 62f), Neutral, 24);
                UnityEventTools.AddIntPersistentListener(b.onClick, ui.OnCategory, i);
            }

            // Liste des articles
            var list = new GameObject("Articles", typeof(RectTransform), typeof(VerticalLayoutGroup));
            list.transform.SetParent(panel, false);
            TaxiKinUIBuilder.Place((RectTransform)list.transform, TL, new Vector2(40f, -250f), new Vector2(1840f, 700f), TL);
            var v = list.GetComponent<VerticalLayoutGroup>();
            v.spacing = 8f; v.childControlHeight = true; v.childControlWidth = true;
            v.childForceExpandHeight = false; v.childForceExpandWidth = true;

            var row = BuildRowTemplate(panel);
            row.gameObject.SetActive(false);

            var note = L(panel, "Note", TL, new Vector2(40f, -260f), new Vector2(1840f, 120f), "", 30, TextAnchor.UpperLeft, Soft, TL);
            var garageBtn = S(panel, "OuvrirGarage", "Ouvrir le garage", TL, new Vector2(40f, -400f), new Vector2(400f, 90f), TaxiKinUIBuilder.Gas, 30);
            UnityEventTools.AddPersistentListener(garageBtn.onClick, ui.OnOpenGarage);
            var msg = L(panel, "Message", TL, new Vector2(40f, -960f), new Vector2(1840f, 100f), "", 28, TextAnchor.UpperLeft, new Color(1f, 0.85f, 0.5f), TL);
            L(panel, "Mention", TL, new Vector2(40f, -1030f), new Vector2(1840f, 40f),
              "Les achats sont facultatifs : tout se débloque en jouant. Prix indicatifs ; le magasin affiche le prix exact avant de payer.", 20, TextAnchor.MiddleLeft, Soft, TL);

            TaxiKinSceneBuilder.Assign(ui, "panel", panel.gameObject);
            TaxiKinSceneBuilder.Assign(ui, "shop", shop);
            TaxiKinSceneBuilder.Assign(ui, "purchases", purchases);
            TaxiKinSceneBuilder.Assign(ui, "rowsContainer", (RectTransform)list.transform);
            TaxiKinSceneBuilder.Assign(ui, "rowTemplate", row);
            TaxiKinSceneBuilder.Assign(ui, "coinsText", coins);
            TaxiKinSceneBuilder.Assign(ui, "goldText", gold);
            TaxiKinSceneBuilder.Assign(ui, "messageText", msg);
            TaxiKinSceneBuilder.Assign(ui, "noteText", note);
            TaxiKinSceneBuilder.Assign(ui, "openGarageButton", garageBtn.gameObject);
            TaxiKinSceneBuilder.Assign(ui, "garageUi", garageUi);
            panel.gameObject.SetActive(false);
        }

        /// <summary>Modèle d'une ligne : titre, description, « Possédé », trois boutons de prix.</summary>
        private static ShopRow BuildRowTemplate(Transform parent)
        {
            var go = new GameObject("ModeleLigne", typeof(RectTransform), typeof(Image), typeof(ShopRow), typeof(LayoutElement));
            go.transform.SetParent(parent, false);
            TaxiKinUIBuilder.Place((RectTransform)go.transform, TL, new Vector2(40f, -250f), new Vector2(1840f, 100f), TL);
            go.GetComponent<Image>().color = new Color(0.14f, 0.15f, 0.19f, 0.95f);
            go.GetComponent<LayoutElement>().preferredHeight = 100f;

            var row = go.GetComponent<ShopRow>();

            row.title = L(go.transform, "Titre", TL, new Vector2(20f, -8f), new Vector2(960f, 42f), "", 32, TextAnchor.MiddleLeft, Color.white, TL);
            row.description = L(go.transform, "Description", TL, new Vector2(20f, -52f), new Vector2(980f, 44f), "", 21, TextAnchor.UpperLeft, Soft, TL);
            row.owned = L(go.transform, "Possede", TL, new Vector2(1500f, -28f), new Vector2(300f, 44f), "Possédé", 32, TextAnchor.MiddleCenter, new Color(0.5f, 1f, 0.5f), TL);

            row.coinsButton = S(go.transform, "PrixPieces", "", TL, new Vector2(1010f, -15f), new Vector2(260f, 70f), Neutral, 24);
            row.goldButton = S(go.transform, "PrixGold", "", TL, new Vector2(1285f, -15f), new Vector2(260f, 70f), new Color(0.55f, 0.42f, 0f, 0.95f), 24);
            row.moneyButton = S(go.transform, "PrixArgent", "", TL, new Vector2(1560f, -15f), new Vector2(260f, 70f), TaxiKinUIBuilder.Gas, 24);
            row.coinsText = row.coinsButton.GetComponentInChildren<Text>();
            row.goldText = row.goldButton.GetComponentInChildren<Text>();
            row.moneyText = row.moneyButton.GetComponentInChildren<Text>();
            return row;
        }
    }
}
