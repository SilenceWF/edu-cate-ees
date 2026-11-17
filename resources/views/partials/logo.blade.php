<span class="flex items-center gap-3">
    @if (setting('branding.logo'))
        <img src="{{ route('brand.logo') }}" alt="" class="{{ $size ?? 'size-10' }} rounded-xl object-contain">
    @endif
    <span class="text-xl font-extrabold tracking-tight">{{ setting('company.name', config('app.name')) }}</span>
</span>
