<?php

use App\Models\Project;
use App\Services\ProjectSettingService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $name = '';

    public string $wilayah_label = '';

    public string $data_mode = Project::MODE_FULL_DPT;

    public string $population = '';

    public bool $is_random_sample = true;

    public bool $publish_results = false;

    public function mount(Project $project)
    {
        $this->project = $project;
        $this->name = $project->name;
        $this->wilayah_label = (string) $project->wilayah_label;
        $this->data_mode = $project->data_mode ?? Project::MODE_FULL_DPT;
        $this->population = (string) ($project->sample_population ?? '');
        $this->is_random_sample = (bool) ($project->is_random_sample ?? true);
        $this->publish_results = $project->resultsArePublished();
    }

    public function save(ProjectSettingService $service)
    {
        $service->update($this->project, $this->only(['name', 'wilayah_label', 'data_mode', 'population', 'is_random_sample', 'publish_results']));

        session()->flash('status', 'Pengaturan disimpan.');

        $this->redirectRoute('pengaturan', ['project' => $this->project->code_project]);
    }

    public function with(ProjectSettingService $service): array
    {
        return ['canChangeMode' => $service->canChangeMode($this->project)];
    }
};
?>

@php
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $modeOptions = [
        \App\Models\Project::MODE_FULL_DPT => ['DPT lengkap', 'Admin mengimpor DPT, lalu responden diundi di halaman Statistik.'],
        \App\Models\Project::MODE_RESPONDENTS_ONLY => ['Responden saja', 'Admin mengimpor calon responden langsung di halaman Responden. Menu DPT disembunyikan.'],
    ];
@endphp

<form wire:submit="save" class="max-w-2xl space-y-8">
    <section aria-labelledby="h-umum" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-umum" class="text-lg font-extrabold">Umum</h2>
        <div>
            <x-field label="Nama proyek" name="nama" wire:model="name" required />
            @error('name') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-1.5">
            <label for="f-wilayah" class="block text-sm font-semibold">Sebutan wilayah</label>
            <input id="f-wilayah" name="wilayah" type="text" wire:model.live.debounce.300ms="wilayah_label" aria-describedby="f-wilayah-help"
                   class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
            <p id="f-wilayah-help" class="text-xs text-slate-600">Contoh: Jaga, RT, Dusun. Dipakai di seluruh teks, misalnya “Progres {{ $wilayah_label !== '' ? $wilayah_label : '…' }}”. Kosongkan jika proyek tidak memakai wilayah.</p>
            @error('wilayah_label') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
    </section>

    <section aria-labelledby="h-mode" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-mode" class="text-lg font-extrabold">Mode data</h2>
        @unless ($canChangeMode)
            <p class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="note">Mode data tidak dapat diganti karena sudah ada DPT, responden, atau undian.</p>
        @endunless
        <fieldset class="space-y-3" @disabled(! $canChangeMode)>
            <legend class="sr-only">Mode data</legend>
            @foreach ($modeOptions as $value => [$title, $description])
                <label class="flex items-start gap-3 rounded-2xl border p-4 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent {{ $canChangeMode ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }} {{ $data_mode === $value ? 'border-accent bg-teal-50' : 'border-slate-200' }}">
                    <input type="radio" name="mode" value="{{ $value }}" wire:model.live="data_mode" @disabled(! $canChangeMode) class="mt-1 h-5 w-5 accent-accent">
                    <span><span class="block font-bold">{{ $title }}</span><span class="block text-sm text-slate-600">{{ $description }}</span></span>
                </label>
            @endforeach
        </fieldset>
        @error('data_mode') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror

        @if ($data_mode === \App\Models\Project::MODE_RESPONDENTS_ONLY)
            <div class="space-y-5 border-t border-slate-200 pt-5">
                <div>
                    <x-field label="Jumlah DPT" name="jumlah_dpt" type="number" min="1" wire:model="population" help="Opsional. Hanya berupa angka perkiraan populasi; jika kosong tampil “-”." />
                    @error('population') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <label class="flex min-h-11 items-start gap-3 text-sm">
                    <input type="checkbox" name="sampel_acak" wire:model="is_random_sample" class="mt-0.5 h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent">
                    <span><span class="block font-semibold">Sampel ini diambil secara acak</span><span class="block text-xs text-slate-600">Jika tidak dicentang, dashboard menampilkan peringatan sampel non-acak.</span></span>
                </label>
            </div>
        @endif
    </section>

    <section aria-labelledby="h-publik" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-publik" class="text-lg font-extrabold">Hasil untuk tamu</h2>
        <p class="text-sm text-slate-600">Selama belum dipublikasikan, halaman hasil hanya menampilkan pemberitahuan bahwa survei masih berlangsung. Jawaban individual tidak pernah ditampilkan.</p>
        <label class="flex min-h-11 items-start gap-3 text-sm">
            <input type="checkbox" name="publikasi" wire:model="publish_results" class="mt-0.5 h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent">
            <span><span class="block font-semibold">Survei selesai, publikasikan hasil</span><span class="block text-xs text-slate-600">Hapus centang untuk menarik kembali hasil dari publik.</span></span>
        </label>
        @error('publish_results') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        <div class="space-y-1.5" x-data="{ disalin: false }">
            <label for="tautan-hasil" class="block text-sm font-semibold">Tautan halaman hasil</label>
            <div class="flex gap-2">
                <input id="tautan-hasil" type="text" readonly value="{{ route('hasil', ['project' => $project->code_project]) }}" class="min-h-11 w-full rounded-2xl border border-slate-300 bg-slate-100 px-4 text-ink">
                <button type="button" @click="navigator.clipboard?.writeText($el.previousElementSibling.value); disalin = true; setTimeout(() => disalin = false, 2000)" class="min-h-11 shrink-0 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"><span x-text="disalin ? 'Tersalin ✓' : 'Salin'">Salin</span></button>
            </div>
            @if ($project->resultsArePublished())
                <p class="text-xs text-slate-600">Dipublikasikan pada {{ $project->results_published_at->format('d/m/Y H:i') }}.</p>
            @endif
        </div>
    </section>

    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="{{ $btnPrimary }}">Simpan pengaturan</button>
</form>
