<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Console;

use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * The Preflight console application. `run` is the default command, so `preflight` with no
 * arguments runs the checks.
 *
 * The default command also accepts positional path arguments. Because Symfony treats the
 * first bare token as a command name, the `bin/preflight` entry point inserts `run` when
 * the first argument is a path rather than a known command (see {@see isKnownCommand()}).
 */
final class Application extends SymfonyApplication
{
    public const string DEFAULT_COMMAND = 'run';

    public function __construct()
    {
        parent::__construct('Preflight', '0.1.0');

        $this->addCommand(new RunCommand());
        $this->addCommand(new DoctorCommand());
        $this->addCommand(new ListStepsCommand());
        $this->addCommand(new InitCommand());
        $this->addCommand(new InstallCommand());
        $this->setDefaultCommand(self::DEFAULT_COMMAND);
    }

    /**
     * Whether a name resolves to a registered command — used by the entry point to decide
     * if a leading argument is a command or a path.
     */
    public function isKnownCommand(string $name): bool
    {
        return $this->has($name);
    }
}
