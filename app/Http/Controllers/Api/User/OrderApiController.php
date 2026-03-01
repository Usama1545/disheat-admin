<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Order\CreateItemReturnRequest;
use App\Http\Requests\User\Order\CreateOrderRequest;
use App\Http\Resources\User\OrderPaymentResource;
use App\Http\Resources\User\OrderResource;
use App\Services\OrderService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('Orders')]
class OrderApiController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Create a new order
     *
     * Creates a new order from the user's cart with the provided payment and address information.
     */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }
        $result = $this->orderService->createOrder($user, $request->validated());

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    /**
     * Get order details
     *
     * Retrieves the details of a specific order by its slug.
     */
    public function getOrder(string $orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->getOrder($user, $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    /**
     * Get user's orders
     *
     * Retrieves all orders for the authenticated user.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of Orders Per Page', type: 'int', default: 1, example: 1)]
    public function getUserOrders(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 15);

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->getUserOrders(user: $user, perPage: $perPage);
        $orders = $result['data'];
        $orders->getCollection()->transform(fn($order) => new OrderResource($order));
        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: ['current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'data' => $orders->items(),]
        );
    }

    /**
     * Get Order Delivery Boy Location
     *
     */

    public function getOrderDeliveryBoyLocation($orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        if (!$orderSlug) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.order_slug_required'),
                []
            );
        }

        $result = $this->orderService->getOrderDeliveryBoyLocation(user: $user, orderSlug: $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

    /**
     * Cancel an order item
     *
     * Cancels a specific order item if it meets the cancellation criteria.
     */
    public function cancelOrderItem(int $orderItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->cancelOrderItem($user, $orderItemId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

    /**
     * Get user's payment transactions
     *
     * Retrieves all payment transactions for the authenticated user.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of Orders Per Page', type: 'int', default: 1, example: 1)]
    #[QueryParameter('payment_status', description: 'Filter by payment status', type: 'string', example: 'completed')]
    #[QueryParameter('search', description: 'Search by transaction reference or other fields', type: 'string', example: 'TX123')]
    public function getTransactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 15);
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('search');

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $query = $user->OrderPaymentTransactions()->latest();

        // Apply payment_status filter
        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        // Apply search filter (e.g. transaction reference or other fields)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%");
            });
        }

        // Paginate
        $transactions = $query->paginate($perPage);

        // Transform using resource collection
        $transactions->getCollection()->transform(function ($transaction) {
            return new OrderPaymentResource($transaction);
        });

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transactions_retrieved_successfully'),
            [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'data' => $transactions->items(),
            ]
        );
    }

    /**
     * Get a specific payment transaction
     *
     * Retrieves details of a specific payment transaction by its ID.
     */
    public function getTransaction($id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $transaction = $user->OrderPaymentTransactions()->where('id', $id)->first();

        if (!$transaction) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.transaction_not_found'),
                []
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transaction_retrieved_successfully'),
            OrderPaymentResource::make($transaction)
        );
    }

    /**
     * Return Order Item
     *
     * Return an order item if it meets the return criteria.
     */
    public function returnOrderItem(int $orderItemId, CreateItemReturnRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $validated = $request->validated();
        $validated['order_item_id'] = $orderItemId;

        $result = $this->orderService->returnOrderItem($user, $validated);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }

    /**
     * Cancel Return Request
     * @param $orderItemId
     * @return JsonResponse
     */

    public function cancelReturnRequest($orderItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->cancelReturnRequest(user:$user, orderItemId: $orderItemId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }
}
