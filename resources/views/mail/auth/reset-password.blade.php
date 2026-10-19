@component('mail::message')
{{ __('email.greeting', ['name' => $userEmail]) }}

{{ __('email.reset_body') }}

@component('mail::button', ['url' => $resetUrl])
{{ __('email.reset_cta') }}
@endcomponent

{{ __('email.reset_expiry') }}

{{ __('email.signature') }}
@endcomponent