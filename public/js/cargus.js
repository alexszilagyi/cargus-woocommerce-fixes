jQuery(function ($) {
    let runningIndex = 0;

    // select2/selectWoo is not guaranteed to be on the page: themes and
    // optimisation plugins routinely dequeue it on the checkout. Every call
    // to it goes through these helpers, so a missing library degrades to the
    // native control instead of throwing and leaving the field empty.
    const select2Method = function () {
        if (typeof $.fn.select2 === 'function') {
            return 'select2';
        }
        if (typeof $.fn.selectWoo === 'function') {
            return 'selectWoo';
        }
        return null;
    };

    const enhanceSelect = function ($select, options) {
        const method = select2Method();
        if (method && $select.length) {
            $select[method](options);
            return true;
        }
        return false;
    };

    const destroySelect = function ($select) {
        const method = select2Method();
        if (method && $select.hasClass('select2-hidden-accessible')) {
            $select[method]('destroy');
        }
    };

    // Parse an admin-ajax answer that should be JSON. admin-ajax answers "0"
    // or an empty body when the handler bailed out; treat that as "nothing".
    const parseJson = function (data) {
        if (typeof data !== 'string') {
            return data;
        }
        try {
            return JSON.parse(data);
        } catch (e) {
            return null;
        }
    };

    // The element that carries the street-id / postal-numbers attributes:
    // the selected <option> of a street <select>, or the street <input>.
    const streetDataSource = function (section) {
        const street = $('[name="' + section + '_street"]');
        return street.prop('nodeName') === 'SELECT' ? street.find('option:selected') : street;
    };

    // Plain text street input, used whenever there is no usable street list.
    const streetTextInput = function (attr_name, attr_id, attr_placeholder, value) {
        return $('<input type="text" class="input-text" autocomplete="street-address" />').attr({
            name: attr_name,
            id: attr_id,
            placeholder: attr_placeholder || null,
        }).val(value || '');
    };
    // Replace the billing/sipping city field.
    const filterStates = function (section, initialRun) {
        initialRun = runningIndex === 0;

        const element = $('[name="' + section + '_city"]');
        if (element != null && ajax_var.locationsDropdown) {
            const type = element.prop('nodeName');
            const attr_class = element.attr('class');
            const attr_name = element.attr('name');
            const attr_id = element.attr('id');
            const attr_placeholder = element.attr('placeholder');
            const attr_autocomplete = element.attr('autocomplete');
            const elementValue = element.val();
            let defaultValue = false;
            const streetValue = $('[name="' + section + '_street"]').val();

            if ($('[name="' + section + '_country"]').val() == 'RO') {
                $('select[name="' + attr_name + '"]').empty();

                if ($('[name="' + section + '_street"]').parents(':eq(1)').is(":hidden")) {
                    $('[name="' + section + '_street"]').parents(':eq(1)').show();
                }

                if ($('[name="' + section + '_street_number"]').parents(':eq(1)').is(":hidden")) {
                    $('[name="' + section + '_street_number"]').parents(':eq(1)').show();
                }

                if (element.prop('nodeName') != 'SELECT') {
                    element.replaceWith('<select class="' + attr_class + '" name="' + attr_name + '"  id="' + attr_id + '"><option elementValue km="0">-</option></select>');
                }

                destroySelect($('select[name="' + attr_name + '"]'));

                $.ajax({
                    type: "POST",
                    url: ajax_var.url,
                    cache: true,
                    data: {
                        action: 'cargus_get_regions',
                        judet: $('[name="' + section + '_state"]').val(),
                        val: elementValue,
                        type: type,
                        security: ajax_var.nonce,
                    },
                    success: function (data) {
                        data = parseJson(data);

                        // No locality list for this county (API down, empty
                        // cache, no token): give the customer a text field
                        // for the locality instead of a select with only "-".
                        if (!data || typeof data !== 'object' || Object.keys(data).filter(function (k) { return k !== '-'; }).length === 0) {
                            const citySelect = $('select[name="' + attr_name + '"]');
                            destroySelect(citySelect);
                            citySelect.replaceWith($('<input type="text" class="input-text" />').attr({
                                name: attr_name,
                                id: attr_id,
                                placeholder: attr_placeholder || null,
                                autocomplete: attr_autocomplete || 'address-level2',
                            }).val(initialRun && elementValue && elementValue !== '-' ? elementValue : ''));
                            $('[name="' + section + '_city"]').trigger('change', {'initial': initialRun});
                            return;
                        }

                        for (const [key, value] of Object.entries(data)) {
                            $('select[name="' + attr_name + '"]').append($("<option></option>").attr({
                                'km': value[0],
                                'locality-id': value[1],
                                'value': value[2],
                                'postal-code': value[3],
                                'saturday-delivery': value[4]
                            }).text(key));

                            if (initialRun && elementValue == key) {
                                defaultValue = true;
                                $('[name="' + section + '_city"] option[value="' + value[2] + '"]').attr('selected', 'selected');
                            }
                        }

                        if ($('[name="' + section + '_street"]').prop('nodeName') == 'SELECT') {
                            $('[name="' + section + '_street"]').empty();
                            destroySelect($('[name="' + section + '_street"]'));
                        } else {
                            $('[name="' + section + '_street"]').val('');
                        }

                        if (!initialRun) {
                            $('[name="' + section + '_postcode"]').val('');
                            $('[name="' + section + '_street_number"]').val('');
                        }

                        // make city field select2 (stays a native select when select2 is not loaded).
                        enhanceSelect($('select[name="' + attr_name + '"]'), {
                            width: '100%'
                        });

                        if (initialRun && defaultValue) {
                            setTimeout(function () {
                                /** Set billing city default value. */
                                $('[name="' + section + '_city"]').val(elementValue).trigger('change', {'initial': true});
                                $('body').trigger(section + '_default_load', {'streetName': streetValue});

                            }, 100);
                        }

                    }
                });
            } else {
                if (type != 'INPUT') {
                    if ($('[name="' + section + '_city"]').hasClass('select2-hidden-accessible')) {
                        $('[name="' + section + '_city"]').empty();
                        destroySelect($('[name="' + section + '_city"]'));
                    }

                    $('[name="' + section + '_city"]').replaceWith('<input type="text" class="input-text" name="' + attr_name + '"  id="' + attr_id + '"' + (attr_placeholder ? ' placeholder="' + attr_placeholder + '"' : '') + ' autocomplete="' + attr_autocomplete + '" value="" />');
                }

                if ($('[name="' + section + '_street"]').prop('nodeName') != 'INPUT') {
                    if ($('[name="' + section + '_street"]').hasClass('select2-hidden-accessible')) {
                        $('[name="' + section + '_street"]').empty();
                        destroySelect($('[name="' + section + '_street"]'));
                    }

                    $('[name="' + section + '_street"]').replaceWith('<input type="text" class="input-text" name="' + section + '_street"  id="' + section + '_street" value="" />');
                }

                $('[name="' + section + '_street"]').parents(':eq(1)').hide();
                $('[name="' + section + '_street_number"]').val('');
                $('[name="' + section + '_street_number"]').parents(':eq(1)').hide();

            }
        }

        runningIndex++;
    }

    if (ajax_var.locationsDropdown) {
        // Replace the billing city field on billing state change.
        $(document).on('change', '[name="billing_state"]', function (e, params) {
            filterStates('billing');
        });

        // Replace the shipping city field on shipping state change.
        $(document).on('change', '[name="shipping_state"]', function () {
            filterStates('shipping');
        });

        // Replace the billing city field on billing country change.
        $(document).on('change', '[name="billing_country"]', function (e, params) {
            hideStreetFields('billing');
            filterStates('billing');
        });

        // Replace the shipping city field on shipping country change.
        $(document).on('change', '[name="shipping_country"]', function () {
            hideStreetFields('shipping');
            filterStates('shipping');
        });

        if (ajax_var.streetDropdown) {
            // Hide the address field 1 on document ready.
            $(document).ready(function () {
                hideAddressField('billing');
                hideAddressField('shipping');
                hideStreetFields('billing');
                hideStreetFields('shipping');
            });

            // Replace the billing city field on billing country change.
            $(document).on('change', '[name="billing_country"]', function (e, params) {
                hideAddressField('billing');
            });

            // Replace the shipping city field on shipping country change.
            $(document).on('change', '[name="shipping_country"]', function () {
                hideAddressField('shipping');
            });

            // Map a cargus_get_streets answer to select2 results. The answer
            // can be "null", "0" or an empty body when the lookup failed.
            const streetResults = function (data) {
                if (!Array.isArray(data)) {
                    return [];
                }
                return data.map(function (item) {
                    return {
                        id: item.StreetName,
                        streetId: item.StreetId,
                        text: item.StreetName,
                        PostalNumbers: item.PostalNumbers
                    };
                });
            };

            // Native fallback for the street list when select2 is not loaded:
            // a free-text input with a <datalist> of the matching streets.
            // Whatever the customer types is accepted; when it matches a known
            // street the street id and postal numbers are kept on the input so
            // the postcode lookup and the AWB street id still work.
            const attachStreetSuggestions = function (section) {
                const input = $('input[name="' + section + '_street"]');
                if (!input.length) {
                    return;
                }

                const listId = input.attr('id') + '_list';
                let list = $('#' + listId);
                if (!list.length) {
                    list = $('<datalist></datalist>').attr('id', listId);
                    input.after(list);
                }
                input.attr('list', listId);

                let known = {};
                let timer = null;
                let lastTerm = '';

                const remember = function () {
                    const match = known[$.trim(input.val()).toLowerCase()];
                    if (match) {
                        input.attr('street-id', match.StreetId);
                        input.attr('postal-numbers', match.PostalNumbers);
                    } else {
                        input.removeAttr('street-id').removeAttr('postal-numbers');
                    }
                };

                input.off('.cargusStreets').on('input.cargusStreets', function () {
                    remember();
                    const term = $.trim(input.val());
                    if (term.length < 2 || term === lastTerm) {
                        return;
                    }
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        lastTerm = term;
                        $.ajax({
                            type: 'POST',
                            url: ajax_var.url,
                            dataType: 'json',
                            data: {
                                q: term,
                                action: 'cargus_get_streets',
                                city: $('[name="' + section + '_city"]').find('option:selected').attr('locality-id'),
                                security: ajax_var.nonce,
                            },
                            success: function (data) {
                                list.empty();
                                if (!Array.isArray(data)) {
                                    return;
                                }
                                data.slice(0, 50).forEach(function (item) {
                                    known[String(item.StreetName).toLowerCase()] = item;
                                    list.append($('<option></option>').attr('value', item.StreetName));
                                });
                                remember();
                            }
                        });
                    }, 200);
                }).on('change.cargusStreets', remember);
            };

            // Replace the billing/shipping street field.
            const filterCities = function (section, initialRun = false) {
                const street = $('[name="' + section + '_street"]');
                const type = street.prop('nodeName');
                const attr_class = street.attr('class');
                const attr_name = street.attr('name');
                const attr_id = street.attr('id');
                const attr_placeholder = street.attr('placeholder');

                const city = $('[name="' + section + '_city"]');
                const cityIsSelect = city.prop('nodeName') === 'SELECT';
                const cityPostalCode = city.find('option:selected').attr('postal-code');
                const cityLocalityId = city.find('option:selected').attr('locality-id');

                // Streets can only be looked up for a locality picked from the
                // Cargus list (a select with a locality id). A typed locality has
                // no street list, so the street is free text.
                const cityHasStreetList = cityIsSelect && cityLocalityId !== undefined && cityLocalityId !== '' &&
                    jQuery.inArray(cityPostalCode, [undefined, '0', 'NULL']) !== -1;

                if (street.length && $('[name="' + section + '_country"]').val() == 'RO') {
                    destroySelect(street);
                    if (street.prop('nodeName') == 'SELECT') {
                        street.empty();
                    }

                    if (cityHasStreetList && select2Method()) {
                        let streetSelect = $('[name="' + section + '_street"]');
                        if (streetSelect.prop('nodeName') != 'SELECT') {
                            streetSelect.replaceWith('<select class="' + attr_class + '" name="' + attr_name + '"  id="' + attr_id + '"></select>');
                            streetSelect = $('select[name="' + section + '_street"]');
                        }

                        enhanceSelect(streetSelect, {
                            language: {
                                searching: function () {
                                    return "Procesare...";
                                },
                                errorLoading: function () {
                                    return "Procesare...";
                                },
                                noResults: function () {
                                    return "Nu a fost gasit niciun rezultat.";
                                },
                                inputTooShort: function () {
                                    return "Introduceti cel putin 2 caractere pentru strazi.";
                                },
                            },
                            ajax: {
                                type: "POST",
                                url: ajax_var.url,
                                dataType: 'json',
                                delay: 100,

                                data: function (params) {
                                    return {
                                        q: params.term, // search term
                                        action: 'cargus_get_streets',
                                        city: $('[name="' + section + '_city"]').find('option:selected').attr('locality-id'),
                                        security: ajax_var.nonce,
                                        type: type,
                                    };
                                },
                                processResults: function (data, params) {
                                    const results = streetResults(data);
                                    // A street that is missing from the Cargus list
                                    // (or a locality whose list is empty) can still
                                    // be entered as typed. It is offered after the
                                    // known streets, so the first match stays the
                                    // highlighted one.
                                    const term = $.trim((params && params.term) || '');
                                    const exists = results.some(function (r) {
                                        return String(r.text).toLowerCase() === term.toLowerCase();
                                    });
                                    if (term.length >= 2 && !exists) {
                                        results.push({id: term, text: term});
                                    }
                                    return {
                                        results: results
                                    };
                                },
                                cache: true

                            },
                            minimumInputLength: 2,
                            placeholder: '-',
                            templateResult: formatOption,
                            templateSelection: formatOptionSelection,
                            width: '100%',
                        });

                        streetSelect.off('change.cargus').on('change.cargus', function (e) {
                            const selected = $(this)[select2Method()]('data')[0];
                            const option = $(this).find('option:selected');
                            if (selected && selected.PostalNumbers) {
                                option.attr('postal-numbers', selected.PostalNumbers);
                                option.attr('street-id', selected.streetId);
                            }
                        });

                        if (!initialRun) {
                            $('[name="' + section + '_postcode"]').val('');
                        }

                    } else {
                        const currentStreet = $('[name="' + section + '_street"]');
                        currentStreet.replaceWith(streetTextInput(attr_name, attr_id, attr_placeholder, ''));

                        if (cityHasStreetList) {
                            // select2 is not available: native input + suggestions.
                            attachStreetSuggestions(section);
                            if (!initialRun) {
                                $('[name="' + section + '_postcode"]').val('');
                            }
                        } else {
                            $('#' + attr_id + '_list').remove();
                            if (cityIsSelect && cityPostalCode !== undefined && jQuery.inArray(cityPostalCode, ['0', 'NULL']) === -1) {
                                $('[name="' + section + '_postcode"]').val(cityPostalCode);
                            }
                        }
                    }

                    if (!initialRun) {
                        $('[name="' + section + '_street_number"]').val('');
                    }

                    function formatOption(city) {
                        let $container = $(
                            "<div class='select2-result-title'></div>"
                        ).text(city.text);

                        return $container;
                    }

                    function formatOptionSelection(city) {
                        return city.text;
                    }
                } else {
                    street.replaceWith(streetTextInput(attr_name, attr_id, attr_placeholder, ''));
                }
            }

            // Replace the shipping street field on shipping_city field change.
            $(document).on('change', '[name="shipping_city"]', function (e, params) {
                if (params && params.initial) {
                    filterCities('shipping', true);
                } else {
                    filterCities('shipping');
                }

                // set the additional cargus delivery status.
                sendAdditionalDelivery('shipping');
            });

            // Replace the billing street field on billing_city field change.
            $(document).on('change', '[name="billing_city"]', function (e, params) {
                if (params && params.initial) {
                    filterCities('billing', true);
                } else {
                    filterCities('billing');
                }

                // set the additional cargus delivery status.
                sendAdditionalDelivery('billing');
            });

            // Add saturday delivery.
            const sendAdditionalDelivery = function (section) {
                const city = $('#' + section + '_city')
                if (city.prop('nodeName') === 'SELECT') {
                    const citySaturdayDelivery = city.find('option:selected').attr('saturday-delivery');
                    const cityId               = city.find('option:selected').attr('locality-id');
                    const cityName             = city.find('option:selected').val();
                    if ( $( 'input[name="' + section + '_city_saturday_delivery"]' ).length ) {
                        $( 'input[name="' + section + '_city_saturday_delivery"]' ).val( citySaturdayDelivery );
                    } else {
                        $('[name="checkout"]').append("<input type='hidden' name='" + section + "_city_saturday_delivery' value='" + citySaturdayDelivery + "' />");
                    }

                    // Ajax request for city saturday delivery. TO DO.
                    // $.ajax({
                    //     type: 'POST',
                    //     url: ajax_var.url,
                    //     data: {
                    //         'action': 'cargus_saturday_delivery',
                    //         'saturday_delivery': citySaturdayDelivery,
                    //         'security': ajax_var.nonce,
                    //     },
                    //     success: function (result) {
                    //     }
                    // });

                    let cityPre10Delivery = false;
                    let cityPre12Delivery = false;
                    //Ajax request for city pre10 and pre12 delivery.
                    $.ajax({
                        type: 'POST',
                        url: ajax_var.url,
                        data: {
                            'action': 'cargus_pre_delivery',
                            'locality_id': cityId,
                            'locality_name': cityName,
                            'security': ajax_var.nonce,
                        },
                        success: function (result) {
                            result = JSON.parse(result);
                            cityPre10Delivery = result.pre10;
                            cityPre12Delivery = result.pre12;

                            if ( $( 'input[name="' + section + '_city_pre10_delivery"]' ).length ) {
                                $( 'input[name="' + section + '_city_pre10_delivery"]' ).val( cityPre10Delivery );
                            } else {
                                $('[name="checkout"]').append("<input type='hidden' name='" + section + "_city_pre10_delivery' value='" + cityPre10Delivery + "' />");
                            }

                            if ( $( 'input[name="' + section + '_city_pre12_delivery"]' ).length ) {
                                $( 'input[name="' + section + '_city_pre12_delivery"]' ).val( cityPre12Delivery );
                            } else {
                                $('[name="checkout"]').append("<input type='hidden' name='" + section + "_city_pre12_delivery' value='" + cityPre12Delivery + "' />");
                            }
                        }
                    });


                }

                return true;
            }

            // Replace the billing street field on billing_cities_generated event.
            $('body').on('billing_default_load', function (e, params) {
                setTimeout(function () {
                    streetAddAjaxOption('billing', params);
                }, 50);
            });

            $('body').on('shipping_default_load', function (e, params) {
                setTimeout(function () {
                    streetAddAjaxOption('shipping', params);
                }, 50);
            });

            // Add and select the default value if there is one.
            const streetAddAjaxOption = function (section, params) {
                const streetName = params && params.streetName;
                if (!streetName || streetName === '0') {
                    return;
                }

                // Free-text street (no street list, or no select2): restore the
                // saved value as typed.
                if ($('[name="' + section + '_street"]').prop('nodeName') !== 'SELECT') {
                    $('[name="' + section + '_street"]').val(streetName).trigger('change');
                    return;
                }

                $.ajax({
                    type: "POST",
                    url: ajax_var.url,
                    dataType: 'json',
                    data: {
                        action: 'cargus_get_streets',
                        city: $('[name="' + section + '_city"]').find('option:selected').attr('locality-id'),
                        security: ajax_var.nonce,
                        val: streetName
                    },
                    success: function (data) {
                        if ( ! Array.isArray(data) || ! data.length ) {
                            // Saved street is not in the Cargus list: keep it as typed.
                            const option = new Option(String(streetName).replace(/^Str\. /, ''), String(streetName).replace(/^Str\. /, ''), true, true);
                            $('[name="' + section + '_street"]').append(option).trigger('change');
                            return;
                        }
                        if ( null !== data ) {
                            const option = new Option(data[0].StreetName, data[0].StreetName, true, true);
                            $('[name="' + section + '_street"]').append(option);
                            $('[name="' + section + '_street"] option').attr('postal-numbers', data[0].PostalNumbers);
                            $('[name="' + section + '_street"] option').attr('street-id', data[0].StreetId);

                            $('[name="' + section + '_street"]').val(data[0].StreetName).trigger('change');

                            // manually trigger the `select2:select` event
                            $('[name="' + section + '_street"]').trigger({
                                type: 'select2:select',
                                params: {
                                    data: data[0]
                                }
                            });
                        }
                    },
                });
            }

            // Fill in the postal code field.
            const filterStreetNumbers = function (section) {

                if ($('[name="' + section + '_country"]').val() != 'RO') {
                    //country is not Romania or Bulgaria, no need for more searches
                    return true;
                }

                const element = $('[name="' + section + '_street_number"]');
                const strNumber = parseInt(element.val());

                if (jQuery.inArray($('[name="' + section + '_city"]').find('option:selected').attr('postal-code'), ['0', 'NULL']) === -1) {
                    //city has postal code, no need for more searches
                    return true;
                }

                const postalNumbers = streetDataSource(section).attr('postal-numbers');

                if (postalNumbers != undefined && !isNaN(strNumber)) {
                    let noFound = 0;
                    let indexes = [];
                    $.each(JSON.parse(postalNumbers), function (key, value) {
                        if (
                            parseInt(value.FromNo) <= strNumber && parseInt(value.ToNo) >= strNumber ||
                            parseInt(value.FromNo) == strNumber && parseInt(value.ToNo) == 10000
                        ) {
                            indexes.push({'key': key, 'value': value.FromNo});
                            noFound++;
                        }
                    });

                    let index = 0;
                    if (noFound > 1) {
                        if (0 === strNumber % 2) {
                            index = getIndexByValue(indexes, index, true);
                            $('[name="' + section + '_postcode"]').val(JSON.parse(postalNumbers)[index].PostalCode);
                        } else {
                            index = getIndexByValue(indexes, index, false);
                            $('[name="' + section + '_postcode"]').val(JSON.parse(postalNumbers)[index].PostalCode);
                        }
                    } else if (noFound === 1) {
                        $('[name="' + section + '_postcode"]').val(JSON.parse(postalNumbers)[indexes[0]['key']].PostalCode);
                    }
                }
            }

            // Get the array item index by array item value.
            const getIndexByValue = function (array, index, odd = true) {
                $.each(array, function (key, value) {
                    if (odd) {
                        if (value.value % 2 == 0) {
                            index = value.key;
                        }
                    } else if (!odd) {
                        if (value.value % 2 != 0) {
                            index = value.key;
                        }
                    }
                });

                return index;
            }

            // Set the postal code and change the billing_address_1 value on billing street field change.
            $(document).on('change', '[name="billing_street"]', function () {
                setAddressValue('billing');
                sendStreetId('billing');
            });

            // Set the postal code and change the billing_address_1 value on billing street number field blur.
            $(document).on('blur', '[name="billing_street_number"]', function () {
                setAddressValue('billing');
                filterStreetNumbers('billing');
            });

            // Set the postal code and change the shipping_address_1 value on shipping street field change.
            $(document).on('change', '[name="shipping_street"]', function () {
                setAddressValue('shipping');
                sendStreetId('shipping');
            });

            // Set the postal code and change the shipping_address_1 value on shipping street number field blur.
            $(document).on('blur', '[name="shipping_street_number"]', function () {
                setAddressValue('shipping');
                filterStreetNumbers('shipping');
            });

            // Set the shipping/billing address 1 value function.
            const setAddressValue = function (section) {
                const street = $('[name="' + section + '_street"]');
                const streetValue = $.trim(street.val() || '');
                const numberValue = $.trim($('[name="' + section + '_street_number"]').val() || '');

                // Rebuilt from scratch every time, so repeated blurs do not keep
                // appending ", Nr. ..." to the previous value.
                if ('' === streetValue) {
                    return;
                }

                // "Str. " only for a street picked from the Cargus list; a street
                // typed by the customer is used exactly as typed.
                const picked = 'SELECT' == street.prop('nodeName') ? street.find('option:selected') : $();
                let address = (picked.length && picked.attr('street-id') !== undefined) ? 'Str. ' + $.trim(picked.text()) : streetValue;
                if ('' !== numberValue) {
                    address += ', Nr. ' + numberValue;
                }
                $('[name="' + section + '_address_1"]').val(address);
            }

            // Set the shipping/billing address 1 value function.
            $(document).on('submit', 'form.woocommerce-checkout', function () {
                setAddressValue('billing');
                setAddressValue('shipping');
            });

            // WooCommerce serialises the form in its own submit handler, which
            // runs before the delegated submit handler above. checkout_place_order
            // fires before that serialisation, so address_1 is current when sent.
            $('form.checkout').on('checkout_place_order', function () {
                setAddressValue('billing');
                setAddressValue('shipping');
                return true;
            });

            // Add Street ID to form data.
            const sendStreetId = function (section) {
                const streetId = streetDataSource(section).attr('street-id');
                const hidden = $('input[name="' + section + '_street_id"]');

                // Only a street picked from the Cargus list has an id. A typed
                // street must not send a stale id or the string "undefined".
                if (streetId !== undefined && /^[0-9]+$/.test(String(streetId))) {
                    if (hidden.length) {
                        hidden.val(streetId);
                    } else {
                        $('[name="checkout"]').append($("<input type='hidden' />").attr({name: section + '_street_id', value: streetId}));
                    }
                } else if (hidden.length) {
                    hidden.remove();
                }

                return true;
            }
        }
    }

    // Hide the address field 1 function.
    const hideAddressField = function (section) {
        if ($('[name="' + section + '_country"]').val() == 'RO' && ajax_var.streetDropdown) {
            $('#' + section + '_address_1_field').hide();
        } else {
            $('#' + section + '_address_1_field').show();
        }
    }

    // Hide the street and street number fields function.
    const hideStreetFields = function (section) {
        if ($('[name="' + section + '_country"]').val() !== 'RO') {
            $('#' + section + '_street_field').hide();
            $('#' + section + '_street_number_field').hide();
        } else {
            $('#' + section + '_street_field').show();
            $('#' + section + '_street_number_field').show();
        }
    }

    if (ajax_var.bgLocationsDropdown){
        console.log('am nomenclator pe intl');
        // Replace the billing city field on billing state change.
        $(document).on('change', '[name="billing_state"]', function (e, params) {
            filterBGStates('billing');
        });

        // Replace the shipping city field on shipping state change.
        $(document).on('change', '[name="shipping_state"]', function () {
            filterBGStates('shipping');
        });

        // Replace the billing city field on billing country change.
        $(document).on('change', '[name="billing_country"]', function (e, params) {
            hideAddressField('billing');
            hideStreetFields('billing');
            filterStates('billing');
        });

        // Replace the shipping city field on shipping country change.
        $(document).on('change', '[name="shipping_country"]', function () {
            hideAddressField('shipping');
            hideStreetFields('shipping');
            filterStates('shipping');
        });
        const filterBGStates = function (section, initialRun) {
            initialRun = runningIndex === 0;

            const element = $('[name="' + section + '_city"]');
            if (element != null && ajax_var.locationsDropdown) {
                const type = element.prop('nodeName');
                const attr_class = element.attr('class');
                const attr_name = element.attr('name');
                const attr_id = element.attr('id');
                const attr_placeholder = element.attr('placeholder');
                const attr_autocomplete = element.attr('autocomplete');
                const elementValue = element.val();
                let defaultValue = false;
                const streetValue = $('[name="' + section + '_street"]').val();

                if($('[name="' + section + '_country"]').val() == 'BG') {
                    $.ajax({
                        type: "POST",
                        url: ajax_var.url,
                        cache: true,
                        data: {
                            action: 'cargus_get_regions_intl_bulgaria',
                            judet: $('[name="' + section + '_state"]').val(),
                            val: elementValue,
                            type: type,
                            security: ajax_var.nonce,
                        },
                        success: function (data) {

                            if(data === "false" || data === false) {
                                return;
                            }
            
                            $('select[name="' + attr_name + '"]').empty();
                            if (element.prop('nodeName') != 'SELECT') {
                                element.replaceWith('<select class="' + attr_class + '" name="' + attr_name + '"  id="' + attr_id + '"><option elementValue km="0">-</option></select>');
                            }
        
                            if ($('select[name="' + attr_name + '"]').hasClass('select2-hidden-accessible')) {
                                $('select[name="' + attr_name + '"]').select2('destroy');
                            }

                            data = JSON.parse(data);

                            for (const [key, value] of Object.entries(data)) {
                                // console.log(value);
                                $('select[name="' + attr_name + '"]').append($("<option></option>").attr({
                                    'locality-id': value['id'],
                                    'value': key,
                                    'postal-code': value['zipCode'],
                                }).text(key));

                                if (initialRun && elementValue == key) {
                                    defaultValue = true;
                                    $('[name="' + section + '_city"] option[value="' + key + '"]').attr('selected', 'selected');
                                }
                            }

                            if (!initialRun) {
                                $('[name="' + section + '_postcode"]').val();
                            }

                            // make city field select2.
                            $('select[name="' + attr_name + '"]').select2({
                                width: '100%'
                            });

                            if (initialRun && defaultValue) {
                                setTimeout(function () {
                                    /** Set billing city default value. */
                                    $('[name="' + section + '_city"]').val(elementValue).trigger('change', {'initial': true});
                                    $('body').trigger(section + '_default_load', {'streetName': streetValue});

                                }, 100);
                            }

                        }
                    });
                }
            }

            runningIndex++;
        }

        // Replace the shipping city field on shipping_city field change.
        $(document).on('change', '[name="shipping_city"]', function () {
            if($('[name="shipping_country"]').val() == 'BG')
                filterBGZipCode('shipping');
        });

        // Replace the billing street field on billing_city field change.
        $(document).on('change', '[name="billing_city"]', function () {
            if($('[name="billing_country"]').val() == 'BG')
                console.log('test1');
                filterBGZipCode('billing');
        });
        
        // Event to fetch postalcodes associated with a city in Bulgaria.
        const filterBGZipCode = function (section) {
            console.log('test');
            const selectedCountry = $('[name="' + section + '_country"]').val(); // Get the selected country
            if (selectedCountry !== 'BG') {
                return;
            }

            const selectedCity = $('[name="' + section + '_city"]').val();
            
            $.ajax({
                type: "POST",
                url: ajax_var.url, // Ensure this points to the correct AJAX endpoint URL
                data: {
                    action: 'get_cargus_bulgaria_zipCode',  // Your server-side action to get the postal code
                    city: selectedCity,
                    security: ajax_var.nonce  // Include nonce for security if needed
                },
                success: function(response) {
                    // Parse the response assuming it is a JSON object
                    try {
                        if (response === 'false' || response === false) {
                            return;
                        }

                        const data = JSON.parse(response);
                        const postalCode = data.zipCode;

                        $('[name="' + section + '_postcode"]').val(postalCode);
                        
                    } catch (e) {
                        console.error('Error parsing postal code response:', e);
                    }
                },
                error: function(xhr, status, error) {
                    if (useMockupMapping && hardcodedPostalCodes[selectedCity]) {
                        $('[name="' + section + '_postcode"]').val("00000");
                    }
                }
            });
        }
    }

    // Change billing or shipping address field in order to force recalculate shipping fee.
    const forceShippingRecalculation = function () {
        // fac asta pentru ca daca nu modific un string din adresa, nu se recalculeaza transpprtul.
        if ($('#ship-to-different-address-checkbox:checked').length > 0) {
            const shipping_address = $('#shipping_address_1').val();
            const shipping_last = shipping_address.substr(shipping_address.length - 1);
            if (shipping_last == '.') {
                $('#shipping_address_1').val($('#shipping_address_1').val().slice(0, -1));
            } else {
                $('#shipping_address_1').val(shipping_address + '.');
            }
        } else {
            const billing_address = $('#billing_address_1').val();
            const billing_last = billing_address.substr(billing_address.length - 1);
            if (billing_last == '.') {
                $('#billing_address_1').val($('#billing_address_1').val().slice(0, -1));
            } else {
                $('#billing_address_1').val(billing_address + '.');
            }
        }

        // trigger functia care face refresh checkout-ului
        $('body').trigger('update_checkout');
    }

    // Force shipping recalculation only if the cargus fixed price is not set, and we have cargus selected as a shipping method.
    $(document).on('change', '[name="payment_method"]', function () {
        if (jQuery.inArray($('input[name^="shipping_method"]:checked').val(), ['cargus', 'cargus_ship_and_go']) === -1) {
            //exit if shipping method is other than cargus shipping.
            return;
        }

        if (ajax_var.freeShippingPrice !== '' && ajax_var.freeShippingPrice < ajax_var.cartSubtotal) {
            //exit if cart subtotal is bigger than free shipping price.
            return;
        }

        const addressCheckbox = $('ship-to-different-address-checkbox');
        if (ajax_var.fixedPrice !== '' ||
            (!addressCheckbox.is(":checked") && $('input#billing_state').val() === 'B' && ajax_var.fixedPriceBucharest !== '') ||
            (addressCheckbox.is(":checked") && $('input#shipping_state').val() === 'B' && ajax_var.fixedPriceBucharest !== '')
        ) {
            //exit if I have fixe shipping price.
            return;
        }

        // force shipping price recalculation.
        forceShippingRecalculation();
    });
});
