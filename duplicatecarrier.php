<?php
/**
 * Module: Duplicate Carrier
 * Permite duplicar transportistas existentes con todos sus datos
 * Compatible con PrestaShop 1.7.x y 8.x
 * 
 * @version 2.0.1
 * @author Custom Module - Versión corregida definitiva
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
        $this->version = '2.0.1';
        $this->author = 'Custom Module';
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
            $output .= $this->processDuplication();
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $carriers = Carrier::getCarriers($this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS);
        $carrier_options = array(
            array('id_carrier' => 0, 'name' => $this->l('-- Seleccionar transportista --'))
        );
        
        foreach ($carriers as $carrier) {
            $carrier_options[] = array(
                'id_carrier' => $carrier['id_carrier'],
                'name' => $carrier['name']
            );
        }

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Duplicar Transportista'),
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Transportista a duplicar'),
                        'name' => 'id_carrier_original',
                        'required' => true,
                        'options' => array(
                            'query' => $carrier_options,
                            'id' => 'id_carrier',
                            'name' => 'name'
                        ),
                        'desc' => $this->l('Selecciona el transportista que quieres duplicar')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Nombre del nuevo transportista'),
                        'name' => 'new_name',
                        'required' => true,
                        'desc' => $this->l('Nombre para el transportista duplicado')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Tiempo de entrega'),
                        'name' => 'new_delay',
                        'required' => true,
                        'desc' => $this->l('Ejemplo: "Entrega en 2-4 días laborables"')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Estado'),
                        'name' => 'active',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Activado')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Desactivado')
                            )
                        ),
                    ),
                    array(
                        'type' => 'file',
                        'label' => $this->l('Logo (opcional)'),
                        'name' => 'logo',
                        'desc' => $this->l('Sube un nuevo logo o se copiará el del transportista original')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Duplicar Transportista'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submitDuplicateCarrier';
        
        $helper->fields_value = array(
            'id_carrier_original' => Tools::getValue('id_carrier_original', 0),
            'new_name' => Tools::getValue('new_name', ''),
            'new_delay' => Tools::getValue('new_delay', ''),
            'active' => Tools::getValue('active', 1),
        );

        return $helper->generateForm(array($fields_form));
    }

    private function processDuplication()
    {
        // Obtener valores del formulario
        $id_carrier_original = (int)Tools::getValue('id_carrier_original');
        $new_name = trim(Tools::getValue('new_name'));
        $new_delay = trim(Tools::getValue('new_delay'));
        $active = (int)Tools::getValue('active');

        // Validaciones
        if (!$id_carrier_original) {
            return $this->displayError($this->l('Debes seleccionar un transportista para duplicar.'));
        }

        if (empty($new_name)) {
            return $this->displayError($this->l('El nombre del transportista es obligatorio.'));
        }

        if (empty($new_delay)) {
            return $this->displayError($this->l('El tiempo de entrega es obligatorio.'));
        }

        // Cargar transportista original
        $original_carrier = new Carrier($id_carrier_original);
        if (!Validate::isLoadedObject($original_carrier)) {
            return $this->displayError($this->l('El transportista seleccionado no existe.'));
        }

        try {
            // Crear nuevo transportista
            $new_carrier = new Carrier();
            
            // Copiar propiedades básicas del original
            $properties_to_copy = array(
                'id_tax_rules_group', 'url', 'shipping_handling', 'range_behavior',
                'is_module', 'is_free', 'shipping_external', 'need_range',
                'external_module_name', 'shipping_method', 'max_width',
                'max_height', 'max_depth', 'max_weight', 'grade'
            );
            
            foreach ($properties_to_copy as $property) {
                if (property_exists($original_carrier, $property)) {
                    $new_carrier->{$property} = $original_carrier->{$property};
                }
            }
            
            // Establecer propiedades específicas del nuevo transportista
            $new_carrier->name = $new_name;
            $new_carrier->active = $active;
            $new_carrier->deleted = 0;
            $new_carrier->position = Carrier::getHigherPosition() + 1;
            
            // IMPORTANTE: Preparar campo delay multiidioma ANTES de add()
            $languages = Language::getLanguages(false);
            $delay_array = array();
            
            // Asignar el delay a todos los idiomas
            foreach ($languages as $language) {
                $delay_array[$language['id_lang']] = $new_delay;
            }
            
            // Asignar el array de delay al carrier
            $new_carrier->delay = $delay_array;
            
            // Guardar el transportista
            if (!$new_carrier->add()) {
                throw new Exception($this->l('Error al crear el transportista.'));
            }
            
            // Actualizar id_reference
            $new_carrier->id_reference = $new_carrier->id;
            $new_carrier->update();
            
            // Es posible que el método add() ya haya creado algunas asociaciones
            // Limpiar asociaciones existentes antes de duplicar
            $this->cleanCarrierAssociations($new_carrier->id);
            
            // Procesar logo
            try {
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                    $this->uploadLogo($new_carrier->id);
                } else {
                    $this->copyLogo($original_carrier->id, $new_carrier->id);
                }
            } catch (Exception $e) {
                // Si hay error con el logo, continuar sin él
                error_log('Error procesando logo: ' . $e->getMessage());
            }
            
            // Duplicar todas las asociaciones
            $this->duplicateCarrierData($original_carrier->id, $new_carrier->id);
            
            // Limpiar caché
            Carrier::cleanPositions();
            
            return $this->displayConfirmation(
                $this->l('Transportista duplicado correctamente.') . ' ' .
                $this->l('ID: ') . $new_carrier->id . ' - ' .
                $this->l('Nombre: ') . $new_name
            );
            
        } catch (Exception $e) {
            return $this->displayError(
                $this->l('Error al duplicar el transportista: ') . $e->getMessage()
            );
        }
    }

    private function cleanCarrierAssociations($carrier_id)
    {
        $db = Db::getInstance();
        
        // Limpiar asociaciones que podrían haberse creado automáticamente
        $db->delete('carrier_shop', 'id_carrier = '.(int)$carrier_id);
        $db->delete('carrier_zone', 'id_carrier = '.(int)$carrier_id);
        $db->delete('carrier_group', 'id_carrier = '.(int)$carrier_id);
        $db->delete('carrier_tax_rules_group_shop', 'id_carrier = '.(int)$carrier_id);
    }
    
    private function duplicateCarrierData($original_id, $new_id)
    {
        $db = Db::getInstance();
        
        try {
            // 1. Duplicar asociaciones con tiendas (evitando duplicados)
            $shops = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'carrier_shop` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            if (empty($shops)) {
                // Si no hay tiendas asociadas, asociar con la tienda actual
                $db->insert('carrier_shop', array(
                    'id_carrier' => (int)$new_id,
                    'id_shop' => (int)$this->context->shop->id
                ), false, true, Db::INSERT_IGNORE);
            } else {
                foreach ($shops as $shop) {
                    $db->insert('carrier_shop', array(
                        'id_carrier' => (int)$new_id,
                        'id_shop' => (int)$shop['id_shop']
                    ), false, true, Db::INSERT_IGNORE);
                }
            }
            
            // 2. Duplicar zonas
            $zones = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'carrier_zone` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            if (empty($zones)) {
                // Si no hay zonas, asociar con todas las zonas activas
                $all_zones = $db->executeS('
                    SELECT `id_zone` FROM `'._DB_PREFIX_.'zone` 
                    WHERE `active` = 1'
                );
                foreach ($all_zones as $zone) {
                    $db->insert('carrier_zone', array(
                        'id_carrier' => (int)$new_id,
                        'id_zone' => (int)$zone['id_zone']
                    ), false, true, Db::INSERT_IGNORE);
                }
            } else {
                foreach ($zones as $zone) {
                    $db->insert('carrier_zone', array(
                        'id_carrier' => (int)$new_id,
                        'id_zone' => (int)$zone['id_zone']
                    ), false, true, Db::INSERT_IGNORE);
                }
            }
            
            // 3. Duplicar grupos
            $groups = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'carrier_group` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            if (empty($groups)) {
                // Si no hay grupos, asociar con todos los grupos
                $all_groups = $db->executeS('SELECT `id_group` FROM `'._DB_PREFIX_.'group`');
                foreach ($all_groups as $group) {
                    $db->insert('carrier_group', array(
                        'id_carrier' => (int)$new_id,
                        'id_group' => (int)$group['id_group']
                    ), false, true, Db::INSERT_IGNORE);
                }
            } else {
                foreach ($groups as $group) {
                    $db->insert('carrier_group', array(
                        'id_carrier' => (int)$new_id,
                        'id_group' => (int)$group['id_group']
                    ), false, true, Db::INSERT_IGNORE);
                }
            }
            
            // 4. Duplicar rangos de peso con mapeo
            $weight_ranges = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'range_weight` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            $weight_range_mapping = array();
            foreach ($weight_ranges as $range) {
                $db->insert('range_weight', array(
                    'id_carrier' => (int)$new_id,
                    'delimiter1' => (float)$range['delimiter1'],
                    'delimiter2' => (float)$range['delimiter2']
                ));
                $weight_range_mapping[$range['id_range_weight']] = $db->Insert_ID();
            }
            
            // 5. Duplicar rangos de precio con mapeo
            $price_ranges = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'range_price` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            $price_range_mapping = array();
            foreach ($price_ranges as $range) {
                $db->insert('range_price', array(
                    'id_carrier' => (int)$new_id,
                    'delimiter1' => (float)$range['delimiter1'],
                    'delimiter2' => (float)$range['delimiter2']
                ));
                $price_range_mapping[$range['id_range_price']] = $db->Insert_ID();
            }
            
            // 6. Duplicar precios de entrega con los nuevos IDs
            $deliveries = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'delivery` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            foreach ($deliveries as $delivery) {
                $new_range_price = 0;
                $new_range_weight = 0;
                
                if ($delivery['id_range_price'] && isset($price_range_mapping[$delivery['id_range_price']])) {
                    $new_range_price = $price_range_mapping[$delivery['id_range_price']];
                }
                
                if ($delivery['id_range_weight'] && isset($weight_range_mapping[$delivery['id_range_weight']])) {
                    $new_range_weight = $weight_range_mapping[$delivery['id_range_weight']];
                }
                
                $db->insert('delivery', array(
                    'id_carrier' => (int)$new_id,
                    'id_range_price' => (int)$new_range_price,
                    'id_range_weight' => (int)$new_range_weight,
                    'id_zone' => (int)$delivery['id_zone'],
                    'price' => (float)$delivery['price']
                ), false, true, Db::INSERT_IGNORE);
            }
            
            // 7. Duplicar reglas de impuestos
            $tax_rules = $db->executeS('
                SELECT * FROM `'._DB_PREFIX_.'carrier_tax_rules_group_shop` 
                WHERE `id_carrier` = '.(int)$original_id
            );
            
            foreach ($tax_rules as $tax_rule) {
                $db->insert('carrier_tax_rules_group_shop', array(
                    'id_carrier' => (int)$new_id,
                    'id_tax_rules_group' => (int)$tax_rule['id_tax_rules_group'],
                    'id_shop' => (int)$tax_rule['id_shop']
                ), false, true, Db::INSERT_IGNORE);
            }
            
        } catch (Exception $e) {
            // Si hay algún error, continuar con el proceso
            error_log('Error duplicando datos del carrier: ' . $e->getMessage());
        }
    }

private function uploadLogo($id_carrier)
    {
        $logo_dir = _PS_SHIP_IMG_DIR_;
        
        if (!file_exists($logo_dir)) {
            mkdir($logo_dir, 0777, true);
        }

        // Verificar que el archivo existe y es válido
        if (!isset($_FILES['logo']['tmp_name']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
            return false;
        }

        $tmp_name = $_FILES['logo']['tmp_name'];
        $name = $_FILES['logo']['name'];
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array($extension, $allowed_extensions)) {
            return false;
        }

        // PrestaShop usa .jpg como extensión estándar para logos de carriers
        $filename = $id_carrier . '.jpg';
        $destination = $logo_dir . $filename;

        if (!move_uploaded_file($tmp_name, $destination)) {
            return false;
        }
        
        return true;
    }

    private function copyLogo($original_id, $new_id)
    {
        $logo_dir = _PS_SHIP_IMG_DIR_;
        $extensions = array('jpg', 'jpeg', 'png', 'gif');
        
        foreach ($extensions as $ext) {
            $original_file = $logo_dir . $original_id . '.' . $ext;
            if (file_exists($original_file)) {
                // PrestaShop usa .jpg como extensión estándar
                $new_file = $logo_dir . $new_id . '.jpg';
                copy($original_file, $new_file);
                break;
            }
        }
    }
}
