<span class="status-pill {{ $record->approval_status === 'approved' ? 'done' : ($record->approval_status === 'rejected' ? 'missing' : 'pending') }}">{{ $record->approvalLabel() }}</span>
