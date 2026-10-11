<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $definition->name }}</title>
    @include('partials.pdf-style')
</head>
<body>
    <table class="head">
        <tr>
            <td><div class="brand">{{ config('payroll.company_name') }}</div><div class="muted">{{ $definition->category }}</div></td>
            <td class="right"><div class="title">{{ $definition->name }}</div><div class="muted">{{ $data['subtitle'] }} · Dicetak {{ now()->translatedFormat('d M Y H:i') }} oleh {{ auth()->user()->name }}</div></td>
        </tr>
    </table>
    <table class="grid">
        <thead><tr><th>No</th>@foreach ($data['columns'] as $i => $c)<th class="{{ in_array($i, $data['money']) ? 'right' : '' }}">{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($data['rows'] as $n => $row)
            <tr><td>{{ $n + 1 }}</td>@foreach ($row as $i => $v)<td class="{{ in_array($i, $data['money']) ? 'right' : '' }}">{{ in_array($i, $data['money']) ? number_format($v, 0, ',', '.') : $v }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($data['columns']) + 1 }}" class="center muted">Tidak ada data.</td></tr>
        @endforelse
        </tbody>
        @if ($data['money'] && $data['rows'])
            <tfoot><tr><th>Total</th>@foreach ($data['columns'] as $i => $c)<th class="right">{{ in_array($i, $data['money']) ? number_format(collect($data['rows'])->sum($i), 0, ',', '.') : '' }}</th>@endforeach</tr></tfoot>
        @endif
    </table>
</body>
</html>
