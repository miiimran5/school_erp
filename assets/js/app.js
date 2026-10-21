(function () {
    'use strict';

    function markActiveNavigation() {
        var currentPath = window.location.pathname.replace(/\/+$/, '');
        var links = document.querySelectorAll('.nav-sidebar a.nav-link[href]');

        links.forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href || href === '#') {
                return;
            }

            var linkPath;
            try {
                linkPath = new URL(href, window.location.origin).pathname.replace(/\/+$/, '');
            } catch (error) {
                return;
            }

            if (linkPath && currentPath === linkPath) {
                link.classList.add('active');
                var parentTree = link.closest('.has-treeview');
                if (parentTree) {
                    parentTree.classList.add('menu-open');
                    var parentLink = parentTree.querySelector(':scope > a.nav-link');
                    if (parentLink) {
                        parentLink.classList.add('active');
                    }
                }
            }
        });
    }

    function showFormLoadingState() {
        document.querySelectorAll('form[data-loading]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (!button || button.disabled) {
                    return;
                }

                button.disabled = true;
                button.setAttribute('data-original-label', button.innerHTML);
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Please wait...';
            });
        });
    }

    function updateSidebarSearch(value) {
        var query = (value || '').trim().toLocaleLowerCase();
        document.querySelectorAll('[data-sidebar-search], [data-global-search]').forEach(function (input) {
            if (input.value !== value) input.value = value;
        });
        document.querySelectorAll('.nav-sidebar > .nav-item').forEach(function (item) {
            var ownLink = item.querySelector(':scope > a.nav-link');
            var ownText = ownLink ? ownLink.textContent.toLocaleLowerCase() : '';
            var childItems = item.querySelectorAll('.nav-treeview > .nav-item');
            var childMatches = false;
            childItems.forEach(function (child) {
                var childText = child.textContent.toLocaleLowerCase();
                var match = !query || childText.indexOf(query) !== -1;
                child.hidden = !match;
                childMatches = childMatches || match;
            });
            var ownMatch = !query || ownText.indexOf(query) !== -1;
            item.hidden = !(ownMatch || childMatches);
        });
    }

    function setupNavigationSearch() {
        document.querySelectorAll('[data-sidebar-search], [data-global-search]').forEach(function (input) {
            input.addEventListener('input', function () {
                updateSidebarSearch(input.value);
            });
            input.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                var first = Array.prototype.find.call(
                    document.querySelectorAll('.nav-sidebar a.nav-link[href]'),
                    function (link) {
                        return !link.closest('li').hidden && link.getAttribute('href') !== '#';
                    }
                );
                if (first) window.location.href = first.href;
            });
        });
    }

    function setupDisplayControls() {
        var theme = localStorage.getItem('erp-theme') || 'light';
        document.body.classList.toggle('erp-dark-mode', theme === 'dark');
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dark = document.body.classList.toggle('erp-dark-mode');
                localStorage.setItem('erp-theme', dark ? 'dark' : 'light');
                button.setAttribute('aria-pressed', dark ? 'true' : 'false');
                var icon = button.querySelector('i');
                if (icon) icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
            });
        });
        document.querySelectorAll('[data-mobile-preview]').forEach(function (button) {
            button.addEventListener('click', function () {
                var enabled = document.body.classList.toggle('mobile-preview');
                button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            });
        });
    }

    var installPrompt = null;
    function setupInstallButton() {
        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            installPrompt = event;
        });
        document.querySelectorAll('[data-install-app]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!installPrompt) {
                    alert('To create a shortcut, open the browser menu and choose “Add to Home screen”. On iPhone, use Safari Share > Add to Home Screen.');
                    return;
                }
                installPrompt.prompt();
                installPrompt.userChoice.then(function () {
                    installPrompt = null;
                });
            });
        });
    }

    function setupReportSelection() {
        document.querySelectorAll('[data-select-all-reports]').forEach(function (button) {
            button.addEventListener('click', function () {
                var boxes = document.querySelectorAll('input[name="reports[]"]');
                var selectAll = Array.prototype.some.call(boxes, function (box) {
                    return !box.checked;
                });
                boxes.forEach(function (box) {
                    box.checked = selectAll;
                });
                button.textContent = selectAll ? 'Clear selection' : 'Select all';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        markActiveNavigation();
        showFormLoadingState();
        setupNavigationSearch();
        setupDisplayControls();
        setupInstallButton();
        setupReportSelection();
    });
}());
