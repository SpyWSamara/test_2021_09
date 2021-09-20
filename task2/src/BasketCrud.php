<?php

namespace Local;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\AutoWire\ExactParameter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Result;
use Bitrix\Sale\Basket;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Fuser;
use Bitrix\Catalog\ProductTable;
use Bitrix\Main\Engine\ActionFilter;

final class BasketCrud extends Controller
{
    /**
     * @var \Bitrix\Sale\Basket Current user basket
     */
    private $basket;
    /**
     * @var array Map of BasketItem fields as keys and post fields as values.
     *     Use for map post fields to update item fields.
     */
    private $updateFields = [
        'NAME' => 'name',
        'QUANTITY' => 'quantity',
        'PRICE' => 'price',
    ];

    /**
     * New instance of BasketCrud handler for API
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\NotImplementedException
     */
    public function __construct()
    {
        if (!Loader::includeModule('sale')) {
            throw new LoaderException('Can\'t load "sale" module.');
        }
        $this->basket = Basket::loadItemsForFUser(
            Fuser::getId(),
            Application::getInstance()->getContext()->getSite()
        );
        parent::__construct();
    }

    /**
     * Get handlers filters config
     *
     * @return array Handler actions execute filters
     *     (https://dev.1c-bitrix.ru/api_d7/bitrix/main/engine/actionfilter/index.php)
     */
    public function configureActions()
    {
        return [
            'getList' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod(
                        [ActionFilter\HttpMethod::METHOD_GET]
                    ),
                    new ActionFIlter\Scope(
                        [ActionFilter\Scope::REST]
                    ),
                ],
            ],
            'addItem' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod(
                        [ActionFilter\HttpMethod::METHOD_POST]
                    ),
                    new ActionFIlter\Scope(
                        [ActionFilter\Scope::REST]
                    ),
                ],
                /**
                 * In case of default behavior Bitrix add CSRF filter to all POST actions.
                 * To prevent error on clear API calls (curl|POSTman and others) we remove this prefilter for now.
                 * TODO: in future add user rights check mechanism.
                 */
                '-prefilters' => [
                    ActionFilter\Csrf::class,
                ],
            ],
            'getItem' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod(
                        [ActionFilter\HttpMethod::METHOD_GET]
                    ),
                    new ActionFIlter\Scope(
                        [ActionFilter\Scope::REST]
                    ),
                ],
            ],
            'updateItem' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod(
                        [ActionFilter\HttpMethod::METHOD_POST]
                    ),
                    new ActionFIlter\Scope(
                        [ActionFilter\Scope::REST]
                    ),
                ],
                /**
                 * In case of default behavior Bitrix add CSRF filter to all POST actions.
                 * To prevent error on clear API calls (curl|POSTman and others) we remove this prefilter for now.
                 * TODO: in future add user rights check mechanism.
                 */
                '-prefilters' => [
                    ActionFilter\Csrf::class,
                ],
            ],
            'deleteItem' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod(
                        [ActionFilter\HttpMethod::METHOD_POST]
                    ),
                    new ActionFIlter\Scope(
                        [ActionFilter\Scope::REST]
                    ),
                ],
                /**
                 * In case of default behavior Bitrix add CSRF filter to all POST actions.
                 * To prevent error on clear API calls (curl|POSTman and others) we remove this prefilter for now.
                 * TODO: in future add user rights check mechanism.
                 */
                '-prefilters' => [
                    ActionFilter\Csrf::class,
                ],
            ],
        ];
    }

    /**
     * Get primary autowired parameter for functions.
     * We can add more parameters by `public function
     * getAutoWiredParameters():array;` method. More
     * info:
     * https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=21162
     *
     * @return \Bitrix\Main\Engine\AutoWire\ExactParameter|null Autowire
     *     parameter config or null
     * @throws \Bitrix\Main\Engine\AutoWire\BinderArgumentException
     */
    public function getPrimaryAutoWiredParameter()
    {
        return new ExactParameter(
            BasketItem::class,
            'item',
            function ($className, $id) {
                return $this->getBasketItemById($id);
            }
        );
    }

    /**
     * Return list if basket items with some fields
     *
     * @return array Basket items list. Empty array if basket is empty.
     */
    public function getListAction(): array
    {
        $result = [];
        /** @var BasketItem $item */
        foreach ($this->basket as $item) {
            $result[] = $this->getItemData($item);
        }

        return $result;
    }

    /**
     * Add item to basket. Use some parameters as `productId`, `quantity`, list
     * of `props`.
     *
     * @return array Array with new basket item id and api get info url
     * @throws \Exception
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectNotFoundException
     * @throws \Bitrix\Main\Routing\Exceptions\ParameterNotFoundException
     */
    public function addItemAction(): array
    {
        $productId = (int) $this->getRequest()->getPost('productId');
        $quantity = (float) $this->getRequest()->getPost('quantity');
        $productData = $this->getProductCatalogInfo($productId);
        if (empty($productData)) {
            $this->getResponse()->setStatus(404);
            throw new \Exception(
                \sprintf('There is no product with id %d.', $productId)
            );
        }
        if (0 !== $quantity % $productData['RATIO']) {
            $quantity = (float) $productData['RATIO'];
        }
        $result = \Bitrix\Catalog\Product\Basket::addProduct([
            'PRODUCT_ID' => $this->getRequest()->getPost('productId'),
            'QUANTITY' => $quantity,
            'PROPS' => $this->getRequest()->getPost('props') ?? [],
        ]);
        $this->processResult($result);
        $cartItemUrl = Application::getInstance()->getRouter()->route(
            'api_basket_get',
            ['id' => $result->getData()['ID']]
        );
        $this->getResponse()->setStatus(201);

        return [
            'id' => (int) $result->getData()['ID'],
            'link' => $cartItemUrl,
        ];
    }

    /**
     * Get basket item info
     *
     * @param \Bitrix\Sale\BasketItem $item Current request basket item
     *     (autowired)
     *
     * @return array Basket item info array
     */
    public function getItemAction(BasketItem $item): array
    {
        return $this->getItemData($item);
    }

    /**
     * Update basket item fields (fields map $this->updateFields). Return
     * basket info with new fields.
     *
     * @param \Bitrix\Sale\BasketItem $item Current request basket item
     *
     * @return array Basket item info array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\NotImplementedException
     */
    public function updateItemAction(BasketItem $item): array
    {
        foreach ($this->updateFields as $itemField => $postField) {
            $postValue = $this->getRequest()->getPost($postField);
            if (\is_null($postValue)) {
                continue;
            }
            if ('PRICE' === $itemField) {
                $this->processResult(
                    $item->setField(
                        'CUSTOM_PRICE',
                        false != $postValue ? 'Y' : 'N'
                    )
                );
            }
            $this->processResult(
                $item->setField($itemField, $postValue)
            );
        }
        if ($item->isChanged()) {
            $this->processResult(
                $item->save()
            );
        }

        return $this->getItemAction($item);
    }

    /**
     * Delete item from basket
     *
     * @param \Bitrix\Sale\BasketItem $item Current request basket item
     *
     * @return \Bitrix\Main\HttpResponse
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectNotFoundException
     */
    public function deleteItemAction(BasketItem $item): HttpResponse
    {
        $item->delete();
        $result = $this->basket->save();
        $this->processResult($result);

        return (new HttpResponse())->setStatus(204);
    }

    /**
     * Internal function to map BasketItem object to info array
     *
     * @param \Bitrix\Sale\BasketItem $item Basket item object for convert to
     *     info array
     *
     * @return array Array with info about basket item
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectNotFoundException
     */
    private function getItemData(BasketItem $item): array
    {
        return [
            'ID' => $item->getId(),
            'PRODUCT_ID' => $item->getProductId(),
            'NAME' => $item->getField('NAME'),
            'PRICE' => $item->getPrice(),
            'VAT_PRICE' => $item->getPriceWithVat(),
            'QUANTITY' => $item->getQuantity(),
            'SUM' => $item->getFinalPrice(),
            'CURRENCY' => $item->getCurrency(),
            'PROPS' => $item->getPropertyCollection()->getPropertyValues(),
        ];
    }

    /**
     * Get basket item by id
     *
     * @param int $id Id of item in basket
     *
     * @return \Bitrix\Sale\BasketItem Basket item object. Use in autowiring.
     * @throws \Bitrix\Main\ArgumentNullException
     */
    private function getBasketItemById(int $id): BasketItem
    {
        $item = $this->basket->getItemById($id);
        if (!$item) {
            $this->getResponse()->setStatus(
                404
            );
            throw new \Exception(
                \sprintf('Basket item with id %d not found.', $id)
            );
        }

        return $item;
    }

    /**
     * Get product info from catalog module
     *
     * @param int $productId Product id as known as iblock element id
     *
     * @return array Catalog info for iblock element
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ArgumentException
     */
    private function getProductCatalogInfo(int $productId): array
    {
        if (!Loader::includeModule('catalog')) {
            throw new LoaderException('Cant load "catalog" module.');
        }
        $list = ProductTable::getCurrentRatioWithMeasure($productId);
        if (false === $list) {
            return [];
        }

        return $list[$productId];
    }

    /**
     * Handle result of change or save. Make string from error list and throw
     * new exception.
     *
     * @param \Bitrix\Main\Result $result Result of change field or save object
     *
     * @throws \Exception
     */
    private function processResult(Result $result)
    {
        if (!$result->isSuccess()) {
            $errors = \implode('; ', $result->getErrorMessages());
            throw new \Exception($errors);
        }
    }

    /**
     * Get current bitrix HttpResponse for modification
     *
     * @return HttpResponse Current application response
     */
    private function getResponse(): HttpResponse
    {
        return Application::getInstance()->getContext()->getResponse();
    }
}
