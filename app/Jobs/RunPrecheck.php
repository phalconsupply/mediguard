<?php

namespace App\Jobs;

use App\Models\PrecheckRun;
use App\Services\Rules\PrecheckRunner;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPrecheck implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public readonly int $precheckRunId, public readonly ?int $actorId = null) {}

    public function handle(PrecheckRunner $runner): void
    {
        $run = PrecheckRun::with('batch')->findOrFail($this->precheckRunId);

        $runner->run($run);

        Audit::record($this->actorId, 'precheck.run_completed', $run, [
            'detected' => $run->detected_count,
            'insufficient' => $run->insufficient_count,
            'rules' => count($run->ruleset_snapshot ?? []),
        ]);
    }
}
