<?php

namespace App\Console\Commands;

use App\Enums\OfferStatus;
use App\Models\Offer;
use Illuminate\Console\Command;

class ExpireOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offers:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire offers that have passed their expiry date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = Offer::query()
            ->whereIn('status', [OfferStatus::Sent, OfferStatus::Viewed])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay())
            ->update([
                'status' => OfferStatus::Expired,
                'expired_at' => now(),
            ]);

        $this->info("Expired {$expired} offer(s).");

        return self::SUCCESS;
    }
}
