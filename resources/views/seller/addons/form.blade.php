@php
    $isEdit = !empty($addon);
    $action = $isEdit ? route('seller.addons.update', $addon->id) : route('seller.addons.store');
@endphp

@extends('layouts.seller.app')

@section('seller-content')

    <form id="addon-form-submit" method="POST"
        action="{{ empty($addon) ? route('seller.addons.store') : route('seller.addons.update', ['id' => $addon->id]) }}"
        enctype="multipart/form-data" novalidate>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $isEdit ? __('labels.edit_addon') : __('labels.add_addon') }}
                </h3>

                <div class="card-actions">
                    <a href="{{ route('seller.addons.index') }}" class="btn btn-secondary">
                        {{ __('labels.back') }}
                    </a>
                </div>
            </div>

            <div class="card-body">

                {{-- Name --}}
                <div class="mb-3">
                    <label class="form-label required">{{ __('labels.name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ $addon->name ?? '' }}">
                </div>

                {{-- Type --}}
                <div class="mb-3">
                    <label class="form-label required">{{ __('labels.type') }}</label>
                    <select name="type" class="form-select">
                        <option value="single" {{ ($addon->type ?? '') === 'single' ? 'selected' : '' }}>Single</option>
                        <option value="multiple" {{ ($addon->type ?? '') === 'multiple' ? 'selected' : '' }}>Multiple
                        </option>
                    </select>
                </div>

                {{-- Required --}}
                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="is_required" value="1"
                        {{ $addon->is_required ?? false ? 'checked' : '' }}>
                    <label class="form-check-label">{{ __('labels.is_required') }}</label>
                </div>

                {{-- Min --}}
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.min_select') }}</label>
                    <input type="number" name="min_select" class="form-control" value="{{ $addon->min_select ?? 0 }}">
                </div>

                {{-- Max --}}
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.max_select') }}</label>
                    <input type="number" name="max_select" class="form-control" value="{{ $addon->max_select ?? 0 }}">
                </div>

                {{-- OPTIONS --}}
                <hr>
                <h4>Options</h4>

                <div id="options-wrapper">
                    @if (!empty($addon?->options))
                        @foreach ($addon->options as $i => $option)
                            <div class="option-item mb-2 d-flex gap-2">
                                <input type="text" name="options[{{ $i }}][name]"
                                    value="{{ $option->name }}" class="form-control">

                                <input type="number" name="options[{{ $i }}][price]"
                                    value="{{ $option->price }}" class="form-control">

                                <button type="button" class="btn btn-danger remove-option">X</button>
                            </div>
                        @endforeach
                    @endif
                </div>

                <button type="button" class="btn btn-outline-primary mt-2" id="add-option">
                    + Add Option
                </button>

            </div>

            <div class="card-footer text-end">
                <button class="btn btn-primary" type="submit">
                    {{ $isEdit ? __('labels.update') : __('labels.create') }}
                </button>
            </div>
        </div>
    </form>

@endsection
@push('scripts')
    <script src="{{ hyperAsset('assets/js/product.js') }}" defer></script>
    <script>
        let optionIndex = {{ isset($addon) && $addon?->options ? count($addon->options) : 0 }};

        document.getElementById('add-option').addEventListener('click', function() {
            document.getElementById('options-wrapper').insertAdjacentHTML('beforeend', `
                <div class="option-item mb-2 d-flex gap-2">
                    <input type="text" name="options[${optionIndex}][name]" class="form-control" placeholder="Name">
                    <input type="number" name="options[${optionIndex}][price]" class="form-control" placeholder="Price">
                    <button type="button" class="btn btn-danger remove-option">X</button>
                </div>
            `);
            optionIndex++;
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-option')) {
                e.target.closest('.option-item').remove();
            }
        });
    </script>
@endpush
