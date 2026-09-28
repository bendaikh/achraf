<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SmtpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AuthSmtpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_forgot_password_and_no_default_credentials(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('Mot de passe oublié');
        $response->assertDontSee('admin@');
        $response->assertDontSee('password123');
        $response->assertDontSee('Mot de passe par défaut');
    }

    public function test_forgot_password_always_returns_generic_message(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertStringContainsString(
            'Si un compte correspond',
            (string) session('status')
        );
        Notification::assertNothingSent();
    }

    public function test_forgot_password_sends_reset_for_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ops@libromart.test']);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_smtp_settings_persist_encrypted_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->put(route('settings.update'), [
            'settings_type' => 'smtp',
            'mail_from_name' => 'Libromart',
            'mail_from_address' => 'noreply@libromart.test',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'noreply@libromart.test',
            'mail_password' => 'secret-smtp-pass',
        ])->assertRedirect(route('settings.smtp'));

        $this->assertTrue(SmtpSettings::isConfigured());
        $this->assertSame('secret-smtp-pass', SmtpSettings::decryptedPassword());
        $all = SmtpSettings::all();
        $this->assertTrue($all['mail_password_set']);
        $this->assertSame('smtp.example.com', $all['mail_host']);
    }
}
