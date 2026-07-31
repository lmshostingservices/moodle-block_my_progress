/**
 * My Progress block JavaScript.
 * Design: Matches AI Quick Links block interactions
 *
 * @module     block_my_progress/my_progress
 * @package    block_my_progress
 * @copyright  2025 AI Grader <support@aigrader.io>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Initialize the My Progress block.
     * Waits for DOM to be ready before attaching event listeners.
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initBlock();
            });
        } else {
            initBlock();
        }
    }

    /**
     * Initialize block after DOM is ready.
     */
    function initBlock() {
        var containers = document.querySelectorAll('[data-region="my-progress"]');

        if (containers.length === 0) {
            console.warn('[My Progress] No containers found with data-region="my-progress"');
            return;
        }

        containers.forEach(function(container) {
            initCategoryToggles(container);
            initBulkControls(container);
            initFilterSort(container);
        });
    }

    /**
     * Initialize category collapse/expand toggles.
     * Categories are collapsed by default.
     *
     * @param {HTMLElement} container The block container
     */
    function initCategoryToggles(container) {
        var categoryHeaders = container.querySelectorAll('.mp-category-header[data-action="toggle-category"]');

        categoryHeaders.forEach(function(header) {
            // Click handler
            header.addEventListener('click', function(e) {
                e.preventDefault();
                toggleCategory(header);
            });

            // Keyboard handler for accessibility
            header.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleCategory(header);
                }
            });
        });
    }

    /**
     * Toggle a single category.
     *
     * @param {HTMLElement} header The category header element
     */
    function toggleCategory(header) {
        var category = header.closest('.mp-category');
        if (!category) {
            return;
        }

        var isExpanded = header.getAttribute('aria-expanded') === 'true';

        if (isExpanded) {
            header.setAttribute('aria-expanded', 'false');
            category.classList.add('mp-collapsed');
        } else {
            header.setAttribute('aria-expanded', 'true');
            category.classList.remove('mp-collapsed');
        }
    }

    /**
     * Initialize bulk collapse/expand controls.
     *
     * @param {HTMLElement} container The block container
     */
    function initBulkControls(container) {
        var collapseAllBtn = container.querySelector('[data-action="collapse-all"]');
        var expandAllBtn = container.querySelector('[data-action="expand-all"]');

        if (collapseAllBtn) {
            collapseAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                collapseAllCategories(container);
            });
        }

        if (expandAllBtn) {
            expandAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                expandAllCategories(container);
            });
        }
    }

    /**
     * Collapse all categories.
     *
     * @param {HTMLElement} container The block container
     */
    function collapseAllCategories(container) {
        var categories = container.querySelectorAll('.mp-category');

        categories.forEach(function(category) {
            var header = category.querySelector('.mp-category-header');
            if (header) {
                header.setAttribute('aria-expanded', 'false');
            }
            category.classList.add('mp-collapsed');
        });
    }

    /**
     * Expand all categories.
     *
     * @param {HTMLElement} container The block container
     */
    function expandAllCategories(container) {
        var categories = container.querySelectorAll('.mp-category');

        categories.forEach(function(category) {
            var header = category.querySelector('.mp-category-header');
            if (header) {
                header.setAttribute('aria-expanded', 'true');
            }
            category.classList.remove('mp-collapsed');
        });
    }

    /**
     * Initialize filter, sort, and search functionality.
     *
     * @param {HTMLElement} container The block container
     */
    function initFilterSort(container) {
        // Filter labels (new pill-style buttons)
        var filterLabels = container.querySelectorAll('.mp-filter-label[data-filter]');
        filterLabels.forEach(function(label) {
            label.addEventListener('click', function(e) {
                e.preventDefault();
                // Remove active class from all filter labels
                filterLabels.forEach(function(l) {
                    l.classList.remove('mp-filter-active');
                });
                // Add active class to clicked label
                label.classList.add('mp-filter-active');
                // Apply filter
                var filterValue = label.getAttribute('data-filter');
                filterCourses(container, filterValue);
            });
        });

        // Sort labels (new inline buttons)
        var sortLabels = container.querySelectorAll('.mp-sort-label[data-sort]');
        sortLabels.forEach(function(label) {
            label.addEventListener('click', function(e) {
                e.preventDefault();
                // Remove active class from all sort labels
                sortLabels.forEach(function(l) {
                    l.classList.remove('mp-sort-active');
                });
                // Add active class to clicked label
                label.classList.add('mp-sort-active');
                // Apply sort
                var sortValue = label.getAttribute('data-sort');
                sortCourses(container, sortValue);
            });
        });

        // Search input
        var searchInput = container.querySelector('[data-action="search-course"]');
        if (searchInput) {
            var searchTimeout = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    searchCourses(container, searchInput.value);
                }, 200);
            });
        }

        // Legacy dropdown support (fallback)
        var filterSelect = container.querySelector('[data-action="filter"]');
        var sortSelect = container.querySelector('[data-action="sort"]');

        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                filterCourses(container, filterSelect.value);
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                sortCourses(container, sortSelect.value);
            });
        }
    }

    /**
     * Search courses by name.
     *
     * @param {HTMLElement} container The block container
     * @param {string} query The search query
     */
    function searchCourses(container, query) {
        var courses = container.querySelectorAll('.mp-course');
        var normalizedQuery = query.toLowerCase().trim();

        courses.forEach(function(course) {
            var courseName = course.querySelector('.mp-course-name');
            var name = courseName ? courseName.textContent.toLowerCase().trim() : '';
            
            if (normalizedQuery === '' || name.indexOf(normalizedQuery) !== -1) {
                course.removeAttribute('data-search-hidden');
                // Only show if not hidden by filter
                if (!course.hasAttribute('data-filter-hidden')) {
                    course.style.display = '';
                }
            } else {
                course.setAttribute('data-search-hidden', 'true');
                course.style.display = 'none';
            }
        });

        updateCategoryCounts(container);
    }

    /**
     * Filter courses by status.
     *
     * @param {HTMLElement} container The block container
     * @param {string} filter The filter value
     */
    function filterCourses(container, filter) {
        var courses = container.querySelectorAll('.mp-course');

        courses.forEach(function(course) {
            var status = course.getAttribute('data-status');
            var show = false;

            switch (filter) {
                case 'all':
                    show = true;
                    break;
                case 'completed':
                    show = (status === 'completed');
                    break;
                case 'inprogress':
                    show = (status === 'inprogress' || status === 'overdue');
                    break;
                case 'notstarted':
                    show = (status === 'notstarted');
                    break;
                default:
                    show = true;
            }

            course.style.display = show ? '' : 'none';
        });

        updateCategoryCounts(container);
    }

    /**
     * Sort courses by criteria.
     *
     * @param {HTMLElement} container The block container
     * @param {string} sortBy The sort criteria
     */
    function sortCourses(container, sortBy) {
        var courseLists = container.querySelectorAll('.mp-courses');

        courseLists.forEach(function(list) {
            var courses = Array.from(list.querySelectorAll('.mp-course'));

            courses.sort(function(a, b) {
                if (sortBy === 'progress') {
                    var progressA = parseInt(a.getAttribute('data-progress') || '0', 10);
                    var progressB = parseInt(b.getAttribute('data-progress') || '0', 10);
                    return progressB - progressA;
                } else if (sortBy === 'name') {
                    var nameA = a.querySelector('.mp-course-name');
                    var nameB = b.querySelector('.mp-course-name');
                    var textA = nameA ? nameA.textContent.toLowerCase().trim() : '';
                    var textB = nameB ? nameB.textContent.toLowerCase().trim() : '';
                    return textA.localeCompare(textB);
                } else if (sortBy === 'enddate') {
                    var dateA = a.getAttribute('data-enddate') || '9999-12-31';
                    var dateB = b.getAttribute('data-enddate') || '9999-12-31';
                    return dateA.localeCompare(dateB);
                }
                return 0;
            });

            courses.forEach(function(course) {
                list.appendChild(course);
            });
        });
    }

    /**
     * Update category course counts after filtering.
     *
     * @param {HTMLElement} container The block container
     */
    function updateCategoryCounts(container) {
        var categories = container.querySelectorAll('.mp-category');

        categories.forEach(function(category) {
            var allCourses = category.querySelectorAll('.mp-course');
            var visibleCourses = Array.from(allCourses).filter(function(c) {
                return c.style.display !== 'none';
            });
            var countEl = category.querySelector('.mp-category-count');

            if (countEl) {
                countEl.textContent = visibleCourses.length;
            }

            if (visibleCourses.length === 0) {
                category.style.display = 'none';
            } else {
                category.style.display = '';
            }
        });
    }

    return {
        init: init
    };
});
