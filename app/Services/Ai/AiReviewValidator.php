<?php

namespace App\Services\Ai;

class AiReviewValidator
{
    /** @param array<string,mixed> $review @param array<int,string> $evidenceIds */
    public function validate(array $review, array $evidenceIds): bool
    {
        if (! in_array($review['assessment'] ?? null, ['SUPPORT', 'WAIT_FOR_CONFIRMATION', 'WEAKEN', 'ABSTAIN'], true)) {
            return false;
        }
        if (! in_array($review['timeframe_alignment'] ?? null, ['ALIGNED', 'PARTIAL', 'CONFLICTED', 'INSUFFICIENT'], true)) {
            return false;
        }
        foreach (array_merge($review['supporting_evidence_ids'] ?? [], $review['contradicting_evidence_ids'] ?? []) as $id) {
            if (! in_array($id, $evidenceIds, true)) {
                return false;
            }
        }

        return is_string($review['rationale'] ?? null) && strlen($review['rationale']) <= 1000;
    }
}
