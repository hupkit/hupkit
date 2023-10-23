<?php

declare(strict_types=1);

/*
 * This file is part of the HuPKit package.
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
use HubKit\Service\Git\GitTempRepository;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\VarExporter\VarExporter;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class InitConfigHandler extends ConfigBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        Filesystem $filesystem,
        GitTempRepository $tempRepository,
        private readonly CliProcess $process,
    ) {
        parent::__construct($style, $git, $github, $config, $filesystem, $tempRepository);
    }

    public function handle(Args $args, IO $io): int
    {
        $this->git->guardWorkingTreeReady();
        $this->informationHeader();

        $this->style->title('Configuration Set-up');

        if ($this->git->branchExists('_hubkit')) {
            $this->ensureRemoteIsNotDiverged();

            throw new \RuntimeException('The "_hubkit" branch already exists. Run `edit-config` instead.');
        }

        if ($this->git->remoteBranchExists('upstream', '_hubkit')) {
            throw new \RuntimeException(
                'The "_hubkit" branch exists remote, but the branch was not found locally.' . \PHP_EOL .
                'Run the "sync-config" command to pull-in the remote branch.' . \PHP_EOL
            );
        }

        $activeBranch = $this->git->getActiveBranchName();

        $this->createHkBranch($activeBranch);
        $this->createConfigFile();

        $this->style->success([
            'The _hubkit configuration branch was created, edit the config.php file with your favorite editor.',
            'Make sure to add to and commit once you are done.',
            sprintf('After you are done run `git checkout %s`.', $activeBranch),
            'And finally run the `sync-config` command to push the configuration to the upstream repository.',
        ]);

        return 0;
    }

    private function createHkBranch(string $activeBranch): void
    {
        $this->style->note('Generating empty "_hubkit" branch');

        $this->process->mustRun(['git', 'checkout', '--orphan', '_hubkit']);
        $this->process->mustRun(['git', 'rm', '-rf', '.']);

        if ($this->filesystem->fileExists('./config.php')) {
            throw new \RuntimeException('The config.php file already exists, cannot safely continue, either (temporarily) move or rename this file.');
        }

        // Do this prior to .gitignore as the file likely already exist.
        $this->mirrorHubKitDirectory($activeBranch);

        try {
            $this->process->mustRun(Process::fromShellCommandline('git show ' . $activeBranch . ':./.gitignore > .gitignore'));
            $this->process->mustRun(['git', 'add', '.gitignore']);
        } catch (\Exception $e) {
            $this->style->warning('Unable to automatically add .gitignore. Error: ' . $e->getMessage());
        }
    }

    private function mirrorHubKitDirectory(string $activeBranch): void
    {
        $tempDirectory = $this->tempRepository->getLocal($this->filesystem->getCwd(), $activeBranch);

        if (! $this->filesystem->fileExists($tempDirectory . '/.hubkit')) {
            return;
        }

        $this->filesystem->getFilesystem()->mirror($tempDirectory . '/.hubkit', $this->filesystem->getCwd(), options: ['copy_on_windows' => true]);

        $this->style->info([
            'The .hubkit directory was found and it\'s files copied to the _hubkit configuration branch.',
            'Make sure to `git add` these files manually.',
        ]);
    }

    private function createConfigFile(): void
    {
        $config = $this->config->getForRepository(
            $host = $this->github->getHostname(),
            $repository = sprintf('%s/%s', $this->github->getOrganization(), $this->github->getRepository())
        );
        $config['host'] = $host;
        $config['repository'] = $repository;
        $config['schema_version'] = 2;

        $configStr = VarExporter::export($config);
        $this->filesystem->dumpFile(
            './config.php',
            <<<CONF
                <?php

                // See https://hupkit.github.io/hupkit/config.html

                return {$configStr};

                CONF
        );

        try {
            $this->process->mustRun(['git', 'add', 'config.php']);
        } catch (ProcessFailedException $e) {
            $this->style->warning($e->getMessage());
        }
    }
}
