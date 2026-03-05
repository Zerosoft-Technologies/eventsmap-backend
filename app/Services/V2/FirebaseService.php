<?php

namespace App\Services\V2;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private ?string $serviceAccountPath;
    private ?array $serviceAccount = null;

    public function __construct()
    {
        $this->serviceAccountPath = config('services.firebase.credentials');

        if ($this->serviceAccountPath && file_exists($this->serviceAccountPath)) {
            $this->serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
        }
    }

    /**
     * Generate a custom Firebase token for a user.
     * This token can be used to authenticate with Firebase on the client side.
     */
    public function generateCustomToken(User $user, array $claims = []): ?string
    {
        if (!$this->serviceAccount) {
            Log::error('Firebase service account not configured');
            return null;
        }

        $privateKey = $this->serviceAccount['private_key'] ?? null;
        $clientEmail = $this->serviceAccount['client_email'] ?? null;

        if (!$privateKey || !$clientEmail) {
            Log::error('Firebase service account missing required fields');
            return null;
        }

        $now = time();

        $payload = [
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
            'iat' => $now,
            'exp' => $now + 3600,
            'uid' => (string) $user->id,
            'claims' => array_merge([
                'name' => $user->name,
                'email' => $user->email,
                'profile_type' => $user->profile_type,
                'account_type' => $user->account_type,
            ], $claims),
        ];

        try {
            $token = JWT::encode($payload, $privateKey, 'RS256');

            Log::info('Firebase custom token generated', [
                'user_id' => $user->id,
            ]);

            return $token;
        } catch (\Exception $e) {
            Log::error('Failed to generate Firebase token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate a custom token with event-specific claims.
     */
    public function generateEventChatToken(User $user, int $eventId, array $permissions = []): ?string
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
     * Get the Firebase project ID.
     */
    public function getProjectId(): ?string
    {
        return $this->serviceAccount['project_id'] ?? null;
    }
}
