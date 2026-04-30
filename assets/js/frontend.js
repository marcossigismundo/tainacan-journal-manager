/**
 * Tainacan Journal Manager — Frontend
 *
 * Handles: login, submission wizard, editorial detail (assign + decide),
 * reviewer detail (accept/decline + parecer form), role management.
 */
(function ($) {
    'use strict';

    var config = window.tjmFrontend || {};

    $(document).ready(function () {
        bindLogin();
        bindWizardNew();
        bindWizard();
        bindEditorialDetail();
        bindReviewerDetail();
        bindRoleManagement();
        bindCopyeditingDetail();
        bindAuthorProof();
        bindIssuesMgmt();
    });

    // ── helpers ────────────────────────────────────────────────────
    function postAjax(action, data) {
        var payload = $.extend({ action: action, nonce: config.nonce }, data || {});
        return $.post(config.ajaxUrl, payload);
    }

    function showMsg($el, text, kind) {
        $el.removeClass('tjm-message--error tjm-message--success')
           .addClass('tjm-message--' + (kind || 'success'))
           .text(text)
           .show();
    }

    // ── login ──────────────────────────────────────────────────────
    function bindLogin() {
        $('#tjm-login-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $msg  = $('#tjm-login-message');
            $msg.removeClass('tjm-message--error tjm-message--success').hide();

            postAjax('tjm_login', {
                username: $form.find('[name="username"]').val(),
                password: $form.find('[name="password"]').val(),
                redirect_to: $form.find('[name="redirect_to"]').val()
            }).done(function (res) {
                if (res.success && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    showMsg($msg, res.data || config.i18n.error, 'error');
                }
            }).fail(function () { showMsg($msg, config.i18n.error, 'error'); });
        });
    }

    // ── new submission entry ──────────────────────────────────────
    function bindWizardNew() {
        var $btn = $('#tjm-new-create');
        if (! $btn.length) return;

        $btn.on('click', function () {
            var title      = ($('#tjm-new-title').val() || '').trim();
            var journal_id = parseInt($('#tjm-new-journal').val(), 10) || 0;
            var $msg = $('#tjm-new-message');

            if (! title || ! journal_id) {
                showMsg($msg, 'Title and journal are required.', 'error');
                return;
            }
            $btn.prop('disabled', true);

            postAjax('tjm_submission_create_draft', { title: title, journal_id: journal_id })
                .done(function (res) {
                    if (res.success && res.data && res.data.submission_id) {
                        window.location.href = '?submission=' + res.data.submission_id;
                    } else {
                        showMsg($msg, (res.data || config.i18n.error), 'error');
                        $btn.prop('disabled', false);
                    }
                }).fail(function () { showMsg($msg, config.i18n.error, 'error'); $btn.prop('disabled', false); });
        });
    }

    // ── multi-step wizard ─────────────────────────────────────────
    function bindWizard() {
        var $wiz = $('.tjm-wizard');
        if (! $wiz.length) return;

        var sid = $wiz.data('submission-id');
        var $msg = $wiz.find('.tjm-wizard-message');

        function gotoStep(n) {
            $wiz.find('.tjm-wizard-step').removeClass('is-active');
            $wiz.find('.tjm-wizard-pane').removeClass('is-active');
            $wiz.find('.tjm-wizard-step[data-step="' + n + '"]').addClass('is-active');
            $wiz.find('.tjm-wizard-pane[data-pane="' + n + '"]').addClass('is-active');
            $msg.hide();
        }

        // step click navigation
        $wiz.find('.tjm-wizard-step').on('click', function () {
            gotoStep($(this).data('step'));
        });

        $wiz.on('click', '[data-action="prev"]', function () {
            var cur = parseInt($wiz.find('.tjm-wizard-pane.is-active').data('pane'), 10);
            if (cur > 1) gotoStep(cur - 1);
        });
        $wiz.on('click', '[data-action="next"]', function () {
            var cur = parseInt($wiz.find('.tjm-wizard-pane.is-active').data('pane'), 10);
            if (cur < 5) gotoStep(cur + 1);
        });

        // ── Step 1: metadata ────────────────────────────────────
        $wiz.on('click', '[data-action="save-metadata"]', function () {
            var data = {
                submission_id: sid,
                title:      $('#tjm-w-title').val(),
                journal_id: $('#tjm-w-journal').val(),
                abstract:   $('#tjm-w-abstract').val(),
                keywords:   $('#tjm-w-keywords').val(),
                language:   $('#tjm-w-language').val(),
                references: $('#tjm-w-references').val(),
                funding:    $('#tjm-w-funding').val()
            };
            postAjax('tjm_submission_save_metadata', data)
                .done(function (res) {
                    if (res.success) { showMsg($msg, 'Saved.', 'success'); gotoStep(2); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        // ── Step 2: coauthors ───────────────────────────────────
        $wiz.on('click', '[data-action="add-author"]', function () {
            var html = ''
                + '<div class="tjm-coauthor-row" data-row>'
                + '  <div class="tjm-field"><label>Name</label><input type="text" data-field="name"></div>'
                + '  <div class="tjm-field"><label>Email</label><input type="email" data-field="email"></div>'
                + '  <div class="tjm-field"><label>Affiliation</label><input type="text" data-field="affiliation"></div>'
                + '  <div class="tjm-field"><label>ORCID iD</label><input type="text" data-field="orcid" placeholder="0000-0000-0000-0000"></div>'
                + '  <button type="button" class="tjm-btn tjm-btn--secondary tjm-btn--sm" data-action="remove-author">Remove</button>'
                + '</div>';
            $('#tjm-coauthors-list [data-empty]').remove();
            $('#tjm-coauthors-list').append(html);
        });

        $wiz.on('click', '[data-action="remove-author"]', function () {
            $(this).closest('[data-row]').remove();
        });

        $wiz.on('click', '[data-action="save-authors"]', function () {
            var coauthors = [];
            $('#tjm-coauthors-list [data-row]').each(function () {
                var $r = $(this);
                coauthors.push({
                    name:        ($r.find('[data-field="name"]').val() || '').trim(),
                    email:       ($r.find('[data-field="email"]').val() || '').trim(),
                    affiliation: ($r.find('[data-field="affiliation"]').val() || '').trim(),
                    orcid:       ($r.find('[data-field="orcid"]').val() || '').trim()
                });
            });
            postAjax('tjm_submission_save_authors', { submission_id: sid, coauthors: coauthors })
                .done(function (res) {
                    if (res.success) { showMsg($msg, 'Saved.', 'success'); gotoStep(3); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        // ── Step 3: file upload ─────────────────────────────────
        $wiz.on('click', '[data-action="upload-file"]', function () {
            var fileInput = document.getElementById('tjm-w-file');
            if (! fileInput || ! fileInput.files || ! fileInput.files.length) {
                showMsg($msg, 'Choose a file first.', 'error');
                return;
            }
            var fd = new FormData();
            fd.append('action', 'tjm_submission_upload_file');
            fd.append('nonce', config.nonce);
            fd.append('submission_id', sid);
            fd.append('manuscript', fileInput.files[0]);

            $.ajax({
                url: config.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false
            }).done(function (res) {
                if (res.success && res.data) {
                    var html = '<div class="tjm-notice">Current file: <a href="' + res.data.url + '" target="_blank" rel="noopener">'
                             + res.data.filename + '</a></div>';
                    $('#tjm-manuscript-current').html(html);
                    showMsg($msg, 'Uploaded.', 'success');
                    gotoStep(4);
                } else {
                    showMsg($msg, res.data || config.i18n.error, 'error');
                }
            }).fail(function () { showMsg($msg, config.i18n.error, 'error'); });
        });

        // ── Step 4: declarations ────────────────────────────────
        $wiz.on('click', '[data-action="save-declarations"]', function () {
            var pane = $wiz.find('[data-pane="4"]');
            var data = {
                submission_id: sid,
                original:  pane.find('[name="original"]').is(':checked')  ? 1 : 0,
                coi:       pane.find('[name="coi"]').is(':checked')       ? 1 : 0,
                copyright: pane.find('[name="copyright"]').is(':checked') ? 1 : 0,
                ethics:    pane.find('[name="ethics"]').is(':checked')    ? 1 : 0
            };
            postAjax('tjm_submission_save_declarations', data)
                .done(function (res) {
                    if (res.success) { showMsg($msg, 'Saved.', 'success'); gotoStep(5); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        // ── Step 5: finalize / withdraw ─────────────────────────
        $wiz.on('click', '[data-action="finalize"]', function () {
            if (! window.confirm(config.i18n.confirm_submit)) return;
            postAjax('tjm_submission_finalize', { submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.href = '?'; }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $wiz.on('click', '[data-action="withdraw"]', function () {
            if (! window.confirm('Withdraw this draft? This cannot be undone.')) return;
            postAjax('tjm_submission_withdraw', { submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.href = '?'; }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });
    }

    // ── editorial detail ──────────────────────────────────────────
    function bindEditorialDetail() {
        var $det = $('.tjm-editorial-detail');
        if (! $det.length) return;

        var sid  = $det.data('submission-id');
        var $msg = $('#tjm-editorial-message');

        $det.on('click', '[data-action="to-triage"]', function () {
            postAjax('tjm_editorial_to_triage', { submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="to-review"]', function () {
            postAjax('tjm_editorial_to_review', { submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="invite-reviewer"]', function () {
            var rid = parseInt($('#tjm-invite-reviewer').val(), 10) || 0;
            var deadline = $('#tjm-invite-deadline').val();
            if (! rid) { showMsg($msg, 'Choose a reviewer.', 'error'); return; }
            postAjax('tjm_editorial_invite_reviewer', {
                submission_id: sid, reviewer_id: rid, deadline: deadline
            }).done(function (res) {
                if (res.success) { window.location.reload(); }
                else { showMsg($msg, res.data || config.i18n.error, 'error'); }
            });
        });

        $det.on('click', '[data-action="record-decision"]', function () {
            var dec = $('#tjm-decision').val();
            var note = $('#tjm-decision-note').val();
            if (! dec) { showMsg($msg, 'Choose a decision.', 'error'); return; }
            if (! window.confirm(config.i18n.confirm_submit)) return;
            postAjax('tjm_editorial_record_decision', {
                submission_id: sid, decision: dec, justification: note
            }).done(function (res) {
                if (res.success) { window.location.reload(); }
                else { showMsg($msg, res.data || config.i18n.error, 'error'); }
            });
        });
    }

    // ── reviewer detail ───────────────────────────────────────────
    function bindReviewerDetail() {
        var $det = $('.tjm-reviewer-detail');
        if (! $det.length) return;

        var rid  = $det.data('review-id');
        var $msg = $('#tjm-review-message');

        $det.on('click', '[data-action="accept-review"]', function () {
            postAjax('tjm_review_accept', { review_id: rid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="decline-review"]', function () {
            $det.find('.tjm-decline-reason').prop('hidden', false);
        });

        $det.on('click', '[data-action="confirm-decline"]', function () {
            var reason = $('#tjm-decline-reason').val();
            postAjax('tjm_review_decline', { review_id: rid, reason: reason })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="submit-review"]', function () {
            var sections = {};
            $det.find('textarea[data-section]').each(function () {
                sections[$(this).data('section')] = $(this).val();
            });

            var data = {
                review_id: rid,
                author_comments: $('#tjm-author-comments').val(),
                editor_comments: $('#tjm-editor-comments').val(),
                recommendation:  $('#tjm-recommendation').val(),
                sections: sections
            };
            if (! data.author_comments || ! data.recommendation) {
                showMsg($msg, 'Author comments and a recommendation are required.', 'error');
                return;
            }
            if (! window.confirm(config.i18n.confirm_submit)) return;

            postAjax('tjm_review_submit', data).done(function (res) {
                if (res.success) { window.location.reload(); }
                else { showMsg($msg, res.data || config.i18n.error, 'error'); }
            });
        });
    }

    // ── role management ───────────────────────────────────────────
    function bindRoleManagement() {
        var $page = $('.tjm-roles-mgmt');
        if (! $page.length) return;

        var rolesData = {};
        try { rolesData = JSON.parse(($('#tjm-roles-data').text() || '{}')); } catch (e) {}
        var users = (rolesData && rolesData.users) || {};

        var $msg = $('#tjm-roles-message');
        var $editor = $('#tjm-roles-editor');
        var currentUser = 0;

        function paintGlobalRoles(uid) {
            var roles = (users[uid] && users[uid].global) || [];
            $('#tjm-global-roles input[type="checkbox"]').each(function () {
                $(this).prop('checked', roles.indexOf($(this).val()) !== -1);
            });
        }

        function paintJournalRoles(uid, jid) {
            var roles = (users[uid] && users[uid].journal && users[uid].journal[jid]) || [];
            $('#tjm-journal-roles input[type="checkbox"]').each(function () {
                $(this).prop('checked', roles.indexOf($(this).val()) !== -1);
            });
        }

        function loadUser(uid) {
            currentUser = parseInt(uid, 10) || 0;
            if (! currentUser) { $editor.prop('hidden', true); return; }
            // Make sure there is an entry (might be empty for new lookup)
            if (! users[currentUser]) { users[currentUser] = { global: [], journal: {} }; }
            $editor.prop('hidden', false);
            paintGlobalRoles(currentUser);
            $('#tjm-roles-journal').val('');
            $('#tjm-journal-roles input[type="checkbox"]').prop('checked', false);
        }

        $('#tjm-roles-user').on('change', function () { loadUser($(this).val()); });
        $('#tjm-roles-user-load').on('click', function () { loadUser($('#tjm-roles-user-id').val()); });

        $('#tjm-roles-journal').on('change', function () {
            var jid = parseInt($(this).val(), 10) || 0;
            if (currentUser && jid) { paintJournalRoles(currentUser, jid); }
        });

        $page.on('click', '[data-action="save-global-roles"]', function () {
            if (! currentUser) return;
            var roles = [];
            $('#tjm-global-roles input[type="checkbox"]:checked').each(function () { roles.push($(this).val()); });

            postAjax('tjm_roles_set_global', { user_id: currentUser, roles: roles })
                .done(function (res) {
                    if (res.success) {
                        users[currentUser].global = roles;
                        showMsg($msg, 'Global roles saved.', 'success');
                    } else {
                        showMsg($msg, res.data || config.i18n.error, 'error');
                    }
                });
        });

        $page.on('click', '[data-action="save-journal-roles"]', function () {
            if (! currentUser) return;
            var jid = parseInt($('#tjm-roles-journal').val(), 10) || 0;
            if (! jid) { showMsg($msg, 'Choose a journal.', 'error'); return; }
            var roles = [];
            $('#tjm-journal-roles input[type="checkbox"]:checked').each(function () { roles.push($(this).val()); });

            postAjax('tjm_roles_set_journal', { user_id: currentUser, journal_id: jid, roles: roles })
                .done(function (res) {
                    if (res.success) {
                        if (! users[currentUser].journal) users[currentUser].journal = {};
                        if (roles.length) users[currentUser].journal[jid] = roles;
                        else delete users[currentUser].journal[jid];
                        showMsg($msg, 'Journal roles saved.', 'success');
                    } else {
                        showMsg($msg, res.data || config.i18n.error, 'error');
                    }
                });
        });
    }

    // ── copyediting + production detail ───────────────────────────
    function bindCopyeditingDetail() {
        var $det = $('.tjm-copyediting-detail');
        if (! $det.length) return;

        var sid  = $det.data('submission-id');
        var $msg = $('#tjm-prod-message');

        function uploadFile(action, fileEl, extras) {
            if (! fileEl || ! fileEl.files || ! fileEl.files.length) {
                showMsg($msg, 'Choose a file first.', 'error');
                return;
            }
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', config.nonce);
            fd.append('submission_id', sid);
            fd.append('file', fileEl.files[0]);
            for (var k in (extras || {})) {
                if (Object.prototype.hasOwnProperty.call(extras, k)) fd.append(k, extras[k]);
            }
            return $.ajax({ url: config.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false });
        }

        $det.on('click', '[data-action="upload-copyediting"]', function () {
            var note = $('#tjm-ce-note').val();
            var req = uploadFile('tjm_copyediting_upload', document.getElementById('tjm-ce-file'), { note: note });
            if (! req) return;
            req.done(function (res) {
                if (res.success) { window.location.reload(); }
                else { showMsg($msg, res.data || config.i18n.error, 'error'); }
            });
        });

        $det.on('click', '[data-action="notify-author"]', function () {
            postAjax('tjm_copyediting_notify_author', { submission_id: sid })
                .done(function (res) {
                    if (res.success) showMsg($msg, 'Author notified.', 'success');
                    else showMsg($msg, res.data || config.i18n.error, 'error');
                });
        });

        $det.on('click', '[data-action="copyediting-done"]', function () {
            if (! window.confirm(config.i18n.confirm_submit)) return;
            var note = $('#tjm-ce-note').val();
            postAjax('tjm_copyediting_to_production', { submission_id: sid, note: note })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="add-galley"]', function () {
            var format   = $('#tjm-galley-format').val();
            var label    = $('#tjm-galley-label').val();
            var language = $('#tjm-galley-language').val();
            var req = uploadFile('tjm_galley_add', document.getElementById('tjm-galley-file'), {
                format: format, label: label, language: language
            });
            if (! req) return;
            req.done(function (res) {
                if (res.success) { window.location.reload(); }
                else { showMsg($msg, res.data || config.i18n.error, 'error'); }
            });
        });

        $det.on('click', '[data-action="remove-galley"]', function () {
            var $row = $(this).closest('[data-att]');
            var att  = parseInt($row.data('att'), 10) || 0;
            if (! att) return;
            if (! window.confirm('Remove this galley?')) return;
            postAjax('tjm_galley_remove', { submission_id: sid, attachment_id: att })
                .done(function (res) {
                    if (res.success) { $row.remove(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="proof-request"]', function () {
            postAjax('tjm_proof_request', { submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $det.on('click', '[data-action="publish-article"]', function () {
            if (! window.confirm(config.i18n.confirm_submit)) return;
            postAjax('tjm_article_publish', { submission_id: sid })
                .done(function (res) {
                    if (res.success) {
                        showMsg($msg, 'Published. Item ID #' + res.data.item_id, 'success');
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        showMsg($msg, res.data || config.i18n.error, 'error');
                    }
                });
        });
    }

    // ── author proof approval (in submission-detail) ──────────────
    function bindAuthorProof() {
        var $box = $('.tjm-author-proof');
        if (! $box.length) return;

        var sid  = $box.data('submission-id');
        var $msg = $('#tjm-proof-message');

        $box.on('click', '[data-action="proof-approve"]', function () {
            if (! window.confirm(config.i18n.confirm_submit)) return;
            postAjax('tjm_proof_approve', { submission_id: sid, note: $('#tjm-proof-note').val() })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $box.on('click', '[data-action="proof-changes"]', function () {
            var note = $('#tjm-proof-note').val();
            if (! note) { showMsg($msg, 'Describe the changes needed.', 'error'); return; }
            postAjax('tjm_proof_request_changes', { submission_id: sid, note: note })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });
    }

    // ── issues management ─────────────────────────────────────────
    function bindIssuesMgmt() {
        var $page = $('.tjm-issues-mgmt');
        if (! $page.length) return;

        var $msg = $('#tjm-issues-message');

        $page.on('click', '[data-action="create-issue"]', function () {
            var $box = $(this).closest('.tjm-issues-create');
            var jid  = parseInt($box.data('journal-id'), 10) || 0;
            if (! jid) return;
            var data = {
                journal_id: jid,
                title:  $('#tjm-new-issue-title').val(),
                volume: $('#tjm-new-issue-volume').val(),
                number: $('#tjm-new-issue-number').val(),
                year:   $('#tjm-new-issue-year').val(),
                type:   $('#tjm-new-issue-type').val()
            };
            if (! data.title) { showMsg($msg, 'Title is required.', 'error'); return; }
            postAjax('tjm_issue_create', data)
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $page.on('click', '[data-action="assign-article"]', function () {
            var $card = $(this).closest('.tjm-issue-card');
            var iid   = parseInt($card.data('issue-id'), 10) || 0;
            var sid   = parseInt($card.find('.tjm-assign-select').val(), 10) || 0;
            if (! iid || ! sid) { showMsg($msg, 'Choose an article.', 'error'); return; }
            postAjax('tjm_issue_assign_article', { issue_id: iid, submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $page.on('click', '[data-action="unassign-article"]', function () {
            var $card = $(this).closest('.tjm-issue-card');
            var iid   = parseInt($card.data('issue-id'), 10) || 0;
            var sid   = parseInt($(this).data('submission-id'), 10) || 0;
            if (! iid || ! sid) return;
            postAjax('tjm_issue_unassign_article', { issue_id: iid, submission_id: sid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });

        $page.on('click', '[data-action="publish-issue"]', function () {
            var $card = $(this).closest('.tjm-issue-card');
            var iid   = parseInt($card.data('issue-id'), 10) || 0;
            if (! iid) return;
            if (! window.confirm(config.i18n.confirm_submit)) return;
            postAjax('tjm_issue_publish', { issue_id: iid })
                .done(function (res) {
                    if (res.success) { window.location.reload(); }
                    else { showMsg($msg, res.data || config.i18n.error, 'error'); }
                });
        });
    }

})(jQuery);
