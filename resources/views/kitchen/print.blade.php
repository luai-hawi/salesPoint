<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('restaurant.titles.print_ticket') }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 12px; }
        .ticket { width: 280px; margin: 0 auto; }
        .title { text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 10px; }
        .muted { color: #555; font-size: 12px; }
        .item { border-top: 1px dashed #999; padding: 8px 0; }
    </style>
</head>
<body onload="window.print()">
    <div class="ticket">
        <div class="title">{{ __('restaurant.titles.print_ticket') }} #{{ $ticket->number }}</div>
        <div>{{ __('restaurant.fields.table') }}: {{ $ticket->order?->table?->name ?? __('restaurant.labels.no_table') }}</div>
        <div>{{ __('restaurant.fields.order_type') }}: {{ __('restaurant.order_types.' . ($ticket->order?->order_type ?? 'takeaway')) }}</div>
        <div class="muted">{{ $ticket->sent_at ? \App\Support\ShopTime::local($ticket->sent_at, $ticket->user_id)->format('Y-m-d H:i') : '' }}</div>

        @foreach ($ticket->items ?? [] as $item)
            <div class="item">
                <div><strong>{{ $item['quantity'] }}</strong> × {{ $item['name'] }}</div>
                @if (! empty($item['note']))
                    <div class="muted">{{ $item['note'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
