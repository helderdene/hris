<?php

namespace App\Console\Commands;

use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Services\HtmlSanitizerService;
use Illuminate\Console\Command;

class SanitizeOfferContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanitize:offer-content {--dry-run : Preview changes without modifying the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sanitize HTML content in existing offers and offer templates to remove XSS vulnerabilities';

    /**
     * Execute the console command.
     */
    public function handle(HtmlSanitizerService $sanitizer): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        $this->sanitizeOffers($sanitizer, $dryRun);
        $this->sanitizeOfferTemplates($sanitizer, $dryRun);

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Sanitization complete.');

        return Command::SUCCESS;
    }

    private function sanitizeOffers(HtmlSanitizerService $sanitizer, bool $dryRun): void
    {
        $this->info('Scanning Offers...');

        $offers = Offer::whereNotNull('content')->get();
        $changedCount = 0;

        foreach ($offers as $offer) {
            $original = $offer->content;
            $sanitized = $sanitizer->sanitize($original);

            if ($original !== $sanitized) {
                $changedCount++;
                $this->warn("Offer #{$offer->id} content will be sanitized.");

                if ($this->output->isVerbose()) {
                    $this->line("  Original length: " . strlen((string) $original));
                    $this->line("  Sanitized length: " . strlen((string) $sanitized));
                }

                if (! $dryRun) {
                    $offer->content = $sanitized;
                    $offer->saveQuietly();
                }
            }
        }

        $this->info("Offers: {$changedCount} of {$offers->count()} records " . ($dryRun ? 'would be' : 'were') . ' sanitized.');
    }

    private function sanitizeOfferTemplates(HtmlSanitizerService $sanitizer, bool $dryRun): void
    {
        $this->info('Scanning Offer Templates...');

        $templates = OfferTemplate::whereNotNull('content')->get();
        $changedCount = 0;

        foreach ($templates as $template) {
            $original = $template->content;
            $sanitized = $sanitizer->sanitize($original);

            if ($original !== $sanitized) {
                $changedCount++;
                $this->warn("OfferTemplate #{$template->id} ({$template->name}) content will be sanitized.");

                if ($this->output->isVerbose()) {
                    $this->line("  Original length: " . strlen((string) $original));
                    $this->line("  Sanitized length: " . strlen((string) $sanitized));
                }

                if (! $dryRun) {
                    $template->content = $sanitized;
                    $template->saveQuietly();
                }
            }
        }

        $this->info("Offer Templates: {$changedCount} of {$templates->count()} records " . ($dryRun ? 'would be' : 'were') . ' sanitized.');
    }
}
