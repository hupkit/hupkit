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

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Preset\Presets\Psr4Preset;

return Architecture::define()
    // src/ root classes are split by responsibility, as they share a single directory
    ->layer('Config', [
        'src/BranchConfig.php',
        'src/Config.php',
        'src/RemotesConfigParser.php',
    ])
    ->layer('Util', 'src/StringUtil.php')
    ->layer('Container', [
        'src/ConfigFactory.php',
        'src/Container.php',
    ])
    ->layer('Exception', 'src/Exception/')
    ->layer('Service', 'src/Service/')
    ->layer('Helper', 'src/Helper/')
    // Handlers are their own layer, so they are carved out of Cli
    ->layer('Cli', 'src/Cli/', 'src/Cli/Handler/')
    ->layer('Handler', 'src/Cli/Handler/')
    ->ruleset([
        'Config' => [],
        'Util' => [],
        'Exception' => [],
        'Service' => ['Config', 'Exception', 'Util'],
        'Helper' => ['Config', 'Service', 'Util'],
        'Container' => ['Config', 'Service'],
        // Handlers get their services injected, they never reach for the Container
        'Handler' => ['Cli', 'Config', 'Helper', 'Service', 'Util'],
        // Cli wires the application: it builds the Container and the Handlers
        'Cli' => ['Container', 'Handler', 'Helper'],
    ])
    ->skip([
        Psr4Preset::CLASSES_MUST_MATCH_COMPOSER => [
            __DIR__ . '/tests/Handler/MergeHandlerTest.php',
            __DIR__ . '/tests/Handler/SwitchBaseHandlerTest.php',
        ],

        __DIR__ . '/tests/Service/Fixtures',
    ])
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY(), Preset::YAGNI());
