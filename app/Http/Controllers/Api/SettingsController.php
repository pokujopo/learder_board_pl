<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get authenticated user's settings.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $settings = $user->settings()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                    'location' => $user->location,
                    'avatar_url' => $settings->avatar_url,
                ],

                'payout' => [
                    'provider' => $settings->payout_provider,
                    'account_number' => $settings->payout_account_number,
                    'account_name' => $settings->payout_account_name,
                ],

                'notifications' => [
                    'email' => $settings->email_notifications,
                    'whatsapp' => $settings->whatsapp_notifications,
                ],
            ],
        ]);
    }

    /**
     * Update authenticated user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:100',
            ],

            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'phone' => [
                'sometimes',
                'string',
                'min:7',
                'max:30',
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],

            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | Update User Profile
        |--------------------------------------------------------------------------
        */

        $userData = [];

        if (array_key_exists('name', $data)) {
            $userData['name'] = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $userData['email'] = $data['email'];
        }

        if (array_key_exists('phone', $data)) {
            $userData['phone_number'] = $data['phone'];
        }

        if (array_key_exists('location', $data)) {
            $userData['location'] = $data['location'];
        }

        if ($userData !== []) {
            $user->update($userData);
        }

        /*
        |--------------------------------------------------------------------------
        | Avatar
        |--------------------------------------------------------------------------
        */

        $settings = $user->settings()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        if ($request->hasFile('avatar')) {
            /*
             * Delete old avatar if it belongs to our public disk.
             */
            if ($settings->avatar_url) {
                $oldPath = str_replace('/storage/', '', $settings->avatar_url);

                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            /*
             * Store new avatar.
             */
            $path = $request->file('avatar')->store('avatars', 'public');

            $settings->update([
                'avatar_url' => '/storage/' . $path,
            ]);
        }

        $user->refresh();
        $settings->refresh();

        return response()->json([
            'status' => 200,
            'message' => 'Profile updated successfully.',
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                    'location' => $user->location,
                    'avatar_url' => $settings->avatar_url,
                ],
            ],
        ]);
    }

    /**
     * Update authenticated user's payout method.
     */
    public function updatePayout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => [
                'required',
                'string',
                'max:100',
            ],

            'account_number' => [
                'required',
                'string',
                'max:50',
            ],

            'account_name' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $settings = $user->settings()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $settings->update([
            'payout_provider' => $request->string('provider')->toString(),
            'payout_account_number' => $request->string('account_number')->toString(),
            'payout_account_name' => $request->string('account_name')->toString(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Payout settings updated successfully.',
            'data' => [
                'payout' => [
                    'provider' => $settings->payout_provider,
                    'account_number' => $settings->payout_account_number,
                    'account_name' => $settings->payout_account_name,
                ],
            ],
        ]);
    }

    /**
     * Update authenticated user's notification preferences.
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'boolean',
            ],

            'whatsapp' => [
                'required',
                'boolean',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $settings = $user->settings()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $settings->update([
            'email_notifications' => $request->boolean('email'),
            'whatsapp_notifications' => $request->boolean('whatsapp'),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Notification settings updated successfully.',
            'data' => [
                'notifications' => [
                    'email' => $settings->email_notifications,
                    'whatsapp' => $settings->whatsapp_notifications,
                ],
            ],
        ]);
    }
}