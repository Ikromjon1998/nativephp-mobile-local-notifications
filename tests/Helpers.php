<?php

declare(strict_types=1);

/**
 * Stub the nativephp_call function with a custom callback.
 */
function stubNativephpCall(Closure $callback): void
{
    global $__nativephp_call_stub;
    $__nativephp_call_stub = $callback;

    if (! function_exists('nativephp_call')) {
        function nativephp_call(string $function, string $data = ''): mixed
        {
            global $__nativephp_call_stub;

            if ($__nativephp_call_stub) {
                return ($__nativephp_call_stub)($function, $data);
            }

            return null;
        }
    }
}

/**
 * Make the nativephp_call stub return null (simulates bridge returning no result).
 */
function stubNativephpCallReturnsNull(): void
{
    stubNativephpCall(fn () => null);
}
