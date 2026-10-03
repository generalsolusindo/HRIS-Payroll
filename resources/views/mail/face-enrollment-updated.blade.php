@component('mail::message')
# {{ $reEnrolled ? 'Verifikasi Wajah Diperbarui' : 'Verifikasi Wajah Didaftarkan' }}

Halo {{ $user->name }},

@if ($reEnrolled)
Data verifikasi wajah pada akun Anda baru saja **diperbarui**.
@else
Verifikasi wajah baru saja **didaftarkan** pada akun Anda.
@endif

Jika ini bukan Anda, segera hubungi HRD.

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
