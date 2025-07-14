<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Service;

use Implementa2\A3Integra\Repository\ErrorRepository;
use Category;
use Product;
use Customer;
use Address;
use Cart;
use Order;
use OrderDetail;
use Context;

class SyncService
{
    private $apiClient;
    private $queueService;
    private $errorRepository;

    public function __construct(A3ERPClient $client, QueueService $queueService, ErrorRepository $errorRepository)
    {
        $this->apiClient = $client;
        $this->queueService = $queueService;
        $this->errorRepository = $errorRepository;
    }

    /**
     * Crea tareas iniciales en la cola para sincronizar todos los datos de la tienda dada.
     */
    public function queueInitialSyncTasks(int $shopId): void
    {
        // Ejemplo: sincronizar productos
        $count = $this->apiClient->getProductsCount();
        $limit = 100;
        $pages = ceil($count / $limit);
        for ($page = 1; $page <= $pages; $page++) {
            $this->queueService->addJob('products_page', $page, $shopId);
        }
        // Similar para categorías
        $countCat = $this->apiClient->getCategoriesCount();
        $pagesCat = ceil($countCat / $limit);
        for ($page = 1; $page <= $pagesCat; $page++) {
            $this->queueService->addJob('categories_page', $page, $shopId);
        }
        // Clientes
        $countCus = $this->apiClient->getCustomersCount();
        $pagesCus = ceil($countCus / $limit);
        for ($page = 1; $page <= $pagesCus; $page++) {
            $this->queueService->addJob('customers_page', $page, $shopId);
        }
        // Pedidos
        $countOrd = $this->apiClient->getOrdersCount();
        $pagesOrd = ceil($countOrd / $limit);
        for ($page = 1; $page <= $pagesOrd; $page++) {
            $this->queueService->addJob('orders_page', $page, $shopId);
        }
    }

    /**
     * Procesa una tarea de la cola según su tipo.
     * Lanzo excepciones en caso de error para que el comando las capture y reprograme.
     */
    public function processJob(array $job): void
    {
        $type = $job['entity_type'];
        $id = (int)$job['entity_id'];
        $shopId = (int)$job['shop_id'];

        Context::getContext()->shop = new \Shop($shopId); // cambiar contexto de tienda

        switch ($type) {
            case 'products_page':
                $this->syncProductsPage($id);
                break;
            case 'categories_page':
                $this->syncCategoriesPage($id);
                break;
            case 'customers_page':
                $this->syncCustomersPage($id);
                break;
            case 'orders_page':
                $this->syncOrdersPage($id);
                break;
            default:
                throw new \Exception("Tipo de tarea desconocido: $type");
        }
    }

    /**
     * Sincroniza una página de productos (creación/actualización en PS).
     */
    private function syncProductsPage(int $page): void
    {
        $limit = 100;
        $products = $this->apiClient->getProducts($page, $limit);
        foreach ($products as $prodData) {
            try {
                $product = new Product((int)$prodData['id_product']);
                // Si existe, actualizar; si no, crear nuevo
                if (!Validate::isLoadedObject($product)) {
                    $product = new Product();
                    // Asignar datos básicos
                    $product->reference = pSQL($prodData['reference']);
                    $product->price = (float)$prodData['price'];
                    $product->active = 1;
                }
                // Multilenguaje: asignar nombre por idioma
                foreach ($prodData['name'] as $langIso => $name) {
                    $idLang = Language::getIdByIso($langIso);
                    if ($idLang) {
                        $product->name[$idLang] = pSQL($name);
                    }
                }
                // Asociar categoría (por simplicidad, se omite chequear categoría existente)
                $product->id_category_default = (int)$prodData['category_id'];
                $product->add(); // o update() si existe
            } catch (\Exception $e) {
                // Registrar error y lanzar para reintentar
                $this->errorRepository->logError('product', (int)$prodData['id_product'], $e->getMessage(), (int)$this->context->shop->id);
                throw $e;
            }
        }
        // Throttling sencillo: dormir 1s para no saturar la API
        sleep(1);
    }

    /**
     * Similarmente para categorías.
     */
    private function syncCategoriesPage(int $page): void
    {
        $limit = 50;
        $categories = $this->apiClient->getCategories($page, $limit);
        foreach ($categories as $catData) {
            try {
                $cat = new Category((int)$catData['id_category'], $this->context->language->id);
                if (!Validate::isLoadedObject($cat)) {
                    $cat = new Category();
                    $cat->active = 1;
                }
                $cat->id_parent = (int)$catData['parent_id'];
                foreach ($catData['name'] as $langIso => $name) {
                    $idLang = Language::getIdByIso($langIso);
                    if ($idLang) {
                        $cat->name[$idLang] = pSQL($name);
                    }
                }
                $cat->add();
            } catch (\Exception $e) {
                $this->errorRepository->logError('category', (int)$catData['id_category'], $e->getMessage(), (int)$this->context->shop->id);
                throw $e;
            }
        }
        sleep(1);
    }

    /**
     * Sincroniza una página de clientes (crea Customer y Address).
     */
    private function syncCustomersPage(int $page): void
    {
        $limit = 50;
        $customers = $this->apiClient->getCustomers($page, $limit);
        foreach ($customers as $cusData) {
            try {
                $customer = new Customer((int)$cusData['id_customer']);
                if (!Validate::isLoadedObject($customer)) {
                    $customer = new Customer();
                    $customer->firstname = pSQL($cusData['firstname']);
                    $customer->lastname = pSQL($cusData['lastname']);
                    $customer->email = pSQL($cusData['email']);
                    $customer->active = 1;
                    $customer->add();
                    // Crear una dirección por defecto
                    $address = new Address();
                    $address->id_customer = (int)$customer->id;
                    $address->firstname = pSQL($cusData['firstname']);
                    $address->lastname = pSQL($cusData['lastname']);
                    $address->address1 = pSQL($cusData['address1']);
                    $address->city = pSQL($cusData['city']);
                    $address->postcode = pSQL($cusData['postcode']);
                    $address->id_country = (int)$cusData['country_id'];
                    $address->alias = 'Dirección a3ERP';
                    $address->add();
                }
            } catch (\Exception $e) {
                $this->errorRepository->logError('customer', (int)$cusData['id_customer'], $e->getMessage(), (int)$this->context->shop->id);
                throw $e;
            }
        }
        sleep(1);
    }

    /**
     * Sincroniza una página de pedidos (cruda, solo ejemplo simplificado).
     */
    private function syncOrdersPage(int $page): void
    {
        $limit = 20;
        $orders = $this->apiClient->getOrders($page, $limit);
        foreach ($orders as $ordData) {
            try {
                // Aquí se crearía Order, Cart, OrderDetail, etc.
                $order = new Order();
                $order->id_customer = (int)$ordData['customer_id'];
                // ... asignar campos básicos ...
                $order->add();
                // Después agregaría detalles de pedido (OrderDetail) por cada producto.
            } catch (\Exception $e) {
                $this->errorRepository->logError('order', (int)$ordData['id_order'], $e->getMessage(), (int)$this->context->shop->id);
                throw $e;
            }
        }
        sleep(1);
    }
}
