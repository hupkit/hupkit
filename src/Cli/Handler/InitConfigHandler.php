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

namespace HubKit\Cli\Handler;

use HubKit\Config;
use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\Git\GitFileReader;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\VarExporter\VarExporter;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class InitConfigHandler extends GitBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        private Filesystem $filesystem,
        private CliProcess $process,
        private GitFileReader $gitFileReader
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args, IO $io): int
    {
        $this->git->guardWorkingTreeReady();
        $this->informationHeader();

        $this->style->title('Configuration Set-up');

        if ($this->git->branchExists('_hubkit')) {
            $this->ensureRemoteIsNotDiverged();

            if ($this->gitFileReader->fileExists('_hubkit', 'config.php')) {
                $this->style->error('The config.php file already exists in the "_hubkit" branch.');

                if ($this->style->confirm('Do you want to checkout the "_hubkit" branch instead?', false)) {
                    $this->git->checkout('_hubkit');

                    return 0;
                }

                return 1;
            }

            $this->git->checkout('_hubkit');
        } elseif ($this->gitFileReader->fileExistsAtRemote('upstream', '_hubkit', 'config.php')) {
            throw new \RuntimeException(
                'The "_hubkit" branch exists remote, but the branch was not found locally.' . \PHP_EOL .
                'Run the "sync-config" command to pull-in the remote branch.' . \PHP_EOL
            );
        }

        $this->createHkBranch();

        $config = VarExporter::export($this->config->getForRepository(
            $this->github->getHostname(),
            sprintf('%s/%s', $this->github->getOrganization(), $this->github->getRepository())
        ));

        $this->filesystem->dumpFile(
            './config.php',
            <<<CONF
                <?php

                // See https://www.park-manager.com/hubkit/config.html

                return {$config};

                CONF
        );

        $this->process->mustRun(['git', 'add', 'config.php']);

        $this->style->success([
            'Config file was created, edit the config.php file with your favorite editor.',
            'Make sure to add the `git add config.php` and commit once you are done.',
        ]);

        return 0;
    }

    private function ensureRemoteIsNotDiverged(): void
    {
        $status = $this->git->getRemoteDiffStatus('upstream', '_hubkit');

        if ($status !== Git::STATUS_UP_TO_DATE && $status !== Git::STATUS_NEED_PUSH) {
            throw new \RuntimeException(
                'The remote "_hubkit" branch and local branch have diverged. Status: ' . $status .
                '.  Run the "sync-config" command to resolve this problem.'
            );
        }
    }

    private function createHkBranch(): void
    {
        if ($this->git->branchExists('_hubkit')) {
            return;
        }

        $this->style->note('Generating empty "_hubkit" branch');

        $this->process->mustRun(['git', 'checkout', '--orphan', '_hubkit']);
        $this->process->mustRun(['git', 'rm', '-rf', '.']);

        try {
            $branch = $this->git->getPrimaryBranch();

            $this->process->mustRun(Process::fromShellCommandline('git show ' . $branch . ':./.gitignore > .gitignore'));
            $this->process->mustRun(['git', 'add', '.gitignore']);
        } catch (\Exception $e) {
            $this->style->warning('Unable to automatically add .gitignore from primary branch: ' . $e->getMessage());
        }
    }
}
