<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component {
    public function mount()
    {
        return redirect()->route('admin.operators.index');
    }
}; ?>

<div></div>
