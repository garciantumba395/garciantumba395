<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** En-têtes de sécurité sur toutes les réponses (valeurs : config/security.php > headers). Un en-tête déjà posé n'est pas écrasé. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $c = config('security.headers');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY', // anciens navigateurs ; frame-ancestors (CSP) fait foi pour les autres
            'Referrer-Policy' => $c['referrer_policy'],
            'Permissions-Policy' => $c['permissions_policy'],
        ];

        // HSTS : uniquement en HTTPS (sur HTTP il est ignoré, et le poser pourrait masquer une mauvaise configuration du proxy).
        if ($c['hsts'] && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.(int) $c['hsts_max_age'].($c['hsts_include_subdomains'] ? '; includeSubDomains' : '');
        }

        if (! empty($c['csp'])) {
            $headers[$c['csp_report_only'] ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy'] = $c['csp'];
        }

        foreach (array_filter($headers) as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
