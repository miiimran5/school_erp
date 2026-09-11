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

    document.addEventListener('DOMContentLoaded', function () {
        markActiveNavigation();
        showFormLoadingState();
    });
}());
