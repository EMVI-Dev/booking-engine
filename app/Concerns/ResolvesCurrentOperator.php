<?php

namespace App\Concerns;

use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ResolvesCurrentOperator
{
    /**
     * Resolve the authenticated user's active operator for the current session.
     */
    #[Computed]
    public function currentOperator(): ?Operator
    {
        return Auth::user()?->currentOperator();
    }
}
