<?php

namespace App\Enums;

/**
 * Whether the scheduler (app:process-customer-services) is running, as shown on "Bugün".
 */
enum SchedulerStatus: string
{
    /** The last run finished within the allowed time. */
    case Healthy = 'healthy';

    /** No run was ever recorded (the hosting cron is not set up yet). */
    case NeverRan = 'never_ran';

    /** The last run is older than the allowed time. */
    case Stale = 'stale';

    /** The last run is older than the allowed time because a pulled release is not deployed yet. */
    case PendingRelease = 'pending_release';
}
