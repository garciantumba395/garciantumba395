using UnityEngine;
using TaxiKin.UI;

namespace TaxiKin.Player
{
    /// <summary>
    /// Fusionne les 3 modes de contrôle (volant, flèches, inclinaison) + gaz/frein/frein à main
    /// en entrées normalisées envoyées au véhicule. Repli clavier dans l'éditeur (flèches / WASD).
    /// </summary>
    public class InputManager : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private VirtualSteeringWheel wheel;
        [SerializeField] private HoldButton arrowLeft;
        [SerializeField] private HoldButton arrowRight;
        [SerializeField] private HoldButton gas;
        [SerializeField] private HoldButton brake;
        [SerializeField] private HoldButton handBrake;

        [Header("Inclinaison")]
        [SerializeField] private float tiltDeadZone = 0.05f;
        [SerializeField] private float tiltRange = 0.4f;     // inclinaison (en g) donnant la direction maximale

        [Header("Lissage")]
        [SerializeField] private float arrowSteerSpeed = 3f;
        [SerializeField] private float pedalSpeed = 5f;

        private float arrowSteer, throttle, brakeValue;

        private void Update()
        {
            if (player == null || player.Vehicle == null) return;

            float steer = ReadSteer();
            if (ControlSettings.InvertSteering) steer = -steer;

            float gasTarget = (gas != null && gas.IsHeld) ? 1f : 0f;
            float brakeTarget = (brake != null && brake.IsHeld) ? 1f : 0f;
            bool hand = handBrake != null && handBrake.IsHeld;

#if UNITY_EDITOR || UNITY_STANDALONE
            // Repli clavier pour tester sans téléphone
            float v = Input.GetAxisRaw("Vertical");
            if (v > 0f) gasTarget = 1f;
            if (v < 0f) brakeTarget = 1f;
            if (Input.GetKey(KeyCode.Space)) hand = true;
            float h = Input.GetAxisRaw("Horizontal");
            if (Mathf.Abs(h) > 0.01f) steer = h * (ControlSettings.InvertSteering ? -1f : 1f);
#endif
            // Les pédales montent progressivement (conduite plus douce au doigt)
            throttle = Mathf.MoveTowards(throttle, gasTarget, pedalSpeed * Time.deltaTime);
            brakeValue = Mathf.MoveTowards(brakeValue, brakeTarget, pedalSpeed * 2f * Time.deltaTime);

            player.Drive(steer, throttle, brakeValue, hand);
        }

        private float ReadSteer()
        {
            switch (ControlSettings.Mode)
            {
                case ControlMode.Arrows:
                    float target = 0f;
                    if (arrowLeft != null && arrowLeft.IsHeld) target -= 1f;
                    if (arrowRight != null && arrowRight.IsHeld) target += 1f;
                    arrowSteer = Mathf.MoveTowards(arrowSteer, target, arrowSteerSpeed * Time.deltaTime);
                    return arrowSteer;

                case ControlMode.Tilt:
                    // Téléphone tenu en paysage : l'axe Y de l'accéléromètre correspond à la gauche/droite
                    float tilt = Input.acceleration.y;
                    if (Screen.orientation == ScreenOrientation.LandscapeRight) tilt = -tilt;   // Signe à vérifier sur appareil réel
                    if (Mathf.Abs(tilt) < tiltDeadZone) return 0f;
                    return Mathf.Clamp(tilt / tiltRange, -1f, 1f) * ControlSettings.WheelSensitivity;

                default:
                    return wheel != null ? Mathf.Clamp(wheel.Value * ControlSettings.WheelSensitivity, -1f, 1f) : 0f;
            }
        }
    }
}
