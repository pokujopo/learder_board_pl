Ndiyo. Na kwa kuwa unataka **body halisi za backend yako**, si vizuri nikazibuni kutoka kwenye route list pekee. Nimeangalia contract/backend context tuliyokuwa nayo; route list inathibitisha endpoints, lakini **GET endpoints hazina body**, na fields za POST/PATCH zinapaswa kufuata validation ya controllers/Form Requests.

Kwa route list uliyonipa, hii ndiyo breakdown ya kutumia kwenye Postman/frontend:

### 🔐 Legend

* **PUBLIC** = Bearer haihitajiki
* **USER 🔒** = `Authorization: Bearer <access_token>`
* **ADMIN 🔐** = Bearer ya admin + `role=admin`
* **COOKIE 🍪** = refresh token iko kwenye HttpOnly cookie
* **ID** = `{game}`, `{reward}`, `{participant}`, `{referral}` ni identifier ya resource

---

## 1. AUTHENTICATION

| Method | Endpoint                | Auth      | Body    |
| ------ | ----------------------- | --------- | ------- |
| POST   | `/auth/register`        | PUBLIC    | **YES** |
| POST   | `/auth/login`           | PUBLIC    | **YES** |
| POST   | `/auth/refresh`         | COOKIE 🍪 | **NO**  |
| POST   | `/auth/logout`          | USER 🔒   | NO      |
| GET    | `/auth/me`              | USER 🔒   | NO      |
| POST   | `/auth/forgot-password` | PUBLIC    | YES     |
| POST   | `/auth/reset-password`  | PUBLIC    | YES     |
| POST   | `/auth/change-password` | USER 🔒   | YES     |

