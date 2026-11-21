<?php
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

namespace mod_listgrades\form;

use html_writer;
use MoodleQuickForm;

/**
 * Render a hierarchical selector where each selectable node has several checkbox columns.
 *
 * The input tree is independent of gradebook classes. A node has an id, display label, optional
 * selectable item id/name, category path and child nodes. Branch nodes may also be selectable.
 * Column definitions use a field prefix as key and provide a label and bulk-toggle label.
 *
 * @package     mod_listgrades
 * @copyright   2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tree_checkbox_selector {
    /** @var MoodleQuickForm Moodle form being populated. */
    private MoodleQuickForm $form;

    /** @var array Checkbox column definitions keyed by field prefix. */
    private array $columns;

    /** @var int[] Selectable item ids in tree order. */
    private array $itemids = [];

    /**
     * Create a tree selector renderer.
     *
     * @param MoodleQuickForm $form Form.
     * @param array $columns Column definitions keyed by field name prefix.
     */
    public function __construct(MoodleQuickForm $form, array $columns) {
        $this->form = $form;
        $this->columns = $columns;
    }

    /**
     * Add header controls and all tree nodes to the form.
     *
     * @param array $tree Normalized tree nodes.
     * @param string $badgeheading Label displayed above selected item badges.
     * @param string $noneSelected Label displayed when no item is selected.
     * @param string $sectionid HTML id of the containing form section.
     * @return void
     */
    public function add_to_form(array $tree, string $badgeheading, string $noneselected, string $sectionid): void {
        $badges = html_writer::tag('strong', $badgeheading);
        $badges .= html_writer::div('', 'listgrades-tree-selector-badges', [
            'role' => 'status',
            'aria-live' => 'polite',
        ]);
        $columnheaders = '';
        foreach ($this->columns as $prefix => $column) {
            $heading = html_writer::span($column['label'], 'listgrades-tree-selector-column-label');
            $heading .= html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'class' => 'form-check-input listgrades-tree-selector-column-toggle',
                'data-column' => $prefix,
                'aria-label' => $column['togglelabel'],
            ]);
            $columnheaders .= html_writer::tag(
                'label',
                $heading,
                ['class' => 'listgrades-tree-selector-column-heading']
            );
        }
        $this->form->addElement(
            'static',
            $sectionid . '_treeheader',
            html_writer::div($badges, 'listgrades-tree-selector-badge-header'),
            html_writer::div($columnheaders, 'listgrades-tree-selector-column-headings')
        );
        foreach ($tree as $node) {
            $this->add_node($node, 0);
        }
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_listgrades/grade_item_tree', 'init', [[
            'sectionId' => $sectionid,
            'columns' => array_map(static function($column) {
                return $column['label'];
            }, $this->columns),
            'noneSelectedLabel' => $noneselected,
        ]]);
    }

    /**
     * Get the selectable identifiers in the same order as they were added.
     *
     * @return int[]
     */
    public function get_item_ids(): array {
        return $this->itemids;
    }

    /**
     * Add a branch, selectable node, or leaf.
     *
     * @param array $node Normalized tree node.
     * @param int $depth Node depth.
     * @return void
     */
    private function add_node(array $node, int $depth): void {
        $id = (string) $node['id'];
        $label = (string) $node['label'];
        $path = implode(',', $node['path'] ?? []);
        $haschildren = !empty($node['branch']);
        $itemid = isset($node['itemid']) ? (int) $node['itemid'] : null;
        $rowlabel = $label;
        if ($haschildren) {
            $togglelabel = strip_tags($label);
            $rowlabel = html_writer::span('', 'listgrades-tree-selector-chevron', ['aria-hidden' => 'true']);
            $rowlabel .= html_writer::span($label, 'listgrades-tree-selector-branch-name');
            $rowlabel = html_writer::tag('button', $rowlabel, [
                'type' => 'button',
                'class' => 'listgrades-tree-selector-toggle',
                'data-branch-id' => $id,
                'data-branch-path' => $path,
                'data-expand-label' => $node['expandlabel'] ?? $togglelabel,
                'data-collapse-label' => $node['collapselabel'] ?? $togglelabel,
                'aria-expanded' => 'false',
                'aria-label' => get_string('expandgradecategory', 'listgrades', $togglelabel),
                'style' => '--listgrades-tree-depth:' . $depth,
            ]);
        } else {
            $rowlabel = html_writer::span($label, 'listgrades-tree-selector-item-name', [
                'style' => '--listgrades-tree-depth:' . $depth,
            ]);
        }

        if ($itemid !== null) {
            $this->itemids[] = $itemid;
            $metadata = html_writer::span('', 'listgrades-tree-selector-item-metadata', [
                'data-item-id' => $itemid,
                'data-item-name' => strip_tags((string) ($node['itemname'] ?? $label)),
                'data-item-type' => $node['itemtype'] ?? '',
                'data-branch-path' => $path,
            ]);
            $checkboxes = [];
            foreach ($this->columns as $prefix => $column) {
                $name = $prefix . '[' . $itemid . ']';
                $checkboxes[] = $this->form->createElement(
                    'advcheckbox',
                    $name,
                    '',
                    html_writer::span($column['label'], 'visually-hidden'),
                    [],
                    [0, 1]
                );
                $this->form->setType($name, PARAM_BOOL);
            }
            $this->form->addGroup($checkboxes, 'treeselect_' . $itemid, $rowlabel . $metadata, '', false);
        } else {
            $this->form->addElement('static', 'treebranch_' . $id, $rowlabel, '');
        }

        foreach ($node['children'] ?? [] as $child) {
            $this->add_node($child, $depth + 1);
        }
    }
}
