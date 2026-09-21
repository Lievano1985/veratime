<?php

namespace App\Domains\Workers\Actions;

use App\Domains\Products\Support\ProductAccess;
use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkerLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticatePersonalMobileUserAction
{
    public function __construct(
        private readonly ProductAccess $productAccess,
    ) {}

    /**
     * @return array{user: User, link: UserWorkerLink|null, companies: list<array{id: string, name: string}>}
     */
    public function handle(string $email, string $password, ?int $companyId = null): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas no son válidas.',
            ]);
        }

        $links = $this->eligibleLinks($user);

        if ($links->isEmpty()) {
            throw new HttpException(403, 'La cuenta no tiene acceso móvil personal activo en VERA Time.');
        }

        if ($companyId !== null) {
            $link = $links->first(fn (UserWorkerLink $link): bool => $link->company_id === $companyId);

            if (! $link) {
                throw ValidationException::withMessages([
                    'company_id' => 'La empresa seleccionada no está disponible para esta cuenta.',
                ]);
            }

            return ['user' => $user, 'link' => $link, 'companies' => []];
        }

        if ($links->count() === 1) {
            return ['user' => $user, 'link' => $links->first(), 'companies' => []];
        }

        return [
            'user' => $user,
            'link' => null,
            'companies' => $links
                ->map(fn (UserWorkerLink $link): array => [
                    'id' => (string) $link->company_id,
                    'name' => $link->company->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return Collection<int, UserWorkerLink> */
    private function eligibleLinks(User $user): Collection
    {
        return UserWorkerLink::query()
            ->with(['worker', 'company.customerAccount'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('worker', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(function (UserWorkerLink $link) use ($user): bool {
                /** @var Company|null $company */
                $company = $link->company;

                return $company !== null
                    && $link->worker?->company_id === $company->id
                    && $user->belongsToCompany($company)
                    && $this->productAccess->companyHasOperationalProduct($company, 'time');
            })
            ->values();
    }
}
