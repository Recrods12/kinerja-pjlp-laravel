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

<table class="pdf-table {{ $side }}">
  <colgroup>
    <col width="7%">
    <col width="14%">
    <col width="61%">
    <col width="18%">
  </colgroup>
  <thead>
    <tr>
      <th class="no-col" width="7%" style="width: 7%;">NO</th>
      <th class="time-col" width="14%" style="width: 14%;">JAM KERJA</th>
      <th class="task-col" width="61%" style="width: 61%; text-align:center;">URAIAN TUGAS</th>
      <th class="note-col" width="18%" style="width: 18%;">KETERANGAN</th>
    </tr>
  </thead>
  <tbody>
    @for ($i = 0; $i < $maxRows; $i++)
      @php $row = $printRows[$i] ?? ['work_time' => '', 'task' => '', 'note' => '']; @endphp
      <tr>
        <td class="no-col" width="7%" style="width: 7%;">{{ $i + 1 }}</td>
        <td class="time-col" width="14%" style="width: 14%;">{{ $row['work_time'] }}</td>
        <td class="task-col" width="61%" style="width: 61%;"><span class="pdf-task-text">{{ $row['task'] }}</span></td>
        <td class="note-col" width="18%" style="width: 18%;">{{ $row['note'] }}</td>
      </tr>
    @endfor
  </tbody>
</table>
