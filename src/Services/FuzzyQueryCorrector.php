<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Services;

use Rakibdevs\AiShopbot\Contracts\ProductProvider;

/**
 * Corrects misspelled search queries using character similarity and
 * phonetic matching against the live product catalogue vocabulary.
 *
 * Example: "air dryer" → "air fryer"
 *          soundex("dryer")=D600, soundex("fryer")=F600 → close phonetic match
 */
class FuzzyQueryCorrector
{
    // Words shorter than this are left as-is — too many false positives on short tokens
    private const MIN_WORD_LENGTH = 3;

    // Minimum combined score to accept a correction (0–100 scale)
    private const MATCH_THRESHOLD = 58;

    // Phonetic bonus when soundex codes match
    private const PHONETIC_BONUS = 25;

    // Penalty per character of length difference (capped)
    private const LENGTH_PENALTY_PER_CHAR = 4;
    private const LENGTH_PENALTY_MAX      = 15;

    // Skip vocab candidates whose length differs by more than this
    private const MAX_LENGTH_DIFF = 4;

    private ?array $vocab = null;

    public function __construct(
        private readonly ProductProvider $productProvider
    ) {}

    /**
     * Attempt to correct misspelled words in a query.
     * Returns the corrected string, or an empty string if no corrections were made.
     */
    public function correct(string $query): string
    {
        $vocab = $this->getVocab();
        if (empty($vocab)) {
            return '';
        }

        $words      = preg_split('/\s+/', strtolower(trim($query)));
        $corrected  = [];
        $anyChanged = false;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z]/', '', $word);

            if (strlen($clean) <= self::MIN_WORD_LENGTH) {
                $corrected[] = $word;
                continue;
            }

            $best = $this->findBestMatch($clean, $vocab);

            if ($best !== $word) {
                $anyChanged = true;
            }

            $corrected[] = $best;
        }

        return $anyChanged ? implode(' ', $corrected) : '';
    }

    /**
     * Expose the vocab for testing or inspection.
     *
     * @return string[]
     */
    public function getVocab(): array
    {
        if ($this->vocab !== null) {
            return $this->vocab;
        }

        return $this->vocab = $this->buildVocab();
    }

    /**
     * Flush the cached vocab — useful after a product import or in tests.
     */
    public function flushVocab(): void
    {
        $this->vocab = null;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function findBestMatch(string $word, array $vocab): string
    {
        $best      = $word;
        $bestScore = 0;

        foreach ($vocab as $candidate) {
            if (abs(strlen($word) - strlen($candidate)) > self::MAX_LENGTH_DIFF) {
                continue;
            }

            $score = $this->score($word, $candidate);

            if ($score > $bestScore && $score > self::MATCH_THRESHOLD) {
                $bestScore = $score;
                $best      = $candidate;
            }
        }

        return $best;
    }

    private function score(string $a, string $b): float
    {
        similar_text($a, $b, $charSimilarity);

        $phonetic   = soundex($a) === soundex($b) ? self::PHONETIC_BONUS : 0;
        $lenPenalty = min(
            abs(strlen($a) - strlen($b)) * self::LENGTH_PENALTY_PER_CHAR,
            self::LENGTH_PENALTY_MAX
        );

        return $charSimilarity + $phonetic - $lenPenalty;
    }

    /**
     * Build a deduplicated word list from product names in the catalogue.
     * Uses featured() as a representative sample — cheap and provider-agnostic.
     *
     * @return string[]
     */
    private function buildVocab(): array
    {
        $vocab = [];

        foreach ($this->productProvider->featured(50) as $product) {
            foreach (preg_split('/[\s\-\/]+/', strtolower($product->name)) as $token) {
                $token = preg_replace('/[^a-z]/', '', $token);
                if (strlen($token) > self::MIN_WORD_LENGTH) {
                    $vocab[] = $token;
                }
            }
        }

        return array_values(array_unique($vocab));
    }
}