<?php

declare(strict_types=1);

/*
 * This file is part of the HubKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Helper;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class StatusTable
{
    private const STATUS_LABELS = [
        'success' => '<fg=green>OK</>',
        'failure' => '<fg=red>FAIL</>',
        'pending' => '<fg=yellow>Pending</>',
        'warning' => '<fg=yellow>Warning</>',
        'skipped' => '<fg=cyan>Skipped</>',
        'cancelled' => '<fg=cyan>Cancelled</>',
        'error' => '<fg=red>Error</>',
        'timed_out' => '<fg=red>Timed out</>',
        'action_required' => '<fg=red>Action required</>',
    ];

    public const STATUS_PENDING = 'pending';

    private $output;
    private $rows = [];
    private $statuses = [];

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function render()
    {
        $table = new Table($this->output);
        $table->getStyle()
            ->setCrossingChars(
                cross: '',
                topLeft: '',
                topMid: '',
                topRight: '',
                midRight: '',
                bottomRight: '',
                bottomMid: '',
                bottomLeft: '',
                midLeft: '',
            )
            ->setHorizontalBorderChars('-', '-')
            ->setVerticalBorderChars(outside: '', inside: '')
            ->setCellRowContentFormat('  %s  ');

        $table->setHeaders(['Item', 'Status', 'Details']);
        $table->setRows($this->rows);
        $table->render();

        $this->output->writeln('');
    }

    public function hasStatus(string $status)
    {
        return isset($this->statuses[$status]);
    }

    // make other methods none static
    public function addRow(string $label, string $status, ?string $message = null)
    {
        $this->rows[] = [$label, self::STATUS_LABELS[$status], wordwrap((string) $message, 38)];
        $this->statuses[$status] = true;
    }

    public function hasFailureStatus(): bool
    {
        $statusses = ['error', 'pending', 'failure', 'action_required', 'timed_out'];

        foreach ($statusses as $status) {
            if ($this->hasStatus($status)) {
                return true;
            }
        }

        return false;
    }
}
