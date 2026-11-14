<?php

namespace App\Domain\Credits;

final class MessageCost
{
    public function __construct(
        public readonly string $encoding,
        public readonly int $segments,
        public readonly int $unitsPerSegment,
        public readonly ?string $rateLabel = null,   // libellé du tarif appliqué (null = tarif par défaut)
    ) {}

    public function totalUnits(): int
    {
        return $this->segments * $this->unitsPerSegment;
    }
}
