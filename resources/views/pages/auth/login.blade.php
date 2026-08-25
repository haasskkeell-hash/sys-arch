<x-layouts::auth :title="__('Log in')">
    <div
        class="auth-stack"
        x-data='{
            submitting: false,
            showPassword: false,
            success: false,
            clientError: "",
            successTitle: "Welcome back",
            redirectUrl: "",
            showLoginSuccess(redirectUrl) {
                this.redirectUrl = redirectUrl;
                this.success = true;
                this.$root.closest(".auth-content")?.classList.add("auth-login-success");
                window.setTimeout(() => window.location.assign(this.redirectUrl), 1100);
            },
            async submitLogin(event) {
                event.preventDefault();

                if (this.submitting) {
                    return;
                }

                this.submitting = true;
                this.clientError = "";

                const form = event.currentTarget;

                try {
                    const response = await fetch(form.action, {
                        method: "POST",
                        body: new FormData(form),
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    });
                    const payload = await response.clone().json().catch(() => ({}));

                    if (payload.two_factor) {
                        window.location.assign("{{ route('two-factor.login') }}");
                        return;
                    }

                    if (payload.two_factor === false) {
                        this.showLoginSuccess("{{ route('dashboard') }}");
                        return;
                    }

                    if (response.redirected && response.url.includes("two-factor-challenge")) {
                        window.location.assign(response.url);
                        return;
                    }

                    const redirectedAwayFromLogin = response.url && !response.url.endsWith("/login");

                    if (response.ok && redirectedAwayFromLogin) {
                        this.showLoginSuccess(response.url);
                        return;
                    }

                    this.clientError = payload.errors?.email?.[0] || payload.message || "These credentials do not match our records.";
                    this.submitting = false;
                } catch (error) {
                    this.clientError = "Connection error. Try again.";
                    this.submitting = false;
                }
            }
        }'
    >
        <div x-show="! success" x-transition.opacity>
            <div class="auth-intro">
                <h1 class="auth-title">{{ __('Welcome back') }}</h1>
                <p class="auth-subtitle">{{ __('Sign in to FixTrack') }}</p>
            </div>

            <x-auth-session-status class="auth-status mb-5" :status="session('status')" />

            <div class="auth-social">
                <a
                    href="{{ route('auth.google.redirect') }}"
                    class="auth-google-button"
                    data-test="google-login-button"
                >
                    <svg class="auth-google-mark" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M21.35 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.42Z" />
                        <path fill="#34A853" d="M12 21.75c2.63 0 4.84-.87 6.45-2.36l-3.14-2.45c-.87.58-1.98.92-3.31.92-2.54 0-4.69-1.72-5.46-4.03H3.3v2.53A9.75 9.75 0 0 0 12 21.75Z" />
                        <path fill="#FBBC05" d="M6.54 13.83A5.86 5.86 0 0 1 6.23 12c0-.64.11-1.27.31-1.83V7.64H3.3A9.75 9.75 0 0 0 2.25 12c0 1.57.38 3.05 1.05 4.36l3.24-2.53Z" />
                        <path fill="#EA4335" d="M12 6.14c1.43 0 2.72.49 3.73 1.45l2.8-2.8C16.84 3.2 14.63 2.25 12 2.25A9.75 9.75 0 0 0 3.3 7.64l3.24 2.53c.77-2.31 2.92-4.03 5.46-4.03Z" />
                    </svg>
                    <span>{{ __('Continue with Google') }}</span>
                </a>

                <div class="auth-divider" aria-hidden="true">
                    <span>{{ __('OR CONTINUE WITH EMAIL') }}</span>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('login.store') }}"
                class="auth-form"
                x-on:submit="submitLogin($event)"
            >
                @csrf

                <div class="auth-field @error('email') auth-field-error @enderror">
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        class="auth-input"
                        placeholder=" "
                        required
                        autofocus
                        autocomplete="email"
                    >
                    <label for="email" class="auth-label">{{ __('Email address') }}</label>
                </div>

                <div class="auth-field auth-field-action @error('password') auth-field-error @enderror">
                    <input
                        id="password"
                        name="password"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        class="auth-input"
                        placeholder=" "
                        required
                        autocomplete="current-password"
                    >
                    <label for="password" class="auth-label">{{ __('Password') }}</label>
                    <button
                        type="button"
                        class="auth-icon-button"
                        x-on:click="showPassword = ! showPassword"
                        aria-label="{{ __('Toggle password visibility') }}"
                    >
                        <svg x-show="! showPassword" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" />
                            <circle cx="12" cy="12" r="2.5" />
                        </svg>
                        <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="m3 3 18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3 3.6M6.2 6.7C3.7 8.1 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5" />
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </div>

                <div class="auth-options">
                    <label class="auth-check">
                        <input type="checkbox" name="remember" class="auth-check-input" @checked(old('remember'))>
                        <span class="auth-check-box" aria-hidden="true">
                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m2.2 6.2 2.2 2.1 5.4-5" /></svg>
                        </span>
                        <span>{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="auth-forgot" href="{{ route('password.request') }}" wire:navigate>
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>

                <button type="submit" class="auth-primary-button" x-bind:disabled="submitting" data-test="login-button">
                    <span x-show="! submitting" class="auth-button-label">
                        {{ __('Continue') }}
                        <span class="auth-button-arrow" aria-hidden="true">→</span>
                    </span>
                    <span x-show="submitting" x-cloak class="auth-button-loading">
                        <span class="auth-spinner" aria-hidden="true"></span>
                        {{ __('Signing in…') }}
                    </span>
                </button>

                @if ($errors->any())
                    <div class="auth-error" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v4M12 16h.01" />
                        </svg>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="auth-error" x-show="clientError" x-cloak role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 8v4M12 16h.01" />
                    </svg>
                    <span x-text="clientError"></span>
                </div>
            </form>

            <p class="auth-switch">
                {{ __('No account?') }}
                <a href="{{ route('register') }}" wire:navigate>{{ __('Create one') }}</a>
            </p>
        </div>

        <div class="auth-success" x-show="success" x-cloak>
            <div class="auth-success-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5" />
                </svg>
            </div>
            <h1 class="auth-success-title" x-text="successTitle">{{ __('Welcome back') }}</h1>
            <p class="auth-success-subtitle">{{ __('Redirecting you to your dashboard') }}</p>
            <div class="auth-progress" aria-hidden="true"><span></span></div>
        </div>
    </div>
</x-layouts::auth>
