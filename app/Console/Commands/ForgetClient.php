<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ClientDataEraser;
use Illuminate\Console\Command;

/**
 * Operator-run fulfilment of a Client's erasure request (GDPR Art. 17). Given the
 * Client's email, anonymises their identity and redacts their authored comments via
 * ClientDataEraser, preserving Thread structure. Confirms before acting unless --force.
 */
class ForgetClient extends Command
{
    protected $signature = 'atelier:forget-client
        {email : The email address of the Client to erase}
        {--force : Skip the confirmation prompt}';

    protected $description = "Erase a Client's personal data (anonymise identity, redact their comments) on request.";

    public function handle(ClientDataEraser $eraser): int
    {
        $client = User::where('email', $this->argument('email'))
            ->where('role', UserRole::Client)
            ->first();

        if ($client === null) {
            $this->error('No Client found with that email.');

            return self::FAILURE;
        }

        $commentCount = $client->comments()->count();

        if (! $this->option('force') && ! $this->confirm(
            "Erase {$client->name} <{$client->email}> and redact {$commentCount} comment(s)? This cannot be undone.",
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // The query above already restricts $client to a Client, which is the only
        // role ClientDataEraser will erase — so no exception can arise on this path.
        $summary = $eraser->erase($client);

        $this->info("Erased the Client's personal data and redacted {$summary['comments']} comment(s).");

        return self::SUCCESS;
    }
}
