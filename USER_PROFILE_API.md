# User Profile API Documentation

Endpoints for managing authenticated user profiles.

## Get Profile

### GET /api/user/profile

Get the authenticated user's profile information.

**Authentication**: Required (Sanctum Bearer Token)

**Response Example**:
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "profile_type": "event",
            "account_type": "premium",
            "role": "user",
            "status": "active",
            "is_active": true,
            "email_verified_at": "2025-02-28T10:35:00.000000Z",
            "premium_started_at": "2025-02-28T11:00:00.000000Z",
            "billing_type": "private",
            "full_name": "John Doe",
            "company_name": null,
            "vat_number": null,
            "vat_validated": false,
            "address": "123 Main Street",
            "postal_code": "10115",
            "city": "Berlin",
            "country": "DE",
            "created_at": "2025-02-28T10:30:00.000000Z",
            "updated_at": "2025-02-28T12:00:00.000000Z"
        }
    }
}
```

---

## Update Profile

### PUT /api/user/profile

Update the authenticated user's profile information.

**Authentication**: Required (Sanctum Bearer Token)

**Request Body** (all fields are optional - only provided fields will be updated):

```json
{
    "name": "John Smith",
    "password": "newpassword123",
    "password_confirmation": "newpassword123",
    "billing_type": "business",
    "company_name": "Acme Corp",
    "vat_number": "DE123456789",
    "address": "456 Business Ave",
    "country": "DE"
}
```

### Validation Rules

| Field | Type | Rules | Notes |
|-------|------|-------|-------|
| name | string | required, max:255 | Only if provided |
| password | string | min:8, confirmed | Only if provided |
| billing_type | string | in:private,business | nullable |
| company_name | string | max:255 | nullable |
| vat_number | string | max:50, special rule | nullable, cannot update if validated |
| address | string | max:255 | nullable |
| country | string | max:255 | nullable |

### Special Validation

- **VAT Number**: Cannot be updated if `vat_validated` is true
- **Password**: Must include `password_confirmation` field
- **Partial Updates**: Only fields included in request will be updated

### Response Examples

#### Success - Profile Updated
**Status: 200 OK**
```json
{
    "success": true,
    "message": "Profile updated successfully.",
    "data": {
        "user": {
            "id": 1,
            "name": "John Smith",
            "email": "john@example.com",
            "profile_type": "event",
            "account_type": "premium",
            "role": "user",
            "status": "active",
            "is_active": true,
            "email_verified_at": "2025-02-28T10:35:00.000000Z",
            "premium_started_at": "2025-02-28T11:00:00.000000Z",
            "billing_type": "business",
            "full_name": null,
            "company_name": "Acme Corp",
            "vat_number": "DE123456789",
            "vat_validated": false,
            "address": "456 Business Ave",
            "postal_code": "10115",
            "city": "Berlin",
            "country": "DE",
            "created_at": "2025-02-28T10:30:00.000000Z",
            "updated_at": "2025-02-28T12:30:00.000000Z"
        }
    }
}
```

#### Success - No Changes
**Status: 200 OK**
```json
{
    "success": true,
    "message": "No changes to update.",
    "data": {
        "user": { ... }
    }
}
```

#### Error - VAT Number Already Validated
**Status: 422 Unprocessable Entity**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "vat_number": [
            "The VAT number cannot be updated once it has been validated."
        ]
    }
}
```

#### Error - Password Confirmation Mismatch
**Status: 422 Unprocessable Entity**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "password_confirmation": [
            "The password confirmation does not match."
        ]
    }
}
```

#### Error - Not Authenticated
**Status: 401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

---

## Security Features

1. **Authentication Required**: All endpoints require valid Sanctum token
2. **Password Hashing**: Passwords are automatically hashed using `Hash::make()`
3. **Token Invalidation**: When password is updated, all existing tokens are revoked
4. **VAT Protection**: Validated VAT numbers cannot be modified
5. **Audit Logging**: All profile updates are logged with user ID and IP address
6. **Input Validation**: All inputs are validated using Form Request

---

## Usage Examples

### Update Name Only
```bash
curl -X PUT http://localhost:8001/api/user/profile \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"name": "New Name"}'
```

### Update Password
```bash
curl -X PUT http://localhost:8001/api/user/profile \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

### Update Billing Information
```bash
curl -X PUT http://localhost:8001/api/user/profile \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "billing_type": "business",
    "company_name": "My Company",
    "vat_number": "DE123456789",
    "address": "123 Business St",
    "country": "DE"
  }'
```

---

## Implementation Details

### Files Created/Modified

1. **Created**: `app/Http/Requests/UpdateProfileRequest.php` - Form request validation
2. **Created**: `app/Http/Controllers/UserProfileController.php` - Controller logic
3. **Modified**: `routes/api.php` - Added profile routes
4. **Created**: `USER_PROFILE_API.md` - This documentation

### Controller Features

- Uses Form Request for validation
- Implements partial updates (only provided fields are updated)
- Automatically hashes passwords
- Invalidates tokens on password change
- Includes audit logging
- Returns consistent JSON responses
- Handles errors gracefully

### Best Practices Applied

1. **Separation of Concerns**: Validation in Form Request, business logic in Controller
2. **RESTful Design**: GET for retrieve, PUT for update
3. **Consistent API**: Standardized JSON response structure
4. **Security**: Proper authentication and validation
5. **Logging**: Audit trail for profile changes
6. **Error Handling**: Proper HTTP status codes and error messages
