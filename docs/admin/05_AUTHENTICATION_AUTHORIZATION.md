# Super-Admin Panel Blueprint
## Part 5: Authentication & Authorization

---

# 8. AUTHENTICATION & AUTHORIZATION

## 8.1 Authentication Mechanism

**Recommended:** Laravel Sanctum with SPA authentication

```
Authentication Flow:
1. Admin visits /admin/login
2. Submits email + password
3. Backend validates credentials
4. Issues Sanctum token (stored as HTTP-only cookie)
5. Frontend stores token for API calls
6. Token expires after configurable period
7. Refresh token mechanism for extended sessions
```

**Token Configuration:**

| Setting | Value |
|---------|-------|
| Token Expiry | 8 hours |
| Refresh Window | 7 days |
| Max Concurrent Sessions | 3 |
| Remember Me Duration | 30 days |

## 8.2 Role Hierarchy

```
Role Hierarchy:
├── super_admin (level: 100)
│   ├── Full system access
│   ├── Can manage other admins
│   ├── Can access system settings
│   └── Can view audit logs
│
└── admin (level: 50) [FUTURE]
    ├── Event management
    ├── Talent management
    ├── Category management (view only)
    ├── Media management
    └── Cannot manage admins or settings
```

## 8.3 Permissions Matrix

### Events Permissions

| Permission | Super-Admin | Admin (Future) |
|------------|:-----------:|:--------------:|
| events.view | ✅ | ✅ |
| events.create | ✅ | ✅ |
| events.edit | ✅ | ✅ |
| events.delete | ✅ | ✅ |
| events.publish | ✅ | ✅ |
| events.feature | ✅ | ❌ |
| events.bulk_operations | ✅ | ❌ |

### Talents Permissions

| Permission | Super-Admin | Admin (Future) |
|------------|:-----------:|:--------------:|
| talents.view | ✅ | ✅ |
| talents.create | ✅ | ✅ |
| talents.edit | ✅ | ✅ |
| talents.delete | ✅ | ❌ |
| talents.activate | ✅ | ❌ |

### Categories Permissions

| Permission | Super-Admin | Admin (Future) |
|------------|:-----------:|:--------------:|
| categories.view | ✅ | ✅ |
| categories.create | ✅ | ❌ |
| categories.edit | ✅ | ❌ |
| categories.delete | ✅ | ❌ |
| categories.reorder | ✅ | ❌ |

### Media Permissions

| Permission | Super-Admin | Admin (Future) |
|------------|:-----------:|:--------------:|
| media.view | ✅ | ✅ |
| media.upload | ✅ | ✅ |
| media.edit | ✅ | ✅ |
| media.delete | ✅ | ✅ |

### Admin Users Permissions

| Permission | Super-Admin | Admin (Future) |
|------------|:-----------:|:--------------:|
| users.view | ✅ | ❌ |
| users.create | ✅ | ❌ |
| users.edit | ✅ | ❌ |
| users.delete | ✅ | ❌ |

### System Permissions

| Permission | Super-Admin | Admin (Future) |
|------------|:-----------:|:--------------:|
| settings.view | ✅ | ❌ |
| settings.edit | ✅ | ❌ |
| analytics.view | ✅ | ✅ |
| analytics.export | ✅ | ❌ |
| audit.view | ✅ | ❌ |

---

## 8.4 Middleware Configuration

```
Laravel Middleware Stack for Admin Routes:

Route::prefix('api/admin')->group(function () {
    
    // Public auth routes (no middleware)
    Route::post('/auth/login', ...);
    Route::post('/auth/password/forgot', ...);
    Route::post('/auth/password/reset', ...);
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        // All admin routes...
    });
    
    // Super-admin only routes
    Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
        // Settings, admin users, audit logs...
    });
});
```

**Custom Middleware:**

| Middleware | Purpose |
|------------|---------|
| `auth:sanctum` | Verify authentication token |
| `role:{role}` | Check user role level |
| `permission:{perm}` | Check specific permission |
| `audit.log` | Log admin actions |
| `throttle:admin` | Rate limiting (60/min) |

---

## 8.5 Audit Logging Requirements

