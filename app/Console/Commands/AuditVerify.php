<?php

namespace App\Console\Commands;

use App\Services\Audit;
use Illuminate\Console\Command;

class AuditVerify extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verify the hash chain of the audit log';

    public function handle(): int
    {
        $broken = Audit::firstBrokenLink();
        if ($broken) {
            $this->error("Audit log chain is broken at entry #$broken.");

            return self::FAILURE;
        }
        $this->info('Audit log chain is intact.');

        return self::SUCCESS;
    }
}
