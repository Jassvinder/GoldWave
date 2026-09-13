<x-mail::message>
# Your GoldWave login code

Use this code to continue:

<x-mail::panel>
{{ $code }}
</x-mail::panel>

This code expires in 10 minutes. If you did not request this, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
