<?php

namespace Tests\Feature;

use Tests\TestCase;

class NewsletterPlacementTest extends TestCase
{
    public function test_the_newsletter_call_to_action_renders_once_directly_before_the_footer(): void
    {
        $response = $this->get(route('contact'));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('Exclusive Access')
            ->assertSee('Join the Cherry Bellemont Community')
            ->assertSee('exclusive Cherry Bellemont updates delivered directly to your inbox.')
            ->assertSee('Optional Name')
            ->assertSee('Email Address');

        $this->assertSame(1, substr_count($content, 'data-newsletter-feature'));
        $this->assertLessThan(strpos($content, '<footer'), strpos($content, 'data-newsletter-feature'));
    }
}
