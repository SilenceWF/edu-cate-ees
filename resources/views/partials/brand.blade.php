@php($c = setting('branding.colors', []))
<style>
    :root {
        --brand: {{ $c['primary'] ?? '#2563EB' }};
        --brand-dark: {{ $c['primary_dark'] ?? ($c['primary'] ?? '#1D4ED8') }};
        --brand-header: {{ $c['header'] ?? '#0F172A' }};
        --brand-on: {{ $c['on_primary'] ?? '#FFFFFF' }};
    }
</style>
