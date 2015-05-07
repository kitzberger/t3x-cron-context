<?php

namespace Cron\CronContext;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Additional configuration loader (based on context)
 *
 * Examples:
 *
 * TYPO3_CONTEXT=Production
 *    -> typo3conf/AdditionalConfiguration/Production.php
 *
 * TYPO3_CONTEXT=Testing
 *    -> typo3conf/AdditionalConfiguration/Testing.php
 *
 * TYPO3_CONTEXT=Development
 *    -> typo3conf/AdditionalConfiguration/Development.php
 *
 * TYPO3_CONTEXT=Production/Staging
 *    -> typo3conf/AdditionalConfiguration/Production.php
 *    -> typo3conf/AdditionalConfiguration/Production/Staging.php
 *
 * TYPO3_CONTEXT=Production/Live
 *    -> typo3conf/AdditionalConfiguration/Production.php
 *    -> typo3conf/AdditionalConfiguration/Production/Live.php
 *
 * TYPO3_CONTEXT=Production/Live/Server4711
 *    -> typo3conf/AdditionalConfiguration/Production.php
 *    -> typo3conf/AdditionalConfiguration/Production/Live.php
 *    -> typo3conf/AdditionalConfiguration/Production/Live/Server4711.php
 *
 */
class ContextLoader {

    /**
     * Application context
     *
     * @var \TYPO3\CMS\Core\Core\ApplicationContext
     */
    protected $applicationContext;

    /**
     * Context list (reversed)
     *
     * @var array
     */
    protected $contextList = array();

    /**
     * Configuration path list (simple files)
     *
     * @var array
     */
    protected $confPathList = array();

    /**
     * Cache file (only set if cache is used)
     *
     * @var null|string
     */
    protected $cacheFile;

    /**
     * Construct
     */
    public function __construct() {
        define('CRON_TYPO3_ADDITIONALCONFIGURATION', 1);

        $this->applicationContext = GeneralUtility::getApplicationContext();
        $this->buildContextList();
    }

    /**
     * Use cache
     *
     * return $this;
     */
    public function useCache() {
        $this->cacheFile    = PATH_site . '/typo3temp/Cache/Code/cache_phpcode/cron_context_conf.php';
        return $this;
    }

    /**
     * Use cache if TYPO3_CONTEXT is production
     *
     * @return $this
     */
    public function useCacheInProduction() {
        if ($this->applicationContext->isProduction()) {
            $this->useCache();
        }
        return $this;
    }

    /**
     * Add path for automatic context loader
     *
     * @param string $path Path to file
     * @return $this
     */
    public function addContextConfiguration($path) {
        $this->confPathList['context'][] = $path;
        return $this;
    }

    /**
     * Add configuration to loader
     *
     * @param string $path Path to file
     * @return $this
     */
    public function addConfiguration($path) {
        $this->confPathList['file'][] = $path;
        return $this;
    }

    /**
     * Load configuration
     *
     * @return $this
     */
    public function loadConfiguration() {
        if (!$this->loadCache()) {
            $this->loadContextConfiguration();
            $this->loadFileConfiguration();
            $this->buildCache();
        }

        return $this;
    }

    /**
     * Build context list
     */
    protected function buildContextList() {
        $contextList = array();
        $currentContext = $this->applicationContext;
        do {
            $contextList[] = (string)$currentContext;
        } while ($currentContext = $currentContext->getParent());

        // Reverse list, general first (eg. PRODUCTION), then specific last (eg. SERVER)
        $this->contextList = array_reverse($contextList);
    }

    /**
     * Load from cache
     */
    protected function loadCache() {
        $ret = false;

        if ($this->cacheFile && file_exists($this->cacheFile)) {
            $conf = unserialize(file_get_contents($this->cacheFile));

            if (!empty($conf)) {
                $GLOBALS['TYPO3_CONF_VARS'] = $conf;
                $ret = true;
            }
        }

        return $ret;
    }

    /**
     * Build context config cache
     */
    protected function buildCache() {
        if ($this->cacheFile) {
            file_put_contents($this->cacheFile, serialize($GLOBALS['TYPO3_CONF_VARS']));
        }
    }

    /**
     * Load configuration based on current context
     */
    protected function loadContextConfiguration() {
        if (!empty($this->confPathList['context'])) {
            foreach ($this->confPathList['context'] as $path) {
                foreach ($this->contextList as $context) {
                    // Sanitize context name
                    $context = preg_replace('/[^-_a-zA-Z0-9\/]/', '', $context);

                    // Build config file name
                    $this->loadConfigurationFile($path . '/' . $context . '.php');
                }
            }
        }
    }

    /**
     * Load simple file configuration
     */
    protected function loadFileConfiguration() {
        if (!empty($this->confPathList['file'])) {
            foreach ($this->confPathList['file'] as $path) {
                $this->loadConfigurationFile($path);
            }
        }
    }

    /**
     * Load configuration file
     *
     * @param string $configurationFile Configuration file
     * @return $this
     */
    protected function loadConfigurationFile($configurationFile) {
        // Load config file
        if (file_exists($configurationFile)) {
            // Load configuration file
            $retConf = require $configurationFile;

            // Apply return'ed configuration (if available)
            if (!empty($retConf) && is_array($retConf)) {
                $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive($GLOBALS['TYPO3_CONF_VARS'], $retConf);
            }
        }

        return $this;
    }

    /**
     * Append context name to sitename (if not production)
     *
     * @return $this
     */
    public function appendContextNameToSitename() {
        if (!$this->applicationContext->isProduction()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] .= ' [[' . strtoupper((string)$this->applicationContext) . ']]';
        }
        return $this;
    }
}