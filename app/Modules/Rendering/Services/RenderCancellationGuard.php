<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Quran\Models\RenderJob;
use App\Modules\Rendering\Exceptions\RenderCancelledException;
use Illuminate\Support\Facades\Log;

class RenderCancellationGuard
{
    /**
     * Reload job status and throw exception if cancellation was requested.
     *
     * @param RenderJob|null $renderJob
     * @throws RenderCancelledException
     */
    public function ensureNotCancelled(?RenderJob $renderJob): void
    {
        if (!$renderJob) {
            return;
        }

        // Fresh reload from the database to get the latest status
        $renderJob->refresh();

        if ($renderJob->isCancelling() || $renderJob->isCancelled()) {
            Log::info("[RenderCancellationGuard] Cancellation detected for job {$renderJob->uuid}. Throwing RenderCancelledException.");
            throw new RenderCancelledException("Rendering was cancelled by the user.");
        }
    }
}
