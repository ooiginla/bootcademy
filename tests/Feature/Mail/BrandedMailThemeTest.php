<?php

namespace Tests\Feature\Mail;

use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class BrandedMailThemeTest extends TestCase
{
    public function test_notification_emails_use_the_bootcademy_brand_shell(): void
    {
        config()->set('app.url', 'https://bootcademy.test');

        $message = (new MailMessage)
            ->subject('Theme test')
            ->greeting('Welcome to Bootcademy')
            ->line('Your academy is ready.')
            ->action('Open Bootcademy', 'https://bootcademy.test/dashboard');

        $html = $message->render()->toHtml();

        $this->assertStringContainsString('Bootcademy%20logo%20design.png', $html);
        $this->assertStringContainsString('Bootcademy — Every Academy. One Platform.', $html);
        $this->assertStringContainsString('Every Academy. One Platform.', $html);
        $this->assertStringContainsString('The Bootcademy Team', $html);
        $this->assertStringContainsString('Open Bootcademy', $html);
        $this->assertStringContainsString('#ff6a13', strtolower($html));
    }
}
