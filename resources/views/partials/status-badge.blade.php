@php
    $map = [
        'pending' => ['warning', 'Menunggu Approval'], 'approved_manager' => ['info', 'Disetujui Atasan'],
        'approved' => ['success', 'Disetujui'], 'rejected' => ['danger', 'Ditolak'],
        'draft' => ['slate', 'Draft'], 'processed' => ['info', 'Diproses'], 'final' => ['dark', 'Final & Locked'],
        'verified' => ['success', 'Verified'], 'review' => ['warning', 'Perlu Review'],
    ];
    [$cls, $text] = $map[$status] ?? ['slate', ucfirst($status)];
@endphp
<span class="badge-soft dot {{ $cls }}">{{ $label ?? $text }}</span>
