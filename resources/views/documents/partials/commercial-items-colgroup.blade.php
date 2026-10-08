<colgroup>
    @foreach($itemColumns as $column)
        <col class="{{ $column['class'] }}" style="width: {{ $column['width'] }}">
    @endforeach
</colgroup>
