/**
 * This file is part of shezar LMS
 *
 * Copyright (C) 2016 onwards shezar Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author  Sam Hemelryk <sam.hemelryk@shezarlms.com>
 * @package shezar_form
 */

/**
 * @module  shezar_form/form_element_datetime
 * @class   DateTimeElement
 * @author  Sam Hemelryk <sam.hemelryk@shezarlms.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'shezar_form/form', 'shezar_form/modernizr'], function($, Form, Modernizr) {

    /**
     * DateTime element
     *
     * @class
     * @constructor
     * @augments Form.Element
     *
     * @param {(Form|Group)} parent
     * @param {string} type
     * @param {string} id
     * @param {HTMLElement} node
     */
    function DateTimeElement(parent, type, id, node) {

        if (!(this instanceof DateTimeElement)) {
            return new DateTimeElement(parent, type, id, node);
        }

        Form.Element.apply(this, arguments);

        this.input = null;

    }

    DateTimeElement.prototype = Object.create(Form.Element.prototype);
    DateTimeElement.prototype.constructor = DateTimeElement;

    /**
     * Returns a string describing this object.
     * @returns {string}
     */
    DateTimeElement.prototype.toString = function() {
        return '[object DateTimeElement]';
    };

    /**
     * Initialises a new instance of this element.
     * @param {Function} done
     */
    DateTimeElement.prototype.init = function(done) {
        var id = this.id,
            deferreds = [];
        this.input = $('#' + id);
        // Call the changed method when this element is changed.
        this.input.change($.proxy(this.changed, this));

        if (this.input.attr('required') && !Modernizr.input.required) {
            var requiredDeferred = $.Deferred();
            deferreds.push(requiredDeferred);
            // Polyfill the required attribute.
            require(['shezar_form/polyfill_required-lazy'], function (poly) {
                poly.init(id);
                requiredDeferred.resolve();
            });
        }

        if (!Modernizr.inputtypes['datetime-local']) {
            var dateDeferred = $.Deferred();
            deferreds.push(dateDeferred);
            // Polyfill the date/time functionality.
            require(['shezar_form/polyfill_date-lazy'], function(date) {
                date.init(id);
                dateDeferred.resolve();
            });
        }

        if (this.input.attr('placeholder') && !Modernizr.input.placeholder ) {
            var placeholderDeferred = $.Deferred();
            deferreds.push(placeholderDeferred);
            require(['shezar_form/polyfill_placeholder-lazy'], function (poly) {
                poly.init(id);
                placeholderDeferred.resolve();
            });
        }

        $.when.apply($, deferreds).done(done);
    };

    /**
     * Returns the datetime elements value.
     * @returns {string}
     */
    DateTimeElement.prototype.getValue = function() {
        return this.input.val();
    };

    return DateTimeElement;

});