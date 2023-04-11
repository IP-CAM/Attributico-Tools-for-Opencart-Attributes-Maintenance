<?php

@include_once(DIR_SYSTEM . 'license/sllic.lic');
require_once(DIR_SYSTEM . 'library/attributico/attributico.php');
//require_once(DIR_SYSTEM . 'library/attributico/interlink.php');

class ControllerModuleAttributico extends Controller
{
    const MODULE_VERSION =  'v3.2.7';
    const TOOLS_GROUP_TREE = 'ft_6';
    const TOOLS_CATEGORY_TREE = 'ft_7';
    const DEFAULT_THUMBNAIL_SIZE = 100;
    protected $data = array();
    protected $error = array();
    private $debug_mode = false;
    private $avcahe = array();
    private $token;
    private $settings = array(
        '1' => array("template", "value"),
        '2' => array("duty"),
        '3' => array("duty"),
        '4' => array("template", "value"),
        '5' => array("template", "value"),
    );
    protected $dbstructure = array(
        'attribute_description' => array(
            /* 'attribute_id' => 'int(11) NOT NULL',
            'language_id' => 'int(11) NOT NULL',
            'name' => 'varchar(64) NOT NULL', */
            'duty' => "text NOT NULL",
        ),
    );
    // For version 4 Attributico
    /* protected $dbstructure = array(        
        'attribute_description_pro' => array(
            'attribute_id' => 'int(11) NOT NULL',
            'language_id' => 'int(11) NOT NULL',
            'name' => 'varchar(64) NOT NULL',            
            'duty' => "text NOT NULL",            
            'duty_status' => "tinyint(1) NOT NULL DEFAULT 1"
        )
    ); */
    protected $module = 'attributico';
    protected $modulefile = 'module/attributico';
    protected $modelfile = 'catalog/attributico';
    protected $model = 'model_catalog_attributico';
    protected $model_tools = 'model_catalog_attributico_tools';
    protected $extension;
    /*  private $coworking; */

    protected function init()
    {
        if (version_compare(VERSION, '2.0.1', '>=')) {
            $this->document->addStyle('view/stylesheet/jquery-ui.css');
        }

        $this->document->addStyle('view/javascript/fancytree/skin-win7/ui.fancytree.css');
        $this->document->addStyle('view/javascript/fancytree/skin-custom/custom.css');
        $this->document->addStyle('view/stylesheet/' . $this->module . '.css');

        $this->document->addScript('view/javascript/attributico/' . $this->module . '.js');

        $this->extension = version_compare(VERSION, '2.3.0', '>=') ? "extension/" : "";
        $edit = version_compare(VERSION, '2.0.0', '>=') ? "edit" : "update";
        $link = version_compare(VERSION, '2.3.0', '>=') ? "extension/extension" : "extension/module";

        if (version_compare(VERSION, '3.0.0', '>=')) {
            $link = "marketplace/extension";
        }

        if (version_compare(VERSION, '2.2.0', '>=')) {
            // $this->load->language($this->extension . $this->modulefile);
            $this->data = array_merge($this->data, $this->load->language($this->extension . $this->modulefile));
            $ssl = true;
        } else {
            //$this->language->load($this->modulefile);
            $this->data = array_merge($this->data, $this->language->load($this->modulefile));
            $ssl = 'SSL';
        }

        if (isset($this->session->data['user_token'])) {
            $this->token = $this->session->data['user_token'];
            $token_name = 'user_token';
        }
        if (isset($this->session->data['token'])) {
            $this->token = $this->session->data['token'];
            $token_name = 'token';
        }

        $this->data['user_token'] = $this->data['token'] = $this->token;
        $this->data['extension'] = $this->extension;
        $this->data['route'] = 'index.php?route=' . $this->extension . $this->modulefile . '/';
        $this->data['edit'] = $edit;

        $this->data['heading_title'] = $this->data['heading_title'] . ' ' . $this::MODULE_VERSION;

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $children = array();
            $i = 1;
            while (isset($this->request->post['ft_' . $i])) {
                $children[$i] = $this->request->post['ft_' . $i];
                $i++;
            }
            $this->request->post[$this->module . '_children'] = serialize($children);

            $filter_settings = array();
            foreach ($this->request->post as $key => $val) {
                if ($val == "on") {
                    $filter_settings[] = $key;
                }
            }
            $this->request->post[$this->module . '_filter'] = serialize($filter_settings);

            if (($this->config->get('module_' . $this->module . '_status'))) { //TODO status error
                $this->request->post['module_' . $this->module . '_status'] = $this->config->get('module_' . $this->module . '_status');
            } else {
                $this->request->post['module_' . $this->module . '_status'] = 0;
            }

            $this->model_setting_setting->editSetting($this->module, $this->request->post);
            $this->model_setting_setting->editSetting('module_' . $this->module, $this->request->post);
            $this->session->data['success'] = $this->data['text_success'];

            if (version_compare(VERSION, '2.0.1', '>=')) { // иначе вылетает из админки
                $this->response->redirect($this->url->link($link, $token_name . '=' . $this->token . '&type=module', $ssl));
            } else {
                $this->redirect($this->url->link($link, $token_name . '=' . $this->token, $ssl));
            }
        }

        if (class_exists('Vendor')) {
            $vendor = new Vendor();
        }
        $this->session->data['free'] = $vendor->franchise() || !(filesize(DIR_SYSTEM . 'license/sllic.lic') > 20000);
        if ($this->session->data['free']) {
            $this->data['heading_title'] = $this->data['heading_title'] . ' View ' . $this::MODULE_VERSION . '(free)';
        }

        if (!$this->dbStructureCheck()) {
            $this->error['warning'] = $this->data['error_status'];
        }

        if (isset($this->session->data['a_debug_mode'])) {
            $this->debug_mode = $this->session->data['a_debug_mode'];
        }

