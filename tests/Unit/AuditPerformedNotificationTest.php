<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Models\User;
use App\Notifications\AuditPerformedNotification;
use App\Notifications\Channels\PreferredNotificationChannel;
use Tests\TestCase;

class AuditPerformedNotificationTest extends TestCase
{
    public function test_system_notifications_require_explicit_opt_in(): void
    {
        $notification = new AuditPerformedNotification(new Audit);
        $user = new User(['status' => 'active', 'receive_notifications' => false]);
        $this->assertSame([], $notification->via($user));

        $user->receive_notifications = true;
        $this->assertSame(['database', PreferredNotificationChannel::class], $notification->via($user));

        $user->status = 'inactive';
        $this->assertSame([], $notification->via($user));
    }
}
