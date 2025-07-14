<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Service;

use Configuration;
use Tools;

class A3ERPClient
{
    private $apiUrl;

   
    public function __construct()
    {
        $raw = Configuration::get('A3ERP_API_URL');
        if (!$raw) {
            throw new \RuntimeException('A3ERP_API_URL no está configurado');
        }
        $this->apiUrl = rtrim($raw, '/');
    }

    /**
     * Realiza una petición GET a la API de a3ERP y retorna el JSON decodificado.
     */
    private function get(string $endpoint, array $params = []): array
    {
        $url = $this->apiUrl . $endpoint . '?' . http_build_query($params);
        $response = Tools::file_get_contents($url);
        if (!$response) {
            throw new \Exception("No se pudo conectar con la API a3ERP en $url");
        }
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON inválido recibido de a3ERP');
        }
        return $data;
    }

    /**
     * Obtiene productos paginados de a3ERP.
     */
    public function getProducts(int $page, int $limit): array
    {
        return $this->get('/products', ['page' => $page, 'limit' => $limit]);
    }

    /**
     * Obtiene la cantidad total de productos (para paginar).
     */
    public function getProductsCount(): int
    {
        $data = $this->get('/products/count');
        return (int)($data['count'] ?? 0);
    }

    // Métodos análogos para categorías, clientes, precios, pedidos, etc.

    public function getCategories(int $page, int $limit): array
    {
        return $this->get('/categories', ['page' => $page, 'limit' => $limit]);
    }
    public function getCategoriesCount(): int
    {
        $data = $this->get('/categories/count');
        return (int)($data['count'] ?? 0);
    }

    public function getCustomers(int $page, int $limit): array
    {
        return $this->get('/customers', ['page' => $page, 'limit' => $limit]);
    }
    public function getCustomersCount(): int
    {
        $data = $this->get('/customers/count');
        return (int)($data['count'] ?? 0);
    }

    public function getOrders(int $page, int $limit): array
    {
        return $this->get('/orders', ['page' => $page, 'limit' => $limit]);
    }
    public function getOrdersCount(): int
    {
        $data = $this->get('/orders/count');
        return (int)($data['count'] ?? 0);
    }
}
