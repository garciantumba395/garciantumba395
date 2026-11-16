namespace TaxiKin.Vehicles
{
    /// <summary>Taxi-voiture : équilibré, plusieurs passagers, missions VIP.</summary>
    public class TaxiController : VehicleController
    {
        public override VehicleType Kind => VehicleType.Taxi;

        protected override VehicleStats CreateDefaultStats() => new VehicleStats
        {
            maxSpeedKmh = 75f, acceleration = 8f, brakeForce = 15f, turnRate = 90f,
            passengerCapacity = 4, fuelCapacity = 40f, fuelPerKm = 0.08f, fragility = 1f
        };
    }
}
