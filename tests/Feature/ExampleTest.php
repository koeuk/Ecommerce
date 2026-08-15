<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The storefront is API-driven, so the web root is not a page — it hands
     * visitors straight to the admin login.
     */
    public function test_the_root_redirects_to_the_admin_login(): void
    {
        $this->get('/')->assertRedirect(route('admin.login'));
    }
}
