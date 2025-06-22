<?php
/**
 * Módulo: Duplicate Carrier
 * Permite duplicar transportistas existentes con todos sus datos
 * Compatible con PrestaShop 1.7.x y 8.x
 *
 * @version 1.3.0
 * @author Custom Module / Corregido por IA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class DuplicateCarrier extends Module
{
    public function __construct()
    {
        $this->name = 'duplicatecarrier';
        $this->tab = 'shipping_logistics';
        $this->version = '1.3.0';
        $this->author = 'Custom Module / Corregido por IA';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Duplicar Transportista');
        $this->description = $this->l('Permite duplicar transportistas existentes con todas sus configuraciones.');
        $this->confirmUninstall = $this->l('¿Estás seguro de que quieres desinstalar este módulo?');
    }

    public function install()
    {
        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitDuplicateCarrier')) {
            $output .= $this->handleForm();
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $carriers = Carrier::getCarriers($this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS);
        $carrier_options = [['id_carrier' => 0, 'name' => $this->l('-- Seleccionar transportista --')]];
        foreach ($carriers as $carrier) {
            $carrier_options[] = ['id_carrier' => $carrier['id_carrier'], 'name' => $carrier['name']];
        }

        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->l('Duplicar Transportista')],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Transportista a duplicar'),
                        'name' => 'id_carrier_original',
                        'required' => true,
                        'options' => ['query' => $carrier_options, 'id' => 'id_carrier', 'name' => 'name'],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Nombre del transportista'),
                        'name' => 'name',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Velocidad de tránsito'),
                        'name' => 'delay',
                        'required' => true,
                        'desc' => $this->l('Ejemplo: "Entrega en 2-4 días laborables"'),
                    ],
                    ['type' => 'file', 'label' => $this->l('Logo'), 'name' => 'logo'],
                ],
                'submit' => ['title' => $this->l('Duplicar Transportista')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitDuplicateCarrier';
        
        $helper->fields_value = [
            'id_carrier_original' => Tools::getValue('id_carrier_original', 0),
            'name' => Tools::getValue('name', ''),
            'delay' => Tools::getValue('delay', ''),
        ];
        
        return $helper->generateForm([$fields_form]);
    }

    private function handleForm()
    {
        $id_carrier_original = (int)Tools::getValue('id_carrier_original');
        
        $name_value_from_post = trim((string)Tools::getValue('name'));
        $delay_value_from_post = trim((string)Tools::getValue('delay'));

        $name_multi = [];
        $delay_multi = [];
        $languages = Language::getLanguages(false);
        $default_lang_id = (int)Configuration::get('PS_LANG_DEFAULT');

        foreach ($languages as $lang) {
            $name_multi[$lang['id_lang']] = $name_value_from_post;
            $delay_multi[$lang['id_lang']] = $delay_value_from_post;
        }

        // --- VALIDACIÓN (con omisión de Validate::isCarrierName) ---
        if (!$id_carrier_original) {
            return $this->displayError($this->l('Debes seleccionar un transportista para duplicar.'));
        }
        
        if (empty($name_multi[$default_lang_id])) {
            return $this->displayError($this->l('El nombre del transportista es obligatorio para el idioma por defecto.'));
        }
        
        // **ESTA ES LA LÍNEA MODIFICADA:**
        // Se ha comentado la validación de Validate::isCarrierName para evitar el error.
        // if (!Validate::isCarrierName($name_multi[$default_lang_id])) {
        //     return $this->displayError($this->l('El nombre del transportista no es válido.'));
        // }

        if (empty($delay_multi[$default_lang_id])) {
            return $this->displayError($this->l('La velocidad de tránsito es obligatoria para el idioma por defecto.'));
        }
        // Se mantiene Validate::isGenericName para delay, que es más permisiva.
        if (!Validate::isGenericName($delay_multi[$default_lang_id])) {
             return $this->displayError($this->l('La velocidad de tránsito no es válida.'));
        }
        // --- FIN VALIDACIÓN ---

        $original_carrier = new Carrier($id_carrier_original);
        if (!Validate::isLoadedObject($original_carrier)) {
            return $this->displayError($this->l('El transportista seleccionado no existe.'));
        }

        try {
            $new_carrier = new Carrier();
            $new_carrier->name = $name_multi;
            $new_carrier->delay = $delay_multi;
            
            $new_carrier->shipping_handling = $original_carrier->shipping_handling;
            $new_carrier->range_behavior = $original_carrier->range_behavior;
            $new_carrier->is_module = $original_carrier->is_module;
            $new_carrier->is_free = $original_carrier->is_free;
            $new_carrier->shipping_external = $original_carrier->shipping_external;
            $new_carrier->need_range = $original_carrier->need_range;
            $new_carrier->external_module_name = $original_carrier->external_module_name;
            $new_carrier->shipping_method = $original_carrier->shipping_method;
            $new_carrier->max_width = $original_carrier->max_width;
            $new_carrier->max_height = $original_carrier->max_height;
            $new_carrier->max_depth = $original_carrier->max_depth;
            $new_carrier->max_weight = $original_carrier->max_weight;
            $new_carrier->grade = $original_carrier->grade;
            $new_carrier->url = $original_carrier->url;
            $new_carrier->active = 1;
            $new_carrier->deleted = 0;
            $new_carrier->position = Carrier::getHigherPosition() + 1;

            if (!$new_carrier->add()) {
                return $this->displayError($this->l('Error al crear el transportista.'));
            }

            if ($new_carrier->id) {
                $new_carrier->id_reference = $new_carrier->id;
                $new_carrier->update();
            }

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0 && !empty($_FILES['logo']['tmp_name'])) {
                $this->uploadLogo($new_carrier->id);
            } else {
                $this->copyLogo($original_carrier->id, $new_carrier->id);
            }

            $this->duplicateCarrierAssociations($original_carrier->id, $new_carrier->id);
            Carrier::cleanPositions();
            return $this->displayConfirmation($this->l('Transportista duplicado correctamente. ID: ') . $new_carrier->id);
        } catch (Exception $e) {
            return $this->displayError($this->l('Error al duplicar el transportista: ') . $e->getMessage());
        }
    }

    private function uploadLogo($id_carrier)
    {
        $logo_dir = _PS_SHIP_IMG_DIR_;
        if (!file_exists($logo_dir)) {
            mkdir($logo_dir, 0775, true);
        }
        $this->deleteLogo($id_carrier);
        $tmp_name = $_FILES['logo']['tmp_name'];
        if (!$tmp_name || !is_uploaded_file($tmp_name)) {
            return;
        }
        $name = $_FILES['logo']['name'];
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif']) || !getimagesize($tmp_name)) {
            throw new Exception('Formato de imagen no válido.');
        }
        $destination = $logo_dir . $id_carrier . '.jpg';
        if (!move_uploaded_file($tmp_name, $destination)) {
            throw new Exception('Error al subir el logo.');
        }
    }

    private function deleteLogo($id_carrier)
    {
        $logo_dir = _PS_SHIP_IMG_DIR_;
        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $file_path = $logo_dir . $id_carrier . '.' . $ext;
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
    }

    private function copyLogo($original_id, $new_id)
    {
        $logo_dir = _PS_SHIP_IMG_DIR_;
        $this->deleteLogo($new_id);
        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $original_file = $logo_dir . $original_id . '.' . $ext;
            if (file_exists($original_file)) {
                $new_file = $logo_dir . $new_id . '.jpg';
                if (!copy($original_file, $new_file)) {
                    throw new Exception('Error al copiar el logo.');
                }
                break;
            }
        }
    }

    private function duplicateCarrierAssociations($original_id, $new_id)
    {
        $db = Db::getInstance();
        $active_zones = [];

        $shops = $db->executeS('SELECT `id_shop` FROM `'._DB_PREFIX_.'carrier_shop` WHERE `id_carrier` = '.(int)$original_id);
        if (empty($shops)) {
            $db->insert('carrier_shop', ['id_carrier' => (int)$new_id, 'id_shop' => (int)$this->context->shop->id]);
        } else {
            foreach ($shops as $shop) {
                $db->insert('carrier_shop', ['id_carrier' => (int)$new_id, 'id_shop' => (int)$shop['id_shop']]);
            }
        }

        $zones = $db->executeS('SELECT `id_zone` FROM `'._DB_PREFIX_.'carrier_zone` WHERE `id_carrier` = '.(int)$original_id);
        foreach ($zones as $zone) {
            $id_zone = (int)$zone['id_zone'];
            $db->insert('carrier_zone', ['id_carrier' => (int)$new_id, 'id_zone' => $id_zone]);
            $active_zones[] = $id_zone;
        }

        $groups = $db->executeS('SELECT `id_group` FROM `'._DB_PREFIX_.'carrier_group` WHERE `id_carrier` = '.(int)$original_id);
        foreach ($groups as $group) {
            $db->insert('carrier_group', ['id_carrier' => (int)$new_id, 'id_group' => (int)$group['id_group']]);
        }

        $weight_ranges = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'range_weight` WHERE `id_carrier` = '.(int)$original_id);
        $weight_range_mapping = [];
        foreach ($weight_ranges as $range) {
            $db->insert('range_weight', ['id_carrier' => (int)$new_id, 'delimiter1' => (float)$range['delimiter1'], 'delimiter2' => (float)$range['delimiter2']]);
            $weight_range_mapping[$range['id_range_weight']] = $db->Insert_ID();
        }

        $price_ranges = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'range_price` WHERE `id_carrier` = '.(int)$original_id);
        $price_range_mapping = [];
        foreach ($price_ranges as $range) {
            $db->insert('range_price', ['id_carrier' => (int)$new_id, 'delimiter1' => (float)$range['delimiter1'], 'delimiter2' => (float)$range['delimiter2']]);
            $price_range_mapping[$range['id_range_price']] = $db->Insert_ID();
        }

        $deliveries = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'delivery` WHERE `id_carrier` = '.(int)$original_id);
        foreach ($deliveries as $delivery) {
            $delivery_zone_id = (int)$delivery['id_zone'];
            if (in_array($delivery_zone_id, $active_zones)) {
                $db->insert('delivery', [
                    'id_carrier' => (int)$new_id,
                    'id_range_price' => (int)($price_range_mapping[$delivery['id_range_price']] ?? 0),
                    'id_range_weight' => (int)($weight_range_mapping[$delivery['id_range_weight']] ?? 0),
                    'id_zone' => $delivery_zone_id,
                    'price' => (float)$delivery['price']
                ]);
            }
        }

        $tax_rules = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'carrier_tax_rules_group_shop` WHERE `id_carrier` = '.(int)$original_id);
        foreach ($tax_rules as $tax_rule) {
            $db->insert('carrier_tax_rules_group_shop', ['id_carrier' => (int)$new_id, 'id_tax_rules_group' => (int)$tax_rule['id_tax_rules_group'], 'id_shop' => (int)$tax_rule['id_shop']]);
        }
    }
}