<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CustomerCard;
use App\Mcp\Tools\ListCalendar;
use App\Mcp\Tools\ListCharges;
use App\Mcp\Tools\ListPayments;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\SearchCustomers;
use App\Mcp\Tools\TodaySummary;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The Claude access endpoint (/mcp, U-42). Every tool calls an existing Action or model scope, checks the token
 * owner's permission and returns hand-picked fields; there is never a delete, cancel, archive or undo tool.
 */
#[Name('Müşteri Takip')]
#[Version('1.0.0')]
#[Instructions('Read the business data of a small web services company: customers, their services, charges, payments, tasks and the calendar. All amounts are per currency and are never added across currencies. Dates are in the Europe/Istanbul timezone.')]
class MusteriTakipServer extends Server
{
    protected array $tools = [
        TodaySummary::class,
        SearchCustomers::class,
        CustomerCard::class,
        ListCharges::class,
        ListPayments::class,
        ListTasks::class,
        ListCalendar::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
