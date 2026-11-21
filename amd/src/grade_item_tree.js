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
 * Hierarchical multi-column checkbox selector used by the activity form.
 *
 * @module     mod_listgrades/grade_item_tree
 * @copyright  2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    badgeContainer: '.listgrades-tree-selector-badges',
    branchToggle: '.listgrades-tree-selector-toggle',
    itemMetadata: '.listgrades-tree-selector-item-metadata',
    columnToggle: '.listgrades-tree-selector-column-toggle',
    columnHeadings: '.listgrades-tree-selector-column-headings',
};

/**
 * Convert a comma-separated branch path to branch ids.
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
    const branchToggles = [...section.querySelectorAll(SELECTORS.branchToggle)];
    const itemMetadata = [...section.querySelectorAll(SELECTORS.itemMetadata)];
    const columnToggles = [...section.querySelectorAll(SELECTORS.columnToggle)];
    const columns = Object.entries(config.columns);
    const itemCheckboxSelector = columns.map(([column]) =>
        `input[type="checkbox"][name^="${column}["]`
    ).join(', ');
    const expandedCategories = new Set();

    const branchRows = branchToggles.map(toggle => {
        const row = toggle.closest('.fitem');
        row.classList.add('listgrades-tree-selector-branch-row');
        return {
            id: toggle.dataset.branchId,
            path: parsePath(toggle.dataset.branchPath),
            row,
            toggle,
        };
    });
    const itemRows = itemMetadata.map(metadata => {
        const row = metadata.closest('.fitem');
        row.classList.add('listgrades-tree-selector-item-row');
        if (metadata.dataset.itemType === 'category') {
            row.classList.add('listgrades-tree-selector-category-item-row');
        }
        const checkboxgroup = row.querySelector('.felement fieldset > .d-flex');
        checkboxgroup?.classList.remove('d-flex', 'flex-wrap');
        checkboxgroup?.classList.add('listgrades-tree-selector-checkboxes');
        return {
            id: metadata.dataset.itemId,
            name: metadata.dataset.itemName,
            path: parsePath(metadata.dataset.branchPath),
            row,
        };
    });

    const headingRow = section.querySelector(SELECTORS.columnHeadings)?.closest('.fitem');
    if (headingRow) {
        headingRow.classList.add('listgrades-tree-selector-header-row');
        const rows = new Set([
            headingRow,
            ...branchRows.map(branch => branch.row),
            ...itemRows.map(item => item.row),
        ]);
        const orderedRows = [...section.querySelectorAll('.fitem')].filter(row => rows.has(row));
        const scrollContainer = document.createElement('div');
        scrollContainer.className = 'listgrades-tree-selector-scroll';
        scrollContainer.style.setProperty('--listgrades-tree-selector-column-count', columns.length);
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

    // Selected items determine the initial expansion only; users control it afterwards.
    itemRows.forEach(item => {
        const selected = columns.some(([column]) => getCheckbox(column, item.id)?.checked);
        if (selected) {
            item.path.forEach(categoryId => expandedCategories.add(categoryId));
        }
    });

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

    /** Apply the current expanded state to every branch and item row. */
    const applyVisibility = () => {
        branchRows.forEach(branch => {
            const visible = branch.path.every(branchId => expandedCategories.has(branchId));
            branch.row.classList.toggle('listgrades-tree-selector-hidden', !visible);
            const isExpanded = expandedCategories.has(branch.id);
            branch.toggle.setAttribute('aria-expanded', isExpanded.toString());
            branch.toggle.setAttribute(
                'aria-label',
                isExpanded ? branch.toggle.dataset.collapseLabel : branch.toggle.dataset.expandLabel
            );
        });
        itemRows.forEach(item => {
            const visible = item.path.every(branchId => expandedCategories.has(branchId));
            item.row.classList.toggle('listgrades-tree-selector-hidden', !visible);
        });
    };

    /** Rebuild the badges from the current checkbox state. */
    const updateBadges = () => {
        badgeContainer.replaceChildren();
        itemRows.forEach(item => {
            const selectedColumns = [];
            columns.forEach(([column, label]) => {
                if (getCheckbox(column, item.id)?.checked) {
                    selectedColumns.push(label);
                }
            });
            if (!selectedColumns.length) {
                return;
            }
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'listgrades-tree-selector-badge';
            badge.textContent = `${item.name} · ${selectedColumns.join(', ')}`;
            badge.addEventListener('click', () => {
                item.path.forEach(categoryId => expandedCategories.add(categoryId));
                applyVisibility();
                item.row.scrollIntoView({behavior: 'smooth', block: 'center'});
                const selected = columns.map(([column]) => getCheckbox(column, item.id))
                    .find(checkbox => checkbox?.checked);
                selected?.focus({preventScroll: true});
            });
            badgeContainer.append(badge);
        });
        if (!badgeContainer.children.length) {
            const empty = document.createElement('span');
            empty.className = 'listgrades-tree-selector-empty';
            empty.textContent = config.noneSelectedLabel;
            badgeContainer.append(empty);
        }
        updateColumnToggles();
        applyVisibility();
    };

    section.addEventListener('click', event => {
        const toggle = event.target.closest(SELECTORS.branchToggle);
        if (toggle) {
            event.preventDefault();
            const branchId = toggle.dataset.branchId;
            const expanded = expandedCategories.has(branchId);
            if (expanded) {
                expandedCategories.delete(branchId);
            } else {
                expandedCategories.add(branchId);
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
        if (event.target.matches(itemCheckboxSelector)) {
            updateBadges();
        }
    });

    updateBadges();
};
