<?php
/**
 * Role management UI.
 *
 * @var \WP_User[] $users
 * @var \WP_Post[] $journals
 * @var string[]   $all_roles
 * @var bool       $is_admin
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Roles\PluginRole;

$role_label = static function (string $role): string {
    return match ($role) {
        PluginRole::JOURNAL_MANAGER     => __('Journal manager', 'tainacan-journal-manager'),
        PluginRole::EDITOR_CHIEF        => __('Editor in chief', 'tainacan-journal-manager'),
        PluginRole::EDITOR_SECTION      => __('Section editor', 'tainacan-journal-manager'),
        PluginRole::AUTHOR              => __('Author', 'tainacan-journal-manager'),
        PluginRole::REVIEWER            => __('Reviewer', 'tainacan-journal-manager'),
        PluginRole::COPYEDITOR          => __('Copyeditor', 'tainacan-journal-manager'),
        PluginRole::LAYOUT_EDITOR       => __('Layout editor', 'tainacan-journal-manager'),
        PluginRole::READER              => __('Reader', 'tainacan-journal-manager'),
        PluginRole::ADMIN_INSTITUTIONAL => __('Institutional admin', 'tainacan-journal-manager'),
        default                         => $role,
    };
};
?>
<div class="tjm-portal tjm-roles-mgmt">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Role management', 'tainacan-journal-manager'); ?></h2>
    </header>

    <div class="tjm-message" id="tjm-roles-message"></div>

    <p class="tjm-text-muted">
        <?php esc_html_e('A user can hold multiple global roles AND different roles per journal. Save buttons apply changes immediately.', 'tainacan-journal-manager'); ?>
    </p>

    <div class="tjm-section">
        <h3><?php esc_html_e('Pick a user', 'tainacan-journal-manager'); ?></h3>
        <div class="tjm-field">
            <label for="tjm-roles-user"><?php esc_html_e('User', 'tainacan-journal-manager'); ?></label>
            <select id="tjm-roles-user">
                <option value=""><?php esc_html_e('— search a user from the list below or pick by ID —', 'tainacan-journal-manager'); ?></option>
                <?php foreach ($users as $u) : ?>
                    <option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html(($u->display_name ?: $u->user_login) . ' (' . $u->user_email . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="tjm-text-muted">
            <?php esc_html_e('Or look up by ID:', 'tainacan-journal-manager'); ?>
            <input type="number" id="tjm-roles-user-id" min="1" placeholder="<?php echo esc_attr__('User ID', 'tainacan-journal-manager'); ?>">
            <button type="button" class="tjm-btn tjm-btn--secondary tjm-btn--sm" id="tjm-roles-user-load"><?php esc_html_e('Load', 'tainacan-journal-manager'); ?></button>
        </p>
    </div>

    <div id="tjm-roles-editor" hidden>
        <?php if ($is_admin) : ?>
        <div class="tjm-section">
            <h3><?php esc_html_e('Global roles', 'tainacan-journal-manager'); ?></h3>
            <p class="tjm-text-muted"><?php esc_html_e('Apply across all journals. Editable only by institutional administrators.', 'tainacan-journal-manager'); ?></p>
            <div class="tjm-roles-checkboxes" id="tjm-global-roles">
                <?php foreach ($all_roles as $role) : ?>
                    <label class="tjm-checkbox">
                        <input type="checkbox" value="<?php echo esc_attr($role); ?>">
                        <span><?php echo esc_html($role_label($role)); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="save-global-roles"><?php esc_html_e('Save global roles', 'tainacan-journal-manager'); ?></button>
        </div>
        <?php endif; ?>

        <div class="tjm-section">
            <h3><?php esc_html_e('Per-journal roles', 'tainacan-journal-manager'); ?></h3>
            <div class="tjm-field">
                <label for="tjm-roles-journal"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></label>
                <select id="tjm-roles-journal">
                    <option value=""><?php esc_html_e('— choose a journal —', 'tainacan-journal-manager'); ?></option>
                    <?php foreach ($journals as $j) : ?>
                        <option value="<?php echo (int) $j->ID; ?>"><?php echo esc_html((string) $j->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tjm-roles-checkboxes" id="tjm-journal-roles">
                <?php foreach ($all_roles as $role) : ?>
                    <label class="tjm-checkbox">
                        <input type="checkbox" value="<?php echo esc_attr($role); ?>">
                        <span><?php echo esc_html($role_label($role)); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="save-journal-roles"><?php esc_html_e('Save journal roles', 'tainacan-journal-manager'); ?></button>
        </div>
    </div>
</div>

<script type="application/json" id="tjm-roles-data">
<?php
$payload = ['users' => []];
foreach ($users as $u) {
    $payload['users'][(int) $u->ID] = [
        'global'  => PluginRole::get_roles((int) $u->ID),
        'journal' => PluginRole::get_journal_roles_map((int) $u->ID),
    ];
}
echo wp_json_encode($payload);
?>
</script>
