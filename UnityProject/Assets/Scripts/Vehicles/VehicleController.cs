using System;
using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>Les trois véhicules jouables.</summary>
    public enum VehicleType { Wewa, Taxi, Bus207 }

    /// <summary>Boîte de vitesses : D = marche avant, N = neutre, R = marche arrière.</summary>
    public enum GearPosition { D, N, R }

    /// <summary>Caractéristiques d'un véhicule (modifiables dans l'Inspector).</summary>
    [Serializable]
    public class VehicleStats
    {
        public float maxSpeedKmh = 60f;
        public float acceleration = 8f;   // m/s²
        public float brakeForce = 15f;    // m/s²
        public float turnRate = 90f;      // degrés/seconde à pleine vitesse
        public int passengerCapacity = 1;
        public float fuelCapacity = 20f;
        public float fuelPerKm = 0.08f;
        public float fragility = 1f;      // multiplicateur de dégâts

        public VehicleStats Clone() => (VehicleStats)MemberwiseClone();
    }

    /// <summary>
    /// Base commune des véhicules : conduite arcade pilotée par la vitesse (pas par la friction),
    /// ce qui rend le comportement identique sur tous les téléphones.
    /// Les entrées viennent d'InputManager via PlayerController.
    /// </summary>
    [RequireComponent(typeof(Rigidbody))]
    public abstract class VehicleController : MonoBehaviour
    {
        [SerializeField] protected VehicleStats stats;
        [SerializeField, HideInInspector] private bool statsInitialized;

        public abstract VehicleType Kind { get; }
        protected abstract VehicleStats CreateDefaultStats();

        // --- Entrées normalisées ---
        public float Steer { get; private set; }      // -1 (gauche) .. 1 (droite)
        public float Throttle { get; private set; }   // 0 .. 1
        public float Brake { get; private set; }      // 0 .. 1
        public bool HandBrake { get; private set; }
        public GearPosition Gear { get; set; } = GearPosition.D;
        public bool Reverse => Gear == GearPosition.R;

        // --- Équipements ---
        public bool HeadlightsOn { get; private set; }
        public bool WipersOn { get; private set; }
        public event Action OnHorn;
        /// <summary>Choc franchissant 2 m/s (compté comme collision par les missions).</summary>
        public event Action<float> OnImpact;                   // Le son sera branché à l'Audio
        public void Honk() => OnHorn?.Invoke();
        public void ToggleHeadlights() => HeadlightsOn = !HeadlightsOn;
        public void ToggleWipers() => WipersOn = !WipersOn;

        // --- État ---
        public float Fuel { get; private set; }
        public float Damage { get; private set; }     // 0..100
        public float SpeedKmh { get; private set; }
        public VehicleStats Stats => stats;

        private Rigidbody rb;
        private VehicleStats baseStats;   // Caractéristiques sans améliorations
        private float ignoreImpactsUntil;   // évite les « collisions » fantômes juste après une téléportation

        // Compatibilité Unity 2022 (velocity) / Unity 6 (linearVelocity)
        private Vector3 Velocity
        {
            get
            {
#if UNITY_6000_0_OR_NEWER
                return rb.linearVelocity;
#else
                return rb.velocity;
#endif
            }
            set
            {
#if UNITY_6000_0_OR_NEWER
                rb.linearVelocity = value;
#else
                rb.velocity = value;
#endif
            }
        }

        protected virtual void Awake()
        {
            rb = GetComponent<Rigidbody>();
            if (!statsInitialized) { stats = CreateDefaultStats(); statsInitialized = true; }
            rb.centerOfMass = new Vector3(0f, -0.4f, 0f);
            rb.constraints = RigidbodyConstraints.FreezeRotationX | RigidbodyConstraints.FreezeRotationZ;
            rb.interpolation = RigidbodyInterpolation.Interpolate;   // mouvement fluide même si la physique tourne à 30 Hz
            baseStats = stats.Clone();
            Fuel = stats.fuelCapacity;
            ApplyFrictionlessMaterial();
        }

        /// <summary>Sans friction sur le véhicule, c'est notre code qui décide de l'adhérence.</summary>
        private void ApplyFrictionlessMaterial()
        {
#if UNITY_6000_0_OR_NEWER
            var m = new PhysicsMaterial("VehicleNoFriction")
            { dynamicFriction = 0f, staticFriction = 0f, frictionCombine = PhysicsMaterialCombine.Minimum };
#else
            var m = new PhysicMaterial("VehicleNoFriction")
            { dynamicFriction = 0f, staticFriction = 0f, frictionCombine = PhysicMaterialCombine.Minimum };
#endif
            foreach (var c in GetComponentsInChildren<Collider>()) c.sharedMaterial = m;
        }

        /// <summary>Reçoit les entrées du joueur (appelé par PlayerController).</summary>
        public void SetInputs(float steer, float throttle, float brake, bool handBrake)
        {
            Steer = Mathf.Clamp(steer, -1f, 1f);
            Throttle = Mathf.Clamp01(throttle);
            Brake = Mathf.Clamp01(brake);
            HandBrake = handBrake;
        }

        private void FixedUpdate()
        {
            float dt = Time.fixedDeltaTime;
            Vector3 vel = Velocity;
            Vector3 fwd = transform.forward;
            Vector3 right = transform.right;
            float vertical = vel.y;

            float forward = Vector3.Dot(vel, fwd);
            float lateral = Vector3.Dot(vel, right);
            float maxMs = stats.maxSpeedKmh / 3.6f;
            bool driving = Gear != GearPosition.N && Throttle > 0f && Fuel > 0f;

            if (driving)
            {
                float dir = Reverse ? -1f : 1f;
                forward += dir * Throttle * stats.acceleration * dt;
            }
            else
            {
                forward = Mathf.MoveTowards(forward, 0f, 1.5f * dt);   // Roue libre
            }

            float brakeAmount = HandBrake ? 1f : Brake;
            if (brakeAmount > 0f)
                forward = Mathf.MoveTowards(forward, 0f, brakeAmount * stats.brakeForce * dt);

            forward = Mathf.Clamp(forward, -maxMs * 0.4f, maxMs);      // Marche arrière plus lente

            // Adhérence latérale : le frein à main fait déraper
            float grip = HandBrake ? 6f : 30f;
            lateral = Mathf.MoveTowards(lateral, 0f, grip * dt);

            Vector3 newVel = fwd * forward + right * lateral;
            newVel.y = vertical;
            Velocity = newVel;
            SpeedKmh = Mathf.Abs(forward) * 3.6f;

            // Direction : proportionnelle à la vitesse, inversée en marche arrière
            float speedFactor = Mathf.Clamp01(Mathf.Abs(forward) / 4f);
            float yaw = Steer * stats.turnRate * speedFactor * Mathf.Sign(forward == 0f ? 1f : forward) * dt;
            rb.MoveRotation(rb.rotation * Quaternion.Euler(0f, yaw, 0f));

            if (driving) Fuel = Mathf.Max(0f, Fuel - (Mathf.Abs(forward) * dt / 1000f) * stats.fuelPerKm);
        }

        private void OnCollisionEnter(Collision collision)
        {
            if (Time.time < ignoreImpactsUntil) return;
            float impact = collision.relativeVelocity.magnitude;
            if (impact >= 2f) OnImpact?.Invoke(impact);
            if (impact < 3f) return;
            Damage = Mathf.Clamp(Damage + impact * stats.fragility, 0f, 100f);
        }

        /// <summary>Place le véhicule (départ de mission, changement de véhicule) sans vitesse résiduelle.</summary>
        public void ResetMotion(Vector3 position, Quaternion rotation)
        {
            if (rb == null) rb = GetComponent<Rigidbody>();
            transform.SetPositionAndRotation(position, rotation);
            rb.position = position;
            rb.rotation = rotation;
            Velocity = Vector3.zero;
            rb.angularVelocity = Vector3.zero;
            ignoreImpactsUntil = Time.time + 1f;
        }

        /// <summary>Applique les améliorations (niveaux 0 à 3) aux caractéristiques de base.</summary>
        public void SetUpgrades(int engine, int brakes, int tires, int armor)
        {
            if (baseStats == null) return;   // Awake pas encore passé : sera réappliqué par VehicleCustomizer
            engine = Mathf.Clamp(engine, 0, 3); brakes = Mathf.Clamp(brakes, 0, 3);
            tires = Mathf.Clamp(tires, 0, 3); armor = Mathf.Clamp(armor, 0, 3);
            stats = baseStats.Clone();
            stats.acceleration *= 1f + 0.08f * engine;
            stats.maxSpeedKmh *= 1f + 0.05f * engine;
            stats.brakeForce *= 1f + 0.10f * brakes;
            stats.turnRate *= 1f + 0.05f * tires;
            stats.fragility *= 1f - 0.12f * armor;
        }

        /// <summary>Restaure l'état sauvegardé (carburant, dommages).</summary>
        public void SetCondition(float fuel, float damage)
        {
            Fuel = Mathf.Clamp(fuel, 0f, stats.fuelCapacity);
            Damage = Mathf.Clamp(damage, 0f, 100f);
        }

        public void Refuel(float amount) => Fuel = Mathf.Min(stats.fuelCapacity, Fuel + amount);
        public void Repair(float amount) => Damage = Mathf.Max(0f, Damage - amount);
    }
}
