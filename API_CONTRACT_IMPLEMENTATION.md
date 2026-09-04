# ReferRace API v1 — Contract Implementation

The backend is aligned to the accepted REST contract under `/api/v1`.

## Authentication
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `POST /api/v1/auth/change-password`

Access tokens are signed JWT HS256 tokens with short lifetime (default 15 minutes). Refresh tokens are random opaque tokens stored only as SHA-256 hashes and issued in a Secure/HttpOnly/SameSite cookie. Refresh rotation and reuse detection are implemented.

## User
- `GET /api/v1/users/me`
- `PATCH /api/v1/users/me`
- `GET /api/v1/users/me/stats`
- `GET /api/v1/dashboard`

## Competitions
The existing internal `Game` model is retained to avoid a destructive database rename. It is exposed as the `competition` resource in the public API.

- `GET /api/v1/competitions`
- `GET /api/v1/competitions/{competition}`
- `POST /api/v1/competitions/{competition}/join`
- `GET /api/v1/competitions/{competition}/me`
- `GET /api/v1/competitions/{competition}/leaderboard`
- `GET /api/v1/competitions/{competition}/leaderboard/me`
- `GET /api/v1/competitions/{competition}/referral`
- `GET /api/v1/competitions/{competition}/referrals`

`POST /join` requires `Authorization: Bearer <access_token>` and `Idempotency-Key`. The server validates competition state, account phone, duplicate participation, referral-code uniqueness, external referral verification, and transactionally creates participation.

## Rewards
- `GET /api/v1/rewards`
- `GET /api/v1/rewards/balance`
- `GET /api/v1/rewards/history`
- `GET /api/v1/rewards/{reward}`
- `POST /api/v1/rewards/{reward}/claim`

## Admin
All admin endpoints require an authenticated JWT whose user has `role=admin`.

- `GET /api/v1/admin/dashboard`
- `GET|POST /api/v1/admin/competitions`
- `GET|PATCH|DELETE /api/v1/admin/competitions/{competition}`
- `GET /api/v1/admin/participants`
- `GET /api/v1/admin/participants/{participant}`
- `GET /api/v1/admin/referrals`
- `GET /api/v1/admin/referrals/{referral}`
- `PATCH /api/v1/admin/referrals/{referral}/status`
- `GET /api/v1/admin/rewards`
- `GET|POST /api/v1/admin/integrations`
- `PATCH|DELETE /api/v1/admin/integrations/{competition}`

## Database additions
- `refresh_tokens`: refresh-token rotation/revocation state.
- `rewards`: reward ledger and claims.
- `idempotency_keys`: safe retry support for state-changing operations.
- `yasuser.status`: referral status for moderation/admin operations.

Existing `games` and `game_user` tables are intentionally retained. This is a compatibility-first migration: public API terminology is Competition while internal legacy table/model names remain stable.

## Security notes
- Never commit `.env` or real credentials.
- Configure `APP_KEY`, `API_AUDIENCE`, `ACCESS_TOKEN_TTL`, `REFRESH_TOKEN_TTL_MINUTES`, `COOKIE_SECURE=true` in production.
- The frontend must send the access token as `Authorization: Bearer ...` and must not trust client-provided role, score, rank, reward amount, or user ID.
- Production should run only over HTTPS and should configure a strict CORS allow-list.
