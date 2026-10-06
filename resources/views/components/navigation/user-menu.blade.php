{{--
    Office room navigation: slide-sorter thumbnails (tab bar on phones).
    items: list of ['label' => ..., 'href' => ?string, 'icon' => ?string, 'image' => ?string, 'active' => bool].
--}}
@props([
    'items' => [],
])

<nav aria-label="{{ __('ui.nav.office') }}" class="sorter-nav" {{ $attributes }}>
    <ul>
        @foreach ($items as $item)
            @php
                $active = (bool) ($item['active'] ?? false);
                $href = $item['href'] ?? null;
                $tag = $href !== null ? 'a' : 'span';
            @endphp
            <li>
                <{{ $tag }}
                    @if ($href !== null) href="{{ $href }}" @else aria-disabled="true" title="{{ __('ui.nav.soon') }}" @endif
                    @if ($active) aria-current="page" @endif
                    class="thumb focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-iris {{ $href === null ? 'cursor-not-allowed opacity-50' : '' }}"
                >
                    @if (! empty($item['image']))
                        <img src="{{ $item['image'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    @endif
                    <span class="thumb-num" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                    <span class="thumb-icon">
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" size="sm" />@endif
                    </span>
                    <span class="thumb-label">{{ $item['label'] }}</span>
                    @if ($href === null)<span class="sr-only">({{ __('ui.nav.soon') }})</span>@endif
                </{{ $tag }}>
            </li>
        @endforeach
    </ul>
</nav>
