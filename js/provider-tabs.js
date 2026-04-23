/**
 * @file
 * Provider tabs — collapsible compare section with ARIA-accessible tab panels.
 *
 * Attaches to every `.ai-content-audit-compare` element that contains a
 * `[role="tablist"]` with one or more `[role="tab"]` buttons and associated
 * `[role="tabpanel"]` elements.  Tab switching is handled entirely via DOM
 * show/hide — no page reload, no AJAX requests.
 *
 * Keyboard support:
 *   Arrow-Right / Arrow-Left — move focus to the next / previous tab and
 *                              activate it (wraps).
 *   Home / End               — jump to first / last tab.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Activates a single tab and reveals its panel; deactivates all siblings.
   *
   * @param {Element} tab     The `<button role="tab">` to activate.
   * @param {NodeList|Array} tabs   All sibling tab buttons in this tablist.
   * @param {Element} details The parent `.ai-content-audit-compare` element.
   */
  function activateTab(tab, tabs, details) {
    // Deactivate all sibling tabs (button + parent <li class="tabs__tab">).
    tabs.forEach(function (t) {
      t.setAttribute('aria-selected', 'false');
      t.classList.remove('is-active');
      t.setAttribute('tabindex', '-1');
      if (t.parentElement) {
        t.parentElement.classList.remove('is-active');
      }
    });

    // Hide all panels inside this compare block.
    var panels = details.querySelectorAll('[role="tabpanel"]');
    panels.forEach(function (p) {
      p.setAttribute('hidden', '');
    });

    // Activate the chosen tab (button + parent <li class="tabs__tab">).
    tab.setAttribute('aria-selected', 'true');
    tab.classList.add('is-active');
    tab.setAttribute('tabindex', '0');
    if (tab.parentElement) {
      tab.parentElement.classList.add('is-active');
    }

    // Show the corresponding panel (matched by data-ai-tab-target / data-ai-tab).
    var targetKey = tab.getAttribute('data-ai-tab-target');
    var panel = details.querySelector('[data-ai-tab="' + targetKey + '"]');
    if (panel) {
      panel.removeAttribute('hidden');
    }
  }

  Drupal.behaviors.aiContentAuditProviderTabs = {
    attach: function (context) {
      once('ai-content-audit-provider-tabs', '.ai-content-audit-compare', context)
        .forEach(function (details) {
          var tabs = details.querySelectorAll('[role="tab"]');

          // Nothing to wire up if there are no tab buttons (e.g. the provider-
          // selection <details> that only contains checkboxes).
          if (!tabs.length) {
            return;
          }

          tabs.forEach(function (tab) {
            // Click: activate tab and show its panel.
            tab.addEventListener('click', function () {
              activateTab(tab, tabs, details);
            });

            // Keyboard: arrow navigation moves focus + activates.
            tab.addEventListener('keydown', function (e) {
              var idx = Array.prototype.indexOf.call(tabs, tab);
              var target = null;

              if (e.key === 'ArrowRight') {
                e.preventDefault();
                target = tabs[(idx + 1) % tabs.length];
              }
              else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                target = tabs[(idx - 1 + tabs.length) % tabs.length];
              }
              else if (e.key === 'Home') {
                e.preventDefault();
                target = tabs[0];
              }
              else if (e.key === 'End') {
                e.preventDefault();
                target = tabs[tabs.length - 1];
              }

              if (target) {
                target.focus();
                activateTab(target, tabs, details);
              }
            });
          });
        });
    },
  };

})(Drupal, once);
