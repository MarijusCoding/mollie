<?php

use _PhpScoper5eddef0da618a\Mollie\Api\MollieApiClient;
use Mollie\Repository\PaymentMethodRepository;
use Mollie\Service\MolliePaymentMailService;
use Mollie\Service\PaymentMethodService;
use Mollie\Service\TransactionService;

class AdminMollieAjaxController extends ModuleAdminController
{
    public function postProcess()
    {
        $action = Tools::getValue('action');
        switch ($action) {
            case 'togglePaymentMethod':
                $this->togglePaymentMethod();
                break;
            case 'resendPaymentMail':
                $this->resendPaymentMail();
                break;
            default:
                break;
        }
    }

    private function togglePaymentMethod()
    {
        $paymentMethod = Tools::getValue('paymentMethod');
        $paymentStatus = Tools::getValue('status');

        /** @var PaymentMethodRepository $paymentMethodRepo */
        $paymentMethodRepo = $this->module->getContainer(PaymentMethodRepository::class);
        $methodId = $paymentMethodRepo->getPaymentMethodIdByMethodId($paymentMethod);
        $method = new MolPaymentMethod($methodId);
        switch ($paymentStatus) {
            case 'deactivate':
                $method->enabled = 0;
                break;
            case 'activate':
                $method->enabled = 1;
                break;
        }
        $method->update();

        $this->ajaxDie(json_encode(
            [
                'success' => true,
                'paymentStatus' => $method->enabled
            ]
        ));
    }

    private function resendPaymentMail()
    {
        $orderId = Tools::getValue('id_order');

        /** @var MolliePaymentMailService $molliePaymentMailService */
        $molliePaymentMailService = $this->module->getContainer(MolliePaymentMailService::class);

        $response = $molliePaymentMailService->sendSecondChanceMail($orderId);

        $this->ajaxDie(json_encode($response));
    }
}