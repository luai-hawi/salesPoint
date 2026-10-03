<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Middleware\StaffPortalAuth;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\User;
use App\Services\Attendance\AttendanceException;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\Geofence;
use App\Services\WebAuthn\WebAuthnException;
use App\Services\WebAuthn\WebAuthnService;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortalController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9Y8l1IYf4oFQ1MquYq2WGa';

    private const LOGIN_ATTEMPTS = 5;

    private const LOGIN_THROTTLE_SECONDS = 60;

    private const LOGIN_OWNER_IP_ATTEMPTS = 10;

    private const LOGIN_IP_ATTEMPTS = 20;

    private const LOGIN_NONCE_TTL_MINUTES = 30;

    private const PUNCH_IDEMPOTENCY_TTL_HOURS = 24;

    public function __construct(
        protected AttendanceService $attendance,
        protected Geofence $geofence,
        protected WebAuthnService $webAuthn,
    ) {
    }

    public function portal(Request $request, string $key): View
    {
        $owner = $this->owner($request);
        $state = $this->portalState($request, $owner);

        return view('staff.portal', [
            'owner' => $owner,
            'boot' => [
                'portalKey' => $key,
                'locale' => app()->getLocale(),
                'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
                'routes' => [
                    'login' => route('staff.login', $key),
                    'logout' => route('staff.logout', $key),
                    'state' => route('staff.state', $key),
                    'punch' => route('staff.punch', $key),
                    'history' => route('staff.history', $key),
                    'manifest' => route('staff.manifest', $key),
                    'langAr' => route('staff.lang', ['key' => $key, 'locale' => 'ar']),
                    'langEn' => route('staff.lang', ['key' => $key, 'locale' => 'en']),
                    'registerOptions' => route('staff.webauthn.register.options', $key),
                    'registerVerify' => route('staff.webauthn.register.verify', $key),
                    'authOptions' => route('staff.webauthn.auth.options', $key),
                ],
                'state' => $state,
                'strings' => [
                    'loading' => __('staff.ui.loading'),
                    'locationPending' => __('staff.location.pending'),
                    'locationDenied' => __('staff.location.denied'),
                    'locationUnavailable' => __('staff.location.unavailable'),
                    'locationTimeout' => __('staff.location.timeout'),
                    'locationUnsupported' => __('staff.location.unsupported'),
                    'secureContextRequired' => __('staff.location.secure_context_required'),
                    'inside' => __('staff.location.inside'),
                    'outside' => __('staff.location.outside'),
                    'noConnection' => __('staff.ui.no_connection'),
                    'queuedOffline' => __('staff.messages.queued_offline'),
                    'syncing' => __('staff.ui.syncing'),
                    'biometricUnsupported' => __('staff.webauthn.unsupported'),
                    'offlineCheckoutReason' => __('staff.messages.offline_checkout_reason'),
                ],
            ],
        ]);
    }

    public function login(Request $request, string $key): JsonResponse
    {
        $owner = $this->owner($request);
        $settings = AttendanceService::settingsForOwner($owner);
        if (! StaffPortalAuth::ownerIsAvailable($owner) || ! ($settings['enabled'] ?? false)) {
            return response()->json(['message' => __('staff.errors.login_failed')], 422);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:255'],
            'login_nonce' => ['required', 'string', 'max:120'],
        ]);

        $this->ensureSecurePortalRequest($request);
        $this->ensureSameOriginRequest($request);

        $rateKeys = $this->loginRateKeys($request, $owner, $data['username']);
        $this->ensureLoginNonceValid($owner, $data['login_nonce']);
        $this->ensureLoginNotRateLimited($rateKeys);

        $employee = Employee::query()
            ->where('shop_owner_id', $owner->id)
            ->where('username', $data['username'])
            ->where('portal_enabled', true)
            ->where('is_active', true)
            ->first();

        $passwordHash = is_string($employee?->password) && $employee->password !== ''
            ? $employee->password
            : self::DUMMY_PASSWORD_HASH;
        $passwordMatches = Hash::check($data['password'], $passwordHash);
        $valid = $employee && $passwordMatches;

        if (! $valid) {
            $this->hitLoginRateLimit($rateKeys);

            return response()->json(['message' => __('staff.errors.login_failed')], 422);
        }

        $this->clearLoginRateLimit($rateKeys);

        $token = Str::random(64);
        $device = EmployeeDevice::query()->create([
            'user_id' => $owner->id,
            'employee_id' => $employee->id,
            'token_hash' => StaffPortalAuth::hashToken($token),
            'label' => $this->deviceLabel($request->userAgent()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
        ]);

        $employee->forceFill(['last_portal_login_at' => now()])->save();
        $request->attributes->set('staffEmployee', $employee);
        $request->attributes->set('staffDevice', $device);

        $state = $this->portalState($request, $owner);

        return response()->json([
            'message' => __('staff.messages.login_success'),
            'state' => $state,
        ])->withCookie(StaffPortalAuth::makeCookie($request, $owner, $token));
    }

    public function logout(Request $request, string $key): JsonResponse
    {
        $this->ensurePortalWriteAllowed($request);
        $device = $this->device($request);

        $device->forceFill(['revoked_at' => now()])->save();

        return response()->json(['message' => __('staff.messages.logout_success')])
            ->withCookie(cookie()->forget(StaffPortalAuth::cookieName($this->owner($request))));
    }

    public function state(Request $request, string $key): JsonResponse
    {
        $owner = $this->owner($request);
        return response()->json($this->portalState($request, $owner));
    }

    public function punch(Request $request, string $key): JsonResponse
    {
        $this->ensurePortalWriteAllowed($request);
        $employee = $this->employee($request);
        $device = $this->device($request);
        $owner = $this->owner($request);

        $rateKey = 'staff-punch:' . $owner->id . ':' . $device->id;
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            return response()->json([
                'message' => __('staff.errors.too_many_requests'),
                'retry_after' => RateLimiter::availableIn($rateKey),
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $payload = $request->validate([
            'action' => ['required', 'string', 'in:check_in,check_out'],
            'request_id' => ['required', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'accuracy' => ['nullable'],
            'claimed_time' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'client_time' => ['nullable', 'string'],
            'offline' => ['nullable', 'boolean'],
            'challenge_id' => ['nullable', 'string'],
            'credential' => ['nullable', 'array'],
        ]);

        if ($replay = $this->replayedPunchResponse($device->id, $payload['request_id'])) {
            return $replay;
        }

        $expectedAction = $this->attendance->openRecord($employee) ? 'check_out' : 'check_in';
        if ($payload['action'] !== $expectedAction) {
            $response = response()->json([
                'message' => __('staff.errors.action_out_of_date'),
                'code' => 'action_out_of_date',
                'expected_action' => $expectedAction,
            ], 409);

            $this->storePunchResponse($device->id, $payload['request_id'], $response);

            return $response;
        }

        try {
            $ctx = [
                'lat' => $payload['lat'] ?? null,
                'lng' => $payload['lng'] ?? null,
                'accuracy' => $payload['accuracy'] ?? null,
                'device_id' => $device->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'claimed_time' => $payload['claimed_time'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'client_time' => $payload['client_time'] ?? null,
                'offline' => (bool) ($payload['offline'] ?? false),
                'method' => 'manual',
            ];

            if (! empty($payload['challenge_id']) && ! empty($payload['credential'])) {
                $this->webAuthn->verifyAssertion(
                    $employee,
                    (string) $payload['challenge_id'],
                    $payload['credential'],
                    $this->rpId($request),
                    $this->origin($request),
                );

                $ctx['method'] = 'biometric';
                $ctx['biometric_verified'] = true;
            }

            $record = $expectedAction === 'check_out'
                ? $this->attendance->checkOut($employee, $ctx)
                : $this->attendance->checkIn($employee, $ctx);
        } catch (AttendanceException|WebAuthnException $e) {
            $response = response()->json([
                'message' => __('staff.errors.' . ($e instanceof AttendanceException ? $e->errorCode() : $e->errorCode())),
                'code' => $e instanceof AttendanceException ? $e->errorCode() : $e->errorCode(),
            ], 422);

            $this->storePunchResponse($device->id, $payload['request_id'], $response);

            return $response;
        }

        $response = response()->json([
            'message' => $record->check_out_at
                ? __('staff.messages.check_out_success')
                : __('staff.messages.check_in_success'),
            'state' => $this->portalState($request, $owner),
        ]);

        $this->storePunchResponse($device->id, $payload['request_id'], $response);

        return $response;
    }

    public function history(Request $request, string $key): View|RedirectResponse
    {
        $employee = $request->attributes->get('staffEmployee');
        if (! $employee instanceof Employee) {
            return redirect()->route('staff.portal', $key);
        }

        $owner = $this->owner($request);
        $tz = ShopTime::timezone($owner);
        $showHours = (bool) (AttendanceService::settingsForOwner($owner)['show_hours_to_staff'] ?? true);
        $records = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $owner->id)
            ->where('employee_id', $employee->id)
            ->latest('check_in_at')
            ->paginate(25)
            ->through(function (AttendanceRecord $record) use ($tz, $showHours) {
                return [
                    'work_date' => $record->work_date?->toDateString(),
                    'check_in' => $record->check_in_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                    'check_out' => $record->check_out_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                    'hours' => $showHours ? $this->formatMinutes($record->minutes_worked) : null,
                    'status' => $record->status,
                    'remote' => $record->check_out_remote,
                    'reason' => $record->reason,
                ];
            });

        return view('staff.history', [
            'owner' => $owner,
            'employee' => $employee,
            'records' => $records,
            'portalUrl' => route('staff.portal', $key),
        ]);
    }

    public function manifest(Request $request, string $key): JsonResponse
    {
        $owner = $this->owner($request);

        return response()->json([
            'name' => __('staff.pwa.name', ['shop' => $owner->name]),
            'short_name' => __('staff.pwa.short_name'),
            'start_url' => route('staff.portal', $key),
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#4f46e5',
            'icons' => [
                ['src' => asset('images/logo.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/logo4.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ]);
    }

    public function lang(Request $request, string $key, string $locale): RedirectResponse
    {
        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
            session(['locale' => $locale]);
            cookie()->queue('locale', $locale, 60 * 24 * 365);
        }

        return redirect()->to($this->validatedStaffRedirect($request, $key));
    }

    protected function owner(Request $request): User
    {
        $owner = $request->attributes->get('staffOwner');
        if (! $owner instanceof User) {
            abort(404);
        }

        return $owner;
    }

    protected function employee(Request $request): Employee
    {
        $employee = $request->attributes->get('staffEmployee');
        if (! $employee instanceof Employee) {
            throw ValidationException::withMessages(['message' => __('staff.errors.auth_required')]);
        }

        return $employee;
    }

    protected function device(Request $request): EmployeeDevice
    {
        $device = $request->attributes->get('staffDevice');
        if (! $device instanceof EmployeeDevice) {
            throw ValidationException::withMessages(['message' => __('staff.errors.auth_required')]);
        }

        return $device;
    }

    protected function ensurePortalWriteAllowed(Request $request): void
    {
        $this->ensureSecurePortalRequest($request);
        $employee = $request->attributes->get('staffEmployee');
        $device = $request->attributes->get('staffDevice');

        if (! $employee instanceof Employee || ! $device instanceof EmployeeDevice) {
            abort(response()->json(['message' => __('staff.errors.auth_required')], 401));
        }

        $origin = $request->header('Origin');
        $expectedOrigin = $this->origin($request);
        if (is_string($origin) && $origin !== '' && ! hash_equals($expectedOrigin, $origin)) {
            abort(response()->json(['message' => __('staff.errors.same_origin_required')], 403));
        }

        $fetchSite = strtolower((string) $request->header('Sec-Fetch-Site', 'same-origin'));
        if (! in_array($fetchSite, ['same-origin', 'same-site', 'none', ''], true)) {
            abort(response()->json(['message' => __('staff.errors.same_origin_required')], 403));
        }

        $provided = (string) $request->header(StaffPortalAuth::CSRF_HEADER, '');
        $expected = StaffPortalAuth::csrfForTokenHash($device->token_hash);
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(response()->json(['message' => __('staff.errors.invalid_header_token')], 403));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function portalState(Request $request, User $owner): array
    {
        $settings = AttendanceService::settingsForOwner($owner);
        $employee = $request->attributes->get('staffEmployee');
        $device = $request->attributes->get('staffDevice');
        $securePortal = StaffPortalAuth::isSecurePortalRequest($request);
        $portalAvailable = StaffPortalAuth::ownerIsAvailable($owner)
            && (bool) ($settings['enabled'] ?? false)
            && $securePortal;

        $this->maybeCloseStale($owner);

        $state = [
            'shop' => ['name' => $owner->name],
            'attendance_enabled' => (bool) ($settings['enabled'] ?? false),
            'portal_available' => $portalAvailable,
            'authenticated' => $employee instanceof Employee && $device instanceof EmployeeDevice,
            'secure_context' => $securePortal,
            'history_url' => route('staff.history', $owner->staff_portal_key),
            'manifest_url' => route('staff.manifest', $owner->staff_portal_key),
            'unavailable_message' => ! $securePortal
                ? __('staff.errors.secure_context_required_server')
                : (! StaffPortalAuth::ownerIsAvailable($owner) ? __('staff.errors.portal_unavailable') : null),
        ];

        if (! ($employee instanceof Employee) || ! ($device instanceof EmployeeDevice)) {
            return $state + [
                'login_nonce' => $portalAvailable ? $this->issueLoginNonce($owner) : null,
                'settings' => [
                    'show_hours_to_staff' => (bool) ($settings['show_hours_to_staff'] ?? true),
                    'show_pay_to_staff' => (bool) ($settings['show_pay_to_staff'] ?? false),
                ],
            ];
        }

        $geo = null;
        if ($request->query('lat') !== null || $request->query('lng') !== null) {
            try {
                $lat = $request->query('lat') !== null ? (float) $request->query('lat') : null;
                $lng = $request->query('lng') !== null ? (float) $request->query('lng') : null;
                $accuracy = $request->query('accuracy') !== null ? (float) $request->query('accuracy') : null;
                $geo = $this->geofence->evaluate($owner->id, $lat, $lng, $accuracy);
                $geo['accuracy'] = $accuracy !== null ? (int) round($accuracy) : null;
            } catch (\Throwable) {
                $geo = null;
            }
        }

        $today = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $owner->id)
            ->where('employee_id', $employee->id)
            ->where('work_date', ShopTime::today($owner))
            ->latest('check_in_at')
            ->first();

        $monthRange = ShopTime::utcRange(
            Carbon::now(ShopTime::timezone($owner))->startOfMonth()->toDateString(),
            Carbon::now(ShopTime::timezone($owner))->endOfMonth()->toDateString(),
            $owner
        );

        $monthRecords = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $owner->id)
            ->where('employee_id', $employee->id)
            ->whereBetween('check_in_at', $monthRange)
            ->get();

        $monthMinutes = (int) $monthRecords->sum(fn (AttendanceRecord $record) => (int) ($record->minutes_worked ?? 0));
        $showHours = (bool) ($settings['show_hours_to_staff'] ?? true);
        $showPay = (bool) ($settings['show_pay_to_staff'] ?? false);
        $monthPay = $showPay ? $this->monthPay($employee, $monthRecords, $monthMinutes) : null;

        $recent = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $owner->id)
            ->where('employee_id', $employee->id)
            ->latest('check_in_at')
            ->limit(7)
            ->get()
            ->map(fn (AttendanceRecord $record) => $this->recordSummary($record, $owner, $showHours))
            ->values()
            ->all();

        $openRecord = $this->attendance->openRecord($employee);

        return $state + [
            'csrf' => StaffPortalAuth::csrfForTokenHash($device->token_hash),
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->job_title,
                'biometric_required' => $employee->biometric_required ?? (bool) ($settings['biometric_required'] ?? false),
                'credentials_count' => $employee->credentials()->count(),
            ],
            'device' => [
                'id' => $device->id,
            ],
            'settings' => [
                'allow_remote_checkout' => (bool) ($settings['allow_remote_checkout'] ?? true),
                'allow_remote_checkin' => (bool) ($settings['allow_remote_checkin'] ?? false),
                'show_hours_to_staff' => $showHours,
                'show_pay_to_staff' => $showPay,
                'show_biometric_setup' => true,
            ],
            'attendance' => [
                'status' => $this->todayStatus($today, $openRecord),
                'status_label' => __('staff.status.' . $this->todayStatus($today, $openRecord)),
                'button' => [
                    'action' => $openRecord ? 'check_out' : 'check_in',
                    'label' => $openRecord ? __('staff.actions.check_out') : __('staff.actions.check_in'),
                ],
                'today' => $today ? $this->recordSummary($today, $owner, $showHours) : null,
                'live_since' => $openRecord?->check_in_at?->toIso8601String(),
            ],
            'stats' => [
                'month_minutes' => $showHours ? $monthMinutes : null,
                'month_hours' => $showHours ? $this->formatMinutes($monthMinutes) : null,
                'month_pay' => $showPay && $monthPay !== null ? number_format($monthPay, 2) : null,
            ],
            'recent_days' => $recent,
            'geofence' => $geo,
        ];
    }

    private function recordSummary(AttendanceRecord $record, User $owner, bool $showHours = true): array
    {
        $timezone = ShopTime::timezone($owner);
        $checkIn = $record->check_in_at?->copy()->setTimezone($timezone);
        $checkOut = $record->check_out_at?->copy()->setTimezone($timezone);

        return [
            'work_date' => $record->work_date?->toDateString(),
            'day_label' => $record->work_date?->copy()->locale(app()->getLocale())->translatedFormat('D d M'),
            'status' => $record->status,
            'status_label' => __('staff.status.' . $record->status),
            'flagged' => $record->status === 'needs_review',
            'hours' => $showHours ? $this->formatMinutes($record->minutes_worked) : null,
            'check_in_time' => $checkIn?->format('H:i'),
            'check_out_time' => $checkOut?->format('H:i'),
            'remote' => (bool) $record->check_out_remote,
            'remote_label' => $record->check_out_remote ? __('staff.ui.remote_checkout') : null,
            'reason' => $record->reason,
        ];
    }

    private function maybeCloseStale(User $owner): void
    {
        if (! StaffPortalAuth::ownerIsAvailable($owner)) {
            return;
        }

        $key = 'staff-close-stale:' . $owner->id;
        if (cache()->add($key, true, now()->addMinutes(15))) {
            $this->attendance->closeStaleForOwner($owner);
        }
    }

    private function todayStatus(?AttendanceRecord $today, ?AttendanceRecord $openRecord): string
    {
        if ($openRecord) {
            return 'open';
        }

        if ($today && $today->check_out_at) {
            return $today->status;
        }

        return 'none';
    }

    private function monthPay(Employee $employee, $monthRecords, int $monthMinutes): ?float
    {
        return match ($employee->salary_type) {
            'hourly' => $employee->hourly_rate !== null ? round(($monthMinutes / 60) * (float) $employee->hourly_rate, 2) : null,
            'daily' => $employee->daily_rate !== null ? round($monthRecords->where('minutes_worked', '>', 0)->count() * (float) $employee->daily_rate, 2) : null,
            default => $employee->monthly_salary !== null ? (float) $employee->monthly_salary : null,
        };
    }

    private function formatMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = intdiv(max(0, $minutes), 60);
        $remaining = max(0, $minutes) % 60;

        return sprintf('%02d:%02d', $hours, $remaining);
    }

    private function deviceLabel(?string $userAgent): string
    {
        $label = trim((string) $userAgent);

        return mb_substr($label !== '' ? $label : __('staff.ui.this_device'), 0, 120);
    }

    /**
     * @return array<string,string>
     */
    private function loginRateKeys(Request $request, User $owner, string $username): array
    {
        return [
            'username' => 'staff-login:' . $owner->id . ':' . Str::transliterate(Str::lower($username) . '|' . $request->ip()),
            'owner_ip' => 'staff-login-owner:' . $owner->id . ':' . $request->ip(),
            'ip' => 'staff-login-ip:' . $request->ip(),
        ];
    }

    /**
     * @param array<string,string> $rateKeys
     */
    private function ensureLoginNotRateLimited(array $rateKeys): void
    {
        $lockedUntil = max(array_map(
            fn (string $rateKey) => (int) cache()->get($rateKey . ':lock', 0),
            array_values($rateKeys)
        ));
        if ($lockedUntil > time()) {
            throw ValidationException::withMessages([
                'username' => [__('staff.errors.login_throttle', ['seconds' => $lockedUntil - time()])],
            ]);
        }

        $thresholds = [
            'username' => self::LOGIN_ATTEMPTS,
            'owner_ip' => self::LOGIN_OWNER_IP_ATTEMPTS,
            'ip' => self::LOGIN_IP_ATTEMPTS,
        ];

        foreach ($rateKeys as $name => $rateKey) {
            if (RateLimiter::tooManyAttempts($rateKey, $thresholds[$name])) {
                throw ValidationException::withMessages([
                    'username' => [__('staff.errors.login_throttle', ['seconds' => RateLimiter::availableIn($rateKey)])],
                ]);
            }
        }
    }

    /**
     * @param array<string,string> $rateKeys
     */
    private function hitLoginRateLimit(array $rateKeys): void
    {
        $thresholds = [
            'username' => self::LOGIN_ATTEMPTS,
            'owner_ip' => self::LOGIN_OWNER_IP_ATTEMPTS,
            'ip' => self::LOGIN_IP_ATTEMPTS,
        ];

        foreach ($rateKeys as $name => $rateKey) {
            RateLimiter::hit($rateKey, self::LOGIN_THROTTLE_SECONDS);
            $failures = cache()->increment($rateKey . ':failures');
            cache()->put($rateKey . ':failures', $failures, now()->addMinutes(30));

            if ($failures > $thresholds[$name]) {
                $seconds = min(900, 60 * ($failures - $thresholds[$name] + 1));
                cache()->put($rateKey . ':lock', time() + $seconds, now()->addSeconds($seconds));
            }
        }
    }

    /**
     * @param array<string,string> $rateKeys
     */
    private function clearLoginRateLimit(array $rateKeys): void
    {
        foreach ($rateKeys as $rateKey) {
            RateLimiter::clear($rateKey);
            cache()->forget($rateKey . ':lock');
            cache()->forget($rateKey . ':failures');
        }
    }

    private function ensureSecurePortalRequest(Request $request): void
    {
        if (! StaffPortalAuth::isSecurePortalRequest($request)) {
            abort(response()->json([
                'message' => __('staff.errors.secure_context_required_server'),
                'code' => 'secure_context_required_server',
            ], 403));
        }
    }

    private function ensureSameOriginRequest(Request $request): void
    {
        $origin = $request->header('Origin');
        $expectedOrigin = $this->origin($request);
        if (is_string($origin) && $origin !== '' && ! hash_equals($expectedOrigin, $origin)) {
            throw ValidationException::withMessages([
                'username' => [__('staff.errors.same_origin_required')],
            ]);
        }

        $fetchSite = strtolower((string) $request->header('Sec-Fetch-Site', ''));
        if ($fetchSite !== '' && ! in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
            throw ValidationException::withMessages([
                'username' => [__('staff.errors.same_origin_required')],
            ]);
        }
    }

    private function issueLoginNonce(User $owner): string
    {
        $nonce = Str::random(48);
        cache()->put($this->loginNonceCacheKey($owner, $nonce), true, now()->addMinutes(self::LOGIN_NONCE_TTL_MINUTES));

        return $nonce;
    }

    private function ensureLoginNonceValid(User $owner, string $nonce): void
    {
        if (! cache()->has($this->loginNonceCacheKey($owner, $nonce))) {
            throw ValidationException::withMessages([
                'username' => [__('staff.errors.invalid_login_nonce')],
            ]);
        }
    }

    private function loginNonceCacheKey(User $owner, string $nonce): string
    {
        return 'staff-login-nonce:' . $owner->id . ':' . hash('sha256', $nonce);
    }

    private function replayedPunchResponse(int $deviceId, string $requestId): ?JsonResponse
    {
        $cached = cache()->get($this->punchIdempotencyKey($deviceId, $requestId));
        if (! is_array($cached) || ! isset($cached['status'], $cached['body']) || ! is_array($cached['body'])) {
            return null;
        }

        return response()->json($cached['body'], (int) $cached['status'], ['X-Idempotent-Replay' => 'true']);
    }

    private function storePunchResponse(int $deviceId, string $requestId, JsonResponse $response): void
    {
        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return;
        }

        cache()->put(
            $this->punchIdempotencyKey($deviceId, $requestId),
            ['status' => $response->getStatusCode(), 'body' => $payload],
            now()->addHours(self::PUNCH_IDEMPOTENCY_TTL_HOURS)
        );
    }

    private function punchIdempotencyKey(int $deviceId, string $requestId): string
    {
        return 'staff-punch-idempotency:' . $deviceId . ':' . hash('sha256', $requestId);
    }

    protected function rpId(Request $request): string
    {
        return $request->getHost();
    }

    protected function origin(Request $request): string
    {
        return StaffPortalAuth::effectiveOrigin($request);
    }

    private function validatedStaffRedirect(Request $request, string $key): string
    {
        $portalUrl = $this->origin($request) . route('staff.portal', $key, false);
        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer === '') {
            return $portalUrl;
        }

        $parts = parse_url($referer);
        if (! is_array($parts)) {
            return $portalUrl;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $requestHost = strtolower($request->getHost());
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $host !== $requestHost) {
            return $portalUrl;
        }

        $path = '/' . ltrim((string) ($parts['path'] ?? '/'), '/');
        $allowedPrefix = rtrim(route('staff.portal', $key, false), '/');
        if ($path !== $allowedPrefix && ! str_starts_with($path, $allowedPrefix . '/')) {
            return $portalUrl;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '') . $path . $query;
    }
}
