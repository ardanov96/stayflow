@component('mail::message')
{{ __('email.greeting', ['name' => $userName]) }}

{{ __('email.recovery_code_used_body', ['count' => $remainingCount]) }}

{{ __('email.recovery_code_used_action') }}

{{ __('email.signature') }}
@endcomponent