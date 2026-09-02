<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Services\BankAccountService;
use Illuminate\Console\Command;

class BackfillBankLedgers extends Command
{
    protected $signature = 'gl:backfill-bank-ledgers {--community= : Limit to a specific community ID}';

    protected $description = 'Assign an 8000/00n Bank GL ledger to every bank account that is missing one';

    /**
     * Execute the console command.
     *
     * @param BankAccountService $service
     * @return int
     */
    public function handle(BankAccountService $service): int
    {
        $query = BankAccount::whereNull('ledger_id');

        if ($communityId = $this->option('community')) {
            $query->where('community_id', $communityId);
        }

        $accounts = $query->orderBy('community_id')->orderBy('created_at')->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->info('No bank accounts require a GL ledger.');

            return self::SUCCESS;
        }

        $this->info("Backfilling GL ledgers for {$accounts->count()} bank account(s)...");

        foreach ($accounts as $account) {
            $ledger = $service->ensureLedger($account);
            $this->line("  {$account->name} → {$ledger->code} - {$ledger->name}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
