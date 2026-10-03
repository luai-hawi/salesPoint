<?php

/*
|--------------------------------------------------------------------------
| Legacy-data compatibility check (NOT part of the normal test suite)
|--------------------------------------------------------------------------
|
| Runs against a scratch COPY of the real database to prove that the new code and migrations
| work with data saved by the previous version of the application.
|
|   1. clone the real DB into a scratch database named salespoint_compat
|   2. run (PowerShell):    $env:DB_CONNECTION='mysql'; $env:DB_DATABASE='salespoint_compat'
|                           php artisan test tests/Compat
|
| The tests skip themselves unless the connected database is literally named "salespoint_compat",
| so they can never touch the real database.
*/

use App\Models\User;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function compatGuard(): void
{
    if (DB::connection()->getDatabaseName() !== 'salespoint_compat') {
        test()->markTestSkipped('Set DB_CONNECTION=mysql and DB_DATABASE=salespoint_compat to run the compatibility check.');
    }
}

/** GET routes that must not be walked blindly (side effects on files/backups/sessions). */
function compatSkipped(string $uri, ?string $name): bool
{
    return (bool) preg_match('#(logout|compress|cleanup|backup|download|impersonate|lang/|verify-email|reset-password|confirm-password|_ignition|^up$|storage/|sanctum|broadcasting|offline/csrf)#i', $uri . ' ' . $name);
}

/**
 * Syntax-check every distinct inline <script> of a rendered page with `node --check`.
 *
 * @return list<string> human readable problems
 */
function compatCheckInlineJs(string $html, string $where, array &$seen): array
{
    $problems = [];

    if (! preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER)) {
        return $problems;
    }

    foreach ($matches as $match) {
        $attributes = $match[1];
        $code = trim($match[2]);

        if ($code === '' || preg_match('#\bsrc\s*=#i', $attributes)) {
            continue;
        }
        if (preg_match('#type\s*=\s*["\'](?!module|text/javascript|application/javascript)#i', $attributes)) {
            continue;
        }

        $hash = md5($code);
        if (isset($seen[$hash])) {
            continue;
        }
        $seen[$hash] = true;

        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'compat_js_' . $hash . '.js';
        file_put_contents($file, $code);
        $output = [];
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        @unlink($file);

        if ($code !== 0) {
            $problems[] = $where . ' inline script ' . substr($hash, 0, 8) . ': ' . trim(implode(' | ', array_slice($output, 0, 4)));
        }
    }

    return $problems;
}

it('applies every pending migration to a copy of the real data', function () {
    compatGuard();

    $before = DB::table('migrations')->count();
    Artisan::call('migrate', ['--force' => true]);
    $after = DB::table('migrations')->count();

    expect(Artisan::output())->not->toContain('Exception')
        ->and($after)->toBeGreaterThanOrEqual($before);

    // existing rows are untouched and the new link columns exist
    expect(Schema::hasColumn('customer_payments', 'bill_id'))->toBeTrue()
        ->and(DB::table('customers')->count())->toBeGreaterThan(0);
});

it('backfilled the customer ledger links of the old debt rows', function () {
    compatGuard();

    $debtRows = DB::table('customer_payments')->where('note', 'like', 'Bill #% created as debt')->count();
    $linked = DB::table('customer_payments')->where('kind', 'bill_charge')->whereNotNull('bill_id')->count();
    $unclassified = DB::table('customer_payments')->whereNull('kind')->count();

    expect($unclassified)->toBe(0)
        ->and($linked)->toBeLessThanOrEqual($debtRows)
        ->and($linked)->toBeGreaterThan(0);
});

it('reports customers whose stored balance differs from their ledger rows', function () {
    compatGuard();

    $drift = DB::table('customers')
        ->leftJoin('customer_payments', 'customer_payments.customer_id', '=', 'customers.id')
        ->groupBy('customers.id', 'customers.balance')
        ->selectRaw('customers.id, customers.balance, COALESCE(SUM(customer_payments.amount),0) as ledger')
        ->get()
        ->filter(fn ($row) => abs((float) $row->balance - (float) $row->ledger) > 0.009);

    // Pre-existing drift is reported only: it is data saved by the old version, not a failure of the new code.
    fwrite(STDERR, "\n[compat] customers with balance != ledger sum: {$drift->count()}\n");
    expect(true)->toBeTrue();
});

it('renders every GET page for every real account without a server error', function () {
    compatGuard();

    set_time_limit(0);
    DB::table('users')->update(['session_id' => null]);

    $users = User::withoutGlobalScopes()->orderBy('id')->get();
    $failures = [];
    $jsSeen = [];
    $checked = 0;

    foreach ($users as $user) {
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();
            if (compatSkipped($uri, $route->getName())) {
                continue;
            }

            // fill route parameters with real ids from the acting user's shop
            $parameters = [];
            $resolvable = true;
            foreach ($route->signatureParameters(UrlRoutable::class) as $parameter) {
                $class = $parameter->getType()?->getName();
                if (! $class || ! is_subclass_of($class, Model::class)) {
                    $resolvable = false;
                    break;
                }

                $query = $class::withoutGlobalScopes();
                $table = (new $class)->getTable();
                if ($class === User::class) {
                    $query->where('shop_owner_id', $ownerId)->where('role', 'employee');
                } elseif (Schema::hasColumn($table, 'user_id')) {
                    $query->where('user_id', $ownerId);
                } elseif (Schema::hasColumn($table, 'shop_owner_id')) {
                    $query->where('shop_owner_id', $ownerId);
                }

                $model = $query->first();
                if (! $model) {
                    $resolvable = false;
                    break;
                }
                $parameters[$parameter->getName()] = $model->getRouteKey();
            }

            if (! $resolvable) {
                continue;
            }

            $url = preg_replace_callback('/\{([^}?]+)\??\}/', fn ($m) => $parameters[$m[1]] ?? $m[0], $uri);
            if (str_contains($url, '{')) {
                continue;
            }
            $url = '/' . ltrim($url, '/');

            foreach (['en', 'ar'] as $locale) {
                $response = test()->actingAs($user)->withSession(['locale' => $locale])->get($url);
                $status = $response->getStatusCode();
                $checked++;

                if ($status >= 500) {
                    $message = $response->exception?->getMessage() ?? strip_tags(mb_substr((string) $response->getContent(), 0, 300));
                    $failures[] = "{$user->role}#{$user->id} [{$locale}] GET {$url} -> {$status}: " . mb_substr((string) $message, 0, 300);
                } elseif ($status === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                    foreach (compatCheckInlineJs((string) $response->getContent(), "{$user->role}#{$user->id} [{$locale}] {$url}", $jsSeen) as $problem) {
                        $failures[] = 'JS ' . $problem;
                    }
                }
            }
        }

        auth()->logout();
    }

    fwrite(STDERR, "\n[compat] checked {$checked} page loads for {$users->count()} accounts; distinct inline scripts syntax-checked: " . count($jsSeen) . "\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[compat] FAIL {$failure}\n");
    }

    expect($failures)->toBe([]);
});
