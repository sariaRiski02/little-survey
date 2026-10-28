<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CandidateService
{
    public function create(Project $project, array $data): Candidate
    {
        $this->ensureUnlocked($project);

        if (! empty($data['picture'])) {
            $data['picture'] = $data['picture']->store('pictures', 'public');
        }

        return $project->Candidate()->create($data);
    }

    /**
     * Update a candidate that belongs to the given project.
     * A new `picture` upload replaces the old file; a missing/null `picture` keeps it.
     *
     * @param  array{name?: string, number?: int|string|null, color?: string|null, picture?: UploadedFile|null}  $data
     */
    public function update(Project $project, array $data, int|string $id): Candidate
    {
        $this->ensureUnlocked($project);

        $candidate = $project->Candidate()->findOrFail($id);
        $oldPicture = $candidate->picture;

        if (! empty($data['picture'])) {
            $data['picture'] = $data['picture']->store('pictures', 'public');
        } else {
            unset($data['picture']);
        }

        $candidate->update($data);

        if (isset($data['picture']) && $oldPicture) {
            Storage::disk('public')->delete($oldPicture);
        }

        return $candidate;
    }

    public function delete(Project $project, int|string $id): void
    {
        $this->ensureUnlocked($project);

        $candidate = $project->Candidate()->findOrFail($id);

        if ($candidate->picture) {
            Storage::disk('public')->delete($candidate->picture);
        }

        $candidate->delete();
    }

    public function setRandomOrder(Project $project, bool $randomize): Project
    {
        $this->ensureUnlocked($project);

        $project->update(['randomize_candidates' => $randomize]);

        return $project;
    }

    public function getCandidate(Project $project)
    {
        return $project->Candidate()->get();
    }

    /**
     * Candidates cannot be changed once the project is locked.
     * The lock state is re-read from the database to avoid stale component state.
     */
    private function ensureUnlocked(Project $project): void
    {
        $isLocked = Project::whereKey($project->getKey())->value('is_locked');

        if ($isLocked) {
            throw ValidationException::withMessages([
                'project' => 'Daftar kandidat terkunci, sehingga tidak dapat diubah.',
            ]);
        }
    }
}
