<?php

use App\Models\Project;
use App\Services\DptService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $name = '';

    public string $nik = '';

    public string $birth_date = '';

    public string $wilayah = '';

    public string $phone = '';

    public string $gender = '';

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function save(DptService $service)
    {
        $data = $this->only(['name', 'nik', 'birth_date', 'wilayah', 'phone', 'gender']);

        $service->validator($this->project, $data)->validate();
        $service->create($this->project, $data);

        $this->reset(['name', 'nik', 'birth_date', 'wilayah', 'phone', 'gender']);
        $this->dispatch('dpt-changed');
    }

    public function with(DptService $service): array
    {
        return ['wilayahOptions' => $service->wilayahOptions($this->project)];
    }
};
?>

@php
    $selectCls = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
@endphp

<form wire:submit="save" class="space-y-5">
    @error('project')
        <p class="rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
    @enderror
    <fieldset :disabled="terkunci" class="grid gap-5 sm:grid-cols-2 disabled:opacity-50">
        <div>
            <x-field label="Nama" name="dpt-nama" wire:model="name" required />
            @error('name') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-field label="NIK" name="dpt-nik" wire:model="nik" inputmode="numeric" maxlength="16" required help="16 digit." />
            @error('nik') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-field label="Tanggal lahir" name="dpt-lahir" wire:model="birth_date" type="date" required />
            @error('birth_date') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        @if ($project->usesWilayah())
            <div class="space-y-1.5">
                <label for="dpt-wilayah" class="block text-sm font-semibold">{{ $project->wilayah_label }}</label>
                <input id="dpt-wilayah" list="dpt-wilayah-list" wire:model="wilayah" required class="{{ $selectCls }}">
                <datalist id="dpt-wilayah-list">
                    @foreach ($wilayahOptions as $option)<option value="{{ $option }}"></option>@endforeach
                </datalist>
                @error('wilayah') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
        @endif
        <div>
            <x-field label="Nomor WhatsApp" name="dpt-wa" wire:model="phone" type="tel" help="Opsional." />
            @error('phone') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-1.5">
            <label for="dpt-jk" class="block text-sm font-semibold">Jenis kelamin</label>
            <select id="dpt-jk" wire:model="gender" class="{{ $selectCls }}">
                <option value="">Tidak diisi</option>
                <option>Laki-laki</option>
                <option>Perempuan</option>
            </select>
            <p class="text-xs text-slate-600">Opsional.</p>
            @error('gender') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <button type="submit" wire:loading.attr="disabled" class="min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">Simpan data</button>
        </div>
    </fieldset>
</form>
