import { CATEGORY_SYNCRO_TREES, MODULE } from '../../constants/global'
import { checkOptions } from "../../actions";

/**
* Common settings change event hundlers
*
**/
export default function commonSettings(store) {

    // access to autoadd radio
    $('[name = "' + MODULE + '_product_text"]').each(function (indx, element) {
        if (!$('[name = "' + MODULE + '_autoadd"]').is(":checked")) {
            $(element).prop({
                "disabled": true,
                "checked": false
            });
        }
    });
    // autoadd attribute values to product
    $('input[name = "' + MODULE + '_autoadd"]:checkbox').on('change', function (e) {
       let flag = $(this).is(":checked");
        $('[name = "' + MODULE + '_product_text"]').each(function (indx, element) {
            $(element).prop({
                "disabled": !flag
            });
        });
    });
    // event handler for smartscroll
    $('input[name = "' + MODULE + '_smart_scroll"]:checkbox').on('change', function (e) {
        if ($(this).is(":checked")) {
            $('[id *= "tree"]:not(.settings) > ul.fancytree-container').addClass("smart-scroll");
        } else {
            $("ul.fancytree-container").removeClass("smart-scroll");
        }
    });
    // event handler for cache on/off
    $('input[name = "' + MODULE + '_cache"]:checkbox').on('change', function (e) {
        $.ajax({
            data: {
                'user_token': user_token,
                'token': token
            },
            url: route + 'cacheDelete',
            success: function (json) { 
                $(CATEGORY_SYNCRO_TREES).each(function (indx, element) {
                    let tree = $.ui.fancytree.getTree("#" + element.id);
                    tree.options.source.data.cache = $('input[name = "' + MODULE + '_cache"]:checkbox').is(":checked");
                    tree.reload()
                });
                store.dispatch(checkOptions());
            }
        });
    });
    // event handler for multistore categories output
    $('input[name = "' + MODULE + '_multistore"]:checkbox').on('change', function (e) {
        $.ajax({
            data: {
                'user_token': user_token,
                'token': token
            },
            url: route + 'cacheDelete',
            success: function (json) {                
                $(CATEGORY_SYNCRO_TREES).each(function (indx, element) {
                    let tree = $.ui.fancytree.getTree("#" + element.id);
                    tree.options.source.data.multistore = $('input[name = "' + MODULE + '_multistore"]:checkbox').is(":checked");
                    tree.reload()
                });
                store.dispatch(checkOptions());
            }
        });
    });    

}