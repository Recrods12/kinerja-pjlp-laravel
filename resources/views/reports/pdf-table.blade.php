@php
  $printRows = [];
  $maxTaskCharacters = 44;
  $maxLongWordCharacters = 43;
  $maxRows = 15;

  foreach ($items as $entry) {
      $task = trim((string) $entry->task);
      $words = preg_split('/\s+/', $task, -1, PREG_SPLIT_NO_EMPTY);
      $longestWord = $words ? max(array_map('strlen', $words)) : 0;
      $wrapAt = $longestWord > $maxTaskCharacters ? $maxLongWordCharacters : $maxTaskCharacters;
      $wrappedTask = wordwrap($task, $wrapAt, "\n", true);
      $taskLines = $wrappedTask === '' ? [''] : preg_split('/\r\n|\r|\n/', $wrappedTask);

      foreach ($taskLines as $lineIndex => $taskLine) {
          if (count($printRows) >= $maxRows) {
              break 2;
          }

          $printRows[] = [
              'work_time' => $lineIndex === 0 ? $entry->work_time : '',
              'task' => $taskLine,
              'note' => $lineIndex === 0 ? $entry->note : '',
          ];
      }
  }
@endphp

<table class="pdf-table">
  <thead>
    <tr>
      <th class="no-col">NO</th>
      <th class="time-col">JAM KERJA</th>
      <th>URAIAN TUGAS</th>
      <th class="note-col">KETERANGAN</th>
    </tr>
  </thead>
  <tbody>
    @for ($i = 0; $i < $maxRows; $i++)
      @php $row = $printRows[$i] ?? ['work_time' => '', 'task' => '', 'note' => '']; @endphp
      <tr>
        <td class="no-col">{{ $i + 1 }}</td>
        <td>{{ $row['work_time'] }}</td>
        <td><span class="pdf-task-text">{{ $row['task'] }}</span></td>
        <td>{{ $row['note'] }}</td>
      </tr>
    @endfor
  </tbody>
</table>
