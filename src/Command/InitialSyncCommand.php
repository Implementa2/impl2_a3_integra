<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Command;

use Implementa2\A3Integra\Service\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitialSyncCommand extends Command
{
    protected static $defaultName = 'impl2a3:sync:initial';
    private $syncService;

    public function __construct(SyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Sincronización inicial de productos, categorías, clientes y pedidos desde a3ERP.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Iniciando sincronización inicial con a3ERP...');

        // Se puede sincronizar para cada tienda configurada
        $shops = json_decode(\Configuration::get('A3ERP_SHOPS'), true) ?: [];
        if (empty($shops)) {
            $output->writeln('<error>No hay tiendas configuradas para sincronizar.</error>');
            return Command::FAILURE;
        }

        foreach ($shops as $shopId) {
            $output->writeln(" * Preparando datos de tienda ID: $shopId");
            $this->syncService->queueInitialSyncTasks((int)$shopId);
        }
        $output->writeln('<info>Tareas de sincronización inicial creadas.</info>');
        return Command::SUCCESS;
    }
}
