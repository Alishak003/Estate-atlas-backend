<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use Illuminate\Console\Command;

class UpdateAffiliateVisits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:update-visits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update total_visits for all affiliates based on actual clicks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $affiliates = Affiliate::all();

        foreach ($affiliates as $affiliate) {
            $actualCount = $affiliate->clicks()->count();
            $affiliate->total_visits = $actualCount;
            $affiliate->save();

            $this->info("Updated affiliate {$affiliate->affiliate_code}: {$actualCount} visits");
        }

        $this->info('All affiliate visit counts updated!');
    }
}
