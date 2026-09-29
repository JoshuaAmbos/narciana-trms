<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

/** Daily: Unpaid bills past due date + grace become Overdue (scheduled in routes/console.php). */
class MarkOverdueBills extends Command
{
    protected $signature = 'bills:mark-overdue';

    protected $description = 'Mark unpaid bills past their due date and grace period as Overdue';

    public function handle(PaymentService $payments): int
    {
        $changed = $payments->refreshOverdue();
        $this->info("Bill statuses updated: {$changed}.");

        return self::SUCCESS;
    }
}
