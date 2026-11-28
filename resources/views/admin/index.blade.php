@extends('layouts.app')
@section('title', trans('oidc::ui.admin.index.sidename'))
@section('content')
    <div class="view-wrapper" data-view="plugin-oidc-index">
        @if ($debugEnabled)
            <div class="w-full px-2 py-2 mb-4 bg-yellow-400" role="status">
                <strong>{{ trans('oidc::ui.admin.index.debug_title') }}</strong>
                {{ trans('oidc::ui.admin.index.debug_text') }}
                @if (Route::has('admin.config.plugins.settings'))
                    <a href="{{ route('admin.config.plugins.settings', ['name' => 'oidc']) }}" class="underline">{{ trans('oidc::ui.admin.index.debug_settings') }}</a>
                @endif
            </div>
        @endif

        <div class="mb-10 text-right">
            <a href="{{ route('plugin-oidc.admin.new') }}" title="{{ trans('oidc::ui.admin.index.create') }}" class="btn-normal bg-emerald-500">
                <i class="fa fa-plus mr-1" aria-hidden="true"></i>
                {{ trans('oidc::ui.admin.index.create') }}
            </a>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[900px]">
                <div class="font-bold bg-slate-200 flex p-2 gap-2">
                    <div class="w-2/12">{{ trans('oidc::ui.admin.index.name') }}</div>
                    <div class="w-4/12">{{ trans('oidc::ui.admin.index.slug') }}</div>
                    <div class="w-3/12">{{ trans('oidc::ui.admin.index.issuer') }}</div>
                    <div class="w-1/12">{{ trans('oidc::ui.admin.index.active') }}</div>
                    <div class="w-2/12 text-right">{{ trans('oidc::ui.admin.index.action') }}</div>
                </div>
                @forelse ($allOidcProviders as $singleProvider)
                    <div class="odd:bg-slate-200 flex items-center p-2 gap-2">
                        <div class="w-2/12">{{ $singleProvider->name }}</div>
                        <div class="w-4/12 min-w-0 flex items-center gap-2">
                            <span class="truncate min-w-0">{{ $singleProvider->slug }}</span>
                            <div class="plugin-oidc-copy" data-copy-menu>
                                <button
                                    type="button"
                                    class="btn-small w-auto px-2 whitespace-nowrap"
                                    aria-haspopup="menu"
                                    aria-expanded="false"
                                    data-copy-menu-toggle
                                >{{ trans('oidc::ui.admin.index.copy') }}</button>
                                <div class="plugin-oidc-copy-menu" role="menu" hidden data-copy-menu-list>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        title="{{ trans('oidc::ui.admin.index.copy_title') }}"
                                        data-copy-text="{{ $redirectUrls[$singleProvider->id] }}"
                                    >{{ trans('oidc::ui.admin.index.copy_redirect_url') }}</button>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        title="{{ trans('oidc::ui.admin.index.copy_launch_title') }}"
                                        data-copy-text="{{ $launchUrls[$singleProvider->id] }}"
                                    >{{ trans('oidc::ui.admin.index.copy_launch_url') }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="w-3/12 truncate">{{ $singleProvider->issuer }}</div>
                        <div class="w-1/12">{{ $singleProvider->active ? trans('oidc::ui.admin.index.yes') : trans('oidc::ui.admin.index.no') }}</div>
                        <div class="w-2/12 flex gap-2 justify-end">
                            <a href="{{ route('plugin-oidc.admin.edit', ['id' => $singleProvider->id]) }}" class="btn-normal w-auto px-2 whitespace-nowrap">{{ trans('oidc::ui.admin.index.edit') }}<span class="sr-only">: {{ $singleProvider->name }}</span></a>
                            <form method="POST" action="{{ route('plugin-oidc.admin.delete', ['id' => $singleProvider->id]) }}" class="inline" data-confirm="{{ trans('oidc::ui.admin.index.delete_confirm', ['name' => $singleProvider->name]) }}" data-confirm-title="{{ trans('oidc::ui.admin.index.delete_title') }}" data-confirm-button="{{ trans('oidc::ui.admin.index.delete') }}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn-normal-danger w-auto px-2 whitespace-nowrap">{{ trans('oidc::ui.admin.index.delete') }}<span class="sr-only">: {{ $singleProvider->name }}</span></button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-2">{{ trans('oidc::ui.admin.index.empty') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <style>
        .plugin-oidc-copy { flex-shrink: 0; }
        /* Fixed instead of absolute: the list scrolls horizontally and would clip the menu of the last rows */
        .plugin-oidc-copy-menu {
            position: fixed; z-index: 50; min-width: 10rem; padding: 0.25rem 0;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 0.25rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .plugin-oidc-copy-menu[hidden] { display: none; }
        .plugin-oidc-copy-menu button { display: block; width: 100%; padding: 0.375rem 0.75rem; text-align: left; white-space: nowrap; cursor: pointer; }
        .plugin-oidc-copy-menu button:hover, .plugin-oidc-copy-menu button:focus { background: #e2e8f0; outline: none; }
    </style>

    <script nonce="{{ csp_nonce() }}">
        (function () {
            var copiedText = @json(trans('oidc::ui.admin.index.copied'));
            var openMenu = null;

            function closeMenu(focusToggle) {
                if (!openMenu) {
                    return;
                }
                var toggle = openMenu.querySelector('[data-copy-menu-toggle]');
                openMenu.querySelector('[data-copy-menu-list]').hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
                if (focusToggle) {
                    toggle.focus();
                }
                openMenu = null;
            }

            function showMenu(container) {
                var toggle = container.querySelector('[data-copy-menu-toggle]');
                var list = container.querySelector('[data-copy-menu-list]');
                var rect = toggle.getBoundingClientRect();

                list.hidden = false;
                list.style.top = (rect.bottom + 4) + 'px';
                list.style.left = Math.max(4, Math.min(rect.left, window.innerWidth - list.offsetWidth - 4)) + 'px';
                // Open above the button if there is no room below it
                if (rect.bottom + 4 + list.offsetHeight > window.innerHeight) {
                    list.style.top = Math.max(4, rect.top - 4 - list.offsetHeight) + 'px';
                }
                toggle.setAttribute('aria-expanded', 'true');
                list.querySelector('[data-copy-text]').focus();
                openMenu = container;
            }

            document.addEventListener('click', function (event) {
                var toggle = event.target.closest('[data-copy-menu-toggle]');
                if (toggle) {
                    var container = toggle.closest('[data-copy-menu]');
                    var wasOpen = openMenu === container;
                    closeMenu(false);
                    if (!wasOpen) {
                        showMenu(container);
                    }
                    return;
                }

                var item = event.target.closest('[data-copy-text]');
                if (!item) {
                    closeMenu(false);
                    return;
                }

                var itemToggle = item.closest('[data-copy-menu]').querySelector('[data-copy-menu-toggle]');
                closeMenu(true);
                navigator.clipboard.writeText(item.dataset.copyText).then(function () {
                    var originalText = itemToggle.textContent;
                    itemToggle.textContent = copiedText;
                    setTimeout(function () {
                        itemToggle.textContent = originalText;
                    }, 1500);
                });
            });

            document.addEventListener('keydown', function (event) {
                if (!openMenu) {
                    return;
                }
                var items = Array.prototype.slice.call(openMenu.querySelectorAll('[data-copy-text]'));
                var index = items.indexOf(document.activeElement);

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMenu(true);
                } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    var step = event.key === 'ArrowDown' ? 1 : -1;
                    items[(index + step + items.length) % items.length].focus();
                } else if (event.key === 'Tab') {
                    closeMenu(false);
                }
            });

            // The menu is fixed, so it would stay behind when the page or the list scrolls
            window.addEventListener('scroll', function () { closeMenu(false); }, true);
            window.addEventListener('resize', function () { closeMenu(false); });
        })();
    </script>
@endsection
