@component('mail::message')
# Keputusan Pengajuan Izin

Halo {{ $leaveRequest->user->name }},

Pengajuan izin Anda dengan nomor **{{ $leaveRequest->request_number }}** telah diproses.

@if ($leaveRequest->status === 'approved')
**Status: Disetujui**
@else
**Status: Ditolak**

Alasan: {{ $leaveRequest->rejection_reason }}
@endif

Jenis Izin: {{ $leaveRequest->typeLabel() }}
Tanggal: {{ $leaveRequest->leave_date->format('d/m/Y') }}

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
