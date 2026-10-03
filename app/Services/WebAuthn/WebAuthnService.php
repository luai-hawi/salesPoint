<?php

namespace App\Services\WebAuthn;

use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class WebAuthnService
{
    public function registerOptions(Employee $employee, string $rpId, string $origin): array
    {
        $challenge = random_bytes(32);
        $challengeId = $this->storeChallenge('register', $employee, $challenge, $rpId, $origin);

        return [
            'challenge_id' => $challengeId,
            'publicKey' => [
                'challenge' => Base64Url::encode($challenge),
                'rp' => [
                    'name' => $employee->shopOwner?->name ?? config('app.name'),
                    'id' => $rpId,
                ],
                'user' => [
                    'id' => Base64Url::encode((string) $employee->id),
                    'name' => $employee->username ?: ('employee-' . $employee->id),
                    'displayName' => $employee->name,
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],
                    ['type' => 'public-key', 'alg' => -257],
                ],
                'timeout' => 60000,
                // "none" proves possession of the authenticator, not that it is hardware-backed.
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'authenticatorAttachment' => 'platform',
                    'userVerification' => 'required',
                    'residentKey' => 'preferred',
                ],
                'excludeCredentials' => $employee->credentials()
                    ->get()
                    ->map(fn (EmployeeCredential $credential) => [
                        'type' => 'public-key',
                        'id' => $credential->credential_id,
                        'transports' => $credential->transports ?? [],
                    ])->values()->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    public function verifyRegistration(Employee $employee, string $challengeId, array $credential, string $rpId, string $origin): EmployeeCredential
    {
        $challenge = $this->consumeChallenge('register', $challengeId, $employee, $rpId, $origin);
        $this->validateCredentialEnvelope($credential, 'webauthn.create');

        $clientDataJson = Base64Url::decode((string) data_get($credential, 'response.clientDataJSON'));
        $this->parseClientDataJson($clientDataJson, 'webauthn.create', $challenge, $origin);

        $attestationObject = Base64Url::decode((string) data_get($credential, 'response.attestationObject'));
        $attestation = Cbor::decode($attestationObject);

        if (! is_array($attestation) || ! isset($attestation['authData']) || ! is_string($attestation['authData'])) {
            throw new WebAuthnException('invalid_attestation');
        }

        $authData = $this->parseAuthData($attestation['authData'], true);
        $this->ensureRpIdHash($authData['rpIdHash'], $rpId);
        $this->ensureFlags($authData['flags'], requireAttestedData: true);

        $publicKey = CoseKey::toPem($authData['credentialPublicKey']);
        $algorithm = CoseKey::algorithm($authData['credentialPublicKey']);
        $credentialId = Base64Url::encode($authData['credentialId']);

        $credentialHash = hash('sha256', $authData['credentialId']);
        $existing = EmployeeCredential::query()
            ->where('credential_hash', $credentialHash)
            ->first();

        if ($existing && (int) $existing->employee_id !== (int) $employee->id) {
            throw new WebAuthnException('credential_already_registered');
        }

        $record = $existing ?? new EmployeeCredential(['credential_hash' => $credentialHash]);
        $record->forceFill([
            'user_id' => $employee->shop_owner_id,
            'employee_id' => $employee->id,
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'algorithm' => $algorithm,
            'sign_count' => (int) $authData['signCount'],
            'transports' => data_get($credential, 'response.transports', data_get($credential, 'transports', [])),
            'label' => $this->trimLabel((string) ($credential['label'] ?? $employee->name . ' device')),
            'last_used_at' => null,
        ])->save();

        return $record->fresh();
    }

    public function authOptions(Employee $employee, string $rpId, string $origin): array
    {
        $challenge = random_bytes(32);
        $challengeId = $this->storeChallenge('authenticate', $employee, $challenge, $rpId, $origin);

        return [
            'challenge_id' => $challengeId,
            'publicKey' => [
                'challenge' => Base64Url::encode($challenge),
                'rpId' => $rpId,
                'timeout' => 60000,
                'userVerification' => 'required',
                'allowCredentials' => $employee->credentials()
                    ->get()
                    ->map(fn (EmployeeCredential $credential) => [
                        'type' => 'public-key',
                        'id' => $credential->credential_id,
                        'transports' => $credential->transports ?? [],
                    ])->values()->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    public function verifyAssertion(Employee $employee, string $challengeId, array $credential, string $rpId, string $origin): EmployeeCredential
    {
        $challenge = $this->consumeChallenge('authenticate', $challengeId, $employee, $rpId, $origin);
        $this->validateCredentialEnvelope($credential, 'webauthn.get');

        $credentialId = Base64Url::decode((string) ($credential['rawId'] ?? $credential['id'] ?? ''));
        $stored = EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('user_id', $employee->shop_owner_id)
            ->where('credential_hash', hash('sha256', $credentialId))
            ->first();

        if (! $stored) {
            throw new WebAuthnException('invalid_credential');
        }

        if (! in_array((int) $stored->algorithm, [-7, -257], true)) {
            throw new WebAuthnException('unsupported_algorithm');
        }

        $clientDataJson = Base64Url::decode((string) data_get($credential, 'response.clientDataJSON'));
        $this->parseClientDataJson($clientDataJson, 'webauthn.get', $challenge, $origin);

        $authDataBytes = Base64Url::decode((string) data_get($credential, 'response.authenticatorData'));
        $authData = $this->parseAuthData($authDataBytes, false);
        $this->ensureRpIdHash($authData['rpIdHash'], $rpId);
        $this->ensureFlags($authData['flags'], requireAttestedData: false);

        $signature = Base64Url::decode((string) data_get($credential, 'response.signature'));
        $signedData = $authDataBytes . hash('sha256', $clientDataJson, true);
        $publicKey = openssl_pkey_get_public($stored->public_key);

        if (! $publicKey || openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new WebAuthnException('invalid_signature');
        }

        $newCount = (int) $authData['signCount'];
        $oldCount = (int) $stored->sign_count;
        if (($newCount > 0 || $oldCount > 0) && $newCount <= $oldCount) {
            throw new WebAuthnException('counter_replay');
        }

        $stored->forceFill([
            'sign_count' => max($oldCount, $newCount),
            'last_used_at' => now(),
        ])->save();

        return $stored->fresh();
    }

    private function storeChallenge(string $purpose, Employee $employee, string $challenge, string $rpId, string $origin): string
    {
        $id = Base64Url::encode(random_bytes(18));

        Cache::put($this->challengeKey($id), [
            'purpose' => $purpose,
            'employee_id' => $employee->id,
            'user_id' => $employee->shop_owner_id,
            'challenge' => Base64Url::encode($challenge),
            'rp_id' => $rpId,
            'origin' => $origin,
        ], now()->addMinutes(5));

        return $id;
    }

    private function consumeChallenge(string $purpose, string $challengeId, Employee $employee, string $rpId, string $origin): string
    {
        $payload = Cache::pull($this->challengeKey($challengeId));
        if (! is_array($payload)) {
            throw new WebAuthnException('invalid_challenge');
        }

        if (($payload['purpose'] ?? null) !== $purpose
            || (int) ($payload['employee_id'] ?? 0) !== (int) $employee->id
            || (int) ($payload['user_id'] ?? 0) !== (int) $employee->shop_owner_id
            || ($payload['rp_id'] ?? null) !== $rpId
            || ($payload['origin'] ?? null) !== $origin) {
            throw new WebAuthnException('invalid_challenge');
        }

        return (string) ($payload['challenge'] ?? '');
    }

    private function challengeKey(string $id): string
    {
        return 'staff-webauthn:' . $id;
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function validateCredentialEnvelope(array $credential, string $expectedType): void
    {
        if (($credential['type'] ?? null) !== 'public-key') {
            throw new WebAuthnException('invalid_credential');
        }

        if (! is_array($credential['response'] ?? null)) {
            throw new WebAuthnException('invalid_credential');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseClientDataJson(string $clientDataJson, string $type, string $challenge, string $origin): array
    {
        $clientData = json_decode($clientDataJson, true);
        if (! is_array($clientData)) {
            throw new WebAuthnException('invalid_client_data');
        }

        if (($clientData['type'] ?? null) !== $type) {
            throw new WebAuthnException('invalid_type');
        }

        if (($clientData['challenge'] ?? null) !== $challenge) {
            throw new WebAuthnException('invalid_challenge');
        }

        if (($clientData['origin'] ?? null) !== $origin) {
            throw new WebAuthnException('invalid_origin');
        }

        return $clientData;
    }

    /**
     * @return array{rpIdHash: string, flags: int, signCount: int, credentialId: string, credentialPublicKey: array<int|string, mixed>|null}
     */
    private function parseAuthData(string $authData, bool $requireAttestedData): array
    {
        if (strlen($authData) < 37) {
            throw new WebAuthnException('invalid_auth_data');
        }

        $offset = 0;
        $rpIdHash = substr($authData, $offset, 32);
        $offset += 32;
        $flags = ord($authData[$offset++]);
        $signCount = unpack('N', substr($authData, $offset, 4))[1];
        $offset += 4;

        $hasAttestedData = (bool) ($flags & 0x40);
        if ($requireAttestedData && ! $hasAttestedData) {
            throw new WebAuthnException('invalid_attestation');
        }

        $credentialId = '';
        $credentialPublicKey = null;

        if ($hasAttestedData) {
            if (strlen($authData) < $offset + 18) {
                throw new WebAuthnException('invalid_attestation');
            }

            $offset += 16;
            $credentialLength = unpack('n', substr($authData, $offset, 2))[1];
            $offset += 2;
            $credentialId = substr($authData, $offset, $credentialLength);
            $offset += $credentialLength;
            $credentialPublicKey = Cbor::decode($authData, $offset);
            if (! is_array($credentialPublicKey)) {
                throw new WebAuthnException('invalid_public_key');
            }
        }

        return [
            'rpIdHash' => $rpIdHash,
            'flags' => $flags,
            'signCount' => $signCount,
            'credentialId' => $credentialId,
            'credentialPublicKey' => $credentialPublicKey,
        ];
    }

    private function ensureRpIdHash(string $rpIdHash, string $rpId): void
    {
        if (! hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
            throw new WebAuthnException('invalid_rp');
        }
    }

    private function ensureFlags(int $flags, bool $requireAttestedData): void
    {
        if (($flags & 0x01) === 0 || ($flags & 0x04) === 0) {
            throw new WebAuthnException('user_verification_required');
        }

        if ($requireAttestedData && ($flags & 0x40) === 0) {
            throw new WebAuthnException('invalid_attestation');
        }
    }

    private function trimLabel(string $label): string
    {
        $label = trim($label);

        return mb_substr($label !== '' ? $label : 'Device', 0, 120);
    }
}
