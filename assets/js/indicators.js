/**
 * Tainacan Journal Manager — Indicators dashboard.
 *
 * Renders Chart.js charts driven by the tjm_indicators_data AJAX endpoint.
 * Requires assets/js/vendor/chart.umd.min.js (Chart.js 4.x). When that file
 * is missing the charts gracefully degrade to a notice; cards still render.
 */
(function ($) {
    'use strict';

    var config = window.tjmFrontend || {};

    $(document).ready(function () {
        var $page = $('.tjm-indicators');
        if (! $page.length) return;

        var journal_id = parseInt($page.data('journal-id'), 10) || 0;
        var $msg = $('#tjm-ind-message');

        // ── load data ────────────────────────────────────────────
        $.post(config.ajaxUrl, {
            action: 'tjm_indicators_data',
            nonce: config.nonce,
            journal_id: journal_id
        }).done(function (res) {
            if (! res.success) {
                $msg.addClass('tjm-message--error').text(res.data || config.i18n.error).show();
                return;
            }
            paint(res.data);
        }).fail(function () {
            $msg.addClass('tjm-message--error').text(config.i18n.error).show();
        });

        // ── actions ──────────────────────────────────────────────
        $page.on('click', '[data-action="print"]', function () {
            window.print();
        });

        $page.on('click', '[data-action="export-csv"]', function () {
            // submit a hidden form so the browser handles the download
            var $form = $('<form/>', { method: 'POST', action: config.ajaxUrl });
            $('<input>').attr({ type: 'hidden', name: 'action', value: 'tjm_indicators_export' }).appendTo($form);
            $('<input>').attr({ type: 'hidden', name: 'nonce', value: config.nonce }).appendTo($form);
            $('<input>').attr({ type: 'hidden', name: 'journal_id', value: journal_id }).appendTo($form);
            $form.appendTo('body').submit().remove();
        });

        // ── rendering ────────────────────────────────────────────
        function paint(data) {
            // Cards
            var totals = data.total || {};
            $page.find('[data-stat="submissions"]').text(totals.submissions || 0);
            $page.find('[data-stat="published"]').text(totals.published || 0);
            $page.find('[data-stat="reviews"]').text(totals.reviews || 0);
            $page.find('[data-stat="journals"]').text(totals.journals || 0);
            $page.find('[data-stat="issues"]').text(totals.issues || 0);

            var ar = data.acceptance_rate || {};
            $page.find('[data-stat="acceptance_rate"]').text((ar.rate || 0) + '%');

            if (typeof window.Chart === 'undefined') {
                $msg.addClass('tjm-message--error')
                    .text('Chart.js not loaded. Place chart.umd.min.js in assets/js/vendor/.')
                    .show();
                return;
            }

            // Status chart (bar)
            renderBar('tjm-chart-status', Object.keys(data.submissions_per_status || {}), Object.values(data.submissions_per_status || {}), 'Submissions');

            // Monthly chart (line)
            renderLine('tjm-chart-monthly', Object.keys(data.submissions_per_month || {}), Object.values(data.submissions_per_month || {}), 'Submissions');

            // Per journal (only when not scoped)
            if (data.submissions_per_journal && Object.keys(data.submissions_per_journal).length) {
                var labels = [], counts = [];
                for (var jid in data.submissions_per_journal) {
                    if (Object.prototype.hasOwnProperty.call(data.submissions_per_journal, jid)) {
                        labels.push(data.submissions_per_journal[jid].name);
                        counts.push(data.submissions_per_journal[jid].count);
                    }
                }
                renderBar('tjm-chart-journals', labels, counts, 'Submissions');
            }

            if (data.top_journals_published && Object.keys(data.top_journals_published).length) {
                var jl = [], jc = [];
                for (var k in data.top_journals_published) {
                    if (Object.prototype.hasOwnProperty.call(data.top_journals_published, k)) {
                        jl.push(data.top_journals_published[k].name);
                        jc.push(data.top_journals_published[k].count);
                    }
                }
                renderBar('tjm-chart-top-journals', jl, jc, 'Published');
            }

            if (data.top_reviewers && Object.keys(data.top_reviewers).length) {
                var rl = [], rc = [];
                for (var rid in data.top_reviewers) {
                    if (Object.prototype.hasOwnProperty.call(data.top_reviewers, rid)) {
                        rl.push(data.top_reviewers[rid].name);
                        rc.push(data.top_reviewers[rid].count);
                    }
                }
                renderBar('tjm-chart-reviewers', rl, rc, 'Submitted reviews');
            }
        }

        function renderBar(id, labels, values, datasetLabel) {
            var el = document.getElementById(id);
            if (! el) return;
            new window.Chart(el, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: datasetLabel,
                        data: values,
                        backgroundColor: 'rgba(26, 68, 128, 0.7)',
                        borderColor: 'rgba(26, 68, 128, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        function renderLine(id, labels, values, datasetLabel) {
            var el = document.getElementById(id);
            if (! el) return;
            new window.Chart(el, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: datasetLabel,
                        data: values,
                        fill: true,
                        backgroundColor: 'rgba(26, 68, 128, 0.15)',
                        borderColor: 'rgba(26, 68, 128, 1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    });
})(jQuery);
