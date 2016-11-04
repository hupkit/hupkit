<?php

declare(strict_types = 1);

namespace HubKit\Helper;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

final class StatusTableRenderer
{
    const STATUS_LABELS = [
        'success' => '<fg=green>OK</>',
        'failure' => '<fg=red>FAIL</>',
        'pending' => '<fg=yellow>Pendind</>',
        'error' => '<fg=red>Error</>',
    ];

    public static function renderTable(OutputInterface $output, array $rows)
    {
        $table = new Table($output);
        $table->getStyle()
            ->setHorizontalBorderChar('-')
            ->setVerticalBorderChar('')
            ->setCrossingChar('')
            ->setCellRowContentFormat('  %s  ');

        $table->setHeaders(['Item', 'Status', 'Details']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
    }

    public static function renderLabel(string $status)
    {
        return self::STATUS_LABELS[$status];
    }
}
