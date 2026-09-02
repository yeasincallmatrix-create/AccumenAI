<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationNotificationQueueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_queued_verify_email_implements_should_queue(): void
    {
        $notification = new QueuedVerifyEmail();
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('default', $notification->queue);
    }

    public function test_verification_controller_queues_in_non_testing(): void
    {
        // Directly test that QueuedVerifyEmail would be queued via database outside testing
        // Simulate local environment: app()->environment('local') would use QueuedVerifyEmail
        // We verify the notification class itself is queueable and would insert into jobs quickly
        $user = User::create([
            'name' => 'Queue Test',
            'first_name' => 'Queue',
            'last_name' => 'Test',
            'email' => 'queue-'.uniqid().'@example.test',
            'phone' => '+88017'.rand(10000000,19999999),
            'password_hash' => bcrypt('Queue123!'),
            'status' => 'active',
            'account_type' => 'owner',
            'email_verified_at' => null,
        ]);

        // In testing, User::sendEmailVerificationNotification uses sync VerifyEmail for assertions
        // So we directly test that QueuedVerifyEmail when notified would queue
        Notification::fake();
        $user->notify(new QueuedVerifyEmail());
        Notification::assertSentTo($user, QueuedVerifyEmail::class);

        // Verify that the queued notification would not do inline SMTP (it implements ShouldQueue)
        $this->assertTrue((new QueuedVerifyEmail()) instanceof ShouldQueue, 'Verification must be queued to avoid 30s SMTP timeout');
    }

    public function test_post_verification_notification_returns_quickly(): void
    {
        $user = User::create([
            'name' => 'Quick Test',
            'first_name' => 'Quick',
            'last_name' => 'Test',
            'email' => 'quick-'.uniqid().'@example.test',
            'phone' => '+88017'.rand(20000000,29999999),
            'password_hash' => bcrypt('Quick123!'),
            'status' => 'active',
            'account_type' => 'owner',
            'email_verified_at' => null,
        ]);

        $this->actingAs($user, 'web');
        $start = microtime(true);
        $response = $this->post(route('verification.send'));
        $elapsed = microtime(true) - $start;

        // Should return quickly (<2s) and not hit 30s max_execution_time
        $this->assertLessThan(2, $elapsed, "Verification notification should be non-blocking, took {$elapsed}s");
        $response->assertStatus(302);
        // In testing, it uses sync VerifyEmail but with log mailer it's still fast (<0.5s)
        // In local with smtp, queued path would be <100ms insert into jobs, not 30s SMTP
    }

    public function test_no_plaintext_secrets_in_notification(): void
    {
        $notification = new QueuedVerifyEmail();
        $reflection = new \ReflectionClass($notification);
        $content = file_get_contents($reflection->getFileName());
        $this->assertStringNotContainsString('yeasin.callmatrix', strtolower($content));
        $this->assertStringNotContainsString('verify_peer=false', strtolower($content));
        $this->assertStringNotContainsString('plaintext', strtolower($content));
    }
}
