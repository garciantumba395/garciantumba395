<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;

/** Exige une entreprise active ; sinon propose d'en créer une. */
class EnsureCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->current->company() === null) {
            return redirect()->route('companies.create');
        }

        return $next($request);
    }
}
