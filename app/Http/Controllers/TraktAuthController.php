<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TokenManagerContract;
use App\Contracts\TraktTvServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TraktAuthController extends Controller
{
    public function __construct(
        private readonly TraktTvServiceContract $traktService,
        private readonly TokenManagerContract $tokenManager,
    ) {}

    /**
     * Check if the current user has Trakt connected.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'connected' => $user->hasTraktConnected(),
        ]);
    }

    /**
     * Start the Trakt device authorization flow.
     * Returns device_code, user_code, and verification_url.
     */
    public function startDevice(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $device = $this->traktService->createDeviceToken();
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Failed to start Trakt authorization: ' . $e->getMessage(),
            ], 500);
        }

        $deviceCode = $device['device_code'] ?? null;
        $userCode = $device['user_code'] ?? null;
        $verificationUrl = $device['verification_url'] ?? null;
        $expiresIn = (int) ($device['expires_in'] ?? 600);
        $interval = (int) ($device['interval'] ?? 5);

        if (!$deviceCode || !$userCode || !$verificationUrl) {
            return response()->json([
                'error' => 'Unexpected response from Trakt',
            ], 500);
        }

        // Store device code in cache, scoped to this user
        $cacheKey = $this->getDeviceCacheKey($user->id);
        Cache::put($cacheKey, [
            'device_code' => $deviceCode,
            'interval' => $interval,
        ], now()->addSeconds($expiresIn));

        return response()->json([
            'user_code' => $userCode,
            'verification_url' => $verificationUrl,
            'expires_in' => $expiresIn,
            'interval' => $interval,
        ]);
    }

    /**
     * Poll for token exchange.
     * Called by frontend until authorization is complete.
     */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = $this->getDeviceCacheKey($user->id);

        $cached = Cache::get($cacheKey);
        if (!$cached || !isset($cached['device_code'])) {
            return response()->json([
                'status' => 'expired',
                'message' => 'Device authorization expired. Please start again.',
            ], 400);
        }

        $deviceCode = $cached['device_code'];

        // Create a user-scoped service for token storage
        $userService = $this->traktService->forUser($user->id);

        try {
            $tokens = $userService->exchangeForAccessToken($deviceCode);

            // Clear the device code from cache
            Cache::forget($cacheKey);

            return response()->json([
                'status' => 'success',
                'message' => 'Trakt account connected successfully.',
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage();

            // Check for common pending states
            if (str_contains($message, 'authorization_pending') || str_contains($message, '400')) {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Waiting for authorization...',
                ]);
            }

            // Check for slow down request
            if (str_contains($message, 'slow_down')) {
                return response()->json([
                    'status' => 'slow_down',
                    'message' => 'Please wait longer between poll requests.',
                ]);
            }

            // Check for denied/expired
            if (str_contains($message, 'denied') || str_contains($message, 'expired')) {
                Cache::forget($cacheKey);
                return response()->json([
                    'status' => 'expired',
                    'message' => 'Authorization was denied or expired.',
                ], 400);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to exchange token: ' . $message,
            ], 500);
        }
    }

    /**
     * Disconnect Trakt from the current user's account.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->tokenManager->deleteTraktTokens($user->id);

        // Clear any pending device authorization
        Cache::forget($this->getDeviceCacheKey($user->id));

        return response()->json([
            'success' => true,
            'message' => 'Trakt account disconnected.',
        ]);
    }

    private function getDeviceCacheKey(int $userId): string
    {
        return "trakt:device_auth:user:{$userId}";
    }
}
