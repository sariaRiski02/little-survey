<?php

use App\Models\Project;
use App\Services\RespondentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    #[On('respondent-changed')]
    public function refreshSummary()
    {
        unset($this->summary);
    }

    #[Computed]
    public function summary(): array
    {
        return app(RespondentService::class)->summary($this->project);
    }
};
?>

<section aria-labelledby="h-ringkasan" class="mb-10">
    <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total responden" :value="number_format($this->summary['total'], 0, ',', '.')" />
        <x-stat-card label="Sudah mengisi" :value="number_format($this->summary['filled'], 0, ',', '.')" />
        <x-stat-card label="Belum mengisi" :value="number_format($this->summary['notFilled'], 0, ',', '.')" />
        <x-stat-card label="Tautan belum dikirim" :value="number_format($this->summary['notSent'], 0, ',', '.')" />
    </div>
</section>
