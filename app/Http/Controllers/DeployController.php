<?php

namespace App\Http\Controllers;

use App\Actions\Deploy\DeployRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DeployController extends Controller
{
    /**
     * Minimum length of the deploy key; a shorter configured key is treated as not set.
     */
    private const MIN_KEY_LENGTH = 32;

    public function __invoke(Request $request, DeployRelease $deployRelease): JsonResponse
    {
        if (! $this->hasValidKey($request)) {
            return response()->json(['status' => 'forbidden', 'message' => __('deploy.forbidden')], 403);
        }

        // A dropped connection or the host's time limit must not stop a migration half way.
        ignore_user_abort(true);
        set_time_limit(0);

        try {
            $result = $deployRelease->handle();
        } catch (Throwable) {
            // The details are already in the log; the response never carries them.
            return response()->json(['status' => 'failed', 'message' => __('deploy.failed')], 500);
        }

        return response()->json(['status' => $result->value, 'message' => $result->message()], $result->httpStatus());
    }

    private function hasValidKey(Request $request): bool
    {
        $key = config('app.deploy.key');
        $token = $request->bearerToken();

        if (! is_string($key) || strlen($key) < self::MIN_KEY_LENGTH || ! is_string($token)) {
            return false;
        }

        return hash_equals($key, $token);
    }
}
