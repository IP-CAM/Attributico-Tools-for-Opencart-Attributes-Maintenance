// Extract product_id from URL
const params = new window.URLSearchParams(window.location.search);
const product_id = (params.get('product_id'));
const token = (params.get('token'));
const user_token = (params.get('user_token'));
const base_url = "index.php?route=module/attributico";
//const common_url = "index.php?route=common/filemanager";

let product_attribute_id = new Array();
//const extension = '<?php echo $extension; ?>'; // для v2.3 другая структура каталогов                  
//const token = '<?php echo $token;?>';
//const user_token = '<?php echo $token;?>';
let add_category_attribute = false;
let remove_category_attribute = 'Remove attributes for this category?';
let splitter = '/';
let method;
let multi = false;

function getServPanel(jQuery) {
    jQuery.ajax({
        data: {
            'token': token,
            'user_token': user_token
        },
        url: base_url + '/getServPanel',
        dataType: "json",
        success: function (json) {
            splitter = json.splitter
            add_category_attribute = json.attributico_autoadd
            remove_category_attribute = json.remove_category_attribute

            $('#serv-panel').append(json.serv_panel)
            $('#attach-attribute').html("<i class='fa fa-plus'></i> " + json.attach_category_attributes)

            method = $('#method-view option:selected').val()

            // Set attribute values display mode when start
            if (localStorage.getItem('display_attribute') == 'template') {
                $('#values-view').removeClass('btn-info');
                $('#template-view').addClass('btn-info');
            } else {
                $('#values-view').addClass('btn-info');
                $('#template-view').removeClass('btn-info');
            }

            // Set filter values display mode when start and loadValues
            if (localStorage.getItem('filter-values') !== "all") {
                $("input[name=filter-values][value=" + localStorage.getItem('filter-values') + "]").trigger('click');
            } else {
                loadValues()
            }

            // Set each textarea edit mode with multi
            if (localStorage.getItem('multi')) {
                multi = (/true/i).test(localStorage.getItem('multi'))
                $('#multi').prop('checked', multi);
                $('#multi').parent().addClass(multi ? 'active' : '');
            }
        },
        error: (err) => console.log(err)
    });
}

function newCategory(category_id) {
    $.each(product_attribute_id, function (key, attribute_id) {
        addAttributeDuty(attribute_id, key);
    });
    $.ajax({
        data: {
            'token': token,
            'user_token': user_token,
            'category_id': category_id
        },
        url: base_url + '/getCategoryAttributes',
        dataType: 'json',
        success: function (json) {
            if (add_category_attribute) {
                $.each(json, (key, attribute) => {
                    if (!in_array(String(attribute['attribute_id']), product_attribute_id)) {
                        var row = attribute_row;
                        addAttribute(String(attribute['attribute_id']));
                        $('input[name="product_attribute[' + row + '][name]"]').val(attribute['name']);
                        $('input[name="product_attribute[' + row + '][attribute_id]"]').val(String(attribute['attribute_id']));
                        $('#group-name' + row).remove()
                        $('input[name=\'product_attribute[' + row + '][name]\']').parent().prepend('<label id=group-name' + row + '>' + attribute['group_name'] + '</label>')
                        makeValuesList(String(attribute['attribute_id']), row);
                        addAttributeDuty(String(attribute['attribute_id']), row);
                        product_attribute_id.push(String(attribute['attribute_id']));
                    }
                });
            }
        }
    });
}

function removeCategoryAttribute(category_id) {
    $.ajax({
        data: {
            'token': token,
            'user_token': user_token,
            'category_id': category_id,
            'categories': getSelectedCategories()
        },
        url: base_url + '/removeCategoryAttributes',
        dataType: 'json',
        success: function (json) {
            if (confirm(remove_category_attribute)) {
                json.forEach(attribute => {
                    $('[id ^= attribute-row]').each((row, tr) => {
                        if ($(tr).find('[name *= attribute_id]').val() === String(attribute['attribute_id'])) {
                            $(tr).remove()
                            product_attribute_id = product_attribute_id.filter(item => item !== String(attribute['attribute_id']));
                            attribute_row--
                        }
                    })
                });
            }
        }
    });
}

function in_array(value, array) {
    for (var i = 0; i < array.length; i++) {
        if (array[i] == value) return true;
    }
    return false;
}

function getSelectedCategories() {
    var selKeys = [];
    $('input[name=\'product_category[]\']').each(function (indx, element) {
        if ($(this).is(":checked") || ($(this).prev().hasClass('fa-minus-circle') && $(this).val() != 0)) {
            selKeys.push($(this).val());
        }
    });
    selKeys.push($('select[name=\'main_category_id\']').val())
    selKeys = [...new Set(selKeys)]
    return selKeys;
}

function loadValues() {
    $('#attribute tbody tr').each(function (index, element) {
        var attribute_id = $('[name="product_attribute\[' + index + '\]\[attribute_id\]"]').val();
        makeValuesList(attribute_id, index);
        product_attribute_id.push(attribute_id);
    });
}

function makeValuesList(attribute_id, attribute_row) {
    $.ajax({
        data: {
            'token': token,
            'user_token': user_token,
            'attribute_id': attribute_id,
            'attribute_row': attribute_row,
            'view_mode': localStorage.getItem('display_attribute'),
            'categories': $('input[id=\'filter-category\']').is(":checked") ? getSelectedCategories() : [],
            'filter_values': localStorage.getItem('filter-values')
        },
        url: base_url + '/getValuesList',
        dataType: 'json',
        success: function (json) {
            $.each(json, function (language_id, select) {
                var textarea = $('textarea[name="product_attribute\[' + attribute_row + '\]\[product_attribute_description\]\[' + language_id + '\]\[text\]"]');
                $('select[language_id="' + language_id + '"]', textarea.parent()).remove();
                textarea.before(select);
                textarea.attr('rows', 3);
            });
        }
    });
}

