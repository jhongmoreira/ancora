<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ShareLayout extends Component
{
    public function __construct(
        public mixed $patient = null,
        public ?string $token = null,
        public mixed $expiresAt = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.share');
    }
}
