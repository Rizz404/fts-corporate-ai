@props([
    'paginator',
])

<div {{ $attributes }}>
    {{ $paginator->onEachSide(1)->links('vendor.pagination.corporate') }}
</div>
