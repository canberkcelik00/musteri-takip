<?php

namespace App\Actions\Tasks;

use App\Models\Customer;
use App\Support\Dns\HostName;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a message copied from WhatsApp into the fields of a new task (Faz 17, U-40). Writes nothing: the task is only
 * created when the user saves the window.
 */
class ParseQuickTask
{
    private const MAX_LENGTH = 5000;

    private const TITLE_LENGTH = 100;

    private const MAX_DOMAINS = 5;

    private const MAX_PHONES = 3;

    private const MAX_SENDERS = 3;

    private const MIN_SHORT_NAME_LENGTH = 3;

    /** "[28.10.2026 14:32] Ad:" and "[14:32, 28.10.2026] Ad:" at the start of a line. */
    private const PREFIX = '/^\[(?:\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4},?\s+\d{1,2}:\d{2}(?::\d{2})?|\d{1,2}:\d{2}(?::\d{2})?,?\s+\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4})\]\s*([^:\n]+):[ \t]*/u';

    /**
     * `matched_by` is the sign (domain, phone, sender name or short name) that found the customer, for the hint in the
     * window. The customer is only set when the signs point to exactly one customer that is not archived.
     *
     * @return array{title: string, notes: ?string, customer_id: ?int, matched_by: ?string}
     */
    public function handle(string $message): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", mb_substr($message, 0, self::MAX_LENGTH)));

        [$lines, $senders] = $this->splitLines($text);

        $title = $this->title($lines);
        $notes = $text === $title ? null : $text;

        if ($title === '') {
            return ['title' => '', 'notes' => null, 'customer_id' => null, 'matched_by' => null];
        }

        $body = implode("\n", $lines);
        [$customerId, $matchedBy] = $this->customer($body, $senders);

        return ['title' => $title, 'notes' => $notes, 'customer_id' => $customerId, 'matched_by' => $matchedBy];
    }

    /**
     * The lines without their WhatsApp prefix, and the sender names found in those prefixes.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitLines(string $text): array
    {
        $lines = [];
        $senders = [];

        foreach (explode("\n", $text) as $line) {
            if (preg_match(self::PREFIX, $line, $matches) === 1) {
                $senders[] = trim($matches[1]);
                $line = (string) preg_replace(self::PREFIX, '', $line);
            }

            $lines[] = $line;
        }

        return [$lines, array_values(array_unique($senders))];
    }

    /**
     * @param  list<string>  $lines
     */
    private function title(array $lines): string
    {
        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', $line));

            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) <= self::TITLE_LENGTH) {
                return $line;
            }

            $cut = mb_substr($line, 0, self::TITLE_LENGTH - 1);
            $space = mb_strrpos($cut, ' ');

            if ($space !== false && $space >= self::TITLE_LENGTH / 2) {
                $cut = mb_substr($cut, 0, $space);
            }

            return rtrim($cut, ' ,.;:-').'…';
        }

        return '';
    }

    /**
     * @param  list<string>  $senders
     * @return array{0: ?int, 1: ?string}
     */
    private function customer(string $body, array $senders): array
    {
        $found = [];

        foreach ($this->domains($body) as $domain) {
            $found[] = [$domain, $this->ids(Customer::query()->search($domain))];
        }

        $phones = $this->phones($body);
        $names = [];

        foreach (array_slice($senders, 0, self::MAX_SENDERS) as $sender) {
            if (preg_match('/^\+?[\d\s().-]+$/u', $sender) === 1) {
                $phones[] = $sender;
            } else {
                $names[] = $sender;
            }
        }

        foreach (array_slice(array_unique($phones), 0, self::MAX_PHONES + self::MAX_SENDERS) as $phone) {
            $found[] = [$phone, $this->ids(Customer::query()->search($phone))];
        }

        foreach ($names as $name) {
            $found[] = [$name, $this->ids(Customer::query()->whereHas('contacts', fn (Builder $contacts) => $contacts->where('name', $name)))];
        }

        foreach ($this->shortNames($body) as $shortName => $id) {
            $found[] = [$shortName, [$id]];
        }

        $ids = [];

        foreach ($found as [, $signIds]) {
            $ids = array_merge($ids, $signIds);
        }

        $ids = array_values(array_unique($ids));

        if (count($ids) !== 1) {
            return [null, null];
        }

        foreach ($found as [$sign, $signIds]) {
            if ($signIds !== []) {
                return [$ids[0], (string) $sign];
            }
        }

        return [null, null];
    }

    /**
     * @param  Builder<Customer>  $query
     * @return list<int>
     */
    private function ids(Builder $query): array
    {
        return $query->notArchived()->pluck('customers.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return list<string>
     */
    private function domains(string $body): array
    {
        preg_match_all('/(?:[\p{L}\p{N}-]+\.)+[\p{L}\p{N}-]{2,}/u', $body, $matches);

        $domains = [];

        foreach ($matches[0] as $candidate) {
            $host = HostName::normalize((string) preg_replace('/^www\./i', '', $candidate));

            if ($host !== null) {
                $domains[$host] = $host;
            }
        }

        return array_slice(array_values($domains), 0, self::MAX_DOMAINS);
    }

    /**
     * @return list<string>
     */
    private function phones(string $body): array
    {
        preg_match_all('/(?<![\d.,])(?:\+?90[\s.-]?)?0?\(?[2-5]\d{2}\)?[\s.-]?\d{3}[\s.-]?\d{2}[\s.-]?\d{2}(?!\d)/u', $body, $matches);

        return array_slice(array_values(array_unique($matches[0])), 0, self::MAX_PHONES);
    }

    /**
     * Short names of customers that are not archived and appear in the text as a whole word.
     *
     * @return array<string, int>
     */
    private function shortNames(string $body): array
    {
        $found = [];

        foreach (Customer::query()->notArchived()->pluck('short_name', 'id') as $id => $shortName) {
            $shortName = trim((string) $shortName);

            if (mb_strlen($shortName) < self::MIN_SHORT_NAME_LENGTH) {
                continue;
            }

            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($shortName, '/').'(?![\p{L}\p{N}])/iu', $body) === 1) {
                $found[$shortName] = (int) $id;
            }
        }

        return $found;
    }
}
