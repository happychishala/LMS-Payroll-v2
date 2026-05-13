<?php

namespace App\Console\Commands;

use App\Services\WithholdingService;
use Illuminate\Console\Command;

class UpdateWithholdingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-withholding-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $updated = app(WithholdingService::class)->refreshPendingStatuses();
        $this->info("Withholding statuses updated: {$updated} record(s) changed.");
    }
}
