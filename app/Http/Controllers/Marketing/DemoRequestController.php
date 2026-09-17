<?php

namespace App\Http\Controllers\Marketing;

use App\Domains\Marketing\Actions\CreateDemoRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemoRequest;
use Illuminate\Http\RedirectResponse;

class DemoRequestController extends Controller
{
    public function store(StoreDemoRequest $request, CreateDemoRequestAction $action): RedirectResponse
    {
        $action->handle($request->safe()->except(['consent', 'website']));

        return redirect()->to(route('home').'#demo')
            ->with('demo_request_success', 'Gracias. Recibimos tus datos y te contactaremos para agendar tu demo.');
    }
}
