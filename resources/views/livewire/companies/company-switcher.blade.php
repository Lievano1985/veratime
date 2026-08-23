<?php

use App\Domains\Tenancy\Actions\SetCurrentCompanyAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $companyId = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        $this->companyId = $currentCompany->get()?->id;
    }

    #[On('companies-updated')]
    public function refreshCompanies(CurrentCompany $currentCompany): void
    {
        $this->companyId = $currentCompany->get()?->id;
    }

    public function updatedCompanyId(): void
    {
        $this->switchCompany(app(SetCurrentCompanyAction::class));
    }

    public function switchCompany(SetCurrentCompanyAction $action): void
    {
        $validated = $this->validate([
            'companyId' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($validated['companyId']);

        $action->handle(auth()->user(), $company);

        $this->redirect(url()->previous() ?: route('dashboard'), navigate: true);
    }

    public function with(): array
    {
        $companies = auth()->user()
            ->activeCompanies()
            ->orderBy('name')
            ->get();

        return [
            'companies' => $companies,
            'showSelector' => $companies->count() > 0 && (auth()->user()->isSuperAdmin() || $companies->count() > 1),
            'singleCompany' => $companies->first(),
        ];
    }
}; ?>

<div class="space-y-2 px-2 py-3">
    <label for="company-switcher" class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
        Empresa activa
    </label>

    @if ($showSelector)
        <select
            id="company-switcher"
            wire:model.live="companyId"
            class="w-full min-w-0 truncate rounded-md border border-zinc-200 bg-white py-1.5 pl-2 pr-9 text-sm text-zinc-900 shadow-xs outline-hidden transition focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
        >
            @foreach ($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>

        @error('companyId')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    @elseif ($singleCompany)
        <div class="truncate rounded-md border border-zinc-700/60 bg-zinc-800/80 px-3 py-2 text-sm font-medium text-zinc-100">
            {{ $singleCompany->name }}
        </div>
    @else
        <div class="rounded-md border border-zinc-700/60 bg-zinc-800/80 px-3 py-2 text-xs font-medium text-zinc-300">
            Sin empresas activas
        </div>
    @endif
</div>
