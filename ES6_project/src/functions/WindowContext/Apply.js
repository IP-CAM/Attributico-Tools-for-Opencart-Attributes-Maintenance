import { CATEGORY_TREE, CATEGORY_CHECK_TREE, ATTRIBUTE_SYNCRO_TREES, MODULE } from "../../constants/global";

export function apply() {
    $('[id ^= "tree"].settings').each(function (indx, element) {
        let tree = $.ui.fancytree.getTree("#" + element.id);
        tree.generateFormElements();
    });
    //alert("POST data:\n" + jQuery.param($("#form-' + MODULE + '").serializeArray()));
    $(".alert-success, #error_warning.alert-danger").hide();
    $.post($('#form-' + MODULE).attr('action'), $('#form-' + MODULE).serialize(), function (html) {
        var $success = $(html).find(".alert-success, #error_warning.alert-danger"); // если после редиректа форма выставила success
        if ($success.length > 0) {
            $(".alert-before").before($success);
        } else {
            // если нет, то ищем success по ссылке, котрая в селекте или active для unshop
            var $href = $(html).find("[selected=\"selected\"]").val() || $(html).find(".active").val();
            $.post($href, "", function (html) {
                var $success = $(html).find(".alert-success, #error_warning.alert-danger");
                if ($success.length > 0) {
                    $(".alert-before").before($success);
                }
            });
        }
        // Re-apply settings for each trees and contextmenus
        $('input[id ^= "multiSelect"]:checkbox').prop("checked", $('input[name = "' + MODULE + '_multiselect"]:checkbox').is(":checked"));
        $('input[id ^= "sortOrder"]:checkbox').prop("checked", $('input[name = "' + MODULE + '_sortorder"]:checkbox').is(":checked"));
        $('input[id ^= "lazyLoad"]:checkbox').prop("checked", $('input[name = "' + MODULE + '_lazyload"]:checkbox').is(":checked"));
        $(CATEGORY_TREE).each(function (indx, element) {
            var tree = $.ui.fancytree.getTree("#" + element.id);
            tree.options.selectMode = $('input[id ^= "multiSelect"]:checkbox').is(":checked") ? 3 : 2;
        });
        $(CATEGORY_CHECK_TREE).each(function (indx, element) {
            var tree = $.ui.fancytree.getTree("#" + element.id);
            tree.options.selectMode = $('input[id ^= "multiSelect"]:checkbox').is(":checked") ? 3 : 2;
        });
        $(ATTRIBUTE_SYNCRO_TREES).each(function (indx, element) { // reload function is located inside as asynchronous request
            var tree = $.ui.fancytree.getTree("#" + element.id);
            tree.options.source.data.sortOrder = $('input[id ^= "sortOrder"]:checkbox').is(":checked");
            tree.options.source.data.lazyLoad = $('input[id ^= "lazyLoad"]:checkbox').is(":checked");
            tree.options.source.data.category_id = currentCategory;
            tree.reload();
        });
    });
}

export function submit() {
    $('[id ^= "tree"].settings').each(function (indx, element) {
        let tree = $.ui.fancytree.getTree("#" + element.id);
        tree.generateFormElements();
    });
    //alert("POST data:\n" + jQuery.param($('#form-' + MODULE).serializeArray()));
    $('#form-' + MODULE + '').trigger('submit')
}