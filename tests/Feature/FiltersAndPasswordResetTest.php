<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Notifications\ClientPasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Runs against the dev database (no RefreshDatabase): every row it creates is
 * torn down in tearDown.
 */
class FiltersAndPasswordResetTest extends TestCase
{
    private array $trash = [];

    protected function tearDown(): void
    {
        foreach ($this->trash as $model) {
            $model->forceDelete();
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No administrator in the database to act as.');
        }

        return $admin;
    }

    public function test_admin_product_filters_render(): void
    {
        $urls = [
            '/admin/products',
            '/admin/products?q=cahier',
            '/admin/products?status=hidden&stock=low',
            '/admin/products?promo=1&featured=1&new=1&shipping=1&no_image=1',
            '/admin/products?min=100&max=5000&sort=price_desc&per_page=50',
            '/admin/products?category=0',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_storefront_filters_narrow_results(): void
    {
        $all = $this->get('/boutique')->assertOk();
        $none = $this->get('/boutique?min=99999999')->assertOk();

        if ($all->viewData('products')->total() === 0) {
            $this->markTestSkipped('No published products to filter.');
        }

        $this->assertSame(0, $none->viewData('products')->total());
        $this->assertTrue($none->viewData('filter')->isActive());
    }

    public function test_admin_resets_a_client_password(): void
    {
        $client = Client::create([
            'name' => 'Test Reset', 'email' => 'test-reset@example.test',
            'phone' => '0000', 'password' => 'oldpass1', 'type' => 'retail', 'is_active' => true,
        ]);
        $this->trash[] = $client;

        $response = $this->actingAs($this->admin())
            ->post(route('admin.clients.password', $client))
            ->assertRedirect();

        $new = session('new_password');
        $this->assertNotEmpty($new);
        $this->assertTrue(Hash::check($new, $client->fresh()->password));
    }

    public function test_admin_resets_a_staff_password(): void
    {
        $user = User::create([
            'name' => 'Test Staff', 'email' => 'test-staff@example.test',
            'password' => Hash::make('oldpass1'), 'role' => 'staff',
            'is_admin' => true, 'is_active' => true, 'permissions' => ['orders'],
        ]);
        $this->trash[] = $user;

        $this->actingAs($this->admin())
            ->post(route('admin.users.password', $user), ['password' => 'chosen-one'])
            ->assertRedirect();

        $this->assertTrue(Hash::check('chosen-one', $user->fresh()->password));
    }

    public function test_staff_without_admin_role_cannot_reset_passwords(): void
    {
        $manager = User::create([
            'name' => 'Test Manager', 'email' => 'test-manager@example.test',
            'password' => Hash::make('oldpass1'), 'role' => 'manager',
            'is_admin' => true, 'is_active' => true, 'permissions' => ['users'],
        ]);
        $this->trash[] = $manager;

        $this->actingAs($manager)
            ->post(route('admin.users.password', $manager))
            ->assertRedirect();

        $this->assertTrue(Hash::check('oldpass1', $manager->fresh()->password));
    }

    public function test_client_can_request_and_use_a_reset_link(): void
    {
        Notification::fake();

        $client = Client::create([
            'name' => 'Test Forgot', 'email' => 'test-forgot@example.test',
            'phone' => '0000', 'password' => 'oldpass1', 'type' => 'retail', 'is_active' => true,
        ]);
        $this->trash[] = $client;

        $this->get(route('account.password.request'))->assertOk();
        $this->post(route('account.password.email'), ['email' => $client->email])
            ->assertRedirect()->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($client, ClientPasswordReset::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get(route('account.password.reset', ['token' => $token, 'email' => $client->email]))->assertOk();

        $this->post(route('account.password.update'), [
            'token' => $token,
            'email' => $client->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('account.login'));

        $this->assertTrue(Hash::check('brand-new-pass', $client->fresh()->password));
    }
}
