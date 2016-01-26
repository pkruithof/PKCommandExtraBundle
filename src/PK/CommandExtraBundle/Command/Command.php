<?php

namespace PK\CommandExtraBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\NullHandler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Peter Kruithof <pkruithof@gmail.com>
 */
abstract class Command extends ContainerAwareCommand
{
    /**
     * @var bool
     */
    private $singleProcessed = false;

    /**
     * @var array
     */
    private $disabledLoggers = [];

    /**
     * @var array
     */
    private $summaries = [
        'time' => true,
        'memory' => false,
    ];

    /**
     * @inheritdoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $timeStart = new \DateTime();
        $memStart = memory_get_usage();

        if ($this->isSummarized('time')) {
            $output->writeln(sprintf(
                '[%s] <info>%s</info> - started',
                $timeStart->format('Y-m-d H:i:s'),
                $this->getName()
            ));
        }

        $statusCode = parent::run($input, $output);

        $timeEnd = new \DateTime();

        if ($this->isSummarized('time')) {
            $diff = $timeStart->diff($timeEnd);

            $output->writeln(sprintf(
                '[%s] <info>%s</info> - ended in <info>%s</info>',
                $timeEnd->format('Y-m-d H:i:s'),
                $this->getName(),
                $diff->format('%h hours, %i minutes, %s seconds')
            ));
        }

        if ($this->isSummarized('memory')) {
            $memEnd = memory_get_usage();
            $peak = memory_get_peak_usage();

            $output->writeln(sprintf(
                '[%s] Consumed <info>%s</info> (peak: <info>%s</info>)',
                $timeEnd->format('Y-m-d H:i:s'),
                $this->formatBytes($memEnd - $memStart),
                $this->formatBytes($peak)
            ));
        }

        return $statusCode;
    }

    /**
     * @param string $serviceId
     *
     * @return object
     */
    protected function get($serviceId)
    {
        return $this->getContainer()->get($serviceId);
    }

    /**
     * @param string $name The object manager name (null for the default one).
     *
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    protected function getDoctrineManager($name = null)
    {
        return $this->get('doctrine')->getManager($name);
    }

    /**
     * @return string
     */
    protected function getPidFile()
    {
        return sprintf(
            '%s/%s.pid',
            $this->getContainer()->getParameter('pk.command_extra.pid_dir'),
            str_replace(':', '.', $this->getName())
        );
    }

    /**
     * @return bool
     */
    protected function isSummarized($type)
    {
        return array_key_exists($type, $this->summaries) && $this->summaries[$type] === true;
    }

    /**
     * Disables common loggers during command. This can be useful when working
     * with large amounts of data.
     *
     * @param array $extraLoggers Optional extra loggers you want disabled
     *
     * @return $this
     */
    protected function disableLoggers(array $extraLoggers = [])
    {
        $this->disabledLoggers = array_merge(['monolog.logger.doctrine', 'logger'], $extraLoggers);

        return $this;
    }

    /**
     * If set to true, the command can only run by one process at a time.
     */
    protected function isSingleProcessed()
    {
        $this->singleProcessed = true;

        return $this;
    }

    /**
     * @param array $definition
     *
     * @return $this
     */
    protected function setSummarizeDefinition(array $definition)
    {
        foreach ($definition as $type => $bool) {
            if (!array_key_exists($type, $this->summaries)) {
                throw new \InvalidArgumentException(sprintf('Summary "%s" is not defined'));
            }

            $this->summaries[$type] = (boolean) $bool;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        if ($this->singleProcessed) {
            $this->makeCommandSingleProcessed($output);
        }

        foreach ($this->disabledLoggers as $service) {
            $this->disableLogger($service);
        }
    }

    /**
     * Adds NullHandler to the major logs to prevent them from further handling.
     *
     * @param string $serviceId A service id
     */
    protected function disableLogger($serviceId)
    {
        if ($this->getContainer()->has($serviceId)) {
            $logger = $this->get($serviceId);
            $logger->pushHandler(new NullHandler());
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function makeCommandSingleProcessed(OutputInterface $output)
    {
        // we need posix support for this
        if (!extension_loaded('posix')) {
            throw new \LogicException('The posix extension is required for single-process commands');
        }

        $pidFile = $this->getPidFile();

        // make sure directory exists
        $pidDir = dirname($pidFile);
        if (!file_exists($pidDir)) {
            $this->get('filesystem')->mkdir($pidDir);
        }

        // check if pidfile exists
        if (file_exists($pidFile) && ($pid = intval(trim(file_get_contents($pidFile))))) {

            // send 0 kill signal to check if the process is still running
            // also check if we got a EPERM error, if we do not own that process
            // see http://www.php.net/manual/en/function.posix-kill.php#82560
            if (posix_kill($pid, 0) || (posix_get_last_error() === 1)) {
                $name = $this->getName();
                $this->setCode(function(InputInterface $input, OutputInterface $output) use ($pid, $name) {
                    $output->writeln(sprintf('<info>%s</info> is still running [<info>pid %s</info>], exiting.', $name, $pid));

                    return 0;
                });

                return;
            }
        }

        // no running process, make sure pidfile is removed when shutting down
        try {
            file_put_contents($pidFile, posix_getpid());
            register_shutdown_function(function() use ($output, $pidFile) {
                try {
                    unlink($pidFile);
                } catch (\Exception $e) {
                    $output->writeln(sprintf('<error>Cannot remove lock file "%s": %s</error>', $pidFile, $e->getMessage()));

                    return 1;
                }
            });
        } catch (\Exception $e) {
            $this->setCode(function(InputInterface $input, OutputInterface $output) use ($pidFile) {
                $output->writeln(sprintf('<error>Cannot write lock file "%s"</error>', $pidFile));

                return 1;
            });
        }
    }

    /**
     * @see Monolog\Processor\MemoryProcessor#formatBytes
     */
    protected function formatBytes($bytes)
    {
        $bytes = (int) $bytes;

        if ($bytes > 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2).' MB';
        } elseif ($bytes > 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
