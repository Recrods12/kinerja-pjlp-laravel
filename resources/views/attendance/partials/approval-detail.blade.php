<div class="muted">
  @if ($record->reviewed_at)
    <p>Diproses oleh {{ $record->reviewer?->name ?? 'Admin' }} pada {{ $record->reviewed_at->format('d/m/Y H:i') }} WIB.</p>
  @endif
  @if ($record->rejection_reason)<p>Alasan penolakan: {{ $record->rejection_reason }}</p>@endif
</div>
