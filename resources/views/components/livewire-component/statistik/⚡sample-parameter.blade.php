<?php

use App\Models\Project;
use App\Services\StatistikService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $confidence = '95';

    public string $margin_error = '5';

    public string $proportion = '0,5';

    public string $reserve = '20';

    public string $population = '';

    public bool $saved = false;

    public function mount(Project $project, StatistikService $service)
    {
        $this->project = $project;

        $params = $service->parameters($project);
        $this->confidence = (string) $params['confidence'];
        $this->margin_error = $this->toInput($params['margin_error']);
        $this->proportion = $this->toInput($params['proportion']);
        $this->reserve = (string) $params['reserve'];
        $this->population = (string) ($params['population'] ?? '');
    }

    public function save(StatistikService $service)
    {
        $this->saved = false;

        $service->saveParameters($this->project, $this->only(['confidence', 'margin_error', 'proportion', 'reserve', 'population']));

        $this->saved = true;
        $this->dispatch('sample-params-saved');
    }

    public function with(StatistikService $service): array
    {
        return ['dptCount' => $this->project->isFullDptMode() ? $service->population($this->project) : null];
    }

    private function toInput(float $value): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.'));
    }
};
?>

@php
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<section aria-labelledby="h-param" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
    <h2 id="h-param" class="text-lg font-extrabold">Parameter</h2>
    <form wire:submit="save" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            @if ($project->isFullDptMode())
                <x-field label="Populasi (N)" name="populasi" :value="number_format($dptCount, 0, ',', '.')" readonly class="bg-slate-100" help="Terisi otomatis dari jumlah DPT." />
            @else
                <x-field label="Populasi (N)" name="populasi" type="number" min="1" wire:model="population" help="Perkiraan jumlah populasi. Isi manual." />
                @error('population') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            @endif
        </div>
        <div class="space-y-1.5">
            <label for="kepercayaan" class="block text-sm font-semibold">Tingkat kepercayaan</label>
            <select id="kepercayaan" wire:model="confidence" class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                @foreach (array_keys(\App\Services\StatistikService::CONFIDENCE_LEVELS) as $level)<option value="{{ $level }}">{{ $level }}%</option>@endforeach
            </select>
            <p class="text-xs text-slate-600">Bawaan 95%.</p>
            @error('confidence') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-field label="Margin of error (%)" name="galat" type="text" inputmode="decimal" wire:model="margin_error" help="Bawaan 5%." />
            @error('margin_error') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-field label="Proporsi (p)" name="p" type="text" inputmode="decimal" wire:model="proportion" help="Bawaan 0,5 (paling hati-hati)." />
            @error('proportion') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-field label="Cadangan non-respons (%)" name="cadangan" type="number" min="0" max="90" wire:model="reserve" help="Bawaan 20%." />
            @error('reserve') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end"><button type="submit" wire:loading.attr="disabled" wire:target="save" class="{{ $btnPrimary }} w-full">Hitung</button></div>
    </form>
    @if ($saved)
        <p class="mt-4 text-sm text-teal-800" role="status">Parameter disimpan dan hasil dihitung ulang.</p>
    @endif
</section>
