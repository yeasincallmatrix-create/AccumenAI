<?php

namespace App\Services\Ai;

/**
 * Raised when an AI request is refused by an access gate (platform switch,
 * institute toggle or feature flag). Caught by AiService and surfaced as a
 * `blocked` status — never as an exception to the caller.
 */
class AiAccessException extends \RuntimeException {}
