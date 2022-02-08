<?php

class ModelCatalogAttributico extends Model
{
    protected $model = 'attributico';

    /** Attribute Data Base service section **/
    /**
     * Universal function for gets attributes depending from incomming data
     *
     * @param array $data
     * @return array of attributes
     */
    public function getAttributes($data = array())
    {
        $language_id = isset($data['language_id']) ? (int)$data['language_id'] : (int)$this->config->get('config_language_id');

        $sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . $language_id . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . $language_id . "'";

        if (!empty($data['filter_name'])) {
            $sql .= " AND ad.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (!empty($data['filter_attribute_group_id'])) {
            $sql .= " AND a.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
        }

        $sort_data = array(
            'ad.name',
            'attribute_group',
            'a.sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY attribute_group, ad.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * Returns an attribute description for displaying additional information in the product tree
     *
     * @param integer $attribute_id
     * @return array
     */
    public function getAttributeDescriptions($attribute_id)
    {
        $attribute_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");

        foreach ($query->rows as $result) {
            $attribute_data[$result['language_id']] = array('name' => $result['name'], 'duty' => $result['duty']);
        }

        return $attribute_data;
    }

    /**
     * Addition New attribute or paste copying attribute
     * $data['new] is manage flag
     *
     * @param array $data
     * $data['attribute_description'] structure example [empty,'name'=>A1ru,empty,'name'=>A1en]
     * empty if language not present by any id   [1] name = A1ru
     *                                           [3] name = A1en
     * 
     * @return int new attribute id 
     * 
     * Executed in a foreach loop, cache delete in controller
     */
    public function addAttribute($data)
    {
        $maxorder = $this->db->query("SELECT MAX(`sort_order`) AS maxorder FROM " . DB_PREFIX . "attribute");
        $this->db->query("INSERT INTO " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$data['attribute_group_id'] . "', sort_order = '" . ((int)$maxorder->row['maxorder'] + 1) . "'");
        /**
         * This id will be added to the name when creating: New attribute_234, when copying is not added - the flag data['new']
         */
        $attribute_id = $this->db->getLastId();

        foreach ($data['attribute_description'] as $language_id => $value) {
            $sql = "INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id . "',
             name = '" . $this->db->escape($value['name']) . ($data['new'] ? '_' . $attribute_id : '') . "'";
            /**
             * We transfer the duty value from the previous attribute if copy paste
             */
            if (!$data['new']) {
                $duty = $this->whoIsOnDuty($data['attribute_id'], $language_id);
                $sql .= ",duty = '" . $this->db->escape($duty) . "'";
            }

            $this->db->query($sql);
        }

        return $attribute_id;
    }

    /**
     * Updates attribute name after edit by inline fansytree editor
     *
     * @param integer $attribute_id
     * @param array $data
     * @return void
     */
    public function editAttributeName($attribute_id, $data)
    {
        $this->cache->delete($this->model);

        foreach ($data['attribute_description'] as $language_id => $value) {
            $this->db->query("UPDATE " . DB_PREFIX . "attribute_description SET name = '" . $this->db->escape($value['name']) . "' WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "'");
        }
    }

    /**
     * Gets all attribute structure with attribute group and description data
     *
     * @param string $attribute_id
     * @param integer $language_id
     * @return array
     */
    public function getAttributeInfo($attribute_id, $language_id = 0)
    {
        $sql_lang = $language_id ? " AND ad.language_id = '" . (int)$language_id . "'" : '';

        $query = $this->db->query("SELECT ad.language_id, a.attribute_id, ad.name, a.attribute_group_id, oagd.name AS group_name, a.sort_order, ad. duty  FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_group_description oagd ON (a.attribute_group_id = oagd.attribute_group_id AND oagd.language_id = ad.language_id) WHERE a.attribute_id = '" . (int)$attribute_id . "'" . $sql_lang);

        if ($language_id) {
            return $query->row;
        } else {
            return $this->groupByLang($query->rows);
        }
    }

    private function deleteAttribute($attribute_id)
    {
        // in foreach
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE attribute_id = '" . (int)$attribute_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "category_attribute WHERE attribute_id = '" . (int)$attribute_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "attribute WHERE attribute_id = '" . (int)$attribute_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");
    }

    public function deleteAttributes($data)
    {
        $this->cache->delete($this->model);

        if (isset($data['attribute'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE attribute_id IN (" . implode(",", $data['attribute']) . ")");
            $this->db->query("DELETE FROM " . DB_PREFIX . "category_attribute WHERE attribute_id IN (" . implode(",", $data['attribute']) . ")");
            $this->db->query("DELETE FROM " . DB_PREFIX . "attribute WHERE attribute_id IN (" . implode(",", $data['attribute']) . ")");
            $this->db->query("DELETE FROM " . DB_PREFIX . "attribute_description WHERE attribute_id IN (" . implode(",", $data['attribute']) . ")");
        }
    }

    /** Group Data Base service section **/
    /**
     * Universal function for gets attribute groups depending from incomming data
     *
     * @param array $data
     * @return array of groups
     */
    public function getAttributeGroups($data = array())
    {
        $language_id = isset($data['language_id']) ? (int)$data['language_id'] : (int)$this->config->get('config_language_id');

        $sql = "SELECT * FROM " . DB_PREFIX . "attribute_group ag LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE agd.language_id = '" . $language_id . "'";

        $sort_data = array(
            'agd.name',
            'ag.sort_order'
        );

        if (isset($data['attribute_group_id'])) {
            $sql .= " AND ag.attribute_group_id = '" . (int)$data['attribute_group_id'] . "'";
        }

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY agd.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Gets group for concrete attribute
     *
     * @param integer $attribute_id
     * @param integer $language_id
     * @return array
     */
    public function getAttributeGroup($attribute_id, $language_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_group_description agd "
            . "ON (agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$language_id . "') WHERE a.attribute_id = '" . (int)$attribute_id . "'");

        return $query->row;
    }

    public function addAttributeGroup($data)
    {
        // in foreach
        $maxorder = $this->db->query("SELECT MAX(`sort_order`) AS maxorder FROM " . DB_PREFIX . "attribute_group");
        $this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group SET sort_order = '" . ((int)$maxorder->row['maxorder'] + 1) . "'");
        // этот id будет добавлен к имени при добавлении: Новая группа_234
        $attribute_group_id = $this->db->getLastId();

        foreach ($data['attribute_group_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int)$attribute_group_id . "', language_id = '" . (int)$language_id .
                "', name = '" . $this->db->escape($value['name'] . '_' . $attribute_group_id) . "'");
        }

        return $attribute_group_id;
    }

    public function editAttributeGroup($attribute_group_id, $data)
    {
        $this->cache->delete($this->model);

        foreach ($data['attribute_group_description'] as $language_id => $value) {
            $this->db->query("UPDATE " . DB_PREFIX . "attribute_group_description SET name = '" . $this->db->escape($value['name']) . "' WHERE attribute_group_id = '" . (int)$attribute_group_id . "' AND language_id = '" . (int)$language_id . "'");
        }
    }

    public function deleteAttributeGroup($attribute_group_id)
    {
        // in foreach
        $query = $this->db->query("SELECT attribute_id FROM " . DB_PREFIX . "attribute WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

        foreach ($query->rows as $result) {
            $this->deleteAttribute($result['attribute_id']);
        }

        $this->db->query("DELETE FROM " . DB_PREFIX . "attribute_group WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "attribute_group_description WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
    }

    public function deleteAttributeGroups($data)
    {
        $this->cache->delete($this->model);

        if (isset($data['group'])) {
            foreach ($data['group'] as $attribute_group_id) {
                $this->deleteAttributeGroup($attribute_group_id);
            }
        }
    }

    public function replaceAttributeGroup($attribute_id, $attribute_group_id)
    {
        // in foreach
        $this->db->query("UPDATE " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$attribute_group_id . "' WHERE attribute_id = '" . (int)$attribute_id . "'");
    }

    /** Value Data Base service section  **/

    /**
     * Gets languages grouped array of values for all products from category list
     *
     * @param integer $attribute_id
     * @param array $categories
     * @return array of values 
     */
    public function getAttributeValues($attribute_id, $categories = array())
    {
        $attribute_values_data = array();
        // (BINARY text) for difference selecting lower case and upper case text recrords in DISTINCT mode
        $sql = "SELECT DISTINCT (BINARY text), text, language_id FROM " . DB_PREFIX . "product_attribute WHERE attribute_id='" . (int)$attribute_id . "'";
        // If the array of categories is not empty, then the category  filter has been turned on
        $sql_categories = $categories ? " AND product_id IN (SELECT ptc.product_id FROM " . DB_PREFIX . "product_to_category ptc WHERE ptc.category_id IN (" . implode(",", $categories) . "))" : "";

        $query = $this->db->query($sql . $sql_categories . " ORDER BY language_id");
        //	$query = $this->db->query("SELECT DISTINCT(text), language_id FROM " . DB_PREFIX . "product_attribute WHERE attribute_id=" . (int) $attribute_id . " ORDER BY CAST(text AS DECIMAL)");

        foreach ($query->rows as $result) {
            $attribute_values_data[$result['language_id']][] = array('text' => $result['text']);
        }
        return $attribute_values_data;
    }

    public function editAttributeTemplates($attribute_id, $data)
    {
        $products = $this->getProductsByText($attribute_id, $data['language_id'], $data['oldtext']);

        $this->cache->delete($this->model);

        foreach ($products as $product) {
            $this->db->query("UPDATE " . DB_PREFIX . "product_attribute SET text = '" . $this->db->escape($data['newtext']) . "' WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$data['language_id'] . "' AND product_id = '" . (int)$product['product_id'] . "'");
            $this->productDateModified($product['product_id']);
        }
    }

    public function editAttributeValues($attribute_id, $data)
    {
        $splitter = !($this->config->get($this->model . '_splitter') == '') ? $this->config->get($this->model . '_splitter') : '/';
        $replace_mode = $this->config->get($this->model . '_replace_mode') ? $this->config->get($this->model . '_replace_mode') : 'substr';
        $search = htmlspecialchars_decode($data['oldtext']);
        $replace = htmlspecialchars_decode($data['newtext']);

        $products = $this->getProductsByAttributeId($attribute_id, $data['language_id']);

        $this->cache->delete($this->model);

        foreach ($products as $product) {
            if ($replace_mode === 'match') {
                // Замена по точному совпадению значения
                $values = explode($splitter, $product['text']);
                $newtext =  implode($splitter, preg_replace('/^(' . $search . ')+$/', $replace, $values));
            } else {
                // Замена по вхождению подстроки в строку
                $newtext = str_replace($search, $replace, $product['text']);
            }

            $this->db->query("UPDATE " . DB_PREFIX . "product_attribute SET text = '" . $this->db->escape($newtext) . "' WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$data['language_id'] . "' AND product_id = '" . (int)$product['product_id'] . "'");
            $this->productDateModified($product['product_id']);
        }
    }

    public function deleteValues($data, $language_id)
    {
        set_time_limit(600);
        $this->cache->delete($this->model);

        if (isset($data['value'])) {
            foreach ($data['value'] as $instance) {
                if ($instance['value'] != '') {
                    $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute  WHERE INSTR(BINARY text, '" . $instance['value'] . "') != '0'
                 AND attribute_id = '" . (int) $instance['attribute_id'] . "'
                 AND language_id = '" . (int) $language_id . "'");
                    //");
                } else {
                    $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE TRIM(text) LIKE ''
                        AND attribute_id = '" . (int) $instance['attribute_id'] . "'
                        AND language_id = '" . (int) $language_id . "'");
                }
            }
        }

        if (isset($data['template'])) {
            foreach ($data['template'] as $instance) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute  WHERE TRIM(text) LIKE BINARY '" . $instance['value'] . "'
                 AND attribute_id = '" . (int) $instance['attribute_id'] . "'
                 AND language_id = '" . (int) $language_id . "'");
                //");
            }
        }
    }

    /**
     * Category and Category Attribute Data Base service
     *
     **/
    public function getAllCategories($non_hierarchical = false)
    {
        // TODO clear cache if checkbox was checked
        if ($this->config->get($this->model . '_multistore')) {
            $multistore = "";
        } else {
            $multistore = " AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ";
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id)
        LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id)
        WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'" . $multistore . "
        ORDER BY c.parent_id, c.sort_order, cd.name");

        if ($non_hierarchical) {

            return $query->rows;
        } else {
            $category_data = array();
            foreach ($query->rows as $row) {
                $category_data[$row['parent_id']][$row['category_id']] = $row;
            }

            return $category_data;
        }
    }

    public function addCategoryAttributes($category_id, $category_attributes)
    {
        // in foreach
        if (isset($category_attributes)) {
            foreach ($category_attributes as $attribute_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_attribute SET category_id = '" . (int)$category_id . "', attribute_id = '" . (int)$attribute_id . "' "
                    . "ON DUPLICATE KEY UPDATE category_id = '" . (int)$category_id . "', attribute_id = '" . (int)$attribute_id . "'");
            }
        }
        return $category_id;
    }

    public function deleteAttributesFromCategory($category_id, $data)
    {
        // in foreach
        if (isset($data['category_attribute'])) {
            foreach ($data['category_attribute'] as $attribute_id) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "category_attribute WHERE category_id = '" . (int)$category_id . "' AND attribute_id = '" . (int)$attribute_id . "'");
            }
        }
    }

    public function getCategoryAttributes($data = array())
    {

        if (isset($data['language_id'])) {
            $language_id = (int)$data['language_id'];
        } else {
            $language_id = (int)$this->config->get('config_language_id');
        }

        $sql = "SELECT ca.category_id, ca.attribute_id, a.attribute_group_id, ad.name AS attribute_description, ad.duty , ag.sort_order AS sort_attribute_group, agd.name AS group_name
          FROM " . DB_PREFIX . "category_attribute ca
          LEFT JOIN " . DB_PREFIX . "attribute a ON (a.attribute_id = ca.attribute_id)
          LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (ad.attribute_id = a.attribute_id AND ad.language_id = '" . (int)$language_id . "')
          LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (ag.attribute_group_id = a.attribute_group_id)
          LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$language_id . "')
          WHERE ca.category_id = '" . (int)$data['category_id'] . "'";

        $sort_data = array(
            'ad.name',
            'sort_attribute_group',
            'a.sort_order',
            'ca.attribute_id',
            'sort_attribute_group, a.sort_order'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY sort_attribute_group, a.sort_order";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getCategoryAttributesId($category_id)
    {
        $data = array();
        $query = $this->db->query("SELECT attribute_id FROM " . DB_PREFIX . "category_attribute WHERE category_id = '" . (int)$category_id . "'");
        foreach ($query->rows as $attribute) {
            $data['category_attribute'][] = $attribute['attribute_id'];
        }
        return $data;
    }

    public function getCategoryDescriptions($category_id)
    {
        $category_description_data = array();

        $query = $this->db->query("SELECT language_id, name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . (int)$category_id . "'");

        foreach ($query->rows as $result) {
            $category_description_data[$result['language_id']] = array(
                'name' => $result['name'],
            );
        }

        return $category_description_data;
    }

    /**
     * Product Data Base service
     *
     **/
    private function getProductsByText($attribute_id, $language_id, $text)
    {
        $product = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_attribute WHERE attribute_id='" . (int)$attribute_id . "' AND language_id='" . (int)$language_id . "' AND text='" . $this->db->escape($text) . "'"); //TODO $this->db->escape in attributico

        return $product->rows;
    }

    private function getProductsByAttributeId($attribute_id, $language_id)
    {
        $product = $this->db->query("SELECT product_id, text FROM " . DB_PREFIX . "product_attribute WHERE attribute_id='" . (int)$attribute_id . "' AND language_id='" . (int)$language_id . "'");

        return $product->rows;
    }

    public function getProductsByAttribute($category_id, $attribute_id, $language_id, $invert = false)
    {
        if (!$invert) {
            $query = $this->db->query("SELECT p.product_id, p.`model`, `pd`.`name` as product_name, p2a.text, `ad`.`name` as attribute_name, p2c.category_id FROM " . DB_PREFIX . "product p
                        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                        LEFT JOIN " . DB_PREFIX . "product_attribute p2a ON (p.product_id = p2a.product_id AND `p2a`.`language_id` = '" . (int)$language_id . "')
                        LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)
                        LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (ad.attribute_id = p2a.`attribute_id` AND `ad`.`language_id` = '" . (int)$language_id . "')
                        WHERE pd.language_id  = '" . (int)$language_id . "' AND p2a.attribute_id = '" . (int)$attribute_id . "' AND p2c.category_id = '" . (int)$category_id . "'
                        ORDER BY pd.name ASC");
        } else {
            $query = $this->db->query("SELECT DISTINCT p.product_id, p.`model`, `pd`.`name` as product_name, p2c.category_id FROM " . DB_PREFIX . "product p
                        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                        LEFT JOIN " . DB_PREFIX . "product_attribute p2a ON (p.product_id = p2a.product_id AND `p2a`.`language_id` = '" . (int)$language_id . "')
                        LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)
                        WHERE pd.language_id  = '" . (int)$language_id . "'
                        AND '" . (int)$attribute_id . "' NOT IN (SELECT  p2a.attribute_id FROM " . DB_PREFIX . "product_attribute p2a WHERE p.product_id = p2a.product_id AND `p2a`.`language_id` = '" . (int)$language_id . "')
                        AND p2c.category_id = '" . (int)$category_id . "'
                        ORDER BY pd.name ASC");
        }
        return $query->rows;
    }

    public function getProductsByCategoryId($category_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p2c.category_id = '" . (int)$category_id . "' ORDER BY pd.name ASC");

        return $query->rows;
    }

    /**
     * Adds Duty data to products depending on the method of adding
     *
     * @param array $products
     * @param array $category_attributes
     * @param array $languages
     * @return integer
     */
    public function addCategoryAttributesToProducts($products, $category_attributes, $languages)
    {
        // in foreach
        set_time_limit(600);

        $count_affected = 0;
        foreach ($products as $product) {
            foreach ($category_attributes as $attribute_id) {
                foreach ($languages as $language) {
                    $text = $this->getDutyByMethod($product['product_id'], $attribute_id, $language['language_id']);

                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product['product_id'] . "', attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language['language_id'] . $text
                        . "ON DUPLICATE KEY UPDATE  product_id = '" . (int)$product['product_id'] . "', attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language['language_id'] . $text);

                    $this->productDateModified($product['product_id']);

                    $count_affected += $this->db->countAffected();
                }
            }
        }
        return $count_affected;
    }

    /**
     * Forms attribute value depending on the method of placing values in the product
     *
     * @param integer $product_id
     * @param integer $attribute_id
     * @param integer $language_id
     * @return string
     */
    protected function getDutyByMethod($product_id, $attribute_id, $language_id)
    {
        $method = $this->config->get($this->model . '_product_text');

        switch ($method) {
            case 'clean':
                $text = "', text = '' ";
                break;
            case 'overwrite':
                $duty = $this->whoIsOnDuty($attribute_id, $language_id);
                $text = $duty ? "', text = '" . $this->db->escape($duty) . "' " : "'";
                break;
            case 'ifempty':
                $query = $this->db->query("SELECT text FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "'  AND language_id = '" . (int)$language_id . "'");

                if (!empty($query->row['text'])) {
                    $text = "'";
                } else {
                    $duty = $this->whoIsOnDuty($attribute_id, $language_id);
                    $text = $duty ? "', text = '" . $this->db->escape($duty) . "' " : "'";
                }
                break;
            case 'unchange':
            default:
                $text = "'";
                break;
        }

        return $text;
    }

    public function deleteCategoryAttributesFromProducts($products, $data)
    {
        // in foreach
        foreach ($products as $product) {
            if (isset($data['category_attribute'])) {
                foreach ($data['category_attribute'] as $attribute_id) {
                    $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product['product_id'] . "' AND attribute_id = '" . (int)$attribute_id . "'");
                    $this->productDateModified($product['product_id']);
                }
            }
        }
    }

    /**
     * Duty Data Base service
     *
     **/
    public function editDuty($attribute_id, $data)
    {
        $this->cache->delete($this->model);

        foreach ($data['attribute_description'] as $language_id => $value) {
            $this->db->query("UPDATE " . DB_PREFIX . "attribute_description SET duty = '" . $this->db->escape($value['duty']) . "' WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "'");
        }
    }

    /**
     * Gets duty value or template for concrete attribute
     * The output array is made in compatibility mode with the output array 
     * of the getAttributeValues function to build common lists of values
     * 
     * @param integer $attribute_id
     * @return array 
     */
    public function getDutyValues($attribute_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");
        $attribute_values_data = array();
        foreach ($query->rows as $result) {
            $attribute_values_data[$result['language_id']][] = array('text' => $result['duty']);
        }
        return $attribute_values_data;
    }

    /**
     * Gets duty value for concrete attribute and language
     *
     * @param integer $attribute_id
     * @param integer $language_id
     * @return string only value of duty field
     */
    public function whoIsOnDuty($attribute_id, $language_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "'");

        return !empty($query->row) ? $query->row['duty'] : '';
    }

    /**
     * OTHER
     *
     **/
    public function sortAttribute($data)
    {
        $this->cache->delete($this->model);

        switch ($data['table']) {
            case 'attribute':
                $data_table = DB_PREFIX . "attribute";
                $id = "attribute_id";
                break;
            case 'group':
                $data_table = DB_PREFIX . "attribute_group";
                $id = "attribute_group_id";
                break;
            default:
                break;
        }

        $target_id = $data['target_id'];
        $target_sort_order = (int) $this->db->query("SELECT target.sort_order FROM " . $data_table . " target WHERE target." . $id . " = '" . (int) $target_id . "'")->row['sort_order'];

        if ($data['direct'] == 'after') {
            $dir = " ASC";
        } else {
            $dir = " DESC";
        }

        $sources = $this->db->query("SELECT a.* FROM " . $data_table . " a  WHERE a." . $id . " IN (" . implode(",", $data['subject_id']) . ") ORDER BY a.sort_order" . $dir);


        foreach ($sources->rows as $source) {
            switch ($data['direct']) {
                case 'after':
                    if ((int) $source['sort_order'] > $target_sort_order) {
                        // Снизу вверх
                        $sql_spread = " a.sort_order = a.sort_order + 1  WHERE a.sort_order > " . $target_sort_order . " AND a.sort_order <= " . (int) $source['sort_order'];
                        $sql_insert = " a.sort_order = " . ($target_sort_order + 1);
                    } else {
                        // Сверху вниз
                        $sql_spread = " a.sort_order = a.sort_order - 1  WHERE a.sort_order <= " . $target_sort_order . " AND a.sort_order >= " . (int) $source['sort_order'];
                        $sql_insert = " a.sort_order = " . $target_sort_order;
                    }
                    break;

                case 'before':
                    if ((int) $source['sort_order'] > $target_sort_order) {
                        $sql_spread = " a.sort_order = a.sort_order + 1  WHERE a.sort_order >= " . $target_sort_order . " AND a.sort_order <= " . (int) $source['sort_order'];
                        $sql_insert = " a.sort_order = " . $target_sort_order;
                    } else {
                        $sql_spread = " a.sort_order = a.sort_order - 1  WHERE a.sort_order < " . $target_sort_order . " AND a.sort_order >= " . (int) $source['sort_order'];
                        $sql_insert = " a.sort_order = " . ($target_sort_order - 1);
                    }
                    break;

                default:
                    break;
            }
            // раздвижка
            $this->db->query("UPDATE " . $data_table . " a SET" . $sql_spread);

            // вставка
            $this->db->query("UPDATE " . $data_table . " a SET" . $sql_insert . " WHERE a." . $id . " = '" . (int) $source[$id] . "'");

            $target_id = $source[$id];
        }

        return;
    }

    protected function productDateModified($product_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
    }

    /**
     * Group data by languages     * 
     * Needs row['language_id']
     * 
     * @param array $rows
     * @return void
     */
    protected function groupByLang($rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $lang_data = [];
            foreach ($row as $key => $value) {
                $lang_data[$key] = $value;
            }
            $result[$row['language_id']] = $lang_data;
        }
        return $result;
    }
}

class ModelCatalogAttributipro extends ModelCatalogAttributico
{
    protected $model = 'attributipro';

    /**
     * Addition New attribute or paste copying attribute
     * $data['new] is manage flag
     *
     * @param array $data
     * $data['attribute_description'] structure example [empty,'name'=>A1ru,empty,'name'=>A1en]
     * empty if language not present by any id   [1] name = A1ru
     *                                           [3] name = A1en
     * 
     * @return int new attribute id 
     * 
     * Executed in a foreach loop, cache delete in controller
     */
    public function addAttribute($data)
    {
        /**
         * Inserting a new record into the database. You can edit the Info structure later with the updateAttributeInfo function
         */
        $maxorder = $this->db->query("SELECT MAX(`sort_order`) AS maxorder FROM " . DB_PREFIX . "attribute");
        $this->db->query("INSERT INTO " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$data['attribute_group_id'] . "', sort_order = '" . ((int)$maxorder->row['maxorder'] + 1) . "'");
        /**
         * This id will be added to the name when creating: New attribute_234, when copying is not added - the flag data['new']
         */
        $attribute_id = $this->db->getLastId();

        foreach ($data['attribute_description'] as $language_id => $value) {
            $sql = "INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id . "',
             name = '" . $this->db->escape($value['name']) . ($data['new'] ? '_' . $attribute_id : '') . "'";

            $this->db->query($sql);

            /**
             * We transfer the all Info structure from the previous attribute if copy paste
             */
            if (!$data['new']) {
                $info = $this->getAttributeInfo($data['attribute_id'], $language_id);
                $this->updateAttributeInfo($attribute_id, $info, $language_id);
                $duty_info = $this->getDutyInfo($data['attribute_id'], $language_id);
                $this->updateDutyInfo($attribute_id, $duty_info, $language_id);
            }
        }

        return $attribute_id;
    }

    /**
     * Gets all attribute structure with attribute group and description data
     *  Overload bas method
     * 
     * @param string $attribute_id
     * @param integer $language_id
     * @return array
     */
    public function getAttributeInfo($attribute_id, $language_id = 0)
    {
        $attribute_info = array();
        if ($language_id) {
            $sql_lang = " AND ad.language_id = '" . (int)$language_id . "'";
        } else {
            $sql_lang = '';
        }

        $query = $this->db->query("SELECT ad.language_id, a.attribute_id, ad.name, a.attribute_group_id, oagd.name AS group_name, a.sort_order, a.image, a.icon, a.unit_id, a.status, ad.tooltip, ad.duty FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_group_description oagd ON (a.attribute_group_id = oagd.attribute_group_id AND oagd.language_id = ad.language_id) WHERE a.attribute_id = '" . (int)$attribute_id . "'" . $sql_lang);

        if ($language_id) {
            return $query->row;
        } else {
            return $this->groupByLang($query->rows);
        }
    }

    /**
     * Update only attribute parameters with only duty field
     * If you need update all duty parameters use updateDutyInfo
     *
     * @param int $attribute_id
     * @param array $data
     * @param integer $language_id
     * @return void
     */
    public function updateAttributeInfo($attribute_id, $data, $language_id)
    {
        $this->cache->delete($this->model);

        $this->db->query("UPDATE " . DB_PREFIX . "attribute SET image = '" . $data['image'] . "', icon = '" . $data['icon'] . "', unit_id = '" . (int)$data['unit_id'] . "', status = '" . (int)$data['status'] . "' WHERE attribute_id = '" . (int)$attribute_id . "'");

        $this->db->query("UPDATE " . DB_PREFIX . "attribute_description SET name = '" . $this->db->escape($data['name']) . "', duty = '" . $this->db->escape($data['duty']) . "', tooltip = '" . $this->db->escape($data['tooltip']) . "' WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "'");
    }

    /**
     * Gets all attribute value info structure for concrete product
     *
     * @param integer $product_id
     * @param integer $attribute_id
     * @param integer $language_id
     * @return array  never returns empty
     */
    public function getAttributeValueInfo($product_id, $attribute_id, $language_id = 0)
    {
        $info = array();
        if ($language_id) {
            $sql_lang = " AND opa.language_id = '" . (int)$language_id . "'";
        } else {
            $sql_lang = '';
        }

        $query = $this->db->query("SELECT opa.*, ad.name FROM " . DB_PREFIX . "product_attribute opa 
            LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (ad.attribute_id = opa.attribute_id AND opa.language_id = ad.language_id) 
            WHERE opa.product_id='" . (int)$product_id . "' AND opa.attribute_id='" . (int)$attribute_id . "'" . $sql_lang);

        if ($language_id) {
            /**
             * If sql query returns empty so it is new value and we must prepare new structure
             */
            return $query->row ? $query->row : array(
                'product_id' => $product_id,
                'attribute_id' => $attribute_id,
                'name' => '',
                'text' => "",
                'image' => null,
                'tooltip' => "",
                'icon' => "",
                'url' => "",
                'unit_id' => 0,
                'status' => 0
            );
        } else {
            return $this->groupByLang($query->rows);
        }
    }

    public function editValueInfo($product_id, $attribute_id, $language_id, $data)
    {
        $this->cache->delete($this->model);

        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id .
            "', text = '" . $this->db->escape($data['text']) . "', image = '" . $data['image'] . "', icon = '" . $data['icon'] . "', url = '" . $this->db->escape($data['url']) .
            "', unit_id = '" . (int)$data['unit_id'] . "', status = '" . (int)$data['status'] . "', tooltip = '" . $this->db->escape($data['tooltip']) . "'" .
            "ON DUPLICATE KEY UPDATE  product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id .
            "', text = '" . $this->db->escape($data['text']) . "', image = '" . $data['image'] . "', icon = '" . $data['icon'] . "', url = '" . $this->db->escape($data['url']) .
            "', unit_id = '" . (int)$data['unit_id'] . "', status = '" . (int)$data['status'] . "', tooltip = '" . $this->db->escape($data['tooltip']) . "'");
        $this->productDateModified($product_id);
    }

    /**
     * Updates language-independent options
     * Will be used for apply for all languages operation
     *
     * @param int $product_id
     * @param int $attribute_id
     * @param array $data
     * @return void
     */
    public function updateValueAllLanguages($product_id, $attribute_id, $data)
    {
        $this->cache->delete($this->model);

        $this->db->query("UPDATE " . DB_PREFIX . "product_attribute SET image = '" . $data['image'] . "', icon = '" . $data['icon'] . "', unit_id = '" . (int)$data['unit_id'] . "', status = '" . (int)$data['status'] . "', url = '" . $data['url'] . "' WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "'");

        $this->productDateModified($product_id);
    }

    /**
     * Get all duty parameters
     *
     * @param integer $attribute_id
     * @param integer $language_id
     * @return array single array of parameters or multi array when $keys = language_id 
     */
    public function getDutyInfo($attribute_id, $language_id = 0)
    {
        $sql_lang = $language_id ? " AND ad.language_id = '" . (int)$language_id . "'" : '';

        $query = $this->db->query("SELECT ad.*, a.attribute_id FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id)  WHERE a.attribute_id = '" . (int)$attribute_id . "'" . $sql_lang);

        return $language_id ? $query->row : $this->groupByLang($query->rows);
    }

    /**
     * Update all duty parameters in attribute_description table
     * If you need to update attribute parameters use updateAttributeInfo
     *
     * @param integer $attribute_id
     * @param array $data
     * @param integer $language_id
     * @return void
     */
    public function updateDutyInfo($attribute_id, $data, $language_id = 0)
    {
        $this->cache->delete($this->model);

        $this->db->query("UPDATE " . DB_PREFIX . "attribute_description SET duty = '" . $this->db->escape($data['duty']) . "', duty_tooltip = '" . $this->db->escape($data['duty_tooltip']) . "', duty_image = '" . $this->db->escape($data['duty_image']) . "', duty_icon = '" . $this->db->escape($data['duty_icon']) . "', duty_unit_id = '" . (int)$data['duty_unit_id'] . "', duty_status = '" . (int)$data['duty_status'] . "' WHERE attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "'");
    }

    /**
     * Updates language-independent options
     * Will be used for apply for all languages operation
     *
     * @param int $attribute_id
     * @param array $data
     * @return void
     */
    public function updateDutyAllLanguages($attribute_id, $data)
    {
        $this->cache->delete($this->model);

        $this->db->query("UPDATE " . DB_PREFIX . "attribute_description SET duty_image = '" . $data['duty_image'] . "', duty_icon = '" . $data['duty_icon'] . "', duty_unit_id = '" . (int)$data['duty_unit_id'] . "', duty_status = '" . (int)$data['duty_status'] . "' WHERE attribute_id = '" . (int)$attribute_id . "'");
    }

    /**
     * Forms attribute value depending on the method of placing values in the product
     * Overload base method
     * 
     * @param integer $product_id
     * @param integer $attribute_id
     * @param integer $language_id
     * @return string
     */
    protected function getDutyByMethod($product_id, $attribute_id, $language_id)
    {
        $method = $this->config->get($this->model . '_product_text');

        switch ($method) {
            case 'clean':
                $text = "', text = '', image = '', icon = '', url = '', unit_id = '0', status = '0', tooltip = ''";
                break;
            case 'overwrite':
                $duty = $this->getDutyInfo($attribute_id, $language_id);
                // Overwrite if duty options not empty exclude unit and status
                $text = $duty['duty'] ? ", text = '" . $this->db->escape($duty['duty']) . "'" : '';
                $image = $duty['duty_image'] ? ", image = '" . $duty['duty_image'] . "'" : '';
                $icon = $duty['duty_icon'] ? ", icon = '" . $duty['duty_icon'] . "'" : '';
                //TODO url not exist in duty because not enough data for create it in duty form
                //$url = $duty['duty_url'] ? ", url = '" . $this->db->escape($duty['duty_url']) . "'" : '';
                $unit_id =  ", unit_id = '" . (int)$duty['duty_unit_id'] . "'";
                $status =  ", status = '" . (int)$duty['duty_status'] . "'";
                $tooltip = $duty['duty_tooltip'] ? ", tooltip = '" . $this->db->escape($duty['duty_tooltip']) . "'" : '';

                $text = $duty['duty'] ? "'" . $text . $image . $icon . $unit_id . $status . $tooltip : "'";
                break;
            case 'ifempty':
                $value = $this->getAttributeValueInfo($product_id, $attribute_id, $language_id);

                $duty = $this->getDutyInfo($attribute_id, $language_id);

                $text = !$value['text'] ? ", text = '" . $this->db->escape($duty['duty']) . "'" : '';
                $image = !$value['image'] ? ", image = '" . $duty['duty_image'] . "'" : '';
                $icon = !$value['icon'] ? ", icon = '" . $duty['duty_icon'] . "'" : '';
                //TODO url not exist in duty because not enough data for create it in duty form
                //$url = !$value['url'] ? ", url = '" . $this->db->escape($duty['duty_url']) . "'" : '';
                $unit_id = !$value['unit_id'] ? ", unit_id = '" . (int)$duty['duty_unit_id'] . "'" : '';
                $status = !$value['status'] ? ", status = '" . (int)$duty['duty_status'] . "'" : '';
                $tooltip = !$value['tooltip'] ? ", tooltip = '" . $this->db->escape($duty['duty_tooltip']) . "'" : '';

                $text = $duty['duty'] ? "'" . $text . $image . $icon . $unit_id . $status . $tooltip : "'";
                break;
            case 'unchange':
            default:
                $text = "'";
                break;
        }

        return $text;
    }

    /**
     * Search and group concat path chain for every category from array categories
     * For example 61_78_120
     *
     * @param array $categories
     * @return array of ['category_id_1, 'path']
     */
    public function getPath($categories)
    {
        $query = $this->db->query("SELECT DISTINCT `category_id` AS `category_id_1`,
                (SELECT CONVERT(GROUP_CONCAT(`path_id` SEPARATOR '_') USING utf8mb4) FROM oc_category_path WHERE `category_id` = `category_id_1` ) AS `path`
                FROM oc_category_path WHERE category_id IN (" . implode(",", $categories) . ")");
        return $query->rows;
    }
}
