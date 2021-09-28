<?php

namespace Oxzion\Messaging;

use Logger;
use Exception;
use Oxzion\Service\ErrorLogService;

class MessageProducer
{
    private static $instance = null;
    private $client;
    private $logger;
    private $errorLogService;

    public function __construct($config, ErrorLogService $errorLogService)
    {
        $this->client = new Client($config);
        $this->logger = Logger::getLogger(__CLASS__);
        $this->errorLogService = $errorLogService;
    }
    public static function getInstance($config, $errorLogService)
    {
        return (new self($config, $errorLogService));
    }

    public function sendTopic($message, $topic)
    {
        $this->logger->info("Topic is ---" . $topic . " with payload " . $message);
        try {
            $result = $this->client->sendMessage('/topic/' . $topic, $message);
            $this->logger->info("The result data : " . print_r($result, true));
        } catch (Exception $e) {
            $this->logger->info("Inside exception : ");
            $this->errorLogService->saveError('amqp', $e->getTraceAsString(), $message, json_encode(array('topic' => $topic)));
            $this->logger->error($e->getMessage(), $e);
            return false;
        }
        return true;
    }
    public function sendQueue($message, $queue)
    {
        $this->logger->info("Queue is ---" . $queue . " with payload " . $message);
        try {
            $result = $this->client->sendMessage('/queue/' . $queue, $message);
        } catch (Exception $e) {
            $this->errorLogService->saveError('amqp', $e->getTraceAsString(), $message, json_encode(array('queue' => $queue)));
            $this->logger->error($e->getMessage(), $e);
            return false;
        }
        return true;
    }
}
