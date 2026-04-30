<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Links;

use TainacanJournalManager\Admin\Tainacan\CptLinkPage;
use TainacanJournalManager\Config;

class ReviewsLinkPage extends CptLinkPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string { return 'tjm_reviews_link'; }
    protected function get_cpt(): string       { return Config::CPT_REVIEW; }
    protected function get_label(): string     { return __('Reviews', 'tainacan-journal-manager'); }
    protected function get_icon(): string      { return 'approved'; }
    protected function get_position(): int     { return 11; }
}
