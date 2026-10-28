<?php

use App\Models\Project;
use App\Services\RespondentService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $name = '';

    public string $nik = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $wilayah = '';

    public string $social_media = '';

    public string $phone = '';

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function save(RespondentService $service)
    {
        $service->create($this->project, $this->only(['name', 'nik', 'birth_date', 'gender', 'wilayah', 'social_media', 'phone']));

        $this->reset(['name', 'nik', 'birth_date', 'gender', 'wilayah', 'social_media', 'phone']);
        $this->dispatch('respondent-changed');
        $this->dispatch('respondent-added');
    }

    public function cancel()
    {
        $this->reset(['name', 'nik', 'birth_date', 'gender', 'wilayah', 'social_media', 'phone']);
        $this->resetValidation();
    }

    public function with(RespondentService $service): array
    {
        return ['wilayahOptions' => $service->wilayahOptions($this->project)];
    }
};
?>

@php
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
@endphp

<div @respondent-added.window="tambah = false">
    <x-modal show="tambah" title="Tambah responden">
        @error('project')
            <p class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
        @enderror
        <div class="max-h-[60vh] space-y-4 overflow-y-auto p-1">
            <div>
                <x-field label="Nama" name="r-nama" wire:model="name" required />
                @error('name') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-field label="NIK" name="r-nik" wire:model="nik" inputmode="numeric" maxlength="16" required help="16 digit." />
                @error('nik') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-field label="Tanggal lahir" name="r-lahir" wire:model="birth_date" type="date" required />
                @error('birth_date') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1.5">
                <label for="r-jk" class="block text-sm font-semibold text-ink">Jenis kelamin</label>
                <select id="r-jk" wire:model="gender" required class="{{ $input }}"><option value="">Pilih</option><option>Laki-laki</option><option>Perempuan</option></select>
                @error('gender') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            @if ($project->usesWilayah())
                <div class="space-y-1.5">
                    <label for="r-wil" class="block text-sm font-semibold text-ink">{{ $project->wilayah_label }}</label>
                    <input id="r-wil" list="r-wil-list" wire:model="wilayah" required class="{{ $input }}">
                    <datalist id="r-wil-list">@foreach ($wilayahOptions as $option)<option value="{{ $option }}"></option>@endforeach</datalist>
                    @error('wilayah') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <x-field label="Sosial media" name="r-sosmed" wire:model="social_media" help="Opsional." />
                @error('social_media') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-field label="Nomor telepon" name="r-telp" wire:model="phone" type="tel" help="Opsional. Dibutuhkan untuk kirim via WhatsApp." />
                @error('phone') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:footer>
            <button type="button" @click="tambah = false" wire:click="cancel" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Batal</button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-50">Simpan</button>
        </x-slot:footer>
    </x-modal>
</div>
