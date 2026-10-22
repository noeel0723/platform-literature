<?php

namespace App\Console\Commands;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CreateAdminAccount extends Command
{
    protected $signature = 'accounts:create-admin';

    protected $description = 'Interactively create a new admin who must verify their email before using protected features';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Create admins interactively so passwords are never passed in command arguments.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Name'),
            'username' => strtolower(trim((string) $this->ask('Username'))),
            'email' => strtolower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, (new RegisterRequest)->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            ...collect($validator->validated())->only(['name', 'username', 'email', 'password'])->all(),
            'role' => User::ROLE_ADMIN,
        ]);

        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $this->warn('Admin created, but email delivery failed. Log in and resend verification after fixing SMTP.');

            return self::FAILURE;
        }

        $this->info('Admin created. Verify the email address before accessing admin features.');

        return self::SUCCESS;
    }
}
