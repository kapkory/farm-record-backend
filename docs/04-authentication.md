# Authentication

Laravel Sanctum SPA authentication with cookie-based sessions.

## Flow

```
1. GET /sanctum/csrf-cookie  → Sets XSRF-TOKEN cookie
2. POST /login               → Creates session, sets cookie
3. GET /api/v1/*            → Include session cookie
```

## Endpoints

### Get CSRF Cookie
```http
GET /sanctum/csrf-cookie
```

### Register
```http
POST /register
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+254712345678",
    "password": "SecurePassword123!",
    "farm_name": "Green Valley Farm",
    "farm_type": "individual"  // individual, group, organization
}
```
Response: `204 No Content` (auto-logged in)

### Login
```http
POST /login
{
    "email": "john@example.com",
    "password": "SecurePassword123!"
}
```
Response: `204 No Content`

### Logout
```http
POST /logout
```

### Get Current User
```http
GET /api/user
```
Response:
```json
{
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com"
}
```

### Password Reset
```http
POST /forgot-password
{"email": "john@example.com"}

POST /reset-password
{
    "token": "...",
    "email": "john@example.com",
    "password": "NewPassword123!",
    "password_confirmation": "NewPassword123!"
}
```

## Nuxt.js Integration

```typescript
const csrf = async () => {
  await $fetch('/sanctum/csrf-cookie', {
    baseURL: 'http://localhost:8000',
    credentials: 'include'
  })
}

const login = async (email: string, password: string) => {
  await csrf()
  return $fetch('/login', {
    method: 'POST',
    baseURL: 'http://localhost:8000',
    credentials: 'include',  // Required!
    body: { email, password }
  })
}
```

## Troubleshooting

| Error | Solution |
|-------|----------|
| CSRF token mismatch | Call `/sanctum/csrf-cookie` first |
| Unauthenticated | Add `credentials: 'include'` to requests |
| CORS error | Check `FRONTEND_URL` and `supports_credentials` |

