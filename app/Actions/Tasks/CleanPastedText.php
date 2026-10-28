<?php

namespace App\Actions\Tasks;

/**
 * Tidies a text pasted into a task's notes (Faz 17, U-41): drops the WhatsApp "[time, date] Name:" prefixes, trims the
 * lines and squeezes blank lines. The sender's name is only kept when more than one person speaks in the text, at the
 * line where the speaker changes. Writes nothing.
 */
class CleanPastedText
{
    public const MAX_LENGTH = 5000;

    /** "[28.10.2026 14:32] Ad:" and "[14:32, 28.10.2026] Ad:" at the start of a line. */
    private const PREFIX = '/^\[(?:\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4},?\s+\d{1,2}:\d{2}(?::\d{2})?|\d{1,2}:\d{2}(?::\d{2})?,?\s+\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4})\]\s*([^:\n]+):[ \t]*/u';

    public function handle(string $text): string
    {
        $lines = [];
        $senders = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            $sender = null;

            if (preg_match(self::PREFIX, $line, $matches) === 1) {
                $sender = trim($matches[1]);
                $senders[$sender] = true;
                $line = (string) preg_replace(self::PREFIX, '', $line);
            }

            $lines[] = [$sender, rtrim($line)];
        }

        $manySenders = count($senders) > 1;
        $speaker = null;
        $cleaned = [];

        foreach ($lines as [$sender, $line]) {
            if ($manySenders && $sender !== null && $sender !== $speaker) {
                $line = $sender.': '.$line;
            }

            $speaker = $sender ?? $speaker;
            $cleaned[] = $line;
        }

        $result = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $cleaned)));

        return mb_substr($result, 0, self::MAX_LENGTH);
    }
}
