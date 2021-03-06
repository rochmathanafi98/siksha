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
 * @module  shezar_form/form_element_passwordunmask
 * @class   PasswordUnmaskElement
 * @author  Sam Hemelryk <sam.hemelryk@shezarlms.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'shezar_form/form'], function($, Form) {

    // Needed for the password unmask.
    // See: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/repeat
    if (!String.prototype.repeat) {
        String.prototype.repeat = function(count) {
            'use strict';
            if (this == null) {
                throw new TypeError('can\'t convert ' + this + ' to object');
            }
            var str = '' + this;
            count = +count;
            if (count != count) {
                count = 0;
            }
            if (count < 0) {
                throw new RangeError('repeat count must be non-negative');
            }
            if (count == Infinity) {
                throw new RangeError('repeat count must be less than infinity');
            }
            count = Math.floor(count);
            if (str.length == 0 || count == 0) {
                return '';
            }
            // Ensuring count is a 31-bit integer allows us to heavily optimize the
            // main part. But anyway, most current (August 2014) browsers can't handle
            // strings 1 << 28 chars or longer, so:
            if (str.length * count >= 1 << 28) {
                throw new RangeError('repeat count must not overflow maximum string size');
            }
            var rpt = '';
            for (;;) {
                if ((count & 1) == 1) {
                    rpt += str;
                }
                count >>>= 1;
                if (count == 0) {
                    break;
                }
                str += str;
            }
            // Could we try:
            // return Array(count + 1).join(this);
            return rpt;
        }
    }

    /**
     * Password unmask element
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
    function PasswordUnmaskElement(parent, type, id, node) {

        if (!(this instanceof PasswordUnmaskElement)) {
            return new PasswordUnmaskElement(parent, type, id, node);
        }

        this.input = null;
        this.unmaskinput = null;

        Form.Element.apply(this, arguments);

    }

    PasswordUnmaskElement.prototype = Object.create(Form.Element.prototype);
    PasswordUnmaskElement.prototype.constructor = PasswordUnmaskElement;

    /**
     * Returns a string describing this object.
     * @returns {string}
     */
    PasswordUnmaskElement.prototype.toString = function() {
        return '[object PasswordUnmaskElement]';
    };

    /**
     * Initialises a new instance of this element.
     * @param {Function} done
     */
    PasswordUnmaskElement.prototype.init = function(done) {
        var id = this.id,
            input = $('#' + id),
            defereds = [],
            mask;

        this.input = input;
        this.unmaskinput = $('#' + id + 'unmask');
        this.wrap = this.input.parent('.wrap');

        var mask = $('<input type="text" value="" class="inputmask" />');
        if (this.input.attr('readonly')) {
            mask.attr('readonly', 'readonly');
        }
        mask.insertBefore('#' + id);

        this.mask = this.wrap.find('.inputmask');
        this.updateMask();

        // Just a safety guard, if for any reason someone focuses on the mask input
        // shift focus automatically to the input focus.
        // This is not entirely accessible, but it will do the job for the time being.
        this.mask.focus(function(e){
            input.focus();
        });

        // Watch the unmask changes.
        this.unmaskinput.change($.proxy(this.unmask, this));

        // Update the astrix with each key up.
        this.input.keyup($.proxy(this.updateMask, this));
        this.input.mouseup();

        // Call the changed method when this element is changed.
        this.input.change($.proxy(this.changed, this));

        if (this.input.attr('required')) {
            var requiredDefer = $.Deferred();
            defereds.push(requiredDefer);

            require(['shezar_form/modernizr'], function(mod) {
                if (!mod.input.required) {
                    require(['shezar_form/polyfill_required-lazy'], function (poly) {
                        poly.init(id);
                        requiredDefer.resolve();
                    });
                } else {
                    requiredDefer.resolve();
                }
            });
        }
        if (this.input.attr('placeholder')) {
            var placeholderDefer = $.Deferred();
            defereds.push(placeholderDefer);

            require(['shezar_form/modernizr'], function(mod) {
                if (!mod.input.placeholder ) {
                    require(['shezar_form/polyfill_placeholder-lazy'], function (poly) {
                        poly.init(id);
                        placeholderDefer.resolve();
                    });
                } else {
                    placeholderDefer.resolve();
                }
            });
        }

        $.when.apply($, defereds).done(done);
    };

    /**
     * Updates the mask.
     */
    PasswordUnmaskElement.prototype.updateMask = function() {
        this.mask.val('●'.repeat(this.input.val().length));
    };

    /**
     * Unmasks the password.
     */
    PasswordUnmaskElement.prototype.unmask = function() {
        if (this.unmaskinput.is(":checked")) {
            this.input.parents('.shezar_form_element_passwordunmask').addClass('unmask-password');
        } else {
            this.input.parents('.shezar_form_element_passwordunmask').removeClass('unmask-password');
        }
    };

    /**
     * Returns the value of the password field.
     * @returns {String}
     */
    PasswordUnmaskElement.prototype.getValue = function() {
        if (this.input) {
            return this.input.val();
        }
        return null;
    };

    return PasswordUnmaskElement;

});