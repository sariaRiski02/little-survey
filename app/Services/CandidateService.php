<?php

namespace App\Services;

use App\Models\Project;

class CandidateService
{
    /**
     * Create a new class instance.
     */

    public function create(Project $project,array $data)
    {
        if($data["picture"])
        {
            $data["picture"] = $data["picture"]->store('pictures', 'public');
        }
        return $project->Candidate()->create($data);
    }

    public function getCandidate(Project $project){
        return $project->Candidate()->get();
    }




}
