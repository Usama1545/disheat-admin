<?php

namespace App\Http\Middleware;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class ValidateSeller
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Attempt to authenticate via token from query string when no active session exists
        if (!$user) {
            $rawToken = $request->query('token');
            if (!empty($rawToken)) {
                $accessToken = PersonalAccessToken::findToken($rawToken);
                if ($accessToken && $accessToken->tokenable) {
                    // Ensure the token belongs to a User model
                    $tokenUser = $accessToken->tokenable;
                    if ($tokenUser instanceof User) {
                        Auth::login($tokenUser);
                        $user = Auth::user();
                        // Remove token from the URL to avoid leaking it via referrers
                        return redirect()->to($request->url());
                    }
                }

                // If still not authenticated, token was invalid/expired
                if (!$user) {
                    return redirect()->route('seller.login')->with('error', __('labels.invalid_or_expired_token') ?? 'Invalid or expired token.');
                }
            }
        }
        // Check if user is authenticated
        if (!$user) {
            return redirect()->route('seller.login');
        }
        // Check if user has admin guard or Super Admin role
        if ($user->getDefaultGuardName() == GuardNameEnum::SELLER()) {
            // Ensure the authenticated user is linked to a seller
            $seller = $user->seller();

            if (!$seller) {
                // Not associated with any seller account
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => __('labels.not_a_seller') ?? 'Not a seller account.',
                        'data' => []
                    ], 403);
                }
                return redirect()->route('seller.login')->with('error', __('labels.not_a_seller') ?? 'Not a seller account.');
            }

            // Validate seller verification status is approved
            if ($seller->verification_status !== SellerVerificationStatusEnum::Approved()) {
                $message = __('labels.account_not_verified') ?? 'Your seller account is not approved yet.';
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'data' => [
                            'verification_status' => $seller->verification_status,
                        ]
                    ], 403);
                }
                Auth::logout();
                return redirect()->route('seller.login')->with('error', $message);
            }

            return $next($request);
        }

        // If user doesn't have admin access, log them out and redirect to login
        Auth::logout();
        return redirect()->route('seller.login')->with('error', 'You do not have permission to access the admin panel.');
    }
}
