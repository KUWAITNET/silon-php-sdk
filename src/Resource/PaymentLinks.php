<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\PaymentLink;
use Silon\Util;

/**
 * `$client->paymentLinks` — ask customers for money
 * (`/api/v1/payment_links`).
 *
 * Reading needs the `payments:read` scope; creating or cancelling needs
 * `payments:write`. Fully mode-aware: an `sk_test_` key creates links against
 * the gateway's sandbox and never sees live ones.
 */
final class PaymentLinks extends Resource
{
    private const PATH = '/api/v1/payment_links';

    /**
     * List payment links in this key's mode, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params status, conversation, client_id,
     *                                    created_since, cursor, limit
     * @return CursorPage<PaymentLink>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, PaymentLink::class, $data);
    }

    /**
     * Create a payment link and get back the URL to send the customer.
     *
     * Pass `amount` as a **string** ('10.500'): a float would round the money
     * before it ever reached the API. No gateway call happens here — the
     * checkout session opens when the customer first opens the link.
     *
     * Links expire after 72 hours by default; `expires_in_hours => 0` means
     * never, which is a standing invoice anyone who sees the URL can pay.
     *
     * @param array<string,mixed> $params amount (required), currency, tax_rate,
     *                                    tax_amount, description, customer_name,
     *                                    customer_email, customer_phone, client_id,
     *                                    conversation_id, expires_in_hours,
     *                                    expires_at, account_id
     */
    public function create(array $params): PaymentLink
    {
        return new PaymentLink(
            $this->client->post(self::PATH, ['json' => Util::dropNull($params)]),
        );
    }

    /** Fetch one payment link. */
    public function retrieve(string $paymentLinkId): PaymentLink
    {
        return new PaymentLink(
            $this->client->get(self::PATH . '/' . rawurlencode($paymentLinkId)),
        );
    }

    /**
     * Withdraw an unpaid link.
     *
     * A link that has already been paid throws `ConflictException` — reversing
     * a settled payment is a refund, done in the gateway, not here.
     */
    public function cancel(string $paymentLinkId): PaymentLink
    {
        $path = self::PATH . '/' . rawurlencode($paymentLinkId) . '/cancel';

        return new PaymentLink($this->client->post($path, ['json' => []]));
    }
}
