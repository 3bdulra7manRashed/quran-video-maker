<?php

namespace App\Enums;

enum GeneratorType: string
{
    case MANUAL = 'manual';
    case GEMINI = 'gemini';
    case OPENAI = 'openai';
    case CLAUDE = 'claude';
}
