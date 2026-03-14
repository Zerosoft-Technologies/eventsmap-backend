# Invitation Notifications (Firestore)

Real-time invitation notifications are stored in Firestore. When an invitation is sent, the backend writes a document; when the user accepts or rejects, the backend marks it completed.

## 1. Backend: Firebase Admin SDK

Install the Firebase PHP SDK so Laravel can write to Firestore:

```bash
composer require kreait/firebase-php
```

Ensure `config/services.php` has Firebase credentials and your `.env` has:

- `FIREBASE_CREDENTIALS` – path to your service account JSON (e.g. `storage/app/firebase-credentials.json`)
- `FIREBASE_PROJECT_ID` – your Firebase project ID (same as chat)

## 2. Firestore security rules

In [Firebase Console](https://console.firebase.google.com) → Firestore Database → Rules, add rules so users can only **read** their own invitation notifications. Writes are done only by the backend (admin).

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // ... your existing rules (e.g. conversations, user_presence) ...

    // Invitation notifications: user can only read docs where they are the receiver
    match /invitation_notifications/{docId} {
      allow read: if request.auth != null && request.auth.uid == resource.data.receiver_id;
      allow create, update, delete: if false;
    }
  }
}
```

## 3. Firestore composite index

The frontend queries: `receiver_id == currentUserId` ordered by `created_at desc`. Create a composite index:

1. Run the app and open the notifications dropdown while logged in. If the index is missing, the browser console will show an error with a **link to create the index** in Firebase Console. Click it and deploy.
2. Or in Firebase Console → Firestore → Indexes → Composite:
   - Collection: `invitation_notifications`
   - Fields: `receiver_id` (Ascending), `created_at` (Descending)

## 4. Flow summary

- **Create:** When an event creator sends invitations, `EventInvitationService` sends the email and calls `FirebaseNotificationService::createInvitationNotification()`, which creates a document `invitation_notifications/invitation_{id}` with `receiver_id`, `message`, `status: 'pending'`, etc.
- **Read:** The frontend subscribes to `invitation_notifications` where `receiver_id == currentUser.id` (real-time). The Header shows a bell and dropdown with pending items.
- **Complete:** When the user accepts or rejects via the email link, `EventInvitationService::respond()` updates the invitation and calls `markInvitationNotificationCompleted()`, which updates the Firestore doc to `status: 'completed'`. The real-time listener updates the UI so the notification disappears from the pending list.
