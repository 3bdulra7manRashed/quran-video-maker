<?php

namespace App\Modules\Rendering\Contracts;

use App\Modules\Rendering\Domain\FrameContext;

interface RenderLayerInterface
{
    public function render(FrameContext $context): void;
}
