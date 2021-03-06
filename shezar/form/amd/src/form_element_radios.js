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
 * @module  shezar_form/form_element_radios
 * @class   Radios
 * @author  Sam Hemelryk <sam.hemelryk@shezarlms.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'shezar_form/form'], function($, Form) {

    var ERROR_CONTAINER_CLASS = 'shezar_form-error-container',
        ERROR_CONTAINER_SELECTOR = '.'+ERROR_CONTAINER_CLASS;

    /**
     * Radios element
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
    function RadiosElement(parent, type, id, node) {

        if (!(this instanceof RadiosElement)) {
            return new RadiosElement(parent, type, id, node);
        }

        Form.Element.apply(this, arguments);

        this.container = null;
        this.inputs = null;
        this.validationerroradded = false;

    }

    RadiosElement.prototype = Object.create(Form.Element.prototype);
    RadiosElement.prototype.constructor = RadiosElement;

    /**
     * Returns a string describing this object.
     * @returns {string}
     */
    RadiosElement.prototype.toString = function() {
        return '[object RadiosElement]';
    };

    /**
     * Initialises a new instance of this element.
     * @param {Function} done
     */
    RadiosElement.prototype.init = function(done) {
        var id = this.id,
            container = $('#' + id),
            inputs = $('#' + id + ' input[type=radio]'),
            input = $(inputs[0]),
            self = this,
            deferred = $.Deferred(),
            submitselector = 'input[type="submit"]:not([formnovalidate])';

        this.container = container;
        this.inputs = inputs;
        // Call the changed method when this element is changed.
        this.inputs.change($.proxy(this.changed, this));

        if (input.attr('required')) {
            require(['shezar_form/modernizr'], function (mod) {
                if (!mod.input.required) {
                    inputs.change($.proxy(self.polyFillValidate, self));
                    container.closest('form').find(submitselector).click($.proxy(self.polyFillValidate, self));
                }
                deferred.resolve();
            });
        } else {
            deferred.resolve();
        }

        deferred.done(done);
    };

    RadiosElement.prototype.polyFillValidate = function(e) {
        var valid = false,
            container = this.container;
        this.inputs.each(function(index, radio) {
            if ($(radio).prop('checked')) {
                valid = true;
            }
        });
        if (valid) {
            this.validationerroradded = false;
            container.closest('.tf_element').find(ERROR_CONTAINER_SELECTOR).remove();
        } else {
            e.preventDefault();
            if (!this.validationerroradded) {
                this.validationerroradded = true;
                require(['core/templates', 'core/str', 'core/config'], function (templates, mdlstrings, mdlconfig) {
                    mdlstrings.get_string('required','core').done(function (requiredstring) {
                        var context = {
                            errors_has_items: true,
                            errors: [{message: requiredstring}]
                        };
                        templates.render('shezar_form/validation_errors', context, mdlconfig.theme).done(function (template) {
                            container.prepend(template);
                        });
                    });
                });
            }
        }
    };

    RadiosElement.prototype.getValue = function() {
        // Check each radio and see if its selected.
        for (var i = 0; i < this.inputs.length; i++) {
            var input = $(this.inputs[i]);
            if (input.is(':checked')) {
                return input.val();
            }
        }
        return false;
    };

    return RadiosElement;

});