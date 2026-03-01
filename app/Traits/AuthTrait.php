<?php

namespace App\Traits;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserRegistered;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Types\Api\ApiResponseType;
use App\Services\SettingService;
use App\Enums\SettingTypeEnum;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

trait AuthTrait
{
    /**
     * Login
     */
    public function login(Request $request): JsonResponse
    {
        log::info('login', ['request' => $request->all()]);
        try {
            // Validate either email or mobile with password
            $validated = $request->validate([
                'email' => 'required_without:mobile|email',
                'mobile' => 'required_without:email|numeric',
                'password' => 'required',
            ]);

            // Build credentials based on provided identifier
            $identifierField = $request->filled('email') ? 'email' : 'mobile';
            $identifierValue = $request->input($identifierField);
            $credentials = [
                $identifierField => $identifierValue,
                'password' => $validated['password'],
            ];

            // Optional role-based access check (admin/seller), if the controller sets $role
            if (property_exists($this, 'role')) {
                $role = $this->role;
                $userForRoleCheck = User::where($identifierField, $identifierValue)->first();

                if (!$userForRoleCheck) {
                    return response()->json([
                        'success' => false,
                        'message' => __('labels.invalid_credentials'),
                        'data' => []
                    ]);
                }

                if ($role === 'seller') {
                    if (!empty($userForRoleCheck->access_panel?->value) && $userForRoleCheck->access_panel->value !== 'seller') {
                        return response()->json([
                            'success' => false,
                            'message' => __('labels.invalid_credentials'),
                            'data' => []
                        ]);
                    }

                    // Also validate seller linkage and verification status during login
                    $seller = $userForRoleCheck->seller();

                    if (!$seller) {
                        return response()->json([
                            'success' => false,
                            'message' => __('labels.not_a_seller') ?? 'Not a seller account.',
                            'data' => []
                        ], 403);
                    }

                    if ($seller->verification_status !== SellerVerificationStatusEnum::Approved()) {
                        return response()->json([
                            'success' => false,
                            'message' => __('labels.account_not_verified') ?? 'Your seller account is not approved yet.',
                            'data' => [
                                'verification_status' => $seller->verification_status,
                            ]
                        ], 403);
                    }
                }
                if ($role === 'admin') {
                    if (!empty($userForRoleCheck->access_panel?->value) && $userForRoleCheck->access_panel->value !== 'admin') {
                        return response()->json([
                            'success' => false,
                            'message' => __('labels.invalid_credentials'),
                            'data' => []
                        ]);
                    }
                }
            }

            if (!FacadesAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => __('labels.invalid_credentials'),
                    'data' => []
                ]);
            }

            $user = $request->user();
            try {
                if (!empty($request['fcm_token']) && !empty($request['device_type'])) {
                    UserFcmToken::updateOrCreate(
                        [
                            'fcm_token' => $request['fcm_token'],
                        ],
                        [
                            'user_id' => $user->id,
                            'device_type' => $request['device_type'],
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::error('Error updating or creating FCM token: ' . $e->getMessage());
            }
            $token = $user->createToken($user->email ?? ($user->mobile ?? 'api-token'))->plainTextToken;
            event(new UserLoggedIn($user));
            return response()->json([
                'success' => true,
                'message' => __('labels.login_successful'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'data' => new UserResource($user)
            ]);
        } catch (ValidationException $e) {
            dd('error', $e);
            return response()->json([
                'success' => false,
                'message' => __('labels.validation_error') . ":- " . $e->getMessage(),
                'data' => []
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.login_failed', ['error' => $e->getMessage()]),
                'data' => []
            ], 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users',
                'mobile' => 'required|unique:users|numeric',
                'password' => 'required|string|min:6|confirmed',
                'country' => 'nullable|string|max:255',
                'iso_2' => 'nullable|string|max:2'
            ]);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'],
                'country' => $validated['country'] ?? null,
                'iso_2' => $validated['iso_2'] ?? null,
                'password' => Hash::make($validated['password'])
            ]);
            // Grant welcome wallet balance if enabled in system settings
            try {
                $settingService = app(SettingService::class);
                $systemSettingsResource = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
                $systemSettings = $systemSettingsResource?->toArray(request())['value'] ?? [];
//                $enableWallet = (bool)($systemSettings['enableWallet'] ?? false);
                $welcomeAmount = (float)($systemSettings['welcomeWalletBalanceAmount'] ?? 0);

                if ($welcomeAmount > 0) {
                    $walletService = app(WalletService::class);
                    $walletService->addBalance($user->id, [
                        'amount' => $welcomeAmount,
                        'payment_method' => 'system',
                        'description' => __('labels.welcome_wallet_bonus') ?? 'Welcome bonus added to wallet',
                    ]);
                }
            } catch (\Throwable $th) {
                // Do not block registration if wallet credit fails; log and continue
                Log::error('Welcome wallet credit failed for user ' . $user->id . ': ' . $th->getMessage());
            }
            event(new UserRegistered($user));
            return response()->json([
                'success' => true,
                'message' => __('labels.registration_successful'),
                'access_token' => $user->createToken($validated['email'])->plainTextToken,
                'token_type' => 'Bearer',
                'data' => [
                    'user' => $user,
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.validation_error') . ":- " . $e->getMessage(),
                'data' => []
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.registration_failed', ['error' => $e->getMessage()]),
                'data' => []
            ], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $request->validate(['email' => 'required|email']);

            $status = Password::sendResetLink($request->only('email'));
            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => __($status),
                    'data' => []
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __($status),
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.password_reset_failed', ['error' => $e->getMessage()]),
                'data' => []
            ], 500);
        }
    }

    /**
     * Logout user
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.logout_successful',
                data: []
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.logout_failed',
            );
        }
    }
}
