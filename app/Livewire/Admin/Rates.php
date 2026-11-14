<?php

namespace App\Livewire\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contacts\PhoneNormalizer;
use App\Domain\Credits\CreditUnits;
use App\Models\SmsRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Administration — tarifs')]
class Rates extends Component
{
    public string $country = 'CD';

    public string $prefix = '';

    public string $label = '';

    public string $smsPer100 = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function add(AuditLogger $audit): void
    {
        $this->authorizeAdmin();
        $prefix = preg_replace('/[\s\-]/', '', $this->prefix);
        $this->prefix = $prefix;

        $this->validate([
            'country' => ['required', 'size:2', fn ($a, $v, $fail) => PhoneNormalizer::isSupportedCountry($v) || $fail('Pays non supporté.')],
            'prefix' => ['nullable', 'regex:/^\+\d{2,15}$/', Rule::unique('sms_rates', 'prefix')->where('country', strtoupper($this->country))],
            'label' => ['nullable', 'string', 'max:100'],
            'smsPer100' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        if ($prefix === '' && SmsRate::where('country', strtoupper($this->country))->whereNull('prefix')->exists()) {
            $this->addError('country', 'Un tarif pour tout ce pays existe déjà.');

            return;
        }

        try {
            $rate = SmsRate::create([
                'country' => strtoupper($this->country),
                'prefix' => $prefix ?: null,
                'label' => $this->label ?: null,
                'units_per_segment' => CreditUnits::unitsPerSegmentFromSmsPer100((int) $this->smsPer100),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->addError('smsPer100', $e->getMessage());

            return;
        }
        $audit->record('rates.created', null, $rate, ['country' => $rate->country, 'has_prefix' => $rate->prefix !== null, 'units_per_segment' => $rate->units_per_segment]);
        $this->reset('prefix', 'label', 'smsPer100');
        session()->flash('status', 'Tarif enregistré.');
    }

    public function toggle(int $id, AuditLogger $audit): void
    {
        $this->authorizeAdmin();
        $rate = SmsRate::findOrFail($id);
        $rate->update(['is_active' => ! $rate->is_active]);
        $audit->record($rate->is_active ? 'rates.activated' : 'rates.deactivated', null, $rate, ['country' => $rate->country]);
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.admin.rates', ['rates' => SmsRate::orderBy('country')->orderBy('prefix')->get(), 'countries' => PhoneNormalizer::countries()]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->is_platform_admin, 403);
    }
}
