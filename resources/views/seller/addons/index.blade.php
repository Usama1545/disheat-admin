@extends('layouts.seller.app', [
    'page' => $menuseller['addons']['active'] ?? '',
])
@section('title', __('labels.addons'))

@section('header_data')
    @php
        $page_title = __('labels.addons');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.addons'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.addons') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                @if ($createPermission ?? false)
                                    <div class="col text-end">
                                        <a href="{{ route('seller.addons.create') }}" class="btn btn-6 btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                                <path d="M12 5l0 14" />
                                                <path d="M5 12l14 0" />
                                            </svg>
                                            {{ __('labels.add_addon') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="addons-table" :columns="$columns" route="{{ route('seller.addons.datatable') }}"
                            :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        document.addEventListener("click", (e) => {
            handleDelete(
                e,
                ".delete-addon",
                `/${panel}/addons/`,
                "You are about to delete this Addon.",
            );
        });
        document.addEventListener("click", (e) => {
            const target = e.target.closest(".edit-addon");
            if (!target) return;

            e.preventDefault();

            const id = target.dataset.id;

            window.location.href = `/${panel}/addons/${id}/edit`;
        });
    </script>
@endpush
