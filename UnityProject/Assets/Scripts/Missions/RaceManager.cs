using System.Collections;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Map;
using TaxiKin.Player;
using TaxiKin.Traffic;
using TaxiKin.UI;
using TaxiKin.Vehicles;

namespace TaxiKin.Missions
{
    /// <summary>
    /// Courses contre des chauffeurs virtuels (missions 9 et 10).
    ///  - Départ en ligne avec compte à rebours 3-2-1-GO (le joueur et les coureurs sont immobilisés).
    ///  - Chaque coureur suit le plus court chemin routier vers l'arrivée à une part « skill » de sa vitesse max.
    ///  - Un coureur loin du joueur n'est PAS simulé physiquement : sa progression est calculée (peu coûteux) ; il devient un vrai
    ///    véhicule seulement à moins de 160 m du joueur, et redevient virtuel au-delà de 280 m. Pas besoin de charger toutes les
    ///    zones du parcours, et aucun véhicule ne conduit sur un sol absent.
    ///  - Classement en direct (affiché dans le HUD) ; la place du joueur est fixée à son arrivée.
    /// </summary>
    public class RaceManager : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private WorldManager world;
        [SerializeField] private HudManager hud;

        private const float VirtualSpeedFactor = 0.89f;     // vitesse moyenne réelle d'un coureur / (vmax x skill) : mesuré par sim_ai.py sur les vraies routes (0,88 à 0,91)
        private const float MaterializeDistance = 160f, DematerializeDistance = 280f, FinishMargin = 12f;

        private static readonly Color[] RacerColors =
        {
            new Color(0.85f, 0.15f, 0.15f), new Color(0.15f, 0.35f, 0.85f), new Color(0.15f, 0.65f, 0.3f), new Color(0.6f, 0.2f, 0.75f), new Color(0.95f, 0.5f, 0.1f),
        };
        private static readonly int BaseColorId = Shader.PropertyToID("_BaseColor");
        private static readonly int ColorId = Shader.PropertyToID("_Color");

        private class Racer
        {
            public float skill, vmax, progress;
            public bool finished, physical;
            public GameObject go; public VehicleAI ai; public VehicleController vehicle;
        }

        private readonly List<Racer> racers = new List<Racer>();
        private readonly RoutePath route = new RoutePath();
        private readonly List<Vector3> pts = new List<Vector3>();
        private readonly List<sbyte> kinds = new List<sbyte>();
        private int finishedCount, playerSegment, lastRank = -1;
        private bool started, playerFinished;
        private float nextTick;
        private Coroutine countdown;

        public bool IsRacing { get; private set; }
        public int Total => racers.Count + 1;

        /// <summary>Prépare la course et lance le compte à rebours. Retourne faux si le réseau ou les modèles manquent.</summary>
        public bool StartRace(Vector3 startPos, Vector3 destPos, VehicleType playerKind, int opponents, float baseSkill)
        {
            Stop();
            if (world == null || !world.GraphReady || player == null || player.Vehicle == null || opponents <= 0) return false;

            var graph = world.Graph;
            int a = graph.Nearest(startPos, true, 300f), b = graph.Nearest(destPos, true, 300f);
            if (!graph.FindPath(a, b, pts, kinds)) { Debug.LogWarning("[Course] Aucun chemin entre le départ et l'arrivée."); return false; }
            route.Set(pts, kinds);

            var prefab = Resources.Load<GameObject>(playerKind == VehicleType.Wewa ? "AI/RaceWewa" : "AI/RaceTaxi");
            if (prefab == null) { Debug.LogWarning("[Course] Modèle de coureur introuvable (Resources/AI)."); return false; }

            Vector3 dir = route.Points[1] - route.Points[0]; dir.y = 0f; dir.Normalize();
            Vector3 right = new Vector3(dir.z, 0f, -dir.x);
            Quaternion rot = Quaternion.LookRotation(dir);

            for (int i = 0; i < opponents; i++)
            {
                var r = new Racer
                {
                    skill = Mathf.Clamp(baseSkill + Mathf.Lerp(-0.08f, 0.08f, opponents > 1 ? i / (float)(opponents - 1) : 0.5f), 0.5f, 0.98f),
                    physical = true,
                };
                r.go = Instantiate(prefab, transform);
                r.ai = r.go.GetComponent<VehicleAI>();
                r.vehicle = r.go.GetComponent<VehicleController>();
                r.vmax = r.vehicle.Stats.maxSpeedKmh / 3.6f;
                Tint(r.go, RacerColors[i % RacerColors.Length]);
                // Grille de départ : deux colonnes, rangées espacées de 7 m, derrière la ligne
                Vector3 place = route.Points[0] - dir * (7f * (i / 2)) + right * (i % 2 == 0 ? -2.6f : 2.6f);
                r.go.SetActive(true);
                r.vehicle.ResetMotion(place + Vector3.up * r.ai.GroundOffset, rot);
                r.vehicle.Frozen = true;
                r.ai.BeginRoute(pts, kinds, r.skill, 0f);
                r.ai.Hold = true;
                racers.Add(r);
            }

            finishedCount = 0; playerSegment = 0; lastRank = -1; playerFinished = false; started = false;
            IsRacing = true;
            player.Vehicle.Frozen = true;
            countdown = StartCoroutine(CountdownRoutine());
            return true;
        }

