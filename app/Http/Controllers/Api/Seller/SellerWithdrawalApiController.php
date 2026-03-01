<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Group('Seller Withdrawal')]
class SellerWithdrawalApiController extends Controller
{
    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    protected WithdrawalService $withdrawalService;

    /**
     * Create a withdrawal request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createWithdrawalRequest(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'note' => 'nullable|string|max:500',
            ]);

            // Get the authenticated seller
            $seller = $request->user()->seller;

            // Create the withdrawal request
            $result = $this->withdrawalService->createWithdrawalRequest(
                $seller->id,
                [
                    'amount' => $validated['amount'],
                    'note' => $validated['note'] ?? null,
                ]);

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data']
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error'),
                data: $e->errors()
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_occurred'),
                data: [],
            );
        }
    }

    /**
     * Get withdrawal requests for the authenticated seller
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWithdrawalRequests(Request $request): JsonResponse
    {
        // Get the authenticated seller
        $seller = $request->user()->seller;

        // Prepare filters
        $filters = $request->only(['status', 'from_date', 'to_date', 'per_page', 'sort', 'order']);
        $filters['seller_id'] = $seller->id;

        // Get the withdrawal requests
        $result = $this->withdrawalService->getWithdrawalRequests($filters);

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['data']
        );
    }

    /**
     * Get a single withdrawal request
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getWithdrawalRequest(Request $request, int $id): JsonResponse
    {
        // Get the authenticated seller
        $seller = $request->user()->seller;

        // Get the withdrawal request
        $result = $this->withdrawalService->getWithdrawalRequest($id);

        // Check if the request belongs to the authenticated seller
        if ($result['success'] && $result['data']->seller_id !== $seller->id) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.unauthorized_action'),
                data: []
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['data']
        );
    }
}
