<?php

declare(strict_types=1);

namespace TainacanJournalManager\Review;

use TainacanJournalManager\Config;

/**
 * Per-journal configuration of the peer review form.
 *
 * The journal can configure which sections appear in the form
 * (default sections always include: comments-to-author,
 * comments-to-editor, recommendation). Optional sections
 * can be enabled per journal.
 *
 * Storage: journal post meta `_tjm_review_form_config` (array of section keys).
 *
 * Sections recognized:
 *   - originality
 *   - methodology
 *   - clarity
 *   - relevance
 *   - references
 *   - ethics
 *
 * Each optional section produces a free-text comment field on the parecer form,
 * NOT a numeric score (we explicitly avoid aggregate scoring per CLAUDE.md).
 */
final class ReviewFormConfig
{
    public const SECTION_ORIGINALITY = 'originality';
    public const SECTION_METHODOLOGY = 'methodology';
    public const SECTION_CLARITY     = 'clarity';
    public const SECTION_RELEVANCE   = 'relevance';
    public const SECTION_REFERENCES  = 'references';
    public const SECTION_ETHICS      = 'ethics';

    public const ALL_SECTIONS = [
        self::SECTION_ORIGINALITY,
        self::SECTION_METHODOLOGY,
        self::SECTION_CLARITY,
        self::SECTION_RELEVANCE,
        self::SECTION_REFERENCES,
        self::SECTION_ETHICS,
    ];

    private const META_KEY = '_tjm_review_form_config';

    /**
     * @return string[] Enabled optional section keys.
     */
    public static function get_for_journal(int $journal_id): array
    {
        if ($journal_id <= 0) {
            return [];
        }
        $raw = get_post_meta($journal_id, self::META_KEY, true);
        if (! is_array($raw)) {
            return [];
        }
        return array_values(array_filter($raw, fn($s) => in_array($s, self::ALL_SECTIONS, true)));
    }

    /**
     * @param string[] $sections
     */
    public static function set_for_journal(int $journal_id, array $sections): void
    {
        if ($journal_id <= 0) {
            return;
        }
        $clean = array_values(array_unique(array_filter($sections, fn($s) => in_array($s, self::ALL_SECTIONS, true))));
        update_post_meta($journal_id, self::META_KEY, $clean);
    }

    public static function label(string $section): string
    {
        return match ($section) {
            self::SECTION_ORIGINALITY => __('Originality', 'tainacan-journal-manager'),
            self::SECTION_METHODOLOGY => __('Methodology', 'tainacan-journal-manager'),
            self::SECTION_CLARITY     => __('Clarity and writing', 'tainacan-journal-manager'),
            self::SECTION_RELEVANCE   => __('Relevance to the journal scope', 'tainacan-journal-manager'),
            self::SECTION_REFERENCES  => __('References and citations', 'tainacan-journal-manager'),
            self::SECTION_ETHICS      => __('Ethical considerations', 'tainacan-journal-manager'),
            default                   => $section,
        };
    }

    /**
     * Get sections relevant to a submission (delegates to its journal).
     *
     * @return string[]
     */
    public static function sections_for_submission(int $submission_id): array
    {
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        return self::get_for_journal($journal_id);
    }
}
