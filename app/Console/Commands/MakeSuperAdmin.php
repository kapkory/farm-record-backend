<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-superadmin {email : The email of the user to promote} {--revoke : Remove superadmin instead}';

    protected $description = 'Grant (or revoke) platform superadmin access for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->update(['is_superadmin' => $grant]);

        $this->info($grant
            ? "{$user->email} is now a platform superadmin."
            : "Superadmin access removed from {$user->email}.");

        return self::SUCCESS;
    }
}
