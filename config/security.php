<?php

/*
 | Sécurité et exploitation (étape 9). Toutes les valeurs sont des CHOIX TECHNIQUES configurables, listés dans
 | docs/DECISIONS.md (D40 et suivantes) comme « à valider ». Aucune valeur secrète ici.
 */
return [
    /*
     | D10 (tranchée : oui) — 2FA OBLIGATOIRE pour les owners/admins d'entreprise et les administrateurs de plateforme : sans 2FA, ils sont
     | redirigés vers /profil tant qu'ils ne l'ont pas activée. Reste optionnelle pour les simples membres. Désactivable
     | (SECURITY_REQUIRE_2FA_FOR_ADMINS=false) uniquement pour le développement local ou la démonstration.
     */
    'require_2fa_for_admins' => (bool) env('SECURITY_REQUIRE_2FA_FOR_ADMINS', true),

    /*
     | D10 (tranchée : oui) — adresse e-mail VÉRIFIÉE obligatoire : un compte non vérifié ne peut accéder à rien d'autre qu'à la page de vérification
     | (renvoi du lien, déconnexion). Nécessite un envoi d'e-mails réel en production (MAIL_MAILER, voir docs/OPERATIONS.md).
     */
    'require_email_verification' => (bool) env('SECURITY_REQUIRE_EMAIL_VERIFICATION', true),

    // TOTP (RFC 6238, HMAC-SHA1 : seul algorithme universellement géré par les applications d'authentification).
    'totp' => [
        'issuer' => env('SECURITY_TOTP_ISSUER'),            // défaut : nom de l'application
        'digits' => 6,
        'period' => 30,                                       // secondes par pas
        'window' => (int) env('SECURITY_TOTP_WINDOW', 1),     // tolérance en pas, de chaque côté (1 = ±30 s)
        'pending_login_minutes' => 10,                        // durée de validité d'un défi en attente à la connexion
        'recovery_codes' => 8,
    ],

    /*
     | Limitation de débit nommée : max tentatives par fenêtre de decay secondes. Clé de comptage indiquée entre parenthèses.
     | Une limite atteinte renvoie une erreur de formulaire (Livewire) ou HTTP 429 (plafond global).
     */
    'rate_limits' => [
        'email_verification' => ['max' => 3, 'decay' => 60],                                       // renvoi du lien (utilisateur)
        'login' => ['max' => 5, 'decay' => 60],                                                  // (e-mail + IP) historique
        'register' => ['max' => (int) env('RATE_REGISTER_MAX', 5), 'decay' => 600],              // (IP)
        'password_forgot' => ['max' => (int) env('RATE_PASSWORD_FORGOT_MAX', 5), 'decay' => 600], // (IP + e-mail)
        'two_factor_challenge' => ['max' => (int) env('RATE_2FA_MAX', 5), 'decay' => 300],       // (utilisateur)
        'sensitive_profile' => ['max' => (int) env('RATE_PROFILE_MAX', 5), 'decay' => 300],      // (utilisateur) : mot de passe actuel, 2FA
        'csv_import' => ['max' => (int) env('RATE_CSV_IMPORT_MAX', 10), 'decay' => 3600],        // (utilisateur)
        'test_send' => ['max' => (int) env('RATE_TEST_SEND_MAX', 10), 'decay' => 600],           // (utilisateur)
        'admin_actions' => ['max' => (int) env('RATE_ADMIN_MAX', 60), 'decay' => 60],            // (utilisateur)
        'livewire' => ['max' => (int) env('RATE_LIVEWIRE_MAX', 240), 'decay' => 60],             // plafond global /livewire/update (utilisateur ou IP)
    ],

    /*
     | En-têtes de sécurité. HSTS uniquement sur une requête HTTPS. CSP : voir docs/ARCHITECTURE.md (une politique
     | script-src stricte casse Livewire/Alpine : seule une politique « non-script » est envoyée par défaut).
     */
    'headers' => [
        'hsts' => (bool) env('SECURITY_HSTS', true),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 15552000), // 180 jours
        'hsts_include_subdomains' => (bool) env('SECURITY_HSTS_SUBDOMAINS', false),
        'csp' => env('SECURITY_CSP', "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"),
        'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
        'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'referrer_policy' => 'strict-origin-when-cross-origin',
    ],

    // Surveillance continue des entreprises validées (commande sending:review).
    'review' => [
        'window_days' => (int) env('SENDING_REVIEW_WINDOW_DAYS', 7),   // fenêtre glissante d'envois RÉELS
        'min_sample' => (int) env('SENDING_REVIEW_MIN_SAMPLE', 50),    // échantillon minimal dans la fenêtre
    ],
];
