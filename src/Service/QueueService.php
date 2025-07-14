<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Service;

use Db;
use DateTime;

class QueueService
{
    /**
     * Agrega una tarea a la cola.
     * @param string $type Tipo de tarea (p.ej. 'products_page')
     * @param int $entityId ID de página o entidad asociada
     * @param int $shopId ID de la tienda (multi-tienda)
     */
    public function addJob(string $type, int $entityId, int $shopId): void
    {
        Db::getInstance()->insert('a3erp_sync_queue', [
            'entity_type' => pSQL($type),
            'entity_id'   => (int)$entityId,
            'shop_id'     => (int)$shopId,
            'attempts'    => 0,
            'available_at'=> date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtiene la próxima tarea disponible (oldest) cuya available_at <= ahora.
     */
    public function getNextJob(): ?array
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'a3erp_sync_queue`
                WHERE available_at <= "'.pSQL($now).'"
                ORDER BY id_queue ASC
                LIMIT 1';
        $row = Db::getInstance()->getRow($sql);
        return $row ?: null;
    }

    /**
     * Elimina una tarea completada de la cola.
     */
    public function removeJob(int $jobId): void
    {
        Db::getInstance()->delete('a3erp_sync_queue', '`id_queue`='.(int)$jobId);
    }

    /**
     * Marca la tarea como fallida: incrementa el contador y reprograma con backoff exponencial.
     */
    public function failJob(int $jobId, string $errorMessage): void
    {
        $row = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'a3erp_sync_queue` WHERE id_queue='.(int)$jobId);
        if (!$row) {
            return;
        }
        $attempts = (int)$row['attempts'] + 1;
        // Backoff: espera = 2^attempts minutos
        $delayMin = pow(2, $attempts);
        $nextTime = (new DateTime())->modify("+{$delayMin} minutes")->format('Y-m-d H:i:s');
        Db::getInstance()->update('a3erp_sync_queue', [
            'attempts'    => $attempts,
            'available_at'=> $nextTime
        ], '`id_queue`='.(int)$jobId);
        // Registrar el error en pendiente
        Db::getInstance()->insert('a3erp_pending_errors', [
            'object_type'   => pSQL($row['entity_type']),
            'object_id'     => (int)$row['entity_id'],
            'error_message' => pSQL($errorMessage),
            'retry_count'   => $attempts,
            'last_attempt'  => date('Y-m-d H:i:s'),
            'shop_id'       => (int)$row['shop_id']
        ]);
    }
}
