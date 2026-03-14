<?php

namespace App\Services\V2;

use App\Models\EventInvitation;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Writes invitation notifications to Firestore via REST API so invited users
 * see real-time in-app notifications. No gRPC extension required.
 *
 * Firestore structure:
 *   Collection: invitation_notifications
 *   Document ID: invitation_{invitation_id}
 *   Fields: receiver_id (string), invitation_id, event_id, event_title,
 *           sender_id, sender_name, message, status ('pending'|'completed'),
 *           created_at, completed_at (optional)
 *
 * Security rules (set in Firebase Console): allow read if
 * request.auth.uid == resource.data.receiver_id; allow write: if false.
 *
 * Requires: composer require kreait/firebase-php (uses google/auth for tokens)
 */
class FirebaseNotificationService
{
    private const COLLECTION = 'invitation_notifications';

    private const FIRESTORE_SCOPE = 'https://www.googleapis.com/auth/datastore';

    private const BASE_URL_TEMPLATE = 'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents';

    public function __construct(
        private readonly FirebaseService $firebaseService
    ) {}

    /**
     * Create a Firestore notification when an invitation is sent.
     */
    public function createInvitationNotification(EventInvitation $invitation): void
    {
        if (!$this->firebaseService->isConfigured()) {
            Log::debug('Firebase not configured, skipping Firestore invitation notification');
            return;
        }

        try {
            $token = $this->getAccessToken();
            $projectId = $this->getProjectId();
        } catch (\Throwable $e) {
            Log::debug('Firestore not available for invitation notification', ['error' => $e->getMessage()]);
            return;
        }

        $docId = $this->documentId($invitation->id);
        $receiverId = (string) $invitation->receiver_id;
        $senderName = $invitation->sender?->name ?? 'Someone';
        $message = "You have a pending invitation from {$senderName}. Please check your email to accept the invitation.";

        $fields = [
            'receiver_id' => ['stringValue' => $receiverId],
            'invitation_id' => ['integerValue' => (string) $invitation->id],
            'event_id' => ['integerValue' => (string) $invitation->event_id],
            'event_title' => ['stringValue' => $invitation->event?->title ?? ''],
            'sender_id' => ['integerValue' => (string) $invitation->sender_id],
            'sender_name' => ['stringValue' => $senderName],
            'message' => ['stringValue' => $message],
            'status' => ['stringValue' => 'pending'],
            'created_at' => ['timestampValue' => $this->timestampRfc3339()],
        ];

        $url = sprintf(
            '%s/%s?documentId=%s',
            $this->baseUrl($projectId),
            self::COLLECTION,
            $docId
        );

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, ['fields' => $fields]);

            if ($response->successful()) {
                Log::info('Firestore invitation notification created', [
                    'invitation_id' => $invitation->id,
                    'receiver_id' => $receiverId,
                ]);
            } else {
                Log::error('Firestore invitation notification failed', [
                    'invitation_id' => $invitation->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create Firestore invitation notification', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark the invitation notification as completed (accepted or rejected).
     */
    public function markInvitationNotificationCompleted(int $invitationId, string $receiverId): void
    {
        if (!$this->firebaseService->isConfigured()) {
            return;
        }

        try {
            $token = $this->getAccessToken();
            $projectId = $this->getProjectId();
        } catch (\Throwable $e) {
            return;
        }

        $docId = $this->documentId($invitationId);
        $url = sprintf(
            '%s/%s/%s?updateMask.fieldPaths=status&updateMask.fieldPaths=completed_at',
            $this->baseUrl($projectId),
            self::COLLECTION,
            $docId
        );

        $fields = [
            'status' => ['stringValue' => 'completed'],
            'completed_at' => ['timestampValue' => $this->timestampRfc3339()],
        ];

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->patch($url, ['fields' => $fields]);

            if ($response->successful()) {
                Log::info('Firestore invitation notification marked completed', [
                    'invitation_id' => $invitationId,
                    'receiver_id' => $receiverId,
                ]);
            } else {
                Log::warning('Firestore invitation notification update failed', [
                    'invitation_id' => $invitationId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to update Firestore invitation notification', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function documentId(int $invitationId): string
    {
        return 'invitation_' . $invitationId;
    }

    private function timestampRfc3339(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    private function baseUrl(string $projectId): string
    {
        return sprintf(self::BASE_URL_TEMPLATE, $projectId);
    }

    private function getAccessToken(): string
    {
        $path = $this->resolveCredentialsPath(config('services.firebase.credentials'));
        if (!$path || !is_readable($path)) {
            throw new \RuntimeException('Firebase credentials file not found or not readable');
        }

        $credentials = new ServiceAccountCredentials(
            self::FIRESTORE_SCOPE,
            $path
        );

        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (empty($accessToken)) {
            throw new \RuntimeException('Failed to obtain Firestore access token');
        }

        return $accessToken;
    }

    private function getProjectId(): string
    {
        $projectId = config('services.firebase.project_id');
        if (!empty($projectId)) {
            return $projectId;
        }

        $path = $this->resolveCredentialsPath(config('services.firebase.credentials'));
        if (!$path || !is_readable($path)) {
            throw new \RuntimeException('Firebase credentials not found');
        }

        $data = json_decode((string) file_get_contents($path), true);
        $projectId = $data['project_id'] ?? null;

        if (empty($projectId)) {
            throw new \RuntimeException('Firebase project_id not found in credentials or config');
        }

        return $projectId;
    }

    private function resolveCredentialsPath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        $path = trim($path);
        if (file_exists($path)) {
            return realpath($path);
        }
        $fromBase = base_path($path);

        return file_exists($fromBase) ? realpath($fromBase) : null;
    }
}
