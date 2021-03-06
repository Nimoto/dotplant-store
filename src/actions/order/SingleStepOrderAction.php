<?php

namespace DotPlant\Store\actions\order;

use DevGroup\Users\helpers\ModelMapHelper;
use DevGroup\Users\models\User;
use DotPlant\Store\components\Store;
use DotPlant\Store\events\AfterUserRegisteredEvent;
use DotPlant\Store\exceptions\OrderException;
use DotPlant\Store\models\order\Cart;
use DotPlant\Store\models\order\Order;
use DotPlant\Store\models\order\OrderDeliveryInformation;
use DotPlant\Store\Module;
use Yii;
use yii\base\Action;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;

/**
 * Class SingleStepOrderAction
 * @package DotPlant\Store\actions\order
 */
class SingleStepOrderAction extends Action
{
    /**
     * @var string the current action route
     */
    public $actionRoute = ['/store/order/create'];

    /**
     * @var array the route to payment action
     */
    public $paymentRoute = ['/store/order/payment'];

    /**
     * @var array the route to cart index action
     */
    public $cartRoute = ['/store/cart'];

    /**
     * @var string the action view file
     */
    public $viewFile = 'single-step-order';

    public function run($hash = null)
    {
        $order = !empty($hash) ? Store::getOrder($hash) : new Order;
        if ($order === null) {
            throw new BadRequestHttpException;
        }
        // check cart
        if ($order->isNewRecord) {
            $cart = Store::getCart(false);
            if ($cart === null || $cart->items_count == 0) {
                Yii::$app->session->setFlash('error', Yii::t('dotplant.store', 'Cart is empty'));
                return $this->controller->redirect($this->cartRoute);
            }
            $order->context_id = $cart->context_id;
            if (!$cart->canEdit()) {
                return $this->controller->redirect(
                    ArrayHelper::merge(
                        $this->actionRoute,
                        ['hash' => $cart->order !== null ? $cart->order->hash : null]
                    )
                );
            }
            $orderDeliveryInformation = new OrderDeliveryInformation;
            $orderDeliveryInformation->loadDefaultValues();
        }
        // create delivery information if it doesn't exist
        if ($order->isNewRecord || $order->deliveryInformation === null) {
            $orderDeliveryInformation = new OrderDeliveryInformation;
            $orderDeliveryInformation->loadDefaultValues();
        } else {
            $orderDeliveryInformation = $order->deliveryInformation;
        }
        $order->scenario = 'single-step-order';
        $orderDeliveryInformation->context_id = Yii::$app->multilingual->context_id;
        $orderDeliveryInformationIsValid = $orderDeliveryInformation->load(Yii::$app->request->post())
            && $orderDeliveryInformation->validate();
        $orderIsValid = $order->load(Yii::$app->request->post()) && $order->validate();
        $userId = null;
        if ($orderDeliveryInformationIsValid && $orderIsValid) {
            if ($order->isNewRecord) {
                if (Yii::$app->user->isGuest && Module::module()->registerGuestInCart == 1) {
                    $userClass = ModelMapHelper::User()['class'];
                    $user = new $userClass;
                    $user->username = uniqid("", true);
                    $user->username_is_temporary = true;
                    $user->password_is_temporary = true;
                    $user->email = $orderDeliveryInformation->email;
                    $user->password = Yii::$app->security->generateRandomString(10);
                    if ($user->save(
                        true,
                        [
                                'username',
                                'email',
                                'username_is_temporary',
                                'password_hash',
                                'password_is_temporary',
                                'created_at',
                            ]
                    )
                    ) {
                        Module::module()->trigger(
                            Module::EVENT_AFTER_USER_REGISTERED,
                            new AfterUserRegisteredEvent(
                                [
                                    'languageId' => Yii::$app->multilingual->language_id,
                                    'password' => $user->password,
                                    'userId' => $user->id,
                                ]
                            )
                        );
                        $userId = $user->id;
                    }
                }

                $cart->addDelivery(ArrayHelper::getValue(Yii::$app->request->post(),$order->formName() . '.delivery_id'));

                /** @var Cart $cart */
                $order = Store::createOrder($cart);
                if ($order === null) {
                    throw new OrderException(Yii::t('dotplant.store', 'Something went wrong'));
                }
                $order->scenario = 'single-step-order';
            }
            $order->load(Yii::$app->request->post()); // @todo: refactor it
            $order->created_by = $userId;
            $orderDeliveryInformation->order_id = $order->id;
            $orderDeliveryInformation->user_id = $userId;
            if ($order->save(false) && $orderDeliveryInformation->save()) {
                return $this->controller->redirect(
                    ArrayHelper::merge($this->paymentRoute, ['hash' => $order->hash, 'paymentId' => $order->payment_id])
                );
            }
            Yii::$app->session->setFlash('error', Yii::t('dotplant.store', 'Can not save delivery information'));
            return $this->controller->redirect(ArrayHelper::merge($this->actionRoute, ['hash' => $order->hash]));
        }
        return $this->controller->render(
            $this->viewFile,
            [
                'order' => $order,
                'orderDeliveryInformation' => $orderDeliveryInformation,
            ]
        );
    }
}
