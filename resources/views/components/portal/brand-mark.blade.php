@props(['brand', 'size' => 'size-9'])

@if ($brand->logoUrl())
    <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->nama() }}" {{ $attributes->class([$size, 'object-contain']) }} />
@else
    <div {{ $attributes->class([$size, 'flex items-center justify-center rounded-xl bg-accent text-accent-foreground font-bold shadow-md']) }}>
        {{ mb_strtoupper(mb_substr($brand->nama(), 0, 1)) }}
    </div>
@endif
