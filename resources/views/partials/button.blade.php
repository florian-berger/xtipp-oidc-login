<div>
    <a
        href="{{ route('plugin-oidc.redirect', ['provider' => $oidcProvider->slug]) }}"
        class="btn-normal plugin-oidc-button mt-2 inline-block"
        @if ($oidcProvider->button_color) style="background-color: {{ $oidcProvider->button_color }};" @endif
    >
        @if ($oidcProvider->icon)
            <i class="{{ $oidcProvider->icon }} mr-2"></i>
        @endif
        {{ trans('oidc::ui.login_with', ['provider' => $oidcProvider->displayName()]) }}
    </a>
</div>
