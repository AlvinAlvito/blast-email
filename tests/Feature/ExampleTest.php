<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_the_import_template_can_be_downloaded(): void
    {
        $response = $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'posi',
        ])->get('/imports/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSeeText('no,nama,email,no hp,provinsi,kota,jenjang', false);
    }
}
