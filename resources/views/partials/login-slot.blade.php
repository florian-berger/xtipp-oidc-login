{{-- Shown below the login form of xTIPP, through the slot "layout.content.bottom" (xTIPP has no slot in the form) --}}
@include('oidc::partials.styles')
<section class="mx-auto w-full md:w-1/2 text-center" style="margin-top: 0.5rem; margin-bottom: 1.0rem;" aria-label="{{ trans('oidc::ui.login.sidename') }}">
    <div class="plugin-oidc-separator text-gray-500">
        <span>{{ trans('oidc::ui.or') }}</span>
    </div>

    @foreach ($oidcProviders as $oidcProvider)
        @include('oidc::partials.button', ['oidcProvider' => $oidcProvider])
    @endforeach
</section>
