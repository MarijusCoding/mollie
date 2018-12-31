<?php
/**
 * Copyright (c) 2012-2018, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @author     Mollie B.V. <info@mollie.nl>
 * @copyright  Mollie B.V.
 * @license    Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @category   Mollie
 * @package    Mollie
 * @link       https://www.mollie.nl
 * @codingStandardsIgnoreStart
 */

use MollieModule\Mollie\Api\Resources\Payment;
use MollieModule\Mollie\Api\Resources\ResourceFactory;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/../../mollie.php';

/**
 * Class MollieReturnModuleFrontController
 * @method setTemplate
 *
 * @property mixed  context
 * @property Mollie module
 */
class MollieWebhookModuleFrontController extends ModuleFrontController
{
    /** @var bool $ssl */
    public $ssl = true;
    /** @var bool $display_column_left */
    public $display_column_left = false;
    /** @var bool $display_column_right */
    public $display_column_right = false;

    /**
     * Prevent displaying the maintenance page
     *
     * @return void
     */
    protected function displayMaintenancePage()
    {
    }

    /**
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function initContent()
    {
        if (Configuration::get(Mollie::DEBUG_LOG_ALL)) {
            Logger::addLog('Mollie incoming webhook: '.Tools::file_get_contents('php://input'));
        }

        die($this->executeWebhook());
    }

    /**
     * @return string
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    protected function executeWebhook()
    {
        if (Tools::getValue('testByMollie')) {
            if (Configuration::get(Mollie::MOLLIE_DEBUG_LOG) == Mollie::DEBUG_LOG_ERRORS) {
                Logger::addLog(__METHOD__.' said: Mollie webhook tester successfully communicated with the shop.', Mollie::NOTICE);
            }

            return 'OK';
        }

        $payment = $this->processTransaction(Tools::getValue('id'));
        if (is_string($payment)) {
            return $payment;
        }

        return 'OK';
    }

    /**
     * @param string|\MollieModule\Mollie\Api\Resources\Payment|\MollieModule\Mollie\Api\Resources\Order $transaction
     *
     * @return string|\MollieModule\Mollie\Api\Resources\Payment|\MollieModule\Mollie\Api\Resources\Order
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     *
     * @since 3.3.0
     * @since 3.3.2 Returns the ApiPayment / ApiOrder instead of OK string, NOT OK/NO ID stays the same
     */
    public function processTransaction($transaction)
    {
        if (empty($transaction)) {
            if (Configuration::get(Mollie::MOLLIE_DEBUG_LOG) == Mollie::DEBUG_LOG_ERRORS) {
                Logger::addLog(__METHOD__.' said: Received webhook request without proper transaction ID.', Mollie::WARNING);
            }

            return 'NO ID';
        }

        // Ensure that we are dealing with a Payment object, in case of transaction ID or Order object, convert to Payment object
        if (!$transaction instanceof \MollieModule\Mollie\Api\Resources\Payment) {
            try {
                if ($transaction instanceof \MollieModule\Mollie\Api\Resources\Order) {
                    $transaction = $transaction->id;
                }
                $apiToUse = Tools::substr($transaction, 0, 3) === 'ord' ? Mollie::MOLLIE_ORDERS_API : Mollie::MOLLIE_PAYMENTS_API;
                if ($apiToUse === Mollie::MOLLIE_ORDERS_API) {
                    $apiOrder = $this->module->api->{Mollie::MOLLIE_ORDERS_API}->get($transaction, array('embed' => 'payments'));
                    $apiPayment = ResourceFactory::createFromApiResult($apiOrder->_embedded->payments[0], new Payment($this->module->api));
                    $apiPayment->metadata = $apiOrder->metadata;
                } else {
                    $apiPayment = $this->module->api->{Mollie::MOLLIE_PAYMENTS_API}->get($transaction);
                }
            } catch (Exception $e) {
                if (Configuration::get(Mollie::MOLLIE_DEBUG_LOG) == Mollie::DEBUG_LOG_ERRORS) {
                    Logger::addLog(__METHOD__.' said: Could not retrieve payment details for transaction_id "'.$transaction.'". Reason: '.$e->getMessage(), Mollie::WARNING);
                }

                return 'NOT OK';
            }
        } else {
            $apiPayment = $transaction;
            $transaction = $apiPayment->id;
        }
        $psPayment = Mollie::getPaymentBy('transaction_id', $transaction);

        $this->setCountryContextIfNotSet($apiPayment);

        $orderId = (int) version_compare(_PS_VERSION_, '1.7.1.0', '>=')
            ? Order::getIdByCartId((int) $apiPayment->metadata->cart_id)
            : Order::getOrderByCartId((int) $apiPayment->metadata->cart_id);
        $cart = new Cart($apiPayment->metadata->cart_id);
        if ($apiPayment->metadata->cart_id) {
            if ($apiPayment->hasRefunds() || $apiPayment->hasChargebacks()) {
                if (isset($apiPayment->settlementAmount->value, $apiPayment->amountRefunded->value)
                    && (float) $apiPayment->amountRefunded->value >= (float) $apiPayment->settlementAmount->value
                ) {
                    $this->module->setOrderStatus($orderId, Mollie::PARTIAL_REFUND_CODE);
                } else {
                    $this->module->setOrderStatus($orderId, \MollieModule\Mollie\Api\Types\RefundStatus::STATUS_REFUNDED);
                }
            } elseif ($psPayment['method'] === \MollieModule\Mollie\Api\Types\PaymentMethod::BANKTRANSFER
                && $psPayment['bank_status'] === \MollieModule\Mollie\Api\Types\PaymentStatus::STATUS_OPEN
                && $apiPayment->status === \MollieModule\Mollie\Api\Types\PaymentStatus::STATUS_PAID
            ) {
                $order = new Order($orderId);
                $order->payment = isset(Mollie::$methods[$apiPayment->method])
                    ? Mollie::$methods[$apiPayment->method]
                    : $this->module->displayName;
                $order->update();

                $this->module->setOrderStatus($orderId, $apiPayment->status);
            } elseif ($psPayment['method'] !== \MollieModule\Mollie\Api\Types\PaymentMethod::BANKTRANSFER
                && (empty($psPayment['order_id']) || !Order::getCartIdStatic($psPayment['order_id']))
                && $apiPayment->isPaid()
                && Tools::encrypt($cart->secure_key) === $apiPayment->metadata->secure_key
            ) {
                $paymentStatus = (int) $this->module->statuses[$apiPayment->status];

                if ($paymentStatus < 1) {
                    $paymentStatus = Configuration::get('PS_OS_PAYMENT');
                }
                $orderReference = isset($apiPayment->metadata->order_reference) ? $apiPayment->metadata->order_reference : '';

                $this->module->currentOrderReference = $orderReference;
                $this->module->validateMollieOrder(
                    (int) $apiPayment->metadata->cart_id,
                    $paymentStatus,
                    $apiPayment->amount->value,
                    isset(Mollie::$methods[$apiPayment->method]) ? Mollie::$methods[$apiPayment->method] : 'Mollie',
                    null,
                    array(),
                    null,
                    false,
                    $cart->secure_key
                );

                $orderId = (int) version_compare(_PS_VERSION_, '1.7.1.0', '>=')
                    ? Order::getIdByCartId((int) $apiPayment->metadata->cart_id)
                    : Order::getOrderByCartId((int) $apiPayment->metadata->cart_id);
            }
        }

        // Store status in database

        $this->saveOrderTransactionData($apiPayment->id, $apiPayment->method, $orderId);

        if (!$this->savePaymentStatus($transaction, $apiPayment->status, $orderId)) {
            if (Configuration::get(Mollie::MOLLIE_DEBUG_LOG) == Mollie::DEBUG_LOG_ERRORS) {
                Logger::addLog(__METHOD__.' said: Could not save Mollie payment status for transaction "'.$transaction.'". Reason: '.Db::getInstance()->getMsgError(), Mollie::WARNING);
            }
        }

        // Log successful webhook requests in extended log mode only
        if (Configuration::get(Mollie::MOLLIE_DEBUG_LOG) == Mollie::DEBUG_LOG_ALL) {
            Logger::addLog(__METHOD__.' said: Received webhook request for order '.(int) $orderId.' / transaction '.$transaction, Mollie::NOTICE);
        }

        return $apiPayment;
    }

