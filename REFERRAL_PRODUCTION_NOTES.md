# Referral verification production notes

## External API contract

The competition stores the **base URL only**. If the external route is:

`POST /api/yas/{refercode}`

store:

`https://external-domain.example/api/yas`

The application appends the normalized referral code and sends POST.

## Expected response

```json
{
  "status": 200,
  "customer_all": {
    "refer_code": "abc823",
    "customer_name": "john doe",
    "invitor_number": 200000000000
  }
}
```

The backend normalizes referral codes to uppercase and requires the returned `refer_code` to match the requested code. It stores the code as uppercase.

## External endpoint recommendation

The external service should also normalize the route parameter so direct lowercase requests work:

```php
Route::post('/yas/{refercode}', function (string $refercode) {
    $refercode = strtoupper(trim($refercode));

    $all_customer = [
        'ABC823' => [
            'refer_code' => 'abc823',
            'customer_name' => 'john doe',
            'invitor_number' => 30000,
        ],
        'ABC120' => [
            'refer_code' => 'abc120',
            'customer_name' => 'jo de',
            'invitor_number' => 98000000000,
        ],
        'ABC999' => [
            'refer_code' => 'abc999',
            'customer_name' => 'Test User',
            'invitor_number' => 2340000000,
        ],
        'ABC270' => [
            'refer_code' => 'abc270',
            'customer_name' => 'Te User',
            'invitor_number' => 200000000000,
        ],
        'ABC83' => [
            'refer_code' => 'abc83',
            'customer_name' => 'john doe',
            'invitor_number' => 30000,
        ],
        'ABC10' => [
            'refer_code' => 'abc10',
            'customer_name' => 'jo de',
            'invitor_number' => 98000000000,
        ],
        'ABC99' => [
            'refer_code' => 'abc99',
            'customer_name' => 'Test User',
            'invitor_number' => 2340000000,
        ],
        'ABC20' => [
            'refer_code' => 'abc20',
            'customer_name' => 'Te User',
            'invitor_number' => 200000000000,
        ],
    ];

    if (!isset($all_customer[$refercode])) {
        return response()->json([
            'status' => 404,
            'message' => 'Refercode not found',
        ], 404);
    }

    return response()->json([
        'status' => 200,
        'customer_all' => $all_customer[$refercode],
    ]);
});
```

## Important database fix

`total_inviter_number` is now `UNSIGNED BIGINT`. Your test data contains values such as `200000000000`, which cannot fit in a normal 32-bit unsigned integer.

## Join flow

`POST /api/v1/competitions/{public_id}/join` remains the authoritative registration endpoint. It verifies the referral code again before creating participation, so a preflight verification cannot bypass the final server-side check.

`POST /api/v1/competitions/{public_id}/verify-refercode` is available for the frontend's Verify button. It verifies only; it does not register the user.

A legacy-compatible `/api/v1/games/{public_id}/verify-refercode` route is also kept for older clients.
