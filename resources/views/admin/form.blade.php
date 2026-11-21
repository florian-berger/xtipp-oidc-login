@php
    $isNew = !$oidcProvider->exists;
@endphp

@extends('layouts.app')
@section('title', trans($isNew ? 'oidc::ui.admin.form.sidename-new' : 'oidc::ui.admin.form.sidename-edit'))
@section('content')
    @include('oidc::partials.styles')
    <div class="view-wrapper" data-view="plugin-oidc-form">
        <div class="mb-10 text-right">
            <a href="{{ route('plugin-oidc.admin.index') }}" title="{{ trans('oidc::ui.admin.form.back_overview') }}" class="btn-normal">{{ trans('oidc::ui.admin.form.back_overview') }}</a>
        </div>

        @if (count($errors) > 0)
            <div class="error-message">
                {!! trans('messages.mistake') !!}<br /><br />
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $isNew ? route('plugin-oidc.admin.create') : route('plugin-oidc.admin.update') }}" method="post">
            {{ csrf_field() }}
            @unless ($isNew)
                <input type="hidden" name="oidc_provider_id" value="{{ $oidcProvider->id }}" />
            @endunless

            {{-- Templates only on the create page; without a valid file there are none and the dropdown is hidden --}}
            @if ($isNew && !empty($providerTemplates))
                <div class="mb-5">
                    <label for="oidc_template" class="label-normal-gray">{{ trans('oidc::ui.admin.form.template') }}</label>
                    <select id="oidc_template" class="input-normal-gray">
                        <option value="">{{ trans('oidc::ui.admin.form.template_none') }}</option>
                        @foreach ($providerTemplates as $providerTemplate)
                            <option value="{{ $providerTemplate['key'] }}">{{ $providerTemplate['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-sm text-gray-500">{{ trans('oidc::ui.admin.form.template_help') }}</p>
                    <div id="oidc_template_hint" class="hidden bg-slate-200 rounded p-3 mt-2" role="status" aria-live="polite">
                        <span id="oidc_template_hint_text"></span>
                        <a id="oidc_template_hint_link" href="#" target="_blank" rel="noopener noreferrer" class="hidden underline break-all"></a>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 md:gap-5">
                <div>
                    <label for="name" class="label-normal-gray">{{ trans('oidc::ui.admin.form.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $oidcProvider->name) }}" id="name" class="input-normal-gray" />
                </div>
                <div>
                    <label for="slug" class="label-normal-gray">{{ trans('oidc::ui.admin.form.slug') }}</label>
                    <input type="text" name="slug" value="{{ old('slug', $oidcProvider->slug) }}" id="slug" class="input-normal-gray" />
                </div>
            </div>
            <div>
                <label for="issuer" class="label-normal-gray">{{ trans('oidc::ui.admin.form.issuer') }}</label>
                <input type="text" name="issuer" value="{{ old('issuer', $oidcProvider->issuer) }}" id="issuer" class="input-normal-gray" placeholder="https://" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 md:gap-5">
                <div>
                    <label for="client_id" class="label-normal-gray">{{ trans('oidc::ui.admin.form.client_id') }}</label>
                    <input type="text" name="client_id" value="{{ old('client_id', $oidcProvider->client_id) }}" id="client_id" class="input-normal-gray" />
                </div>
                <div>
                    <label for="client_secret" class="label-normal-gray">{{ trans('oidc::ui.admin.form.client_secret') }}</label>
                    <input type="password" name="client_secret" value="" id="client_secret" class="input-normal-gray" autocomplete="off"
                           @unless ($isNew) placeholder="{{ trans('oidc::ui.admin.form.client_secret_keep') }}" @endunless />
                </div>
            </div>
            <div>
                <label for="scopes" class="label-normal-gray">{{ trans('oidc::ui.admin.form.scopes') }}</label>
                <input type="text" name="scopes" value="{{ old('scopes', $oidcProvider->scopes) }}" id="scopes" class="input-normal-gray" />
            </div>
            <div class="mt-5{{ $errors->has('extra_authorize_params') ? ' has-error' : '' }}">
                <label for="extra_authorize_params" class="label-normal-gray">{{ trans('oidc::ui.admin.form.extra_authorize_params') }}</label>
                <textarea name="extra_authorize_params" id="extra_authorize_params" class="input-normal-gray h-20">{{ old('extra_authorize_params', $oidcProvider->extra_authorize_params ? json_encode($oidcProvider->extra_authorize_params) : '') }}</textarea>
                <p class="text-sm text-gray-500">{{ trans('oidc::ui.admin.form.extra_authorize_params_help') }}</p>
            </div>
            <div class="mt-5">
                <label>
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $oidcProvider->active))> {{ trans('oidc::ui.admin.form.active') }}
                </label>
            </div>

            <div class="mt-8">
                <h2 class="font-bold" style="font-size: 1.125rem; line-height: 1.75rem;">{{ trans('oidc::ui.admin.form.appearance') }}</h2>
                <hr class="mt-1 mb-5" />

                <div class="grid grid-cols-1 md:grid-cols-2 md:gap-5">
                    <div>
                        <div>
                            <label for="button_label" class="label-normal-gray">{{ trans('oidc::ui.admin.form.button_label') }}</label>
                            <input type="text" name="button_label" value="{{ old('button_label', $oidcProvider->button_label) }}" id="button_label" class="input-normal-gray" />
                            <p class="text-sm text-gray-500">{{ trans('oidc::ui.admin.form.button_label_help') }}</p>
                        </div>
                        <div class="mt-5">
                            <label for="button_color" class="label-normal-gray">{{ trans('oidc::ui.admin.form.button_color') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" id="button_color_picker" value="{{ old('button_color', $oidcProvider->button_color) ?: '#6b7280' }}" class="cursor-pointer" style="height: 2.5rem; width: 3.5rem; padding: 0; border: 0;" />
                                <input type="text" name="button_color" value="{{ old('button_color', $oidcProvider->button_color) }}" id="button_color" class="input-normal-gray" placeholder="#6B7280" maxlength="7" />
                            </div>
                        </div>
                        <div class="mt-5">
                            <label for="icon" class="label-normal-gray">{{ trans('oidc::ui.admin.form.icon') }}</label>
                            <input type="text" name="icon" value="{{ old('icon', $oidcProvider->icon) }}" id="icon" class="input-normal-gray" placeholder="fa-brands fa-twitch" />
                            <p class="text-sm text-gray-500">{{ trans('oidc::ui.admin.form.icon_help') }}</p>
                        </div>
                    </div>
                    <div>
                        <span class="label-normal-gray">{{ trans('oidc::ui.admin.form.preview') }}</span>
                        <span id="oidc_button_preview" class="btn-normal plugin-oidc-button inline-block">
                            <i id="oidc_button_preview_icon" class="hidden mr-2"></i>
                            <span id="oidc_button_preview_label"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap sm:justify-end">
                <div class="mt-1 sm:order-2">
                    <button type="submit" class="btn-normal bg-emerald-500">
                        <i class="fa fa-check mr-1" aria-hidden="true"></i>
                        {{ trans($isNew ? 'oidc::ui.admin.form.create' : 'oidc::ui.admin.form.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- The json directive of Blade splits its argument at commas, so only plain variables are passed to it --}}
    @php
        $jsLabelTemplate   = trans('oidc::ui.login_with', ['provider' => '__PROVIDER__']);
        $jsNamePlaceholder = \FlorianBerger\Oidc\Models\OidcProvider::BUTTON_LABEL_NAME_PLACEHOLDER;
        $jsTemplates       = (object) collect($providerTemplates ?? [])->keyBy('key')->all();
        $jsDefaultScopes   = $defaultScopes ?? '';
        $jsHintPlain       = trans('oidc::ui.admin.form.template_applied', ['name' => '__NAME__']);
        $jsHintConsole     = trans('oidc::ui.admin.form.template_applied_console', ['name' => '__NAME__']);
    @endphp
    <script nonce="{{ csp_nonce() }}">
        (function () {
            var nameInput     = document.getElementById('name');
            var labelInput    = document.getElementById('button_label');
            var colorText     = document.getElementById('button_color');
            var colorPicker   = document.getElementById('button_color_picker');
            var iconInput     = document.getElementById('icon');
            var preview       = document.getElementById('oidc_button_preview');
            var previewIcon   = document.getElementById('oidc_button_preview_icon');
            var previewLabel  = document.getElementById('oidc_button_preview_label');
            var labelTemplate   = @json($jsLabelTemplate);
            var namePlaceholder = @json($jsNamePlaceholder);

            function updatePreview() {
                // Same as OidcProvider::buttonText(): the own label with "$Name" replaced, otherwise "Anmelden mit <name>"
                var providerName = nameInput.value.trim() || 'Provider';
                var ownLabel     = labelInput.value.trim();
                previewLabel.textContent = ownLabel
                    ? ownLabel.split(namePlaceholder).join(providerName)
                    : labelTemplate.replace('__PROVIDER__', providerName);

                var color = colorText.value.trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    preview.style.backgroundColor = color;
                    colorPicker.value = color;
                } else {
                    preview.style.backgroundColor = '';
                }

                var iconClass = iconInput.value.trim();
                previewIcon.className = iconClass ? iconClass + ' mr-2' : 'hidden mr-2';
            }

            colorPicker.addEventListener('input', function () {
                colorText.value = colorPicker.value;
                updatePreview();
            });

            [nameInput, labelInput, colorText, iconInput].forEach(function (el) {
                el.addEventListener('input', updatePreview);
            });

            updatePreview();

            /*
             * Templates: fill the fields that are the same for every installation, without a reload. Client-ID and
             * Client-Secret are never touched. A field the template does not have is emptied or gets its default.
             */
            var templateSelect = document.getElementById('oidc_template');
            if (!templateSelect) {
                return;
            }

            var templates     = @json($jsTemplates);
            var defaultScopes = @json($jsDefaultScopes);
            var hint          = document.getElementById('oidc_template_hint');
            var hintText      = document.getElementById('oidc_template_hint_text');
            var hintLink      = document.getElementById('oidc_template_hint_link');
            var hintTexts     = {
                plain:   @json($jsHintPlain),
                console: @json($jsHintConsole)
            };
            var neutralColor  = '#6B7280';
            var fieldDefaults = {
                name: '', slug: '', issuer: '', scopes: defaultScopes, extra_authorize_params: '',
                button_label: '', icon: ''
            };

            templateSelect.addEventListener('change', function () {
                var template = templates[templateSelect.value];
                if (!template) {
                    hint.classList.add('hidden');
                    return;
                }

                Object.keys(fieldDefaults).forEach(function (field) {
                    var value = template[field];
                    document.getElementById(field).value = (value === null || value === undefined) ? fieldDefaults[field] : value;
                });

                // Always the color of the template; a neutral gray if it has none or an invalid one
                var templateColor = (typeof template.button_color === 'string') ? template.button_color.trim() : '';
                var buttonColor   = /^#[0-9A-Fa-f]{6}$/.test(templateColor) ? templateColor : neutralColor;
                colorText.value   = buttonColor;
                colorPicker.value = buttonColor.toLowerCase();
                updatePreview();

                // textContent only, the values come from a file and are never inserted as HTML
                if (template.console_url) {
                    hintText.textContent = hintTexts.console.replace('__NAME__', template.name) + ' ';
                    hintLink.textContent = template.console_url;
                    hintLink.href        = template.console_url;
                    hintLink.classList.remove('hidden');
                } else {
                    hintText.textContent = hintTexts.plain.replace('__NAME__', template.name);
                    hintLink.classList.add('hidden');
                    hintLink.removeAttribute('href');
                }
                hint.classList.remove('hidden');

                document.getElementById('client_id').focus();
            });
        })();
    </script>
@endsection
