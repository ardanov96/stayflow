@component('mail::message')
{{ __('email.greeting', ['name' => $userName]) }}

{{ __('email.verify_body') }}

@component('mail::button', ['url' => $verifyUrl])
{{ __('email.verify_cta') }}
@endcomponent

{{ __('email.verify_expiry') }}

{{ __('email.signature') }}
@endcomponent