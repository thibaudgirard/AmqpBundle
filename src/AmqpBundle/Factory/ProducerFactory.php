<?php

namespace M6Web\Bundle\AmqpBundle\Factory;

use M6Web\Bundle\AmqpBundle\Amqp\Producer;

class ProducerFactory extends AMQPFactory
{
    protected string $channelClass;
    protected string $exchangeClass;
    protected string $queueClass;

    /**
     * __construct.
     *
     * @param class-string $channelClass  Channel class name
     * @param class-string $exchangeClass Exchange class name
     * @param class-string $queueClass    Queue class name
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $channelClass, string $exchangeClass, string $queueClass)
    {
        if (!class_exists($channelClass) || !is_a($channelClass, 'AMQPChannel', true)) {
            throw new \InvalidArgumentException(
                sprintf("channelClass '%s' doesn't exist or not a AMQPChannel", $channelClass)
            );
        }

        if (!class_exists($exchangeClass) || !is_a($exchangeClass, 'AMQPExchange', true)) {
            throw new \InvalidArgumentException(
                sprintf("exchangeClass '%s' doesn't exist or not a AMQPExchange", $exchangeClass)
            );
        }

        if (!class_exists($queueClass) || !is_a($queueClass, 'AMQPQueue', true)) {
            throw new \InvalidArgumentException(
                sprintf("queueClass '%s' doesn't exist or not a AMQPQueue", $queueClass)
            );
        }

        $this->channelClass = $channelClass;
        $this->exchangeClass = $exchangeClass;
        $this->queueClass = $queueClass;
    }

    /**
     * build the producer class.
     *
     * @param class-string     $class           Provider class name
     * @param \AMQPConnection $connexion       AMQP connexion
     * @param array           $exchangeOptions Exchange Options
     * @param array           $queueOptions    Queue Options
     * @param bool            $lazy            Specifies if it should connect
     *
     * @return Producer
     */
    public function get(string $class, \AMQPConnection $connexion, array $exchangeOptions, array $queueOptions, bool $lazy = false): Producer
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(
                sprintf("Producer class '%s' doesn't exist", $class)
            );
        }

        if ($lazy) {
            if (!$connexion->isConnected()) {
                $connexion->connect();
            }
        }

        // Open a new channel
        $channel = new $this->channelClass($connexion);
        $exchange = $this->createExchange($this->exchangeClass, $channel, $exchangeOptions);

        if (isset($queueOptions['name'])) {
            // create, declare queue, and bind it to exchange
            /** @var \AMQPQueue $queue */
            $queue = new $this->queueClass($channel);
            $queue->setName($queueOptions['name']);
            $queue->setArguments($queueOptions['arguments']);
            $queue->setFlags(
                ($queueOptions['passive'] ? AMQP_PASSIVE : AMQP_NOPARAM) |
                ($queueOptions['durable'] ? AMQP_DURABLE : AMQP_NOPARAM) |
                ($queueOptions['auto_delete'] ? AMQP_AUTODELETE : AMQP_NOPARAM)
            );
            $queue->declareQueue();
            $queue->bind($exchangeOptions['name']);

            // Bind the queue to some routing keys
            foreach ($queueOptions['routing_keys'] as $routingKey) {
                $queue->bind($exchangeOptions['name'], $routingKey);
            }
        }

        // Create the producer
        return new $class($exchange, $exchangeOptions);
    }
}
