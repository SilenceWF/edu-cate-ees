@if ($department)
    <span class="chip !text-xs whitespace-nowrap" style="background: {{ $department->hex_color }}; color: {{ $department->textColor() }}">{{ $department->name }}</span>
@endif
