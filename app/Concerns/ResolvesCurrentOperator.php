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

    /**
     * Whether the active operator's plan includes a feature.
     */
    public function operatorHasFeature(string $featureKey): bool
    {
        return $this->currentOperator?->hasFeature($featureKey) ?? false;
    }

    /**
     * Stop an action the operator's plan does not include.
     *
     * Hiding a control in the UI is not authorization; Livewire actions remain
     * callable by anyone who knows the component and method name.
     */
    public function authorizeFeature(string $featureKey): void
    {
        abort_unless($this->operatorHasFeature($featureKey), 403, __('Your current plan does not include this feature.'));
    }

    /**
     * Stop an action the signed-in teammate is not allowed to do.
     */
    public function authorizeAbility(string $ability): void
    {
        abort_unless(
            Auth::user()?->canOperate($this->currentOperator, $ability),
            403,
            __('You do not have permission to do this.'),
        );
    }
}
