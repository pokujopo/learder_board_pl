<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class register_login_api extends Controller
{
    public function register_api(Request $request) {

    $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:20',
                'unique:users,phone_number',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'phone_number' => $validated['phone_number'],
        'location' => $validated['location'],
        'role' => 'user',

    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    return response()->json([
        'status' => 201,
        'message' => 'User registered successfully',
        'user' => $user,
        'token' => $token,
    ], 201);
}
    public function login_api(Request $request) {

    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'status' => 401,
            'message' => 'Invalid email or password',
        ], 401);
    }

    $user->tokens()
        ->where('name', 'auth-token')
        ->delete();

    $token = $user->createToken('auth-token')->plainTextToken;

    return response()->json([
        'status' => 200,
        'message' => 'Login successful',
        'user' => $user,
        'token' => $token,
    ]);
}

    public function logout_api(Request $request) {

    $user = $request->user();

    if (!$user) {
        return response()->json([
            'status' => 401,
            'message' => 'Unauthenticated'
        ], 401);
    }

    $user->currentAccessToken()->delete();

    return response()->json([
        'status' => 200,
        'message' => 'Logout successful'
    ]);
}


}
