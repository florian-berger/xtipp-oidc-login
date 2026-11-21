@extends('layouts.app')
@section('title', trans('oidc::ui.admin.index.sidename'))
@section('content')
    <div class="view-wrapper" data-view="plugin-oidc-index">
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
                            <button
                                type="button"
                                class="btn-small w-auto px-2 whitespace-nowrap"
                                style="flex-shrink: 0;"
                                title="{{ trans('oidc::ui.admin.index.copy_title') }}"
                                data-copy-text="{{ $redirectUrls[$singleProvider->id] }}"
                            >{{ trans('oidc::ui.admin.index.copy_redirect_url') }}</button>
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

    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy-text]');
            if (!button) {
                return;
            }

            navigator.clipboard.writeText(button.dataset.copyText).then(function () {
                var originalText = button.textContent;
                button.textContent = @json(trans('oidc::ui.admin.index.copied'));
                setTimeout(function () {
                    button.textContent = originalText;
                }, 1500);
            });
        });
    </script>
@endsection
