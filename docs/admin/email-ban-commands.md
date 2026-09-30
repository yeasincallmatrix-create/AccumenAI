# Email Ban Admin Commands

Ban checks run **before user lookup** in Google OAuth, password login, and
registration — a banned email is blocked even if the user row was hard-deleted.
Matching is case-insensitive (emails are normalized on write and on check).

## Ban permanently

```bash
php artisan tinker --execute="
app(App\Services\Auth\EmailBanService::class)->ban(
    'spammer@example.com', 'Repeated fraud', auth()->id()
);
"
```

## Ban temporarily (30 days)

```bash
php artisan tinker --execute="
app(App\Services\Auth\EmailBanService::class)->ban(
    'temp@example.com', 'Suspended', null, false, now()->addDays(30)
);
"
```

## Unban

```bash
php artisan tinker --execute="
app(App\Services\Auth\EmailBanService::class)->unban('spammer@example.com');
"
```

## Check status

```bash
php artisan tinker --execute="
var_dump(app(App\Services\Auth\EmailBanService::class)->isBanned('test@example.com'));
"
```

## List active bans

```bash
php artisan tinker --execute="
App\Models\BannedEmail::active()->get(['email','reason','banned_at'])
    ->each(fn(\$b) => print(\$b->email . PHP_EOL));
"
```

## Ban + force-delete user (combined)

```bash
php artisan tinker --execute="
\$u = App\Models\User::withTrashed()->find(123);
if (\$u) {
    app(App\Services\Auth\EmailBanService::class)->ban(\$u->email, 'Deleted by admin');
    \$u->forceDelete();
    echo 'Done.' . PHP_EOL;
}
"
```

The ban row survives `forceDelete()` — re-registration with the same email
stays blocked until `unban()` removes the row.
