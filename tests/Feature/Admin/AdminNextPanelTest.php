<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNextPanelTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function panelPaths(): array
    {
        return [
            'root' => ['/admin-next'],
            'root with trailing slash' => ['/admin-next/'],
            'list' => ['/admin-next/products'],
            'list with filters in the query' => ['/admin-next/products?search=rake&sort=-price&page=2'],
            'details' => ['/admin-next/products/12'],
            'create' => ['/admin-next/products/new'],
            'edit' => ['/admin-next/users/3/edit'],
            'unknown' => ['/admin-next/does/not/exist'],
        ];
    }

    #[DataProvider('panelPaths')]
    public function test_every_panel_path_returns_the_vue_shell(string $path): void
    {
        $this->withoutVite();

        // Vue Router decides what to render (including its own "not found" page).
        $this->get($path)
            ->assertOk()
            ->assertViewIs('admin-next')
            ->assertSee('<div id="app"></div>', false);
    }

    public function test_shell_loads_the_vue_entry_point(): void
    {
        $hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($hotFile, 'http://vite.test');
        Vite::useHotFile($hotFile);

        try {
            $this->get('/admin-next/products')
                ->assertOk()
                ->assertSee('http://vite.test/resources/js/admin/main.ts', false);
        } finally {
            unlink($hotFile);
        }
    }

    public function test_shell_is_read_only(): void
    {
        $this->post('/admin-next/products')->assertMethodNotAllowed();
    }

    public function test_paths_outside_the_panel_are_not_caught(): void
    {
        $this->get('/admin-nextish')->assertNotFound();
        $this->getJson('/api/admin-next/products')->assertNotFound()->assertJsonStructure(['message']);
    }
}
