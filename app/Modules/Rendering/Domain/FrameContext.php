<?php

namespace App\Modules\Rendering\Domain;

use App\Modules\Segmentation\DTO\Segment;

class FrameContext
{
    /**
     * GD image resource/object.
     *
     * @var \GdImage|resource
     */
    public $image;

    /**
     * Width of the canvas in pixels.
     */
    public int $width;

    /**
     * Height of the canvas in pixels.
     */
    public int $height;

    /**
     * Layout configurations and computed bounds.
     */
    public array $layoutData;

    /**
     * The active Arabic segment being rendered.
     */
    public ?Segment $arabicSegment;

    /**
     * @param \GdImage|resource $image
     */
    public function __construct(
        $image,
        int $width,
        int $height,
        array $layoutData,
        ?Segment $arabicSegment = null
    ) {
        $this->image = $image;
        $this->width = $width;
        $this->height = $height;
        $this->layoutData = $layoutData;
        $this->arabicSegment = $arabicSegment;
    }
}
