<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactSectionTest extends TestCase
{
    public function test_the_contact_page_and_footer_use_the_central_store_configuration(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee(config('store.support_email'))
            ->assertSee(config('store.general_email'))
            ->assertSee(config('store.threads_url'), false)
            ->assertSee(config('store.instagram_url'), false)
            ->assertSee(config('store.facebook_url'), false)
            ->assertSee(route('about'), false)
            ->assertSee(route('collection'), false)
            ->assertSee(route('shipping.policy'), false)
            ->assertSee(route('refund.policy'), false)
            ->assertSee(route('privacy.policy'), false)
            ->assertSee(route('terms.policy'), false);
    }
}
