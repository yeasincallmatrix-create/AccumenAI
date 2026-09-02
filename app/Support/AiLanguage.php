<?php

namespace App\Support;

/**
 * Lightweight language detection for AI prompts.
 *
 * The AI itself is a single engine that can handle all languages; this helper
 * only decides which follow-up instruction to give the model so it mirrors the
 * user's language (Bangla script, English, or Banglish). Detection is a hint,
 * not an absolute gate — the model still parses the actual query.
 */
final class AiLanguage
{
    public const BN = 'bn';

    public const BANGLISH = 'banglish';

    public const EN = 'en';

    /** Common Latin-script (Banglish) words that indicate Bangla content. */
    private const BANGLISH_HINTS = [
        'ache', 'amra', 'apnar', 'ase', 'bar', 'bolo', 'chai', 'dekh', 'ei',
        'hoy', 'jonno', 'kato', 'kono', 'kore', 'koto', 'koyjon', 'mashe',
        'moj', 'na', 'niyeche', 'niyechhe', 'ottho', 'somoy', 'tumi', 'tomon',
        'koyta', 'kobor', 'bolen', 'janan', 'hobe', 'ache', 'asche', 'kise',
    ];

    public static function detect(string $text): string
    {
        if ($text === '') {
            return self::EN;
        }

        // Bengali Unicode block U+0980–U+09FF → Bangla script.
        if (preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1) {
            return self::BN;
        }

        // Otherwise check for common Latin-script Bangla tokens (Banglish).
        $tokens = preg_split('/\s+|[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        if (array_intersect($tokens, self::BANGLISH_HINTS) !== []) {
            return self::BANGLISH;
        }

        return self::EN;
    }

    /**
     * Human guidance line appended to the system prompt for the detected language.
     */
    public static function instruction(string $text): string
    {
        return match (self::detect($text)) {
            self::BN => 'The user is writing in Bangla (Bengali script). Reply in Bangla.',
            self::BANGLISH => 'The user is writing in Banglish (Bangla words in Latin script). '
                .'Reply in a friendly, natural way that matches their mix; Bengali script is fine too.',
            default => 'The user is writing in English. Reply in English.',
        };
    }

    /**
     * Human guidance line that honours an explicit response-language preference.
     *
     * `auto` keeps the existing per-message detection (AiLanguage::instruction);
     * a pinned language forces the reply language without duplicating the
     * detection logic used by the AI engine.
     */
    public static function instructionFor(string $text, string $preference): string
    {
        return match ($preference) {
            self::BN => 'The user wants replies in Bangla (Bengali script). Reply in Bangla.',
            self::EN => 'The user wants replies in English. Reply in English.',
            default => self::instruction($text),
        };
    }
}
