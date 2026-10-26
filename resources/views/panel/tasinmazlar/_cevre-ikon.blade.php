{{-- Çevresel analiz kategori ikonu. $kod · $boy (px, varsayılan 16) --}}
@php $boy = $boy ?? 16; @endphp
<svg width="{{ $boy }}" height="{{ $boy }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($kod)
        @case('ulasim')
            <rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 11h16M8 21l1-3M16 21l-1-3"/><circle cx="8" cy="14.5" r="1"/><circle cx="16" cy="14.5" r="1"/>
            @break
        @case('egitim')
            <path d="M22 9 12 4 2 9l10 5 10-5z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/>
            @break
        @case('saglik')
            <rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>
            @break
        @case('yesil')
            <path d="M12 22v-6"/><path d="M12 16c-4 0-7-2.5-7-6.5C5 6 8 3 12 3s7 3 7 6.5c0 4-3 6.5-7 6.5z"/>
            @break
        @case('ticaret')
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>
            @break
        @default
            <circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 3.1a4 4 0 0 1 0 7.8M21 21v-2a4 4 0 0 0-3-3.9"/>
    @endswitch
</svg>