### Register

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone_number": "0712345678",
  "location": "Dar es Salaam",
  "password": "Password123!",
  "password_confirmation": "Password123!"
}
```

**Success:** `201`

Returns user + access token.

**Possible errors:**

* `422` validation
* `409` duplicate email/phone
* `429` too many requests

---

### Login

```json
{
  "email": "john@example.com",
  "password": "Password123!"
}
```

**Success:** `200`

```json
{
  "status": 200,
  "message": "Login successful",
  "user": {},
  "access_token": "<JWT>",
  "token_type": "Bearer",
  "expires_in": 900
}
```

**Errors:**

* `401` invalid credentials
* `403` account disabled
* `422` validation
* `429` rate limit

---

### Refresh

```http
POST /api/v1/auth/refresh
```

**Auth:** 🍪 Refresh-token cookie.

**Body:** none.

**Success:**

```json
{
  "status": 200,
  "message": "Token refreshed successfully.",
  "access_token": "<new JWT>",
  "token_type": "Bearer",
  "expires_in": 900
}
```

**Errors:**

* `401` Refresh token is required
* `401` invalid/expired/revoked refresh token
* `401` refresh-token reuse detected
* `403` account disabled

---

### Logout

```http
Authorization: Bearer <access_token>
```

Body: none.

Success:

```json
{
  "status": 200,
  "message": "Logout successful"
}
```

---

### Me

```http
GET /api/v1/auth/me
Authorization: Bearer <access_token>
```

Body: none.

Success:

```json
{
  "status": 200,
  "user": {}
}
```

---

### Forgot password

```json
{
  "email": "john@example.com"
}
```

**PUBLIC**

Success:

```json
{
  "status": 200,
  "message": "If an account exists, password reset instructions have been sent."
}
```

---

### Reset password

```json
{
  "token": "RESET_TOKEN",
  "email": "john@example.com",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

**PUBLIC**

Errors mainly `400/401/422`.

---

### Change password

**USER 🔒**

```json
{
  "current_password": "OldPassword123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

Success:

```json
{
  "status": 200,
  "message": "Password changed successfully."
}
```

---

# 2. USER APIs

### GET `/users/me`

🔒 **USER Bearer**

Body: none.

---

### PATCH `/users/me`

🔒 **USER Bearer**

```json
{
  "name": "John Updated",
  "phone_number": "0712345678",
  "location": "Dar es Salaam"
}
```

Usitume:

```text
role
id
score
rank
balance
reward
```

Success:

```json
{
  "status": 200,
  "message": "Profile updated successfully.",
  "user": {}
}
```

Errors: `401`, `409`, `422`.

---

### GET `/users/me/stats`

🔒 **USER Bearer**

Body: none.

Returns statistics za user.

---

# 3. DASHBOARD

### GET `/dashboard`

🔒 **USER Bearer**

Body: none.

Success mfano:

```json
{
  "status": 200,
  "data": {
    "user": {},
    "active_competitions": [],
    "total_referrals": 24,
    "total_winnings": 150000,
    "current_rank": 12,
    "recent_activity": []
  }
}
```

`401` ikiwa user haja-login.

---

# 4. COMPETITIONS

### GET `/competitions`

**PUBLIC**

Body: none.

Returns competitions zinazoweza kuonekana kwa user.

---

### GET `/competitions/{game}`

**PUBLIC**

Body: none.

`{game}` = competition/game identifier.

---

### POST `/competitions/{game}/join`

Hii ndiyo **muhimu sana** kwenye project yako.

🔒 **USER Bearer REQUIRED**

Na lazima:

```http
Idempotency-Key: unique-key
```

Body:

```json
{
  "phone_number": "0712345678",
  "referral_code": "ABC123"
}
```

Ikiwa backend yako imeweka consent fields:

```json
{
  "phone_number": "0712345678",
  "referral_code": "ABC123",
  "consents": {
    "terms": true
  }
}
```

**Muhimu:** hakuna OTP.

User anaandika **phone number**, lakini verification/referral business logic inafanywa backend.

Success:

```json
{
  "status": 201,
  "message": "Successfully joined competition.",
  "data": {}
}
```

Errors muhimu:

```text
401 → not logged in
403 → not eligible
404 → competition not found
409 → already joined / duplicate / competition closed
422 → invalid phone/referral
429 → too many attempts
503 → external verification unavailable
```

---

### GET `/competitions/{game}/me`

🔒 **USER Bearer**

Body: none.

Returns participation ya current user.

Mfano:

```json
{
  "status": 200,
  "joined": true,
  "data": {
    "participation_id": "part_xxx",
    "competition_id": "comp_xxx",
    "rank": 17,
    "referrals": 24,
    "verified_referrals": 19,
    "score": 240
  }
}
```

---

### GET `/competitions/{game}/leaderboard`

🔒 **USER Bearer** ikiwa leaderboard imefungwa kwa participants.

Body: none.

Optional query:

```text
?page=1&limit=50
```

Mfano:

```json
{
  "status": 200,
  "data": [],
  "meta": {
    "page": 1,
    "limit": 50,
    "total": 100
  }
}
```

---

### GET `/competitions/{game}/leaderboard/me`

🔒 **USER Bearer**

Body: none.

Returns rank ya current user.

```json
{
  "status": 200,
  "data": {
    "rank": 17,
    "referrals": 24,
    "score": 240
  }
}
```

---

### GET `/competitions/{game}/referral`

🔒 **USER Bearer**

Body: none.

Returns referral code/link ya user.

```json
{
  "status": 200,
  "data": {
    "competition_id": "comp_xxx",
    "code": "ABC123",
    "url": "https://yourdomain.com/join/ABC123"
  }
}
```

---

### GET `/competitions/{game}/referrals`

🔒 **USER Bearer**

Body: none.

Optional:

```text
?page=1&limit=20&status=verified
```

Returns referrals wa current participant.

---

# 5. REWARDS

### GET `/rewards`

🔒 **USER Bearer**

Body: none.

---

### GET `/rewards/balance`

🔒 **USER Bearer**

Body: none.

Returns:

```json
{
  "status": 200,
  "data": {
    "available": 150000,
    "pending": 50000,
    "claimed": 300000,
    "currency": "TZS"
  }
}
```

---

### GET `/rewards/history`

🔒 **USER Bearer**

Body: none.

Optional:

```text
?page=1&limit=20
```

---

### GET `/rewards/{reward}`

🔒 **USER Bearer**

Body: none.

---

### POST `/rewards/{reward}/claim`

🔒 **USER Bearer**

Body:

```json
{}
```

Usitume amount kutoka frontend.

Backend ndiyo iamue amount.

Success:

```json
{
  "status": 200,
  "message": "Reward claim submitted successfully.",
  "data": {
    "reward_id": "reward_xxx",
    "amount": 150000,
    "currency": "TZS",
    "status": "pending"
  }
}
```

Errors:

```text
401
403
404
409
422
429
```

---

# 6. ADMIN APIs

**KILA moja hapa inahitaji:**

```http
Authorization: Bearer <ADMIN_ACCESS_TOKEN>
```

Na backend lazima ithibitishe:

```text
user.role === admin
```

Frontend haiwezi kuamua admin.

---

### GET `/admin/dashboard`

🔐 **ADMIN**

Body: none.

Returns admin statistics.

---

### GET `/admin/competitions`

🔐 **ADMIN**

Body: none.

Optional:

```text
?page=1&limit=20&status=active
```

---

### POST `/admin/competitions`

🔐 **ADMIN**

Body:

```json
{
  "name": "Referral Race",
  "description": "Competition description",
  "starts_at": "2026-09-01T10:00:00Z",
  "ends_at": "2026-09-30T18:00:00Z",
  "rules": {},
  "prizes": []
}
```

Success `201`.

---

### GET `/admin/competitions/{game}`

🔐 **ADMIN**

Body: none.

---

### PATCH `/admin/competitions/{game}`

🔐 **ADMIN**

Body:

```json
{
  "name": "Updated Competition",
  "description": "Updated description",
  "status": "active",
  "starts_at": "2026-09-01T10:00:00Z",
  "ends_at": "2026-09-30T18:00:00Z"
}
```

---

### DELETE `/admin/competitions/{game}`

🔐 **ADMIN**

Body: none.

Success:

```text
204 No Content
```

---

# 7. ADMIN PARTICIPANTS

### GET `/admin/participants`

🔐 **ADMIN**

Body: none.

Query:

```text
?page=1&limit=50&competition_id=1
```

---

### GET `/admin/participants/{participant}`

🔐 **ADMIN**

Body: none.

---

# 8. ADMIN REFERRALS

### GET `/admin/referrals`

🔐 **ADMIN**

Query:

```text
?page=1&limit=50&status=pending&competition_id=1
```

Body: none.

---

### GET `/admin/referrals/{referral}`

🔐 **ADMIN**

Body: none.

---

### PATCH `/admin/referrals/{referral}/status`

🔐 **ADMIN**

Body:

```json
{
  "status": "verified",
  "reason": "External verification passed."
}
```

Possible statuses should follow backend's defined enum, e.g.:

```text
pending
verified
rejected
```

---

# 9. ADMIN REWARDS

### GET `/admin/rewards`

🔐 **ADMIN**

Query:

```text
?page=1&limit=50&status=pending&competition_id=1
```

Body: none.

---

# 10. ADMIN INTEGRATIONS

### GET `/admin/integrations`

🔐 **ADMIN**

Body: none.

---

### POST `/admin/integrations`

🔐 **ADMIN**

Body depends on integration provider. General structure:

```json
{
  "provider": "sms",
  "name": "SMS Provider",
  "credentials": {
    "api_key": "SECRET"
  }
}
```

**IMPORTANT:** secret haipaswi kurudishwa kwenye GET response.

---

### PATCH `/admin/integrations/{game}`

🔐 **ADMIN**

Body mfano:

```json
{
  "name": "Updated SMS Provider",
  "credentials": {
    "api_key": "NEW_SECRET"
  }
}
```

---

### DELETE `/admin/integrations/{game}`

🔐 **ADMIN**

Body: none.

Success:

```text
204 No Content
```

---

# 🔥 MUHTASARI WA AUTHORIZATION

```text
PUBLIC
│
├── POST /auth/register
├── POST /auth/login
├── POST /auth/forgot-password
├── POST /auth/reset-password
├── GET  /competitions
└── GET  /competitions/{game}

USER BEARER
│
├── auth/logout
├── auth/me
├── auth/change-password
├── users/me
├── users/me/stats
├── dashboard
├── competitions/{game}/join
├── competitions/{game}/me
├── competitions/{game}/leaderboard
├── competitions/{game}/leaderboard/me
├── competitions/{game}/referral
├── competitions/{game}/referrals
└── rewards/*

REFRESH COOKIE
│
└── POST /auth/refresh

ADMIN BEARER
│
├── admin/dashboard
├── admin/competitions/*
├── admin/participants/*
├── admin/referrals/*
├── admin/rewards
└── admin/integrations/*
```

**Point muhimu sana:** route list yako bado ina `{game}` kwenye public URL badala ya `{competition}`, na integrations pia ina `{game}`. Hii **haivunji route yenyewe**, lakini kwa contract mpya ningependekeza tuibadilishe kuwa `{competition}` na `{integration}` kwa consistency.

Pia, kwa **body za 100% exact kulingana na Laravel code yako**, ningependa kutumia files za backend yenyewe (hasa `AuthController`, `CompetitionController`, `AdminController`, `RewardController`, `UserController` na `routes/api.php`) badala ya kutegemea route list. Hapo naweza kukupa **request body + exact response + exact error ya kila endpoint bila kubashiri hata field moja**.
