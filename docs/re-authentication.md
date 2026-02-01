# Re-Authentication Pattern

This document describes the re-authentication pattern used in this application to protect sensitive actions from unauthorized access, even when a user's session is compromised.

## Overview

Re-authentication requires users to confirm their password before performing sensitive actions. Once confirmed, the password is valid for a configurable period (default: 3 hours) before requiring re-confirmation.

This pattern uses Laravel's built-in `password.confirm` middleware, which works seamlessly with Laravel Fortify's password confirmation endpoints.

## Configuration

The password confirmation timeout is configured in `config/auth.php`:

```php
'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800), // 3 hours in seconds
```

## Actions Requiring Re-Authentication

The following actions currently require password confirmation:

### User Management (Tenant)
| Action | Route | Method | Description |
|--------|-------|--------|-------------|
| Update user role | `/api/users/{user}` | PATCH | Changing a user's role in the tenant |
| Remove user | `/api/users/{user}` | DELETE | Removing a user from the tenant |

### Future Actions (To Be Implemented)
The following actions should require re-authentication when their routes are added:

| Category | Action | Rationale |
|----------|--------|-----------|
| **Payroll** | Process payroll | Financial impact, irreversible |
| **Payroll** | Modify tax tables | Financial compliance |
| **Payroll** | Modify contribution tables | Financial compliance |
| **Settings** | Change tenant settings | Organization-wide impact |
| **Records** | Delete employee records | Data loss prevention |
| **Records** | Delete payroll records | Data loss prevention |
| **Profile** | Change own email | Account security |
| **Profile** | Change own password | Account security |

## How It Works

### Backend Flow

1. User initiates a sensitive action (e.g., change user role)
2. The `password.confirm` middleware checks if the password was recently confirmed
3. If not confirmed (or expired), returns `423 Locked` status with `password_confirmation_required` message
4. Frontend displays password confirmation modal
5. User submits password to `/user/confirm-password` (Fortify endpoint)
6. On success, session is updated with confirmation timestamp
7. User retries the original action, which now succeeds

### Frontend Integration

The application provides a `PasswordConfirmationModal.vue` component and `usePasswordConfirmation` composable for consistent UX:

```vue
<script setup>
import { usePasswordConfirmation } from '@/composables/usePasswordConfirmation';
import PasswordConfirmationModal from '@/Components/PasswordConfirmationModal.vue';

const { confirmPassword, showModal, closeModal, isConfirmed } = usePasswordConfirmation();

async function handleSensitiveAction() {
  const confirmed = await confirmPassword();
  if (confirmed) {
    // Proceed with the action
    await updateUserRole();
  }
}
</script>

<template>
  <button @click="handleSensitiveAction">Change Role</button>
  <PasswordConfirmationModal
    v-model:open="showModal"
    @confirmed="closeModal"
  />
</template>
```

## Adding Re-Authentication to New Features

### Step 1: Apply Middleware to Route

Add the `password.confirm` middleware to your route in the appropriate routes file:

```php
// routes/tenant.php or routes/api.php
Route::patch('/sensitive-action', [Controller::class, 'action'])
    ->middleware('password.confirm')
    ->name('api.sensitive.action');
```

### Step 2: Handle 423 Response in Frontend

When your API call receives a 423 response, trigger the password confirmation flow:

```typescript
import { usePasswordConfirmation } from '@/composables/usePasswordConfirmation';

const { confirmPassword } = usePasswordConfirmation();

async function performAction() {
  try {
    await api.post('/sensitive-action', data);
  } catch (error) {
    if (error.response?.status === 423) {
      const confirmed = await confirmPassword();
      if (confirmed) {
        // Retry the action
        await api.post('/sensitive-action', data);
      }
    }
  }
}
```

### Step 3: Update This Documentation

Add your new sensitive action to the "Actions Requiring Re-Authentication" table above.

## Testing Re-Authentication

When writing tests for routes with `password.confirm` middleware:

```php
it('requires password confirmation for sensitive action', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    // Without confirmation - should return 423
    $response = $this->actingAs($user)
        ->postJson('/api/sensitive-action', $data);

    $response->assertStatus(423);
});

it('allows action after password confirmation', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    // Confirm password first
    $this->actingAs($user)
        ->postJson(route('password.confirm.store'), ['password' => 'password'])
        ->assertStatus(201);

    // Now action should succeed
    $response = $this->actingAs($user)
        ->postJson('/api/sensitive-action', $data);

    $response->assertSuccessful();
});
```

## Security Considerations

1. **Timeout Duration**: The 3-hour timeout balances security with user experience. Consider shorter timeouts for highly sensitive environments.

2. **Session Binding**: Password confirmation is stored in the session. If sessions are invalidated, users must re-confirm.

3. **Rate Limiting**: The password confirmation endpoint should be rate-limited to prevent brute force attacks. Fortify handles this by default.

4. **Audit Logging**: Consider logging all sensitive actions that required re-authentication for audit purposes.

## Related Files

- `config/auth.php` - Password timeout configuration
- `routes/tenant.php` - Routes with `password.confirm` middleware
- `resources/js/Components/PasswordConfirmationModal.vue` - Modal component
- `resources/js/composables/usePasswordConfirmation.ts` - Composable for password confirmation flow
- `tests/Feature/ReAuthenticationMiddlewareTest.php` - Test coverage
