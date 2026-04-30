<?php

/**
 * Enhanced Readability Auditor
 *
 * Provides full Flesch-Kincaid Reading Ease & Grade Level, passive voice detection,
 * transition word ratio, paragraph structure, and sentence length analysis.
 *
 * @package SEOAudit\Audits
 */

namespace SEOAudit\Audits;

class EnhancedReadabilityAuditor
{
    /**
     * Common English transition words used to assess content flow.
     */
    private const TRANSITION_WORDS = [
        'additionally', 'afterward', 'also', 'although', 'and', 'as a result',
        'because', 'before', 'besides', 'but', 'consequently', 'despite',
        'during', 'either', 'even though', 'finally', 'first', 'for example',
        'for instance', 'furthermore', 'generally', 'hence', 'however',
        'if', 'in addition', 'in contrast', 'in fact', 'in order to',
        'in particular', 'indeed', 'instead', 'later', 'likewise',
        'meanwhile', 'moreover', 'nevertheless', 'next', 'nonetheless',
        'on the contrary', 'on the other hand', 'otherwise', 'overall',
        'previously', 'similarly', 'since', 'so', 'specifically',
        'subsequently', 'such as', 'then', 'therefore', 'thus',
        'to begin with', 'to conclude', 'to summarize', 'ultimately',
        'whereas', 'while', 'yet',
    ];

