@php
    $user = auth()->user();
    $allowed = fn ($can) => $user->hasPermission(...(array) $can);
    $isActive = fn ($item) => request()->routeIs(...(array) ($item['active'] ?? $item['route'] ?? '__none'));
    $href = fn ($item) => route($item['route'], $item['params'] ?? []) . (isset($item['query']) ? '?' . http_build_query($item['query']) : '');
    $childActive = function ($child) use ($href) {
        $url = $href($child);
        $current = url()->full();
        return $current === $url || (! isset($child['query']) && url()->current() === $url && ! request()->hasAny(['tab', 'view', 'create']));
    };

    // Kelompokkan item per section; section tanpa item yang diizinkan tidak ditampilkan.
    $sections = [];
    foreach (config('menu') as $item) {
        if (isset($item['section'])) {
            $sections[] = ['title' => $item['section'], 'items' => []];
        } elseif ($allowed($item['can'])) {
            $sections[array_key_last($sections)]['items'][] = $item;
        }
    }
@endphp
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('img/logo-mark.svg') }}" width="36" height="36" alt="">
        <div>
            <div class="brand-name">Nexa<span>HCMS</span></div>
            <div class="brand-sub">ENTERPRISE SUITE</div>
        </div>
    </div>

    <div class="sidebar-user">
        <span class="avatar dark">{{ $user->employee?->initials() ?? strtoupper(substr($user->name, 0, 2)) }}</span>
        <div class="overflow-hidden">
            <div class="name text-truncate">{{ $user->name }}</div>
            <div class="role text-truncate">{{ $user->employee?->position?->name ?? $user->role?->name }}</div>
        </div>
    </div>

    <ul class="sidebar-nav" id="sidebarNav">
        @foreach ($sections as $section)
            @continue(empty($section['items']))
            <li class="nav-section px-2">{{ $section['title'] }}</li>
            @foreach ($section['items'] as $item)
                @php $active = $isActive($item) || collect($item['children'] ?? [])->contains(fn ($c) => $childActive($c)); @endphp
                @if (empty($item['children']))
                    <li>
                        <a href="{{ $href($item) }}" class="nav-main {{ $active ? 'active' : '' }}">
                            <span class="material-symbols-outlined">{{ $item['icon'] }}</span>{{ $item['label'] }}
                            @if (! empty($item['live']))<span class="badge-live">Live</span>@endif
                        </a>
                    </li>
                @else
                    @php $collapseId = 'nav-' . \Illuminate\Support\Str::slug($item['label']); @endphp
                    <li class="nav-group {{ $active ? 'open' : '' }}">
                        <button type="button" class="nav-main nav-toggle {{ $active ? '' : 'collapsed' }}"
                                data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                aria-expanded="{{ $active ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                            <span class="material-symbols-outlined">{{ $item['icon'] }}</span>{{ $item['label'] }}
                            <span class="material-symbols-outlined nav-chevron">expand_more</span>
                        </button>
                        <div class="collapse {{ $active ? 'show' : '' }}" id="{{ $collapseId }}" data-bs-parent="#sidebarNav">
                            <ul class="nav-sub">
                                @foreach ($item['children'] as $child)
                                    @continue(! $allowed($child['can']))
                                    <li><a href="{{ $href($child) }}" class="{{ $active && $childActive($child) ? 'active' : '' }}">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endif
            @endforeach
        @endforeach
    </ul>
</aside>
