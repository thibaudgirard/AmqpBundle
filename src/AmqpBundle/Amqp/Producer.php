<?php

namespace M6Web\Bundle\AmqpBundle\Amqp;

use M6Web\Bundle\AmqpBundle\Event\PrePublishEvent;

/**
 * Producer.
 */
class Producer extends AbstractAmqp
{
    protected \AMQPExchange $exchange;

    protected array $exchangeOptions = [];

    /**
     * Constructor.
     *
     * @param \AMQPExchange $exchange        Amqp Exchange
     * @param array         $exchangeOptions Exchange options
     */
    public function __construct(\AMQPExchange $exchange, array $exchangeOptions)
    {
        $this->setExchange($exchange);
        $this->setExchangeOptions($exchangeOptions);
    }

    /**
     * @param string $message     the message to publish
     * @param int    $flags       one or more of AMQP_MANDATORY and AMQP_IMMEDIATE
     * @param array  $attributes  one of content_type, content_encoding,
     *                            message_id, user_id, app_id, delivery_mode, priority,
     *                            timestamp, expiration, type or reply_to
     * @param array  $routingKeys If set, overrides the Producer 'routing_keys' for this message
     *
     * @return bool tRUE on success or FALSE on failure
     *
     * @throws \AMQPExchangeException   on failure
     * @throws \AMQPChannelException    if the channel is not open
     * @throws \AMQPConnectionException if the connection to the broker was lost
     */
    public function publishMessage(string $message, int $flags = AMQP_NOPARAM, array $attributes = [], array $routingKeys = []): bool
    {
        // Merge attributes
        $attributes = empty($attributes) ? $this->exchangeOptions['publish_attributes'] :
                      (empty($this->exchangeOptions['publish_attributes']) ? $attributes :
                      array_merge($this->exchangeOptions['publish_attributes'], $attributes));

        $routingKeys = !empty($routingKeys) ? $routingKeys : $this->exchangeOptions['routing_keys'];

        if ($this->eventDispatcher) {
            $prePublishEvent = new PrePublishEvent($message, $routingKeys, $flags, $attributes);
            $this->eventDispatcher->dispatch($prePublishEvent,PrePublishEvent::NAME);

            if (!$prePublishEvent->canPublish()) {
                return true;
            }

            $routingKeys = $prePublishEvent->getRoutingKeys();
            $message = $prePublishEvent->getMessage();
            $flags = $prePublishEvent->getFlags();
            $attributes = $prePublishEvent->getAttributes();
        }

        if (!$routingKeys) {
            return $this->call($this->exchange, 'publish', [$message, null, $flags, $attributes]);
        }

        // Publish the message for each routing keys
        $success = true;
        foreach ($routingKeys as $routingKey) {
            $success &= $this->call($this->exchange, 'publish', [$message, $routingKey, $flags, $attributes]);
        }

        return (bool) $success;
    }

    public function getExchange(): \AMQPExchange
    {
        return $this->exchange;
    }

    public function setExchange(\AMQPExchange $exchange): self
    {
        $this->exchange = $exchange;

        return $this;
    }

    public function getExchangeOptions(): array
    {
        return $this->exchangeOptions;
    }

    public function setExchangeOptions(array $exchangeOptions): self
    {
        $this->exchangeOptions = $exchangeOptions;

        if (!array_key_exists('publish_attributes', $this->exchangeOptions)) {
            $this->exchangeOptions['publish_attributes'] = [];
        }

        if (!array_key_exists('routing_keys', $this->exchangeOptions)) {
            $this->exchangeOptions['routing_keys'] = [];
        }

        return $this;
    }
}
