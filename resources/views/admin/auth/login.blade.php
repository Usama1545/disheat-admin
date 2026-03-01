@extends('layouts.admin.guest')

@section('title', __('labels.admin_login'))
@section('content')
    <div>
        <div class="page page-center">
            @if ($systemSettings['demoMode'] ?? false)
                <div class="container-fluid">
                    <div class="alert alert-warning mt-2" role="alert">
                        <div class="alert-icon">
                            <!-- Download SVG icon from http://tabler.io/icons/icon/alert-triangle -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon alert-icon icon-2">
                                <path d="M12 9v4"></path>
                                <path
                                    d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z">
                                </path>
                                <path d="M12 16h.01"></path>
                            </svg>
                        </div>
                        <div>

                            If you can't login to admin panel, please <span><a href="{{ route('admin.login') }}"
                                    target="_blank" class="alert-link">click
                                    here</a></span> to go to website.
                        </div>
                    </div>
                </div>
            @endif
            <div class="container container-tight py-4">
                <div class="text-center mb-4">
                    <!-- BEGIN NAVBAR LOGO -->
                    <a href="." class="navbar-brand navbar-brand-autodark">
                        @if ($systemSettings['demoMode'] ?? false)
                            <img src="{{ asset('logos/logo.png') }}" alt="{{ $systemSettings['appName'] ?? '' }}"
                                width="150px">
                        @else
                            <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/logo.png') }}"
                                alt="{{ $systemSettings['appName'] ?? '' }}" width="150px">
                        @endif
                    </a>
                    <!-- END NAVBAR LOGO -->
                </div>
                <div class="card card-md">
                    <div class="card-body">
                        <h2 class="h2 text-center mb-4">Login to your account</h2>
                        <form id="login-form" action="{{ route('admin.login.post') }}" method="post" autocomplete="off"
                            novalidate>
                            <input type="hidden" name="fcm_token" value="">
                            <input type="hidden" name="device_type" value="web">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input type="email" class="form-control" name="email" placeholder="your@email.com"
                                    autocomplete="off" />
                            </div>
                            <div class="mb-2">
                                <label class="form-label">
                                    Password
                                    <span class="form-label-description"><a href="{{ route('admin.password.request') }}">I
                                            forgot password</a></span>
                                </label>
                                <div class="input-group input-group-flat">
                                    <input type="password" class="form-control" name="password" placeholder="Your password"
                                        autocomplete="off" id="password" />
                                    <span class="input-group-text">
                                        <a href="#" class="link-secondary" title="Show password" id="password-toggle"
                                            data-bs-toggle="tooltip">
                                            <!-- Download SVG icon from http://tabler.io/icons/icon/eye -->
                                            Show
                                        </a>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-check">
                                    <input type="checkbox" class="form-check-input" />
                                    <span class="form-check-label">Remember me on this device</span>
                                </label>
                            </div>
                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">Sign in</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        var token = localStorage.getItem('fcm_token');
        if (token) {
            document.querySelector('input[name="fcm_token"]').value = token;
        }
    </script>
@endpush