        private static void Tint(GameObject go, Color c)
        {
            var rend = go.GetComponentInChildren<Renderer>();
            if (rend == null) return;
            var block = new MaterialPropertyBlock();
            rend.GetPropertyBlock(block);
            block.SetColor(BaseColorId, c); block.SetColor(ColorId, c);
            rend.SetPropertyBlock(block);
        }

        private IEnumerator CountdownRoutine()
        {
            for (int n = 3; n >= 1; n--)
            {
                ToastManager.Show(n.ToString());
                yield return new WaitForSecondsRealtime(1f);
            }
            ToastManager.Show("PARTEZ !");
            if (player != null && player.Vehicle != null) player.Vehicle.Frozen = false;
            foreach (var r in racers) { r.vehicle.Frozen = false; r.ai.Hold = false; }
            started = true;
            countdown = null;
        }

        /// <summary>Arrête la course : retire les coureurs et libère le joueur.</summary>
        public void Stop()
        {
            if (countdown != null) { StopCoroutine(countdown); countdown = null; }
            foreach (var r in racers) if (r.go != null) Destroy(r.go);
            racers.Clear();
            if (player != null && player.Vehicle != null) player.Vehicle.Frozen = false;
            IsRacing = false; started = false;
            hud?.SetRace(0, 0);
        }

        /// <summary>Le joueur est arrivé : retourne sa place (1 = premier).</summary>
        public int PlayerArrived()
        {
            if (playerFinished) return finishedCount + 1;
            playerFinished = true;
            return finishedCount + 1;
        }

        // ---------- Mise à jour (10 fois par seconde) ----------

        private void Update()
        {
            if (!IsRacing || !started || Time.unscaledTime < nextTick) return;
            float dt = Time.unscaledTime - (nextTick - 0.1f);
            nextTick = Time.unscaledTime + 0.1f;
            if (dt <= 0f || dt > 1f) dt = 0.1f;
            if (Time.timeScale <= 0f) return;                      // pause : la course est figée

            var pv = player != null ? player.Vehicle : null;
            if (pv == null) return;
            float playerS = route.Project(pv.transform.position, ref playerSegment, out _);

            foreach (var r in racers)
            {
                if (r.finished) continue;

                if (r.physical)
                {
                    r.progress = r.ai.Progress;
                    if (r.ai.IsStuck) Unstick(r);
                    if (r.progress < playerS - DematerializeDistance || r.progress > playerS + DematerializeDistance) Dematerialize(r);
                }
                else
                {
                    r.progress += dt * r.vmax * r.skill * VirtualSpeedFactor;
                    if (Mathf.Abs(r.progress - playerS) < MaterializeDistance) TryMaterialize(r);
                }

                if (r.progress >= route.Length - FinishMargin)
                {
                    r.finished = true;
                    finishedCount++;
                    if (r.go != null) r.go.SetActive(false);
                }
            }
            UpdateRank(playerS);
        }

        private void UpdateRank(float playerS)
        {
            int rank = 1;
            foreach (var r in racers) if (r.finished || r.progress > playerS) rank++;
            if (playerFinished) rank = finishedCount + 1;
            if (rank != lastRank) { lastRank = rank; hud?.SetRace(rank, Total); }
        }

        private void TryMaterialize(Racer r)
        {
            Vector3 pos = route.PointAt(r.progress, out int seg);
            if (!world.IsGroundReadyAt(pos)) return;                // le sol n'existe pas encore sous lui : il reste virtuel
            Vector3 d = route.Points[Mathf.Min(seg + 1, route.Count - 1)] - route.Points[seg]; d.y = 0f;
            r.go.SetActive(true);
            r.vehicle.ResetMotion(pos + Vector3.up * r.ai.GroundOffset, d.sqrMagnitude > 0.01f ? Quaternion.LookRotation(d) : Quaternion.identity);
            r.ai.BeginRoute(pts, kinds, r.skill, r.progress);
            r.physical = true;
        }

        private void Dematerialize(Racer r)
        {
            r.ai.Stop();
            r.go.SetActive(false);
            r.physical = false;
        }

        // Coureur coincé : on le replace sur le trajet, un peu plus loin
        private void Unstick(Racer r)
        {
            Vector3 pos = route.PointAt(r.progress + 8f, out int seg);
            Vector3 d = route.Points[Mathf.Min(seg + 1, route.Count - 1)] - route.Points[seg]; d.y = 0f;
            r.vehicle.ResetMotion(pos + Vector3.up * r.ai.GroundOffset, d.sqrMagnitude > 0.01f ? Quaternion.LookRotation(d) : Quaternion.identity);
            r.ai.BeginRoute(pts, kinds, r.skill, r.progress + 8f);
        }
    }
}