    /**
     * Retrieves the OrderPayment object, created at validateOrder. And add transaction data.
     *
     * @param string $molliePaymentId
     * @param string $molliePaymentMethod
     * @param int    $orderId
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function saveOrderTransactionData($molliePaymentId, $molliePaymentMethod, $orderId)
    {
        // retrieve ALL payments of order.
        // in the case of a cancel or expired on banktransfer, this will fire too.
        // if no OrderPayment objects is retrieved in the collection, do nothing.
        $order = new Order((int) $orderId);
        $collection = OrderPayment::getByOrderReference($order->reference);
        if (count($collection) > 0) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $collection[0];

            // for older versions (1.5) , we check if it hasn't been filled yet.
            if (!$orderPayment->transaction_id) {
                $orderPayment->transaction_id = $molliePaymentId;
                $orderPayment->payment_method = $molliePaymentMethod;
                $orderPayment->update();
            }
        }
    }

    /**
     * @param string $transactionId
     * @param int    $status
     * @param int    $orderId
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function savePaymentStatus($transactionId, $status, $orderId)
    {
        try {
            return Db::getInstance()->update(
                'mollie_payments',
                array(
                    'updated_at'  => array('type' => 'sql', 'value' => 'NOW()'),
                    'bank_status' => pSQL($status),
                    'order_id'    => (int) $orderId,
                ),
                '`transaction_id` = \''.pSQL($transactionId).'\''
            );
        } catch (PrestaShopDatabaseException $e) {
            Mollie::tryAddOrderReferenceColumn();
            throw $e;
        }
    }

    /**
     * (Re)sets the controller country context.
     * When Prestashop receives a call from Mollie (without context)
     * Prestashop always has default context to fall back on, so context->country
     * is allways Set before executing any controller methods
     *
     * @param \MollieModule\Mollie\Api\Resources\Payment $payment
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    protected function setCountryContextIfNotSet($payment)
    {
        if (empty($this->context->country) || !$this->context->country->active) {
            if ($payment->metadata->cart_id) {
                $cart = new Cart((int) $payment->metadata->cart_id);
                if (!empty($cart)) {
                    $address = new Address($cart->id_address_delivery);
                    if (!empty($address)) {
                        $country = new Country($address->id_country);
                        if (!empty($country)) {
                            $this->context->country = $country;
                        }
                    }
                }
            }
        }
    }
}
