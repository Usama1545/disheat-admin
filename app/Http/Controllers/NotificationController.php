<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;
    protected int $sellerId = 0;
    protected int $sellerUserId = 0;
    protected $firebase;

    public function __construct(protected NotificationService $notificationService, FirebaseService $firebase)
    {
        $this->firebase = $firebase;

        $user = auth()->user();
        if ($user) {
            $seller = $user->seller();
            $this->sellerId = $seller ? $seller->id : 0;
            $this->sellerUserId = $seller?->user_id ?? 0;
            $enum = $this->getPanel() === 'seller' ? SellerPermissionEnum::class : AdminPermissionEnum::class;

            $this->editPermission = $this->hasPermission($enum::NOTIFICATION_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->deletePermission = $this->hasPermission($enum::NOTIFICATION_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->createPermission = $this->hasPermission($enum::NOTIFICATION_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        }
    }

    /**
     * Display a listing of notifications.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Notification::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
            ['data' => 'message', 'name' => 'message', 'title' => __('labels.message')],
            ['data' => 'type', 'name' => 'type', 'title' => __('labels.type')],
//            ['data' => 'sent_to', 'name' => 'sent_to', 'title' => __('labels.sent_to')],
            ['data' => 'is_read', 'name' => 'is_read', 'title' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;

        return view($this->panelView('notifications.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission'));
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', Notification::class);

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'store_id' => 'nullable|exists:stores,id',
                'order_id' => 'nullable|exists:orders,id',
                'type' => ['required', new Enum(NotificationTypeEnum::class)],
                'sent_to' => 'required|string|in:admin,customer,seller',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'metadata' => 'nullable|array',
            ]);

            $notification = $this->notificationService->createNotification($validated);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notification_created_successfully'),
                data: new NotificationResource($notification),
                status: 201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.validation_failed'), data: $e->errors());
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.permission_denied'), data: []);
        }
    }

    /**
     * Display the specified notification.
     */
    public function show($id): JsonResponse
    {
        try {
            $notification = Notification::with(['user', 'store', 'order'])->findOrFail($id);
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notification_retrieved_successfully'),
                data: new NotificationResource($notification)
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.notification_not_found'), data: []);
        }
    }

    /**
     * Update the specified notification.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);
            $this->authorize('update', $notification);

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'message' => 'sometimes|required|string',
                'type' => ['sometimes', 'required', new Enum(NotificationTypeEnum::class)],
                'sent_to' => 'sometimes|required|string|in:admin,customer,seller',
                'is_read' => 'sometimes|boolean',
                'metadata' => 'nullable|array',
            ]);

            $notification->update($validated);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notification_updated_successfully'),
                data: new NotificationResource($notification)
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors()
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.notification_not_found'),
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        }
    }

    /**
     * Remove the specified notification.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);
            $this->authorize('delete', $notification);

            $this->notificationService->deleteNotification($id);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notification_deleted_successfully'),
                data: []
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.notification_not_found'),
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        }
    }

    /**
     * Get notifications for the authenticated user.
     */
    public function getUserNotifications(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Notification::class);

            $perPage = $request->get('per_page', 15);
            $userId = auth()->id();

            $result = $this->notificationService->getUserNotifications($userId, $perPage);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notifications_retrieved_successfully'),
                data: [
                    'notifications' => NotificationResource::collection($result['notifications']),
                    'pagination' => $result['pagination']
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_retrieving_notifications'),
                data: []
            );
        }
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);
            $this->authorize('markAsRead', $notification);

            $this->notificationService->markAsRead($id);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notification_marked_as_read'),
                data: []
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.notification_not_found'),
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        }
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $this->authorize('readAll', Notification::class);
            if ($this->getPanel() === 'seller') {
                $userId = $this->sellerUserId;
                $count = Notification::where('user_id', $userId)->where('is_read', false)->count();
                if ($count === 0) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.no_unread_notifications'),
                        data: []
                    );
                }
                $this->notificationService->markAllAsRead($userId);
            } else {
                $count = Notification::where('sent_to', $this->getPanel())->where('is_read', false)->count();
                if ($count === 0) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.no_unread_notifications'),
                        data: []
                    );
                }
                $this->notificationService->markAllAsReadAdmin();
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.all_notifications_marked_as_read'),
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_marking_notifications_as_read'),
                data: []
            );
        }
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $count = $this->notificationService->getUnreadCount($userId);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.unread_count_retrieved_successfully'),
                data: ['unread_count' => $count]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_retrieving_unread_count'),
                data: []
            );
        }
    }

    /**
     * Get notifications for DataTables
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Notification::class);

            $draw = $request->get('draw');
            $start = $request->get('start');
            $length = $request->get('length');
            $searchValue = $request->get('search')['value'] ?? '';

            $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
            $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';

            $columns = ['id', 'title', 'type', 'sent_to', 'is_read', 'created_at'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

            $query = Notification::query();

            // Filter by admin notifications only
            if ($this->getPanel() === 'admin') {
                $query->where('sent_to', 'admin');
            } elseif ($this->getPanel() === 'seller') {
                $query->where('sent_to', 'seller')
                    ->where('user_id', $this->sellerUserId);
            } else {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.invalid_panel'),
                    data: []
                );
            }

            $totalRecords = $query->count();

            // Apply search
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('title', 'like', "%{$searchValue}%")
                        ->orWhere('message', 'like', "%{$searchValue}%")
                        ->orWhere('type', 'like', "%{$searchValue}%")
                        ->orWhere('sent_to', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();

            // Apply ordering and pagination
            $notifications = $query->orderBy($orderColumn, $orderDirection)
                ->skip($start)
                ->take($length)
                ->with(['user', 'store', 'order'])
                ->get();

            $data = [];
            foreach ($notifications as $notification) {
                $data[] = [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => ucfirst($notification->type?->value ?? $notification->type),
//                    'sent_to' => ucfirst($notification->sent_to),
                    'is_read' => $notification->is_read ?
                        '<span class="badge delivered">' . __('labels.read') . '</span>' :
                        '<span class="badge inactive">' . __('labels.unread') . '</span>',
                    'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                    'action' => $this->getActionButtons($notification)
                ];
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'draw' => intval($request->get('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate action buttons for notifications
     */
    private function getActionButtons($notification): string
    {
        $actions = '';

        if ($this->editPermission) {

            // Mark as read/unread button
            if (!$notification->is_read) {
                $actions .= '<button class="btn btn-outline-success mark-read-btn me-2 p-1" data-id="' . $notification->id . '" title="' . __('labels.mark_as_read') . '">
                <i class="ti ti-check"></i>
            </button>';
            } else {
                $actions .= '<button class="btn btn-outline-warning mark-unread-btn me-2 p-1" data-id="' . $notification->id . '" title="' . __('labels.mark_as_unread') . '">
                <i class="ti ti-x"></i>
            </button>';
            }
        }

        // View button
        $actions .= '<button class="btn btn-outline-primary view-notification-btn me-2 p-1" data-id="' . $notification->id . '" title="' . __('labels.view') . '">
            <i class="ti ti-eye"></i>
        </button>';

        // Delete button
        if ($this->deletePermission) {
            $actions .= '<button class="btn btn-outline-danger delete-notification-btn p-1" data-id="' . $notification->id . '" title="' . __('labels.delete') . '">
                <i class="ti ti-trash"></i>
            </button>';
        }

        return $actions;
    }


    /**
     * Mark notification as unread
     */
    public function markAsUnread($id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);
            $this->authorize('update', $notification);

            $notification->update(['is_read' => false]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.notification_marked_as_unread'),
                data: []
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.notification_not_found'),
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        }
    }

    public function test(Request $request)
    {
        $token = $request->input('token'); // FCM device/web token

        if (!$token) {
            return response()->json(['error' => 'Token is required'], 400);
        }

        try {
            $response = $this->firebase->sendNotification(
                token: $token,
                title: 'Test Notification 🚀',
                body: 'This is a test message from Laravel + Firebase.',
                data: ['type' => 'test', 'click_action' => 'click action']
            );

            return response()->json(['success' => true, 'response' => $response]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendBulk(Request $request)
    {
        $tokens = $request->input('tokens'); // array of FCM tokens

        if (empty($tokens) || !is_array($tokens)) {
            return response()->json(['error' => 'tokens array is required'], 400);
        }

        try {
            $result = $this->firebase->sendBulkNotification(
                tokens: $tokens,
                title: '🚀 Bulk Notification Test',
                body: 'This is a test message with automatic token cleanup.',
                data: ['type' => 'bulk_test']
            );

            return response()->json(['success' => true, 'summary' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
