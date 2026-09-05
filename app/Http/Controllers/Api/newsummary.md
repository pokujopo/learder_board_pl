Sasa nimeona **code halisi**. Hapa tunaweza kuwa exact kabisa bila kubashiri. Kuna point moja muhimu: kwenye code yako field ni **`phone`**, si `phone_number`, na `/join` inahitaji **`phone`, `referral_code`, na `consents` zote**.

Hii ndiyo contract ya kutumia kwenye frontend/Postman.

## 🔐 AUTH

### 1. Register

`POST /api/v1/auth/register`

**Bearer:** ❌ No

**Body:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "0712345678",
  "location": "Dar es Salaam",
  "password": "Password123",
  "password_confirmation": "Password123"
}
```

`location` ni optional.

**Success:** `201`

```json
{
  "status": 201,
  "message": "User registered successfully.",
  "data": {
    "user": {},
    "token": {
      "token": "...",
      "token_type": "Bearer"
    }
  }
}
```

**Errors:** `422` validation.

---

### 2. Login

`POST /api/v1/auth/login`

**Bearer:** ❌ No

**Body:**

```json
{
  "email": "john@example.com",
  "password": "Password123"
}
```

**Success:** `200`

**Error:**

```json
{
  "status": 401,
  "message": "Invalid email or password."
}
```

Pia `422` ikiwa validation imefail.

---

### 3. Refresh

`POST /api/v1/auth/refresh`

**Bearer:** ❌ No

**Refresh cookie:** ✅ Yes

**Body:** hakuna.

Browser lazima itume cookie `refresh_token`.

**Success:** `200`

```json
{
  "status": 200,
  "message": "Token refreshed.",
  "data": {
    "token": {
      "token": "...",
      "token_type": "Bearer"
    }
  }
}
```

**Errors:**

```text
401 Refresh token is required.
401 Invalid or expired refresh token.
401 Refresh token reuse detected. Please sign in again.
```

---

### 4. Logout

`POST /api/v1/auth/logout`

**Bearer:** 🔒 Yes

```http
Authorization: Bearer ACCESS_TOKEN
```

**Body:** hakuna.

**Success:** `200`

```json
{
  "status": 200,
  "message": "Logout successful."
}
```

---

### 5. Auth Me

`GET /api/v1/auth/me`

**Bearer:** 🔒 Yes

**Body:** hakuna.

**Success:** `200`

```json
{
  "status": 200,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "0712345678",
      "location": "Dar es Salaam",
      "role": "user",
      "email_verified_at": null
    }
  }
}
```

---

### 6. Forgot Password

`POST /api/v1/auth/forgot-password`

**Bearer:** ❌ No

**Body:**

```json
{
  "email": "john@example.com"
}
```

**Success:** `200`

```json
{
  "status": 200,
  "message": "If the account exists, password reset instructions will be sent."
}
```

---

### 7. Reset Password

`POST /api/v1/auth/reset-password`

**Bearer:** ❌ No

**Body:**

```json
{
  "email": "john@example.com",
  "token": "RESET_TOKEN",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

**Success:** `200`

```json
{
  "status": 200,
  "message": "Password reset successfully."
}
```

**Error:** `422 Unable to reset password.`

---

### 8. Change Password

`POST /api/v1/auth/change-password`

**Bearer:** 🔒 Yes

**Body:**

```json
{
  "current_password": "OldPassword123",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

**Success:** `200`

**Error:**

```json
{
  "status": 422,
  "message": "Current password is incorrect."
}
```

---

# 👤 USER

### 9. Get User

`GET /api/v1/users/me`

**Bearer:** 🔒 Yes

Body: none.

---

### 10. Update User

`PATCH /api/v1/users/me`

**Bearer:** 🔒 Yes

**Body:**

```json
{
  "name": "John Updated",
  "phone": "0712345678",
  "location": "Dar es Salaam"
}
```

All three are optional.

**Success:** `200`

Returns same structure as `/users/me`.

**Errors:** `422` validation, including duplicate phone.

---

### 11. User Stats

`GET /api/v1/users/me/stats`

**Bearer:** 🔒 Yes

Body: none.

Response:

```json
{
  "status": 200,
  "data": {
    "stats": {
      "competitions_joined": 2,
      "current_rankings": 2
    }
  }
}
```

---

# 🏠 DASHBOARD

### 12. Dashboard

`GET /api/v1/dashboard`

**Bearer:** 🔒 Yes

Body: none.

⚠️ `DashboardController` hujaituma kwenye code hii, kwa hiyo **response yake siwezi kusema exact** bila kuona controller hiyo.

---

# 🏆 COMPETITIONS

### 13. List competitions

`GET /api/v1/competitions`

**Bearer:** ❌ No

Body: none.

Optional query:

```text
?active_only=true&per_page=20
```

---

### 14. Competition details

`GET /api/v1/competitions/{game}`

**Bearer:** ❌ No

Body: none.

---

## ⭐ 15. JOIN COMPETITION

`POST /api/v1/competitions/{game}/join`

**Bearer:** 🔒 YES

```http
Authorization: Bearer ACCESS_TOKEN
Idempotency-Key: UNIQUE_RANDOM_KEY
Content-Type: application/json
```

### Body — EXACT kutoka kwenye controller yako

```json
{
  "phone": "0712345678",
  "referral_code": "ABC123",
  "consents": {
    "terms": true,
    "sms": true,
    "future_competitions": true
  }
}
```

`terms` ni **required**.

`sms` na `future_competitions` ni optional.

### Muhimu sana

**Hakuna OTP.**

Lakini phone lazima iwe:

```text
phone kwenye account = phone inayotumwa kwenye join
```

Backend inafanya normalization kabla ya comparison.

### Success `201`

```json
{
  "status": 201,
  "message": "Competition joined successfully.",
  "data": {
    "competition": {},
    "participation": {
      "id": 1,
      "referral_code": "ABC123",
      "verified": true,
      "joined_at": "2026-09-04T..."
    }
  }
}
```

### Errors

```text
400 Idempotency-Key header is required.

409 This competition is not currently accepting joins.

422 The phone number must match your account phone number.

409 You are already registered for this competition.

409 This referral code has already been used.

409 This referral code has already been used or the participation already exists.

503 Unable to verify referral code at this time.
```

Pia validation inaweza kurudisha `422` kwa:

* phone missing
* referral_code missing
* invalid referral code format
* consents missing
* terms missing/not accepted

---

### 16. My competition participation

`GET /api/v1/competitions/{game}/me`

**Bearer:** 🔒 Yes

Body: none.

Kama haja-join:

```json
{
  "status": 404,
  "message": "You have not joined this competition."
}
```

---

### 17. Leaderboard

`GET /api/v1/competitions/{game}/leaderboard`

**Bearer:** ❌ **No kwenye route/controller uliyonipa.**

Hii ni important.

Body: none.

Returns:

```json
{
  "status": 200,
  "data": {
    "competition": {},
    "leaderboard": []
  }
}
```

Kwa hiyo kwa **current backend**, leaderboard hii haijawekewa `JwtAuthMiddleware`.

---

### 18. My leaderboard

`GET /api/v1/competitions/{game}/leaderboard/me`

**Bearer:** 🔒 Yes

Body: none.

Kama hajajiunga:

`404 You have not joined this competition.`

---

### 19. My referral

`GET /api/v1/competitions/{game}/referral`

**Bearer:** 🔒 Yes

Body: none.

Response:

```json
{
  "status": 200,
  "data": {
    "referral_code": "ABC123",
    "verified": true
  }
}
```

---

### 20. My referrals

`GET /api/v1/competitions/{game}/referrals`

**Bearer:** 🔒 Yes

Body: none.

Response:

```json
{
  "status": 200,
  "data": {
    "referrals": [
      {
        "refer_code": "ABC123",
        "name": "John",
        "invitor_number": 10,
        "last_synced_at": "..."
      }
    ]
  }
}
```

---

# 💰 REWARDS

### 21. Rewards

`GET /api/v1/rewards`

**Bearer:** 🔒 Yes

Body: none.

---

### 22. Balance

`GET /api/v1/rewards/balance`

**Bearer:** 🔒 Yes

Body: none.

Exact response:

```json
{
  "status": 200,
  "data": {
    "balance": 150000,
    "currency": "TZS"
  }
}
```

---

### 23. Reward history

`GET /api/v1/rewards/history`

**Bearer:** 🔒 Yes

Body: none.

---

### 24. Single reward

`GET /api/v1/rewards/{reward}`

**Bearer:** 🔒 Yes

Body: none.

Kama reward si ya huyo user:

`404`

---

### 25. Claim reward

`POST /api/v1/rewards/{reward}/claim`

**Bearer:** 🔒 Yes

**Body:** hakuna.

```http
POST /api/v1/rewards/123/claim
Authorization: Bearer ACCESS_TOKEN
```

Success:

```json
{
  "status": 200,
  "message": "Reward claimed successfully.",
  "data": {
    "reward": {}
  }
}
```

Error:

```json
{
  "status": 409,
  "message": "Reward is not available for claim."
}
```

---

# 👑 ADMIN

**Kila endpoint hapa:**

```http
Authorization: Bearer ADMIN_ACCESS_TOKEN
```

Na lazima `role=admin`.

---

### 26. Admin dashboard

`GET /api/v1/admin/dashboard`

**Bearer:** 🔐 ADMIN

Body: none.

---

### 27. Admin competitions

`GET /api/v1/admin/competitions`

**Bearer:** 🔐 ADMIN

Body: none.

Optional:

```text
?per_page=20
```

---

### 28. Create competition

`POST /api/v1/admin/competitions`

**Bearer:** 🔐 ADMIN

**EXACT BODY:**

```json
{
  "name": "Referral Race September",
  "code": "RR-SEPT-2026",
  "is_active": true,
  "external_api_base_url": "https://api.example.com",
  "start_at": "2026-09-01 00:00:00",
  "end_at": "2026-09-30 23:59:59",
  "first_prize": 500000,
  "second_prize": 300000,
  "third_prize": 100000,
  "competition_rules": "Competition rules here",
  "winning_instructions": "Winning instructions here"
}
```

Required zote isipokuwa `is_active`.

---

### 29. Admin competition details

`GET /api/v1/admin/competitions/{game}`

**Bearer:** 🔐 ADMIN

Body: none.

---

### 30. Update competition

`PATCH /api/v1/admin/competitions/{game}`

**Bearer:** 🔐 ADMIN

Fields zote ni optional:

```json
{
  "name": "Updated Race",
  "code": "RR-UPDATED",
  "is_active": true,
  "external_api_base_url": "https://api.example.com",
  "start_at": "2026-09-01 00:00:00",
  "end_at": "2026-09-30 23:59:59",
  "first_prize": 600000,
  "second_prize": 350000,
  "third_prize": 150000,
  "competition_rules": "Updated rules",
  "winning_instructions": "Updated instructions"
}
```

---

### 31. Delete/deactivate competition

`DELETE /api/v1/admin/competitions/{game}`

**Bearer:** 🔐 ADMIN

Body: none.

⚠️ Hii **haifuti record**. Inafanya:

```text
is_active = false
```

Response:

```json
{
  "status": 200,
  "message": "Competition deactivated successfully."
}
```

---

# 👥 ADMIN PARTICIPANTS

### 32.

`GET /api/v1/admin/participants`

🔐 Admin Bearer.

Optional:

```text
?per_page=20&competition_id=PUBLIC_COMPETITION_ID
```

Body none.

### 33.

`GET /api/v1/admin/participants/{participant}`

🔐 Admin Bearer.

Body none.

---

# 🔗 ADMIN REFERRALS

### 34.

`GET /api/v1/admin/referrals`

🔐 Admin Bearer.

Optional:

```text
?per_page=20&competition_id=PUBLIC_COMPETITION_ID
```

Body none.

### 35.

`GET /api/v1/admin/referrals/{referral}`

🔐 Admin Bearer.

Body none.

### 36. Change referral status

`PATCH /api/v1/admin/referrals/{referral}/status`

🔐 Admin Bearer.

**Body:**

```json
{
  "status": "verified"
}
```

Allowed:

```text
active
blocked
verified
```

---

# 💵 ADMIN REWARDS

### 37.

`GET /api/v1/admin/rewards`

🔐 Admin Bearer.

Body none.

Optional:

```text
?per_page=20
```

---

# 🔌 ADMIN INTEGRATIONS

### 38.

`GET /api/v1/admin/integrations`

🔐 Admin Bearer.

Body none.

---

### 39. Create/update integration

`POST /api/v1/admin/integrations`

🔐 Admin Bearer.

**Body:**

```json
{
  "competition_id": "PUBLIC_COMPETITION_ID",
  "external_api_base_url": "https://api.example.com"
}
```

**Success:** `201`

---

### 40. Update integration

`PATCH /api/v1/admin/integrations/{game}`

🔐 Admin Bearer.

**Body:**

```json
{
  "external_api_base_url": "https://api.example.com",
  "is_active": true
}
```

`external_api_base_url` ni required.

---

### 41. Delete integration

`DELETE /api/v1/admin/integrations/{game}`

🔐 Admin Bearer.

Body: none.

Inafanya:

```text
external_api_base_url = null
```

Response:

```json
{
  "status": 200,
  "message": "Integration removed successfully."
}
```

---

## ⚠️ Kuna vitu 3 muhimu nimegundua kwenye code yako

### 1. Leaderboard si protected

Umesema kwenye requirement yako kwamba pages fulani zinahitaji user awe ameingia/amejiunga. Lakini route yako sasa ni:

```php
Route::get('leaderboard',[CompetitionController::class,'leaderboard']);
```

na haina `JwtAuthMiddleware`.

Kwa hiyo **current backend inaruhusu leaderboard bila Bearer**.

### 2. `/competitions/{game}` pia ni public

Hii ni sawa kama unataka mtu aone details kabla ya kujoin.

Lakini:

```text
/competitions/{game}/me
/referral
/referrals
leaderboard/me
```

zote ziko protected.

### 3. `/join` ndiyo endpoint yenye requirements nyingi zaidi

Frontend lazima itume:

```http
Authorization: Bearer ACCESS_TOKEN
Idempotency-Key: UNIQUE_KEY
Content-Type: application/json
```

na:

```json
{
  "phone": "0712345678",
  "referral_code": "ABC123",
  "consents": {
    "terms": true
  }
}
```

**Hakuna OTP kabisa.**

Na kwa backend yako ya sasa, `phone` ndiyo field sahihi — **usiweke `phone_number` kwenye frontend join request**.

Kwa ujumla, sasa tunaweza kutumia hii kama **source of truth ya ku-connect frontend**, kwa sababu hapa nimetumia validation na route middleware zilizo kwenye code uliyonipa, si contract ya kubahatisha.
