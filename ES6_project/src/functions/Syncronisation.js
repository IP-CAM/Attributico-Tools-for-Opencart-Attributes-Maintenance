import { ATTRIBUTE_SYNCRO_TREES, GROUP_SYNCRO_TREES, CATEGORY_TREE, MODULE } from '../constants/global'

/* Functions for trees handling logic */
export function reactivateCategory() { 
    $(CATEGORY_TREE).each(function (indx, element) {
        const tree = $.ui.fancytree.getTree("#" + element.id);
        let activeNode = tree.getActiveNode();
        if (activeNode) {
            tree.getNodeByKey(activeNode.key).setActive(false);
            tree.getNodeByKey(activeNode.key).setActive(true);
        }
    });
}

export function reloadAttribute(task) {
    // Активный узел выставлять бессмысленно, т.к. после инструментов его уже может не быть
    let treeSelector = (task == 'casechange') ? GROUP_SYNCRO_TREES : ATTRIBUTE_SYNCRO_TREES
    $(treeSelector).each(function (indx, element) {
        const tree = $.ui.fancytree.getTree("#" + element.id); 
        tree.options.source.data.cache = $('input[name = "' + MODULE + '_cache"]:checkbox').is(":checked");
        tree.reload()
    });
}