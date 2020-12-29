<?php

namespace MolliePrefix;

use MolliePrefix\Monolog\Logger;
use MolliePrefix\Monolog\Handler\AbstractProcessingHandler;
class Raven_Breadcrumbs_MonologHandler extends \MolliePrefix\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    protected $logLevels = array(\MolliePrefix\Monolog\Logger::DEBUG => \MolliePrefix\Raven_Client::DEBUG, \MolliePrefix\Monolog\Logger::INFO => \MolliePrefix\Raven_Client::INFO, \MolliePrefix\Monolog\Logger::NOTICE => \MolliePrefix\Raven_Client::INFO, \MolliePrefix\Monolog\Logger::WARNING => \MolliePrefix\Raven_Client::WARNING, \MolliePrefix\Monolog\Logger::ERROR => \MolliePrefix\Raven_Client::ERROR, \MolliePrefix\Monolog\Logger::CRITICAL => \MolliePrefix\Raven_Client::FATAL, \MolliePrefix\Monolog\Logger::ALERT => \MolliePrefix\Raven_Client::FATAL, \MolliePrefix\Monolog\Logger::EMERGENCY => \MolliePrefix\Raven_Client::FATAL);
    protected $excMatch = '/^exception \'([^\']+)\' with message \'(.+)\' in .+$/s';
    /**
     * @var Raven_Client the client object that sends the message to the server
     */
    protected $ravenClient;
    /**
     * @param Raven_Client $ravenClient
     * @param int          $level       The minimum logging level at which this handler will be triggered
     * @param bool         $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(\MolliePrefix\Raven_Client $ravenClient, $level = \MolliePrefix\Monolog\Logger::DEBUG, $bubble = \true)
    {
        parent::__construct($level, $bubble);
        $this->ravenClient = $ravenClient;
    }
    /**
     * @param string $message
     * @return array|null
     */
    protected function parseException($message)
    {
        if (\preg_match($this->excMatch, $message, $matches)) {
            return array($matches[1], $matches[2]);
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // sentry uses the 'nobreadcrumb' attribute to skip reporting
        if (!empty($record['context']['nobreadcrumb'])) {
            return;
        }
        if (isset($record['context']['exception']) && ($record['context']['exception'] instanceof \Exception || \PHP_VERSION_ID >= 70000 && $record['context']['exception'] instanceof \Throwable)) {
            /**
             * @var \Exception|\Throwable $exc
             */
            $exc = $record['context']['exception'];
            $crumb = array('type' => 'error', 'level' => $this->logLevels[$record['level']], 'category' => $record['channel'], 'data' => array('type' => \get_class($exc), 'value' => $exc->getMessage()));
        } else {
            // TODO(dcramer): parse exceptions out of messages and format as above
            if ($error = $this->parseException($record['message'])) {
                $crumb = array('type' => 'error', 'level' => $this->logLevels[$record['level']], 'category' => $record['channel'], 'data' => array('type' => $error[0], 'value' => $error[1]));
            } else {
                $crumb = array('level' => $this->logLevels[$record['level']], 'category' => $record['channel'], 'message' => $record['message'], 'data' => $record['context']);
            }
        }
        $this->ravenClient->breadcrumbs->record($crumb);
    }
}
\class_alias('MolliePrefix\\Raven_Breadcrumbs_MonologHandler', 'Raven_Breadcrumbs_MonologHandler', \false);
