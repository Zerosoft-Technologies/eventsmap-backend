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
 * Firestore structure (matches frontend {@code invitation_notifications} listener):
 *   Collection: invitation_notifications
 *   Document ID: invitation_{invitation_id}
 *   Fields: receiver_id (string), invitation_id, event_id, event_title,
 *           sender_id, sender_name, receiver_type, message,
 *           status ('pending'|'completed'), created_at, completed_at (optional)
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
     * Create or refresh a pending Firestore notification when an invitation is sent.
     */
    public function createInvitationNotification(EventInvitation $invitation): void
    {
        $invitation->loadMissing([
            'event:id,title',
            'sender:id,name',
            'receiver:id',
        ]);

        $this->upsertPendingNotification($invitation);
    }

    /**
     * Mark the invitation notification as completed (accepted, rejected, or cancelled).
     */
    public function markInvitationNotificationCompleted(int $invitationId, string $receiverId): void
    {
        if (! $this->firebaseService->isConfigured()) {
            return;
        }

        try {
            $token = $this->getAccessToken();
            $projectId = $this->getProjectId();
        } catch (\Throwable $e) {
            Log::warning('Firestore credentials unavailable for invitation completion', [
                'error' => $e->getMessage(),
            ]);

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

                return;
            }

            if ($response->status() === 404) {
                Log::debug('Firestore invitation notification not found on complete', [
                    'invitation_id' => $invitationId,
                ]);

                return;
            }

            Log::warning('Firestore invitation notification update failed', [
                'invitation_id' => $invitationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to update Firestore invitation notification', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function upsertPendingNotification(EventInvitation $invitation): void
    {
        if (! $this->firebaseService->isConfigured()) {
            Log::debug('Firebase not configured, skipping Firestore invitation notification');

            return;
        }

        try {
            $token = $this->getAccessToken();
            $projectId = $this->getProjectId();
        } catch (\Throwable $e) {
            Log::warning('Firestore not available for invitation notification', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $docId = $this->documentId($invitation->id);
        $fields = $this->pendingNotificationFields($invitation);

        $documentUrl = sprintf(
            '%s/%s/%s',
            $this->baseUrl($projectId),
            self::COLLECTION,
            $docId
        );

        $fieldPaths = array_keys($fields);
        $updateMask = implode('&', array_map(
            static fn (string $path) => 'updateMask.fieldPaths='.urlencode($path),
            $fieldPaths
        ));

        try {
            $patchResponse = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->patch($documentUrl.'?'.$updateMask, ['fields' => $fields]);

            if ($patchResponse->successful()) {
                Log::info('Firestore invitation notification updated', [
                    'invitation_id' => $invitation->id,
                    'receiver_id' => $invitation->receiver_id,
                ]);

                return;
            }

            if ($patchResponse->status() !== 404) {
                Log::error('Firestore invitation notification patch failed', [
                    'invitation_id' => $invitation->id,
                    'status' => $patchResponse->status(),
                    'body' => $patchResponse->body(),
                ]);

                return;
            }

            $createUrl = sprintf(
                '%s/%s?documentId=%s',
                $this->baseUrl($projectId),
                self::COLLECTION,
                $docId
            );

            $createResponse = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($createUrl, ['fields' => $fields]);

            if ($createResponse->successful()) {
                Log::info('Firestore invitation notification created', [
                    'invitation_id' => $invitation->id,
                    'receiver_id' => $invitation->receiver_id,
                ]);

                return;
            }

            Log::error('Firestore invitation notification create failed', [
                'invitation_id' => $invitation->id,
                'status' => $createResponse->status(),
                'body' => $createResponse->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to upsert Firestore invitation notification', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function pendingNotificationFields(EventInvitation $invitation): array
    {
        $senderName = $invitation->sender?->name ?? 'Someone';
        $message = "You have a pending invitation from {$senderName}, Event Publisher. Please accept or decline your invitation.";

        return [
            'receiver_id' => ['stringValue' => (string) $invitation->receiver_id],
            'invitation_id' => ['integerValue' => (string) $invitation->id],
            'event_id' => ['integerValue' => (string) $invitation->event_id],
            'event_title' => ['stringValue' => $invitation->event?->title ?? ''],
            'sender_id' => ['integerValue' => (string) $invitation->sender_id],
            'sender_name' => ['stringValue' => $senderName],
            'receiver_type' => ['stringValue' => (string) ($invitation->receiver_type ?? '')],
            'message' => ['stringValue' => $message],
            'status' => ['stringValue' => 'pending'],
            'created_at' => ['timestampValue' => $this->timestampRfc3339($invitation->created_at)],
        ];
    }

    private function documentId(int $invitationId): string
    {
        return 'invitation_'.$invitationId;
    }

    private function timestampRfc3339(?\DateTimeInterface $at = null): string
    {
        $dt = $at
            ? \DateTimeImmutable::createFromInterface($at)->setTimezone(new \DateTimeZone('UTC'))
            : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $dt->format('Y-m-d\TH:i:s.u\Z');
    }

    private function baseUrl(string $projectId): string
    {
        return sprintf(self::BASE_URL_TEMPLATE, $projectId);
    }

    private function getAccessToken(): string
    {
        $path = $this->firebaseService->getCredentialsPath();
        if (! $path) {
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
        $projectId = $this->firebaseService->getProjectId();
        if (! empty($projectId)) {
            return $projectId;
        }

        throw new \RuntimeException('Firebase project_id not found in credentials or config');
    }
}
