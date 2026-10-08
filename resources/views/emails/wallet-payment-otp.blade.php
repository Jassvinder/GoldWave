<x-mail::message>
# Your GoldWave wallet payment code

A payment from your GoldWave wallet for a new member's registration@if ($newMemberName || $planName) ({{ collect([$newMemberName, $planName])->filter()->implode(', ') }})@endif is waiting for your approval.

Enter this code to approve the payment:

<x-mail::panel>
{{ $code }}
</x-mail::panel>

This code expires in 10 minutes and works only once.

**If you did not start this payment, do not share this code with anyone and change your password.**

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
