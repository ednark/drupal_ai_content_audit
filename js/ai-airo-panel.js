/**
 * @file
 * Drupal behaviors for the AIRO off-canvas panel.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.airoPanel = {
    attach: function (context) {
      once('airo-tab-switch', '.airo-panel__tab', context).forEach(function (tab) {
        tab.addEventListener('click', function (e) {
          e.preventDefault();
          var tabId = this.getAttribute('data-tab');
          var panel = this.closest('.airo-panel');
          if (!panel || !tabId) return;

          var nodeId = panel.getAttribute('data-node-id');

          // Update active tab visual state
          panel.querySelectorAll('.airo-panel__tab').forEach(function (t) {
            t.classList.remove('airo-panel__tab--active');
            t.setAttribute('aria-selected', 'false');
          });
          this.classList.add('airo-panel__tab--active');
          this.setAttribute('aria-selected', 'true');

          // Show loading in content area
          var contentArea = panel.querySelector('#airo-tab-content');
          if (contentArea) {
            contentArea.innerHTML =
              '<div class="airo-panel__analyzing">' +
                '<div class="airo-panel__analyzing-spinner"></div>' +
                '<div class="airo-panel__analyzing-label">' + Drupal.t('Loading...') + '</div>' +
              '</div>';
          }

          // AJAX load tab content
          var url = Drupal.url('admin/ai-content-audit/panel/' + nodeId + '/' + tabId);
          var ajaxSettings = {
            url: url,
            element: this,
            progress: { type: 'none' },
          };

          var ajaxObject = Drupal.ajax(ajaxSettings);
          ajaxObject.execute();
        });
      });
    },

    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('airo-tab-switch', '.airo-panel__tab', context);
      }
    },
  };

})(Drupal, once);
