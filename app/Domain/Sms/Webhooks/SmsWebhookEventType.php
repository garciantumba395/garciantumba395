<?php

namespace App\Domain\Sms\Webhooks;

enum SmsWebhookEventType: string
{
    case DeliveryReport = 'delivery_report';
    case Inbound = 'inbound';
}
