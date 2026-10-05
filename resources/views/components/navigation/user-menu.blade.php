{{--
    Office room navigation on the dark header.
    items: list of ['label' => ..., 'href' => ?string, 'icon' => ?string, 'active' => bool].
--}}
@props([
    'items' => [],
    'orientation' => 'horizontal',
])

<nav aria-label="{{ __('ui.nav.office') }}" {{ $attributes }}>
    <ul class="flex gap-1 {{ $orientation === 'vertical' ? 'flex-col' : 'flex-row flex-wrap items-center' }}">
        @foreach ($items as $item)
            @php
                $active = (bool) ($item['active'] ?? false);
                $href = $item['href'] ?? null;
            @endphp
            <li>
                @if ($href !== null)
                    <a
                        href="{{ $href }}"
                        @if ($active) aria-current="page" @endif
                        class="flex items-center gap-2 rounded-control px-3 py-2 text-body font-medium transition-colors duration-150 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-on-primary {{ $active ? 'bg-secondary-hover text-on-primary' : 'text-on-primary/80 hover:bg-secondary-hover hover:text-on-primary' }}"
                    >
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" size="sm" />@endif
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <span aria-disabled="true" title="{{ __('ui.nav.soon') }}" class="flex cursor-not-allowed items-center gap-2 rounded-control px-3 py-2 text-body font-medium text-on-primary/60">
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" size="sm" />@endif
                        <span>{{ $item['label'] }}</span>
                        <span class="sr-only">({{ __('ui.nav.soon') }})</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
