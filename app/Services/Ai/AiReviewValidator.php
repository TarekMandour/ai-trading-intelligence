<?php

namespace App\Services\Ai;

class AiReviewValidator
{
    private const ASSESSMENTS = ['SUPPORT', 'WAIT_FOR_CONFIRMATION', 'WEAKEN', 'ABSTAIN'];

    private const ALIGNMENTS = ['ALIGNED', 'PARTIAL', 'CONFLICTED', 'INSUFFICIENT'];

    private const CONFIDENCE_LABELS = ['LOW', 'MODERATE', 'HIGH'];

    /**
     * @param array<string,mixed> $review
     * @param array<int,string> $evidenceIds
     */
    public function validate(array $review, array $evidenceIds): bool
    {
        $expectedKeys = [
            'assessment',
            'timeframe_alignment',
            'confidence_label',
            'supporting_evidence_ids',
            'contradicting_evidence_ids',
            'missing_confirmations',
            'risk_observations',
            'rationale',
        ];

        $actualKeys = array_keys($review);
        sort($expectedKeys);
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys) {
            return false;
        }

        if (! in_array($review['assessment'], self::ASSESSMENTS, true)
            || ! in_array($review['timeframe_alignment'], self::ALIGNMENTS, true)
            || ! in_array($review['confidence_label'], self::CONFIDENCE_LABELS, true)) {
            return false;
        }

        if (! $this->isEvidenceIdList($review['supporting_evidence_ids'], $evidenceIds)
            || ! $this->isEvidenceIdList($review['contradicting_evidence_ids'], $evidenceIds)) {
            return false;
        }

        if (array_intersect($review['supporting_evidence_ids'], $review['contradicting_evidence_ids']) !== []) {
            return false;
        }

        if (! $this->isBoundedTextList($review['missing_confirmations'])
            || ! $this->isBoundedTextList($review['risk_observations'])) {
            return false;
        }

        return is_string($review['rationale'])
            && trim($review['rationale']) !== ''
            && mb_strlen($review['rationale']) <= 1000;
    }

    /**
     * @param mixed $values
     * @param array<int,string> $allowedIds
     */
    private function isEvidenceIdList(mixed $values, array $allowedIds): bool
    {
        if (! is_array($values) || count($values) > count($allowedIds)) {
            return false;
        }

        if (count($values) !== count(array_unique($values, SORT_REGULAR))) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || ! in_array($value, $allowedIds, true)) {
                return false;
            }
        }

        return true;
    }

    private function isBoundedTextList(mixed $values): bool
    {
        if (! is_array($values) || count($values) > 5) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 240) {
                return false;
            }
        }

        return true;
    }
}
