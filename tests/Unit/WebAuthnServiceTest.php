<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\WebAuthn\Base64Url;
use App\Services\WebAuthn\Cbor;
use App\Services\WebAuthn\WebAuthnException;
use App\Services\WebAuthn\WebAuthnService;
use Tests\Support\Builds;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class WebAuthnServiceTest extends TestCase
{
    use TestDatabase;
    use Builds;

    public function test_registration_and_assertion_verification_work_for_es256(): void
    {
        $this->assertRoundTripForAlgorithm(-7, 'webauthn-owner-ec', 'portal-user-ec');
    }

    public function test_registration_and_assertion_verification_work_for_rs256(): void
    {
        $this->assertRoundTripForAlgorithm(-257, 'webauthn-owner-rsa', 'portal-user-rsa');
    }

    private function assertRoundTripForAlgorithm(int $alg, string $portalKey, string $username): void
    {
        $owner = $this->makeOwner(['staff_portal_key' => $portalKey]);
        $employee = $this->makeEmployeeRecord($owner, $username);
        $service = app(WebAuthnService::class);
        $authenticator = $this->authenticator($alg);

        $register = $service->registerOptions($employee, 'localhost', 'http://localhost');
        $credential = $this->registrationCredential($authenticator, $register['publicKey']['challenge'], 'http://localhost', 'localhost');
        $stored = $service->verifyRegistration($employee, $register['challenge_id'], $credential, 'localhost', 'http://localhost');

        $this->assertSame($alg, $stored->algorithm);

        $auth = $service->authOptions($employee, 'localhost', 'http://localhost');
        $assertion = $this->assertionCredential($authenticator, $stored->credential_id, $auth['publicKey']['challenge'], 'http://localhost', 'localhost', 2);
        $verified = $service->verifyAssertion($employee, $auth['challenge_id'], $assertion, 'localhost', 'http://localhost');

        $this->assertSame($stored->id, $verified->id);
    }

    public function test_webauthn_rejects_wrong_challenge_origin_rp_counter_and_signature(): void
    {
        $owner = $this->makeOwner(['staff_portal_key' => 'webauthn-owner-2']);
        $employee = $this->makeEmployeeRecord($owner);
        $service = app(WebAuthnService::class);
        $authenticator = $this->authenticator(-7);

        $register = $service->registerOptions($employee, 'localhost', 'http://localhost');
        $credential = $this->registrationCredential($authenticator, $register['publicKey']['challenge'], 'http://localhost', 'localhost');
        $stored = $service->verifyRegistration($employee, $register['challenge_id'], $credential, 'localhost', 'http://localhost');

        $badRegister = $service->registerOptions($employee, 'localhost', 'http://localhost');
        $badCredential = $this->registrationCredential($authenticator, $badRegister['publicKey']['challenge'], 'http://evil.test', 'localhost');

        $this->expectException(WebAuthnException::class);
        $service->verifyRegistration($employee, $badRegister['challenge_id'], $badCredential, 'localhost', 'http://localhost');

        $auth = $service->authOptions($employee, 'localhost', 'http://localhost');
        $assertion = $this->assertionCredential($authenticator, $stored->credential_id, $auth['publicKey']['challenge'], 'http://localhost', 'localhost', 2);
        $assertion['response']['signature'] = Base64Url::encode(random_bytes(32));

        try {
            $service->verifyAssertion($employee, $auth['challenge_id'], $assertion, 'localhost', 'http://localhost');
            $this->fail('Expected invalid signature.');
        } catch (WebAuthnException $e) {
            $this->assertSame('invalid_signature', $e->errorCode());
        }

        $auth = $service->authOptions($employee, 'localhost', 'http://localhost');
        $assertion = $this->assertionCredential($authenticator, $stored->credential_id, $auth['publicKey']['challenge'], 'http://localhost', 'other.test', 3);
        try {
            $service->verifyAssertion($employee, $auth['challenge_id'], $assertion, 'localhost', 'http://localhost');
            $this->fail('Expected invalid origin.');
        } catch (WebAuthnException $e) {
            $this->assertSame('invalid_origin', $e->errorCode());
        }

        $auth = $service->authOptions($employee, 'localhost', 'http://localhost');
        $assertion = $this->assertionCredential($authenticator, $stored->credential_id, $auth['publicKey']['challenge'], 'http://localhost', 'localhost', 2);
        $service->verifyAssertion($employee, $auth['challenge_id'], $assertion, 'localhost', 'http://localhost');

        $replay = $service->authOptions($employee, 'localhost', 'http://localhost');
        $replayedAssertion = $this->assertionCredential($authenticator, $stored->credential_id, $replay['publicKey']['challenge'], 'http://localhost', 'localhost', 2);

        try {
            $service->verifyAssertion($employee, $replay['challenge_id'], $replayedAssertion, 'localhost', 'http://localhost');
            $this->fail('Expected replay failure.');
        } catch (WebAuthnException $e) {
            $this->assertSame('counter_replay', $e->errorCode());
        }

        $badRp = $service->authOptions($employee, 'localhost', 'http://localhost');
        $badRpAssertion = $this->assertionCredential($authenticator, $stored->credential_id, $badRp['publicKey']['challenge'], 'http://localhost', 'wronghost.test', 4);
        try {
            $service->verifyAssertion($employee, $badRp['challenge_id'], $badRpAssertion, 'localhost', 'http://localhost');
            $this->fail('Expected rpId failure.');
        } catch (WebAuthnException $e) {
            $this->assertSame('invalid_rp', $e->errorCode());
        }
    }

    public function test_registration_rejects_credentials_owned_by_another_employee_and_unsupported_algorithms(): void
    {
        $owner = $this->makeOwner(['staff_portal_key' => 'webauthn-owner-3']);
        $employeeA = $this->makeEmployeeRecord($owner, 'portal-user-a');
        $employeeB = $this->makeEmployeeRecord($owner, 'portal-user-b');
        $service = app(WebAuthnService::class);
        $authenticator = $this->authenticator(-257);

        $registerA = $service->registerOptions($employeeA, 'localhost', 'http://localhost');
        $credentialA = $this->registrationCredential($authenticator, $registerA['publicKey']['challenge'], 'http://localhost', 'localhost');
        $service->verifyRegistration($employeeA, $registerA['challenge_id'], $credentialA, 'localhost', 'http://localhost');

        $registerB = $service->registerOptions($employeeB, 'localhost', 'http://localhost');
        $credentialB = $this->registrationCredential($authenticator, $registerB['publicKey']['challenge'], 'http://localhost', 'localhost');
        try {
            $service->verifyRegistration($employeeB, $registerB['challenge_id'], $credentialB, 'localhost', 'http://localhost');
            $this->fail('Expected duplicate credential ownership failure.');
        } catch (WebAuthnException $e) {
            $this->assertSame('credential_already_registered', $e->errorCode());
        }

        $registerBad = $service->registerOptions($employeeB, 'localhost', 'http://localhost');
        $badCredential = $this->registrationCredential($authenticator, $registerBad['publicKey']['challenge'], 'http://localhost', 'localhost', ['cose' => [
            1 => 3,
            3 => -7,
            -1 => openssl_pkey_get_details($authenticator['key'])['rsa']['n'],
            -2 => openssl_pkey_get_details($authenticator['key'])['rsa']['e'],
        ]]);

        try {
            $service->verifyRegistration($employeeB, $registerBad['challenge_id'], $badCredential, 'localhost', 'http://localhost');
            $this->fail('Expected unsupported algorithm failure.');
        } catch (WebAuthnException $e) {
            $this->assertSame('unsupported_algorithm', $e->errorCode());
        }
    }

    public function test_malformed_cbor_payloads_are_rejected(): void
    {
        $owner = $this->makeOwner(['staff_portal_key' => 'webauthn-owner-4']);
        $employee = $this->makeEmployeeRecord($owner, 'portal-user-c');
        $service = app(WebAuthnService::class);
        $authenticator = $this->authenticator(-7);

        $register = $service->registerOptions($employee, 'localhost', 'http://localhost');
        $credential = $this->registrationCredential($authenticator, $register['publicKey']['challenge'], 'http://localhost', 'localhost');
        $credential['response']['attestationObject'] = Base64Url::encode(str_repeat('a', 17000));

        try {
            $service->verifyRegistration($employee, $register['challenge_id'], $credential, 'localhost', 'http://localhost');
            $this->fail('Expected malformed CBOR failure.');
        } catch (WebAuthnException $e) {
            $this->assertSame('invalid_cbor', $e->errorCode());
        }
    }

    private function makeEmployeeRecord($owner, string $username = 'portal-user'): Employee
    {
        $employee = new Employee([
            'shop_owner_id' => $owner->id,
            'name' => 'Portal Staff',
            'username' => $username,
            'password' => 'secret-pass',
            'portal_enabled' => true,
            'is_active' => true,
        ]);
        $employee->save();

        return $employee->fresh();
    }

    /**
     * @return array{alg: int, key: mixed, credentialId: string, cose: array<int, mixed>}
     */
    private function authenticator(int $alg): array
    {
        $config = $this->opensslConfigPath();

        if ($alg === -257) {
            $resource = openssl_pkey_new(array_filter([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $config,
            ]));
            $details = openssl_pkey_get_details($resource);

            return [
                'alg' => $alg,
                'key' => $resource,
                'credentialId' => random_bytes(16),
                'cose' => [
                    1 => 3,
                    3 => -257,
                    -1 => $details['rsa']['n'],
                    -2 => $details['rsa']['e'],
                ],
            ];
        }

        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            'config' => $config,
        ]);
        $details = openssl_pkey_get_details($resource);

        return [
            'alg' => $alg,
            'key' => $resource,
            'credentialId' => random_bytes(16),
            'cose' => [
                1 => 2,
                3 => -7,
                -1 => 1,
                -2 => $details['ec']['x'],
                -3 => $details['ec']['y'],
            ],
        ];
    }

    private function opensslConfigPath(): ?string
    {
        $current = getenv('OPENSSL_CONF');
        if (is_string($current) && $current !== '' && is_file($current)) {
            return $current;
        }

        foreach ([
            'C:\\Users\\lolo_\\.config\\herd\\bin\\php84\\extras\\ssl\\openssl.cnf',
            'C:\\Users\\lolo_\\.config\\herd\\bin\\php83\\extras\\ssl\\openssl.cnf',
            'C:\\Users\\lolo_\\.config\\herd\\openssl.cnf',
            'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\openssl.cnf',
        ] as $candidate) {
            if (is_file($candidate)) {
                putenv('OPENSSL_CONF=' . $candidate);
                $_ENV['OPENSSL_CONF'] = $candidate;
                $_SERVER['OPENSSL_CONF'] = $candidate;

                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array{alg: int, key: mixed, credentialId: string, cose: array<int, mixed>}  $authenticator
     * @return array<string, mixed>
     */
    private function registrationCredential(array $authenticator, string $challenge, string $origin, string $rpId, array $overrides = []): array
    {
        $clientDataJson = json_encode([
            'type' => 'webauthn.create',
            'challenge' => $challenge,
            'origin' => $origin,
        ], JSON_UNESCAPED_SLASHES);

        $cose = $overrides['cose'] ?? $authenticator['cose'];

        $authData = hash('sha256', $rpId, true)
            . chr(0x45)
            . pack('N', 1)
            . str_repeat("\0", 16)
            . pack('n', strlen($authenticator['credentialId']))
            . $authenticator['credentialId']
            . Cbor::encode($cose);

        $attestationObject = Cbor::encode([
            'fmt' => 'none',
            'authData' => $authData,
            'attStmt' => [],
        ]);

        return [
            'id' => Base64Url::encode($authenticator['credentialId']),
            'rawId' => Base64Url::encode($authenticator['credentialId']),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientDataJson),
                'attestationObject' => Base64Url::encode($attestationObject),
                'transports' => ['internal'],
            ],
        ];
    }

    /**
     * @param  array{alg: int, key: mixed, credentialId: string, cose: array<int, mixed>}  $authenticator
     * @return array<string, mixed>
     */
    private function assertionCredential(array $authenticator, string $credentialId, string $challenge, string $origin, string $rpId, int $signCount): array
    {
        $rawCredentialId = Base64Url::decode($credentialId);
        $clientDataJson = json_encode([
            'type' => 'webauthn.get',
            'challenge' => $challenge,
            'origin' => $origin,
        ], JSON_UNESCAPED_SLASHES);

        $authenticatorData = hash('sha256', $rpId, true) . chr(0x05) . pack('N', $signCount);
        $signed = $authenticatorData . hash('sha256', $clientDataJson, true);
        openssl_sign($signed, $signature, $authenticator['key'], OPENSSL_ALGO_SHA256);

        return [
            'id' => Base64Url::encode($rawCredentialId),
            'rawId' => Base64Url::encode($rawCredentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientDataJson),
                'authenticatorData' => Base64Url::encode($authenticatorData),
                'signature' => Base64Url::encode($signature),
            ],
        ];
    }
}
