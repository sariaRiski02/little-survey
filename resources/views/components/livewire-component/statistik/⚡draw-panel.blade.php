<?php

use App\Models\Project;
use App\Services\StatistikService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $reason = '';

    public bool $understood = false;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    #[On('sample-params-saved')]
    public function refreshPanel()
    {
        unset($this->calculation, $this->draws);
    }

    public function draw(StatistikService $service)
    {
        $service->draw($this->project);

        $this->afterDraw();
    }

    public function redraw(StatistikService $service)
    {
        $this->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'understood' => ['accepted'],
        ], [
            'reason.required' => 'Alasan undi ulang wajib diisi.',
            'reason.min' => 'Alasan terlalu singkat (minimal 5 karakter).',
            'reason.max' => 'Alasan maksimal 500 karakter.',
            'understood.accepted' => 'Centang pernyataan ini untuk melanjutkan.',
        ]);

        $service->redraw($this->project, $this->reason);

        $this->reset(['reason', 'understood']);
        $this->afterDraw();
    }

    public function cancelRedraw()
    {
        $this->reset(['reason', 'understood']);
        $this->resetValidation();
    }

    private function afterDraw(): void
    {
        $this->project->refresh();
        unset($this->calculation, $this->draws);
        $this->dispatch('sample-drawn');
        $this->dispatch('redraw-done');
    }

    #[Computed]
    public function calculation(): ?array
    {
        $service = app(StatistikService::class);

        return $service->calculate($service->population($this->project), $service->parameters($this->project));
    }

    #[Computed]
    public function draws()
    {
        return $this->project->SampleDraws()->latest('id')->get();
    }
};
?>

@php
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $draws = $this->draws;
    $latest = $draws->first();
    $calculation = $this->calculation;
    $paramsChanged = $latest && $calculation && $calculation['invitations'] !== $latest->invitation_count;
@endphp

<div x-data="{ ulang: false }" @redraw-done.window="ulang = false">
    <section aria-labelledby="h-undi" class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-undi" class="text-lg font-extrabold">Pengundian</h2>
        @error('project')
            <p class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
        @enderror

        @if (! $latest)
            <div class="mt-5 space-y-4">
                <p class="text-slate-600">Undi acak memilih responden dari DPT{{ $project->usesWilayah() ? ' sesuai kuota tiap '.$project->wilayah_label : ' secara acak sederhana dari seluruh daftar' }}. Setelah diundi, DPT dan daftar kandidat dikunci.</p>
                @unless ($calculation)
                    <p class="text-sm text-amber-900">DPT masih kosong. Impor atau tambah data DPT terlebih dahulu.</p>
                @endunless
                <button type="button" wire:click="draw" wire:loading.attr="disabled" wire:target="draw" @disabled(! $calculation) class="{{ $btnPrimary }}">Undi acak</button>
            </div>
        @else
            <div class="mt-5 space-y-4">
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                    <strong>Pengundian terkunci.</strong> DPT tidak dapat diubah. Undi ulang membutuhkan konfirmasi dan alasan, dan akan dicatat.
                </div>
                @if ($paramsChanged)
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900" role="note">
                        Parameter berubah sejak undi terakhir (undangan sekarang {{ number_format($calculation['invitations'], 0, ',', '.') }}, terpilih {{ number_format($latest->selected_count, 0, ',', '.') }}). Undi ulang untuk menerapkannya.
                    </div>
                @endif
                <dl class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Nilai seed</dt><dd class="font-mono text-lg font-extrabold">{{ $latest->seed }}</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Waktu undi</dt><dd class="font-bold">{{ $latest->drawn_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Responden terpilih</dt><dd class="font-bold">{{ number_format($latest->selected_count, 0, ',', '.') }} dari {{ number_format($latest->population, 0, ',', '.') }}</dd></div>
                </dl>
                <button type="button" @click="ulang = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">Undi ulang…</button>
            </div>

            <div class="mt-6">
                <h3 class="mb-3 font-bold">Riwayat undi ({{ $draws->count() }})</h3>
                <ul class="space-y-2">
                    @foreach ($draws as $item)
                        <li wire:key="undi-{{ $item->id }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 p-3 text-sm">
                            <x-badge :tone="$item->isRedraw() ? 'warning' : 'success'">{{ $item->isRedraw() ? 'Undi ulang' : 'Undi pertama' }}</x-badge>
                            <span>{{ $item->drawn_at->format('d/m/Y H:i') }}</span>
                            <span class="font-mono text-slate-600">seed {{ $item->seed }}</span>
                            <span class="text-slate-600">{{ number_format($item->selected_count, 0, ',', '.') }} terpilih</span>
                            @if ($item->reason)<span class="w-full text-slate-600">Alasan: {{ $item->reason }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @if ($latest)
        <x-modal show="ulang" title="Undi ulang responden?">
            Undi ulang akan mengganti daftar responden saat ini.
            <div class="mt-4 space-y-4">
                <div class="space-y-1.5">
                    <label for="alasan" class="block text-sm font-semibold text-ink">Alasan undi ulang (wajib)</label>
                    <textarea id="alasan" rows="3" wire:model="reason" class="w-full rounded-2xl border border-slate-300 bg-white p-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                    @error('reason') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="flex min-h-11 items-center gap-3 text-sm text-ink">
                        <input type="checkbox" wire:model="understood" class="h-5 w-5 rounded border-slate-300 accent-accent focus-visible:outline-2 focus-visible:outline-accent">
                        Saya paham daftar responden lama akan diganti.
                    </label>
                    @error('understood') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <x-slot:footer>
                <button type="button" @click="ulang = false" wire:click="cancelRedraw" class="{{ $btnGhost }}">Batal</button>
                <button type="button" wire:click="redraw" wire:loading.attr="disabled" wire:target="redraw" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 disabled:cursor-not-allowed disabled:opacity-50">Undi ulang</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
