<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Dealership\OrderApproval;
use App\Models\Dealership\SrCollection;
use App\Models\Dealership\SrOrder;
use App\Models\InstituteUser;
use App\Models\MobileSyncIdempotency;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mobile v1 offline sync (extended: dealership masters + transactions).
 *
 * delta: read-only changed-record feed for an explicit entity allowlist
 * (tenant-scoped via TenantContext, cursor = updated/created timestamp).
 * push: idempotent inserts keyed by device UUID client_id, mapped via
 * mobile_sync_idempotency (duplicate → same server_id, no second insert).
 */
class SyncController extends Controller
{
    private const DELTA_ENTITIES = [
        'branches',
        'notifications',
        'dealership_brands',
        'dealership_beats',
        'dealership_products',
        'dealership_customers',
        'dealership_price_lists',
        'dealership_sales_force',
        'module_registry',
        'subscription_packages',
    ];

    private const PUSH_ENTITIES = [
        'branches',
        'dealership_sr_orders',
        'dealership_sr_collections',
    ];

    public function delta(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser && ! $user instanceof User) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'since' => 'sometimes|date',
            'entities' => 'sometimes|string',
        ]);

        $since = isset($validated['since'])
            ? \Carbon\Carbon::parse($validated['since'])
                // Storage holds app-timezone-naive datetimes; align the
                // cursor to the same convention so lexical comparison is exact.
                ->setTimezone(date_default_timezone_get())
            : now()->subDays(30);

        if ($since->lt(now()->subDays(90))) {
            return ApiResponse::error(
                'VALIDATION_FAILED',
                'Sync window too large (max 90 days).',
                422,
                ['since' => ['The since cursor must be within the last 90 days.']]
            );
        }

        $requested = isset($validated['entities'])
            ? array_values(array_filter(array_map('trim', explode(',', $validated['entities']))))
            : self::DELTA_ENTITIES;

        if ($requested === []) {
            $requested = self::DELTA_ENTITIES;
        }

        $unknown = array_diff($requested, self::DELTA_ENTITIES);
        if ($unknown !== []) {
            return ApiResponse::validation(['entities' => ['Unknown entity: '.implode(', ', $unknown)]]);
        }

        $instituteId = TenantContext::id() ?? $user->institute_id;
        $entities = [];

        foreach ($requested as $entity) {
            $entities[$entity] = $this->deltaFor($entity, $instituteId, $user, $since);
        }

        $now = now()->utc();

        return ApiResponse::success(
            ['entities' => $entities],
            ['next_since' => $now->toISOString(), 'server_time' => $now->toISOString()]
        );
    }

    private function deltaFor(string $entity, int $instituteId, InstituteUser|User $user, \Carbon\Carbon $since): mixed
    {
        return match ($entity) {
            'branches' => Branch::where('institute_id', $instituteId)
                ->where('updated_at', '>', $since)
                ->orderBy('updated_at')
                ->get(['id', 'code', 'name', 'phone', 'email', 'address', 'status', 'updated_at']),
            'notifications' => DB::table('notifications')
                ->where(function ($q) use ($instituteId, $user) {
                    $q->where(function ($q) use ($instituteId) {
                        $q->where('scope', 'institute')
                            ->where('institute_id', $instituteId);
                    })->orWhere(function ($q) use ($user) {
                        $q->where('scope', 'user')
                            ->where('target_user_type', 'institute_user')
                            ->where('target_user_id', $user->id);
                    });
                })
                ->where('created_at', '>', $since)
                ->orderBy('created_at')
                ->get(['id', 'category', 'title', 'message', 'link_url', 'created_at']),
            'dealership_brands' => $this->tenantDelta('dealership_brands', $instituteId, $since),
            'dealership_beats' => $this->tenantDelta('dealership_beats', $instituteId, $since),
            'dealership_products' => $this->tenantDelta('dealership_products', $instituteId, $since),
            'dealership_customers' => $this->tenantDelta('dealership_customers', $instituteId, $since),
            'dealership_price_lists' => $this->tenantDelta('dealership_price_lists', $instituteId, $since),
            'dealership_sales_force' => $this->tenantDelta('dealership_sales_force', $instituteId, $since),
            'module_registry' => DB::table('module_registry')
                ->where('status', 'active')
                ->where('updated_at', '>', $since)
                ->orderBy('sort_order')
                ->get(['key', 'name', 'icon', 'parent_key', 'type', 'is_core', 'sort_order', 'updated_at']),
            'subscription_packages' => DB::table('subscription_packages')
                ->where('status', 'active')
                ->where('updated_at', '>', $since)
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'updated_at']),
        };
    }

    private function tenantDelta(string $table, int $instituteId, \Carbon\Carbon $since): mixed
    {
        return DB::table($table)
            ->where('institute_id', $instituteId)
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->get();
    }

    public function push(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser && ! $user instanceof User) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'operations' => 'required|array|max:100',
            'operations.*.entity' => 'required|string',
            'operations.*.action' => 'required|in:create,update,upsert',
            'operations.*.client_id' => 'sometimes|nullable|string|max:80',
            'operations.*.payload' => 'sometimes|array',
        ]);

        $instituteId = TenantContext::id() ?? $user->institute_id;
        $results = [];

        foreach ($validated['operations'] as $op) {
            $results[] = match ($op['entity']) {
                'branches' => $this->pushBranch($instituteId, $op),
                'dealership_sr_orders' => $this->pushSrOrder($instituteId, $user, $op),
                'dealership_sr_collections' => $this->pushSrCollection($instituteId, $user, $op),
                default => $this->failed($op['client_id'] ?? null, 'UNSUPPORTED_ENTITY', 'Unknown entity: '.$op['entity']),
            };
        }

        return ApiResponse::success(['results' => $results]);
    }

    /**
     * @param array{action: string, client_id?: ?string, payload: array} $op
     */
    private function pushBranch(int $instituteId, array $op): array
    {
        $payload = $op['payload'] ?? [];

        if (empty($payload['code']) || empty($payload['name'])) {
            return $this->failed($op['client_id'] ?? null, 'VALIDATION_FAILED', 'Branch payload requires code and name.');
        }

        $branch = Branch::updateOrCreate(
            ['institute_id' => $instituteId, 'code' => $payload['code']],
            array_intersect_key($payload, array_flip([
                'name', 'phone', 'email', 'address', 'status',
            ]))
        );

        return [
            'client_id' => $op['client_id'] ?? null,
            'status' => 'created',
            'server_id' => $branch->id,
            'error' => null,
        ];
    }

    /**
     * @param array{action: string, client_id?: ?string, payload: array} $op
     */
    private function pushSrOrder(int $instituteId, InstituteUser|User $user, array $op): array
    {
        if ($op['action'] !== 'create') {
            return $this->failed($op['client_id'] ?? null, 'UNSUPPORTED_ENTITY', 'Only create is supported for dealership_sr_orders.');
        }

        $check = $this->checkClientId($instituteId, 'dealership_sr_orders', $op['client_id'] ?? null);
        if ($check !== null) {
            return $check;
        }
        $clientId = $op['client_id'];

        $payload = $op['payload'] ?? [];
        $errors = [];

        foreach (['customer_id', 'sales_force_id', 'items'] as $field) {
            if (! array_key_exists($field, $payload)) {
                $errors[$field][] = "The $field field is required.";
            }
        }

        if (isset($payload['customer_id']) && ! $this->belongsToInstitute('dealership_customers', (int) $payload['customer_id'], $instituteId)) {
            $errors['customer_id'][] = 'Customer not found in this institute.';
        }

        if (isset($payload['sales_force_id']) && ! $this->belongsToInstitute('dealership_sales_force', (int) $payload['sales_force_id'], $instituteId)) {
            $errors['sales_force_id'][] = 'Sales force member not found in this institute.';
        }

        $items = $payload['items'] ?? null;
        if (is_array($items)) {
            if ($items === []) {
                $errors['items'][] = 'At least one item is required.';
            }
            foreach ($items as $i => $item) {
                foreach (['product_id', 'qty', 'unit_price'] as $f) {
                    if (! array_key_exists($f, $item)) {
                        $errors["items.$i.$f"][] = "The $f field is required.";
                    }
                }
                if (isset($item['product_id']) && ! $this->belongsToInstitute('dealership_products', (int) $item['product_id'], $instituteId)) {
                    $errors["items.$i.product_id"][] = 'Product not found in this institute.';
                }
                if (isset($item['qty']) && (float) $item['qty'] <= 0) {
                    $errors["items.$i.qty"][] = 'Quantity must be greater than 0.';
                }
            }
        }

        if (isset($payload['channel']) && ! in_array($payload['channel'], ['general', 'retail', 'wholesale', 'sub_dealer'], true)) {
            $errors['channel'][] = 'Invalid channel.';
        }

        if ($errors !== []) {
            return $this->failed($clientId, 'VALIDATION_FAILED', 'Order payload invalid.', $errors);
        }

        $subtotal = collect($items)->sum(fn ($i) => (float) $i['qty'] * (float) $i['unit_price']);
        $discount = (float) ($payload['discount'] ?? 0);
        $total = max(0, $subtotal - $discount);

        try {
            $order = DB::transaction(function () use ($instituteId, $user, $payload, $items, $subtotal, $discount, $total, $clientId) {
                $orderNo = $this->nextNumber('dealership_sr_orders', 'order_no', 'SR', $instituteId);

                $order = SrOrder::create([
                    'institute_id' => $instituteId,
                    'order_no' => $orderNo,
                    'customer_id' => $payload['customer_id'],
                    'sales_force_id' => $payload['sales_force_id'],
                    'channel' => $payload['channel'] ?? 'general',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'status' => 'submitted',
                    'remarks' => $payload['remarks'] ?? null,
                    'submitted_at' => now(),
                ]);

                foreach ($items as $item) {
                    $order->items()->create([
                        'institute_id' => $instituteId,
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => (float) $item['qty'] * (float) $item['unit_price'],
                    ]);
                }

                OrderApproval::create([
                    'institute_id' => $instituteId,
                    'sr_order_id' => $order->id,
                    'action' => 'submitted',
                    'actor_id' => $user->id,
                ]);

                $this->recordMapping($instituteId, $clientId, 'dealership_sr_orders', $order->id);

                return $order;
            });
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($clientId, 'VALIDATION_FAILED', 'Order could not be created.');
        }

        return [
            'client_id' => $clientId,
            'status' => 'created',
            'server_id' => $order->id,
            'error' => null,
        ];
    }

    /**
     * @param array{action: string, client_id?: ?string, payload: array} $op
     */
    private function pushSrCollection(int $instituteId, InstituteUser|User $user, array $op): array
    {
        if ($op['action'] !== 'create') {
            return $this->failed($op['client_id'] ?? null, 'UNSUPPORTED_ENTITY', 'Only create is supported for dealership_sr_collections.');
        }

        $check = $this->checkClientId($instituteId, 'dealership_sr_collections', $op['client_id'] ?? null);
        if ($check !== null) {
            return $check;
        }
        $clientId = $op['client_id'];

        $payload = $op['payload'] ?? [];
        $errors = [];

        foreach (['customer_id', 'sales_force_id', 'method', 'amount', 'collected_on'] as $field) {
            if (! array_key_exists($field, $payload)) {
                $errors[$field][] = "The $field field is required.";
            }
        }

        if (isset($payload['customer_id']) && ! $this->belongsToInstitute('dealership_customers', (int) $payload['customer_id'], $instituteId)) {
            $errors['customer_id'][] = 'Customer not found in this institute.';
        }

        if (isset($payload['sales_force_id']) && ! $this->belongsToInstitute('dealership_sales_force', (int) $payload['sales_force_id'], $instituteId)) {
            $errors['sales_force_id'][] = 'Sales force member not found in this institute.';
        }

        if (isset($payload['method']) && ! in_array($payload['method'], ['cash', 'cheque', 'bank_transfer', 'mobile_banking'], true)) {
            $errors['method'][] = 'Invalid method.';
        }

        if (isset($payload['amount']) && (float) $payload['amount'] <= 0) {
            $errors['amount'][] = 'Amount must be greater than 0.';
        }

        $srOrderId = $payload['sr_order_id'] ?? null;
        if (isset($payload['sr_order_client_id'])) {
            $mapped = MobileSyncIdempotency::where('institute_id', $instituteId)
                ->where('client_id', $payload['sr_order_client_id'])
                ->where('entity', 'dealership_sr_orders')
                ->value('server_id');
            if ($mapped === null) {
                $errors['sr_order_client_id'][] = 'Linked order not found on server.';
            } else {
                $srOrderId = $mapped;
            }
        }

        if ($srOrderId !== null && ! $this->belongsToInstitute('dealership_sr_orders', (int) $srOrderId, $instituteId)) {
            $errors['sr_order_id'][] = 'Order not found in this institute.';
        }

        if ($errors !== []) {
            return $this->failed($clientId, 'VALIDATION_FAILED', 'Collection payload invalid.', $errors);
        }

        try {
            $collection = DB::transaction(function () use ($instituteId, $payload, $srOrderId, $clientId) {
                $receiptNo = $this->nextNumber('dealership_sr_collections', 'receipt_no', 'RC', $instituteId);

                $collection = SrCollection::create([
                    'institute_id' => $instituteId,
                    'receipt_no' => $receiptNo,
                    'customer_id' => $payload['customer_id'],
                    'sales_force_id' => $payload['sales_force_id'],
                    'sr_order_id' => $srOrderId,
                    'method' => $payload['method'],
                    'amount' => $payload['amount'],
                    'reference' => $payload['reference'] ?? null,
                    'collected_on' => $payload['collected_on'],
                    'status' => 'pending',
                ]);

                $this->recordMapping($instituteId, $clientId, 'dealership_sr_collections', $collection->id);

                return $collection;
            });
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($clientId, 'VALIDATION_FAILED', 'Collection could not be created.');
        }

        return [
            'client_id' => $clientId,
            'status' => 'created',
            'server_id' => $collection->id,
            'error' => null,
        ];
    }

    /**
     * Validate client_id and replay duplicates.
     * Returns null when the op may proceed, else the final result array.
     */
    private function checkClientId(int $instituteId, string $entity, mixed $clientId): ?array
    {
        if (! is_string($clientId) || $clientId === '') {
            return $this->failed(null, 'CLIENT_ID_REQUIRED', 'Each operation requires a client_id.');
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $clientId)) {
            return $this->failed($clientId, 'INVALID_CLIENT_ID', 'client_id must be a UUID v4.');
        }

        $existing = MobileSyncIdempotency::where('institute_id', $instituteId)
            ->where('client_id', $clientId)
            ->first();

        if ($existing) {
            return [
                'client_id' => $clientId,
                'status' => 'duplicate',
                'server_id' => $existing->server_id,
                'error' => null,
            ];
        }

        return null;
    }

    private function recordMapping(int $instituteId, string $clientId, string $entity, int $serverId): void
    {
        try {
            MobileSyncIdempotency::create([
                'institute_id' => $instituteId,
                'client_id' => $clientId,
                'entity' => $entity,
                'server_id' => $serverId,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Lost a race with a concurrent retry — mapping already exists.
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
        }
    }

    private function belongsToInstitute(string $table, int $id, int $instituteId): bool
    {
        return DB::table($table)
            ->where('id', $id)
            ->where('institute_id', $instituteId)
            ->exists();
    }

    /**
     * Server-computed sequential number: {PREFIX}-{institute}-{YYYYMMDD}-{seq}.
     */
    private function nextNumber(string $table, string $column, string $prefix, int $instituteId): string
    {
        $date = now()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $count = DB::table($table)
                ->where('institute_id', $instituteId)
                ->whereDate('created_at', now()->toDateString())
                ->count();

            $number = sprintf('%s-%d-%s-%04d', $prefix, $instituteId, $date, $count + 1 + $attempt);

            if (! DB::table($table)->where($column, $number)->exists()) {
                return $number;
            }
        }

        return sprintf('%s-%d-%s-%04d-%s', $prefix, $instituteId, $date, $count + 1, strtoupper(Str::random(4)));
    }

    private function failed(?string $clientId, string $code, string $message, array $details = []): array
    {
        return [
            'client_id' => $clientId,
            'status' => 'failed',
            'server_id' => null,
            'error' => ['code' => $code, 'message' => $message] + ($details === [] ? [] : ['details' => $details]),
        ];
    }
}
