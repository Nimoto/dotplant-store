<?php


namespace DotPlant\Store\components\calculator;

use DotPlant\Currencies\helpers\CurrencyHelper;
use DotPlant\Store\interfaces\DeliveryTermCalculatorInterface;
use DotPlant\Store\interfaces\NoGoodsCalculatorInterface;
use DotPlant\Store\models\goods\Goods;
use DotPlant\Store\models\order\OrderItem;
use DotPlant\Store\models\price\Price;
use DotPlant\Store\Module;
use yii\base\InvalidParamException;

class OrderItemCalculator implements NoGoodsCalculatorInterface, DeliveryTermCalculatorInterface
{
    /**
     * Calculates object price
     *
     * @param OrderItem $orderItem
     *
     * @return array
     */
    public static function getPrice($orderItem)
    {
        if ($orderItem instanceof OrderItem === false) {
            throw new InvalidParamException;
        }
        $price = ['totalPriceWithoutDiscount' => 0, 'totalPriceWithDiscount' => 0, 'items' => 0, 'extendedPrice' => []];
        $priceType = $orderItem->cart->is_retail == 1 ? Price::TYPE_RETAIL : Price::TYPE_WHOLESALE;

        $goodsId = $orderItem->goods_id;

        $goods = Goods::get($goodsId);
        $goodsPrice = $goods->getPrice(
            $orderItem->warehouse_id,
            $priceType,
            true,
            $orderItem->cart->currency_iso_code
        );
        $price['totalPriceWithDiscount'] = $goodsPrice['value'] * $orderItem->quantity;
        $price['totalPriceWithoutDiscount'] = (
            isset($goodsPrice['valueWithoutDiscount']) ?
                $goodsPrice['valueWithoutDiscount'] :
                $goodsPrice['value']
            ) * $orderItem->quantity;
        $price['items'] = $orderItem->quantity;
        $price['discountReasons'] = $goodsPrice['discountReasons'];

        return $price;
    }


    /**
     * @param OrderItem $object
     * @return int
     */
    public static function getDeliveryTerm($object)
    {
        $result = 0;
        if (Module::getInstance()->deliveryFromWarehouse == false) {
            $handlerClass = $object->warehouse->handler_class;
            $result = $handlerClass::getTerm($object->warehouse->params);
        }
        return $result;
    }
}
