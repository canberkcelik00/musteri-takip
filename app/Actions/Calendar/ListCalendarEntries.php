<?php

namespace App\Actions\Calendar;

use App\Enums\CalendarCategory;
use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Enums\EventRepeat;
use App\Enums\Permission;
use App\Enums\QuoteStatus;
use App\Models\CalendarEvent;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\SiteIncident;
use App\Models\Task;
use App\Models\User;
use App\Support\Calendar\CalendarEntry;
use App\Support\Calendar\EventOccurrences;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the calendar shows for a range of days (docs/PRD.md, Takvim): the events (repeats expanded here, never
 * stored), the dates the system already holds (service end, charge due, quote validity, task due) and what was
 * recorded (payment, quote sent, task done). Nothing is stored; each source is one query for the whole range, so the
 * number of queries does not grow with the number of records. A source the user may not see is never queried: the
 * money sources need `money.view`, service ends `services.view`, tasks `tasks.manage`. Days are counted in the
 * application time zone (Europe/Istanbul).
 */
class ListCalendarEntries
{
    /**
     * @param  list<CalendarCategory>|null  $categories  null means every category
     * @return Collection<int, CalendarEntry> by day, all-day entries first, then by start time
     */
    public function handle(User $user, Carbon $from, Carbon $to, ?array $categories = null, bool $onlyMine = false): Collection
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $wants = fn (CalendarCategory $category): bool => $categories === null || in_array($category, $categories, true);

        $entries = collect();

        if ($wants(CalendarCategory::Event)) {
            $entries = $entries->concat($this->events($user, $from, $to, $onlyMine));
        }

        if ($wants(CalendarCategory::Task) && $user->can(Permission::TasksManage->value)) {
            $entries = $entries->concat($this->tasks($user, $from, $to, $onlyMine));
        }

        $systemDates = ! $onlyMine;
        $seesMoney = $user->can(Permission::MoneyView->value);

        if ($systemDates && $wants(CalendarCategory::ServiceEnd) && $user->can(Permission::ServicesView->value)) {
            $entries = $entries->concat($this->serviceEnds($from, $to));
        }

        if ($systemDates && $wants(CalendarCategory::AlertSoon) && $user->can(Permission::ServicesView->value)) {
            $entries = $entries->concat($this->sslExpiries($from, $to));
        }

        if ($systemDates && $wants(CalendarCategory::AlertDown) && $user->can(Permission::ServicesView->value)) {
            $entries = $entries->concat($this->siteIncidents($from, $to));
        }

        if ($systemDates && $seesMoney && $wants(CalendarCategory::ChargeDue)) {
            $entries = $entries->concat($this->chargesDue($from, $to));
        }

        if ($systemDates && $seesMoney && $wants(CalendarCategory::Payment)) {
            $entries = $entries->concat($this->payments($from, $to));
        }

        if ($systemDates && $seesMoney && $wants(CalendarCategory::Quote)) {
            $entries = $entries->concat($this->quotes($from, $to));
        }

