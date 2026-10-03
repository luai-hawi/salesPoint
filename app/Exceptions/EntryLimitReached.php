<?php

namespace App\Exceptions;

use RuntimeException;

class EntryLimitReached extends RuntimeException
{
    public function __construct(
        public readonly string $resourceKey,
        public readonly int $ownerId,
        public readonly int $limit,
        public readonly int $used,
    ) {
        parent::__construct((string) __('admin.entry_limit.errors.limit_reached', [
            'resource' => __('admin.entry_limit.resources.' . $resourceKey),
            'limit' => number_format($limit),
        ]));
    }
}
