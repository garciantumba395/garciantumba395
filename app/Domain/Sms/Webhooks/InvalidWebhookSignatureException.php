<?php

namespace App\Domain\Sms\Webhooks;

use RuntimeException;

/** Signature absente, invalide, ou horodatage hors fenêtre : la requête est refusée (401). */
class InvalidWebhookSignatureException extends RuntimeException {}
