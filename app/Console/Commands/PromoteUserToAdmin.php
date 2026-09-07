<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literahaven:promote-admin {email : Email address of the account to promote}')]
#[Description('Promote an existing Literahaven account to the administrator role')]
class PromoteUserToAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No account was found for {$email}.");

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info("{$user->email} is already an administrator.");

            return self::SUCCESS;
        }

        $user->update(['role' => User::ROLE_ADMIN]);
        $this->info("{$user->email} can now access the moderation dashboard.");

        return self::SUCCESS;
    }
}
