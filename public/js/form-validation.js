/**
 * Global jQuery Validate Configuration & Extensions
 * Cauchos Alfa — Bootstrap 4 & Select2 Validation Defaults
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined' || typeof $.validator === 'undefined') {
        console.warn('jQuery Validator plugin is not loaded.');
        return;
    }

    // Dynamic style injection to ensure Select2 invalid borders override theme !important rules
    (function () {
        if (typeof document === 'undefined') return;
        var styleId = 'form-validation-select2-css';
        if (!document.getElementById(styleId)) {
            var style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = [
                '.select2-container .select2-selection.border-danger,',
                '.select2-container .select2-selection--single.border-danger,',
                '.select2-container .select2-selection--multiple.border-danger {',
                '    border-color: #dc3545 !important;',
                '}'
            ].join('\n');
            document.head.appendChild(style);
        }
    })();

    // Configure global jQuery Validate defaults
    $.validator.setDefaults({
        errorElement: 'span',
        errorClass: 'invalid-feedback d-block',
        validClass: 'is-valid',
        ignore: ':hidden:not(.select2-hidden-accessible)',
        highlight: function (element, errorClass, validClass) {
            var $el = $(element);
            $el.addClass('is-invalid').removeClass(validClass || 'is-valid');
            if ($el.hasClass('select2-hidden-accessible')) {
                var $s2 = $el.next('.select2-container');
                if (!$s2.length) {
                    $s2 = $el.siblings('.select2-container');
                }
                $s2.find('.select2-selection').addClass('border-danger');
            }
        },
        unhighlight: function (element, errorClass, validClass) {
            var $el = $(element);
            $el.removeClass('is-invalid');
            if ($el.hasClass('select2-hidden-accessible')) {
                var $s2 = $el.next('.select2-container');
                if (!$s2.length) {
                    $s2 = $el.siblings('.select2-container');
                }
                $s2.find('.select2-selection').removeClass('border-danger');
            }
        },
        errorPlacement: function (error, element) {
            if (element.parent('.input-group').length) {
                error.insertAfter(element.parent());
            } else if (element.closest('.input-group').length) {
                error.insertAfter(element.closest('.input-group'));
            } else if (element.hasClass('select2-hidden-accessible')) {
                var $select2 = element.next('.select2-container');
                if (!$select2.length) {
                    $select2 = element.siblings('.select2-container');
                }
                error.insertAfter($select2.length ? $select2 : element);
            } else if (element.parent('.custom-file').length) {
                error.insertAfter(element.parent());
            } else if (element.closest('.custom-file').length) {
                error.insertAfter(element.closest('.custom-file'));
            } else if (element.closest('.custom-control').length) {
                error.insertAfter(element.closest('.custom-control'));
            } else if (element.closest('.form-check').length) {
                error.insertAfter(element.closest('.form-check'));
            } else if (element.parent().hasClass('d-flex') && !element.parent().is('form')) {
                error.insertAfter(element.parent());
            } else {
                error.insertAfter(element);
            }
        }
    });

    // Custom Geolocation Validators
    $.validator.addMethod(
        'coordLatitude',
        function (value, element) {
            if (this.optional(element)) {
                return true;
            }
            var val = String(value).trim().replace(',', '.');
            if (!/^[+-]?(\d+(\.\d+)?|\.\d+)$/.test(val)) {
                return false;
            }
            var num = parseFloat(val);
            return !isNaN(num) && num >= -90.0 && num <= 90.0;
        },
        'Por favor, ingrese una latitud válida (-90 a 90).'
    );

    $.validator.addMethod(
        'coordLongitude',
        function (value, element) {
            if (this.optional(element)) {
                return true;
            }
            var val = String(value).trim().replace(',', '.');
            if (!/^[+-]?(\d+(\.\d+)?|\.\d+)$/.test(val)) {
                return false;
            }
            var num = parseFloat(val);
            return !isNaN(num) && num >= -180.0 && num <= 180.0;
        },
        'Por favor, ingrese una longitud válida (-180 a 180).'
    );

    // Dynamic Select2 validation synchronization
    $(document).on('change', 'select.select2-hidden-accessible', function () {
        var $form = $(this).closest('form');
        if ($form.length && $form.data('validator')) {
            var validator = $form.validate();
            if ((validator.submitted && validator.submitted[this.name]) || $(this).hasClass('is-invalid')) {
                $(this).valid();
            }
        }
    });

    $(document).on('select2:close', 'select.select2-hidden-accessible', function () {
        var $form = $(this).closest('form');
        if ($form.length && $form.data('validator')) {
            var validator = $form.validate();
            if ((validator.submitted && validator.submitted[this.name]) || $(this).hasClass('is-invalid')) {
                $(this).valid();
            }
        }
    });

})(typeof jQuery !== 'undefined' ? jQuery : (typeof window !== 'undefined' ? window.jQuery : undefined));
