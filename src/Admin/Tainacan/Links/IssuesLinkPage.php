<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Links;

use TainacanJournalManager\Admin\Tainacan\CptLinkPage;
use TainacanJournalManager\Config;

class IssuesLinkPage extends CptLinkPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string { return 'tjm_issues_link'; }
    protected function get_cpt(): string       { return Config::CPT_ISSUE; }
    protected function get_label(): string     { return __('Issues', 'tainacan-journal-manager'); }
    protected function get_icon(): string      { return 'collection'; }
    protected function get_position(): int     { return 12; }
}
