using UnityEditor;
using UnityEditor.Events;
using UnityEventTools = UnityEditor.Events.UnityEventTools;   // évite l'ambiguïté avec UnityEngine.Events
using UnityEngine;
using UnityEngine.Events;
using UnityEngine.EventSystems;
using UnityEngine.UI;
using TaxiKin.Missions;
using TaxiKin.Player;
using TaxiKin.UI;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Construit le Canvas tactile (référence 1920x1080, paysage) : volant et flèches à gauche,
    /// pédales/vitesses/caméra à droite, HUD en haut. Appelé par TaxiKinSceneBuilder.
    /// </summary>
    public static class TaxiKinUIBuilder
    {
        internal static Font font;
        internal static readonly Color Btn = new Color(0.1f, 0.1f, 0.12f, 0.65f);
        internal static readonly Color Gas = new Color(0.15f, 0.6f, 0.25f, 0.8f);
        internal static readonly Color Red = new Color(0.75f, 0.15f, 0.15f, 0.8f);

        public static void Build(GameObject managers, PlayerController player, PassengerManager passengers,
                                 MissionManager missions, CameraRig rig, Camera minimapCam, Transform destination)
        {
            font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf");

            // EventSystem (multi-touch géré par le module d'entrée)
            new GameObject("EventSystem", typeof(EventSystem), typeof(StandaloneInputModule));

            // Canvas adaptatif
            var canvasGo = new GameObject("Canvas", typeof(Canvas), typeof(CanvasScaler), typeof(GraphicRaycaster));
            var canvas = canvasGo.GetComponent<Canvas>();
            canvas.renderMode = RenderMode.ScreenSpaceOverlay;
            var scaler = canvasGo.GetComponent<CanvasScaler>();
            scaler.uiScaleMode = CanvasScaler.ScaleMode.ScaleWithScreenSize;
            scaler.referenceResolution = new Vector2(1920f, 1080f);
            scaler.screenMatchMode = CanvasScaler.ScreenMatchMode.MatchWidthOrHeight;
            scaler.matchWidthOrHeight = 0.5f;

            // Zone sûre (encoche, barre de navigation) : TOUT le contenu de l'interface est dedans
            var safeGo = new GameObject("ZoneSure", typeof(RectTransform), typeof(SafeAreaFitter));
            safeGo.transform.SetParent(canvasGo.transform, false);
            var safeRt = (RectTransform)safeGo.transform;
            safeRt.anchorMin = Vector2.zero; safeRt.anchorMax = Vector2.one; safeRt.offsetMin = safeRt.offsetMax = Vector2.zero;
            canvasGo.AddComponent<UiScaleController>();   // interface lisible sur petits écrans

            var root = safeGo.transform;

            // Composants logiques (sur le Canvas)
            var layout = canvasGo.AddComponent<ControlsLayout>();
            var input = canvasGo.AddComponent<InputManager>();
            var hud = canvasGo.AddComponent<HudManager>();
            var arrowComp = canvasGo.AddComponent<DirectionArrow>();
            TaxiKinSceneBuilder.Assign(layout, "player", player);

            // Actions des boutons (sur Managers)
            var actions = managers.AddComponent<ControlActions>();
            TaxiKinSceneBuilder.Assign(actions, "player", player);
            TaxiKinSceneBuilder.Assign(actions, "passengers", passengers);
            if (rig != null) TaxiKinSceneBuilder.Assign(actions, "cameraRig", rig);

            var BL = new Vector2(0f, 0f);   // coin bas-gauche
            var BR = new Vector2(1f, 0f);   // coin bas-droit
            var TL = new Vector2(0f, 1f);   // coin haut-gauche
            var TR = new Vector2(1f, 1f);   // coin haut-droit

            // ---------- GAUCHE : volant virtuel (centré), flèches, boutons ----------
            var wheelGo = new GameObject("Volant", typeof(RectTransform), typeof(Image), typeof(VirtualSteeringWheel));
            wheelGo.transform.SetParent(root, false);
            var wheelRt = (RectTransform)wheelGo.transform;
            Place(wheelRt, BL, new Vector2(290f, 290f), new Vector2(460f, 460f), new Vector2(0.5f, 0.5f));   // grand volant en bas à gauche (style Dr. Driving)
            var wheelImg = wheelGo.GetComponent<Image>();
            wheelImg.sprite = AssetDatabase.GetBuiltinExtraResource<Sprite>("UI/Skin/Knob.psd");
            wheelImg.color = new Color(0.15f, 0.15f, 0.18f, 0.7f);
            var marker = new GameObject("Repere", typeof(RectTransform), typeof(Image));   // repère « haut » du volant
            marker.transform.SetParent(wheelGo.transform, false);
            Place((RectTransform)marker.transform, new Vector2(0.5f, 1f), new Vector2(0f, -10f), new Vector2(30f, 60f), new Vector2(0.5f, 1f));
            marker.GetComponent<Image>().color = new Color(1f, 0.83f, 0f);
            marker.GetComponent<Image>().raycastTarget = false;
            layout.Register(ControlId.Wheel, wheelRt);

            var left = Button(root, "FlecheGauche", null, "<", BL, new Vector2(60f, 80f), new Vector2(200f, 200f), Btn);
            var right = Button(root, "FlecheDroite", null, ">", BL, new Vector2(290f, 80f), new Vector2(200f, 200f), Btn);
            layout.Register(ControlId.ArrowLeft, (RectTransform)left.transform);
            layout.Register(ControlId.ArrowRight, (RectTransform)right.transform);

            var hand = Button(root, "FreinAMain", "handbrake", null, BL, new Vector2(60f, 520f), new Vector2(130f, 110f), Red);
            var hornL = Button(root, "KlaxonG", "horn", null, BL, new Vector2(200f, 520f), new Vector2(130f, 110f), Btn);
            var lights = Button(root, "Phares", "headlights", null, BL, new Vector2(340f, 520f), new Vector2(130f, 110f), Btn);
            var wipers = Button(root, "EssuieGlaces", "wipers", null, BL, new Vector2(480f, 520f), new Vector2(130f, 110f), Btn);
            layout.Register(ControlId.HandBrake, (RectTransform)hand.transform);
            layout.Register(ControlId.Horn, (RectTransform)hornL.transform);
            layout.Register(ControlId.Headlights, (RectTransform)lights.transform);
            layout.Register(ControlId.Wipers, (RectTransform)wipers.transform);
            On(hornL, actions.Horn);
            On(lights, actions.ToggleHeadlights);
            On(wipers, actions.ToggleWipers);

            // ---------- DROITE : accélérateur, frein, vitesses, caméra, actions ----------
            // Pédales façon « Dr. Driving » : deux grandes pédales verticales côte à côte au coin du pouce droit (accélérateur tout à droite)
            var gas = Button(root, "Accelerateur", "gas", null, BR, new Vector2(-40f, 40f), new Vector2(230f, 380f), Gas);
            var brake = Button(root, "Frein", "brake", null, BR, new Vector2(-290f, 40f), new Vector2(230f, 300f), Red);
            layout.Register(ControlId.Gas, (RectTransform)gas.transform);
            layout.Register(ControlId.Brake, (RectTransform)brake.transform);

            var hornR = Button(root, "KlaxonD", "horn", null, BR, new Vector2(-570f, 40f), new Vector2(130f, 110f), Btn);
            On(hornR, actions.Horn);

            var cam = Button(root, "Camera", "camera", null, BR, new Vector2(-290f, 360f), new Vector2(150f, 110f), Btn);
            layout.Register(ControlId.Camera, (RectTransform)cam.transform);
            On(cam, actions.NextCamera);

            // Boîte de vitesses D / N / R (trois boutons dans un conteneur)
            var gearGo = new GameObject("Vitesses", typeof(RectTransform));
            gearGo.transform.SetParent(root, false);
            Place((RectTransform)gearGo.transform, BR, new Vector2(-40f, 420f), new Vector2(220f, 80f), BR);
            string[] g = { "D", "N", "R" };
            for (int i = 0; i < 3; i++)
            {
                var gb = Button(gearGo.transform, "Gear" + g[i], null, g[i], BL, new Vector2(i * 75f, 0f), new Vector2(70f, 80f), Btn);
                UnityEventTools.AddIntPersistentListener(gb.onPressed, actions.SetGear, i);
            }
            layout.Register(ControlId.Gear, (RectTransform)gearGo.transform);

            // Passagers (Wewa : un bouton ; taxi : client ; bus : passagers, portes, arrêt)
            var pass = Button(root, "Passager", "passenger", null, BR, new Vector2(-40f, 520f), new Vector2(200f, 90f), Btn);
            layout.Register(ControlId.PassengerAction, (RectTransform)pass.transform);
            On(pass, actions.TogglePassenger);     // prend le client, puis le dépose (un seul bouton pour le Wewa)
            var passLabel = pass.GetComponentInChildren<Text>();
            Object.DestroyImmediate(passLabel.GetComponent<LocalizedText>());
            var passDynamic = pass.gameObject.AddComponent<PassengerButtonLabel>();
            TaxiKinSceneBuilder.Assign(passDynamic, "label", passLabel);
            TaxiKinSceneBuilder.Assign(passDynamic, "passengers", passengers);

            var pickup = Button(root, "PrendreClient", "pickup", null, BR, new Vector2(-40f, 520f), new Vector2(200f, 90f), Btn);
            var dropoff = Button(root, "DeposerClient", "dropoff", null, BR, new Vector2(-40f, 620f), new Vector2(200f, 90f), Btn);
            layout.Register(ControlId.Pickup, (RectTransform)pickup.transform);
            layout.Register(ControlId.Dropoff, (RectTransform)dropoff.transform);
            On(pickup, actions.PickupPassengers);
            On(dropoff, actions.DropPassengers);

            var pickupAll = Button(root, "PrendrePassagers", "pickup_all", null, BR, new Vector2(-40f, 520f), new Vector2(200f, 90f), Btn);
            var dropAll = Button(root, "DeposerPassagers", "drop_all", null, BR, new Vector2(-40f, 620f), new Vector2(200f, 90f), Btn);
            layout.Register(ControlId.PickupAll, (RectTransform)pickupAll.transform);
            layout.Register(ControlId.DropAll, (RectTransform)dropAll.transform);
            On(pickupAll, actions.PickupPassengers);
            On(dropAll, actions.DropPassengers);

            var doors = Button(root, "Portes", "doors", null, BR, new Vector2(-260f, 520f), new Vector2(200f, 90f), Btn);
            var stop = Button(root, "Arret", "stop", null, BR, new Vector2(-260f, 620f), new Vector2(200f, 90f), Btn);
            layout.Register(ControlId.Doors, (RectTransform)doors.transform);
            layout.Register(ControlId.Stop, (RectTransform)stop.transform);
            On(doors, actions.ToggleDoors);
            On(stop, actions.RequestStop);

            // ---------- HAUT : minimap + HUD ----------
            var rt = new RenderTexture(256, 256, 16) { name = "MinimapRT" };
            const string rtPath = "Assets/UI/MinimapRT.renderTexture";
            var existing = AssetDatabase.LoadAssetAtPath<RenderTexture>(rtPath);
            if (existing == null) { AssetDatabase.CreateAsset(rt, rtPath); existing = rt; }
            minimapCam.targetTexture = existing;

            var mapGo = new GameObject("Minimap", typeof(RectTransform), typeof(RawImage));
            mapGo.transform.SetParent(root, false);
            Place((RectTransform)mapGo.transform, TL, new Vector2(20f, -20f), new Vector2(200f, 200f), TL);
            var raw = mapGo.GetComponent<RawImage>();
            raw.texture = existing;
            raw.raycastTarget = false;

            string[] keys = { "hud_money", "hud_passengers", "hud_fuel", "hud_damage", "hud_time", "hud_distance" };
            var values = new Text[6];
            for (int i = 0; i < 6; i++)
            {
                float x = 240f + i * 240f;
                Caption(root, "Legende" + i, keys[i], new Vector2(x, -15f));
                values[i] = Value(root, "Valeur" + i, new Vector2(x, -50f));
            }
            var speed = Value(root, "Vitesse", new Vector2(240f, -120f));
            var raceText = Value(root, "Classement", new Vector2(720f, -120f));    // « 2e / 5 » en course
            raceText.color = new Color(1f, 0.83f, 0f);
            raceText.text = "";

            // Flèche de direction
            var arrowGo = new GameObject("FlecheDirection", typeof(RectTransform), typeof(Image));
            arrowGo.transform.SetParent(root, false);
            Place((RectTransform)arrowGo.transform, TR, new Vector2(-100f, -260f), new Vector2(50f, 110f), new Vector2(0.5f, 0.5f));
            arrowGo.GetComponent<Image>().color = new Color(1f, 0.6f, 0.1f, 0.9f);
            arrowGo.GetComponent<Image>().raycastTarget = false;
            var tip = new GameObject("Pointe", typeof(RectTransform), typeof(Image));
            tip.transform.SetParent(arrowGo.transform, false);
            Place((RectTransform)tip.transform, new Vector2(0.5f, 1f), Vector2.zero, new Vector2(80f, 30f), new Vector2(0.5f, 0f));
            tip.GetComponent<Image>().color = new Color(1f, 0.6f, 0.1f, 0.9f);
            tip.GetComponent<Image>().raycastTarget = false;

            // Boutons de TEST (haut-droite) : changer de véhicule et de mode de contrôle
            string[] names = { "Wewa", "Taxi", "207" };
            for (int i = 0; i < 3; i++)
            {
                var b = Button(root, "Test" + names[i], null, names[i], TR, new Vector2(-20f - (2 - i) * 130f - 130f, -150f), new Vector2(120f, 60f), Btn);
                UnityEventTools.AddIntPersistentListener(b.onPressed, player.SelectVehicle, i);
            }
            var modeBtn = Button(root, "TestMode", null, "Mode", TR, new Vector2(-20f, -150f), new Vector2(120f, 60f), Btn);
            On(modeBtn, actions.CycleControlMode);

            // ---------- Écrans de mission (début / fin) ----------
            var missionUi = canvasGo.AddComponent<MissionUIManager>();
            var brief = MissionPanel(root, "EcranDebutMission");
            var bTitle = PanelText(brief, "Titre", new Vector2(0f, -70f), new Vector2(1500f, 90f), 56, TextAnchor.MiddleCenter, new Color(1f, 0.83f, 0f));
            var bDesc = PanelText(brief, "Description", new Vector2(0f, -190f), new Vector2(1400f, 150f), 34, TextAnchor.UpperCenter, Color.white);
            var bVeh = PanelText(brief, "Vehicule", new Vector2(0f, -360f), new Vector2(1400f, 60f), 34, TextAnchor.MiddleCenter, Color.white);
            var bRoute = PanelText(brief, "Trajet", new Vector2(0f, -430f), new Vector2(1400f, 60f), 34, TextAnchor.MiddleCenter, Color.white);
            var bTime = PanelText(brief, "Chrono", new Vector2(0f, -500f), new Vector2(1400f, 60f), 34, TextAnchor.MiddleCenter, Color.white);
            var bRew = PanelText(brief, "Recompense", new Vector2(0f, -570f), new Vector2(1500f, 60f), 34, TextAnchor.MiddleCenter, new Color(0.6f, 1f, 0.6f));
            var startBtn = PanelButton(brief, "Commencer", "mission_start", new Vector2(0f, 120f), new Vector2(420f, 110f), Gas);

            var end = MissionPanel(root, "EcranFinMission");
            var eTitle = PanelText(end, "Titre", new Vector2(0f, -70f), new Vector2(1500f, 100f), 64, TextAnchor.MiddleCenter, new Color(1f, 0.83f, 0f));
            var eObj = PanelText(end, "Objectifs", new Vector2(0f, -200f), new Vector2(1500f, 420f), 32, TextAnchor.UpperCenter, Color.white);
            var eRew = PanelText(end, "Recompense", new Vector2(0f, -640f), new Vector2(1500f, 70f), 38, TextAnchor.MiddleCenter, new Color(0.6f, 1f, 0.6f));
            var retryBtn = PanelButton(end, "Rejouer", "mission_retry", new Vector2(-250f, 120f), new Vector2(420f, 110f), Btn);
            var nextBtn = PanelButton(end, "Suivante", "mission_next", new Vector2(250f, 120f), new Vector2(420f, 110f), Gas);

            TaxiKinSceneBuilder.Assign(missionUi, "missions", missions);
            TaxiKinSceneBuilder.Assign(missionUi, "briefingPanel", brief.gameObject);
            TaxiKinSceneBuilder.Assign(missionUi, "briefTitle", bTitle);
            TaxiKinSceneBuilder.Assign(missionUi, "briefDescription", bDesc);
            TaxiKinSceneBuilder.Assign(missionUi, "briefVehicle", bVeh);
            TaxiKinSceneBuilder.Assign(missionUi, "briefRoute", bRoute);
            TaxiKinSceneBuilder.Assign(missionUi, "briefTime", bTime);
            TaxiKinSceneBuilder.Assign(missionUi, "briefReward", bRew);
            TaxiKinSceneBuilder.Assign(missionUi, "endPanel", end.gameObject);
            TaxiKinSceneBuilder.Assign(missionUi, "endTitle", eTitle);
            TaxiKinSceneBuilder.Assign(missionUi, "endObjectives", eObj);
            TaxiKinSceneBuilder.Assign(missionUi, "endReward", eRew);
            TaxiKinSceneBuilder.Assign(missionUi, "nextButton", nextBtn.gameObject);
            On(startBtn, missionUi.OnStart);
            On(retryBtn, missionUi.OnRetry);
            On(nextBtn, missionUi.OnNext);
            brief.gameObject.SetActive(false);
            end.gameObject.SetActive(false);

            // Bouton de TEST : ouvre la prochaine mission (sera remplacé par le menu principal)
            var missionBtn = Button(root, "TestMission", "mission_menu", null, TR, new Vector2(-170f, -220f), new Vector2(120f, 60f), Btn);
            On(missionBtn, missionUi.OnOpenMenu);

            // MissionManager : références de la scène
            TaxiKinSceneBuilder.Assign(missions, "player", player);
            TaxiKinSceneBuilder.Assign(missions, "passengers", passengers);
            TaxiKinSceneBuilder.Assign(missions, "weather", managers.GetComponent<TaxiKin.Weather.WeatherManager>());
            TaxiKinSceneBuilder.Assign(missions, "traffic", managers.GetComponent<TaxiKin.Traffic.TrafficManager>());
            if (rig != null) TaxiKinSceneBuilder.Assign(missions, "cameraRig", rig);
            TaxiKinSceneBuilder.Assign(missions, "hud", hud);
            TaxiKinSceneBuilder.Assign(managers.GetComponent<RaceManager>(), "hud", hud);
            TaxiKinSceneBuilder.Assign(missions, "directionArrow", arrowComp);
            TaxiKinSceneBuilder.Assign(missions, "fallbackDestination", destination);
            TaxiKinSceneBuilder.Assign(actions, "missions", missions);

            // ---------- Câblage ----------
            TaxiKinSceneBuilder.Assign(input, "player", player);
            TaxiKinSceneBuilder.Assign(input, "wheel", wheelGo.GetComponent<VirtualSteeringWheel>());
            TaxiKinSceneBuilder.Assign(input, "arrowLeft", left);
            TaxiKinSceneBuilder.Assign(input, "arrowRight", right);
            TaxiKinSceneBuilder.Assign(input, "gas", gas);
            TaxiKinSceneBuilder.Assign(input, "brake", brake);
            TaxiKinSceneBuilder.Assign(input, "handBrake", hand);

            TaxiKinSceneBuilder.Assign(hud, "player", player);
            TaxiKinSceneBuilder.Assign(hud, "passengers", passengers);
            TaxiKinSceneBuilder.Assign(hud, "missions", missions);
            TaxiKinSceneBuilder.Assign(hud, "money", values[0]);
            TaxiKinSceneBuilder.Assign(hud, "passengersText", values[1]);
            TaxiKinSceneBuilder.Assign(hud, "fuel", values[2]);
            TaxiKinSceneBuilder.Assign(hud, "damage", values[3]);
            TaxiKinSceneBuilder.Assign(hud, "timer", values[4]);
            TaxiKinSceneBuilder.Assign(hud, "distance", values[5]);
            TaxiKinSceneBuilder.Assign(hud, "speed", speed);
            TaxiKinSceneBuilder.Assign(hud, "raceText", raceText);
            TaxiKinSceneBuilder.Assign(hud, "destination", destination);

            TaxiKinSceneBuilder.Assign(arrowComp, "player", player);
            TaxiKinSceneBuilder.Assign(arrowComp, "arrow", arrowGo.GetComponent<RectTransform>());
            TaxiKinSceneBuilder.Assign(arrowComp, "destination", destination);

            EditorUtility.SetDirty(layout);
            layout.Apply();
        }

        // ---------- Aides de construction ----------

        internal static void On(HoldButton b, UnityAction action) => UnityEventTools.AddPersistentListener(b.onPressed, action);

        internal static RectTransform Place(RectTransform rt, Vector2 anchor, Vector2 pos, Vector2 size, Vector2 pivot)
        {
            rt.anchorMin = rt.anchorMax = anchor;
            rt.pivot = pivot;
            rt.anchoredPosition = pos;
            rt.sizeDelta = size;
            return rt;
        }

        /// <summary>Bouton tactile avec libellé localisé (locKey) ou texte fixe (raw).</summary>
        internal static HoldButton Button(Transform parent, string name, string locKey, string raw,
                                         Vector2 anchor, Vector2 pos, Vector2 size, Color color)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Image), typeof(HoldButton));
            go.transform.SetParent(parent, false);
            Place((RectTransform)go.transform, anchor, pos, size, anchor);
            go.GetComponent<Image>().color = color;

            var label = new GameObject("Libelle", typeof(RectTransform), typeof(Text));
            label.transform.SetParent(go.transform, false);
            var lrt = (RectTransform)label.transform;
            lrt.anchorMin = Vector2.zero; lrt.anchorMax = Vector2.one; lrt.offsetMin = lrt.offsetMax = Vector2.zero;
            var t = label.GetComponent<Text>();
            t.font = font;
            t.alignment = TextAnchor.MiddleCenter;
            t.color = Color.white;
            t.raycastTarget = false;
            t.resizeTextForBestFit = true;
            t.resizeTextMinSize = 14;
            t.resizeTextMaxSize = 36;
            if (!string.IsNullOrEmpty(locKey)) label.AddComponent<LocalizedText>().Key = locKey;
            else t.text = raw;
            return go.GetComponent<HoldButton>();
        }

        /// <summary>Panneau plein écran semi-transparent (bloque les touches derrière lui).</summary>
        internal static RectTransform MissionPanel(Transform parent, string name)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Image));
            go.transform.SetParent(parent, false);
            var rt = (RectTransform)go.transform;
            rt.anchorMin = Vector2.zero; rt.anchorMax = Vector2.one; rt.offsetMin = rt.offsetMax = Vector2.zero;
            go.GetComponent<Image>().color = new Color(0f, 0f, 0f, 0.82f);
            return rt;
        }

        /// <summary>Texte ancré en haut au centre d'un panneau (pos.y négatif = vers le bas).</summary>
        private static Text PanelText(Transform parent, string name, Vector2 pos, Vector2 size, int fontSize, TextAnchor align, Color color)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Text));
            go.transform.SetParent(parent, false);
            Place((RectTransform)go.transform, new Vector2(0.5f, 1f), pos, size, new Vector2(0.5f, 1f));
            var t = go.GetComponent<Text>();
            t.font = font; t.fontSize = fontSize; t.alignment = align; t.color = color; t.raycastTarget = false;
            t.horizontalOverflow = HorizontalWrapMode.Wrap;
            return t;
        }

        /// <summary>Bouton ancré en bas au centre d'un panneau.</summary>
        private static HoldButton PanelButton(Transform parent, string name, string locKey, Vector2 pos, Vector2 size, Color color) =>
            Button(parent, name, locKey, null, new Vector2(0.5f, 0f), pos, size, color);

        private static Text Value(Transform parent, string name, Vector2 pos)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Text));
            go.transform.SetParent(parent, false);
            Place((RectTransform)go.transform, new Vector2(0f, 1f), pos, new Vector2(220f, 50f), new Vector2(0f, 1f));
            var t = go.GetComponent<Text>();
            t.font = font; t.fontSize = 38; t.fontStyle = FontStyle.Bold; t.color = Color.white; t.raycastTarget = false;
            t.text = "--";
            return t;
        }

        private static void Caption(Transform parent, string name, string key, Vector2 pos)
        {
            var go = new GameObject(name, typeof(RectTransform), typeof(Text), typeof(LocalizedText));
            go.transform.SetParent(parent, false);
            Place((RectTransform)go.transform, new Vector2(0f, 1f), pos, new Vector2(220f, 30f), new Vector2(0f, 1f));
            var t = go.GetComponent<Text>();
            t.font = font; t.fontSize = 22; t.color = new Color(1f, 0.83f, 0f); t.raycastTarget = false;
            go.GetComponent<LocalizedText>().Key = key;
        }
    }
}
