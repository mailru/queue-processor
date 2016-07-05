<?php

namespace MailRu\QueueProcessor\Config;

use RuntimeException;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
abstract class AbstractFileConfigReader implements ConfigReaderInterface
{
    private $configPath;

    /**
     * @return string
     *
     * @throws RuntimeException
     */
    public function getConfigPath()
    {
        if ($this->configPath === null) {
            throw new RuntimeException('You should specify configPath');
        }
        if (!is_file($this->configPath)) {
            throw new RuntimeException("Config isn't file in {$this->configPath}");
        }
        if (!is_readable($this->configPath)) {
            throw new RuntimeException("Config file {$this->configPath} isn't readable");
        }

        return $this->configPath;
    }

    /**
     * @param string $configPath
     */
    public function setConfigPath($configPath)
    {
        $this->configPath = (string) $configPath;
    }
}
