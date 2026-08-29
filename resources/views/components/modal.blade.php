@props([
    'id' => null,
    'title' => null,
    'maxWidth' => 'md',
    'open' => false,
    'animated' => false,
    'showCloseButton' => true,
])

@php
    $sizes = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
    $sizeClass = $sizes[$maxWidth] ?? $sizes['md'];
@endphp

<div
    id="{{ $id }}"
    role="dialog"
    aria-modal="true"
    @if($title) aria-labelledby="{{ $id }}-title" @endif
    data-modal
    @if($animated) data-modal-animated @endif
    class="fixed inset-0 z-50 overflow-y-auto {{ $animated ? 'opacity-0 transition-opacity duration-300' : '' }} {{ $open ? '' : 'hidden' }}"
>
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900/75 transition-opacity" data-modal-overlay aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div data-modal-panel
             class="relative inline-block align-bottom bg-white dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:w-full w-full border dark:border-border-dark {{ $sizeClass }} {{ $animated ? 'scale-95 transition-transform duration-300' : '' }}">
            @if($title || $showCloseButton)
            <div class="flex items-center justify-between px-6 pt-5 sm:pt-6 {{ $title ? '' : 'justify-end' }}">
                @if($title)
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-text-primary-dark" id="{{ $id }}-title">{{ $title }}</h3>
                @endif
                @if($showCloseButton)
                <button type="button"
                        data-modal-close
                        class="p-2 -m-2 rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-text-primary-dark hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                        aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                @endif
            </div>
            @endif

            {{ $slot }}

            @isset($footer)
            <div class="bg-gray-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200 dark:border-border-dark">
                {{ $footer }}
            </div>
            @endisset
        </div>
    </div>
</div>