        if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
        } else {
            $this->data['error_warning'] = '';
        }

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->data['text_home'],
            'href' => $this->url->link('common/dashboard', $token_name . '=' . $this->token, $ssl),
            'separator' => false
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->data['text_module'],
            'href' => $this->url->link($link, $token_name . '=' . $this->token . '&type=module', $ssl),
            'separator' => ' :: '
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->data['heading_title'],
            'href' => $this->url->link($this->extension . $this->modulefile, $token_name . '=' . $this->token, $ssl),
            'separator' => ' :: '
        );

        $this->load->model('localisation/language');
        $this->data['languages'] = $this->model_localisation_language->getLanguages();
        $this->session->data['languages'] = $this->data['languages'];
        // Защита от тупого мода "Скрыть отключенные языки"
        $language_code = array_keys($this->data['languages']);
        if (isset($this->data['languages'][$this->config->get('config_admin_language')])) {
            $this->data['config_language'] = $this->data['languages'][$this->config->get('config_admin_language')]['language_id'];
        } else {
            $this->data['config_language'] = $this->data['languages'][array_shift($language_code)]['language_id'];
        }

        $default_settings = array();
        $global_words = [
            'entry_attribute_groups', 'entry_templates', 'entry_values', 'entry_duty', 'entry_attribute_category', 'error_not_category',
            'entry_categories', 'error_not_attribute', 'entry_products', 'entry_attribute', 'entry_attributes'
        ];
        foreach ($global_words as $word) {
            $this->session->data[$word] = [];
        }
        $menu_words = [
            'text_New_attribute', 'text_New_group', 'text_Expande', 'text_Collapse', 'text_Refresh', 'text_sortOrder', 'text_Diver',
            'text_Edit', 'text_Delete', 'text_Copy', 'text_Cut', 'text_Paste', 'text_Merge', 'text_lazyLoad', 'entry_clone'
        ];
        foreach ($menu_words as $word) {
            $this->data[$word] = [];
        }
        $filter_words = [
            'text_matches', 'text_filter', 'text_autoComplete', 'text_Hide_unmatched_nodes', 'text_autoCollapse', 'text_Regular_expression',
            'text_Highlight', 'text_Fuzzy', 'text_hideExpandedCounter', 'text_Counter_badges', 'text_Auto_expand', 'text_Leaves_only', 'text_Attributes_only', 'button_filter_action', 'f_empty', 'f_digital', 'f_html', 'f_default'
        ];
        foreach ($filter_words as $word) {
            $this->data[$word] = [];
        }
        $option_words = ['text_Options', 'text_Sort', 'button_submit', 'text_multiSelect'];
        foreach ($option_words as $word) {
            $this->data[$word] = [];
        }

        foreach ($this->data['languages'] as $language) {
            $lng = $this->getLanguage($language['language_id']);

            if (version_compare(VERSION, '2.2.0', '>=')) {
                $this->data['languages'][$language['code']]['src'] = 'language/' . $language['code'] . '/' . $language['code'] . '.png';
            } else {
                $this->data['languages'][$language['code']]['src'] = 'view/image/flags/' . $language['image'];
            }
            // global tree entry-text
            foreach ($global_words as $word) {
                $this->session->data[$word][$language['language_id']] = $lng->get($word);
            }
            // menu
            foreach ($menu_words as $word) {
                $this->data[$word][$language['language_id']] = $lng->get($word);
            }
            // options
            foreach ($option_words as $word) {
                $this->data[$word][$language['language_id']] = $lng->get($word);
            }
            // filter
            foreach ($filter_words as $word) {
                $this->data[$word][$language['language_id']] = $lng->get($word);
            }

            $alltabs = ['tab-attribute', 'tab-duty', 'tab-category', 'tab-products'];
            foreach ($alltabs as $tab) {
                array_push($default_settings, 'fs_' . $tab . '_autoComplete' . $language['language_id'], 'fs_' . $tab . '_autoExpand' . $language['language_id'], 'fs_' . $tab . '_counter' . $language['language_id'], 'fs_' . $tab . '_hideExpandedCounter' . $language['language_id'], 'fs_' . $tab . '_highlight' . $language['language_id']);
            }
        }

        $this->data['action'] = $this->url->link($this->extension . $this->modulefile, $token_name . '=' . $this->token, $ssl);
        $this->data['cancel'] = $this->url->link($link, $token_name . '=' . $this->token . '&type=module', $ssl);

        if ($this->config->get($this->module . '_filter')) {
            $this->data['filter_settings'] = unserialize($this->config->get($this->module . '_filter'));
        } else {
            $this->data['filter_settings'] = $default_settings;
        }

        $this->assignData($this->module . '_splitter', '/');
        $this->assignData($this->module . '_sortorder', 0);
        $this->assignData($this->module . '_smart_scroll', 0);
        $this->assignData($this->module . '_multiselect', 0);
        $this->assignData($this->module . '_empty', 0);
        $this->assignData($this->module . '_autoadd', 0);
        $this->assignData($this->module . '_autodel', 0);
        $this->assignData($this->module . '_autoadd_subcategory', 0);
        $this->assignData($this->module . '_autodel_subcategory', 0);
        $this->assignData($this->module . '_product_text', 'unchange');
        $this->assignData($this->module . '_about_blank', 0);
        $this->assignData($this->module . '_lazyload', 0);
        $this->assignData($this->module . '_cache', 0);
        $this->assignData($this->module . '_multistore', 0);
        $this->assignData($this->module . '_value_compare_mode', 'substr');
    }

    protected function out()
    {
        $this->data['module_version'] = $this->module . '_' . $this::MODULE_VERSION;

        if (version_compare(VERSION, '2.0.1', '>=')) {
            $this->data['header'] = $this->load->controller('common/header');
            $this->data['column_left'] = $this->load->controller('common/column_left');
            $this->data['footer'] = $this->load->controller('common/footer');

            $tpl = version_compare(VERSION, '2.2.0', '>=') ? "" : ".tpl";
            $this->response->setOutput($this->load->view($this->extension . $this->modulefile . $tpl, $this->data));
        } else {
            $this->template = 'module/' . $this->module . '_1_5_x.tpl';
            $this->children = array(
                'common/header',
                'common/footer'
            );
            $this->response->setOutput($this->render());
        }
    }
    public function index()
    {

        $this->init();

        $this->out();
    }

    protected function validate()
    {
        //$this->extension = version_compare(VERSION, '2.3.0', '>=') ? "extension/" : "";
        if (!$this->user->hasPermission('modify', $this->extension . $this->modulefile)) {
            $this->error['warning'] = $this->data['error_permission'];
        }

        $splitter = isset($this->request->post[$this->module . '_splitter']) ? $this->request->post[$this->module . '_splitter'] :
            $this->config->get($this->module . '_splitter');
        if (
            strlen($splitter) > 1 || preg_match_all('/(?!_)[\w\d\s\[\]\'\"\-]/mu', $splitter)
        ) {
            $this->error['warning'] = $this->data['error_splitter'];
        }
        return !$this->error;
    }

    /**
     * Get and return value from Post or Config
     * or default value if Config value is undefined    
     *     
     * @param string $param key in Config or Post request
     * @param void $default_value     
     * @return void 
     */

    protected function assignData($key, $default_value)
    {
        if (isset($this->request->post[$key])) {
            $this->data[$key] = $this->request->post[$key];
        } elseif (($this->config->get($key))) {
            $this->data[$key] = $this->config->get($key);
        } else {
            $this->data[$key] = $default_value;
        }
    }

    /**issetRequest
     * Get and return value of GET or POST request parameter
     * or default value if parameter is undefined
     *
     * @param string $request_type 'post' or 'get'
     * @param string $param GET or POST request parameter name
     * @param void $default
     * @return void or boolean if $default is boolean
     */
    protected function issetRequest($request_type, $param, $default)
    {
        if (is_bool($default) === true) {
            return isset($this->request->{$request_type}[$param]) ? filter_var($this->request->{$request_type}[$param], FILTER_VALIDATE_BOOLEAN) : $default;
        }
        if (is_array($default)) {
            return isset($this->request->{$request_type}[$param]) ? (is_string($this->request->{$request_type}[$param]) ? explode('_', $this->request->{$request_type}[$param]) : $this->request->{$request_type}[$param]) : $default;
        }
        if (is_string($default) && $default === '') {
            return isset($this->request->{$request_type}[$param]) ? htmlspecialchars_decode($this->request->{$request_type}[$param]) : $default;
        }

        return isset($this->request->{$request_type}[$param]) ? $this->request->{$request_type}[$param] : $default;
    }

    /**
     * Get and return value from Config
     * or default value if Config value is undefined
     * In strict check for empty
     *     
     * @param string $param key in Config
     * @param void $default
     * @param bool $strict
     * @return void 
     */
    protected function configGet($param, $default, $strict = false)
    {
        if ($strict) {
            return !($this->config->get($param) == '') ? $this->config->get($param) : $default;
        } else {
            return $this->config->get($param) ? $this->config->get($param) : $default;
        }
    }

    /** Fuction for product form integration */

    /**
     * Returns the differense between attributes of unbinded product category 
     * and remaining binded categories
     * 
     * @return array 
     */
    public function removeCategoryAttributes()
    {
        $sortOrder = $this->config->get($this->module . '_sortorder') == '1' ? true : false;
        $product_id = isset($this->request->get['product_id']) ? (int) $this->request->get['product_id'] : 0;
        $category_id = isset($this->request->get['category_id']) ? (int) $this->request->get['category_id'] : 0;
        $categories = isset($this->request->get['categories']) ? $this->request->get['categories'] : array();
        $categories_attributes = [];

        $this->load->model($this->modelfile);
        // Это те, которые удалять нельзя, т.к. товар привязан к нескольким категориям и атрибуты должны удалятся только если они не пересекаются.
        // Если не передано, значит просто вернется список для $category_id
        if ($categories) {
            foreach ($categories as $category) {
                $filter_data = array(
                    'category_id' => (int) $category,
                    'sort' => $sortOrder ? 'sort_attribute_group, a.sort_order' : ''
                );
                $categories_attributes = array_merge($categories_attributes, $this->{$this->model}->getCategoryAttributes($filter_data));
            }
        }

        // Это те, которые удалять или добавлять если не передано $categories
        $filter_data = array(
            'category_id' => (int) $category_id,
            'sort' => $sortOrder ? 'sort_attribute_group, a.sort_order' : ''
        );
        $category_attributes = $this->{$this->model}->getCategoryAttributes($filter_data);

        function compare_func($a, $b)
        {
            return (int) $a['attribute_id'] - (int) $b['attribute_id'];
        }
        // Отдаются только непересекающиеся атрибуты
        $diff_category_attribute = array_udiff($category_attributes, $categories_attributes, "compare_func");

        $json = array();
        foreach ($diff_category_attribute as $attribute) {
            $json[] = array(
                'attribute_id' => (int) $attribute['attribute_id'],
                'name' => $attribute['attribute_description'],
                'group_name' => $attribute['group_name']
            );
        }

        if (!$sortOrder) {
            $sort_order = array();
            foreach ($json as $key => $value) {
                $sort_order[$key] = $value['name'];
            }
            array_multisort($sort_order, SORT_ASC, $json);
        }

        $this->{$this->model}->deleteAttributesFromProducts([['product_id' => $product_id]], array_column($json, 'attribute_id'));

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getCategoryAttributes()
    {
        $json = array();
        $sortOrder = $this->config->get($this->module . '_sortorder') == '1' ? true : false;
        $category_id = isset($this->request->get['category_id']) ? (int) $this->request->get['category_id'] : 0;
        $categories = isset($this->request->get['categories']) ? $this->request->get['categories'] : array();
        $categories_attributes = [];

        $this->load->model($this->modelfile);

        // Это те, которые удалять или добавлять если не передано $categories
        $filter_data = array(
            'category_id' => (int) $category_id,
            'sort' => $sortOrder ? 'sort_attribute_group, a.sort_order' : ''
        );
        $category_attributes = $this->{$this->model}->getCategoryAttributes($filter_data);

        foreach ($category_attributes as $attribute) {
            $json[] = array(
                'attribute_id' => (int) $attribute['attribute_id'],
                'name' => $attribute['attribute_description'],
                'group_name' => $attribute['group_name']
            );
        }

        if (!$sortOrder) {
            $sort_order = array();
            foreach ($json as $key => $value) {
                $sort_order[$key] = $value['name'];
            }
            array_multisort($sort_order, SORT_ASC, $json);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** Fuction for product form integration and Duty select in edit mode */
    public function getValuesList()
    {
        $json = array();
        $attribute_id = $this->issetRequest('get', 'attribute_id', 0);
        $attribute_row = $this->issetRequest('get', 'attribute_row', 0);
        $view_mode = $this->issetRequest('get', 'view_mode', 'template');
        $categories = $this->issetRequest('get', 'categories', array());
        $filter_values = $this->issetRequest('get', 'filter_values', 'all');
        $language_id = $this->issetRequest('get', 'language_id', '');

        $languages = $this->getLanguages();

        $values = $this->fetchValueList($attribute_id, $filter_values, $categories);

        if (!$language_id) {
            foreach ($languages as $language) {
                if (!isset($values[$language['language_id']])) {
                    $values[$language['language_id']][] = array('text' => '');
                }
                $select = $this->makeValuesSelect($values[$language['language_id']], $view_mode, $attribute_id, $language['language_id'], $attribute_row);
                $json[$language['language_id']][] = $select;
            }
        } else {
            if ($values) {
                $value_list = $values[$language_id];  // isset&
                $json = array_unique($value_list, SORT_REGULAR);
                array_multisort($json, SORT_REGULAR);
            } else {
                $json[] = ['text' => 'No data...'];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Prepares values or duties for compiling a list of values depending on filter_values
     *
     * @param integer $attribute_id
     * @param string $filter_values
     * @param array $categories
     * @return array
     */
    protected function fetchValueList($attribute_id, $filter_values, $categories)
    {
        $this->load->model($this->modelfile);

        switch ($filter_values) {
            case 'duty':
                $values = $this->{$this->model}->getDutyValues($attribute_id);
                break;
            case 'categories':
                $values = $this->{$this->model}->getAttributeValues($attribute_id, $categories);
                break;
            case 'all':
            default:
                $values = $this->{$this->model}->getAttributeValues($attribute_id, []);
                $duties = $this->{$this->model}->getDutyValues($attribute_id);
                foreach ($values as $lang => $value) {
                    $values[$lang] = array_merge($values[$lang], $duties[$lang]);
                }
                break;
        }

        foreach ($values as $lang => $value) {
            $values[$lang] = array_unique($value, SORT_REGULAR);
            array_multisort($values[$lang], SORT_REGULAR);
        }
        return $values;
    }

    protected function makeValuesSelect($values, $view_mode, $attribute_id, $language_id, $attribute_row = '')
    {
        $splitter = $this->configGet($this->module . '_splitter', '/', true);
        $select = "<select name='attribute_select_{$attribute_id}' class='form-control attribute_select' attribute_id='{$attribute_id}' language_id='{$language_id}' attribute_row ='{$attribute_row}' style='display:block;'>";
        $options = array();

        if ($view_mode == 'template') {
            foreach ($values as $option) {
                $options[] = ['key' => '', 'value' => $option['text'], 'title' => $option['text']];
            }
            $select .= $this->makeOptionList($options, '0', $this->language->get('text_select'));
            $select .= "</select>";
        } else {
            $value_list = splitTemplate($values, $splitter);
            foreach ($value_list as $value) {
                $options[] = ['key' => '', 'value' => $value, 'title' => $value];
            }
            $select .= $this->makeOptionList($options, '0', $this->language->get('text_select'));
            $select .= "</select>";
        }
        return $select;
    }

    /**
     * makeOptionList function making options for select tag
     *
     * @param array $options
     * @param string $default_value mark selected
     * @param string $title0 if needs append first option for inform about no selection
     * @param string $style
     * @return string html as <option key= , value= > title </option>  for select tag 
     */
    protected function makeOptionList($options, $default_value, $title0 = '', $style = '')
    {
        $option_list = $title0 ? "<option key='{0}' value='{0}'>{$title0}</option>" : '';
        foreach ($options as $option) {
            $selected = $option['value'] === $default_value ? 'selected' : '';
            $key = isset($option['key']) ? $option['key'] : '';
            $value = isset($option['value']) ? $option['value'] : '';
            $title = isset($option['title']) ? $option['title'] : $value;
            if ($title) {
                $option_list .= "<option key='{$key}' value='{$value}' {$selected} style='{$style}'>{$title}</option>";
            }
        }
        return $option_list;
    }

    /** Fuction for product form integration */
    public function getAttributeDuty()
    {
        $json = array();
        $attribute_id = isset($this->request->get['attribute_id']) ? (int) $this->request->get['attribute_id'] : 0;
        $method = isset($this->request->get['method']) ? $this->request->get['method'] : $this->config->get($this->module . '_product_text');

        if ($this->config->get($this->module . '_autoadd')) {

            $languages = $this->getLanguages();

            $this->load->model($this->modelfile);

            if ($method == 'overwrite' || $method == 'ifempty')
                foreach ($languages as $language) {
                    $json[$language['language_id']][] = $this->{$this->model}->getDutyInfo($attribute_id, $language['language_id'])['duty'];
                }
            if ($method == 'clean')
                foreach ($languages as $language) {
                    $json[$language['language_id']][] = '';
                }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getServPanel()
    {
        $extension = version_compare(VERSION, '2.3.0', '>=') ? "extension/" : "";

        if (version_compare(VERSION, '2.2.0', '>=')) {
            $this->load->language($extension . $this->modulefile);
        } else {
            $this->language->load($this->modulefile);
        }
        $checkbox = "<div class = 'btn-group' data-toggle = 'buttons' ><label class = 'btn btn-default' style='margin-left:3px;'>
           <input type = 'checkbox' name='multi' id='multi'><i class='fa fa-copy'></i></label></div>";
        $labels = "<label class='radio-inline'><input type='radio' name='filter-values' id='filter-nofilter' value='all' checked>" . $this->language->get('entry_flter_all') . "</label>";
        $labels .= "<label class='radio-inline'><input type='radio' name='filter-values' id='filter-category' value='categories'>" . $this->language->get('entry_flter_category') . "</label>";
        $labels .= "<label class='radio-inline'><input type='radio' name='filter-values' id='filter-duty' value='duty'>" . $this->language->get('entry_flter_duty') . "</label>";
        /* $labels .= "<label class='radio-inline'></label>"; */

        $buttons =  "<div class='btn-group' style='margin-left:10px;'>";
        $buttons .= "<button type='button' id='template-view' class='btn btn-default'><i class='fa fa-th-list'></i>" . $this->language->get('entry_templates') . "</button>";
        $buttons .= "<button type='button' id='values-view' class='btn btn-default'><i class='fa fa-th'></i>" . $this->language->get('entry_values') . "</button>";
        $buttons .= "</div>";

        $select = "<select class='form-control method-view' id='method-view' style='margin-left:3px; font-weight:normal; width:27%'>";
        $option_style = "overflow:hidden; white-space:nowrap; text-overflow:ellipsis;";
        //$method = $this->config->get($this->module . '_product_text');
        $method_options = [
            ['key' => 'clean', 'value' => 'clean', 'title' => $this->language->get('text_clear')],
            ['key' => 'unchange', 'value' => 'unchange', 'title' => $this->language->get('text_keep')],
            ['key' => 'overwrite', 'value' => 'overwrite', 'title' => $this->language->get('text_duty')],
            ['key' => 'ifempty', 'value' => 'ifempty', 'title' => $this->language->get('text_duty_only')],
        ];
        $options = $this->makeOptionList($method_options, $this->config->get($this->module . '_product_text'), '', $option_style);
        $select .= $options;
        $select .= "</select>";

        $splitter = !($this->config->get($this->module . '_splitter') == '') ? $this->config->get($this->module . '_splitter') : '/';
        $autoadd = $this->config->get($this->module . '_autoadd') ? $this->config->get($this->module . '_autoadd') : 0;
        /* $remove_category_attribute = $this->language->get('alert_remove_ca_confirm');
        $attach_category_attributes = $this->language->get('tab_category_attributes'); */

        $json = ['serv_panel' => $labels . $buttons . $select . $checkbox, 'splitter' => quotemeta($splitter), $this->module . '_autoadd' => $autoadd, 'extension' => $extension, 'remove_category_attribute' => $this->language->get('alert_remove_ca_confirm'), 'attach_category_attributes' => $this->language->get('tab_category_attributes')];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /* Tree functions */
    public function getAttributeGroupTree()
    {
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $sortOrder = isset($this->request->get['sortOrder']) ? filter_var($this->request->get['sortOrder'], FILTER_VALIDATE_BOOLEAN) : true;
        $lazyLoad = isset($this->request->get['lazyLoad']) ? filter_var($this->request->get['lazyLoad'], FILTER_VALIDATE_BOOLEAN) : false;
        $onlyGroup = isset($this->request->get['onlyGroup']) ? filter_var($this->request->get['onlyGroup'], FILTER_VALIDATE_BOOLEAN) : false;
        $cache = isset($this->request->get['cache']) ? filter_var($this->request->get['cache'], FILTER_VALIDATE_BOOLEAN) : $this->config->get($this->module . '_cache');
        $cachename = '';

        $tree = isset($this->request->get['tree']) ? $this->request->get['tree'] : '1';

        $children = $this->childrenSettings($tree);

        if ($cache) {
            $cachename = $this->module . ".tree." . (int) $language_id . (int) $sortOrder . (int) $lazyLoad . (int) $onlyGroup . (int) $children["template"] . (int) $children["value"] . (int) $children["duty"];
            $cache_tree_data = $this->cache->get($cachename);
        } else {
            $cache_tree_data = false;
        }

        if (!$cache_tree_data) {

            $this->load->model($this->modelfile);

            $filter_data = array(
                'sort' => $sortOrder ? 'ag.sort_order' : '',
                'language_id' => $language_id
            );
            $attribute_groups = $this->{$this->model}->getAttributeGroups($filter_data);

            if (isset($this->session->data['a_debug_mode'])) {
                $this->debug_mode = $this->session->data['a_debug_mode'];
            }

            $groupNode = new Node();
            foreach ($attribute_groups as $attribute_group) {
                $debug_group = $this->debug_mode ? " (id=" . $attribute_group['attribute_group_id'] . ")" : '';
                $groupNode->addSibling(new Node(array(
                    "title" => $attribute_group['name'] . $debug_group,
                    "key" => "group_" . (string) $attribute_group['attribute_group_id'],
                    "folder" => true,
                    "extraClasses" => $attribute_group['attribute_group_id'] == '1' ? "custom3" : '',
                    "children" => $onlyGroup ? '' : $this->getAttributeNodes($attribute_group['attribute_group_id'], $language_id, $sortOrder, $children, $lazyLoad)
                )));
            }

            $rootData = array(
                "title" => $this->session->data['entry_attribute_groups'][$language_id],
                "folder" => true,
                "expanded" => true,
                "children" => $groupNode->render(),
                "unselectable" => $onlyGroup ? false : true,
            );

            $AttributeGroupTree = new Tree(new Node($rootData));
            $cache_tree_data = $AttributeGroupTree->render();
            if ($cache) {
                $this->cache->set($cachename, $cache_tree_data);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($cache_tree_data));
    }

    private function getAttributeNodes($attribute_group_id, $language_id, $sortOrder, $children, $lazyLoad)
    {
        $filter_data = array(
            'filter_attribute_group_id' => (int) $attribute_group_id,
            'sort' => $sortOrder ? 'a.sort_order' : '',
            'language_id' => $language_id,
        );

        $attributeNode = new Node();
        $attributes = $this->{$this->model}->getAttributes($filter_data);
        foreach ($attributes as $attribute) {
            $templateNode = new Node(array(
                "title" => $this->session->data['entry_templates'][$language_id], "unselectable" => true, "key" => "template_" . (string) $attribute['attribute_id'],
                "children" => $lazyLoad ? '' : $this->getAttributeValuesNodes($attribute['attribute_id'], $language_id, 'template'), "lazy" => $lazyLoad ? true : false,
            ));
            $valueNode = new Node(array(
                "title" => $this->session->data['entry_values'][$language_id], "unselectable" => true, "key" => "value_" . (string) $attribute['attribute_id'],
                "children" => $lazyLoad ? '' : $this->getAttributeValuesNodes($attribute['attribute_id'], $language_id, 'values'), "lazy" => $lazyLoad ? true : false,
            ));
            $dutyNode = new Node(array("title" => $attribute['duty'], "key" => "duty_" . (string) $attribute['attribute_id'], "extraClasses" => "custom1",));
            $childNode = new Node();

            if ($children['duty']) {
                $childNode->addSibling($dutyNode);
            }
            if ($children['template']) {
                $childNode->addSibling($templateNode);
            }
            if ($children['value']) {
                $childNode->addSibling($valueNode);
            }
            $debug_attribute = $this->debug_mode ? " (id=" . $attribute['attribute_id'] . ")" : '';
            $attributeNode->addSibling(new Node(array(
                "title" => $attribute['name'] . $debug_attribute,
                "key" => "attribute_" . (string) $attribute['attribute_id'], "children" => $childNode->render()
            )));
        }

        return $attributeNode->render();
    }

    private function getAttributeValuesNodes($attribute_id, $language_id, $mode = 'template', $duty = "")
    {
        $splitter = $this->configGet($this->module . '_splitter', '/', true);
        if (!isset($this->avcahe[$attribute_id])) {
            $this->avcahe[$attribute_id] = $this->{$this->model}->getAttributeValues($attribute_id);
        }
        $attribute_values = $this->avcahe[$attribute_id];

        $empty = $this->config->get($this->module . '_empty');

        $nodeValues = new Node();
        if (array_key_exists($language_id, $attribute_values)) {
            if ($mode == 'template') {
                foreach ($attribute_values[$language_id] as $index => $value) {
                    if ($value['text'] != "" || $empty) { // сделать проверку на пустой текст
                        $nodeValues->addSibling(new Node(array(
                            "title" => $value['text'], "key" => "template_" . (string) $attribute_id . "_" . $index, "unselectable" => false,
                            //  "extraClasses" => $value['text'] == $duty ? "custom1" : ""
                        )));
                    }
                }
            } else if ($mode == 'values') {
                $values = splitTemplate($attribute_values[$language_id], $splitter);
                foreach ($values as $index => $value) {
                    $nodeValues->addSibling(new Node(array("title" => $value, "key" => "value_" . (string) $attribute_id . "_" . $index, "unselectable" => false)));
                }
            }
        }
        return $nodeValues->render();
    }

    public function getLazyAttributeValues()
    {
        $json = array();
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $key = isset($this->request->get['key']) ? explode("_", $this->request->get['key']) : array('0', '0');

        if (isset($this->session->data['a_debug_mode'])) {
            $this->debug_mode = $this->session->data['a_debug_mode'];
        }

        $this->load->model($this->modelfile);
        if ($key[0] == 'value') {
            $attribute_id = $key[1];
            $json = $this->getAttributeValuesNodes($attribute_id, $language_id, 'values');
        }
        if ($key[0] == 'template') {
            $attribute_id = $key[1];
            $json = $this->getAttributeValuesNodes($attribute_id, $language_id, 'template');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getLazyGroup()
    {
        $json = array();
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $sortOrder = isset($this->request->get['sortOrder']) ? filter_var($this->request->get['sortOrder'], FILTER_VALIDATE_BOOLEAN) : true;
        $lazyLoad = isset($this->request->get['lazyLoad']) ? filter_var($this->request->get['lazyLoad'], FILTER_VALIDATE_BOOLEAN) : false;
        $key = isset($this->request->get['key']) ? explode("_", $this->request->get['key']) : array('0', '0');

        $tree = isset($this->request->get['tree']) ? $this->request->get['tree'] : '1';

        $children = $this->childrenSettings($tree);

        $this->load->model($this->modelfile);
        if ($key[0] == 'group') {
            $attribute_group_id = $key[1];

            $json = $this->getAttributeNodes($attribute_group_id, $language_id, $sortOrder, $children, $lazyLoad);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //----------------------------------------CategoryTree------------------------------------------------------------------
    public function getCategoryTree()
    {
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $sortOrder = isset($this->request->get['sortOrder']) ? filter_var($this->request->get['sortOrder'], FILTER_VALIDATE_BOOLEAN) : true;
        $cache = isset($this->request->get['cache']) ? filter_var($this->request->get['cache'], FILTER_VALIDATE_BOOLEAN) : $this->config->get($this->module . '_cache');
        $multistore = isset($this->request->get['multistore']) ? filter_var($this->request->get['multistore'], FILTER_VALIDATE_BOOLEAN) : $this->config->get($this->module . '_multistore');

        $this->config->set($this->module . '_multistore', (string) $multistore);

        if (isset($this->session->data['a_debug_mode'])) {
            $this->debug_mode = $this->session->data['a_debug_mode'];
        }

        if ($cache) {
            $cachename = $this->module  . ".tree.category" . (int) $language_id . (int) $sortOrder . (int) $this->debug_mode;
            $cache_tree_data = $this->cache->get($cachename);
        } else {
            $cache_tree_data = false;
        }

        if (!$cache_tree_data) {

            $this->load->model($this->modelfile);
            $all_categories = $this->{$this->model}->getAllCategories();

            $mainCategory = new Node();
            foreach ($all_categories[0] as $main_category) {
                $categories_recursive = $this->getCategoriesRecursive($all_categories, $language_id, $main_category['category_id'], $sortOrder);
                $debug_category = $this->debug_mode ? " (id=" . $main_category['category_id'] . ")" : '';
                $mainCategory->addSibling(new Node(array(
                    "title" => $main_category['name'] . $debug_category, "folder" => true,
                    "key" => "category_" . (string) $main_category['category_id'], "children" => $categories_recursive
                )));
            }

            if (!$sortOrder) {
                $mainCategory->sort();
            }

            $rootData = array(
                "key" => 'RootCategoryTree',
                "title" => $this->session->data['entry_categories'][$language_id],
                "folder" => true,
                "expanded" => true,
                "children" => $mainCategory->render(),
            );

            $CategoryTree = new Tree(new Node($rootData));
            $cache_tree_data = $CategoryTree->render();
            if ($cache) {
                $this->cache->set($cachename, $cache_tree_data);
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($cache_tree_data));
    }

    private function getCategoriesRecursive($categories, $language_id, $parent_id, $sortOrder)
    {
        $categoryNode = new Node();
        if (array_key_exists($parent_id, $categories)) {
            foreach ($categories[$parent_id] as $category) {
                $categories_recursive = $this->getCategoriesRecursive($categories, $language_id, $category['category_id'], $sortOrder);
                $debug_category = $this->debug_mode ? " (id=" . $category['category_id'] . ")" : '';
                $categoryNode->addSibling(new Node(array(
                    "title" => $category['name'] . $debug_category,
                    "key" => "category_" . (string) $category['category_id'], "folder" => true, "children" => $categories_recursive
                )));
            }
        }

        if (!$sortOrder) {
            $categoryNode->sort();
        }

        return $categoryNode->render();
    }

    public function getCategoryAttributeTree()
    { // Category_attribute_tree
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $sortOrder = isset($this->request->get['sortOrder']) ? filter_var($this->request->get['sortOrder'], FILTER_VALIDATE_BOOLEAN) : true;
        $key = isset($this->request->get['category_id']) ? explode("_", $this->request->get['category_id']) : array('0', '0');

        if (isset($this->session->data['a_debug_mode'])) {
            $this->debug_mode = $this->session->data['a_debug_mode'];
        }

        $tree = isset($this->request->get['tree']) ? $this->request->get['tree'] : '1';

        $children = $this->childrenSettings($tree);

        if ($key[0] == 'category') {
            $category_id = $key[1];
        } else {
            $category_id = '0';
        }

        $this->load->model($this->modelfile);

        $rootData = array("title" => $this->session->data['error_not_category'][$language_id]);

        $filter_data = array(
            'category_id' => (int) $category_id,
            'language_id' => (int) $language_id,
            'sort' => $sortOrder ? 'sort_attribute_group, a.sort_order' : ''
        );

        $attributeNode = new Node();
        if (is_numeric($category_id) && $category_id !== '0') {
            $categoryAttributes = $this->{$this->model}->getCategoryAttributes($filter_data);
            $category_description = $this->{$this->model}->getCategoryDescriptions($category_id);
            foreach ($categoryAttributes as $attribute) {
                $dutyNode = new Node(array("title" => $attribute['duty'], "key" => "duty_" . (string) $attribute['attribute_id'], "extraClasses" => "custom1",));
                $templateNode = new Node(array(
                    "title" => $this->session->data['entry_templates'][$language_id], "unselectable" => true,
                    "key" => "template_" . (string) $attribute['attribute_id'], "lazy" => true,
                ));
                $valueNode = new Node(array(
                    "title" => $this->session->data['entry_values'][$language_id], "unselectable" => true,
                    "key" => "value_" . (string) $attribute['attribute_id'], "lazy" => true,
                ));
                $childNode = new Node();

                if ($children['duty']) {
                    $childNode->addSibling($dutyNode);
                }
                if ($children['template']) {
                    $childNode->addSibling($templateNode);
                }
                if ($children['value']) {
                    $childNode->addSibling($valueNode);
                }

                $debug_attribute = $this->debug_mode ? " (id=" . $attribute['attribute_id'] . ")" : '';
                $attributeNode->addSibling(new Node(array(
                    "title" => $attribute['attribute_description'] . ' (' . $attribute['group_name'] . ')' . $debug_attribute,
                    "key" => "attribute_" . (string) $attribute['attribute_id'], "children" => $childNode->render()
                )));
            }

            if (!$sortOrder) {
                $attributeNode->sort();
            }

            $rootData = array(
                "title" => $this->session->data['entry_attributes'][$language_id] . ' (' . $category_description[(int) $language_id]['name'] . ')',
                "folder" => true,
                "expanded" => true,
                "children" => $attributeNode->render(),
                "key" => "category_" . (string) $category_id,
            );
        }

        $CategoryAttributeTree = new Tree(new Node($rootData));

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($CategoryAttributeTree->render()));
    }

    //------------------------------------------------ProductTree-------------------------------------------------------
    public function getProductTree()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        $key = $this->issetRequest('post', 'attribute_id', array('0', '0'));
        $title = $this->issetRequest('post', 'title', '');
        $invert = $this->issetRequest('post', 'invert', false);
        $value_compare_mode = $this->configGet($this->module . '_value_compare_mode', 'substr');
        $splitter = $this->configGet($this->module . '_splitter', '/', true);

        if (($key[0] == 'template' || $key[0] == 'value' || $key[0] == 'duty') && $invert) {
            $invert = false;
            $diver = true;
        } else {
            $diver = false;
        }

        if (isset($this->session->data['a_debug_mode'])) {
            $this->debug_mode = $this->session->data['a_debug_mode'];
        }

        if ($key[0] == 'attribute' || $key[0] == 'template' || $key[0] == 'value' || $key[0] == 'duty') {
            $attribute_id = $key[1];
        } else {
            $attribute_id = '0';
        }

        $non_hierarchical = true;
        $rootData = array("title" => $this->session->data['error_not_attribute'][$language_id]);

        $this->load->model($this->modelfile);
        $all_categories = $this->{$this->model}->getAllCategories($non_hierarchical);
        $sort_order = array();

        foreach ($all_categories as $k => $value) {
            $sort_order[$k] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $all_categories);

        $attribute_descriptions = $this->{$this->model}->getAttributeDescriptions($attribute_id);

        if (is_numeric($attribute_id) && $attribute_id !== '0') {
            $categoryNode = new Node();
            foreach ($all_categories as $category) {
                $productNode = new Node();
                $category_id = $category['category_id'];
                $products = $this->{$this->model}->getProductsByAttribute($category_id, $attribute_id, $language_id, $invert);
                foreach ($products as $product) {
                    $debug_category = $this->debug_mode ? " (cat=" . $product['category_id'] . ")" : '';
                    //  $childNode = new Node();
                    $product_item = new Node(array(
                        "title" => $product['product_name'] . ' (id=' . $product['product_id'] . ', model=' . $product['model'] . ')' . $debug_category,
                        "key" => "product_" . (string) $product['product_id'],
                        "extraClasses" => "custom2"
                    ));
                    if (!$diver) {
                        switch ($key[0]) {
                            case 'template':
                            case 'duty':
                                if ($product['text'] == $title) {
                                    $productNode->addSibling($product_item);
                                }
                                break;
                            case 'value':
                                if (compareValue($title, $product['text'], $value_compare_mode, $splitter)) {
                                    $productNode->addSibling($product_item);
                                }
                                break;
                            default:
                                $productNode->addSibling($product_item);
                        }
                    } else {
                        switch ($key[0]) {
                            case 'template':
                            case 'duty':
                                if ($product['text'] != $title) {
                                    $productNode->addSibling($product_item);
                                }
                                break;
                            case 'value':
                                if (!compareValue($title, $product['text'], $value_compare_mode, $splitter)) {
                                    $productNode->addSibling($product_item);
                                }
                                break;
                            default:
                                $productNode->addSibling($product_item);
                        }
                    }
                }

                $debug_category = $this->debug_mode ? " (id=" . $category['category_id'] . ")" : '';
                if ($productNode->nodeData) {
                    $categoryNode->addSibling(new Node(array(
                        "title" => $category['name'] . $debug_category,
                        "key" => "category_" . (string) $category['category_id'],
                        "folder" => $diver || $invert ? false : true,
                        "extraClasses" => $diver || $invert ? "custom4" : "",
                        "children" => $productNode->render()
                    )));
                }
            }

            $debug_attribute = $this->debug_mode ? " (id=" . $attribute_id . ")" : '';
            $rootData = array(
                "title" => $this->session->data['entry_products'][$language_id] . ' (' . $attribute_descriptions[(int) $language_id]['name'] . ')' . $debug_attribute,
                "folder" => $diver || $invert ? false : true,
                "extraClasses" => $diver || $invert ? "custom4" : "",
                "expanded" => true,
                "children" => $categoryNode->render(),
                "key" => "attribute_" . (string) $attribute_id,
            );
        }

        $ProductTree = new Tree(new Node($rootData));
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($ProductTree->render()));
    }

    // ----------------------- The end of getTree functions ------------------------

    /**
     * Save result after edit by inline fancytree editor
     *
     * @return void
     */
    public function editAttribute()
    {
        $data = array();
        $language_id = isset($this->request->post['language_id']) ? $this->request->post['language_id'] : $this->config->get('config_language_id');
        $name = isset($this->request->post['name']) ? htmlspecialchars_decode($this->request->post['name']) : '';
        $key = isset($this->request->post['key']) ? explode("_", $this->request->post['key']) : array('0', '0');
        $splitter = $this->configGet($this->module . '_splitter', '/', true);
        //$clone = isset($this->request->post['clone']) ? filter_var($this->request->post['clone'], FILTER_VALIDATE_BOOLEAN) : false;
        $oldname = isset($this->request->post['oldname']) ? htmlspecialchars_decode($this->request->post['oldname']) : '';

        $this->load->model($this->modelfile);

        if ($this->session->data['free']) {
            $acceptedTitle["acceptedTitle"] = $name;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($acceptedTitle));
            return;
        }

        if ($key[0] == 'group') {
            $attribute_group_id = $key[1];
            $data['attribute_group_description'][$language_id]['name'] = $name;
            $this->{$this->model}->editAttributeGroup($attribute_group_id, $data);
        }

        if ($key[0] == 'attribute') {
            $attribute_id = $key[1];
            $data['attribute_description'][$language_id]['name'] = $name;
            $this->{$this->model}->editAttributeName($attribute_id, $data);
        }

        if ($key[0] == 'template') {
            $attribute_id = $key[1];
            $data['language_id'] = $language_id;
            $data['oldtext'] = $oldname;
            $data['newtext'] = trim($name, $splitter);
            $this->{$this->model}->editAttributeTemplates($attribute_id, $data);
        }

        if ($key[0] == 'value') {
            $attribute_id = $key[1];
            $data['language_id'] = $language_id;
            $data['oldtext'] = $oldname;
            $data['newtext'] = trim($name, $splitter);
            $this->{$this->model}->editAttributeValues($attribute_id, $data);
        }

        if ($key[0] == 'duty') {
            $attribute_id = $key[1];
            $data['attribute_description'][$language_id]['duty'] = $name;
            $this->{$this->model}->editDuty($attribute_id, $data);
        }

        $acceptedTitle["acceptedTitle"] = $name;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($acceptedTitle));
    }

    /**
     * Update Duty after delete or clone action of CRUD
     *
     * @return void
     */
    public function updateDuty()
    {
        $data = array();
        $language_id = isset($this->request->post['language_id']) ? $this->request->post['language_id'] : $this->config->get('config_language_id');
        $name = isset($this->request->post['name']) ? htmlspecialchars_decode($this->request->post['name']) : '';
        $key = isset($this->request->post['key']) ? explode("_", $this->request->post['key']) : array('0', '0');
        $clone = isset($this->request->post['clone']) ? filter_var($this->request->post['clone'], FILTER_VALIDATE_BOOLEAN) : false;

        $this->load->model($this->modelfile);

        if ($key[0] == 'duty') {
            $attribute_id = $key[1];
            if ($clone) {
                $languages = $this->getLanguages();
                foreach ($languages as $language) {
                    $data['attribute_description'][$language['language_id']]['duty'] = $name;
                }
            } else {
                $data['attribute_description'][$language_id]['duty'] = $name;
            }
            $this->{$this->model}->editDuty($attribute_id, $data);
        }
    }

    /* Add New attribute or group */
    public function addAttribute()
    {
        $data = array();
        $data['new'] = true;
        $tree = isset($this->request->get['tree']) ? $this->request->get['tree'] : '1';
        $key = isset($this->request->get['key']) ? explode("_", $this->request->get['key']) : array('0', '0');
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $lazyLoad = isset($this->request->get['lazyLoad']) ? filter_var($this->request->get['lazyLoad'], FILTER_VALIDATE_BOOLEAN) : false;
        $attribute_group_id = '';

        if ($this->session->data['free']) {
            return 0;
        }
        if ($key[0] == 'group') {
            $attribute_group_id = $key[1];
        }

        $languages = $this->getLanguages();
        $current_lng = $this->getLanguage($language_id);

        $data['sort_order'] = '';
        $this->load->model($this->modelfile);
        $this->cache->delete($this->module);

        if ($attribute_group_id) {
            $data['attribute_group_id'] = $attribute_group_id;
            foreach ($languages as $language) {
                // В lng будет массив из языкового файла
                $lng = $this->getLanguage($language['language_id']);
                // Заполняем названия нового атрибута для каждого языка
                $data['attribute_description'][$language['language_id']]['name'] = $lng->get('text_New_attribute');
            }
            // Добавляем новую запись в БД
            $new_attribute_id = $this->{$this->model}->addAttribute($data);

            $children = $this->childrenSettings($tree);

            $templateNode = new Node(array(
                "title" => $current_lng->get('entry_templates'), "unselectable" => true, "key" => "template_" . (string) $new_attribute_id,
                "children" => $lazyLoad ? '' : $this->getAttributeValuesNodes($new_attribute_id, $language_id, 'template'), "lazy" => $lazyLoad ? true : false,
            ));
            $valueNode = new Node(array(
                "title" => $current_lng->get('entry_values'), "unselectable" => true, "key" => "value_" . (string) $new_attribute_id,
                "children" => $lazyLoad ? '' : $this->getAttributeValuesNodes($new_attribute_id, $language_id, 'values'), "lazy" => $lazyLoad ? true : false,
            ));
            $dutyNode = new Node(array("title" => "", "key" => "duty_" . (string) $new_attribute_id, "extraClasses" => "custom1",));

            $childNode = new Node();
            if ($children['duty']) {
                $childNode->addSibling($dutyNode);
            }
            if ($children['template']) {
                $childNode->addSibling($templateNode);
            }
            if ($children['value']) {
                $childNode->addSibling($valueNode);
            }
            $node_data = array(
                "title" => $current_lng->get('text_New_attribute') . "_" . (string) $new_attribute_id,
                "key" => "attribute_" . (string) $new_attribute_id,
                "folder" => false,
                "children" => $childNode->render()
            );
        } else {
            foreach ($languages as $language) {
                $lng = $this->getLanguage($language['language_id']);
                $data['attribute_group_description'][$language['language_id']]['name'] = $lng->get('text_New_group');
            }
            $new_group_id = $this->{$this->model}->addAttributeGroup($data);
            $node_data = array(
                "title" => $current_lng->get('text_New_group') . "_" . (string) $new_group_id,
                "key" => "group_" . (string) $new_group_id,
                "folder" => true,
                "extraClasses" => $new_group_id == 1 ? "custom3" : ''
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($node_data));
    }

    /**
     * Paste Attributes functions
     * TODO: rename function
     * @param array $titles
     * $titles oldstructure example
     * [[empty,A1ru,empty,A1en],[empty,A2ru,empty,A2en],...[empty,A100ru,empty,A100en]]
     * empty if language not present by any id
     * 
     * @param array $attributes
     * @param string $target NodeKey type
     * 
     * This function knows nothing about the data structure.
     * It just prepares headers and identifiers
     * Placement is made by the model
     * 
     * @return json
     */
    public function addAttributes()
    {
        /** 
         * $titles oldstructure example
         *  [[empty,A1ru,empty,A1en],[empty,A2ru,empty,A2en],...[empty,A100ru,empty,A100en]]
         *  empty if language not present by any id         
         */
        $data = array();
        $data['new'] = false;
        $target = isset($this->request->post['target']) ? explode("_", $this->request->post['target']) : array('0', '0');
        $titles = isset($this->request->post['titles']) ? $this->request->post['titles'] : array('0', '0');
        $attributes = isset($this->request->post['attributes']) ? $this->request->post['attributes'] : array('0', '0');
        $attribute_group_id = '';

        if ($target[0] == 'group') {
            $attribute_group_id = $target[1];
        }
        if ($this->session->data['free']) {
            return 0;
        }

        $attributes_id = [];
        foreach ($attributes as $attribute) {
            $attributes_id[] = explode("_", $attribute)[1];
        }

        $languages = $this->getLanguages();

        /** 
         * Transform arr.id [123, 124 ... 129] and arr.titles [[],[тайтл123,тайтл124... ],[],[title123,title124,...]
         * to arr [123 => [тайтл123,title123], 124 => [тайтл123,title123], ...]
         */
        $new_titles = [];
        foreach ($languages as $language) {
            foreach ($titles[$language['language_id']] as $key => $title) {
                $new_titles[$attributes_id[$key]][$language['language_id']] = $title;
            }
        }

        $this->load->model($this->modelfile);
        $this->cache->delete($this->module);

        foreach ($new_titles as $attribute_id => $title) {
            if ($attribute_group_id) {
                $data['attribute_group_id'] = $attribute_group_id;
                $data['attribute_id'] = $attribute_id;
                foreach ($languages as $language) {
                    $data['attribute_description'][$language['language_id']]['name'] = $title[$language['language_id']];
                }
                $id = $this->{$this->model}->addAttribute($data);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($id));
    }

    public function deleteAttributes()
    {
        $data = array();
        $keys = isset($this->request->post['keys']) ? $this->request->post['keys'] : array('0', '0');
        $titles = isset($this->request->post['titles']) ? $this->request->post['titles'] : array('', '');
        $language_id = isset($this->request->post['language_id']) ? $this->request->post['language_id'] : $this->config->get('config_language_id');

        $combine = array_combine($keys, $titles);

        foreach ($combine as $key => $value) {
            $node_type = explode("_", $key);
            if ($node_type[0] == 'group') {
                $data['group'][] = $node_type[1];
            }
            if ($node_type[0] == 'attribute') {
                $data['attribute'][] = $node_type[1];
            }
            if ($node_type[0] == 'template') {
                $data['template'][] = ['attribute_id' => $node_type[1], 'value' => $value];
            }
            if ($node_type[0] == 'value') {
                $data['value'][] = ['attribute_id' => $node_type[1], 'value' => $value];
            }
        }

        if ($this->session->data['free']) {
            return;
        }

        $this->load->model($this->modelfile);
        $this->{$this->model}->deleteAttributeGroups($data);
        $this->{$this->model}->deleteAttributes($data);
        $this->{$this->model}->deleteValues($data, $language_id);
    }

    public function replaceAttributeGroup()
    {
        $attribute_group_id = '';
        $target = isset($this->request->post['target']) ? explode("_", $this->request->post['target']) : array('0', '0');
        $subjects = isset($this->request->post['subjects']) ? $this->request->post['subjects'] : array();
        $group = isset($this->request->post['group']) ? explode("_", $this->request->post['group']) : array('0', '0');

        $this->load->model($this->modelfile);

        if ($target[0] == 'group') {
            $attribute_group_id = $target[1];
        } elseif ($target[0] == 'attribute' && $group[0] == 'group') {
            $attribute_group_id = $group[1];
        }

        if ($attribute_group_id) {
            $this->cache->delete($this->module);
            foreach ($subjects as $subject) {
                $attribute_id = explode("_", $subject);
                $this->{$this->model}->replaceAttributeGroup($attribute_id[1], $attribute_group_id);
            }
        }
    }

    public function sortAttribute()
    {
        $data = array();
        $target = isset($this->request->post['target']) ? explode("_", $this->request->post['target']) : array('0', '0');
        $direct = isset($this->request->post['direct']) ? $this->request->post['direct'] : "before";
        $subjects = isset($this->request->post['subjects']) ? $this->request->post['subjects'] : array('0', '0');

        $data['table'] = $target[0];
        $data['target_id'] = $target[1];
        $data['direct'] = $direct;
        foreach ($subjects as $subject) {
            $subject_id = explode("_", $subject);
            $data['subject_id'][] = $subject_id[1];
        }

        $this->load->model($this->modelfile);

        $this->{$this->model}->sortAttribute($data);
    }

    public function mergeAttributeGroup()
    {
        $target = isset($this->request->post['target']) ? explode("_", $this->request->post['target']) : array('0', '0');
        $subjects = isset($this->request->post['subjects']) ? $this->request->post['subjects'] : array('0', '0');

        if ($this->session->data['free']) {
            return;
        }
        if ($target[0] == 'group') {
            $this->load->model($this->modelfile);
            $attribute_group_id = $target[1];
            $this->cache->delete($this->module);
            foreach ($subjects as $subject) {
                $attribute_id = explode("_", $subject);
                if ($attribute_id[0] == 'attribute') {
                    $this->{$this->model}->replaceAttributeGroup($attribute_id[1], $attribute_group_id);
                }
                if ($attribute_id[0] == 'group') {
                    $filter_data = array(
                        'filter_attribute_group_id' => (int) $attribute_id[1],
                    );
                    $attributes = $this->{$this->model}->getAttributes($filter_data);
                    foreach ($attributes as $attribute) {
                        $this->{$this->model}->replaceAttributeGroup($attribute['attribute_id'], $attribute_group_id);
                    }
                    $this->{$this->model}->deleteAttributeGroup($attribute_id[1]);
                }
            }
        }
        if ($target[0] == 'attribute') {
            $this->load->model($this->modelfile . '_tools');
            $this->cache->delete($this->module);
            foreach ($subjects as $subject) {
                $subject_id = explode("_", $subject);
                $this->{$this->model_tools}->mergeAttribute($target[1], $subject_id[1]);
            }
        }
    }

    /**
     * The Tree function is calling from addAttributeToCategory function in CRUD operations
     * Is working at drag&drop attribute (multi attributes) to category (multi categories)
     */
    public function addCategoryAttributes()
    {
        $category_attributes = array();
        $sub_categories = array();
        // Target category id
        $category_id = isset($this->request->post['category_id']) ? explode("_", $this->request->post['category_id'])[1] : '0';
        // All selected attributes in attribute tree
        $attributes = isset($this->request->post['attributes']) ? $this->request->post['attributes'] : array();
        // All selected categories in category tree
        $categoryList = isset($this->request->post['categories']) ? $this->request->post['categories'] : array();
        // Inheritance
        $subCategory = $this->config->get($this->module . '_autoadd_subcategory');
        // TODO check it in frontend
        $multistore = isset($this->request->post['multistore']) ? filter_var($this->request->post['multistore'], FILTER_VALIDATE_BOOLEAN) : $this->config->get($this->module . '_multistore');

        $this->config->set($this->module . '_multistore', (string) $multistore); //?

        if ($this->session->data['free']) {
            return 0;
        }

        if (is_array($attributes)) {
            foreach ($attributes as $attribute) {
                $category_attributes[] = explode("_", $attribute)[1];
            }
        } else {
            $category_attributes[] = explode("_", $attributes)[1];
        }

        $this->load->model($this->modelfile);
        $all_categories = $this->{$this->model}->getAllCategories();

        if (is_numeric($category_id) && $category_id !== '0') {
            // Add target category id
            $categories = array((int) $category_id);

            if ($categoryList) { //TODO Get subcategories for all selected categories or target category only?
                foreach ($categoryList as $category) {
                    $categories[] = (int) explode("_", $category)[1];
                }
            } elseif ($subCategory) {
                // Inheritance for target category 
                $sub_categories = $this->getsubCategories($all_categories, $category_id);
                $array_iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($sub_categories), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($array_iterator as $id => $value) {
                    $categories[] = $id;
                }
            }

            $this->cache->delete($this->module);
            $languages = $this->getLanguages();

            foreach ($categories as $CategoryId) {
                if ($this->config->get($this->module . '_autoadd')) {
                    // Insert duty to products 
                    $category_products = $this->{$this->model}->getProductsByCategoryId($CategoryId);
                    $this->{$this->model}->addAttributesToProducts($category_products, $category_attributes, $languages);
                }
                $this->{$this->model}->addCategoryAttributes($CategoryId, $category_attributes);
            }
        }
    }

    public function deleteAttributesFromCategory()
    {
        $category_id = isset($this->request->post['category_id']) ? explode("_", $this->request->post['category_id'])[1] : '0';
        $attributes = isset($this->request->post['attributes']) ? $this->request->post['attributes'] : array();
        $categoryList = isset($this->request->post['categories']) ? $this->request->post['categories'] : array();
        $subCategory = $this->config->get($this->module . '_autodel_subcategory');
        $multistore = isset($this->request->post['multistore']) ? filter_var($this->request->post['multistore'], FILTER_VALIDATE_BOOLEAN) : $this->config->get($this->module . '_multistore');

        $this->config->set($this->module . '_multistore', (string) $multistore);

        if ($this->session->data['free']) {
            return 0;
        }

        $category_attributes = array();
        foreach ($attributes as $attribute) {
            $category_attributes[] = explode("_", $attribute)[1];
        }

        /* if ($this->session->data['free']) {
            return;
        } */

        $this->load->model($this->modelfile);
        $all_categories = $this->{$this->model}->getAllCategories();

        if (is_numeric($category_id) && $category_id !== '0') {
            $categories = array((int) $category_id);

            if ($categoryList) {
                foreach ($categoryList as $category) {
                    $categories[] = (int) explode("_", $category)[1];
                }
            } elseif ($subCategory) {
                $sub_categories = $this->getsubCategories($all_categories, $category_id);
                $array_iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($sub_categories), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($array_iterator as $key => $value) {
                    $categories[] = $key;
                }
            }

            $this->cache->delete($this->module);
            foreach ($categories as $CategoryId) {
                if ($this->config->get($this->module . '_autodel')) {
                    $category_products = $this->{$this->model}->getProductsByCategoryId($CategoryId);
                    $this->{$this->model}->deleteAttributesFromProducts($category_products, $category_attributes);
                }
                $this->{$this->model}->deleteAttributesFromCategory($CategoryId, $category_attributes);
            }
        }
    }

    private function getsubCategories($categories, $parent_id)
    {
        $sub_categories = array();
        if (array_key_exists($parent_id, $categories)) {
            foreach ($categories[$parent_id] as $category) {
                $sub_categories[$category['category_id']] = $this->getsubCategories($categories, $category['category_id']);
            }
        }
        return $sub_categories;
    }

    private function childrenSettings($tree)
    {
        if ($this->config->get($this->module . '_children')) {
            $settings = unserialize($this->config->get($this->module . '_children'));
        } else {
            $settings = $this->settings;
        }
        return array(
            "template" => isset($settings[$tree]) ? in_array("template", $settings[$tree]) : false,
            "value" => isset($settings[$tree]) ? in_array("value", $settings[$tree]) : false,
            "duty" => isset($settings[$tree]) ? in_array("duty", $settings[$tree]) : false
        );
    }

    protected function getLanguage($language_id)
    {
        $extension = version_compare(VERSION, '2.3.0', '>=') ? "extension/" : "";
        $directory = $this->getLanguageDirectory($language_id);
        $language = new Language($directory);
        $language->load($extension . $this->modulefile);
        return $language;
    }

    protected function getLanguageDirectory($language_id)
    {
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $lang) {
            if ($lang['language_id'] == $language_id) {
                if (version_compare(VERSION, '2.2', '>') == true) {
                    return $lang['code'];
                } else {
                    return $lang['directory'];
                }
            }
        }
        return "english";
    }

    protected function getLanguages()
    {
        if (isset($this->session->data['languages'])) {
            $languages = $this->session->data['languages'];
        } else {
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
        }
        return $languages;
    }

    public function autocomplete()
    {
        $json = array();

        if (isset($this->request->get['filter_name'])) {
            $this->load->model($this->modelfile);

            $filter_data = array(
                'filter_name' => $this->request->get['filter_name'],
                'start' => 0,
                'limit' => 10000
            );
            if (isset($this->request->get['language_id'])) {
                $filter_data['language_id'] = $this->request->get['language_id'];
            }

            $results = $this->{$this->model}->getAttributes($filter_data);

            foreach ($results as $result) {
                $json[] = array(
                    'attribute_id' => $result['attribute_id'],
                    'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                    'attribute_group' => $result['group_name']
                );
            }
        }

        $sort_order = array();

        foreach ($json as $key => $value) {
            $sort_order[$key] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $json);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "category_attribute
		(`category_id` INTEGER(11) NOT NULL,`attribute_id` INTEGER(11) NOT NULL, PRIMARY KEY (`category_id`,`attribute_id`) USING BTREE)
         CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");
        // For version 4 attributico
        /* $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "attribute_description_pro (
            attribute_id int(11) NOT NULL,
            language_id int(11) NOT NULL,
            name varchar(64) NOT NULL,           
            duty text NOT NULL,            
            duty_status tinyint(1) NOT NULL DEFAULT 1,            
            PRIMARY KEY (attribute_id, language_id)
          )
          ENGINE = MYISAM, CHARACTER SET utf8, COLLATE utf8_general_ci");

        $query = $this->columnCheck('attribute_description', 'duty') ? "INSERT INTO " . DB_PREFIX . "attribute_description_pro (attribute_id, language_id, name, duty) 
          SELECT ad.attribute_id, ad.language_id, ad.name, ad.duty FROM " . DB_PREFIX . "attribute_description ad
          ON DUPLICATE KEY UPDATE attribute_id=ad.attribute_id, language_id=ad.language_id, name=ad.name, duty=ad.duty" : "INSERT INTO " . DB_PREFIX . "attribute_description_pro (attribute_id, language_id, name) 
          SELECT ad.attribute_id, ad.language_id, ad.name FROM " . DB_PREFIX . "attribute_description ad
          ON DUPLICATE KEY UPDATE attribute_id=ad.attribute_id, language_id=ad.language_id, name=ad.name";

        $this->db->query($query); */

        foreach ($this->dbstructure as $checking_table => $checking_column) {
            foreach ($checking_column as $column_name => $column_type)
                if (!$this->columnCheck($checking_table, $column_name)) {
                    $this->columnUpgrade($checking_table,  $column_name, $column_type);
                }
        }

        $data[$this->module . '_splitter'] = '/';
        $data[$this->module . '_cache'] = '1';
        $data[$this->module . '_lazyload'] = '1';
        $data[$this->module . '_children'] = 'a:5:{i:1;a:2:{i:0;s:8:"template";i:1;s:5:"value";}i:2;a:1:{i:0;s:4:"duty";}i:3;a:1:{i:0;s:4:"duty";}i:4;a:2:{i:0;s:8:"template";i:1;s:5:"value";}i:5;a:2:{i:0;s:8:"template";i:1;s:5:"value";}}';
        $data['module_' . $this->module . '_status'] = '1';

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting($this->module, $data);
        $this->model_setting_setting->editSetting('module_' . $this->module, $data);
    }

    public function uninstall()
    {
        $data['module_' . $this->module . '_status'] = 0;

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_' . $this->module, $data);
        $this->cache->delete($this->module);
    }

    public function columnsCheck($table, $checking_columns)
    {
        $query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_DATABASE . "' AND TABLE_NAME='" . DB_PREFIX . $table . "' ");
        $existing_columns = [];
        foreach ($query->rows as $column) {
            $existing_columns[$column['COLUMN_NAME']] = $column['COLUMN_TYPE'] . ($column['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : ' DEFAULT NULL') . ($column['COLUMN_DEFAULT'] ? ' DEFAULT ' . $column['COLUMN_DEFAULT'] : '') . ($column['EXTRA'] ? ' ' . $column['EXTRA'] : '');
        };
        $result = array_diff_assoc($checking_columns, $existing_columns);
        return (empty($result));
    }

    public function columnCheck($table, $column)
    {
        $query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_DATABASE . "' AND TABLE_NAME='" . DB_PREFIX . $table . "' AND COLUMN_NAME='" . $column . "'");
        return (!empty($query->row));
    }

    public function columnUpgrade($table, $column, $type)
    {
        $this->db->query("ALTER TABLE " . DB_PREFIX . $table . " ADD COLUMN " . $column . " " . $type);
        return true;
    }

    protected function tableCheck($table)
    {
        $query = $this->db->query("SELECT * FROM information_schema.tables WHERE TABLE_NAME='" . DB_PREFIX . $table . "' LIMIT 1");
        return (!empty($query->row));
    }

    private function dbStructureCheck()
    {
        foreach ($this->dbstructure as $checking_table => $checking_columns) {
            if (!$this->tableCheck($checking_table)) {
                return false;
            }
            if (!$this->columnsCheck($checking_table, $checking_columns)) {
                return false;
            }
            /* foreach ($checking_column as $column_name => $column_type)
                if (!$this->columnCheck($checking_table, $column_name)) {
                    return false;
                } */
        }

        return true;
    }

    // settings
    public function getChildrenSettings()
    {
        $language_id = isset($this->request->get['language_id']) ? $this->request->get['language_id'] : $this->config->get('config_language_id');
        $tree = isset($this->request->get['tree']) ? $this->request->get['tree'] : '';

        $children = $this->childrenSettings($tree);

        $rootData = array(
            "title" => $this->session->data['entry_attribute'][$language_id], "expanded" => true, "unselectable" => true, "checkbox" => false,
            "children" => array(
                array(
                    "title" => $this->session->data['entry_duty'][$language_id], "key" => "duty", "extraClasses" => "custom1", "selected" => $tree === '2' ? true : $children['duty'],
                    "unselectable" => $tree === '2' ? true : false
                ),
                array("title" => $this->session->data['entry_templates'][$language_id], "key" => "template", "selected" => $children['template']),
                array("title" => $this->session->data['entry_values'][$language_id], "key" => "value", "selected" => $children['value']),
            ),
        );

        $childrens = new Tree(new Node($rootData));

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($childrens->render()));
    }

    public function setFilterSettings()
    {
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $children = array();
            $i = 1;
            while (isset($this->request->post['ft_' . $i])) {
                $children[$i] = $this->request->post['ft_' . $i];
                $i++;
            }
            $filter_settings[$this->module . '_filter'] = serialize($this->request->post);
            $this->model_setting_setting->editSetting($this->module, $filter_settings);
        }
    }

    public function debugSwitch()
    {
        $this->cache->delete($this->module);
        $this->debug_mode = !$this->session->data['a_debug_mode'];
        $this->session->data['a_debug_mode'] = $this->debug_mode;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->debug_mode));
    }

    //------------------------------------------------------ Tools ------------------------------------------
    public function tools()
    {
        $language = $this->getLanguage($this->config->get('config_language_id'));
        $task = isset($this->request->post['task']) ? $this->request->post['task'] : "";
        $task_result = $language->get('alert_success') . "  ";

        $options = array();
        if (isset($this->request->post['options'])) {
            parse_str(htmlspecialchars_decode($this->request->post['options']), $options);
        }

        if ($this->session->data['free']) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($language->get('error_free')));
            return;
        }

        $this->load->model($this->modelfile . '_tools');
        $this->load->model($this->modelfile);

        switch ($task) {
            case 'empty':
                $count_of_empty = $this->{$this->model_tools}->deleteEmptyValues();
                $task_result .= $language->get('message_empty') . "  " . (string) $count_of_empty;
                break;
            case 'defrag':
                if (isset($options['tab-defrag-group'])) {
                    $count_of_defragmentation_group = $this->{$this->model_tools}->defragmentation('attribute_group', 'attribute_group_id');
                    $task_result .= $language->get('message_defragmentation_group') . "  " . (string) $count_of_defragmentation_group . " ";
                }
                if (isset($options['tab-defrag-attribute'])) {
                    $count_of_defragmentation = $this->{$this->model_tools}->defragmentation('attribute', 'attribute_id');
                    $task_result .= $language->get('message_defragmentation') . "  " . (string) $count_of_defragmentation;
                }
                break;
            case 'sorting':
                $count_of_sorted = $this->{$this->model_tools}->sorting();
                $task_result .= $language->get('message_sorted') . "  " . (string) $count_of_sorted;
                break;
            case 'scavengery':
                $count_of_scavengery = $this->{$this->model_tools}->scavengery();
                $task_result .= $language->get('message_scavengery') . "  " . (string) $count_of_scavengery;
                break;
            case 'detached':
                $count_of_detached = 0;
                if (isset($options[$this::TOOLS_GROUP_TREE])) {
                    foreach ($options[$this::TOOLS_GROUP_TREE] as $group) {
                        $group_id = explode("_", $group);
                        if ($group_id[0] == 'group') {
                            $count_of_detached = $this->{$this->model_tools}->detached($group_id[1]);
                        }
                    }
                }
                $task_result .= $language->get('message_detached') . "  " . (string) $count_of_detached;
                break;
            case 'deduplicate':
                $count_of_duplicates = 0;
                if (isset($options[$this::TOOLS_GROUP_TREE])) {
                    foreach ($options[$this::TOOLS_GROUP_TREE] as $group) {
                        $group_id = explode("_", $group);
                        if ($group_id[0] == 'group') {
                            $count_of_duplicates += $this->{$this->model_tools}->deduplicate($group_id[1]);
                        }
                    }
                }
                $task_result .= $language->get('message_duplicate') . "  " . (string) $count_of_duplicates;
                break;
            case 'createcategory':
                $count_of_categories = 0;
                $count_of_products = 0;
                $categories = array();
                /* $start_time = microtime(true); */
                if (isset($options[$this::TOOLS_CATEGORY_TREE])) {
                    foreach ($options[$this::TOOLS_CATEGORY_TREE] as $category) {
                        $categories[] = explode("_", $category)[1];
                    }
                    if (isset($options['tab-create-categories'])) {
                        $count_of_categories = $this->{$this->model_tools}->createCategoryAttributes($categories);
                        $task_result .= $language->get('message_create_categories') . "  " . (string) $count_of_categories . "  ";
                    }
                    if (isset($options['tab-inject-to-products'])) {
                        $this->cache->delete($this->module);

                        foreach ($categories as $CategoryId) {
                            $count_of_products += $this->{$this->model_tools}->addCategoryAttributesToProducts($CategoryId);
                        }
                        $task_result .= $language->get('message_inject_to_products') . "  " . (string) $count_of_products;
                        /*  $diff_time = microtime(true) - $start_time;
                        file_put_contents($this->module . '.txt', $diff_time, FILE_APPEND);
                        file_put_contents($this->module . '.txt', PHP_EOL, FILE_APPEND); */
                    }
                }
                break;
            case 'cache':
                $this->cache->delete($this->module);
                break;
            case 'clone':
                $source_lng = isset($options['clone-language-source']) ? $options['clone-language-source'] : $this->config->get('config_language_id');
                $target_lng = isset($options['clone-language-target']) ? $options['clone-language-target'] : $this->config->get('config_language_id');
                $mode = isset($options['clone-language-mode']) ? $options['clone-language-mode'] : 'insert';
                $node = [
                    'group' => isset($options['clone-language-group']),
                    'attribute' => isset($options['clone-language-attribute']),
                    'value' => isset($options['clone-language-value']),
                    'duty' => isset($options['clone-language-duty'])
                ];

                if ($source_lng !== $target_lng) {
                    $count_obj = $this->{$this->model_tools}->cloneLanguage($source_lng, $target_lng, $mode, $node);

                    $task_result .= $language->get('message_clone_group') . "  " . (string) $count_obj->group . " "
                        . $language->get('message_clone_attribute') . "  " . (string) $count_obj->attribute . " "
                        . $language->get('message_clone_value') . "  " . (string) $count_obj->value . " "
                        . $language->get('message_clone_duty') . "  " . (string) $count_obj->duty . " ";
                } else {
                    $task_result = $language->get('message_clone_error');
                }

                break;
            case 'batchreplace':
                $count_of_batch = 0;
                $search = isset($options['tab-batch-splitter-search']) ? $options['tab-batch-splitter-search'] : '';
                $replace = isset($options['tab-batch-splitter-replace']) ? $options['tab-batch-splitter-replace'] : '';
                $categories = $this->explodeOptions($options, $this::TOOLS_CATEGORY_TREE);
                $groups = $this->explodeOptions($options, $this::TOOLS_GROUP_TREE);

                // Pattern and replace cleaning
                $matches = preg_filter('/(?!_)[\w\d\s\[\]\'\"\-]/mu', '', ' ' . $search);
                /* $pattern =  '[' . preg_quote($matches, '/') . ']'; */
                $pattern =  $this->db->escape('[' . quotemeta($matches) . ']');
                $splitter = preg_filter('/(?!_)[\w\d\s\[\]\'\"\-]/mu', '', ' ' . $replace);

                if ($matches && $splitter) {
                    $splitter = substr($splitter, 0, 1);

                    $batch_records = $this->{$this->model_tools}->getValuesByPattern($pattern, $categories, $groups);

                    if ($batch_records) {
                        foreach ($batch_records as $key => $record) {
                            $batch_records[$key]['text'] = preg_replace('/\s*' . $pattern . '\s*/mu', $splitter, $record['text']);
                        }
                        $count_of_batch += $this->{$this->model_tools}->updateValues($batch_records);
                    }

                    $task_result .= $language->get('message_batch') . "  " . (string) ($count_of_batch);
                } else {
                    $task_result = $language->get('error_splitter') . "  " . $language->get('message_batch') . "  " . (string) ($count_of_batch);
                }
                break;
            case 'casechange':
                $count_of_batch = 0;
                $group_case = isset($options['case_group_check']) ? $options['case_group'] : '';
                $attribute_case = isset($options['case_attribute_check']) ? $options['case_attribute'] : '';
                $value_case = isset($options['case_value_check']) ? $options['case_value'] : '';
                $duty_case = isset($options['case_duty_check']) ? $options['case_duty'] : '';
                $categories = $this->explodeOptions($options, $this::TOOLS_CATEGORY_TREE);
                $groups = $this->explodeOptions($options, $this::TOOLS_GROUP_TREE);

                if ($group_case) {
                    $case_records = $this->{$this->model_tools}->getGroups($groups);
                    $this->changeFirstCase($case_records, $group_case, 'name');
                    $count_of_batch += $this->{$this->model_tools}->updateGroups($case_records);
                }

                if ($attribute_case) {
                    $case_records = $this->{$this->model_tools}->getAttributesByGroups($groups);
                    $this->changeFirstCase($case_records, $attribute_case, 'name');
                    $count_of_batch += $this->{$this->model_tools}->updateAttributes($case_records);
                }

                if ($value_case) {
                    $pattern = '.';
                    $case_records = $this->{$this->model_tools}->getValuesByPattern($pattern, $categories, $groups);
                    $this->changeFirstCase($case_records, $value_case, 'text');
                    $count_of_batch += $this->{$this->model_tools}->updateValues($case_records);
                }

                if ($duty_case) {
                    $case_records = $this->{$this->model_tools}->getDuties($groups);
                    $this->changeFirstCase($case_records, $duty_case, 'duty');
                    $count_of_batch += $this->{$this->model_tools}->updateDuties($case_records);
                }

                $task_result .= $language->get('message_batch') . "  " . (string) ($count_of_batch);
                break;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($task_result));
    }

    private function changeFirstCase(&$case_records, $case, $column)
    {
        $splitter = !($this->config->get($this->module . '_splitter') == '') ? $this->config->get($this->module . '_splitter') : '/';

        if ($case_records) {
            foreach ($case_records as $key => $record) {
                // Split template
                $elements = explode($splitter, $record[$column]);
                switch ($case) {
                    case 'uppercase':
                        // Uppercase elements
                        foreach ($elements as $index => $element) {
                            $elements[$index] =  mb_ucfirst(trim($element));
                        }
                        break;
                    case 'lowercase':
                        foreach ($elements as $index => $element) {
                            $elements[$index] =  mb_lcfirst(trim($element));
                        }
                        break;
                }
                // Concat template
                $new_name = implode($splitter, $elements);
                // Return to records
                $case_records[$key][$column] = $new_name;
            }
        }
    }

    private function explodeOptions($options, $option_name)
    {
        $options_array = array();

        if (isset($options[$option_name])) {
            foreach ($options[$option_name] as $option) {
                $options_array[] = explode("_", $option)[1];
            }
        }
        return $options_array;
    }

    public function checkForUpdates()
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://attributico.su/check_for_updates.php?module=' . $this->module);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $content = json_decode(curl_exec($ch));
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        // $header = curl_getinfo($ch);
        curl_close($ch);

        $header['errno'] = $err;
        $header['errmsg'] = $errmsg;
        $header['content'] = $content;

        if (version_compare($this::MODULE_VERSION, $content->lastversion, '>=')) {
            $header['compare'] = 'OK!';
        } else {
            $header['compare'] = '';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($header));
    }

    public function cacheDelete()
    {

        $this->cache->delete($this->module);
    }
}

class ControllerExtensionModuleAttributico extends ControllerModuleAttributico
{
}
class ControllerModuleAttributipro extends ControllerModuleAttributico
{
    const MODULE_VERSION =  'v0.6.3';
    const TOOLS_GROUP_TREE = 'ft_6';
    const TOOLS_CATEGORY_TREE = 'ft_7';
    protected $dbstructure = array(
        'attribute_pro' => array(
            'attribute_id' => 'int(11) NOT NULL auto_increment',
            'attribute_group_id' => 'int(11) NOT NULL',
            'sort_order' => 'int(3) NOT NULL',
            'image' => "varchar(255) DEFAULT NULL",
            'icon' => "varchar(255) NOT NULL",
            'unit_id' => "int(11) NOT NULL",
            'status' => "tinyint(1) NOT NULL DEFAULT 1",
            'url' => "text NOT NULL",
        ),
        'attribute_description_pro' => array(
            'attribute_id' => 'int(11) NOT NULL',
            'language_id' => 'int(11) NOT NULL',
            'name' => 'varchar(64) NOT NULL',
            'tooltip' => "text NOT NULL",
            'duty' => "text NOT NULL",
            'duty_tooltip' => "text NOT NULL",
            'duty_image' => "varchar(255) DEFAULT NULL",
            'duty_icon' => "varchar(255) NOT NULL",
            'duty_unit_id' => "int(11) NOT NULL",
            'duty_status' => "tinyint(1) NOT NULL DEFAULT 1"
        ),
        'product_attribute_pro' => array(
            'product_id' => 'int(11) NOT NULL',
            'attribute_id' => 'int(11) NOT NULL',
            'language_id' => 'int(11) NOT NULL',
            'text' => 'text NOT NULL',
            'tooltip' => 'text NOT NULL',
            'image' => 'varchar(255) DEFAULT NULL',
            'icon' => 'varchar(255) NOT NULL',
            'unit_id' => 'int(11) NOT NULL',
            'status' => 'tinyint(1) NOT NULL DEFAULT 1',
            'url' => 'text NOT NULL',
        ),
        'unit' => array(
            'unit_id' => "int(11) NOT NULL auto_increment",
            'unit_group_id' => "int(11) NOT NULL",
            'sort_order' => "int(3) NOT NULL",
        ),
        'unit_description' => array(
            'unit_id' => "int(11) NOT NULL",
            'language_id' => "int(11) NOT NULL",
            'title' => "varchar(255) NOT NULL",
            'unit' => "varchar(32) NOT NULL",
        ),
        'attribute_interlink' => array(
            'rule_id'  => "int(11) NOT NULL auto_increment",
            'name' =>  "varchar(255) NOT NULL",
            'route' =>  "varchar(255) NOT NULL",
            'filter_alias' =>  "varchar(255) NOT NULL",
            'args' =>  "varchar(255) NOT NULL",
            'block_separator' =>  "char(20) NOT NULL",
            'between_separator' =>  "char(20) NOT NULL",
            'value_separator'  => "char(20) NOT NULL",
            'advance'  => "varchar(255) NOT NULL",
        ),
    );
    protected $module = 'attributipro';
    protected $modulefile = 'module/attributipro';
    protected $modelfile = 'catalog/attributipro';
    protected $model = 'model_catalog_attributipro';
    protected $model_tools = 'model_catalog_attributipro_tools';

    /**
     * Overloads base class method
     *
     * @return void
     */
    public function index()
    {
        $this->init();

        if ($this->tableCheck('unit') && $this->tableCheck('unit_description')) {
            $this->data['units'] = $this->getUnitOptions($this->config->get('config_language_id'), $this->data['not_selected']);
        } else {
            $this->data['units'] = [];
        }

        if ($this->tableCheck('attribute_interlink')) {
            $this->data['profiles'] = $this->makeOptionList($this->getInterlinkOptions(), $this->config->get($this->module . '_interlink'));
        } else {
            $this->data['profiles'] = [];
        }

        $this->out();
    }

    public function imageResize()
    {
        $image = $this->issetRequest('get', 'image', null);
        $size = $this->issetRequest('get', 'size', $this::DEFAULT_THUMBNAIL_SIZE);

        $thumb = $this->getThumbnail($image, $size);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($thumb, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Gets images thumb and resize it by size
     *
     * @param string $image url of image
     * @param integer $size
     * @return string url for thumb
     */
    private function getThumbnail($image, $size = 100)
    {
        /**
         * Resize image for form view
         */
        $this->load->model('tool/image');

        if (is_file(DIR_IMAGE . $image)) {
            $thumb = $this->model_tool_image->resize($image, $size, $size);
        } else {
            $thumb = $this->model_tool_image->resize('no_image.png', $size, $size);;
        }

        return $thumb;
    }

    protected function getUnitOptions($language_id, $title0)
    {
        if (!$this->user->hasPermission('modify', 'attributico/unit')) {
            $this->data['error_warning'] = $this->data['error_permission'] . '  Check permissions for attributico/unit!';
        }

        $this->load->model('localisation/unit');
        $units = $this->model_localisation_unit->getUnits(['language_id' => $language_id]);

        $options = [['key' => '0', 'value' => '0', 'title' => $title0]];
        foreach ($units as $unit) {
            $options[] =  ['key' => $unit['unit_id'], 'value' => $unit['unit_id'], 'title' => $unit['title'] . ', ' . $unit['unit']];
        }

        return $options;
    }

    protected function getInterlinkOptions()
    {
        if (!$this->user->hasPermission('modify', 'attributico/interlink')) {
            $this->data['error_warning'] = $this->data['error_permission'] . '  Check permissions for attributico/interlink!';
        }

        $this->load->model('attributico/interlink');
        $rules = $this->model_attributico_interlink->getRules();

        $options = [];
        foreach ($rules as $rule) {
            $options[] =  ['key' => $rule['rule_id'], 'value' => $rule['rule_id'], 'title' => $rule['name']];
        }

        return $options;
    }

    public function getAttributeValueForm()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        $attribute_id = $this->issetRequest('post', 'attribute_id', 0);
        $product_id = $this->issetRequest('post', 'product_id', 0);
        $attribute_row = $this->issetRequest('post', 'attribute_row', 0);
        $size =  $this->issetRequest('post', 'size', $this::DEFAULT_THUMBNAIL_SIZE);
        $text = $this->issetRequest('post', 'text', '');
        $view_mode = $this->issetRequest('post', 'view_mode', 'template');
        $filter_values = $this->issetRequest('post', 'filter_values', 'all');
        $categories = $this->issetRequest('post', 'categories', array());
        $form = [];

        $this->load->model($this->modelfile);

        if ($attribute_id && $product_id) {

            $info = $this->{$this->model}->getAttributeValueInfo($product_id, $attribute_id, $language_id);

            $info['thumb'] = $this->getThumbnail($info['image'], $size);

            /**
             * Making select options for form select tags
             */
            $language = $this->getLanguage($language_id);

            $unit_options = $this->getUnitOptions($language_id, $language->get('not_selected'));
            $units = $this->makeOptionList($unit_options, $info['unit_id']);

            $status_options = [
                ['key' => '0', 'value' => '0', 'title' => $language->get('status_off')],
                ['key' => '1', 'value' => '1', 'title' => $language->get('status_on')]
            ];
            $statuses = $this->makeOptionList($status_options, $info['status']);

            /**
             * Values for all languages
             */
            $values = $this->fetchValueList($attribute_id, $filter_values, $categories);
            if (!isset($values[$language_id])) {
                $values[$language_id][] = array('text' => '');
            }

            $select = $this->makeValuesSelect($values[$language_id], $view_mode, $attribute_id, $language_id, $attribute_row);

            $modal =
                "<div class='modal-dialog' role='document'>
                <div class='panel panel-default'>
                    <div class='panel-heading'>
                        <button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                        <h3 class='panel-title'><i class='fa fa-pencil'></i>" . $language->get('form_title') . "</h3>
                    </div>
                    <form id='_valueForm' method='post' action='javascript:void(null)' onSubmit='return valueSubmit(this)'>
                        <div class='panel-body'>
                            <div class='form-group'>
                                <label class='col-sm-2 control-label' for='value_text'>{$language->get('entry_attribute_value')}</label>
                                <div class='col-sm-10'>
                                    " . $select . "
                                    <textarea class='form-control' id='value_text' name='text'>{$text}</textarea>
                                </div>
                            </div>
                            <div></div>
                            <div class='row' style='margin-left: 15px; margin-right: 15px;'>
                                <div class='col-sm-5 col-md-5 col-xs-12'>
                                    <div>
                                        <div class='form-group text-center'>
                                            <label class='control-label' for='image'>{$language->get('label_image')}</label>
                                            <div><a href='' id='thumb-image' data-toggle='image' class='img-thumbnail'><img src='{$info['thumb']}' alt='' title=''></a><input type='hidden' name='image' id='image' value='{$info['image']}'></div>
                                        </div>
                                    </div>                         
                                </div>
                                <div class='col-sm-7 col-md-7 col-xs-12'>
                                    <div class='form-group'>
                                        <label class='control-label' for='tooltip'>{$language->get('label_tooltip')}<span data-toggle='tooltip' title='{$language->get('help_tooltip')}'></span></label>
                                        <textarea class='form-control' rows='5' name='tooltip' id='tooltip' placeholder='{$language->get('placeholder_tooltip')}'>{$info['tooltip']}</textarea>
                                    </div>
                                </div>
                            </div>                            
                            <div>
                                <div class='form-group'>
                                    <label class='col-sm-2 control-label' for='icon'>{$language->get('label_icon')}<span data-toggle='tooltip' title='{$language->get('help_icon')}'></span></label>
                                    <div class='col-sm-10'>
                                        <div class='input-group'><span class='input-group-addon'><i class='{$info['icon']}'></i></span>
                                              <input type='text' class='form-control' name='icon' id='icon' placeholder='{$language->get('placeholder_icon')}' value='{$info['icon']}'>
                                        </div>
                                    </div>
                                </div>                    
                            </div>
                            <div>
                                <div class='form-group'>
                                    <label class='control-label col-sm-2' for='url'>{$language->get('label_url')}<span data-toggle='tooltip' title='{$language->get('help_url')}'></span></label>
                                    <div class='col-sm-10'>
                                        <div class='input-group'>
                                            <textarea class='form-control' rows='3' name='url' id='url'>{$info['url']}</textarea>
                                            <span class='input-group-addon'><button type='button' onclick='return createLink()' data-toggle='tooltip' title='{$language->get('placeholder_url')}' class='btn btn-primary btn-xs'><i class='fa fa-play'></i></button></span>
                                        </div>
                                    </div>
                                </div>                    
                            </div>
                            <div>
                                <div class='form-group'>
                                    <label class='col-sm-2 control-label' for='unit_id'>{$language->get('label_unit')}<span data-toggle='tooltip' title='{$language->get('help_unit')}'></span></label>
                                    <div class='col-sm-10'><select class='form-control' name='unit_id'>{$units}</select></div>
                                </div>                    
                            </div>  
                            <div>
                                <div class='form-group'>
                                    <label class='col-sm-2 control-label' for='status'>{$language->get('label_status')}<span data-toggle='tooltip' title='{$language->get('help_status')}'></span></label>
                                    <div class='col-sm-10'><select class='form-control' name='status'>{$statuses}</select></div>
                                </div>                         
                            </div>
                        </div>
                        <div class='modal-footer'>
                            <label class='checkbox-inline control-label'>
                                <input type='checkbox' name='apply_for_all' checked value='true'></input>
                                <span data-toggle='tooltip' data-container='body' title='{$language->get('help_apply_for')}'>&nbsp;{$language->get('label_apply_for')}</span>&nbsp;&nbsp;&nbsp;  
                            </label>
                            <button type='button' class='btn btn-default' data-dismiss='modal' onclick='modalRemove()'>Close</button>
                            <button type='submit' class='btn btn-primary'>Save changes</button>
                        </div>
                        <input type='hidden' name='language_id' value='{$language_id}'>
                        <input type='hidden' name='product_id' value='{$product_id}'>
                        <input type='hidden' name='attribute_id' value='{$attribute_id}'>
                        <input type='hidden' name='attribute_row' value='{$attribute_row}'>
                        <input type='hidden' name='attribute_name' value='{$info['name']}'>
                    </form>
                </div>
            </div>";

            $form['modal'] = $modal;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($form, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Creates interlink for attribute value.
     * Uses profile config for specific filter
     *
     * @param array $value
     * @return string
     */
    public function createLink()
    {
        $attribute_id = isset($this->request->post['attribute_id']) ? $this->request->post['attribute_id'] : 0;
        $product_id = isset($this->request->post['product_id']) ? $this->request->post['product_id'] : 0;
        $text = isset($this->request->post['text']) ? $this->request->post['text'] : '';
        $categories = isset($this->request->post['categories']) ? $this->request->post['categories'] : array();
        $main_category = isset($this->request->post['main_category_id']) ? $this->request->post['main_category_id'] : 0;
        /* $poduct_filters = isset($this->request->post['filters']) ? $this->request->post['filters'] : array(); */
        $name = isset($this->request->post['name']) ? $this->request->post['name'] : '';
        $link = '';

        $this->load->model($this->modelfile);

        if (isset($this->request->post['filters'])) {
            $poduct_filters = $this->request->post['filters'];
        } else {
            $poduct_filters = [];
            $filters = $this->{$this->model}->getProductFilters($product_id, $this->config->get('config_language_id'));
            if ($filters) {
                foreach ($filters as $filter) {
                    $poduct_filters[] = array(
                        'filter_id' => $filter['filter_id'],
                        'name'      => $filter['group'] . ' &gt; ' . $filter['name']
                    );
                }
            }
        }

        /**
         * Get rule profile from DB
         * Profile structure :
         * rule_id
         * route - default as well product/category
         * filter_alias - filter alias (mfp or ocf or filter by default) 
         * args - string of variables parameters for parse, fe. {path} 
         * block_separator 
         * between_separator - attribute_value_separator
         * value_separator
         * advance - reserved       
         */
        $this->load->model('attributico/interlink');

        $profile = $this->model_attributico_interlink->getRule($this->config->get($this->module . '_interlink'));
//TODO проверка на пустые значения иначе вылетает ошибка, например при пустых категориях
        if ($profile) {
            /**
             * Dependency injection container for Interlink class
             * To set a custom replacement method , you need to inherit from the class and overload the replace() method
             * For example :
             * class ReplaceFil extends ReplaceFilter{
             *      public function replace() {
             *          ...
             *          return string $result;
             *      }
             * }
             */
            $di = [
                'product_id' => new ReplaceProductID($product_id, $profile),
                'attribute_id' => new ReplaceAttributeID($attribute_id, $profile),
                'main_category' => new ReplaceMainCategory($main_category, $profile),
                'categories' => new ReplaceCategories($categories, $profile),
                'filters' => new ReplaceFilters($poduct_filters, $profile),
                'name' => new ReplaceName($name, $profile),
                'value' => new ReplaceValue($text, $profile, $this->config->get($this->module . '_splitter')),
                'path' => new ReplacePath($this->{$this->model}->getPath($categories), $profile, $main_category),
                'alias' => new ReplaceAlias($profile['filter_alias']),
            ];

            /**
             * Another way to set any options
             * $di['path']->setMainCategory($main_category);
             * $di['value']->setSplitter($this->config->get($this->module . '_splitter'));
             */

            $interlink = new Interlink($di, $profile);
            $interlink->setUrl(new Url(HTTP_SERVER, $this->config->get('config_secure') ? HTTPS_SERVER : HTTP_SERVER));
            $link = $interlink->create();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($link, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Update all value structure after value form submit
     *
     * @return void
     */
    public function setAttributeValueInfo()
    {
        $form_values = $this->request->post;
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        /* $this->issetRequest('post', 'attribute_row', 0); */
        $attribute_row = $this->issetRequest('post', 'attribute_row', 0);
        $attribute_id = $this->issetRequest('post', 'attribute_id', 0);
        $product_id = $this->issetRequest('post', 'product_id', 0);
        // Transform text (value of attribute)
        $text = isset($this->request->post['text']) ? htmlspecialchars_decode($this->request->post['text']) : '';
        $form_values['text'] = $text;
        // Transform Image
        $form_values['image'] = $this->issetRequest('post', 'image', '');		

        $request =  new \stdClass();
        $request->table = []; 
        $request->accepted = ['acceptedText' => $text, 'language_id' => $language_id, 'attribute_row' => $attribute_row];

        $this->load->model($this->modelfile);

        if ($this->session->data['free']) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($request));
            return;
        }

        if ($attribute_id && $product_id) {            
            $this->{$this->model}->editValueInfo($product_id, $attribute_id, $language_id, $form_values);
            // If checkbox apply_for_all is checked
            if (isset($form_values['apply_for_all']) && $form_values['apply_for_all'] === 'true') {
                $this->{$this->model}->updateValueAllLanguages($product_id, $attribute_id, $form_values);
            }
        }  

        $request->table = $this->{$this->model}->getProductAttributeInfo($product_id, $attribute_id); 

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($request));
    }

    public function getAttributeForm()
    {
        $language_id = $this->issetRequest('get', 'language_id', $this->config->get('config_language_id'));
        $attribute_id = $this->issetRequest('get', 'attribute_id', 0);
        $size = $this->issetRequest('get', 'size', $this::DEFAULT_THUMBNAIL_SIZE);
        $form = [];

        $this->load->model($this->modelfile);

        if ($attribute_id) {
            $info = $this->{$this->model}->getAttributeInfo($attribute_id, $language_id);

            $info['thumb'] = $this->getThumbnail($info['image'], $size);

            $form = $this->configAttributeForm($info, $language_id);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($form, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Config attribute form for React modal
     * * The element must contain the "name" key to create a unique component key.
     *
     * @param array $info
     * @param integer $language_id
     * @return array of form elemens
     */
    private function configAttributeForm($info, $language_id)
    {
        $language = $this->getLanguage($language_id);

        $options = $this->getUnitOptions($language_id, $language->get('not_selected'));

        $config = [
            'title' => $language->get('form_title'),
            'idForm' => '_attributeform',
            'elements' => [
                [
                    'type' => 'text',
                    'name' => 'attribute',
                    'label' => $language->get('entry_attribute'),
                    'value' => $info['name'],
                    'validationProps' => [
                        'required' => $language->get('error_required')
                    ]
                ],
                [
                    'type' => 'autocomplete',
                    'name' => 'duty',
                    'label' => $language->get('entry_duty'),
                    'value' => $info['duty'],
                    'tooltip' => $language->get('help_duty'),
                    'placeholder' => $language->get('placeholder_duty')
                ],
                [
                    'type' => 'editor',
                    'name' => 'tooltip',
                    'label' => $language->get('label_tooltip'),
                    'value' => html_entity_decode($info['tooltip']),
                    'defaultValue' => '',
                    'tooltip' => $language->get('help_tooltip'),
                    'placeholder' => $language->get('placeholder_tooltip'),
                    'data' => ['language' => substr($this->getLanguageDirectory($language_id), 0, 2)]
                ],
                [
                    'rowname' => 'images',
                    'cols' => [
                        [
                            'width' => '5',
                            'type' => 'image',
                            'name' => 'image',
                            'label' => $language->get('label_image'),
                            'value' => $info['image'],
                            'thumb' => $info['thumb'],
                            'placeholder' => $this->getThumbnail('no_image.png', $this::DEFAULT_THUMBNAIL_SIZE),
                            'validationProps' => []
                        ],
                        [
                            'width' => '7',
                            'type' => 'icon',
                            'inline' => false,
                            'name' => 'icon',
                            'label' => $language->get('label_icon'),
                            'value' => $info['icon'],
                            'tooltip' => $language->get('help_icon'),
                            'placeholder' => $language->get('placeholder_icon'),
                            'validationProps' => []
                        ],
                    ]
                ],
                [
                    'type' => 'select',
                    'name' => 'unit_id',
                    'label' => $language->get('label_unit'),
                    'value' => $info['unit_id'],
                    'options' => $options,
                    'tooltip' =>  $language->get('help_unit')
                ],
                [
                    'type' => 'select',
                    'name' => 'status',
                    'label' => $language->get('label_status'),
                    'value' => $info['status'],
                    'options' => [
                        ['key' => 'on', 'value' => '1', 'title' => $language->get('status_on')],
                        ['key' => 'off', 'value' => '0', 'title' => $language->get('status_off')],
                    ],
                    'tooltip' => $language->get('help_status')
                ]
            ]
        ];

        return $config;
    }

    public function updateAttributeInfo()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        $key = isset($this->request->post['key']) ? explode("_", $this->request->post['key']) : array('0', '0');
        //$oldname = $this->issetRequest('post', 'oldname', '');
        $form_values = $this->issetRequest('post', 'values', array());
        $name = htmlspecialchars_decode($form_values['attribute']);

        $this->load->model($this->modelfile);

        if ($this->session->data['free']) {
            $acceptedTitle["acceptedTitle"] = $name;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($acceptedTitle));
            return;
        }

        if ($key[0] == 'attribute') {
            $attribute_id = $key[1];
            $form_values['name'] = $name;
            $this->{$this->model}->updateAttributeInfo($attribute_id, $form_values, $language_id);
        }

        $acceptedTitle["acceptedTitle"] = $name;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($acceptedTitle));
    }

    public function getDutyForm()
    {
        $language_id = $this->issetRequest('get', 'language_id', $this->config->get('config_language_id'));
        $attribute_id = $this->issetRequest('get', 'attribute_id', 0);
        $size = $this->issetRequest('get', 'size', $this::DEFAULT_THUMBNAIL_SIZE);
        $form = [];

        $this->load->model($this->modelfile);

        if ($attribute_id) {

            $info = $this->{$this->model}->getDutyInfo($attribute_id, $language_id);

            $info['thumb'] = $this->getThumbnail($info['duty_image'], $size);

            $form = $this->configDutyForm($info, $language_id);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($form, JSON_UNESCAPED_UNICODE));
    }
    /**
     * Configure duty form
     *
     * @param array $info of duty
     * @param string $language_id
     * @return array $config 
     */
    private function configDutyForm($info, $language_id)
    {
        $language = $this->getLanguage($language_id);

        $options = $this->getUnitOptions($language_id, $language->get('not_selected'));

        $config = [
            'title' => $language->get('form_title'),
            'idForm' => '_dutyform',
            'elements' => [
                [
                    'type' => 'autocomplete',
                    'name' => 'duty',
                    'label' => $language->get('entry_duty'),
                    'value' => $info['duty'],
                    'tooltip' => $language->get('help_duty'),
                    'placeholder' => $language->get('placeholder_duty')
                ],
                [
                    'type' => 'editor',
                    'name' => 'duty_tooltip',
                    'label' => $language->get('label_tooltip'),
                    'value' => html_entity_decode($info['duty_tooltip']),
                    'tooltip' => $language->get('help_tooltip'),
                    'placeholder' => $language->get('placeholder_tooltip'),
                    'data' => ['language' => substr($this->getLanguageDirectory($language_id), 0, 2)]
                ],
                [
                    'rowname' => 'images',
                    'cols' => [
                        [
                            'width' => '5',
                            'type' => 'image',
                            'name' => 'duty_image',
                            'label' => $language->get('label_image'),
                            'value' => $info['duty_image'],
                            'thumb' => $info['thumb'],
                            'placeholder' => $this->getThumbnail('no_image.png', $this::DEFAULT_THUMBNAIL_SIZE),
                            'validationProps' => []
                        ],
                        [
                            'width' => '7',
                            'type' => 'icon',
                            'inline' => false,
                            'name' => 'duty_icon',
                            'label' => $language->get('label_icon'),
                            'value' => $info['duty_icon'],
                            'tooltip' => $language->get('help_icon'),
                            'placeholder' => $language->get('placeholder_icon'),
                            'validationProps' => []
                        ],
                    ]
                ],
                [
                    'type' => 'select',
                    'name' => 'duty_unit_id',
                    'label' => $language->get('label_unit'),
                    'value' => $info['duty_unit_id'],
                    'options' => $options,
                    'tooltip' =>  $language->get('help_unit')
                ],
                [
                    'type' => 'select',
                    'name' => 'duty_status',
                    'label' => $language->get('label_status'),
                    'value' => $info['duty_status'],
                    'options' => [
                        ['key' => 'on', 'value' => '1', 'title' => $language->get('status_on')],
                        ['key' => 'off', 'value' => '0', 'title' => $language->get('status_off')],
                    ],
                    'tooltip' => $language->get('help_status')
                ]
            ],
            'footerCheckBox' => [
                'name' => 'apply_for_all',
                'label' => $language->get('label_apply_for'),
                'tooltip' => $language->get('help_apply_for')
            ]
        ];

        return $config;
    }

    /**
     * Save duty form values after Submit
     *
     * @return json
     */
    public function updateDutyInfo()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        $key = isset($this->request->post['key']) ? explode("_", $this->request->post['key']) : array('0', '0');
        //$oldname = $this->issetRequest('post', 'oldname', '');
        $form_values = $this->issetRequest('post', 'values', array());
        $duty =  htmlspecialchars_decode($form_values['duty']);

        $this->load->model($this->modelfile);

        if ($this->session->data['free']) {
            $acceptedTitle["acceptedTitle"] = $duty;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($acceptedTitle));
            return;
        }

        if ($key[0] == 'duty') {
            $attribute_id = $key[1];
            $form_values['duty'] = $duty;

            $this->{$this->model}->updateDutyInfo($attribute_id, $form_values, $language_id);

            // If checkbox apply_for_all is checked
            if (isset($form_values['apply_for_all']) && $form_values['apply_for_all'] === 'true') {
                $this->{$this->model}->updateDutyAllLanguages($attribute_id, $form_values);
            }
        }

        $acceptedTitle["acceptedTitle"] = $duty;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($acceptedTitle));
    }

    /**
     * Update Duty after delete or clone action of CRUD
     * Overload base method
     *
     * @return void
     */
    public function updateDuty()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        $key = isset($this->request->post['key']) ? explode("_", $this->request->post['key']) : array('0', '0');
        $clone = $this->issetRequest('post', 'clone', false);

        $this->load->model($this->modelfile);
        $languages = $this->getLanguages();

        if ($key[0] == 'duty') {
            $attribute_id = $key[1];
            if ($clone) {
                $duty_info = $this->{$this->model}->getDutyInfo($attribute_id, $language_id);
                foreach ($languages as $language) {
                    $this->{$this->model}->updateDutyInfo($attribute_id, $duty_info, $language['language_id']);
                }
            } else {
                // It is the delete action  
                //TODO duty_image => null            
                $duty_info = ['duty' => '', 'duty_tooltip' => '', 'duty_image' => '', 'duty_icon' => '', 'duty_unit' => '0', 'duty_status' => '0'];
                $this->{$this->model}->updateDutyInfo($attribute_id, $duty_info, $language_id);
            }
        }
    }

    /**
     * React Product Attributes  management
     */

    /**
     * Get product attributes for ProductAttributesForm     * 
     *
     * @return json
     */
    public function getProductAttributeInfo()
    {
        $product_id = $this->issetRequest('get', 'product_id', 0);
        $this->load->model($this->modelfile);

        $info =  new \stdClass();

        $info->data = $this->{$this->model}->getProductAttributeInfo($product_id);

        $splitter = !($this->config->get($this->module . '_splitter') == '') ? $this->config->get($this->module . '_splitter') : '/';
        $autoadd = $this->config->get($this->module . '_autoadd') ? $this->config->get($this->module . '_autoadd') : 0;

        $info->settings = [
            'splitter' => quotemeta($splitter), 'attribute_autoinjection' => $autoadd === '1',
            'value_insert_mode' => $this->config->get($this->module . '_product_text'), 'language_id' => (int)$this->config->get('config_language_id')
        ];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Returns the differense between attributes of unbinded product category 
     * and remaining binded categories
     * 
     * @return array 
     */
    public function deleteAttributesFromProduct()
    {
        $product_id = $this->issetRequest('post', 'product_id', 0);
        $attributes = $this->issetRequest('post', 'attributes', []);


        $this->load->model($this->modelfile);
        $this->cache->delete($this->module);

        $count_affected = $this->{$this->model}->deleteAttributesFromProducts([['product_id' => $product_id]], $attributes);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($count_affected));
    }

    public function getProductCategoryInfo()
    {
        $product_id = $this->issetRequest('get', 'product_id', 0);
        $this->load->model($this->modelfile);

        $info =  new \stdClass();

        $info->data = $this->{$this->model}->getProductCategories($product_id, $this->config->get('config_language_id'));

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }

    public function deleteProductCategory()
    {
        $product_id = $this->issetRequest('get', 'product_id', 0);
        $category_id = $this->issetRequest('get', 'category_id', 0);

        $this->load->model($this->modelfile);
        $this->cache->delete($this->module);
        $count_affected = $this->{$this->model}->deleteProductCategory($product_id, $category_id);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($count_affected, JSON_UNESCAPED_UNICODE));
    }

    public function updateProductCategory()
    {
        $product_id = $this->issetRequest('get', 'product_id', 0);
        $category_id = $this->issetRequest('get', 'category_id', 0);
        $main = $this->issetRequest('get', 'main', 0);
        $autoadd = $this->config->get($this->module . '_autoadd') ? $this->config->get($this->module . '_autoadd') : 0;
        $insert_mode = $this->issetRequest('get', 'value_insert_mode', '');

        $this->load->model($this->modelfile);
        $languages = $this->getLanguages();

        $this->cache->delete($this->module);
        $count_affected = $this->{$this->model}->updateProductCategory($product_id, $category_id, $main);

        if ($autoadd) {
            $category_attributes = $this->{$this->model}->getCategoriesAttributesId([$category_id]);
            $this->{$this->model}->addAttributesToProducts([['product_id' => $product_id]], $category_attributes, $languages, $insert_mode);
            $info = $this->{$this->model}->getProductAttributeInfo($product_id);
        } else $info = [];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }

    public function changeMainCategory()
    {
        $product_id = $this->issetRequest('get', 'product_id', 0);
        $category_id = $this->issetRequest('get', 'category_id', 0);
        $main_category_id =  $this->issetRequest('get', 'main_category_id', 0);

        $this->load->model($this->modelfile);

        $this->cache->delete($this->module);
        $count_affected = $this->{$this->model}->updateProductCategory($product_id, $category_id, 0);
        $count_affected = $this->{$this->model}->updateProductCategory($product_id,  $main_category_id, 1);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($count_affected, JSON_UNESCAPED_UNICODE));
    }

    public function addCategoryAttributesToProducts()
    {
        $product_id = $this->issetRequest('post', 'product_id', 0);
        $categories = $this->issetRequest('post', 'categories', []);
        $insert_mode = $this->issetRequest('post', 'value_insert_mode', '');

        $this->load->model($this->modelfile);

        $languages = $this->getLanguages();

        if ($categories) {
            $category_attributes = $this->{$this->model}->getCategoriesAttributesId($categories);
            $this->cache->delete($this->module);
            $this->{$this->model}->addAttributesToProducts([['product_id' => $product_id]], $category_attributes, $languages, $insert_mode);
            $info = $this->{$this->model}->getProductAttributeInfo($product_id);
        } else $info = [];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }

    public function addAttributeToProduct()
    {
        $product_id = $this->issetRequest('post', 'product_id', 0);
        $attribute_id = $this->issetRequest('post', 'attribute_id', 0);
        $insert_mode = $this->issetRequest('post', 'value_insert_mode', '');

        $this->load->model($this->modelfile);

        $languages = $this->getLanguages();

        if ($attribute_id) {
            $this->cache->delete($this->module);
            $this->{$this->model}->addAttributesToProducts([['product_id' => $product_id]], [$attribute_id], $languages, $insert_mode);
            $info = $this->{$this->model}->getProductAttributeInfo($product_id, $attribute_id);
        } else $info = [];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }

    public function updateProductAttribute()
    {
        $product_id = $this->issetRequest('post', 'product_id', 0);
        $attribute_id = $this->issetRequest('post', 'attribute_id', 0);
        $insert_mode = $this->issetRequest('post', 'value_insert_mode', '');
        $current_id = $this->issetRequest('post', 'current_id', 0);

        $this->load->model($this->modelfile);

        $languages = $this->getLanguages();

        if ($attribute_id) {
            $this->cache->delete($this->module);
            $this->{$this->model}->deleteAttributesFromProducts([['product_id' => $product_id]], [$current_id]);
            $this->{$this->model}->addAttributesToProducts([['product_id' => $product_id]], [$attribute_id], $languages, $insert_mode);
            $info = $this->{$this->model}->getProductAttributeInfo($product_id, $attribute_id);
        } else $info = [];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }
    /**
     * Get all attributes for autocomplete
     *
     * @return void
     */
    public function getAttributes()
    {
        $this->load->model($this->modelfile);

        $filter_data = array(
            'language_id' => $this->config->get('config_language_id')
        );
        $results = $this->{$this->model}->getAttributes($filter_data);

        $json = array();
        foreach ($results as $result) {
            $json[] = array(
                'attribute_id' => (int)$result['attribute_id'],
                'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                'attribute_group' => strip_tags(html_entity_decode($result['group_name'], ENT_QUOTES, 'UTF-8')),
            );
        }

        $sort_order = array();
        foreach ($json as $key => $value) {
            $sort_order[$key] = $value['name'];
        }
        array_multisort($sort_order, SORT_ASC, $json);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    /**
     * Get all data for ValueForm (Edit value of selected language)
     *
     * @return void
     */
    public function getValueInfo()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));
        $attribute_id = $this->issetRequest('post', 'attribute_id', 0);
        $product_id = $this->issetRequest('post', 'product_id', 0);
        /* $attribute_row = $this->issetRequest('post', 'attribute_row', 0); */
        $size =  $this->issetRequest('post', 'size', $this::DEFAULT_THUMBNAIL_SIZE);
        /* $text = $this->issetRequest('post', 'text', ''); */
        $view_mode = $this->issetRequest('post', 'view_mode', 'template');
        $filter_values = $this->issetRequest('post', 'filter', 'all');
        $categories = $this->issetRequest('post', 'categories', array());
        $splitter = $this->configGet($this->module . '_splitter', '/', true);

        $this->load->model($this->modelfile);

        if ($attribute_id && $product_id) {

            $info = $this->{$this->model}->getAttributeValueInfo($product_id, $attribute_id, $language_id);

            $info['thumb'] = $this->getThumbnail($info['image'], $size);
            $info['placeholder'] = $this->getThumbnail('no_image.png', $size);            

            /**
             * Values for all languages
             */
            $values = $this->fetchValueList($attribute_id, $filter_values, $categories);
            if (!isset($values[$language_id])) {
                $values[$language_id][] = array('text' => '');
            }

            /**
             * Making list of existing values
             */
            if ($view_mode == 'template') {
                $info['list'] = $values[$language_id];
            } else {
                $splited = splitTemplate($values[$language_id], $splitter);
                foreach ($splited as $split) {
                    $info['list'][] = ["text" => $split];
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($info, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get all data for ValueForm (Edit value of selected language)
     *
     * @return void
     */
    public function getUnits()
    {
        $language_id = $this->issetRequest('post', 'language_id', $this->config->get('config_language_id'));

        $this->load->model($this->modelfile);

        $language = $this->getLanguage($language_id);

        $unit_options = $this->getUnitOptions($language_id, $language->get('not_selected'));

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(typeChecking($unit_options), JSON_UNESCAPED_UNICODE));
    }

    public function install()
    {
        // Extension of the existing DB structure
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "attribute_pro (
            attribute_id int(11) NOT NULL auto_increment,
            attribute_group_id int(11) NOT NULL,
            sort_order int(3) NOT NULL,
            image varchar(255) DEFAULT NULL,
            icon varchar(255) NOT NULL,
            unit_id int(11) NOT NULL,
            status tinyint(1) NOT NULL DEFAULT 1,
            url text NOT NULL,            
            PRIMARY KEY (attribute_id)
          )
          CHARACTER SET utf8, COLLATE utf8_general_ci");

        $this->db->query("INSERT INTO " . DB_PREFIX . "attribute_pro (attribute_id, attribute_group_id, sort_order) 
        SELECT a.attribute_id, a.attribute_group_id, a.sort_order FROM " . DB_PREFIX . "attribute  a
        ON DUPLICATE KEY UPDATE attribute_id=a.attribute_id, attribute_group_id=a.attribute_group_id, sort_order=a.sort_order");

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "attribute_description_pro (
            attribute_id int(11) NOT NULL,
            language_id int(11) NOT NULL,
            name varchar(64) NOT NULL,
            tooltip text NOT NULL,
            duty text NOT NULL,
            duty_tooltip text NOT NULL,
            duty_image varchar(255) DEFAULT NULL,
            duty_icon varchar(255) NOT NULL,
            duty_unit_id int(11) NOT NULL,
            duty_status tinyint(1) NOT NULL DEFAULT 1,            
            PRIMARY KEY (attribute_id, language_id)
          )
          CHARACTER SET utf8, COLLATE utf8_general_ci");

        $query = $this->columnCheck('attribute_description', 'duty') ? "INSERT INTO " . DB_PREFIX . "attribute_description_pro (attribute_id, language_id, name, duty) 
          SELECT ad.attribute_id, ad.language_id, ad.name, ad.duty FROM " . DB_PREFIX . "attribute_description ad
          ON DUPLICATE KEY UPDATE attribute_id=ad.attribute_id, language_id=ad.language_id, name=ad.name, duty=ad.duty" : "INSERT INTO " . DB_PREFIX . "attribute_description_pro (attribute_id, language_id, name) 
          SELECT ad.attribute_id, ad.language_id, ad.name FROM " . DB_PREFIX . "attribute_description ad
          ON DUPLICATE KEY UPDATE attribute_id=ad.attribute_id, language_id=ad.language_id, name=ad.name";

        $this->db->query($query);

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "product_attribute_pro (
            product_id int(11) NOT NULL,
            attribute_id int(11) NOT NULL,
            language_id int(11) NOT NULL,
            text text NOT NULL,
            tooltip text NOT NULL,
            image varchar(255) DEFAULT NULL,
            icon varchar(255) NOT NULL,
            unit_id int(11) NOT NULL,
            status tinyint(1) NOT NULL DEFAULT 1,
            url text NOT NULL,
            PRIMARY KEY (product_id, attribute_id, language_id)
          )
           CHARACTER SET utf8, COLLATE utf8_general_ci");

        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute_pro (product_id, attribute_id, language_id, text) 
            SELECT pa.product_id, pa.attribute_id, pa.language_id, pa.text FROM " . DB_PREFIX . "product_attribute pa
            ON DUPLICATE KEY UPDATE product_id=pa.product_id, attribute_id=pa.attribute_id, language_id=pa.language_id, text=pa.text");

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "category_attribute
		(`category_id` INTEGER(11) NOT NULL,`attribute_id` INTEGER(11) NOT NULL, PRIMARY KEY (`category_id`,`attribute_id`) USING BTREE)
        CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "unit
		(unit_id int(11) NOT NULL auto_increment, unit_group_id int(11) NOT NULL, sort_order int(3) DEFAULT 0, PRIMARY KEY (unit_id)) CHARACTER SET utf8, COLLATE utf8_general_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "unit_description
		(unit_id int(11) NOT NULL, language_id int(11) NOT NULL, title varchar(255) NOT NULL DEFAULT '', unit varchar(32) NOT NULL DEFAULT '',
        PRIMARY KEY (unit_id, language_id))  CHARACTER SET utf8, COLLATE utf8_general_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "attribute_interlink (rule_id int(11) NOT NULL auto_increment, name varchar(255) NOT NULL, route varchar(255) NOT NULL, filter_alias varchar(255) NOT NULL, args varchar(255) NOT NULL, block_separator char(20) NOT NULL, between_separator char(20) NOT NULL, value_separator char(20) NOT NULL, advance varchar(255) NOT NULL, PRIMARY KEY (rule_id)) CHARACTER SET utf8, COLLATE utf8_general_ci;");

        foreach ($this->dbstructure as $checking_table => $checking_column) {
            foreach ($checking_column as $column_name => $column_type)
                if (!$this->columnCheck($checking_table, $column_name)) {
                    $this->columnUpgrade($checking_table,  $column_name, $column_type);
                }
        }

        $data[$this->module . '_splitter'] = '/';
        $data[$this->module . '_sortorder'] = '1';
        $data[$this->module . '_smart_scroll'] = '1';
        $data[$this->module . '_cache'] = '1';
        $data[$this->module . '_lazyload'] = '1';
        $data[$this->module . '_children'] = 'a:5:{i:1;a:3:{i:0;s:4:"duty";i:1;s:8:"template";i:2;s:5:"value";}i:2;a:1:{i:0;s:4:"duty";}i:3;a:1:{i:0;s:4:"duty";}i:4;a:2:{i:0;s:8:"template";i:1;s:5:"value";}i:5;a:3:{i:0;s:4:"duty";i:1;s:8:"template";i:2;s:5:"value";}}';
        $data['module_' . $this->module . '_status'] = '1';

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting($this->module, $data);
        $this->model_setting_setting->editSetting('module_' . $this->module, $data);
    }
}
class ControllerExtensionModuleAttributipro extends ControllerModuleAttributipro
{
}
