<x-layouts::auth :title="__('Register')">
    @php
        $selectedServiceCategories = old('service_categories');
        if (! is_array($selectedServiceCategories)) {
            $legacyServiceCategory = old('service_category');
            $selectedServiceCategories = $legacyServiceCategory ? [$legacyServiceCategory] : [];
        }
    @endphp

    <div
        class="auth-stack"
        x-data='{
            role: @js(old("role")),
            selectingRole: @js(! old("role")),
            submitting: false,
            showPassword: false,
            showConfirmation: false,
            chooseRole(value) {
                this.role = value;
                this.selectingRole = false;
                this.$nextTick(() => this.$refs.name?.focus());
            },
            changeRole() {
                this.role = "";
                this.selectingRole = true;
            }
        }'
    >
        <div class="auth-intro">
            <h1 class="auth-title" x-text="role === 'technician' ? 'Join as a technician' : role === 'customer' ? 'Create your customer account' : 'Create your account'">{{ __('Create your account') }}</h1>
            <p class="auth-subtitle" x-text="role === 'technician' ? 'Tell us about your services to start verification.' : role === 'customer' ? 'Book trusted home services with FixTrack.' : 'Choose how you will use FixTrack.'">{{ __('Choose how you will use FixTrack.') }}</p>
        </div>

        <x-auth-session-status class="auth-status mb-5" :status="session('status')" />

        @if ($errors->any())
            <div class="auth-error mb-5" role="alert">
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

        <div x-show="selectingRole" x-transition.opacity class="auth-role-view">
            <div class="auth-role-grid">
                <button
                    type="button"
                    class="auth-role-card"
                    x-on:click="chooseRole('customer')"
                    x-bind:aria-pressed="role === 'customer'"
                >
                    <span class="auth-role-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="12" cy="8" r="3" />
                            <path d="M5.5 20c.7-3.1 2.9-5 6.5-5s5.8 1.9 6.5 5" />
                        </svg>
                    </span>
                    <span>
                        <strong>{{ __('Customer') }}</strong>
                        <small>{{ __('Book and track home services.') }}</small>
                    </span>
                    <span class="ml-auto text-xl text-zinc-400" aria-hidden="true">→</span>
                </button>

                <button
                    type="button"
                    class="auth-role-card"
                    x-on:click="chooseRole('technician')"
                    x-bind:aria-pressed="role === 'technician'"
                >
                    <span class="auth-role-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="m14.5 6.5 3-3 3 3-3 3" />
                            <path d="m17.5 6.5-6.2 6.2" />
                            <path d="M5 8.5h4M5 12h3M5 15.5h4" />
                            <path d="M14 15.5a4.5 4.5 0 1 1-9 0" />
                        </svg>
                    </span>
                    <span>
                        <strong>{{ __('Technician') }}</strong>
                        <small>{{ __('Apply to receive service jobs.') }}</small>
                    </span>
                    <span class="ml-auto text-xl text-zinc-400" aria-hidden="true">→</span>
                </button>
            </div>
        </div>

        <form
            x-show="! selectingRole"
            x-cloak
            method="POST"
            action="{{ route('register.store') }}"
            class="auth-form"
            x-on:submit="submitting = true"
        >
            @csrf
            <input type="hidden" name="role" x-model="role" required>

            <div class="auth-selected-role">
                <div>
                    <strong x-text="role === 'technician' ? 'Technician account' : 'Customer account'"></strong>
                    <span x-text="role === 'technician' ? 'Verification is required before dispatch.' : 'You can book and manage your services.'"></span>
                </div>
                <button type="button" class="auth-change-role" x-on:click="changeRole()">{{ __('Change') }}</button>
            </div>

            <div class="auth-field @error('name') auth-field-error @enderror">
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    class="auth-input"
                    placeholder=" "
                    required
                    autocomplete="name"
                    x-ref="name"
                >
                <label for="name" class="auth-label">{{ __('Full name') }}</label>
            </div>

            <div class="auth-field @error('email') auth-field-error @enderror">
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    class="auth-input"
                    placeholder=" "
                    required
                    autocomplete="email"
                >
                <label for="email" class="auth-label">{{ __('Email address') }}</label>
            </div>

            <template x-if="role === 'technician'">
                <div class="auth-tech-fields">
                    <div class="auth-field @error('phone') auth-field-error @enderror">
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="auth-input" placeholder=" " x-bind:required="role === 'technician'" autocomplete="tel">
                        <label for="phone" class="auth-label">{{ __('Phone number') }}</label>
                    </div>

                    <div class="auth-field @error('service_categories') auth-field-error @enderror">
                        <div class="text-sm font-medium text-zinc-800">{{ __('What work can you personally handle?') }}</div>
                        <p class="auth-helper">{{ __('Choose one or more exact services. You will only receive matching requests.') }}</p>
                        <div class="grid gap-3">
                            @foreach ($serviceCatalog->groupBy('category') as $category => $services)
                                <fieldset class="rounded-lg border border-zinc-200 p-3">
                                    <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $category }}</legend>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @foreach ($services as $service)
                                            <label for="service-category-{{ $service->code }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 transition hover:border-blue-400 hover:bg-blue-50/40">
                                                <input id="service-category-{{ $service->code }}" name="service_categories[]" type="checkbox" value="{{ $service->code }}" class="mt-0.5 size-4 accent-blue-600" @checked(in_array($service->code, $selectedServiceCategories, true))>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-medium text-zinc-800">{{ $service->name }}</span>
                                                    <span class="mt-0.5 block text-xs text-zinc-500">{{ $service->description }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                        @error('service_categories')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="auth-field @error('service_area') auth-field-error @enderror">
                            <input id="service_area" name="service_area" type="text" value="{{ old('service_area') }}" class="auth-input" placeholder=" " x-bind:required="role === 'technician'">
                            <label for="service_area" class="auth-label">{{ __('Service area') }}</label>
                        </div>
                        <div class="auth-field @error('years_experience') auth-field-error @enderror">
                            <input id="years_experience" name="years_experience" type="number" min="0" max="60" value="{{ old('years_experience') }}" class="auth-input" placeholder=" " x-bind:required="role === 'technician'">
                            <label for="years_experience" class="auth-label">{{ __('Years of experience') }}</label>
                        </div>
                    </div>
                </div>
            </template>

            <div class="auth-field auth-field-action @error('password') auth-field-error @enderror">
                <input
                    id="password"
                    name="password"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    class="auth-input"
                    placeholder=" "
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                >
                <label for="password" class="auth-label">{{ __('Password') }}</label>
                <button type="button" class="auth-icon-button" x-on:click="showPassword = ! showPassword" aria-label="{{ __('Toggle password visibility') }}">
                    <svg x-show="! showPassword" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" /></svg>
                    <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3 3.6M6.2 6.7C3.7 8.1 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
                </button>
            </div>
            <p class="auth-helper">{{ __('Use a strong password with letters, numbers, and symbols.') }}</p>

            <div class="auth-field auth-field-action @error('password_confirmation') auth-field-error @enderror">
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    x-bind:type="showConfirmation ? 'text' : 'password'"
                    class="auth-input"
                    placeholder=" "
                    required
                    autocomplete="new-password"
                >
                <label for="password_confirmation" class="auth-label">{{ __('Confirm password') }}</label>
                <button type="button" class="auth-icon-button" x-on:click="showConfirmation = ! showConfirmation" aria-label="{{ __('Toggle password visibility') }}">
                    <svg x-show="! showConfirmation" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" /></svg>
                    <svg x-show="showConfirmation" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3 3.6M6.2 6.7C3.7 8.1 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
                </button>
            </div>

            <button type="submit" class="auth-primary-button" x-bind:disabled="submitting" data-test="register-user-button">
                <span x-show="! submitting" class="auth-button-label">
                    <span x-show="role !== 'technician'">{{ __('Create account') }}</span>
                    <span x-show="role === 'technician'" x-cloak>{{ __('Submit application') }}</span>
                    <span class="auth-button-arrow" aria-hidden="true">→</span>
                </span>
                <span x-show="submitting" x-cloak class="auth-button-loading">
                    <span class="auth-spinner" aria-hidden="true"></span>
                    {{ __('Creating account…') }}
                </span>
            </button>
        </form>

        <p class="auth-switch">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" wire:navigate>{{ __('Sign in') }}</a>
        </p>
    </div>
</x-layouts::auth>
