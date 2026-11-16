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
    /// Construit le menu principal et la liste des missions, le bouton « Menu » du HUD, le bouton « Menu » de l'écran de fin
    /// de mission, et range les outils de test dans un conteneur réservé au développement.
    /// À appeler EN DERNIER : les panneaux du menu sont ensuite placés SOUS les autres écrans plein écran (créateur de chauffeur,
    /// garage, boutique, réglages), pour que ces écrans s'affichent par-dessus le menu qui les ouvre.
    /// </summary>
    public static class TaxiKinMenuUIBuilder
    {
        private static readonly Vector2 TL = new Vector2(0f, 1f);
        private static readonly Vector2 TR = new Vector2(1f, 1f);
        private static readonly Color Neutral = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Gold = new Color(1f, 0.83f, 0f);
        private static readonly Color Soft = new Color(0.8f, 0.8f, 0.8f);

        private static Text L(Transform p, string n, Vector2 a, Vector2 pos, Vector2 size, string t, int fs, TextAnchor al, Color c, Vector2 pv) =>
            TaxiKinCustomizationUIBuilder.Label(p, n, a, pos, size, t, fs, al, c, pv);

        private static UiButton S(Transform p, string n, string label, Vector2 a, Vector2 pos, Vector2 size, Color c, int fs) =>
            TaxiKinCustomizationUIBuilder.Std(p, n, label, a, pos, size, c, fs);

        public static void Build(Transform root, GameObject managers, AvatarPreviewStage avatarStage)
        {
            var menuUi = root.gameObject.AddComponent<MenuUIManager>();

            // =================== MENU PRINCIPAL ===================
            var menu = TaxiKinUIBuilder.MissionPanel(root, "EcranMenu");
            menu.GetComponent<Image>().color = new Color(0.04f, 0.05f, 0.07f, 0.94f);

            var avatarGo = new GameObject("AvatarMenu", typeof(RectTransform), typeof(RawImage), typeof(AvatarPreviewDrag));
            avatarGo.transform.SetParent(menu, false);
            TaxiKinUIBuilder.Place((RectTransform)avatarGo.transform, new Vector2(0f, 0.5f), new Vector2(70f, -20f), new Vector2(520f, 780f), new Vector2(0f, 0.5f));
            TaxiKinSceneBuilder.Assign(avatarGo.GetComponent<AvatarPreviewDrag>(), "stage", avatarStage);

            L(menu, "Titre", TL, new Vector2(680f, -30f), new Vector2(1200f, 110f), "TAXI KIN", 92, TextAnchor.MiddleLeft, Gold, TL);
            L(menu, "SousTitre", TL, new Vector2(680f, -140f), new Vector2(1200f, 50f), "Wewa · Taxi · 207", 34, TextAnchor.MiddleLeft, Color.white, TL);
            var nameText = L(menu, "NomChauffeur", TL, new Vector2(680f, -215f), new Vector2(1200f, 60f), "", 46, TextAnchor.MiddleLeft, Color.white, TL);
            var levelText = L(menu, "Niveau", TL, new Vector2(680f, -278f), new Vector2(1200f, 45f), "", 28, TextAnchor.MiddleLeft, Soft, TL);

            L(menu, "LibelleCoins", TL, new Vector2(680f, -335f), new Vector2(190f, 50f), "Kin Coins", 28, TextAnchor.MiddleLeft, Soft, TL);
            var coinsText = L(menu, "Coins", TL, new Vector2(870f, -335f), new Vector2(230f, 50f), "", 34, TextAnchor.MiddleLeft, Color.white, TL);
            L(menu, "LibelleGold", TL, new Vector2(1150f, -335f), new Vector2(190f, 50f), "Kin Gold", 28, TextAnchor.MiddleLeft, Soft, TL);
            var goldText = L(menu, "Gold", TL, new Vector2(1340f, -335f), new Vector2(230f, 50f), "", 34, TextAnchor.MiddleLeft, Gold, TL);

            var play = S(menu, "Jouer", "JOUER", TL, new Vector2(680f, -410f), new Vector2(570f, 120f), TaxiKinUIBuilder.Gas, 46);
            var missionsBtn = S(menu, "Missions", "Missions", TL, new Vector2(1270f, -410f), new Vector2(570f, 120f), Neutral, 36);
            var garage = S(menu, "Garage", "Garage", TL, new Vector2(680f, -550f), new Vector2(570f, 110f), Neutral, 34);
            var shop = S(menu, "Boutique", "Boutique", TL, new Vector2(1270f, -550f), new Vector2(570f, 110f), Neutral, 34);
            var driver = S(menu, "MonChauffeur", "Mon chauffeur", TL, new Vector2(680f, -680f), new Vector2(570f, 110f), Neutral, 34);
            var settings = S(menu, "Reglages", "Réglages", TL, new Vector2(1270f, -680f), new Vector2(570f, 110f), Neutral, 34);
            var resume = S(menu, "Reprendre", "Reprendre la mission", TL, new Vector2(680f, -820f), new Vector2(1160f, 100f), TaxiKinUIBuilder.Red, 34);
            L(menu, "Attribution", new Vector2(0.5f, 0f), new Vector2(0f, 12f), new Vector2(1500f, 34f),
              "Données cartographiques © contributeurs OpenStreetMap", 22, TextAnchor.MiddleCenter, Soft, new Vector2(0.5f, 0f));

            UnityEventTools.AddPersistentListener(play.onClick, menuUi.OnPlay);
            UnityEventTools.AddPersistentListener(missionsBtn.onClick, menuUi.OnMissions);
            UnityEventTools.AddPersistentListener(garage.onClick, menuUi.OnGarage);
            UnityEventTools.AddPersistentListener(shop.onClick, menuUi.OnShop);
            UnityEventTools.AddPersistentListener(driver.onClick, menuUi.OnDriver);
            UnityEventTools.AddPersistentListener(settings.onClick, menuUi.OnSettings);
            UnityEventTools.AddPersistentListener(resume.onClick, menuUi.OnResume);

            // =================== LISTE DES MISSIONS ===================
            var list = TaxiKinUIBuilder.MissionPanel(root, "EcranMissions");
            list.GetComponent<Image>().color = new Color(0.04f, 0.05f, 0.07f, 0.96f);
            L(list, "Titre", TL, new Vector2(60f, -20f), new Vector2(900f, 80f), "Missions", 54, TextAnchor.MiddleLeft, Gold, TL);
            var back = S(list, "Retour", "Retour", TR, new Vector2(-30f, -20f), new Vector2(220f, 80f), TaxiKinUIBuilder.Red, 32);
            UnityEventTools.AddPersistentListener(back.onClick, menuUi.OnBackFromMissions);

            var grid = new GameObject("GrilleMissions", typeof(RectTransform), typeof(GridLayoutGroup));
            grid.transform.SetParent(list, false);
            TaxiKinUIBuilder.Place((RectTransform)grid.transform, TL, new Vector2(60f, -130f), new Vector2(1800f, 880f), TL);
            var g = grid.GetComponent<GridLayoutGroup>();
            g.cellSize = new Vector2(890f, 100f);
            g.spacing = new Vector2(20f, 12f);
            g.constraint = GridLayoutGroup.Constraint.FixedColumnCount;
            g.constraintCount = 2;
            var template = S(list, "ModeleMission", "Mission", TL, new Vector2(60f, -130f), new Vector2(890f, 100f), Neutral, 26);
            template.gameObject.SetActive(false);

            // =================== BOUTON « MENU » DU HUD ===================
            var hudMenu = TaxiKinUIBuilder.Button(root, "BoutonMenu", null, "Menu", TR, new Vector2(-30f, -20f), new Vector2(150f, 70f), TaxiKinUIBuilder.Btn);
            TaxiKinUIBuilder.On(hudMenu, menuUi.Open);

            // =================== BOUTON « MENU » DE L'ÉCRAN DE FIN DE MISSION ===================
            var missionUi = root.parent.GetComponent<MissionUIManager>();
            var endPanel = root.Find("EcranFinMission");
            if (missionUi != null && endPanel != null)
            {
                var toMenu = S(endPanel, "Menu", "Menu", new Vector2(0.5f, 0f), new Vector2(0f, 265f), new Vector2(300f, 80f), Neutral, 30);
                UnityEventTools.AddPersistentListener(toMenu.onClick, missionUi.OnMenu);
                TaxiKinSceneBuilder.Assign(missionUi, "menu", menuUi);
            }

            // =================== CÂBLAGE ===================
            TaxiKinSceneBuilder.Assign(menuUi, "missions", managers.GetComponent<MissionManager>());
            TaxiKinSceneBuilder.Assign(menuUi, "creatorUi", root.GetComponent<CharacterCreatorUI>());
            TaxiKinSceneBuilder.Assign(menuUi, "garageUi", managers.GetComponent<GarageUIManager>());
            TaxiKinSceneBuilder.Assign(menuUi, "shopUi", root.GetComponent<ShopUIManager>());
            TaxiKinSceneBuilder.Assign(menuUi, "settingsUi", root.GetComponent<SettingsUIManager>());
            TaxiKinSceneBuilder.Assign(menuUi, "avatarStage", avatarStage);
            TaxiKinSceneBuilder.Assign(menuUi, "menuPanel", menu.gameObject);
            TaxiKinSceneBuilder.Assign(menuUi, "missionsPanel", list.gameObject);
            TaxiKinSceneBuilder.Assign(menuUi, "avatarImage", avatarGo.GetComponent<RawImage>());
            TaxiKinSceneBuilder.Assign(menuUi, "nameText", nameText);
            TaxiKinSceneBuilder.Assign(menuUi, "levelText", levelText);
            TaxiKinSceneBuilder.Assign(menuUi, "coinsText", coinsText);
            TaxiKinSceneBuilder.Assign(menuUi, "goldText", goldText);
            TaxiKinSceneBuilder.Assign(menuUi, "resumeButton", resume.gameObject);
            TaxiKinSceneBuilder.Assign(menuUi, "missionsGrid", (RectTransform)grid.transform);
            TaxiKinSceneBuilder.Assign(menuUi, "missionTemplate", template);
            menu.gameObject.SetActive(false);
            list.gameObject.SetActive(false);

            // =================== ORDRE D'AFFICHAGE ===================
            // Le menu, la liste et le bouton « Menu » se placent juste SOUS le créateur de chauffeur : tous les écrans plein écran
            // construits après (créateur, garage, boutique, réglages) restent au-dessus.
            var creatorPanel = root.Find("EcranMonChauffeur");
            if (creatorPanel != null)
            {
                int index = creatorPanel.GetSiblingIndex();
                hudMenu.transform.SetSiblingIndex(index);
                menu.SetSiblingIndex(index + 1);
                list.SetSiblingIndex(index + 2);
            }

            // =================== OUTILS DE TEST ===================
            // Les anciens boutons de test des écrans sont remplacés par le menu ; ceux qui restent (véhicule, mode de contrôle)
            // sont rangés dans un conteneur désactivé dans les versions de production.
            foreach (var name in new[] { "TestMission", "TestChauffeur", "TestGarage", "TestBoutique", "TestReglages" })
            {
                var t = root.Find(name);
                if (t != null) Object.DestroyImmediate(t.gameObject);
            }
            var dev = new GameObject("OutilsDev", typeof(RectTransform), typeof(DevOnly));
            dev.transform.SetParent(root, false);
            var devRt = (RectTransform)dev.transform;
            devRt.anchorMin = Vector2.zero; devRt.anchorMax = Vector2.one; devRt.offsetMin = devRt.offsetMax = Vector2.zero;
            var toMove = new System.Collections.Generic.List<Transform>();
            foreach (Transform t in root) if (t.name.StartsWith("Test")) toMove.Add(t);
            foreach (var t in toMove) t.SetParent(devRt, false);
            dev.transform.SetSiblingIndex(creatorPanel != null ? creatorPanel.GetSiblingIndex() : root.childCount - 1);
        }
    }
}
