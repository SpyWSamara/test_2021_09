<?php

use Bitrix\Main\Application;
use Bitrix\Main\Routing\RoutingConfigurator;
use Local\BasketCrud;

/**
 * Main routing config for basket API
 */
return function (RoutingConfigurator $routes) {
    $routes->prefix('api/basket')->name('api_basket_')->group(
        function (RoutingConfigurator $routes) {
            /**
             * Get basket items list
             */
            $routes->name('list')->get('', function () {
                Application::getInstance()->runController(
                    BasketCrud::class,
                    'getList'
                );
            });
            /**
             * add new item to basket
             */
            $routes->name('add')->post('', function () {
                Application::getInstance()->runController(
                    BasketCrud::class,
                    'addItem'
                );
            });
            /**
             * get basket item info
             */
            $routes->name('get')->get('{id}/', function () {
                Application::getInstance()->runController(
                    BasketCrud::class,
                    'getItem'
                );
            })->where('id', '\\d+');
            /**
             * update basket item
             */
            $routes->name('update')->post('{id}/', function () {
                Application::getInstance()->runController(
                    BasketCrud::class,
                    'updateItem'
                );
            })->where('id', '\\d+');
            /**
             * remove item from basket
             */
            $routes->name('delete')->post('delete/{id}/', function () {
                Application::getInstance()->runController(
                    BasketCrud::class,
                    'deleteItem'
                );
            })->where('id', '\\d+');
        }
    );
};
