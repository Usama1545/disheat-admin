<div class="d-flex flex-wrap gap-1">
    @foreach ($addons as $addon)
        <span class="badge bg-indigo-lt text-uppercase">
            {{ $addon->name }}
        </span>
    @endforeach
</div>
