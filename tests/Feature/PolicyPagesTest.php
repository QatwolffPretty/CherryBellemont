<?php

namespace Tests\Feature;

use Tests\TestCase;

class PolicyPagesTest extends TestCase
{
    public function test_the_customer_policy_pages_are_available_at_their_named_routes(): void
    {
        $pages = [
            'shipping.policy' => 'Shipping Policy',
            'refund.policy' => 'Refund & Returns Policy',
            'privacy.policy' => 'Privacy Policy',
            'terms.policy' => 'Terms & Conditions',
        ];

        foreach ($pages as $route => $heading) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($heading);
        }
    }
}
