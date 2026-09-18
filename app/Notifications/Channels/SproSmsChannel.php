<?php

namespace App\Notifications\Channels;

use Fouladgar\OTP\Notifications\OTPNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SproSmsChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (!$notification instanceof OTPNotification) {
            throw new RuntimeException(
                'SproSmsChannel can only be used with OTPNotification.'
            );
        }

        $message = $notification->toSMS($notifiable);

        $payload = $message->getPayload();

        $phone = $this->normalizePhoneNumber($payload->to());

        $response = Http::acceptJson()
            ->post(config('services.spro_sms.url'), [
                'api_key' => config('services.spro_sms.api_key'),
                'to' => $phone,
                'message' => $payload->content(),
                'sender_id' => config('services.spro_sms.sender_id'),
                'domain' => config('services.spro_sms.domain'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'SPRO SMS request failed: ' . $response->body()
            );
        }

        $result = $response->json();

        if (($result['status'] ?? null) !== 'success') {
            throw new RuntimeException(
                'SPRO SMS delivery failed: ' . $response->body()
            );
        }

        $failed = collect($result['results'] ?? [])
            ->contains(function ($item) {
                return ($item['status'] ?? null) !== 'sent';
            });

        if ($failed) {
            throw new RuntimeException(
                'SPRO SMS recipient delivery failed: ' . $response->body()
            );
        }
    }

    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        if (!$phone) {
            throw new RuntimeException(
                'Invalid phone number.'
            );
        }

        if (str_starts_with($phone, '0')) {
            return '255' . substr($phone, 1);
        }

        if (str_starts_with($phone, '255')) {
            return $phone;
        }

        throw new RuntimeException(
            'Phone number must be a valid Tanzania number.'
        );
    }
}