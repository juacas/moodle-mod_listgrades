// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Grade item tree used by the activity configuration form.
 *
 * @module     mod_listgrades/grade_item_tree
 * @copyright  2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    badgeContainer: '.listgrades-grade-tree-badges',
    categoryToggle: '.listgrades-grade-tree-toggle',
    itemMetadata: '.listgrades-grade-tree-item-metadata',
    checkbox: 'input[type="checkbox"][name^="gradeitems["], input[type="checkbox"][name^="feedbackitems["]',
    columnToggle: '.listgrades-grade-tree-column-toggle',
    columnHeadings: '.listgrades-grade-tree-column-headings',
};

/**
 * Convert a comma-separated category path to category ids.
 *
 * @param {string} path Category path.
 * @returns {string[]} Category ids.
 */
const parsePath = path => path ? path.split(',').filter(Boolean) : [];

/**
 * Initialise one grade item tree.
 *
 * @param {Object} config Labels and the section id.
 */
export const init = config => {
    const section = document.getElementById(config.sectionId);
    if (!section) {
        return;
    }

    const badgeContainer = section.querySelector(SELECTORS.badgeContainer);
    const categoryToggles = [...section.querySelectorAll(SELECTORS.categoryToggle)];
    const itemMetadata = [...section.querySelectorAll(SELECTORS.itemMetadata)];
    const columnToggles = [...section.querySelectorAll(SELECTORS.columnToggle)];
    const manuallyExpanded = new Set();
    let selectedExpanded = new Set();

    const categoryRows = categoryToggles.map(toggle => {
        const row = toggle.closest('.fitem');
        row.classList.add('listgrades-grade-tree-category-row');
        return {
            id: toggle.dataset.categoryId,
            path: parsePath(toggle.dataset.categoryPath),
            row,
            toggle,
        };
    });
    const itemRows = itemMetadata.map(metadata => {
        const row = metadata.closest('.fitem');
        row.classList.add('listgrades-grade-tree-row');
        row.querySelector('.felement fieldset > .d-flex')?.classList.add('listgrades-grade-tree-checkboxes');
        return {
            id: metadata.dataset.itemId,
            name: metadata.dataset.itemName,
            path: parsePath(metadata.dataset.categoryPath),
            row,
        };
    });

    const headingRow = section.querySelector(SELECTORS.columnHeadings)?.closest('.fitem');
    if (headingRow) {
        headingRow.classList.add('listgrades-grade-tree-header-row');
        const rows = new Set([
            headingRow,
            ...categoryRows.map(category => category.row),
            ...itemRows.map(item => item.row),
        ]);
        const orderedRows = [...section.querySelectorAll('.fitem')].filter(row => rows.has(row));
        const scrollContainer = document.createElement('div');
        scrollContainer.className = 'listgrades-grade-tree-scroll';
        headingRow.before(scrollContainer);
        orderedRows.forEach(row => scrollContainer.append(row));
    }

    /**
     * Find one checkbox for an item and column.
     *
     * @param {string} column Form field prefix.
     * @param {string} itemId Grade item id.
     * @returns {HTMLInputElement|null} Checkbox.
     */
    const getCheckbox = (column, itemId) => section.querySelector(
        `input[type="checkbox"][name="${column}[${itemId}]"]`
    );

    /** Keep each column controller synchronised with its item checkboxes. */
    const updateColumnToggles = () => {
        columnToggles.forEach(toggle => {
            const checkboxes = [...section.querySelectorAll(
                `input[type="checkbox"][name^="${toggle.dataset.column}["]`
            )];
            const checked = checkboxes.filter(checkbox => checkbox.checked).length;
            toggle.checked = checked > 0;
            toggle.indeterminate = false;
        });
    };

    /** Recalculate categories which must remain expanded for selected items. */
    const updateSelectedExpanded = () => {
        selectedExpanded = new Set();
        itemRows.forEach(item => {
            const grade = getCheckbox('gradeitems', item.id);
            const feedback = getCheckbox('feedbackitems', item.id);
            if (grade?.checked || feedback?.checked) {
                item.path.forEach(categoryId => selectedExpanded.add(categoryId));
            }
        });
    };

    /** @returns {Set} Categories expanded manually or because they contain a selected item. */
    const getExpandedCategories = () => new Set([...manuallyExpanded, ...selectedExpanded]);

    /** Apply the current expanded state to every category and item row. */
    const applyVisibility = () => {
        const expanded = getExpandedCategories();
        categoryRows.forEach(category => {
            const visible = category.path.every(categoryId => expanded.has(categoryId));
            category.row.classList.toggle('listgrades-grade-tree-hidden', !visible);
            const isExpanded = expanded.has(category.id);
            category.toggle.setAttribute('aria-expanded', isExpanded.toString());
            category.toggle.setAttribute(
                'aria-label',
                isExpanded ? category.toggle.dataset.collapseLabel : category.toggle.dataset.expandLabel
            );
        });
        itemRows.forEach(item => {
            const visible = item.path.every(categoryId => expanded.has(categoryId));
            item.row.classList.toggle('listgrades-grade-tree-hidden', !visible);
        });
    };

    /** Rebuild the badges from the current checkbox state. */
    const updateBadges = () => {
        updateSelectedExpanded();
        badgeContainer.replaceChildren();
        itemRows.forEach(item => {
            const grade = getCheckbox('gradeitems', item.id);
            const feedback = getCheckbox('feedbackitems', item.id);
            const selectedColumns = [];
            if (grade?.checked) {
                selectedColumns.push(config.gradeLabel);
            }
            if (feedback?.checked) {
                selectedColumns.push(config.feedbackLabel);
            }
            if (!selectedColumns.length) {
                return;
            }
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'listgrades-grade-tree-badge';
            badge.textContent = `${item.name} · ${selectedColumns.join(', ')}`;
            badge.addEventListener('click', () => {
                item.path.forEach(categoryId => manuallyExpanded.add(categoryId));
                applyVisibility();
                item.row.scrollIntoView({behavior: 'smooth', block: 'center'});
                (grade?.checked ? grade : feedback)?.focus({preventScroll: true});
            });
            badgeContainer.append(badge);
        });
        if (!badgeContainer.children.length) {
            const empty = document.createElement('span');
            empty.className = 'listgrades-grade-tree-empty';
            empty.textContent = config.noneSelectedLabel;
            badgeContainer.append(empty);
        }
        updateColumnToggles();
        applyVisibility();
    };

    section.addEventListener('click', event => {
        const toggle = event.target.closest(SELECTORS.categoryToggle);
        if (toggle) {
            event.preventDefault();
            const categoryId = toggle.dataset.categoryId;
            const expanded = getExpandedCategories().has(categoryId);
            if (expanded) {
                manuallyExpanded.delete(categoryId);
            } else {
                manuallyExpanded.add(categoryId);
            }
            applyVisibility();
            return;
        }
    });
    section.addEventListener('change', event => {
        const columnToggle = event.target.closest(SELECTORS.columnToggle);
        if (columnToggle) {
            section.querySelectorAll(
                `input[type="checkbox"][name^="${columnToggle.dataset.column}["]`
            ).forEach(checkbox => {
                checkbox.checked = columnToggle.checked;
            });
            updateBadges();
            return;
        }
        if (event.target.matches(SELECTORS.checkbox)) {
            updateBadges();
        }
    });

    updateBadges();
};
