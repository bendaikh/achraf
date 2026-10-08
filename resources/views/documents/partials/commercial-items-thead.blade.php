<tr>
    @foreach($itemColumns as $column)
        <th class="{{ $column['align'] }} {{ $column['class'] }}" style="width: {{ $column['width'] }}">@if(empty($ghost)){{ $column['label'] }}@endif</th>
    @endforeach
</tr>