    /**
     * Common passive voice auxiliary verbs.
     */
    private const PASSIVE_AUX = [
        'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'get', 'got', 'gets',
    ];

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    /**
     * Full readability analysis of the given HTML/plain content.
     *
     * @param  string $content Raw HTML or plain text.
     * @return array
     */
    public function analyze_content(string $content): array
    {
        $text = wp_strip_all_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (empty($text)) {
            return $this->empty_result();
        }

        $sentences   = $this->split_sentences($text);
        $words       = $this->split_words($text);
        $paragraphs  = $this->split_paragraphs($text);

        $word_count     = count($words);
        $sentence_count = max(1, count($sentences));
        $para_count     = max(1, count($paragraphs));

        // Core metrics
        $avg_words_per_sentence = round($word_count / $sentence_count, 1);
        $syllable_count         = $this->count_syllables_text($words);
        $avg_syllables_per_word = $word_count > 0
            ? round($syllable_count / $word_count, 2)
            : 0;

        // Flesch Reading Ease (0-100; higher = easier)
        $flesch_ease = $this->flesch_reading_ease(
            $word_count,
            $sentence_count,
            $syllable_count
        );

        // Flesch-Kincaid Grade Level
        $flesch_grade = $this->flesch_kincaid_grade(
            $word_count,
            $sentence_count,
            $syllable_count
        );

        // Passive voice
        $passive_count = $this->count_passive_voice($sentences);
        $passive_ratio = round(($passive_count / $sentence_count) * 100, 1);

        // Transition words
        $transition_sentence_count = $this->count_transition_sentences($sentences);
        $transition_ratio          = round(($transition_sentence_count / $sentence_count) * 100, 1);

        // Long sentences (> 25 words)
        $long_sentences = array_filter($sentences, fn($s) => str_word_count($s) > 25);
        $long_sentence_ratio = round((count($long_sentences) / $sentence_count) * 100, 1);

        // Paragraph length warning
        $long_paragraphs = array_filter(
            $paragraphs,
            fn($p) => str_word_count($p) > 150
        );

        // Grade label
        $grade_label = $this->grade_label($flesch_ease);

        // Scores / pass-fail
        $ease_passed       = $flesch_ease >= 60;
        $passive_passed    = $passive_ratio <= 10;
        $transition_passed = $transition_ratio >= 30;
        $long_sent_passed  = $long_sentence_ratio <= 25;

        return [
            'flesch_reading_ease'     => round($flesch_ease, 1),
            'flesch_kincaid_grade'    => round($flesch_grade, 1),
            'grade_label'             => $grade_label,
            'word_count'              => $word_count,
            'sentence_count'          => $sentence_count,
            'paragraph_count'         => $para_count,
            'avg_words_per_sentence'  => $avg_words_per_sentence,
            'avg_syllables_per_word'  => $avg_syllables_per_word,
            'passive_voice_count'     => $passive_count,
            'passive_voice_percent'   => $passive_ratio,
            'transition_word_count'   => $transition_sentence_count,
            'transition_word_percent' => $transition_ratio,
            'long_sentence_count'     => count($long_sentences),
            'long_sentence_percent'   => $long_sentence_ratio,
            'long_paragraph_count'    => count($long_paragraphs),
            'complexity_score'        => $flesch_ease < 50 ? 'High' : ($flesch_ease < 70 ? 'Medium' : 'Low'),
            'checks'                  => [
                'ease_passed'          => $ease_passed,
                'passive_passed'       => $passive_passed,
                'transition_passed'    => $transition_passed,
                'long_sentence_passed' => $long_sent_passed,
            ],
            'recommendations'         => $this->build_recommendations(
                $ease_passed,
                $passive_passed,
                $passive_ratio,
                $transition_passed,
                $transition_ratio,
                $long_sent_passed,
                $long_sentence_ratio,
                count($long_paragraphs)
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // SCORING FORMULAS
    // -------------------------------------------------------------------------

    private function flesch_reading_ease(int $words, int $sentences, int $syllables): float
    {
        if ($words === 0 || $sentences === 0) return 0.0;
        return 206.835
            - (1.015  * ($words / $sentences))
            - (84.6   * ($syllables / $words));
    }

    private function flesch_kincaid_grade(int $words, int $sentences, int $syllables): float
    {
        if ($words === 0 || $sentences === 0) return 0.0;
        return (0.39  * ($words / $sentences))
             + (11.8  * ($syllables / $words))
             - 15.59;
    }

    // -------------------------------------------------------------------------
    // SYLLABLE COUNTING
    // -------------------------------------------------------------------------

    /**
     * Estimate total syllable count across an array of words.
     */
    private function count_syllables_text(array $words): int
    {
        $total = 0;
        foreach ($words as $w) {
            $total += $this->syllable_count($w);
        }
        return max($total, 1);
    }

    /**
     * Estimate syllable count for a single word.
     * Uses a heuristic approach accurate to ~85–90%.
     */
    private function syllable_count(string $word): int
    {
        $word = strtolower(preg_replace('/[^a-zA-Z]/', '', $word));
        if (strlen($word) <= 3) return 1;

        $word    = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word    = preg_replace('/^y/', '', $word);
        $matches = preg_match_all('/[aeiouy]{1,2}/', $word);

        return max(1, (int) $matches);
    }

    // -------------------------------------------------------------------------
    // PASSIVE VOICE
    // -------------------------------------------------------------------------

    /**
     * Count sentences containing passive voice constructs.
     * Pattern: auxiliary verb + past participle (regular: *ed).
     */
    private function count_passive_voice(array $sentences): int
    {
        $count   = 0;
        $aux_pat = implode('|', self::PASSIVE_AUX);
        // Match: (auxiliary) + optional adverb + (past participle ending in -ed)
        $pattern = '/\b(' . $aux_pat . ')\b\s+(?:\w+ly\s+)?(\w+ed)\b/i';

        foreach ($sentences as $sentence) {
            if (preg_match($pattern, $sentence)) {
                $count++;
            }
        }
        return $count;
    }

    // -------------------------------------------------------------------------
    // TRANSITION WORDS
    // -------------------------------------------------------------------------

    /**
     * Count sentences that begin with or contain a transition word/phrase.
     */
    private function count_transition_sentences(array $sentences): int
    {
        $count = 0;
        foreach ($sentences as $sentence) {
            $sentence_lower = strtolower(trim($sentence));
            foreach (self::TRANSITION_WORDS as $tw) {
                if (
                    str_starts_with($sentence_lower, $tw . ' ')
                    || str_starts_with($sentence_lower, $tw . ',')
                    || preg_match('/\b' . preg_quote($tw, '/') . '\b/i', $sentence)
                ) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }

    // -------------------------------------------------------------------------
    // TEXT SPLITTING
    // -------------------------------------------------------------------------

    private function split_sentences(string $text): array
    {
        $raw = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($raw, fn($s) => str_word_count($s) > 0);
    }

    private function split_words(string $text): array
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($words, fn($w) => preg_match('/[a-zA-Z]/', $w));
    }

    private function split_paragraphs(string $text): array
    {
        $raw = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($raw, fn($p) => strlen(trim($p)) > 0);
    }

    // -------------------------------------------------------------------------
    // LABELS & RECOMMENDATIONS
    // -------------------------------------------------------------------------

    private function grade_label(float $ease): string
    {
        if ($ease >= 90) return __('Very Easy (5th grade)', 'seo-audit');
        if ($ease >= 80) return __('Easy (6th grade)', 'seo-audit');
        if ($ease >= 70) return __('Fairly Easy (7th grade)', 'seo-audit');
        if ($ease >= 60) return __('Standard (8th–9th grade)', 'seo-audit');
        if ($ease >= 50) return __('Fairly Difficult (10th–12th grade)', 'seo-audit');
        if ($ease >= 30) return __('Difficult (College)', 'seo-audit');
        return __('Very Confusing (Graduate)', 'seo-audit');
    }

    private function build_recommendations(
        bool  $ease_passed,
        bool  $passive_passed,
        float $passive_ratio,
        bool  $transition_passed,
        float $transition_ratio,
        bool  $long_sent_passed,
        float $long_sent_ratio,
        int   $long_paras
    ): array {
        $recs = [];

        if (!$ease_passed) {
            $recs[] = __('Simplify your language. Aim for a Flesch score ≥ 60 (Standard/8th grade) for broad audiences.', 'seo-audit');
        }
        if (!$passive_passed) {
            $recs[] = sprintf(
                __('%s%% of sentences use passive voice. Keep it below 10%% — rewrite these to active voice.', 'seo-audit'),
                $passive_ratio
            );
        }
        if (!$transition_passed) {
            $recs[] = sprintf(
                __('Only %s%% of sentences use transition words (target ≥ 30%%). Add connectors like "however", "therefore", "in addition".', 'seo-audit'),
                $transition_ratio
            );
        }
        if (!$long_sent_passed) {
            $recs[] = sprintf(
                __('%s%% of sentences are longer than 25 words. Break long sentences up for easier reading.', 'seo-audit'),
                $long_sent_ratio
            );
        }
        if ($long_paras > 0) {
            $recs[] = sprintf(
                __('%d paragraph(s) exceed 150 words. Split them into smaller, focused blocks.', 'seo-audit'),
                $long_paras
            );
        }

        return $recs;
    }

    private function empty_result(): array
    {
        return [
            'flesch_reading_ease'     => 0.0,
            'flesch_kincaid_grade'    => 0.0,
            'grade_label'             => __('No content', 'seo-audit'),
            'word_count'              => 0,
            'sentence_count'          => 0,
            'paragraph_count'         => 0,
            'avg_words_per_sentence'  => 0,
            'avg_syllables_per_word'  => 0,
            'passive_voice_count'     => 0,
            'passive_voice_percent'   => 0,
            'transition_word_count'   => 0,
            'transition_word_percent' => 0,
            'long_sentence_count'     => 0,
            'long_sentence_percent'   => 0,
            'long_paragraph_count'    => 0,
            'complexity_score'        => 'N/A',
            'checks'                  => [],
            'recommendations'         => [],
        ];
    }
}
