<?php

namespace App\Services\Ai;

interface AiAnalysisProvider
{
    /** @param array<string,mixed> $evidence @return array<string,mixed> */
    public function review(array $evidence): array;
}
