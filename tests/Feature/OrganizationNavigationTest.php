<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_navigate_both_groups_with_the_current_page_highlighted(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        foreach ([
            'companies.index' => 'Estructura de organización',
            'centers.index' => 'Estructura de organización',
            'organization.units' => 'Estructura de organización',
            'workers.index' => 'Gestión de trabajadores',
            'organization.assignments' => 'Gestión de trabajadores',
        ] as $route => $label) {
            $html = $this->get(route($route))->assertOk()->getContent();
            preg_match('/<nav aria-label="'.preg_quote($label, '/').'"[^>]*>(.*?)<\/nav>/s', $html, $matches);
            $this->assertNotEmpty($matches[1] ?? null);
            $nav = $matches[1];
            $this->assertMatchesRegularExpression('/href="'.preg_quote(route($route), '/').'"[^>]*aria-current="page"/', $nav);
            $routes = $label === 'Estructura de organización'
                ? ['companies.index', 'centers.index', 'organization.units']
                : ['workers.index', 'organization.assignments'];
            foreach ($routes as $destination) {
                $this->assertStringContainsString(route($destination), $nav);
            }
            $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
        }
    }
}
