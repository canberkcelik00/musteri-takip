<?php

namespace App\Filament\Widgets;

use App\Actions\Scheduler\CheckSchedulerHealth;
use App\Enums\SchedulerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\CustomerService;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;

/**
 * The scheduler warning at the top of "Bugün" (G-5.6): shown only when
 * app:process-customer-services has not run for over an hour (or never), and when services
 * failed in its last run. Only calls CheckSchedulerHealth; no polling, it is current on load.
 */
class SchedulerHealth extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    /**
     * At most this many failing services are named; the rest are counted.
     */
    public const LISTED_FAILED_SERVICES = 5;

    protected string $view = 'filament.widgets.scheduler-health';

    protected int|string|array $columnSpan = 'full';

    /**
     * Rendered with the page, so the warning does not push the stats down after they appeared.
     */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $health = app(CheckSchedulerHealth::class)->handle();

        return $health['status'] !== SchedulerStatus::Healthy || $health['failed_services']->isNotEmpty();
    }

    public function content(Schema $schema): Schema
    {
        $health = app(CheckSchedulerHealth::class)->handle();
        $failedServices = $health['failed_services'];

        $statusMessage = match ($health['status']) {
            SchedulerStatus::Healthy => null,
            SchedulerStatus::NeverRan => __('scheduler.never_ran'),
            SchedulerStatus::Stale => __('scheduler.stale', ['time' => $health['last_run_at']?->format('d.m.Y H:i')]),
            SchedulerStatus::PendingRelease => __('scheduler.pending_release'),
        };

        return $schema->components(array_values(array_filter([
            $statusMessage === null ? null : Callout::make($statusMessage)->danger(),
            $failedServices->isEmpty() ? null : Callout::make(__('scheduler.failed_services', ['count' => $failedServices->count()]))
                ->description($this->failedServicesDescription($failedServices->all()))
                ->danger(),
        ])));
    }

    /**
     * "Logi · Hosting, Anadolu Gıda · SSL ve 2 hizmet daha. Ayrıntılar hata kaydında." with each
     * name linking to its customer's card.
     *
     * @param  array<int, CustomerService>  $failedServices
     */
    private function failedServicesDescription(array $failedServices): HtmlString
    {
        $links = array_map(
            fn (CustomerService $customerService): string => sprintf(
                '<a href="%s" class="fi-link">%s</a>',
                e(CustomerResource::getUrl('view', ['record' => $customerService->customer])),
                e($customerService->customer->short_name.' · '.$customerService->name),
            ),
            array_slice($failedServices, 0, self::LISTED_FAILED_SERVICES),
        );

        $remaining = count($failedServices) - self::LISTED_FAILED_SERVICES;
        $more = $remaining > 0 ? ' '.e(__('scheduler.failed_services_more', ['count' => $remaining])) : '';

        return new HtmlString(implode(', ', $links).$more.'. '.e(__('scheduler.failed_services_log')));
    }
}
