<?php

namespace Local;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Internals\OrderTable;

final class StuckOrderForAdmin
{
    /**
     * Static method for use as agent in Bitrix. Add new agent with name
     * "StuckOrderForAdmin::exec('STUCK_NEW_ORDERS')" to send 'STUCK_NEW_ORDER'
     * event with id, account numbers and total stuck orders count.
     *
     * @param string $eventName Run agent name
     *
     * @return string
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function exec(string $eventName): string
    {
        $self = new self();
        $self->sendEvent($eventName);

        return \sprintf('%s("%s");', __METHOD__, $eventName);
    }

    public function __construct()
    {
        if (!Loader::includeModule('sale')) {
            throw new LoaderException('Can\'t include "sale" module.');
        }
    }

    /**
     * Make query for search order in status "N" (new) and last status update
     * more than two days.
     *
     * @return \Bitrix\Main\ORM\Query\Query Order ORM query
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    public function getQuery(): Query
    {
        return OrderTable::query()
            ->where('STATUS_ID', 'N')
            ->where(
                'DATE_STATUS',
                '<=',
                DateTime::createFromPhp(new \DateTime('-2 day'))
            )
            ->where('CANCELED', 'N');
    }

    /**
     * Find orders in status "N" and last updated more than two days. Get
     * iterator by them with fields "ID" and "ACCOUNT_NUMBER".
     *
     * @return \Iterator Result order iterator
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getIterator(): \Iterator
    {
        return $this->getQuery()
            ->addSelect('ID')
            ->addSelect('ACCOUNT_NUMBER')
            ->exec()
            ->getIterator();
    }

    /**
     * Get fields for email.
     *
     * @return array Email fields of order ids and order account numbers
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getMailFields(): array
    {
        $fields = [
            'ORDER_ID_LIST' => [],
            'ORDER_NUMBER_LIST' => [],
            'ORDER_COUNT' => 0,
        ];
        foreach ($this->getIterator() as $row) {
            $fields['ORDER_ID_LIST'][] = $row['ID'];
            $fields['ORDER_NUMBER_LIST'][] = $row['ACCOUNT_NUMBER'];
            ++$fields['ORDER_COUNT'];
        }

        return \array_map(static function ($field) {
            return \implode(', ', $field);
        }, $fields);
    }

    /**
     * Search orders in status "N" and status updated more than two days and
     * send event with custom fields: order id list, order account number list
     * and found order count.
     *
     * @param string $eventName Bitrix event name to send
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function sendEvent(string $eventName)
    {
        $fields = $this->getMailFields();
        if (0 < $fields['ORDER_COUNT']) {
            Event::send([
                'EVENT_NAME' => $eventName,
                'C_FIELDS' => $fields,
            ]);
        }
    }

    /**
     * Register as Bitrix agent this class
     *
     * @param string $eventName Email event name to send
     * @param int $interval Interval of agent
     * @param \DateTime|null $start Start execute agent from date and time,
     *     from now if null
     */
    public function registerAgent(
        string $eventName,
        int $interval,
        ?\DateTime $start = null
    ) {
        if (\is_null($start)) {
            $start = new \DateTime();
        }
        $datetime = DateTime::createFromPhp($start)->toString();
        \CAgent::AddAgent(
            \sprintf('%s::exec("%s");', __CLASS__, $eventName),
            '',
            'N',
            $interval,
            $datetime,
            "Y",
            $datetime
        );
    }
}
