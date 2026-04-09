/**
 * @file
 * Drupal behaviors for the AIRO inline score widget.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.airoInlineWidget = {
    attach: function (context) {
      // Run Analysis / Re-analyze button
      once('airo-assess-btn', '.airo-widget__run-analysis, .airo-widget__reanalyze', context).forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var url = this.getAttribute('data-assess-url');
          var widget = this.closest('.airo-widget');
          if (!url || !widget) return;

          // Switch to analyzing state
          widget.innerHTML =
            '<div class="airo-widget__analyzing">' +
              '<div class="airo-widget__spinner"></div>' +
              '<div class="airo-widget__analyzing-text">' +
                '<span class="airo-widget__analyzing-title">' + Drupal.t('Analyzing content...') + '</span>' +
                '<span class="airo-widget__analyzing-subtitle">' + Drupal.t('AI readiness check in progress') + '</span>' +
              '</div>' +
            '</div>';

          // POST to assess endpoint
          fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
          })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (data.status === 'complete') {
              // Reload the page to show updated widget
              window.location.reload();
            } else {
              // Start polling
              Drupal.behaviors.airoInlineWidget.pollStatus(widget);
            }
          })
          .catch(function () {
            widget.innerHTML =
              '<div class="airo-widget__empty">' +
                '<span class="airo-widget__empty-title">' + Drupal.t('Analysis failed') + '</span>' +
                '<span class="airo-widget__empty-subtitle">' + Drupal.t('Please try again.') + '</span>' +
              '</div>';
          });
        });
      });
    },

    pollStatus: function (widget) {
      var nodeId = widget.getAttribute('data-node-id');
      if (!nodeId) return;
      var url = Drupal.url('admin/ai-content-audit/status/' + nodeId);

      var interval = setInterval(function () {
        fetch(url, { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.status === 'complete') {
              clearInterval(interval);
              window.location.reload();
            }
          })
          .catch(function () {
            clearInterval(interval);
          });
      }, 2000);
    },
  };

})(Drupal, once);
