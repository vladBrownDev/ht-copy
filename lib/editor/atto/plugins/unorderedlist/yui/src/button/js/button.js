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

/*
 * @package    atto_unorderedlist
 * @copyright  2013 Damyon Wiese  <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module     moodle-atto_unorderedlist-button
 */

/**
 * Atto text editor unorderedlist plugin.
 *
 * @namespace M.atto_unorderedlist
 * @class button
 * @extends M.editor_atto.EditorPlugin
 */

Y.namespace('M.atto_unorderedlist').Button = Y.Base.create('button', Y.M.editor_atto.EditorPlugin, [], {
    initializer: function() {
        this.addButton({
            callback: this._insertUnorderedList,
            icon: 'e/bullet_list',

            // Watch the following tags and add/remove highlighting as appropriate:
            tags: 'ul'
        });
    },
    /**
     * InsertUnorderedList
     *
     * @method _insertUnorderedList
     * @private
     */
    _insertUnorderedList: function() {
        // Store the current cursor position.
        this._currentSelection = this.get('host').getSelection();

        // Set direction according to current page language.
        var html = '<ul dir="' + this.buttons.unorderedlist.coreDirection + '" style="' +
            this.buttons.unorderedlist.styleAlignment + '"><li></li></ul>';

        this.get('host').insertContentAtFocusPoint(html);

        // Mark the content as updated.
        this.markUpdated();
    }
});
