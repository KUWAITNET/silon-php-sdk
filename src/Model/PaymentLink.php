<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * A request for money, addressed to one customer.
 *
 * Money fields are fixed-precision decimal **strings** at the currency's own
 * precision (3 places for KWD and its Gulf siblings, 2 elsewhere) — never
 * floats, which would round a merchant's money. Use bcmath on the strings.
 */
final class PaymentLink extends Model
{
    /** Payment link id, `pl_` prefixed. */
    public string $id = '';

    public string $object = 'payment_link';

    /** `pending`, `paid`, `failed`, `expired` or `cancelled`. */
    public string $status = '';

    /** `false` for a link created with an `sk_test_` key. */
    public ?bool $livemode = null;

    /** Net amount, before tax. */
    public ?string $amount = null;

    public ?string $tax_amount = null;

    /** What the customer is charged: `amount` + `tax_amount`. */
    public ?string $total = null;

    public ?string $tax_rate = null;
    public ?string $currency = null;
    public ?string $description = null;

    /** The page to send the customer to, on your own domain. */
    public ?string $url = null;

    public ?string $customer_name = null;
    public ?string $customer_email = null;
    public ?string $customer_phone = null;
    public ?string $conversation_id = null;
    public ?string $client_id = null;
    public ?string $provider = null;

    public ?DateTimeImmutable $expires_at = null;
    public ?DateTimeImmutable $paid_at = null;
    public ?DateTimeImmutable $cancelled_at = null;
    public ?DateTimeImmutable $created = null;
    public ?DateTimeImmutable $updated = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'status' => 'string',
            'livemode' => 'bool',
            'amount' => 'string',
            'tax_amount' => 'string',
            'total' => 'string',
            'tax_rate' => 'string',
            'currency' => 'string',
            'description' => 'string',
            'url' => 'string',
            'customer_name' => 'string',
            'customer_email' => 'string',
            'customer_phone' => 'string',
            'conversation_id' => 'string',
            'client_id' => 'string',
            'provider' => 'string',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created' => 'datetime',
            'updated' => 'datetime',
        ];
    }
}
