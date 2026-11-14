<?php

namespace App\Domain\Consent;

use Carbon\CarbonInterface;

/** Déclaration de consentement applicable à tout un fichier importé (source, preuve, date). */
final class ConsentDeclaration
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $evidence,
        public readonly CarbonInterface $obtainedAt,
        public readonly ?int $userId = null,
    ) {}
}
