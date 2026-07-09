<?php

use App\Domains\Tenancy\Actions\SetCurrentCompanyAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $companyId = null;

    public function mount(CurrentCompany $currentCompany): void
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
        return [
            'companies' => auth()->user()
                ->activeCompanies()
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="space-y-2 px-2 py-3">
    <label for="company-switcher" class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
        Empresa activa
    </label>

    <select
        id="company-switcher"
        wire:model.live="companyId"
        class="w-full rounded-md border border-zinc-200 bg-white px-2 py-1.5 text-sm text-zinc-900 shadow-xs outline-hidden transition focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
    >
        @foreach ($companies as $company)
            <option value="{{ $company->id }}">{{ $company->name }}</option>
        @endforeach
    </select>

    @error('companyId')
        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
