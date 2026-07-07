<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$eventType = $payload['data']['attributes']['type'] ?? '';
$eventData = $payload['data']['attributes']['data'] ?? [];

if ($eventType === 'checkout_session.payment.paid') {
    $checkoutId = $eventData['id'] ?? '';
    $metadata = $eventData['attributes']['metadata'] ?? [];

    $paymentIdLocal = (int)($metadata['payment_id'] ?? 0);

    $paymongoPaymentId = '';
    $payments = $eventData['attributes']['payments'] ?? [];

    if (!empty($payments[0]['id'])) {
        $paymongoPaymentId = $payments[0]['id'];
    }

    if ($paymentIdLocal > 0 && $paymongoPaymentId !== '') {
        $stmt = mysqli_prepare($conn, "
            UPDATE booking_payments
            SET 
                provider_transaction_id = ?,
                provider_reference = IF(provider_reference IS NULL OR provider_reference = '', ?, provider_reference),
                webhook_payload = ?,
                note = CONCAT(IFNULL(note, ''), ' | Webhook payment.paid received.')
            WHERE id = ?
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssi", $paymongoPaymentId, $checkoutId, $raw, $paymentIdLocal);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (
    $eventType === 'payment.refund.updated' ||
    $eventType === 'payment.refunded'
) {
    $eventId = $eventData['id'] ?? '';
    $attr = $eventData['attributes'] ?? [];

    $refundId = $eventId;
    $refundStatus = $attr['status'] ?? '';
    $paymongoPaymentId = $attr['payment_id'] ?? '';

    if ($eventType === 'payment.refunded') {
        $paymongoPaymentId = $eventId;
        $refundStatus = 'succeeded';
    }

    if ($refundStatus === 'refunded') {
        $refundStatus = 'succeeded';
    }

    if ($paymongoPaymentId !== '') {
        $paymentStatus = ($refundStatus === 'succeeded') ? 'Refunded' : 'Refund Pending';

        $stmt = mysqli_prepare($conn, "
            UPDATE booking_payments
            SET 
                refund_id = IF(refund_id IS NULL OR refund_id = '', ?, refund_id),
                refund_status = ?,
                payment_status = ?,
                refunded_at = IF(? = 'succeeded', NOW(), refunded_at),
                webhook_payload = ?,
                note = CONCAT(IFNULL(note, ''), ' | Refund webhook: ', ?)
            WHERE provider_transaction_id = ?
        ");

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssssss",
                $refundId,
                $refundStatus,
                $paymentStatus,
                $refundStatus,
                $raw,
                $refundStatus,
                $paymongoPaymentId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

http_response_code(200);
echo json_encode(['success' => true]);
exit;