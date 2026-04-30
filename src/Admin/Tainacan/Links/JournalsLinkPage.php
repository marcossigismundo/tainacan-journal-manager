<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Links;

use TainacanJournalManager\Admin\Tainacan\CptLinkPage;
use TainacanJournalManager\Config;

class JournalsLinkPage extends CptLinkPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string { return 'tjm_journals_link'; }
    protected function get_cpt(): string       { return Config::CPT_JOURNAL; }
    protected function get_label(): string     { return __('Journals', 'tainacan-journal-manager'); }
    protected function get_icon(): string      { return 'repository'; }
    protected function get_position(): int     { return 9; }
}
