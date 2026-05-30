<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

class FirebaseService
{
    private ?string $serviceAccountPath;
    private ?array $serviceAccount = null;

    public function __construct()
    {
        $credentialsPath = config('services.firebase.credentials');

        $this->serviceAccountPath = $this->resolveCredentialsPath($credentialsPath);

        if ($this->serviceAccountPath && file_exists($this->serviceAccountPath)) {
            $this->serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
        }
    }

    /**
     * Resolve credentials path: absolute, project-relative, or storage/app.
     */
    public function resolveCredentialsPath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $path = trim($path, " \t\n\r\0\x0B\"'");

        if ($path === '') {
            return null;
        }

        $basename = basename($path);

        foreach (array_unique(array_filter([
            $path,
            base_path($path),
            storage_path($path),
            storage_path('app/'.$basename),
            base_path('storage/app/'.$basename),
        ])) as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    /**
     * Generate a custom Firebase token for a user.
     * This token can be used to authenticate with Firebase on the client side.
     *
     * @return array{token: ?string, error: ?string}
     */
    public function generateCustomToken(User $user, array $claims = []): array
    {
        if (!$this->serviceAccount) {
            Log::error('Firebase service account not configured');

            return ['token' => null, 'error' => 'Firebase service account not configured.'];
        }

        $privateKey = $this->serviceAccount['private_key'] ?? null;
        $clientEmail = $this->serviceAccount['client_email'] ?? null;

        if (!$privateKey || !$clientEmail) {
            Log::error('Firebase service account missing required fields');

            return ['token' => null, 'error' => 'Firebase service account JSON is missing private_key or client_email.'];
        }

        try {
            // Prefer the decoded service account array so Docker/path issues do not break signing.
            $factory = (new Factory)->withServiceAccount($this->serviceAccount);
            $auth = $factory->createAuth();

            $additionalClaims = $this->claimsForCustomToken($user, $claims);

            $customToken = $auth->createCustomToken((string) $user->id, $additionalClaims);
            $token = $this->customTokenToString($customToken);

            Log::info('Firebase custom token generated', [
                'user_id' => $user->id,
            ]);

            return ['token' => $token, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Failed to generate Firebase token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return ['token' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Firebase custom-token claims must be JSON-serializable scalars or shallow structures.
     * Nested arrays are flattened to string keys so signing does not fail on some JWT stacks.
     *
     * @param  array<string, mixed>  $claims
     * @return array<string, bool|float|int|string>
     */
    private function claimsForCustomToken(User $user, array $claims): array
    {
        $base = [
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'profile_type' => (string) ($user->profile_type ?? ''),
            'account_type' => (string) ($user->account_type ?? ''),
        ];

        $merged = array_merge($base, $claims);

        $flat = [];
        foreach ($merged as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nestedKey => $nestedValue) {
                    $flat[$key.'_'.$nestedKey] = $this->scalarClaimValue($nestedValue);
                }
            } else {
                $flat[(string) $key] = $this->scalarClaimValue($value);
            }
        }

        /** @var array<string, bool|float|int|string> */
        $out = [];
        foreach ($flat as $k => $v) {
            if ($v === null) {
                continue;
            }
            $out[(string) $k] = $v;
        }

        return $out;
    }

    private function scalarClaimValue(mixed $value): bool|float|int|string|null
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_array($value)) {
            $encoded = json_encode($value);
            if ($encoded === false) {
                return null;
            }

            return $encoded;
        }

        return (string) $value;
    }

    /**
     * Kreait / lcobucci token objects vary; normalize to JWT string.
     */
    private function customTokenToString(mixed $customToken): string
    {
        if (is_string($customToken)) {
            return $customToken;
        }

        if (is_object($customToken)) {
            if (method_exists($customToken, 'toString')) {
                return $customToken->toString();
            }

            return (string) $customToken;
        }

        throw new \UnexpectedValueException('Unexpected custom token type: '.get_debug_type($customToken));
    }

    /**
     * Generate a custom token with event-specific claims.
     *
     * @return array{token: ?string, error: ?string}
     */
    public function generateEventChatToken(User $user, int $eventId, array $permissions = []): array
    {
        $claims = [
            'event_id' => $eventId,
            'chat_permissions' => $permissions,
        ];

        return $this->generateCustomToken($user, $claims);
    }

    /**
     * Check if Firebase is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->serviceAccount !== null
            && isset($this->serviceAccount['private_key'])
            && isset($this->serviceAccount['client_email']);
    }

    /**
     * Get the Firebase project ID (config override, then service account JSON).
     */
    public function getProjectId(): ?string
    {
        $fromConfig = config('services.firebase.project_id');

        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        return $this->serviceAccount['project_id'] ?? null;
    }

    public function getCredentialsPath(): ?string
    {
        return $this->serviceAccountPath && is_readable($this->serviceAccountPath)
            ? $this->serviceAccountPath
            : null;
    }
}
