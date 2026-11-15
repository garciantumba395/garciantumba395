using System.Text.RegularExpressions;

namespace TaxiKin.Player
{
    /// <summary>Validation du nom du chauffeur : longueur, caractères autorisés et mots interdits.</summary>
    public static class NameFilter
    {
        public const int MinLength = 3;
        public const int MaxLength = 16;

        // Lettres (accents inclus), chiffres, espace, tiret, underscore, apostrophe
        private static readonly Regex Allowed = new Regex(@"^[\p{L}0-9 _\-']+$", RegexOptions.Compiled);

        // Liste à compléter (FR/Lingala/Swahili/EN) avant la sortie
        private static readonly string[] Banned = { "admin", "moderateur" };

        public static string Clean(string raw) => Regex.Replace((raw ?? string.Empty).Trim(), @"\s+", " ");

        public static bool ContainsBanned(string text)
        {
            string lower = (text ?? string.Empty).ToLowerInvariant();
            foreach (var word in Banned) if (lower.Contains(word)) return true;
            return false;
        }

        /// <summary>Retourne true si le nom est valide ; sinon, error contient le message à afficher.</summary>
        public static bool IsValid(string raw, out string error)
        {
            string name = Clean(raw);
            error = null;
            if (name.Length < MinLength) { error = $"Le nom doit contenir au moins {MinLength} caractères."; return false; }
            if (name.Length > MaxLength) { error = $"Le nom ne doit pas dépasser {MaxLength} caractères."; return false; }
            if (!Allowed.IsMatch(name)) { error = "Caractères non autorisés."; return false; }

            string lower = name.ToLowerInvariant();
            foreach (var word in Banned)
                if (lower.Contains(word)) { error = "Ce nom n'est pas autorisé."; return false; }
            return true;
        }
    }
}
