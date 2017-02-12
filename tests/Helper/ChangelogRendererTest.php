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

namespace HubKit\Tests\Helper;

use HubKit\Helper\ChangelogRenderer;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

final class ChangelogRendererTest extends TestCase
{
    const COMMITS = [
        [
            'sha' => '2bee3b83a9b0073497f37acd4f0920ef61945552',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'feature #93 Introduce a new API for ValuesBag (sstok)',
            'message' => 'This PR was merged into the master branch.

                Discussion
                ----------
            
                |Q            |A  |
                |---          |---|
                |Bug Fix?     |no |
                |New Feature? |yes|
                |BC Breaks?   |no |
                |Deprecations?|yes|
                |Fixed Tickets|   |
                |License      |MIT|
            
                Commits
                -------
            
                1b04532c8a09d9084abce36f8d9daf675f89eacc Introduce a new API for ValuesBag',
        ],
        [
            'sha' => 'd22220c0a97a5fc0ff4e0a0e595247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'minor #56 Clean up (sstok)',
            'message' => 'This PR was merged into the 1.0-dev branch.

                9b67df3871e871084d0ebbf1e0db639d552fc7eb commit 1',
        ],
        [
            'sha' => 'd2222010a97a5fc0ff4e0a0e595247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'feature #55 Great new architecture (sstok, someone)',
            'message' => 'This PR was merged into the 1.0-dev branch.
labels: deprecation , removed-deprecation

9b67df3871e871084d0ebbf1e0db639d552fc7eb commit 1',
        ],
        [
            'sha' => 'd2222010a97a5fc0ff4e0a0e595247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'refactor #52 Removed deprecated API (sstok)',
            'message' => 'This PR was merged into the 1.0-dev branch.
labels: removed-deprecation,bc-break

9b67df3871e871084d0abef1e0db639d552fc7e commit 2',
        ],
        [
            'sha' => 'd22220c0a97a666fc0ff4e0a0e5247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'Merge pull request #50 from sstok/docs-cleanup',
            'message' => 'testing #50 Diddly (sstok)',
        ],
    ];

    /** @var ObjectProphecy */
    private $git;
    /** @var ObjectProphecy */
    private $github;

    /** @before */
    public function setUpCommandHandler()
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->guardWorkingTreeReady()->willReturn(null);

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');
    }

    /** @test */
    public function it_renders_a_changelog_with_categories_excluding_empty()
    {
        $this->git->getLogBetweenCommits('base', 'head')->willReturn(self::COMMITS);

        self::assertEquals(<<<'LOG'
### Added
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)

### Changed
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)

### Deprecated
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)

### Removed
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)
LOG
, $this->getRenderer()->renderChangelogByCategories('base', 'head'));
    }

    /** @test */
    public function it_renders_a_changelog_with_categories_including_empty()
    {
        $this->git->getLogBetweenCommits('base', 'head')->willReturn(self::COMMITS);

        self::assertEquals(<<<'LOG'
### Security
- nothing

### Added
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)

### Changed
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)

### Deprecated
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)

### Removed
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)

### Fixed
- nothing
LOG
, $this->getRenderer()->renderChangelogByCategories('base', 'head', false));
    }

    /** @test */
    public function it_renders_a_changelog_without_categories()
    {
        $this->git->getLogBetweenCommits('base', 'head')->willReturn(self::COMMITS);

        self::assertEquals(<<<'LOG'
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)
LOG
, $this->getRenderer()->renderChangelogOneLine('base', 'head'));
    }

    private function getRenderer(): ChangelogRenderer
    {
        return new ChangelogRenderer($this->git->reveal(), $this->github->reveal());
    }
}
