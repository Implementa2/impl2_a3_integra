<?php
/**
 * 2007-2025 PrestaShop and contributors
 *
 * NOTICE OF LICENSE
 * ...
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;

class Impl2_A3_Integra extends Module
{
    public function __construct()
    {
        $this->name = 'impl2_a3_integra';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Impl2';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Integración con a3ERP');
        $this->description = $this->l('Sincroniza PrestaShop con el ERP a3ERP (productos, categorías, clientes, precios y pedidos).');
    }

    public function install(): bool
    {
        return parent::install() && $this->installDatabase() && $this->registerHooks();
    }

    public function uninstall(): bool
    {
        return $this->uninstallDatabase() && parent::uninstall();
    }

    /**
     * El método getContent redirige al controlador Symfony de configuración:contentReference[oaicite:11]{index=11}.
     */
    public function getContent()
    {
        $route = $this->context->getTranslator()->trans(
            '', [], 'Admin.Notifications.Success'
        );
        // Genera la URL de la ruta configurada en config/routes.yml
        $url = $this->get('router')->generate('impl2_a3_integra_config');
        Tools::redirectAdmin($url);
    }

    /**
     * Crea las tablas necesarias en la instalación.
     */
    public function installDatabase(): bool
    {
        $queries = [];

        // Tabla de errores pendientes
        $queries[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'a3erp_pending_errors` (
            `id_error` INT(11) NOT NULL AUTO_INCREMENT,
            `object_type` VARCHAR(50) NOT NULL,
            `object_id` INT(11) NOT NULL,
            `error_message` TEXT NOT NULL,
            `retry_count` INT(3) NOT NULL DEFAULT 0,
            `last_attempt` DATETIME DEFAULT NULL,
            `shop_id` INT(11) NOT NULL,
            PRIMARY KEY (`id_error`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        // Cola de sincronización (tareaj: tipo-entidad y ID por tienda)
        $queries[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'a3erp_sync_queue` (
            `id_queue` INT(11) NOT NULL AUTO_INCREMENT,
            `entity_type` VARCHAR(50) NOT NULL,
            `entity_id` INT(11) NOT NULL,
            `shop_id` INT(11) NOT NULL,
            `attempts` INT(3) NOT NULL DEFAULT 0,
            `available_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_queue`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }
        return true;
    }

    private function registerHooks(): bool
    {
        $hooks = [
            'actionProductAdd',
            'actionProductUpdate',
            'actionProductDelete',
            'actionProductPriceUpdate', // precio
            'actionCategoryAdd',
            'actionCategoryUpdate',
            'actionCategoryDelete',
            'actionUpdateQuantity', // stock
            'actionCustomerAdd',
            'actionCustomerUpdate',
            'actionCustomerDelete',
            'actionValidateOrder',
            'actionOrderStatusPostUpdate',
        ];
        foreach ($hooks as $hook) {
            if (!parent::registerHook($hook)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Elimina las tablas al desinstalar el módulo.
     */
    public function uninstallDatabase(): bool
    {
        $queries = [
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'a3erp_pending_errors`',
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'a3erp_sync_queue`'
        ];
        $result = true;
        foreach ($queries as $sql) {
            $result &= Db::getInstance()->execute($sql);
        }
        return $result;
    }

    // Hooks implementation
    public function hookActionProductAdd(array $params)    { $this->enqueue('product', (int)$params['id_product'], 'create'); }
    public function hookActionProductUpdate(array $params) { $this->enqueue('product', (int)$params['id_product'], 'update'); }
    public function hookActionProductDelete(array $params) { $this->enqueue('product', (int)$params['id_product'], 'delete'); }
    public function hookActionProductPriceUpdate(array $params) { $this->enqueue('price', (int)$params['id_product'], 'update'); }
    public function hookActionCategoryAdd(array $params)   { $this->enqueue('category', (int)$params['id_category'], 'create'); }
    public function hookActionCategoryUpdate(array $params){ $this->enqueue('category', (int)$params['id_category'], 'update'); }
    public function hookActionCategoryDelete(array $params){ $this->enqueue('category', (int)$params['id_category'], 'delete'); }
    public function hookActionUpdateQuantity(array $params) { $this->enqueue('stock', (int)$params['id_product'], 'update'); }
    public function hookActionCustomerAdd(array $params)   { $this->enqueue('customer', (int)$params['newCustomer']->id, 'create'); }
    public function hookActionCustomerUpdate(array $params){ $this->enqueue('customer', (int)$params['customer']->id, 'update'); }
    public function hookActionCustomerDelete(array $params){ $this->enqueue('customer', (int)$params['customer']->id, 'delete'); }
    public function hookActionValidateOrder(array $params)  { $this->enqueue('order', (int)$params['order']->id, 'create'); }
    public function hookActionOrderStatusPostUpdate(array $params){ $this->enqueue('order', (int)$params['id_order'], 'update'); }

    /**
     * Decide si encolar o sincronizar al vuelo
     */
    private function enqueue(string $entity, int $id, string $action): void
    {
        $immediate = (bool) Configuration::get('A3_SYNC_IMMEDIATE');
        if ($immediate) {
            $this->get('implementa2.a3_integra.sync_service')->syncSingle($entity, $id, $action);
        } else {
            $this->get('implementa2.a3_integra.queue_service')->enqueue($entity, $id, $action);
        }
    }

}
