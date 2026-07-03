<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('central:make-super-admin {email} {--password=} {--name=Super Admin}', function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $email = (string) $this->argument('email');
    $name = (string) $this->option('name');
    $password = (string) ($this->option('password') ?: Str::password(16));

    $user = User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => Hash::make($password),
        ]
    );

    $this->info('Central super admin siap.');
    $this->line('Email: ' . $user->email);
    $this->line('Password: ' . $password);
})->purpose('Create/update central super admin user (central guard)');
