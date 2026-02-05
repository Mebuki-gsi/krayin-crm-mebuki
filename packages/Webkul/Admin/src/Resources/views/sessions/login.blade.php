<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('admin::app.users.login.title')
        </x-slot>

        <!-- UI Container with Standard CSS Layout -->
        <div
            style="display: flex; height: 100vh; width: 100%; overflow: hidden; font-family: 'Poppins', sans-serif; background: #f9fafb;">

            <!-- Left Side: Branding (Standard CSS flex) -->
            <div class="login-branding"
                style="flex: 0 0 60%; position: relative; display: flex; flex-direction: column; justify-content: space-between; padding: 3rem; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('{{ asset('images/crm_login_background.png') }}'); background-size: cover; background-position: center;">

                <div style="position: relative; z-index: 10;">
                    @if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                        <img style="height: 3rem; width: auto;" src="{{ Storage::url($logo) }}"
                            alt="{{ config('app.name') }}" />
                    @else
                        <img style="height: 3rem; width: auto; filter: brightness(0) invert(1);"
                            src="{{ url('images/logo_mebuki.png') }}" alt="{{ config('app.name') }}" />
                    @endif
                </div>

                <div style="position: relative; z-index: 10;">
                    <h1
                        style="font-size: 3.5rem; font-weight: 800; color: white; line-height: 1.2; margin: 0; text-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        Impulsione suas Vendas<br />
                        <span style="color: var(--brand-color, #046c8e);">com Inteligência.</span>
                    </h1>
                    <p
                        style="margin-top: 1.5rem; max-width: 450px; font-size: 1.125rem; color: rgba(255,255,255,0.9); line-height: 1.6; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        Gerencie seus leads, cotações e relacionamentos em uma plataforma unificada desenhada para o
                        alto desempenho comercial.
                    </p>
                </div>

                <div style="position: relative; z-index: 10; font-size: 0.875rem; color: rgba(255,255,255,0.7);">
                    © {{ date('Y') }} Mebuki.
                </div>
            </div>

            <!-- Right Side: Login Form -->
            <div
                style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; background: #f9fafb; position: relative;">
                <!-- VERSION MARKER: 2.2 -->
                <div style="position: absolute; top: 10px; right: 10px; font-size: 10px; color: #ccc;">v2.2</div>
                <div style="width: 100%; max-width: 420px;">

                    <!-- Mobile Logo Header -->
                    <div class="mobile-logo-header" style="text-align: center; margin-bottom: 2.5rem; display: none;">
                        @if ($logo = core()->getConfigData('general.design.admin_logo.logo_image'))
                            <img style="height: 2.5rem; width: auto;" src="{{ Storage::url($logo) }}"
                                alt="{{ config('app.name') }}" />
                        @else
                            <img style="height: 2.5rem; width: auto;" src="{{ url('images/logo_mebuki.png') }}"
                                alt="{{ config('app.name') }}" />
                        @endif
                    </div>

                    <div style="margin-bottom: 2rem;">
                        <h2 style="font-size: 1.875rem; font-weight: 700; color: #111827; margin: 0;">
                            @lang('admin::app.users.login.title')
                        </h2>
                        <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                            Bem-vindo de volta! Por favor, insira suas credenciais.
                        </p>
                    </div>

                    <!-- Form Card with Standard CSS Shadow -->
                    <div
                        style="background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); border: 1px solid #f3f4f6;">
                        {!! view_render_event('admin.sessions.login.form_controls.before') !!}

                        <x-admin::form :action="route('admin.session.store')">
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                <!-- Email -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required"
                                        style="font-weight: 600; color: #374151;">
                                        @lang('admin::app.users.login.email')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control type="email" class="w-full" id="email"
                                        name="email" rules="required|email"
                                        style="height: 3rem; padding: 0 1rem; border-radius: 0.75rem; border: 1px solid #d1d5db;"
                                        :label="trans('admin::app.users.login.email')"
                                        :placeholder="trans('admin::app.users.login.email')" />

                                    <x-admin::form.control-group.error control-name="email" />
                                </x-admin::form.control-group>

                                <!-- Password -->
                                <x-admin::form.control-group class="relative w-full">
                                    <div
                                        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                                        <x-admin::form.control-group.label class="required"
                                            style="margin: 0; font-weight: 600; color: #374151;">
                                            @lang('admin::app.users.login.password')
                                        </x-admin::form.control-group.label>

                                        <a style="font-size: 0.75rem; font-weight: 700; color: var(--brand-color, #046c8e); text-decoration: none;"
                                            href="{{ route('admin.forgot_password.create') }}">
                                            @lang('admin::app.users.login.forget-password-link')
                                        </a>
                                    </div>

                                    <div class="relative" style="position: relative;">
                                        <x-admin::form.control-group.control type="password" class="w-full"
                                            style="height: 3rem; padding: 0 3rem 0 1rem; border-radius: 0.75rem; border: 1px solid #d1d5db;"
                                            id="password" name="password" rules="required|min:6"
                                            :label="trans('admin::app.users.login.password')"
                                            :placeholder="trans('admin::app.users.login.password')" />

                                        <span class="icon-eye-hide"
                                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.5rem; color: #9ca3af;"
                                            onclick="switchVisibility()" id="visibilityIcon" role="presentation"
                                            tabindex="0">
                                        </span>
                                    </div>

                                    <x-admin::form.control-group.error control-name="password" />
                                </x-admin::form.control-group>

                                <!-- Submit Button -->
                                <button class="primary-button"
                                    style="width: 100%; height: 3.25rem; border-radius: 0.75rem; border: none; color: white; background: var(--brand-color, #046c8e); font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(var(--brand-rgb, 4, 108, 142), 0.3);"
                                    aria-label="{{ trans('admin::app.users.login.submit-btn')}}">
                                    @lang('admin::app.users.login.submit-btn')
                                </button>
                            </div>
                        </x-admin::form>

                        {!! view_render_event('admin.sessions.login.form_controls.after') !!}
                    </div>
                </div>
            </div>
        </div>

        <!-- Responsive Styles -->
        <style>
            @media (max-width: 1024px) {
                .login-branding {
                    display: none !important;
                }

                .mobile-logo-header {
                    display: block !important;
                }
            }

            .primary-button:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }

            .primary-button:active {
                transform: translateY(0);
            }

            input:focus {
                outline: none;
                border-color: var(--brand-color, #046c8e) !important;
                box-shadow: 0 0 0 3px rgba(var(--brand-rgb, 4, 108, 142), 0.1) !important;
            }
        </style>

        @push('scripts')
            <script>
                function switchVisibility() {
                    let passwordField = document.getElementById("password");
                    let visibilityIcon = document.getElementById("visibilityIcon");

                    passwordField.type = passwordField.type === "password" ? "text" : "password";
                    visibilityIcon.classList.toggle("icon-eye");
                    visibilityIcon.classList.toggle("icon-eye-hide");
                }
            </script>
        @endpush
</x-admin::layouts.anonymous>