function addAttributeDuty(attribute_id, current_row) {
    $.ajax({
        data: {
            'token': token,
            'user_token': user_token,
            'attribute_id': attribute_id,
            'method': method
        },
        url: base_url + '/getAttributeDuty',
        dataType: 'json',
        success: function (json) {
            setTextareaDuty(json, current_row)
        }
    });
}

function setTextareaDuty(json, currentRow) {
    $.each(json, function (language_id, duty) {
        let textarea = $('textarea[name="product_attribute\[' + currentRow + '\]\[product_attribute_description\]\[' + language_id + '\]\[text\]"]');
        switch (method) {
            case "clean":
                textarea.val('');
                break;
            case "unchange":
                break;
            case "overwrite":
                if (duty != '')
                    textarea.val(duty);
                break;
            case "ifempty":
                if (textarea.val() == '')
                    textarea.val(duty);
                break;
            default:
                break;
        }
    });
}

function setSelectedValue() {
    const select = $(this);
    const row = select.attr("attribute_row") || select.next('textarea').attr("name").match(/[0-9]+/)[0];
    if (localStorage.getItem('display_attribute') == 'template') {
        if (this.selectedIndex != 0) {
            if (localStorage.getItem('filter-values') == 'duty') {
                let attribute_id = select.attr("attribute_id")
                select.next('textarea').val(select.val());
                addAttributeDuty(attribute_id, row)
            } else {
                if (!multi) {
                    select.next('textarea').val(select.val());
                } else {
                    eachTextareaValue(row, select.val())
                }
            }
            this.selectedIndex = 0
        }
    } else {
        if (this.selectedIndex != 0) {
            if (!multi) {
                let textarea_val = select.next('textarea').val();
                textarea_val = (textarea_val == '') ? textarea_val : textarea_val + splitter;
                select.next('textarea').val(textarea_val + select.val());
            } else {
                eachTextareaValue(row, select.val())
            }
            this.selectedIndex = 0
        }
    }
}

function eachTextareaValue(currentRow, selectedVal) {
    $('textarea[name^="product_attribute\[' + currentRow + '\]\[product_attribute_description\]"]').each(function () {
        //console.log($(this).attr("name").match(/[0-9]+/g)[1]);    
        if (localStorage.getItem('display_attribute') == 'template') {
            $(this).val(selectedVal);
        } else {
            let textareaVal = ($(this).val() == '') ? $(this).val() : $(this).val() + splitter;
            $(this).val(textareaVal + selectedVal);
        }
    })
}

// Replace or add attribute after autocomplete select
function selectAttributeAutocomplete(row, oldId, newAttribute) {
    let index = product_attribute_id.indexOf(oldId)
    let newId = newAttribute.value;
    let groupName = newAttribute.category;

    if (~index) {
        product_attribute_id[index] = newId;
    } else {
        product_attribute_id.push(newId);
    }
    makeValuesList(newId, row);
    addAttributeDuty(newId, row);
    $('#group-name' + row).remove()
    $('input[name=\'product_attribute[' + row + '][name]\']').parent().prepend('<label id=group-name' + row + '>' + groupName + '</label>')
}

// Event Category onchange
$('body').on('change', 'input[name=\'product_category[]\'], select[name=\'main_category_id\']', function (e) {
    if ($(this).is(":checked") || (this.tagName == "SELECT" && $(this).val() != 0)) {
        newCategory($(this).val())
    } else {
        removeCategoryAttribute($(this).val())
    }
});
$('#product-category').on('click', '.fa-minus-circle', function () {
    let category_id = $(this).parent().find('[name *= product_category]').val()
    removeCategoryAttribute(category_id)
});

// Event apply value or template and set selected in textarea


$('#attribute tbody').on('change', 'select', setSelectedValue);

// Event change filter mode for attribute values
$('#serv-panel').on('change', 'input[type=\'radio\']', function () {
    localStorage.setItem('filter-values', $('input[name=filter-values]:checked').val());
    product_attribute_id = []
    loadValues()
});

// Event set template mode
$('#serv-panel').on('click', '#template-view', function () {
    localStorage.setItem('display_attribute', 'template');
    $(this).addClass('btn-info');
    $('#values-view').removeClass('btn-info');
    product_attribute_id = []
    loadValues()
});

// Event set values mode
$('#serv-panel').on('click', '#values-view', function () {
    localStorage.setItem('display_attribute', 'values');
    $(this).addClass('btn-info');
    $('#template-view').removeClass('btn-info');
    product_attribute_id = []
    loadValues()
});

// Event override method 
$('#serv-panel').on('change', '#method-view', () => {
    method = $('#method-view option:selected').val()
});

// Event each textarea value edit 
$('#serv-panel').on('change', '#multi', () => {
    multi = $('#multi').is(':checked')
    localStorage.setItem('multi', multi.toString());
});

// Event attach categories attributes 
$('#attach-attribute').on('click', () => {
    let categories = getSelectedCategories()
    if (categories) {
        for (let category_id of categories) {
            newCategory(category_id)
        }
    }
});

$(getServPanel);