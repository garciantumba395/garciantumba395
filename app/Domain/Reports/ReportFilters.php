<?php

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Filtres d'un rapport. Les dates sont des JOURS CIVILS du fuseau de l'application (`config('app.timezone')`),
 * fin incluse. Laravel enregistre et relit les horodatages dans ce fuseau (aucune conversion UTC) : les bornes
 * sont donc comparées telles quelles : `created_at >= « début 00:00:00 » AND created_at < « lendemain de la fin 00:00:00 »`.
 */
final class ReportFilters
{
    public const MODE_REAL = 'real';

    public const MODE_SIMULATED = 'simulated';

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $campaignId,
        public readonly string $mode,
    ) {}

    /** Période par défaut : les N derniers jours, aujourd'hui inclus, « aujourd'hui » étant celui du fuseau de l'application. */
    public static function defaultPeriod(): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return [
            'from' => $today->subDays(max(1, (int) config('reports.default_days')) - 1)->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ];
    }

    /** @param array{from?:mixed,to?:mixed,campaign?:mixed,mode?:mixed} $input */
    public static function validator(array $input, int $companyId): ValidatorContract
    {
        $validator = Validator::make($input, [
            'from' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date_format:Y-m-d'],
            'to' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date_format:Y-m-d', 'after_or_equal:from'],
            // Une campagne d'une AUTRE entreprise est refusée comme une campagne inexistante.
            'campaign' => ['nullable', 'integer', 'min:1', Rule::exists('campaigns', 'id')->where('company_id', $companyId)],
            'mode' => ['required', Rule::in([self::MODE_REAL, self::MODE_SIMULATED])],
        ], [
            'from.required' => 'La date de début est obligatoire.',
            'from.regex' => 'La date de début est invalide.',
            'from.date_format' => 'La date de début est invalide.',
            'to.required' => 'La date de fin est obligatoire.',
            'to.regex' => 'La date de fin est invalide.',
            'to.date_format' => 'La date de fin est invalide.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'campaign.exists' => 'Campagne introuvable.',
            'campaign.integer' => 'Campagne introuvable.',
            'campaign.min' => 'Campagne introuvable.',
            'mode.required' => 'Choisissez Réel ou Simulation.',
            'mode.in' => 'Mode inconnu.',
        ]);

        $validator->after(function ($v) {
            if ($v->errors()->hasAny(['from', 'to'])) {
                return;
            }
            $data = $v->getData();
            $days = (int) CarbonImmutable::createFromFormat('!Y-m-d', $data['from'])
                ->diffInDays(CarbonImmutable::createFromFormat('!Y-m-d', $data['to'])) + 1;
            $max = (int) config('reports.max_range_days');
            if ($days > $max) {
                $v->errors()->add('to', "La période ne peut pas dépasser {$max} jours.");
            }
        });

        return $validator;
    }

    /** À n'appeler qu'avec des données issues de `validator()` (validées). */
    public static function fromValidated(array $data): self
    {
        $campaign = $data['campaign'] ?? null;

        return new self($data['from'], $data['to'], ($campaign === null || $campaign === '') ? null : (int) $campaign, $data['mode']);
    }

    public function simulated(): bool
    {
        return $this->mode === self::MODE_SIMULATED;
    }

    /** Borne basse incluse (heure locale de l'application, comme stockée en base). */
    public function startSql(): string
    {
        return $this->from.' 00:00:00';
    }

    /** Borne haute EXCLUE : minuit du lendemain du dernier jour (fin incluse). */
    public function endExclusiveSql(): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->to)->addDay()->format('Y-m-d').' 00:00:00';
    }

    /** Préfixe des clés d'idempotence des messages d'une campagne (campaign:{id}:contact:{id}[:r{n}]), ou null. */
    public function campaignKeyPrefix(): ?string
    {
        return $this->campaignId === null ? null : 'campaign:'.$this->campaignId.':';
    }

    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from, 'to' => $this->to, 'campaign' => $this->campaignId, 'mode' => $this->mode,
        ], fn ($v) => $v !== null);
    }
}
