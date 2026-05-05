<?php

namespace Tests\Unit;

use App\Http\Controllers\MeetingScheduleController;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class MeetingScheduleControllerTest extends TestCase
{
    public function test_start_time_before_now_is_treated_as_past(): void
    {
        Carbon::setTestNow('2026-04-14 10:41:00');

        $controller = new MeetingScheduleController();
        $method = new ReflectionMethod($controller, 'startsInPast');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, Carbon::parse('2026-04-14 10:40:00')));
        $this->assertFalse($method->invoke($controller, Carbon::parse('2026-04-14 10:45:00')));

        Carbon::setTestNow();
    }
}