        return $entries
            ->sort(fn (CalendarEntry $a, CalendarEntry $b): int => [$a->date->timestamp, $a->allDay ? 0 : 1, $a->start, $a->key]
                <=> [$b->date->timestamp, $b->allDay ? 0 : 1, $b->start, $b->key])
            ->values();
    }

    /**
     * @return list<CalendarEntry>
     */
    private function events(User $user, Carbon $from, Carbon $to, bool $onlyMine): array
    {
        $events = CalendarEvent::query()
            ->with('customer:id,short_name')
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->where(function (Builder $query) use ($from): void {
                $query->where(fn (Builder $single): Builder => $single
                    ->where('repeat', EventRepeat::None)
                    ->whereDate('starts_on', '>=', $from->toDateString()))
                    ->orWhere(fn (Builder $series): Builder => $series
                        ->where('repeat', '!=', EventRepeat::None)
                        ->where(fn (Builder $open): Builder => $open
                            ->whereNull('repeat_until')
                            ->orWhereDate('repeat_until', '>=', $from->toDateString())));
            })
            ->when($onlyMine, fn (Builder $query): Builder => $query->where(fn (Builder $mine): Builder => $mine
                ->where('created_by', $user->id)
                ->orWhereHas('attendees', fn (Builder $attendees): Builder => $attendees->whereKey($user->id))))
            ->get();

        $entries = [];

        foreach ($events as $event) {
            foreach ((new EventOccurrences)->between($event, $from, $to) as $date) {
                $entries[] = new CalendarEntry(
                    key: 'event:'.$event->id.':'.$date->toDateString(),
                    category: CalendarCategory::Event,
                    title: $event->title,
                    date: $date,
                    allDay: $event->all_day,
                    start: $event->all_day ? null : $this->hourMinute($event->start_time),
                    end: $event->all_day ? null : $this->hourMinute($event->end_time),
                    detail: $event->customer?->short_name,
                    eventId: $event->id,
                );
            }
        }

        return $entries;
    }

    /**
     * Open tasks on their due date, finished tasks at the moment they were finished.
     *
     * @return list<CalendarEntry>
     */
    private function tasks(User $user, Carbon $from, Carbon $to, bool $onlyMine): array
    {
        $tasks = Task::query()
            ->with('customer:id,short_name')
            ->where(function (Builder $query) use ($from, $to): void {
                $query->where(fn (Builder $open): Builder => $open
                    ->whereNull('completed_at')
                    ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()]))
                    ->orWhereBetween('completed_at', [$from, $to->copy()->endOfDay()]);
            })
            ->when($onlyMine, fn (Builder $query): Builder => $query->where('assigned_to', $user->id))
            ->get();

        $entries = [];

        foreach ($tasks as $task) {
            if ($task->completed_at !== null) {
                $entries[] = new CalendarEntry(
                    key: 'task-done:'.$task->id,
                    category: CalendarCategory::Task,
                    title: __('calendar.entry.task_done', ['title' => $task->title]),
                    date: $task->completed_at->copy()->startOfDay(),
                    allDay: false,
                    start: $task->completed_at->format('H:i'),
                    detail: $task->customer?->short_name,
                    recorded: true,
                    window: 'edit-task',
                    params: ['task' => $task->id],
                );

                continue;
            }

            $entries[] = new CalendarEntry(
                key: 'task:'.$task->id,
                category: CalendarCategory::Task,
                title: $task->title,
                date: $task->due_date->copy()->startOfDay(),
                allDay: true,
                detail: $task->customer?->short_name,
                window: 'edit-task',
                params: ['task' => $task->id],
            );
        }

        return $entries;
    }

    /**
     * @return list<CalendarEntry>
     */
    private function serviceEnds(Carbon $from, Carbon $to): array
    {
        return CustomerService::query()
            ->with(['customer:id,short_name', 'catalogService:id,name', 'package:id,name', 'items'])
            ->where('status', CustomerServiceStatus::Active)
            ->whereBetween('end_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('customer', fn (Builder $customers): Builder => $customers->scopes(['notArchived']))
            ->get()
            ->map(fn (CustomerService $service): CalendarEntry => new CalendarEntry(
                key: 'service-end:'.$service->id,
                category: CalendarCategory::ServiceEnd,
                title: __('calendar.entry.service_end', ['service' => $service->package_id !== null ? $service->package->name : $service->catalogService->name]),
                date: $service->end_date->copy()->startOfDay(),
                allDay: true,
                detail: implode(' · ', array_filter([$service->customer->short_name, ...$service->domains()])),
                url: route('app.customers.show', $service->customer_id),
            ))
            ->all();
    }

    /**
     * The day a certificate read by the SSL check ends (G-16.3), an amber warning (alert_soon).
     *
     * @return list<CalendarEntry>
     */
    private function sslExpiries(Carbon $from, Carbon $to): array
    {
        return CustomerServiceItem::query()
            ->with('customerService.customer:id,short_name')
            ->whereBetween('ssl_expires_at', [$from, $to->copy()->endOfDay()])
            ->whereHas('customerService', fn (Builder $services): Builder => $services
                ->where('status', CustomerServiceStatus::Active)
                ->whereHas('customer', fn (Builder $customers): Builder => $customers->scopes(['notArchived'])))
            ->get()
            ->map(fn (CustomerServiceItem $item): CalendarEntry => new CalendarEntry(
                key: 'ssl-end:'.$item->id,
                category: CalendarCategory::AlertSoon,
                title: __('calendar.entry.ssl_end', ['domain' => (string) ($item->details['domain'] ?? '')]),
                date: $item->ssl_expires_at->copy()->startOfDay(),
                allDay: true,
                detail: $item->customerService->customer->short_name,
                url: route('app.customers.show', $item->customerService->customer_id),
            ))
            ->all();
    }

    /**
     * A site that stopped answering shows in red at the moment the incident opened, and in green at the moment it
     * answered again (G-16.6); both are records of what happened, never dates ahead.
     *
     * @return list<CalendarEntry>
     */
    private function siteIncidents(Carbon $from, Carbon $to): array
    {
        $end = $to->copy()->endOfDay();

        $incidents = SiteIncident::query()
            ->with('item.customerService.customer:id,short_name')
            ->where(fn (Builder $query): Builder => $query
                ->whereBetween('started_at', [$from, $end])
                ->orWhereBetween('ended_at', [$from, $end]))
            ->whereHas('item.customerService.customer', fn (Builder $customers): Builder => $customers->scopes(['notArchived']))
            ->get();

        $entries = [];

        foreach ($incidents as $incident) {
            $customer = $incident->item->customerService->customer;
            $url = route('app.customers.show', $customer->id);

            if ($incident->started_at->between($from, $end)) {
                $entries[] = new CalendarEntry(
                    key: 'site-down:'.$incident->id,
                    category: CalendarCategory::AlertDown,
                    title: __('calendar.entry.site_down', ['domain' => $incident->domain]),
                    date: $incident->started_at->copy()->startOfDay(),
                    allDay: false,
                    start: $incident->started_at->format('H:i'),
                    detail: implode(' · ', array_filter([$customer->short_name, $incident->reason])),
                    recorded: true,
                    url: $url,
                );
            }

            if ($incident->ended_at !== null && $incident->ended_at->between($from, $end)) {
                $entries[] = new CalendarEntry(
                    key: 'site-up:'.$incident->id,
                    category: CalendarCategory::AlertDown,
                    title: __('calendar.entry.site_up', ['domain' => $incident->domain]),
                    date: $incident->ended_at->copy()->startOfDay(),
                    allDay: false,
                    start: $incident->ended_at->format('H:i'),
                    detail: implode(' · ', [$customer->short_name, __('calendar.entry.site_down_for', ['duration' => $incident->started_at->diffForHumans($incident->ended_at, CarbonInterface::DIFF_ABSOLUTE)])]),
                    recorded: true,
                    url: $url,
                    tone: 'green',
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<CalendarEntry>
     */
    private function chargesDue(Carbon $from, Carbon $to): array
    {
        return Charge::query()
            ->with('customer:id,short_name')
            ->withSum('allocations', 'amount')
            ->where('status', ChargeStatus::Open)
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->unarchivedCustomer()
            ->get()
            ->map(fn (Charge $charge): CalendarEntry => new CalendarEntry(
                key: 'charge-due:'.$charge->id,
                category: CalendarCategory::ChargeDue,
                title: __('calendar.entry.charge_due', ['customer' => $charge->customer->short_name]),
                date: $charge->due_date->copy()->startOfDay(),
                allDay: true,
                detail: __('calendar.entry.remaining', ['amount' => (new Money($charge->remainingAmount(), $charge->currency))->format()]),
                window: 'record-payment',
                params: ['charge' => $charge->id],
            ))
            ->all();
    }

    /**
     * @return list<CalendarEntry>
     */
    private function payments(Carbon $from, Carbon $to): array
    {
        return Payment::query()
            ->with('customer:id,short_name')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->get()
            ->map(fn (Payment $payment): CalendarEntry => new CalendarEntry(
                key: 'payment:'.$payment->id,
                category: CalendarCategory::Payment,
                title: __('calendar.entry.payment', ['customer' => $payment->customer->short_name]),
                date: $payment->created_at->copy()->startOfDay(),
                allDay: false,
                start: $payment->created_at->format('H:i'),
                detail: (new Money($payment->amount, $payment->currency))->format(),
                recorded: true,
                window: 'view-payment',
                params: ['payment' => $payment->id],
            ))
            ->all();
    }

    /**
     * A sent quote shows on the last day it is valid and, as a record, at the moment it was sent.
     *
     * @return list<CalendarEntry>
     */
    private function quotes(Carbon $from, Carbon $to): array
    {
        $quotes = Quote::query()
            ->with('customer:id,short_name')
            ->where(function (Builder $query) use ($from, $to): void {
                $query->where(fn (Builder $valid): Builder => $valid
                    ->where('status', QuoteStatus::Sent)
                    ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()]))
                    ->orWhereBetween('sent_at', [$from, $to->copy()->endOfDay()]);
            })
            ->get();

        $entries = [];

        foreach ($quotes as $quote) {
            $recipient = $quote->customer_id !== null ? $quote->customer->short_name : $quote->recipient_name;

            if ($quote->status === QuoteStatus::Sent
                && $quote->valid_until->between($from, $to)) {
                $entries[] = new CalendarEntry(
                    key: 'quote-valid:'.$quote->id,
                    category: CalendarCategory::Quote,
                    title: __('calendar.entry.quote_valid', ['number' => $quote->number]),
                    date: $quote->valid_until->copy()->startOfDay(),
                    allDay: true,
                    detail: $recipient,
                    window: 'quote',
                    params: ['quote' => $quote->id],
                );
            }

            if ($quote->sent_at !== null && $quote->sent_at->between($from, $to->copy()->endOfDay())) {
                $entries[] = new CalendarEntry(
                    key: 'quote-sent:'.$quote->id,
                    category: CalendarCategory::Quote,
                    title: __('calendar.entry.quote_sent', ['number' => $quote->number]),
                    date: $quote->sent_at->copy()->startOfDay(),
                    allDay: false,
                    start: $quote->sent_at->format('H:i'),
                    detail: $recipient,
                    recorded: true,
                    window: 'quote',
                    params: ['quote' => $quote->id],
                );
            }
        }

        return $entries;
    }

    private function hourMinute(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
