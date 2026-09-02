<?php

namespace App\Services\Sales;

/**
 * Reusable lifecycle statuses for Sales documents — industry-neutral.
 * No DB table required; kept as constants for services/controllers to reuse.
 */
final class SalesDocumentStatus
{
    // Quotation: draft → sent → accepted / rejected / expired → cancelled
    public const QUOTATION_DRAFT = 'draft';

    public const QUOTATION_SENT = 'sent';

    public const QUOTATION_ACCEPTED = 'accepted';

    public const QUOTATION_REJECTED = 'rejected';

    public const QUOTATION_EXPIRED = 'expired';

    public const QUOTATION_CANCELLED = 'cancelled';

    public const QUOTATION_STATUSES = [
        self::QUOTATION_DRAFT,
        self::QUOTATION_SENT,
        self::QUOTATION_ACCEPTED,
        self::QUOTATION_REJECTED,
        self::QUOTATION_EXPIRED,
        self::QUOTATION_CANCELLED,
    ];

    // Sales Order: draft → pending → approved → processing → completed → cancelled
    public const ORDER_DRAFT = 'draft';

    public const ORDER_PENDING = 'pending';

    public const ORDER_APPROVED = 'approved';

    public const ORDER_PROCESSING = 'processing';

    public const ORDER_COMPLETED = 'completed';

    public const ORDER_CANCELLED = 'cancelled';

    public const ORDER_STATUSES = [
        self::ORDER_DRAFT,
        self::ORDER_PENDING,
        self::ORDER_APPROVED,
        self::ORDER_PROCESSING,
        self::ORDER_COMPLETED,
        self::ORDER_CANCELLED,
    ];

    // Delivery: draft → pending → delivered → cancelled
    public const DELIVERY_DRAFT = 'draft';

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_CANCELLED = 'cancelled';

    public const DELIVERY_STATUSES = [
        self::DELIVERY_DRAFT,
        self::DELIVERY_PENDING,
        self::DELIVERY_DELIVERED,
        self::DELIVERY_CANCELLED,
    ];
}
