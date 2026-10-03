<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Middleware\StaffPortalAuth;
use App\Models\Employee;
use App\Services\WebAuthn\WebAuthnException;
use App\Services\WebAuthn\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class WebAuthnController extends Controller
{
    public function __construct(
        protected WebAuthnService $webAuthn,
    ) {
    }

    public function registerOptions(Request $request, string $key): JsonResponse
    {
        $employee = $this->employee($request);
        $this->ensureAllowed($request, $employee);

        return response()->json(
            $this->webAuthn->registerOptions($employee, $request->getHost(), StaffPortalAuth::effectiveOrigin($request))
        );
    }

    public function verifyRegistration(Request $request, string $key): JsonResponse
    {
        $employee = $this->employee($request);
        $this->ensureAllowed($request, $employee);

        $payload = $request->validate([
            'challenge_id' => ['required', 'string'],
            'credential' => ['required', 'array'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $credential = $payload['credential'];
            if (! empty($payload['label'])) {
                $credential['label'] = $payload['label'];
            }

            $stored = $this->webAuthn->verifyRegistration(
                $employee,
                (string) $payload['challenge_id'],
                $credential,
                $request->getHost(),
                StaffPortalAuth::effectiveOrigin($request),
            );
        } catch (WebAuthnException $e) {
            return response()->json([
                'message' => __('staff.errors.' . $e->errorCode()),
                'code' => $e->errorCode(),
            ], 422);
        }

        return response()->json([
            'message' => __('staff.webauthn.registered'),
            'credential' => ['id' => $stored->id, 'label' => $stored->label],
        ]);
    }

    public function authOptions(Request $request, string $key): JsonResponse
    {
        $employee = $this->employee($request);
        $this->ensureAllowed($request, $employee);

        return response()->json(
            $this->webAuthn->authOptions($employee, $request->getHost(), StaffPortalAuth::effectiveOrigin($request))
        );
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->attributes->get('staffEmployee');
        abort_unless($employee instanceof Employee, 401);

        return $employee;
    }

    private function ensureAllowed(Request $request, Employee $employee): void
    {
        $device = $request->attributes->get('staffDevice');
        abort_unless($device, 401);

        $key = 'staff-webauthn:' . $employee->shop_owner_id . ':' . $employee->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(response()->json([
                'message' => __('staff.errors.too_many_requests'),
                'retry_after' => RateLimiter::availableIn($key),
            ], 429));
        }

        RateLimiter::hit($key, 60);
    }
}
