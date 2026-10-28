<?php

namespace App\Mcp\Tools;

use App\Actions\Calendar\ListCalendarEntries;
use App\Models\User;
use App\Support\Calendar\CalendarEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The calendar for Claude through ListCalendarEntries, which already leaves out the sources the user may not see. The
 * range is at most MAX_DAYS days, the same as a quarter.
 */
#[Description('Calendar entries for a date range of at most 92 days: events, task due dates and finished tasks, service ends, charge due dates, payments, quotes, SSL ends and site outages. only_mine keeps my events and my tasks. "recorded" entries already happened; the others are dates ahead.')]
#[IsReadOnly]
#[IsIdempotent]
class ListCalendar extends BaseTool
{
    /**
     * The longest range, both days included.
     */
    public const int MAX_DAYS = 92;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->format('date')->description('First day, YYYY-MM-DD.')->required(),
            'to' => $schema->string()->format('date')->description('Last day, YYYY-MM-DD.')->required(),
            'only_mine' => $schema->boolean()->description('Only my events and my tasks.'),
        ];
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'only_mine' => ['boolean'],
        ]);

        $from = Carbon::parse($input['from']);
        $to = Carbon::parse($input['to']);

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            throw ValidationException::withMessages([
                'to' => __('mcp.calendar_range', ['days' => self::MAX_DAYS]),
            ]);
        }

        $entries = app(ListCalendarEntries::class)->handle($user, $from, $to, onlyMine: (bool) ($input['only_mine'] ?? false));

        return Response::structured([
            'total' => $entries->count(),
            'entries' => $entries->take(self::MAX_ROWS)->map(fn (CalendarEntry $entry): array => [
                'date' => $entry->date->toDateString(),
                'all_day' => $entry->allDay,
                'start' => $entry->start,
                'end' => $entry->end,
                'category' => $entry->category->value,
                'title' => $entry->title,
                'detail' => $entry->detail,
                'recorded' => $entry->recorded,
            ])->values()->all(),
        ]);
    }
}
