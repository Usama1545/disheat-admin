@extends('layouts.admin.app',['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['notification']['sub_active'] ?? "" ])

@section('title', __('labels.notification_settings'))

@section('header_data')
    @php
        $page_title = __('labels.notification_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.notification_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.notification_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-general">{{ __('labels.general') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="type" value="notification">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.firebase_project_id') }}</label>
                                            <input type="text" class="form-control" name="firebaseProjectId"
                                                   placeholder="{{ __('labels.firebase_project_id_placeholder') }}"
                                                   value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['firebaseProjectId'] ?? '****'), '****', 3, 8) : ($settings['firebaseProjectId'] ?? '') }}"/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.service_account_file') }}</label>
                                            <input type="file" class="form-control" name="serviceAccountFile"
                                                   data-service-url="{{ !empty($settings['serviceAccountFile']) ? url('storage/' . $settings['serviceAccountFile']) : '' }}"/>
                                            <small class="form-text text-muted">{{ __('messages.service_account_file_description') }}</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.vap_id_key') }}</label>
                                            <input type="text" class="form-control" name="vapIdKey"
                                                   placeholder="{{ __('labels.vap_id_key_placeholder') }}"
                                                   value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['vapIdKey'] ?? '****'), '****', 3, 8) : ($settings['vapIdKey'] ?? '') }}"/>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'notification'])
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
