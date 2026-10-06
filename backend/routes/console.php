<?php

use App\Services\OrderService;
use App\Services\ProductSearchIndexer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:cancel-unpaid', function (OrderService $orders) {
    $n = $orders->cancelStaleUnpaid();
    $this->info("Cancelled {$n} unpaid order(s).");
})->purpose('Cancel online-payment orders that were never paid and release their stock');

Artisan::command('products:reindex', function (ProductSearchIndexer $indexer) {
    $this->info('Indexed '.$indexer->reindexAll().' products.');
})->purpose('Rebuild the product search keyword index');

/** Strong enough for a staff account that can see every order and customer. */
$staffPasswordError = function (string $password): ?string {
    if (strlen($password) < 10) {
        return 'Use at least 10 characters.';
    }
    if (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
        return 'Use letters and numbers.';
    }
    if (in_array(strtolower($password), ['password', 'password1', 'password12', 'password123', 'motogears1', 'admin12345'], true)) {
        return 'That password is too common.';
    }

    return null;
};

Artisan::command('app:create-admin {email} {--name=Store Owner} {--password=}', function (string $email) use ($staffPasswordError) {
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('That is not a valid email address.');

        return 1;
    }
    $password = $this->option('password') ?: $this->secret('Password for '.$email);
    if ($error = $staffPasswordError((string) $password)) {
        $this->error($error);

        return 1;
    }
    $user = \App\Models\User::firstOrNew(['email' => $email]);
    $user->fill(['name' => $user->exists ? $user->name : $this->option('name'), 'password' => $password]);
    $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'is_active' => true])->save();
    $user->assignRole(\App\Models\Role::SUPER_ADMIN);
    $user->tokens()->delete();
    $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' super admin '.$email.'. Sign in at /admin.');

    return 0;
})->purpose('Create a super admin (or make an existing user one) with a strong password');

Artisan::command('app:set-password {email} {--password=}', function (string $email) use ($staffPasswordError) {
    $user = \App\Models\User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with email {$email}.");

        return 1;
    }
    $password = $this->option('password') ?: $this->secret('New password for '.$email);
    if ($user->isStaff() && ($error = $staffPasswordError((string) $password))) {
        $this->error($error);

        return 1;
    }
    $user->forceFill(['password' => $password])->save();
    $user->tokens()->delete();   // signs the user out everywhere
    $this->info("Password changed for {$email}; existing sessions were signed out.");

    return 0;
})->purpose("Change a user's password and sign them out everywhere");

Artisan::command('app:remove-demo-staff', function () {
    $emails = ['admin@example.com', 'manager@example.com', 'catalog@example.com', 'orders@example.com', 'inventory@example.com', 'content@example.com'];
    $users = \App\Models\User::whereIn('email', $emails)->get();
    foreach ($users as $user) {
        $user->tokens()->delete();
        $user->roles()->detach();
        $user->forceFill(['is_active' => false, 'password' => \Illuminate\Support\Str::random(40)])->save();
    }
    $this->info('Disabled '.$users->count().' demo staff account(s). Make sure you have your own admin first (app:create-admin).');
})->purpose('Lock the demo staff logins (admin@example.com etc.) on a live server');

Artisan::command('product-imports:prune', function () {
    $days = (int) config('imports.keep_files_days', 30);
    $n = 0;
    \App\Models\ProductImport::where('created_at', '<', now()->subDays($days))->where('file_path', '!=', '')
        ->whereNotIn('status', \App\Models\ProductImport::ACTIVE)
        ->each(function ($import) use (&$n) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($import->file_path);
            $import->forceFill(['file_path' => ''])->save();
            $n++;
        });
    $this->info("Removed {$n} old import file(s).");
})->purpose('Delete uploaded product import files older than imports.keep_files_days (history is kept)');

Schedule::command('orders:cancel-unpaid')->everyTenMinutes()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('product-imports:prune')->daily();
