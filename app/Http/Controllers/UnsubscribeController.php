<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Consent\ConsentService;
use App\Models\Company;
use App\Models\UnsubscribeLink;
use App\Support\Tenancy\CurrentCompany;

/**
 * Page PUBLIQUE de désabonnement (art. 70, 72, 213, 348 du Code du numérique) : aucun compte requis.
 *  - GET  : affiche l'identité de l'expéditeur (art. 66) et demande une confirmation. Un simple chargement NE désinscrit PAS
 *           (les messageries et antivirus préchargent les liens) ;
 *  - POST : désinscrit IMMÉDIATEMENT, de façon idempotente, et accuse réception (art. 70, point 1).
 * La désinscription est honorée même si l'entreprise est inactive ou si le contact a été supprimé.
 */
class UnsubscribeController extends Controller
{
    public function show(string $code)
    {
        [$link, $company] = $this->resolve($code);

        return $this->noStore(response()->view('unsubscribe.show', ['company' => $company, 'code' => $link->code, 'already' => $link->used_at !== null]));
    }

    public function confirm(string $code, CurrentCompany $current, ConsentService $consent, AuditLogger $audit)
    {
        [$link, $company] = $this->resolve($code);

        $current->runAs($company, function () use ($link, $consent, $audit, $company) {
            $consent->optOut($link->phone, 'unsubscribe_link', 'Désinscription par lien public');
            if ($link->used_at === null) {
                UnsubscribeLink::whereKey($link->id)->whereNull('used_at')->update(['used_at' => now()]);
                $audit->recordSystem('contact.unsubscribed', $company, $link, ['channel' => 'link']); // aucun numéro dans l'audit
            }
        });

        return $this->noStore(response()->view('unsubscribe.done', ['company' => $company]));
    }

    /** @return array{0:UnsubscribeLink,1:Company} */
    private function resolve(string $code): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{6,32}$/', $code) === 1, 404);
        $link = UnsubscribeLink::where('code', $code)->firstOrFail();

        return [$link, Company::findOrFail($link->company_id)];
    }

    private function noStore($response)
    {
        return $response->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
