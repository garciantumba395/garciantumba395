<?php

namespace App\Domain\Sms\Webhooks;

use RuntimeException;

/** Corps authentifié mais illisible ou incomplet (400). */
class InvalidWebhookPayloadException extends RuntimeException {}
