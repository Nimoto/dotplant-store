<?php

namespace DotPlant\Store\models\price;

use DotPlant\Store\components\calculator\PartGoodsCalculator;

/**
 * Class PartPrice
 *
 * @package DotPlant\Store\models\price
 */
class PartPrice extends Price
{
    protected $_calculatorClass = PartGoodsCalculator::class;
}
