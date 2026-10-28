<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Project;
use App\Services\CandidateService;
use Livewire\Attributes\Computed;


new class extends Component
{
    use WithFileUploads;

    public $name;
    public $number;
    public $color = '#196e6b';
    public $picture;
    public Project $project;

    public function mount(Project $project)
    {
        $this->project = $project;
    }


    public function cleanForm()
    {
        $this->name = '';
        $this->number = '';
        $this->color = '#196e6b';
        $this->picture = null;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'number' => ['nullable', 'integer', 'min:1', function($attribute, $value, $fail){
                if($value === null || $value === ''){
                    return;
                }
                if($this->project->candidate->where($attribute, $value)->isNotEmpty()){
                    $fail('Number has been used');
                }
            }],
            'color' => 'nullable|string',
            'picture' => 'nullable|image|max:1024'
        ]);

        $instance = new CandidateService();
        $instance->create($this->project, [
            'name' => strtolower($this->name),
            'number' => $this->number ?? null,
            'color' => $this->color ?? null,
            'picture' => $this->picture ?? null,

        ]);

        $this->dispatch('candidate-added');

        $this->cleanForm();

    }


};
?>


<section aria-labelledby="h-tambah" class="h-fit rounded-2xl border border-slate-200 bg-white p-6">
    <h2 id="h-tambah" class="text-lg font-extrabold">Tambah kandidat</h2>
    <form method="POST" wire:submit="save" class="mt-5 space-y-5">
        @csrf
        <fieldset class="space-y-5 disabled:opacity-50">
            <x-field label="Nama" name="name" wire:model="name" required help="Wajib diisi." />
            @error('name')
                <p class="-mt-4 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            <x-field label="Nomor urut" name="nomor" wire:model="number" type="number" min="1" help="Opsional." />
            @error('number')
                <p class="-mt-4 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            <div class="space-y-1.5">
                <label for="f-warna" class="block text-sm font-semibold">Warna</label>
                <input id="f-warna" name="warna" wire:model="color" type="color" value="{{ $color }}" aria-describedby="f-warna-help" class="h-11 w-full cursor-pointer rounded-2xl border border-slate-300 bg-white p-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                <p id="f-warna-help" class="text-xs text-slate-600">Opsional. Jika tidak diubah, warna otomatis.</p>
                @error('color')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <x-field label="Foto" name="foto" wire:model="picture" type="file" accept="image/*" help="Opsional. Jika kosong, ditampilkan inisial." class="py-2.5" />
            @error('picture')
                <p class="-mt-4 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            <button type="submit" class="min-h-11 w-full rounded-2xl bg-accent px-4 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed">Tambah kandidat</button>
        </fieldset>
    </form>
</section>



