@php
    use App\Enums\Order\OrderItemStatusEnum;
    use App\Enums\Product\ProductImageFitEnum;
    use App\Enums\Product\ProductTypeEnum;
    use Illuminate\Support\Str;
@endphp
@extends('layouts.seller.app', [
    'page' => $menuSeller['products']['active'] ?? '',
    'sub_page' => $menuSeller['products']['route']['add_products']['sub_active'],
])
@php
    $title = empty($product) ? __('labels.add_product') : __('labels.edit_product');
@endphp
@section('title', $title)

@section('header_data')
    @php
        $page_title = $title;
        $page_pretitle = __('labels.seller') . ' ' . __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.products'), 'url' => route('seller.products.index')],
        ['title' => $title, 'url' => ''],
    ];
@endphp

@section('seller-content')

    <form id="product-form-submit" method="POST"
        action="{{ empty($product) ? route('seller.products.store') : route('seller.products.update', ['id' => $product->id]) }}"
        enctype="multipart/form-data" novalidate>
        @csrf
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $title }}</h3>
                <div class="card-actions">
                    <a href="{{ route('seller.products.index') }}" class="btn btn-secondary">
                        <i class="ti ti-arrow-left me-1"></i> {{ __('labels.back_to_products') }}
                    </a>
                </div>
            </div>
            <div class="card-header">
                <nav class="nav nav-segmented nav-2 w-100" role="tablist">
                    <button type="button" class="nav-link active" data-step="1" aria-selected="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-category">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 4h6v6h-4z" />
                            <path d="M14 4h6v6h-6z" />
                            <path d="M4 14h6v6h-6z" />
                            <path d="M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                        </svg>
                        Select Category
                    </button>
                    <button type="button" class="nav-link" data-step="2" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-info">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                            <path d="M11 14h1v4h1" />
                            <path d="M12 11h.01" />
                        </svg>
                        Product Info
                    </button>
                    <button type="button" class="nav-link" data-step="3" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-pizza">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path
                                d="M12 21.5c-3.04 0 -5.952 -1.18 -8.12 -3.26l.02 -2.24l12.1 -12.1l2.24 .02c2.08 2.17 3.26 5.08 3.26 8.12c0 6.63 -5.37 12 -12 12z" />
                            <path d="M7 8l.01 .01" />
                            <path d="M12 12l.01 .01" />
                            <path d="M12 16l.01 .01" />
                            <path d="M16 12l.01 .01" />
                            <path d="M8 12l.01 .01" />
                            <path d="M12 8l.01 .01" />
                        </svg>
                        {{ __('labels.details') }}
                    </button>
                    <button type="button" class="nav-link" data-step="4" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-layout-collage">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                            <path d="M10 4l4 16" />
                            <path d="M12 12l-8 2" />
                        </svg>
                        {{ __('labels.images') }}
                    </button>
                    <button type="button" class="nav-link" data-step="5" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-file-description">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                            <path d="M9 17h6" />
                            <path d="M9 13h6" />
                        </svg>
                        {{ __('labels.description') }}
                    </button>
                    <button type="button" class="nav-link" data-step="6" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-currency-dollar">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M16.7 8a3 3 0 0 0 -2.7 -2h-4a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-4a3 3 0 0 1 -2.7 -2" />
                            <path d="M12 3v3m0 12v3" />
                        </svg>
                        {{ __('labels.pricing') }}
                    </button>
                </nav>
            </div>

            <div class="card-body">
                <!-- Step 1: Category Selection -->
                <div class="wizard-step" data-step="1">
                    <div class="container">
                        <div class="mb-3">
                            <h4>Search Category</h4>
                            <select class="form-select" id="select-category" type="text">
                                <!-- Category options here, add :selected for $product->category_id -->
                            </select>
                        </div>
                        <div class="mb-3">

                            {{-- Keep div for jsTree --}}
                            <div id="categories" data-categories="{{ $categories }}"></div>
                            <input type="hidden" id="selected_category" name="category_id"
                                value="{{ $product->category_id ?? '' }}">
                        </div>
                        <div id="categories-tree" style="display: none;"></div>
                        <div class="mb-3">
                            <h4>Search Store</h4>
                            <select class="form-select" name="store_id" id="select-store" type="text">
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}"
                                        {{ !empty($product) && $product->store_id == $store->id ? 'selected' : '' }}>
                                        {{ $store->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="wizard-step d-none" data-step="2">
                    <div class="container">
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.product_title') }}</label>
                            <input type="text" class="form-control" name="title"
                                value="{{ $product->title ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.base_prep_time') }}</label>
                            <div class="input-group mb-2">
                                <input type="number" min="0" class="form-control" name="base_prep_time"
                                    value="{{ $product->base_prep_time ?? '' }}">
                                <span class="input-group-text">{{ __('labels.minutes') }}</span>
                            </div>
                        </div>
                        {{-- <div class="mb-3">
                            <label class="form-label">{{ __('labels.calories') }}</label>
                            <div class="input-group">
                                <input type="number" min="0" class="form-control" name="calories"
                                    value="{{ $product->calories ?? '' }}">
                                <span class="input-group-text">kcal</span>
                            </div>
                            <small class="form-hint">Optional: Estimated calories per serving</small>
                        </div> --}}
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.custom_fields') }}</label>
                            <div id="customFieldsContainer" class="vstack gap-2"
                                data-existing='@json($product->custom_fields ?? new \stdClass())'></div>
                            <button type="button" class="btn btn-outline-primary mt-2" id="addCustomFieldBtn">
                                <i class="ti ti-plus me-1"></i> Add Field
                            </button>
                            <small class="form-hint d-block mt-1">Add any additional product information like dietary info,
                                ingredients, etc.</small>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Product Details (Allergens & Dietary Options) -->
                <div class="wizard-step d-none" data-step="3">
                    <div class="container">
                        <div class="mb-3">
                            <h4>Addons</h4>
                            <select id="addon-choices" name="addon_ids[]" multiple class="form-select"
                                data-placeholder="Type to search addons...">
                            </select>
                            <small class="form-hint mt-2">Search and select addons - they will appear as tags</small>
                            <div id="selected-addons" data-selected='@json($selectedAddons)'></div>
                        </div>

                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Note:</strong> Selected addons will be available for customers to add to their order
                                when purchasing this product.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Images -->
            <div class="wizard-step d-none" data-step="4">
                <div class="container">
                    <div class="mb-3">
                        <label class="form-label required">{{ __('labels.main_image') }}</label>
                        <x-filepond_image name="main_image" imageUrl="{{ $product->main_image ?? '' }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.additional_images') }}</label>
                        <input type="file" name="additional_images[]" class="form-control"
                            data-images='@json($product->additional_images ?? [])' multiple>
                        <small class="form-hint">You can select multiple images at once</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.image_fit') }}</label>
                        <select class="form-select text-capitalize" name="image_fit">
                            @foreach (ProductImageFitEnum::values() as $value)
                                <option value="{{ $value }}"
                                    {{ !empty($product->image_fit) && $product->image_fit == $value ? 'selected' : '' }}>
                                    {{ Str::replace('_', ' ', $value) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 5: Description -->
            <div class="wizard-step d-none" data-step="5">
                <div class="container">
                    <div class="mb-3">
                        <label class="form-label required">{{ __('labels.short_description') }}</label>
                        <textarea class="form-control" name="short_description" rows="3">{{ $product->short_description ?? '' }}</textarea>
                        <small class="form-hint">Brief description that appears in product listings</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">{{ __('labels.description') }}</label>
                        <textarea class="form-control hugerte-mytextarea" name="description" rows="5">{{ $product->description ?? '' }}</textarea>
                        <small class="form-hint">Detailed description including ingredients, preparation method,
                            etc.</small>
                    </div>
                </div>
            </div>

            <!-- Step 6: Pricing -->
            <div class="wizard-step d-none" data-step="6">
                <div class="container">
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.tax_group') }}</label>
                        <select class="form-select" name="tax_groups[]" multiple id="select-tax-group">
                            @if (!empty($product))
                                @foreach ($product->taxClasses as $taxClass)
                                    <option value="{{ $taxClass->id }}" selected>{{ $taxClass->title }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Product Pricing</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label required">{{ __('labels.price') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text">{{ config('app.currency_symbol', '$') }}</span>
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                name="price" value="{{ $product->price ?? '' }}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('labels.compare_at_price') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text">{{ config('app.currency_symbol', '$') }}</span>
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                name="compare_at_price" value="{{ $product->compare_at_price ?? '' }}">
                                        </div>
                                        <small class="form-hint">Original price before discount (if any)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" id="prevStep">Previous</button>
            <button class="btn btn-primary" id="nextStep">Next</button>
        </div>
        </div>
    </form>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/vendor/js_tree/main.min.css') }}" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="{{ hyperAsset('assets/vendor/js_tree/main.min.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/product.js') }}" defer></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-calculate profit
            const priceInput = document.querySelector('input[name="price"]');
            const costInput = document.querySelector('input[name="cost_per_item"]');
            const profitDisplay = document.getElementById('profit_display');

            function calculateProfit() {
                const price = parseFloat(priceInput?.value) || 0;
                const cost = parseFloat(costInput?.value) || 0;
                const profit = price - cost;
                if (profitDisplay) {
                    profitDisplay.value = profit.toFixed(2);
                    // Change color based on profit
                    if (profit < 0) {
                        profitDisplay.classList.add('text-danger');
                        profitDisplay.classList.remove('text-success');
                    } else if (profit > 0) {
                        profitDisplay.classList.add('text-success');
                        profitDisplay.classList.remove('text-danger');
                    }
                }
            }

            if (priceInput && costInput) {
                priceInput.addEventListener('input', calculateProfit);
                costInput.addEventListener('input', calculateProfit);
                calculateProfit();
            }
        });
    </script>
@endpush
