<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    public function test_admin_panel_page_is_served_without_session(): void
    {
        $this->withoutVite();

        // The page is only a shell; data and permissions come from the token-protected API.
        $this->get('/admin')
            ->assertOk()
            ->assertSee('<title>Panel sklepu</title>', false)
            ->assertSee('x-data="adminPanel"', false);
    }

    public function test_seeder_credentials_hint_is_shown_only_locally(): void
    {
        $this->withoutVite();

        $this->get('/admin')->assertDontSee('admin@example.com');

        $this->app['env'] = 'local';

        $this->get('/admin')->assertSee('admin@example.com');
    }
}
