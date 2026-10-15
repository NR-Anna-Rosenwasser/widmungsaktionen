<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('users:create-super-admin {--email=} {--name=}')]
#[Description('Create (or promote) a super admin who can manage all shops')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->option('email') ?: text('E-Mail', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Ungültige E-Mail-Adresse');
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($email)])->first();

        if (! $user) {
            $user = User::create([
                'name' => $this->option('name') ?: text('Name', required: true),
                'email' => $email,
                'password' => password('Passwort (mind. 12 Zeichen)', required: true, validate: fn ($v) => strlen($v) >= 12 ? null : 'Mindestens 12 Zeichen'),
            ]);
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->info("{$user->email} ist jetzt Super-Admin.");

        return self::SUCCESS;
    }
}
