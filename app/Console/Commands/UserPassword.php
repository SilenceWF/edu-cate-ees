<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;

class UserPassword extends Command
{
    protected $signature = 'user:password {email} {password? : Leave out to be prompted}';

    protected $description = 'Set the local-login password of a user (for ENABLE_LOCAL_AUTH)';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }
        $password = $this->argument('password') ?? $this->secret('New password');
        if (strlen((string) $password) < 8) {
            $this->error('Use at least 8 characters.');

            return self::FAILURE;
        }
        $user->update(['password' => $password]);
        Audit::log('user.password_set', $user);
        $this->info('Password updated for '.$user->name.'.');

        return self::SUCCESS;
    }
}
