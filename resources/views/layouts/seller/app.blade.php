@extends('layouts.main')

{{-- @section('title', 'Admin Dashboard') --}}

@section('header_data')
    @php
        $page_title = $page_title ?? 'Seller Dashboard';
        $page_pretitle = $page_pretitle ?? 'Overview';
    @endphp
@endsection
@section('content')
    @if(empty($page) || $page != 'login')
        @include('layouts.partials._header', [
            'page_title' => $page_title ?? 'Seller Dashboard',
            'page_pretitle' => $page_pretitle ?? 'Overview',
        ])
    @endif
    <div class="page-body">
        <div class="container-xl">
            @yield('seller-content')
        </div>
    </div>
@endsection
