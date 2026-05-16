<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup;

/**
 * Thrown by a CallsignProviderInterface when the upstream is unreachable,
 * times out, or returns unparseable data. The orchestrator catches this
 * and falls through to the next provider; the failure is logged but
 * doesn't fail the user-facing request.
 */
final class CallsignLookupException extends \RuntimeException
{
}
