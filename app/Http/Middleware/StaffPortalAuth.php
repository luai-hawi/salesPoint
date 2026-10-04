<?php

namespace App\Http\Middleware;

use App\Models\EmployeeDevice;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class StaffPortalAuth
{
    public const CSRF_HEADER = 'X-Staff-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $owner = User::withoutGlobalScopes()
            ->where('staff_portal_key', $request->route('key'))
            ->first();

        if (! $owner) {
            abort(404);
        }

        abort_unless($owner->canAccessFeature('hr'), 403, __('messages.tier_feature_blocked'));

        $request->attributes->set('staffOwner', $owner);

        if (! self::ownerIsAvailable($owner) || ! self::isSecurePortalRequest($request)) {
            return $next($request);
        }

        $token = $this->resolveToken($request, $owner);
        if ($token !== null) {
            $device = EmployeeDevice::query()
                ->with('employee')
                ->where('user_id', $owner->id)
                ->where('token_hash', self::hashToken($token))
                ->first();

            if ($device
                && $device->isActive()
                && $device->employee
                && $device->employee->shop_owner_id === $owner->id
                && $device->employee->portal_enabled
                && $device->employee->is_active
                && self::ownerIsAvailable($owner)) {
                $request->attributes->set('staffDevice', $device);
                $request->attributes->set('staffEmployee', $device->employee);
                $request->attributes->set('staffDeviceToken', $token);

                $this->touchDevice($device);
            }
        }

        return $next($request);
    }

    public static function cookieName(User|int $owner): string
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        return 'sp_staff_' . $ownerId;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function csrfForTokenHash(string $tokenHash): string
    {
        return hash_hmac('sha256', $tokenHash, (string) config('app.key') . '|staff-portal');
    }

    public static function makeCookie(Request $request, User|int $owner, string $token): Cookie
    {
        return cookie(
            self::cookieName($owner),
            $token,
            60 * 24 * 365,
            '/',
            null,
            self::requestScheme($request) === 'https',
            true,
            false,
            'lax'
        );
    }

    public static function ownerIsAvailable(User $owner): bool
    {
        return $owner->role !== 'disabled' && $owner->is_active !== false && $owner->canAccessFeature('hr');
    }

    public static function isSecurePortalRequest(Request $request): bool
    {
        return self::isLocalHost($request) || self::requestScheme($request) === 'https';
    }

    public static function requestScheme(Request $request): string
    {
        if ($request->isSecure()) {
            return 'https';
        }

        $forwardedProto = strtolower(trim((string) $request->headers->get('X-Forwarded-Proto', '')));
        if ($forwardedProto !== '') {
            $proto = strtok($forwardedProto, ',');
            if (is_string($proto) && strtolower(trim($proto)) === 'https') {
                return 'https';
            }
        }

        $frontEndHttps = strtolower(trim((string) $request->headers->get('Front-End-Https', '')));
        if (in_array($frontEndHttps, ['on', '1'], true)) {
            return 'https';
        }

        $origin = self::sameHostOrigin($request);
        if ($origin !== null) {
            return $origin['scheme'];
        }

        $referer = self::sameHostHeaderUrl($request, 'Referer');

        return $referer['scheme'] ?? 'http';
    }

    public static function effectiveOrigin(Request $request): string
    {
        $origin = self::sameHostOrigin($request) ?? self::sameHostHeaderUrl($request, 'Referer');
        if ($origin !== null) {
            return $origin['origin'];
        }

        return self::requestScheme($request) . '://' . $request->getHttpHost();
    }

    private function resolveToken(Request $request, User $owner): ?string
    {
        $cookieToken = $request->cookie(self::cookieName($owner));
        return is_string($cookieToken) && $cookieToken !== '' ? $cookieToken : null;
    }

    private function touchDevice(EmployeeDevice $device): void
    {
        $now = now();

        if (! $device->last_used_at || $device->last_used_at->diffInMinutes($now) >= 5) {
            $device->forceFill(['last_used_at' => $now])->save();
        }

        if (! $device->employee->last_portal_login_at || $device->employee->last_portal_login_at->diffInMinutes($now) >= 15) {
            $device->employee->forceFill(['last_portal_login_at' => $now])->save();
        }
    }

    private static function isLocalHost(Request $request): bool
    {
        return in_array($request->getHost(), ['localhost', '127.0.0.1'], true);
    }

    /**
     * @return array{scheme: 'http'|'https', origin: string}|null
     */
    private static function sameHostOrigin(Request $request): ?array
    {
        return self::sameHostHeaderUrl($request, 'Origin');
    }

    /**
     * @return array{scheme: 'http'|'https', origin: string}|null
     */
    private static function sameHostHeaderUrl(Request $request, string $header): ?array
    {
        $value = trim((string) $request->headers->get($header, ''));
        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $host !== strtolower($request->getHost())) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return [
            'scheme' => $scheme,
            'origin' => $scheme . '://' . $host . $port,
        ];
    }
}
