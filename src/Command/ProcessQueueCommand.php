<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Command;

use Implementa2\A3Integra\Service\QueueService;
use Implementa2\A3Integra\Service\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessQueueCommand extends Command
{
    protected static $defaultName = 'impl2a3:sync:process_queue';
    private $queueService;
    private $syncService;

    public function __construct(QueueService $queueService, SyncService $syncService)
    {
        parent::__construct();
        $this->queueService = $queueService;
        $this->syncService = $syncService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Procesa la cola de sincronización con a3ERP.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Procesando cola de sincronización...');
        while ($job = $this->queueService->getNextJob()) {
            $jobId = $job['id_queue'];
            $type = $job['entity_type'];
            $entityId = $job['entity_id'];
            $shopId = $job['shop_id'];
            $output->writeln(" - Procesando tarea #$jobId ({$type} - ID {$entityId}, tienda {$shopId})");

            try {
                // Procesa la tarea según su tipo (por ejemplo, página de productos, categorías, etc.)
                $this->syncService->processJob($job);
                // Si fue exitosa, eliminar de la cola
                $this->queueService->removeJob((int)$jobId);
                $output->writeln("   ✓ Procesado correctamente.");
            } catch (\Exception $e) {
                $output->writeln("   <error>Error: {$e->getMessage()}</error>");
                // Loguear error y reprogramar la tarea con backoff exponencial
                $this->queueService->failJob((int)$jobId, $e->getMessage());
            }
        }
        $output->writeln('<info>Fin de la cola de sincronización.</info>');
        return Command::SUCCESS;
    }
}