### Audit Log Entry Structure

| Field | Type | Description |
|-------|------|-------------|
| `id` | uuid | Primary key |
| `admin_id` | uuid | Acting admin |
| `admin_email` | string | Snapshot of email |
| `action` | string | create, update, delete, etc. |
| `entity_type` | string | events, talents, etc. |
| `entity_id` | uuid | Affected entity |
| `entity_title` | string | Snapshot of entity name |
| `old_values` | json | Previous state |
| `new_values` | json | New state |
| `ip_address` | string | Request IP |
| `user_agent` | string | Browser/client info |
| `request_id` | uuid | Correlation ID |
| `created_at` | timestamp | Action time |

### Actions to Audit

| Category | Actions |
|----------|---------|
| **Authentication** | login, logout, login_failed, password_reset |
| **Events** | created, updated, deleted, published, unpublished, featured, unfeatured, cancelled, archived, restored, duplicated |
| **Talents** | created, updated, deleted, activated, deactivated, restored |
| **Categories** | created, updated, deleted, reordered, activated, deactivated |
| **Media** | uploaded, updated, deleted, moved |
| **Admin Users** | created, updated, deleted, activated, deactivated, password_reset |
| **Settings** | updated, cache_cleared |
| **Bulk Operations** | bulk_deleted, bulk_published, bulk_archived, bulk_category_updated |

### Audit Log Example

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "admin_id": "123e4567-e89b-12d3-a456-426614174000",
  "admin_email": "admin@example.com",
  "action": "updated",
  "entity_type": "events",
  "entity_id": "789e0123-e89b-12d3-a456-426614174000",
  "entity_title": "Summer Music Festival",
  "old_values": {
    "status": "draft",
    "title": "Summer Festival"
  },
  "new_values": {
    "status": "published",
    "title": "Summer Music Festival"
  },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)...",
  "request_id": "req_abc123",
  "created_at": "2026-01-15T14:30:00Z"
}
```

---

## 8.6 Security Best Practices

### Password Requirements

| Rule | Value |
|------|-------|
| Minimum Length | 12 characters |
| Require Uppercase | Yes |
| Require Lowercase | Yes |
| Require Number | Yes |
| Require Special Character | Yes |
| Password History | Last 5 passwords |
| Max Age | 90 days (configurable) |

### Session Security

| Setting | Value |
|---------|-------|
| Session Timeout (Idle) | 30 minutes |
| Concurrent Sessions | Max 3 |
| Force Logout on Password Change | Yes |
| Secure Cookie Flag | Yes (HTTPS only) |
| SameSite Cookie | Strict |
| HttpOnly Cookie | Yes |

### Rate Limiting

| Endpoint Type | Limit |
|---------------|-------|
| Login Attempts | 5 per minute per IP |
| Password Reset | 3 per hour per email |
| API Requests | 60 per minute per user |
| Bulk Operations | 10 per minute per user |

### Failed Login Handling

```
Failed Login Policy:
├── 3 failures: Show CAPTCHA
├── 5 failures: 15-minute lockout
├── 10 failures: 1-hour lockout
├── 20 failures: Account disabled (manual unlock)
└── All failures: Logged to audit trail
```

---

## 8.7 CORS Configuration

```php
// For SPA same-origin (recommended):
'paths' => ['api/admin/*'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('ADMIN_PANEL_URL')],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

---

## 8.8 Environment Variables

```env
# Authentication
ADMIN_TOKEN_EXPIRY=480           # minutes (8 hours)
ADMIN_REFRESH_WINDOW=10080       # minutes (7 days)
ADMIN_SESSION_TIMEOUT=30         # minutes
ADMIN_MAX_SESSIONS=3

# Security
ADMIN_MIN_PASSWORD_LENGTH=12
ADMIN_PASSWORD_MAX_AGE=90        # days
ADMIN_FAILED_LOGIN_LOCKOUT=5     # attempts

# Rate Limiting
ADMIN_RATE_LIMIT_API=60          # requests per minute
ADMIN_RATE_LIMIT_LOGIN=5         # attempts per minute
ADMIN_RATE_LIMIT_BULK=10         # operations per minute
```
