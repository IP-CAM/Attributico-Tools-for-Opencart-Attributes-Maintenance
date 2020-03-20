/**
 * Handling shortcut click commands
 * @type {class}
 *
 **/
import { deSelectNodes } from '../functions/Select'
import { copyPaste, deleteDuty, deleteAttributesFromCategory, deleteAttribute, addAttribute } from '../functions/Crud'
import CollapseExpande from './Events/CollapseExpande';
import { isCategory } from '../functions/Plugin/NodeMethod';

export class KeydownCommand {
    constructor(event, data, store) { 
        this.e = event;
        this.node = data.node;
        this.tree = data.tree;
        this.selector = this.tree.$div[0].id;
        this.lng_id = parseInt(this.selector.replace(/\D+/ig, ''));
        this.access = {
            remove: true,
            addChild: true,
            addSibling: true,
            copy: true,
            paste: true
        };
        this.store = store;
    }

    set permissions(newPermissions) {
        this.access = newPermissions;
    }

    execute() {
        switch (this.e.which) {
            case 68:
                //     alt+D  cmd = "debug mode";
                if (this.e.altKey) {
                    console.log("Debug mode: ");
                    $.ajax({
                        data: {
                            'user_token': user_token,
                            'token': token,
                        },
                        url: 'index.php?route=' + extension + 'module/attributico/debugSwitch',
                        success: function (message) {
                            console.log(message);
                        }
                    });
                }
                break;
            case 66:
                //     ctrl+B  cmd = "expande/collapse";
                if (this.e.ctrlKey) {
                    CollapseExpande(this.tree);
                }
                break;
            case 46:
                //   del   cmd = "remove";
                if (this.access.remove && confirm(textConfirm))
                    this.remove();
                break;
            case 77:
                //   ctrl+M    cmd = "addSibling";
                if (this.access.addSibling && this.e.ctrlKey) {
                    this.addSibling();
                }
                break;
            case 81:
                //  ctrl+Q  cmd = "addChild";
                if (this.access.addChild && this.e.ctrlKey) {
                    if (this.node.isCategory() || this.node.getLevel() !== 1) {
                        this.addChild();
                    }
                }
                break;
            case 67:
                // Ctrl-C copy
                if (this.access.copy && this.e.ctrlKey) {
                    copyPaste("copy", this.node, this.store);
                    return false;
                }
                break;
            case 86:
                // Ctrl-V paste
                if (this.access.paste && this.e.ctrlKey) {
                    copyPaste("paste", this.node, this.store);
                    deSelectNodes(this.node);
                    return false;
                }
                break;
            //  case 88:
            //   if( event.ctrlKey ) { // Ctrl-X
            //      copyPaste("cut", node);
            //      return false;
            //    }
            //    break;
        }
    }

    remove() {
        deleteAttribute(this.node, this.store);
    }

    addChild() {
        addAttribute(this.node, 'attribute', this.lng_id);
    }

    addSibling() {
        addAttribute(this.node, 'group', this.lng_id);
    }
}
/* Override methods for CategoryattributeTree and DutyTree*/
export class KeydownCommandCategory extends KeydownCommand {

    remove() {
        deleteAttributesFromCategory(this.node, null, this.store);
    }

    addChild() {
        this.tree.getRootNode().getFirstChild().editCreateNode("child"); // add child attribute to root category
    }
}

export class KeydownCommandDuty extends KeydownCommand {

    remove() {
        deleteDuty(this.node, this.store);
    }
}