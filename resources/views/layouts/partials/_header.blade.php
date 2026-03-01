 @php
     use App\Models\Notification;
     use App\Enums\AdminPermissionEnum;
     use App\Enums\SellerPermissionEnum;
     use App\Enums\DefaultSystemRolesEnum;
     use Illuminate\Support\Facades\Auth;
 @endphp
<div class="page-header d-print-none mt-0">
    <div class="container-xl navbar align-items-end justify-content-end py-2">
        <div class="">
            <!-- Page title actions -->
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <!-- Theme switcher -->
                    <div class="nav-item dropdown d-none d-md-flex me-3">
                        <a href="#" onclick="toggleTheme()" class="nav-link px-0" tabindex="-1" aria-label="Switch theme">
                            <i id="theme-icon" class="ti ti-moon fs-2"></i>
                        </a>
                    </div>

                    <!-- Seller store status toggle -->
{{--                    @php--}}
{{--                        $currentPanel = request()->segment(1);--}}
{{--                        $storeForToggle = null;--}}
{{--                        if ($currentPanel === 'seller') {--}}
{{--                            $sellerUser = auth()->user();--}}
{{--                            $seller = $sellerUser?->seller();--}}
{{--                            if ($seller) {--}}
{{--                                $storeForToggle = \App\Models\Store::where('seller_id', $seller->id)->first();--}}
{{--                            }--}}
{{--                        }--}}
{{--                    @endphp--}}
{{--                    @if($storeForToggle)--}}
{{--                        <div class="nav-item d-none d-md-flex align-items-center me-3">--}}
{{--                            <div class="me-2 small text-muted">{{ __('labels.store_status') }}:</div>--}}
{{--                            <div class="form-check form-switch m-0">--}}
{{--                                <input class="form-check-input" type="checkbox"--}}
{{--                                       id="seller-store-status-switch"--}}
{{--                                       data-store-id="{{ $storeForToggle->id }}"--}}
{{--                                       data-online-label="{{ __('labels.online') }}"--}}
{{--                                       data-offline-label="{{ __('labels.offline') }}"--}}
{{--                                       {{ $storeForToggle->isOnline() ? 'checked' : '' }}--}}
{{--                                       aria-label="Toggle store online/offline">--}}
{{--                                <label class="form-check-label ms-2" for="seller-store-status-switch">--}}
{{--                                    <span id="seller-store-status-label">{{ $storeForToggle->isOnline() ? __('labels.online') : __('labels.offline') }}</span>--}}
{{--                                </label>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    @endif--}}

                    <!-- Language dropdown -->
                    <div class="nav-item dropdown d-none d-md-flex me-3">
                        <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1"
                           aria-label="Select language">
                            <i class="ti ti-language fs-2"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="{{ route('set.language', 'en') }}"
                               class="dropdown-item {{ app()->getLocale() == 'en' ? 'active' : '' }}">
                                {{ __('labels.languages.english') }}</a>
                            <a href="{{ route('set.language', 'es') }}"
                               class="dropdown-item {{ app()->getLocale() == 'es' ? 'active' : '' }}">
                                {{ __('labels.languages.spanish') }}</a>
                            <a href="{{ route('set.language', 'fr') }}"
                               class="dropdown-item {{ app()->getLocale() == 'fr' ? 'active' : '' }}">
                                {{ __('labels.languages.french') }}</a>
                            <a href="{{ route('set.language', 'de') }}"
                               class="dropdown-item {{ app()->getLocale() == 'de' ? 'active' : '' }}">
                                {{ __('labels.languages.german') }}</a>
                            <a href="{{ route('set.language', 'zh') }}"
                               class="dropdown-item {{ app()->getLocale() == 'zh' ? 'active' : '' }}">
                                {{ __('labels.languages.chinese') }}</a>
                        </div>
                    </div>

                    <!-- Notification dropdown -->
                    @php
                        $currentPanel = request()->segment(1); // Get current panel (admin/seller)
                        $userAuth = Auth::user();
                        // Determine if the user can view notifications in the header
                        $canViewNotifications = false;
                        if ($currentPanel === 'admin') {
                            // Super Admin bypass on an admin panel
                            if ($userAuth && $userAuth->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                                $canViewNotifications = true;
                            } else {
                                try {
                                    $canViewNotifications = $userAuth?->hasPermissionTo(AdminPermissionEnum::NOTIFICATION_VIEW()) ?? false;
                                } catch (\Throwable $e) {
                                    $canViewNotifications = false;
                                }
                            }
                        } elseif ($currentPanel === 'seller') {
                            // Default SELLER role has full seller module access
                            if ($userAuth && $userAuth->hasRole(DefaultSystemRolesEnum::SELLER())) {
                                $canViewNotifications = true;
                            } else {
                                try {
                                    $canViewNotifications = $userAuth?->hasPermissionTo(SellerPermissionEnum::NOTIFICATION_VIEW()) ?? false;
                                } catch (\Throwable $e) {
                                    $canViewNotifications = false;
                                }
                            }
                        } else {
                            $canViewNotifications = false; // other panels: hide by default
                        }
                    @endphp
                    @if($canViewNotifications)
                        @php
                            $sentTo = $currentPanel === 'admin' ? 'admin' : 'seller';

                            // Fetch latest notifications for current panel
                            $notifications = Notification::where('sent_to', $sentTo)
                                ->orderBy('created_at', 'desc')
                                ->limit(5)
                                ->get();

                            // Get unread count
                            $unreadCount = Notification::where('sent_to', $sentTo)
                                ->where('is_read', false)
                                ->count();
                        @endphp
                        <div class="nav-item dropdown d-none d-md-flex me-3">
                            <button type="button" class="btn btn-icon border-0 shadow-none nav-link px-0"
                                    data-bs-toggle="dropdown" tabindex="-1" aria-label="Show notifications">
                                <i class="ti ti-bell fs-2"></i>
                                @if($unreadCount > 0)
                                    <span class="badge bg-red badge-sm mt-1 badge-notification text-red-fg">
                                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-card">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">{{ __('labels.notifications') }}
                                        </h3>
                                    </div>
                                    <div class="list-group list-group-flush list-group-hoverable">
                                        @forelse($notifications as $notification)
                                            <div class="list-group-item">
                                                <div class="row align-items-center">
                                                    <div class="col-auto">
                                                        <span
                                                            class="status-dot {{ $notification->is_read ? 'bg-secondary' : 'status-dot-animated bg-red' }} d-block"></span>
                                                    </div>
                                                    <div class="col text-truncate">
                                                        <a href="#" class="text-body d-block text-ellipsis-1">{{ $notification->message }}</a>
                                                        <div class="d-block text-muted text-truncate mt-n1">
                                                            {{ $notification->created_at->diffForHumans() }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="list-group-item">
                                                <div class="row align-items-center">
                                                    <div class="col text-center text-muted">
                                                        <i class="ti ti-bell-off fs-2 mb-2"></i>
                                                        <div>{{ __('labels.no_notifications') }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforelse
                                    </div>
                                    <div class="card-footer">
                                        <a href="{{ route($currentPanel . '.notifications.index') }}"
                                           class="btn btn-outline-primary w-100">{{ __('labels.view_all_notifications') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Admin profile dropdown -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                           aria-label="Open user menu">
                            @if($user->profile_image)
                                <span class="avatar avatar-sm" id="profile"
                                      style="background-image: url({{ $user->profile_image }});"></span>
                            @else
                                <span class="avatar avatar-sm bg-primary text-white"
                                      id="profile">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                            @endif
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ $user->username ?? "" }}</div>
                                <div class="mt-1 small text-muted">
                                    @if ($user->role == 'admin')
                                        {{ __('labels.administrator')}}
                                    @elseif($user->role == 'delivery_boy')
                                        {{ __('labels.delivery_boy') }}
                                    @endif
                                </div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="{{route(request()->segment(1).'.profile.index')}}" class="dropdown-item">
                                <i class="ti ti-user me-2 fs-2"></i>{{ __('labels.profile') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="{{ route(request()->segment(1).'.logout') }}" class="dropdown-item">
                                <i class="ti ti-logout me-2 fs-2"></i>{{ __('labels.logout') }}
                            </a>
                        </div>
                    </div>
                </div>
                <!-- BEGIN MODAL -->
                <!-- END MODAL -->
            </div>
        </div>
    </div>
    @if(($systemSettings['demoMode'] ?? false))
        <div class="alert alert-danger alert-dismissible mt-3 text-danger" role="alert">
            <div class="alert-icon">
                <!-- Download SVG icon from http://tabler.io/icons/icon/info-circle -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon alert-icon icon-2">
                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                    <path d="M12 9h.01"></path>
                    <path d="M11 12h1v4h1"></path>
                </svg>
            </div>
            @php
                $demoMessage = $user->access_panel->value === 'admin'
                    ? ($systemSettings['adminDemoModeMessage'] ?? null)
                    : ($systemSettings['sellerDemoModeMessage'] ?? null);
            @endphp
            {{ $demoMessage ?: __('labels.demo_mode_message') }}
        </div>
    @endif
</div>
