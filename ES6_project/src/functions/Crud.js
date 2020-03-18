import { getLanguageId, getParentByKey } from './Plugin/NodeMethod';
import { getSelectedKeys, getSelectedTitles, deSelectNodes, deSelectCategories } from './Select'
import { reactivateCategory, reloadAttribute, smartReload } from './Syncronisation'
import { ATTRIBUTE_GROUP_TREE } from '../constants/global';
import { copyNode, deleteNode } from '../actions';
import { dndAddNode } from '../actions/dndActions';

export function addAttribute(activeNode, activeKey, lng_id) {
    let node = activeNode,
        parentLevel = (activeKey === 'attribute') ? 2 : 1;
    while (node.getLevel() > parentLevel) {
        node = node.getParent();
    }
    $.ajax({
        data: {
            'user_token': user_token,
            'token': token,
            'key': node.key,
            name: activeKey === 'attribute' ? textNewAttribute : textNewGroup
        },
        url: 'index.php?route=' + extension + 'module/attributico/addAttribute',
        success: function (new_id) {
            node.editCreateNode("child", {
                title: activeKey === 'attribute' ? textNewAttribute[lng_id] + "_" + new_id : textNewGroup[lng_id] + "_" + new_id,
                key: activeKey + "_" + new_id,
                folder: (activeKey === 'group') ? true : false
            });
        }
    });
}

export function deleteAttribute(node, store) {
    let level = node.getLevel();
    if (level === 2 || level === 3 || level === 5) {
        $.ajax({
            data: {
                'user_token': user_token,
                'token': token,
                'keys': selNodes ? getSelectedKeys(selNodes) : [node.key],
                'titles': selNodes ? getSelectedTitles(selNodes) : [node.title],
                'language_id': node.getLanguageId()
            },
            url: 'index.php?route=' + extension + 'module/attributico/deleteAttributes',
            success: function () {
                store.dispatch(deleteNode(node)); // Надо до remove

                if (selNodes) {
                    // TODO selNodes всегда есть?, можно убрать if else???. Проверить корректность удаления кэша в модели
                    $.each(selNodes, function (i, o) {
                        o.remove();
                    });
                } else {
                    node.remove();
                }

                selNodes = null;
            }
        });
    }
}

export function pasteNodes(targetNode, store) {
    let parentNode = targetNode.getParentByKey('group') || targetNode.getParentByKey('category');
    let sourceNode = clipboardNodes[0][1];
    if (parentNode.isGroup()) {
        $.ajax({
            data: {
                'user_token': user_token,
                'token': token,
                'target': parentNode.key,
                'attributes': clipboardTitles
            },
            url: 'index.php?route=' + extension + 'module/attributico/addAttributes',
            success: function () {
                store.dispatch(copyNode(sourceNode, parentNode));
            }
        });
    }
    if (parentNode.isCategory()) {
        addAttributeToCategory(sourceNode, parentNode, false, store);
        /* $.ajax({
            data: {
                'attributes': selNodes ? getSelectedKeys(selNodes) : [sourceNode.key],
                'category_id': parentNode.key,
                'categories': selCategories ? getSelectedKeys(selCategories) : [parentNode.key]
            },
            url: 'index.php?route=' + extension + 'module/attributico/addCategoryAttributes' + '&user_token=' + user_token + '&token=' + token,
            type: 'POST',
            success: function () {
                reactivateCategory(parentNode);
            }
        }); */
    }
}
// sourceNode = data.otherNode это узел источника
// Синхронизировать деревья атрибутов надо, т.к. могли добавиться или удалиться значения после add/delete
export function deleteAttributesFromCategory(sourceNode) {
    let category_id = sourceNode.getParent().key;

    $.ajax({
        data: {
            'attributes': selNodes ? getSelectedKeys(selNodes) : [sourceNode.key],
            'category_id': category_id,
            'categories': selCategories ? getSelectedKeys(selCategories) : []
        },
        url: 'index.php?route=' + extension + 'module/attributico/deleteAttributesFromCategory' + '&user_token=' + user_token + '&token=' + token,
        type: 'POST',
        success: function () {
            reactivateCategory();
            reloadAttribute(sourceNode, true); // при удалении надо засинхронизировать все деревья где были lazy вдруг это были последние
        }
    });
    deSelectNodes();
}

export function addAttributeToCategory(sourceNode, targetNode, remove, store) {    
    $.ajax({
        data: {
            'attributes': selNodes ? getSelectedKeys(selNodes) : [sourceNode.key],
            'category_id': targetNode.key,
            'categories': selCategories ? getSelectedKeys(selCategories) : []
        },
        url: 'index.php?route=' + extension + 'module/attributico/addCategoryAttributes' + '&user_token=' + user_token + '&token=' + token,
        type: 'POST'
    }).done(function () {
        if (!remove) {
            smartReload(sourceNode.tree, selNodes ? selNodes : [sourceNode]); // TODO возможно надо будет удалить если включено в reloadAttribute
            reactivateCategory(targetNode);
            // Надо перезагружать, чтоб подхватить новые значения и шаблоны (попробовать перенести в смарт)
            /* reloadAttribute(sourceNode, false); */
            store.dispatch(dndAddNode(sourceNode,targetNode,selNodes));
            deSelectNodes();
        } else {
            deSelectCategories(); // чтобы не удалялось в отмеченных категориях
            deleteAttributesFromCategory(sourceNode);
        }
    });
}

export function deleteDuty(node, store) {
    $.ajax({
        data: {
            'user_token': user_token,
            'token': token,
            'key': node.key,
            'language_id': node.getLanguageId(),
            'name': '',
        },
        url: 'index.php?route=' + extension + 'module/attributico/editAttribute',
        success: function () {
            store.dispatch(deleteNode(node)); // при удалении надо перезагрузить дерево т.к. поле не удаестя сделать пустым при edit           
        }
    });
}

export function copyPaste(action, targetNode, store) {
    switch (action) {
        case "cut":
        case "copy":
            // clipboardNode = node;
            pasteMode = action;
            if (selNodes) {
                selNodes.forEach(function (node, i) {
                    clipboardNodes[i] = [];
                    clipboardTitles[i] = [];
                    $(ATTRIBUTE_GROUP_TREE).each(function (indx, element) {
                        let tree = $("#" + element.id).fancytree("getTree");
                        let lng_id = parseInt(element.id.replace(/\D+/ig, ''));
                        clipboardNodes[i][lng_id] = tree.getNodeByKey(node.key);
                        clipboardTitles[i][lng_id] = tree.getNodeByKey(node.key).title;
                    });
                });
            } else {
                clipboardNodes[0] = [];
                clipboardTitles[0] = [];
                $(ATTRIBUTE_GROUP_TREE).each(function (indx, element) {
                    let tree = $("#" + element.id).fancytree("getTree");
                    let lng_id = parseInt(element.id.replace(/\D+/ig, ''));
                    clipboardNodes[0][lng_id] = tree.getNodeByKey(targetNode.key);
                    clipboardTitles[0][lng_id] = tree.getNodeByKey(targetNode.key).title;
                });
            }
            break;
        case "paste":
            if (clipboardNodes.length == 0) {
                alert("Clipoard is empty.");
                break;
            }
            if (pasteMode == "cut") {
                // Cut mode: check for recursion and remove source
                // TODO
                // pasteNodes(targetNode);
                // clipboardNodes[indx].remove();                
            } else {               
                pasteNodes(targetNode, store);
            }
            clipboardNodes = [];
            clipboardTitles = [];
            //   selNodes = null;
            pasteMode = null;
            break;
        default:
            alert("Unhandled clipboard action '" + action + "'");
    }
}