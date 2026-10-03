<?php

namespace App\Services\Restaurant;

use App\Models\KitchenTicket;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenDisplayService
{
    public function feed(int $ownerId, ?string $since = null): array
    {
        $today = ShopTime::today($ownerId);
        $sinceState = $this->parseCursor($since);
        $tickets = $this->visibleTickets($ownerId, $today);
        $fingerprint = $tickets->mapWithKeys(fn (KitchenTicket $ticket) => [
            (string) $ticket->id => $this->ticketFingerprint($ticket),
        ])->all();

        $cursor = $this->encodeCursor([
            'version' => 1,
            'visible' => $fingerprint,
        ]);

        $etag = sha1(json_encode([
            'owner' => $ownerId,
            'today' => $today,
            'tickets' => $fingerprint,
        ]));

        $removedIds = [];
        $payloadTickets = $tickets;

        if ($sinceState !== null) {
            $previousVisible = $sinceState['visible'] ?? [];
            $payloadTickets = $tickets->filter(function (KitchenTicket $ticket) use ($fingerprint, $previousVisible) {
                $id = (string) $ticket->id;

                return ($previousVisible[$id] ?? null) !== ($fingerprint[$id] ?? null);
            })->values();
            $removedIds = collect(array_keys($previousVisible))
                ->filter(fn (string $id) => ! array_key_exists($id, $fingerprint))
                ->map(fn (string $id) => (int) $id)
                ->values()
                ->all();
        }

        return [
            'cursor' => $cursor,
            'etag' => $etag,
            'delta' => $sinceState !== null,
            'removed_ids' => $removedIds,
            'tickets' => $payloadTickets->map(fn (KitchenTicket $ticket) => $this->serializeTicket($ticket))->values()->all(),
            'columns' => [
                'new' => $tickets->where('status', 'new')->count(),
                'preparing' => $tickets->where('status', 'preparing')->count(),
                'ready' => $tickets->where('status', 'ready')->count(),
                'served' => $tickets->where('status', 'served')->count(),
            ],
        ];
    }

    public function transition(KitchenTicket $ticket, string $action, ?string $cancelReason = null): KitchenTicket
    {
        return DB::transaction(function () use ($ticket, $action, $cancelReason) {
            $locked = KitchenTicket::withoutGlobalScopes()
                ->with(['order.table'])
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = $locked->order;
            if (! $order || $order->status !== 'open') {
                throw ValidationException::withMessages(['action' => __('restaurant.validation.order_closed')]);
            }

            $map = [
                'start' => ['from' => ['new'], 'to' => 'preparing', 'field' => 'started_at'],
                'ready' => ['from' => ['new', 'preparing'], 'to' => 'ready', 'field' => 'ready_at'],
                'served' => ['from' => ['ready'], 'to' => 'served', 'field' => 'served_at'],
                'recall' => ['from' => ['ready', 'served'], 'to' => 'preparing', 'field' => null],
                'cancel' => ['from' => ['new', 'preparing', 'ready'], 'to' => 'cancelled', 'field' => 'cancelled_at'],
            ];

            if ($action === 'rush') {
                $locked->priority = $locked->priority === 'rush' ? 'normal' : 'rush';
                $locked->save();

                return $locked->fresh(['order.table']);
            }

            $rule = $map[$action] ?? null;
            if (! $rule || ! in_array($locked->status, $rule['from'], true)) {
                throw ValidationException::withMessages(['action' => __('restaurant.messages.kds_update_failed')]);
            }

            $locked->status = $rule['to'];
            foreach (['started_at', 'ready_at', 'served_at', 'cancelled_at'] as $field) {
                if ($rule['field'] === $field) {
                    $locked->{$field} = now();
                }
            }

            if ($action === 'recall') {
                $locked->ready_at = null;
                $locked->served_at = null;
            }

            if ($action === 'cancel') {
                $locked->cancel_reason = $cancelReason;
            }

            $locked->save();

            return $locked->fresh(['order.table']);
        });
    }

    private function visibleTickets(int $ownerId, string $today): Collection
    {
        return KitchenTicket::withoutGlobalScopes()
            ->with(['order.table'])
            ->where('user_id', $ownerId)
            ->where(function ($query) use ($today) {
                $query->whereIn('status', ['new', 'preparing', 'ready'])
                    ->orWhere(function ($builder) use ($today) {
                        $builder->where('local_service_date', $today)
                            ->where('status', 'served');
                    });
            })
            ->orderByRaw("CASE priority WHEN 'rush' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'preparing' THEN 1 WHEN 'ready' THEN 2 ELSE 3 END")
            ->orderBy('number')
            ->get()
            ->whenNotEmpty(function (Collection $tickets) {
                $served = $tickets->where('status', 'served')->sortByDesc('served_at')->take(25)->pluck('id')->all();

                return $tickets->filter(function (KitchenTicket $ticket) use ($served) {
                    return $ticket->status !== 'served' || in_array($ticket->id, $served, true);
                })->values();
            });
    }

    private function parseCursor(?string $since): ?array
    {
        $value = trim((string) $since);

        if ($value === '') {
            return null;
        }

        try {
            $normalized = strtr($value, '-_', '+/');
            $padding = strlen($normalized) % 4;
            if ($padding > 0) {
                $normalized .= str_repeat('=', 4 - $padding);
            }

            $decoded = base64_decode($normalized, true);
            if ($decoded === false) {
                return null;
            }

            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['version'] ?? null) !== 1) {
                return null;
            }

            $visible = $data['visible'] ?? null;

            return [
                'visible' => is_array($visible) ? $visible : [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function encodeCursor(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    private function ticketCursorToken(KitchenTicket $ticket): Carbon
    {
        $token = $ticket->updated_at ? $ticket->updated_at->copy() : now();
        foreach ([$ticket->order?->updated_at, $ticket->order?->table?->updated_at] as $candidate) {
            if ($candidate && $candidate->gt($token)) {
                $token = $candidate->copy();
            }
        }

        return $token->utc();
    }

    private function ticketFingerprint(KitchenTicket $ticket): string
    {
        return sha1(json_encode([
            'id' => $ticket->id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'number' => $ticket->number,
            'station' => $ticket->station,
            'token' => $this->ticketCursorToken($ticket)->toISOString(),
            'order_updated_at' => optional($ticket->order?->updated_at)->toISOString(),
            'table_updated_at' => optional($ticket->order?->table?->updated_at)->toISOString(),
            'table' => $ticket->order?->table?->name,
            'label' => $ticket->order?->label,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function serializeTicket(KitchenTicket $ticket): array
    {
        $order = $ticket->order;
        $table = $order?->table;

        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'station' => $ticket->station,
            'sent_at' => optional($ticket->sent_at)->toISOString(),
            'started_at' => optional($ticket->started_at)->toISOString(),
            'ready_at' => optional($ticket->ready_at)->toISOString(),
            'served_at' => optional($ticket->served_at)->toISOString(),
            'updated_at' => optional($ticket->updated_at)->toISOString(),
            'cursor_token' => $this->ticketCursorToken($ticket)->toISOString(),
            'items' => $ticket->items ?? [],
            'order' => [
                'id' => $order?->id,
                'label' => $order?->label,
                'type' => $order?->order_type,
                'guests' => $order?->guests,
                'table' => $table?->name,
                'delivery' => [
                    'name' => $order?->customer_name,
                    'phone' => $order?->customer_phone,
                    'address' => $order?->customer_address,
                ],
            ],
        ];
    }
}
