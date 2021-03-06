<?php


namespace DotPlant\Store\widgets\backend;

use DotPlant\EntityStructure\models\BaseStructure;
use DotPlant\Store\assets\ExtendedPriceAssets;
use DotPlant\Store\handlers\extendedPrice\ProductRule;
use DotPlant\Store\handlers\extendedPrice\StructureRule;
use DotPlant\Store\models\extendedPrice\ExtendedPrice;
use DotPlant\Store\models\extendedPrice\ExtendedPriceHandler;
use DotPlant\Store\models\extendedPrice\ExtendedPriceRule;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class EntityExtendedPriceEdit extends Widget
{
    /**
     * @var BaseStructure
     */
    public $entity;
    /**
     * @var ActiveRecord
     */
    public $noEntity;
    /**
     * @var bool
     */
    public $singleRule = false;
    /**
     * @var string
     */
    public $handlerClass = StructureRule::class;
    /**
     * @var ExtendedPriceHandler
     */
    private $_handler;

    private $_handlersMap = [
        ProductRule::class => 'products',
        StructureRule::class => 'structures'
    ];

    private $_findField;

    public function init()
    {
        $this->_handler = ExtendedPriceHandler::findOne(['handler_class' => $this->handlerClass]);
        if (is_null($this->_handler) === true || !isset($this->_handlersMap[$this->handlerClass])) {
            throw new InvalidParamException;
        }
        $this->_findField = $this->_handlersMap[$this->handlerClass];
    }

    function run()
    {
        if (is_object($this->entity) === true) {
            if ($this->entity->isNewRecord) {
                return '';
            }
            ExtendedPriceAssets::register($this->getView());
            $allRules = ExtendedPriceRule::find()->joinWith('extendedPrice')->where(
                [
                    ExtendedPrice::tableName() . '.target_class' => ExtendedPrice::TARGET_TYPE_GOODS,
                    'extended_price_handler_id' => $this->_handler->id,
                ]
            )->all();
            $acceptableRules = array_reduce(
                $allRules,
                function ($carry, ExtendedPriceRule $item) {
                    if ($this->singleRule === false) {
                        $ids = (array) ArrayHelper::getValue($item->params, $this->_findField, []);
                        if (array_search($this->entity->id, $ids) !== false) {
                            $carry[] = $item;
                        }
                    } else {
                        if (ArrayHelper::getValue($item->params, $this->_findField, 0) === $this->entity->id) {
                            $carry[] = $item;
                        }
                    }
                    return $carry;
                },
                []
            );
            if (empty($acceptableRules)) {
                $rule = new ExtendedPriceRule();
                $rule->loadDefaultValues();
                $rule->extended_price_handler_id = $this->_handler->id;

                if ($this->singleRule) {
                    $params = [$this->_findField => $this->entity->id];
                } else {
                    $params = [$this->_findField => [$this->entity->id]];
                }
                $rule->params = $params;
                $rule->packAttributes();
                $acceptableRules[] = $rule;
            }

            return $this->render(
                'entity-extended-price-edit',
                [
                    'acceptableRules' => $acceptableRules,
                ]
            );
        } elseif (is_object($this->noEntity)) {
            if ($this->noEntity->isNewRecord) {
                return '';
            }
            throw new Exception('Not implemented yet');
        } else {
            throw new InvalidParamException('Not implemented yet');
        }
    }
}
