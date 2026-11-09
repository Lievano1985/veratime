<?php

namespace App\Livewire\Personal;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\GeneratePersonalKioskPinAction;
use App\Domains\Workers\Actions\ResolvePersonalWorkerAction;
use Livewire\Component;

class KioskCredential extends Component
{
    public bool $showPanel = false;

    public string $currentPassword = '';

    public function openPanel(): void
    {
        $this->personalWorker();
        $this->reset('currentPassword');
        $this->resetValidation();
        $this->dispatch('personal-kiosk-pin-clear');
        $this->showPanel = true;
    }

    public function closePanel(): void
    {
        $this->reset('currentPassword', 'showPanel');
        $this->resetValidation();
        $this->dispatch('personal-kiosk-pin-clear');
    }

    public function generatePin(GeneratePersonalKioskPinAction $action): void
    {
        $this->dispatch('personal-kiosk-pin-clear');
        try {
            $this->validate(['currentPassword' => ['required', 'string', 'max:255']]);
            $company = app(CurrentCompany::class)->get();
            abort_unless($company, 403);
            $result = $action->handle(auth()->user(), $company, $this->currentPassword);
            $this->dispatch('personal-kiosk-pin-generated', code: $result['code'], pin: $result['pin']);
        } finally {
            $this->reset('currentPassword');
        }
    }

    private function personalWorker()
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($company && auth()->check() && auth()->user()->belongsToCompany($company), 403);

        return app(ResolvePersonalWorkerAction::class)->handle(auth()->user(), $company);
    }

    public function render()
    {
        $worker = $this->personalWorker();

        return view('livewire.personal.kiosk-credential', [
            'worker' => $worker, 'credential' => $worker->credential()->where('company_id', $worker->company_id)->first(),
        ]);
    }
}
