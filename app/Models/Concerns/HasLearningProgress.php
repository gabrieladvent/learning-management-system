<?php

namespace App\Models\Concerns;

use App\Models\LearningProgressEvent;
use App\Models\LearningProgressSession;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLearningProgress
{
    public function progressEvents(): MorphMany
    {
        return $this->morphMany(LearningProgressEvent::class, 'trackable');
    }

    public function progressSessions(): MorphMany
    {
        return $this->morphMany(LearningProgressSession::class, 'trackable');
    }
}
