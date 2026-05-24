@props([
    'name',
    'label',
    'options' => [],
    'placeholder' => 'Tous',
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $value => $text)
            <option value="{{ $value }}" {{ (string) request($name) === (string) $value ? 'selected' : '' }}>{{ $text }}</option>
        @endforeach
    </select>
</div>
