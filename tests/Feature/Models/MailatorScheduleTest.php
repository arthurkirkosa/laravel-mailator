<?php

namespace Binarcode\LaravelMailator\Tests\Feature\Models;

use Binarcode\LaravelMailator\Models\MailatorSchedule;
use Binarcode\LaravelMailator\Tests\Fixtures\CustomAction;
use Binarcode\LaravelMailator\Tests\Fixtures\InvoiceReminderMailable;
use Binarcode\LaravelMailator\Tests\Fixtures\SerializedConditionCondition;
use Binarcode\LaravelMailator\Tests\Fixtures\SingleSendingCondition;
use Binarcode\LaravelMailator\Tests\Fixtures\User;
use Binarcode\LaravelMailator\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Spatie\TestTime\TestTime;

class MailatorScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestTime::freeze();
    }

    public function test_can_create_mailator_schedule()
    {
        MailatorSchedule::init('Invoice reminder.')
            ->mailable(new InvoiceReminderMailable())
            ->days(1)
            ->before(now()->addWeek())
            ->when(function() {
                return 'Working.';
            })
            ->save();

        $this->assertCount(1, MailatorSchedule::all());
    }

    public function test_sending_email_only_once()
    {
        Mail::fake();
        Mail::assertNothingSent();

        MailatorSchedule::init('Invoice reminder.')
            ->recipients([
                'zoo@bar.com',
            ])
            ->mailable(
                (new InvoiceReminderMailable())->to('foo@bar.com')
            )
            ->days(1)
            ->constraint(new SingleSendingCondition)
            ->save();

        $_SERVER['can_send'] = true;
        MailatorSchedule::run();
        Mail::assertSent(InvoiceReminderMailable::class, 1);

        $_SERVER['can_send'] = false;

        MailatorSchedule::run();
        Mail::assertSent(InvoiceReminderMailable::class, 1);

        MailatorSchedule::run();
        Mail::assertSent(InvoiceReminderMailable::class, 1);

        Mail::assertSent(InvoiceReminderMailable::class, function(InvoiceReminderMailable $mail) {
            return $mail->hasTo('foo@bar.com') && $mail->hasTo('zoo@bar.com');
        });
    }

    public function test_can_send_serialized_constrained()
    {
        Mail::fake();
        Mail::assertNothingSent();

        $scheduler = MailatorSchedule::init('Invoice reminder.')
            ->recipients([
                'zoo@bar.com',
            ])
            ->mailable(
                (new InvoiceReminderMailable())->to('foo@bar.com')
            )
            ->days(1)
            ->constraint(
                new SerializedConditionCondition(new User([
                    'email' => 'john.doe@binarcode.com',
                ]))
            )
            ->before();

        $scheduler->save();

        MailatorSchedule::run();
        MailatorSchedule::run();

        Mail::assertSent(InvoiceReminderMailable::class, 2);
    }

    public function test_can_use_carbon_target_date()
    {
        Mail::fake();
        Mail::assertNothingSent();

        $scheduler = MailatorSchedule::init('Invoice reminder.')
            ->recipients([
                'zoo@bar.com',
            ])
            ->mailable(
                (new InvoiceReminderMailable())->to('foo@bar.com')
            )
            ->days(1)
            ->before(now()->addDays(7));

        $scheduler->save();

        MailatorSchedule::run();
        Mail::assertNothingSent();

        TestTime::addDays(6);
        MailatorSchedule::run();

        Mail::assertSent(InvoiceReminderMailable::class, 1);
    }

    public function test_can_handle_custom_action()
    {
        Mail::fake();
        Mail::assertNothingSent();

        $mock = $this->partialMock(CustomAction::class);

        $mock->shouldReceive('handle')->once();

        MailatorSchedule::init('Invoice reminder.')
            ->days(1)
            ->before(now()->addDays(2))
            ->actionClass(CustomAction::class)
            ->save();

        TestTime::addDay();

        MailatorSchedule::run();
    }
}
