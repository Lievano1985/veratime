<?php

use App\Domains\Workers\Actions\LinkUserToWorkerAction;
use App\Domains\Workers\Actions\RevokeUserWorkerLinkAction;
use App\Models\Company;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;

it('keeps the worker link scoped to one active user and revocable without deleting history', function (): void {
    $company = Company::factory()->create();
    $firstUser = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $secondUser = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $link = app(LinkUserToWorkerAction::class)->handle($company, $firstUser, $worker);

    expect(fn () => app(LinkUserToWorkerAction::class)->handle($company, $secondUser, $worker))
        ->toThrow(InvalidArgumentException::class);

    expect(app(RevokeUserWorkerLinkAction::class)->handle($company, $firstUser))->toBeTrue();

    $this->assertDatabaseHas('user_worker_links', [
        'id' => $link->id,
        'status' => 'revoked',
    ]);
});
