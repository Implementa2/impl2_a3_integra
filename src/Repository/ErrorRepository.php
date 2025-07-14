<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Repository;

use Db;
use Tools;

class ErrorRepository
{
    /**
     * Agrega un error pendiente a la tabla.
     */
    public function logError(string $type, int $entityId, string $message, int $shopId): void
    {
        Db::getInstance()->insert('a3erp_pending_errors', [
            'object_type'   => pSQL($type),
            'object_id'     => (int)$entityId,
            'error_message' => pSQL($message),
            'retry_count'   => 0,
            'last_attempt'  => date('Y-m-d H:i:s'),
            'shop_id'       => (int)$shopId
        ]);
        // Envío de alerta por email al administrador de la tienda
        $to = \Configuration::get('PS_SHOP_EMAIL');
        $subject = 'Error en sincronización a3ERP';
        $content = "Ha ocurrido un error sincronizando {$type} con ID {$entityId}: {$message}";
        @mail($to, $subject, $content);
    }
}
