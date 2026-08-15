@props([
    'value' => '—',
    'type' => 'text',
])

@php
    $displayValue = trim((string) ($value ?? '—')) ?: '—';
    $normalizedValue = str($displayValue)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    $badgeColor = match ($type) {
        'status' => match ($normalizedValue) {
            'online', 'ready', 'healthy', 'active', 'approved', 'completed', 'paid', 'published', 'resolved', 'available' => 'emerald',
            'pending', 'attention', 'information_requested', 'waiting', 'called', 'busy', 'open', 'on_hold' => 'amber',
            'matching', 'under_review', 'serving', 'refunded' => 'violet',
            'assigned', 'in_progress' => 'sky',
            'failed', 'cancelled', 'rejected', 'suspended', 'no_show', 'flagged' => 'rose',
            'inactive', 'hidden', 'offline', 'closed', 'disabled' => 'zinc',
            default => 'zinc',
        },
        'role' => match ($normalizedValue) {
            'superadmin' => 'violet',
            'admin' => 'sky',
            'dispatcher' => 'cyan',
            'support' => 'teal',
            'finance' => 'emerald',
            'technician' => 'amber',
            default => 'zinc',
        },
        'verification' => $normalizedValue === 'verified' ? 'emerald' : 'amber',
        'risk' => match ($normalizedValue) {
            'high' => 'rose',
            'medium' => 'amber',
            'low' => 'emerald',
            default => 'zinc',
        },
        'priority' => match ($normalizedValue) {
            'urgent', 'priority' => 'rose',
            'high' => 'amber',
            default => 'zinc',
        },
        'availability' => match ($normalizedValue) {
            'available' => 'emerald',
            'busy' => 'amber',
            'offline' => 'zinc',
            default => 'zinc',
        },
        'category' => match ($normalizedValue) {
            'billing', 'payment' => 'violet',
            'technical', 'service' => 'sky',
            'complaint', 'dispute' => 'rose',
            default => 'zinc',
        },
        'action' => match (true) {
            str_contains($normalizedValue, 'failed'), str_contains($normalizedValue, 'cancelled'), str_contains($normalizedValue, 'suspended'), str_contains($normalizedValue, 'revoked') => 'rose',
            str_contains($normalizedValue, 'created'), str_contains($normalizedValue, 'approved'), str_contains($normalizedValue, 'completed'), str_contains($normalizedValue, 'activated') => 'emerald',
            str_contains($normalizedValue, 'updated'), str_contains($normalizedValue, 'assigned') => 'sky',
            default => 'zinc',
        },
        default => null,
    };
@endphp

@if ($type === 'chips')
    <div class="flex flex-wrap gap-1.5" data-super-admin-badge-type="chips">
        @foreach (explode(',', $displayValue) as $chip)
            <flux:badge color="zinc" size="sm">{{ trim($chip) }}</flux:badge>
        @endforeach
    </div>
@elseif ($type === 'booking')
    @php($bookingReference = str($displayValue)->before(' · Priority')->toString())
    <div class="flex flex-wrap items-center gap-1.5">
        <span>{{ $bookingReference }}</span>
        @if (str_contains($displayValue, ' · Priority'))
            <span data-super-admin-badge-type="priority" data-super-admin-badge-color="amber">
                <flux:badge color="amber" size="sm">Priority</flux:badge>
            </span>
        @endif
    </div>
@elseif ($type === 'rating')
    @php($rating = (float) str($displayValue)->before('/')->trim()->toString())
    @php($ratingColor = $rating >= 4.5 ? 'emerald' : ($rating >= 3 ? 'amber' : 'rose'))
    <span data-super-admin-badge-type="rating" data-super-admin-badge-color="{{ $ratingColor }}">
        <flux:badge :color="$ratingColor" size="sm">★ {{ $displayValue }}</flux:badge>
    </span>
@elseif ($type === 'wait')
    @php($waitMinutes = (int) preg_replace('/[^0-9]/', '', $displayValue))
    @php($waitColor = $waitMinutes >= 30 ? 'rose' : ($waitMinutes >= 15 ? 'amber' : 'emerald'))
    <span data-super-admin-badge-type="wait" data-super-admin-badge-color="{{ $waitColor }}">
        <flux:badge :color="$waitColor" size="sm">{{ $displayValue }}</flux:badge>
    </span>
@elseif ($type === 'assignment' && $normalizedValue === 'unassigned')
    <span data-super-admin-badge-type="assignment" data-super-admin-badge-color="amber">
        <flux:badge color="amber" size="sm">{{ $displayValue }}</flux:badge>
    </span>
@elseif ($type === 'price' && $normalizedValue === 'quotation')
    <span data-super-admin-badge-type="price" data-super-admin-badge-color="amber">
        <flux:badge color="amber" size="sm">{{ $displayValue }}</flux:badge>
    </span>
@elseif ($badgeColor !== null)
    <span data-super-admin-badge-type="{{ $type }}" data-super-admin-badge-color="{{ $badgeColor }}">
        <flux:badge :color="$badgeColor" size="sm">{{ $displayValue }}</flux:badge>
    </span>
@else
    <span @class([
        'font-mono' => $type === 'mono',
        'tabular-nums' => in_array($type, ['metric', 'money', 'price'], true),
    ])>{{ $displayValue }}</span>
@endif
