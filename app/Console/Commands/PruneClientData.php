<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ClientDataEraser;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Scheduled, time-based enforcement of the client-data retention promise (GDPR
 * Art. 5(1)(e) / §13). It complements the on-request `atelier:forget-client`: a
 * passwordless Client is erased once every project they commented on has gone
 * inactive (archived or past its share-link expiry) AND their most recent comment
 * is older than `atelier.privacy.retention_days`. A Client still active on any live
 * project is never touched.
 *
 * Disabled by default (retention_days === null): erasure stays on-request only,
 * which is the recorded posture. Set the window to switch time-based pruning on.
 */
class PruneClientData extends Command
{
    protected $signature = 'atelier:prune-client-data {--dry-run : List who would be erased without erasing}';

    protected $description = 'Erase Client PII whose projects are all inactive and whose last comment is past the retention window.';

    public function handle(ClientDataEraser $eraser): int
    {
        $days = config('atelier.privacy.retention_days');

        if ($days === null) {
            $this->info('Time-based client-data retention is disabled (atelier.privacy.retention_days is null); erasure is on request only.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $days);
        $dryRun = (bool) $this->option('dry-run');

        $clients = $this->erasableClients($cutoff);

        if ($clients->isEmpty()) {
            $this->info('No Clients are past the retention window on fully inactive projects.');

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            if ($dryRun) {
                $this->line("[dry-run] would erase {$client->name} <{$client->email}>");

                continue;
            }

            $summary = $eraser->erase($client);
            $this->info("Erased {$client->name} <{$client->email}> and redacted {$summary['comments']} comment(s).");
        }

        $this->info(($dryRun ? 'Would erase ' : 'Erased ').$clients->count().' Client(s).');

        return self::SUCCESS;
    }

    /**
     * Clients whose latest comment predates the cutoff and who have no comment on a
     * still-active project. Already-anonymised Clients are skipped so re-runs are cheap.
     *
     * @return Collection<int, User>
     */
    private function erasableClients(CarbonInterface $cutoff): Collection
    {
        return User::query()
            ->where('role', UserRole::Client)
            ->where('email', 'not like', '%@atelier.invalid')
            ->whereHas('comments')
            ->whereDoesntHave('comments', fn ($query) => $query->where('created_at', '>=', $cutoff))
            ->whereDoesntHave('comments.artifact.project', function ($query): void {
                $query->where('status', ProjectStatus::Active)
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->get();
    }
}
