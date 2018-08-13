<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Mercanet;

use Mercanet\Api\MercanetApi;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Routing\Router;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

/**
 * Class Mercanet
 * @package Mercanet
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class Mercanet extends AbstractPaymentModule
{
    const MODULE_DOMAIN = 'mercanet';

    /**
     * The confirmation message identifier
     */
    const CONFIRMATION_MESSAGE_NAME = 'mercanet_payment_confirmation';

    private $parameters;

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        // Setup some default values
        if (null === Mercanet::getConfigValue('merchantId', null)) {
            // Initialize with test data
            Mercanet::setConfigValue('merchantId', '211000021310001');
            Mercanet::setConfigValue('secretKeyVersion', 1);
            Mercanet::setConfigValue('secretKey', 'S9i8qClCnb2CZU3y3Vn0toIOgz3z_aBi79akR30vM9o');
            Mercanet::setConfigValue('mode', 'TEST');
            Mercanet::setConfigValue('allowed_ip_list', $_SERVER['REMOTE_ADDR']);
            Mercanet::setConfigValue('minimum_amount', 0);
            Mercanet::setConfigValue('maximum_amount', 0);
            Mercanet::setConfigValue('send_payment_confirmation_message', 1);
        }

        if (null === MessageQuery::create()->findOneByName(Mercanet::CONFIRMATION_MESSAGE_NAME)) {
            $message = new Message();

            $message
                ->setName(Mercanet::CONFIRMATION_MESSAGE_NAME)
                ->setHtmlTemplateFileName('mercanet-payment-confirmation.html')
                ->setTextTemplateFileName('mercanet-payment-confirmation.txt')
                ->setLocale('en_US')
                ->setTitle('Mercanet payment confirmation')
                ->setSubject('Payment of order {$order_ref}')
                ->setLocale('fr_FR')
                ->setTitle('Confirmation de paiement par Mercanet')
                ->setSubject('Confirmation du paiement de votre commande {$order_ref}')
                ->save()
            ;
        }
    }

    /**
     * @param ConnectionInterface|null $con
     * @param bool $deleteModuleData
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        if ($deleteModuleData) {
            MessageQuery::create()->findOneByName(Mercanet::CONFIRMATION_MESSAGE_NAME)->delete();
        }
    }

    /**
     *
     * generate a transaction id
     * @return int|mixed
     */
    private function generateTransactionID()
    {
        $transId = Mercanet::getConfigValue('transactionId', 1);

        $transId = 1 + intval($transId);

        Mercanet::setConfigValue('transactionId', $transId);

        return sprintf("%09d", $transId);
    }

    /**
     *
     *  Method used by payment gateway.
     *
     *  If this method return a \Thelia\Core\HttpFoundation\Response instance, this response is send to the
     *  browser.
     *
     *  In many cases, it's necessary to send a form to the payment gateway.
     *  On your response you can return this form already completed, ready to be sent
     *
     * @param  \Thelia\Model\Order                       $order processed order
     * @return null|\Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function pay(Order $order)
    {
        $amount = $order->getTotalAmount();
        $customer = $order->getCustomer();

        /** @var Router $router */
        $router = $this->getContainer()->get('router.mercanet');

        $transactionId = $this->generateTransactionID();

        // Initialisation de la classe Mercanet avec passage en parametre de la cle secrete
        $paymentRequest = new MercanetApi(Mercanet::getConfigValue('secretKey'));

        // Indiquer quelle page de paiement appeler : TEST ou PRODUCTION
        if ("TEST" === Mercanet::getConfigValue('mode', 'TEST')) {
            $paymentRequest->setUrl(MercanetApi::TEST);
        } else {
            $paymentRequest->setUrl(MercanetApi::PRODUCTION);
        }

        // Renseigner les parametres obligatoires pour l'appel de la page de paiement
        $paymentRequest->setMerchantId(Mercanet::getConfigValue('merchantId'));
        $paymentRequest->setKeyVersion(Mercanet::getConfigValue('secretKeyVersion'));

        $paymentRequest->setTransactionReference($transactionId);
        $paymentRequest->setAmount(intval(round(100 * $amount)));

        $paymentRequest->setCurrency($order->getCurrency()->getCode());

        $paymentRequest->setNormalReturnUrl(URL::getInstance()->absoluteUrl($router->generate('mercanet.payment.manual_response')));
        $paymentRequest->setAutomaticResponseUrl(URL::getInstance()->absoluteUrl($router->generate('mercanet.payment.confirmation')));


        // Renseigner les parametres facultatifs pour l'appel de la page de paiement
        try {
            $paymentRequest->setLanguage(substr($order->getLang()->getCode(), 0, 2));
        } catch (\Exception $ex) {
            $paymentRequest->setLanguage('en');
        }

        $paymentRequest->setCustomerContactEmail($customer->getEmail());

        // Verification de la validite des parametres renseignes
        $paymentRequest->validate();


        // Save transaction ID
        $order->setTransactionRef($transactionId)->save();

        // Appel de la page de paiement Mercanet avec le connecteur POST en passant en parametres : Data, InterfaceVersion, Seal
        /*
        echo "<html><body><form name=\"redirectForm\" method=\"POST\" action=\"" . $paymentRequest->getUrl() . "\">" .
            "<input type=\"hidden\" name=\"Data\" value=\"". $paymentRequest->toParameterString() . "\">" .
            "<input type=\"hidden\" name=\"InterfaceVersion\" value=\"". Mercanet::INTERFACE_VERSION . "\">" .
            "<input type=\"hidden\" name=\"Seal\" value=\"" . $paymentRequest->getShaSign() . "\">" .
            "<noscript><input type=\"submit\" name=\"Go\" value=\"Click to continue\"/></noscript> </form>" .
            "<script type=\"text/javascript\"> document.redirectForm.submit(); </script>" .
            "</body></html>";
        */

        return $this->generateGatewayFormResponse(
            $order,
            $paymentRequest->getUrl(),
            [
                'Data' => $paymentRequest->toParameterString(),
                'InterfaceVersion' => MercanetApi::INTERFACE_VERSION,
                'Seal' => $paymentRequest->getShaSign(),
            ]
        );
    }

    /**
     * @return boolean true to allow usage of this payment module, false otherwise.
     */
    public function isValidPayment()
    {
        $valid = (null !== Mercanet::getConfigValue('secretKey')) && (null !== Mercanet::getConfigValue('merchantId'));

        if ($valid) {
            $mode = Mercanet::getConfigValue('mode', false);

            // If we're in test mode, do not display Payzen on the front office, except for allowed IP addresses.
            if ('TEST' == $mode) {
                $raw_ips = explode("\n", Mercanet::getConfigValue('allowed_ip_list', ''));

                $allowed_client_ips = array();

                foreach ($raw_ips as $ip) {
                    $allowed_client_ips[] = trim($ip);
                }

                $client_ip = $this->getRequest()->getClientIp();

                $valid = in_array($client_ip, $allowed_client_ips);

            } elseif ('PRODUCTION' == $mode) {
                $valid = true;
            }

            if ($valid) {
                // Check if total order amount is in the module's limits
                $valid = $this->checkMinMaxAmount();
            }
        }

        return $valid;
    }

    /**
     * Check if total order amount is in the module's limits
     *
     * @return bool true if the current order total is within the min and max limits
     */
    protected function checkMinMaxAmount()
    {
        // Check if total order amount is in the module's limits
        $order_total = $this->getCurrentOrderTotalAmount();

        $min_amount = Mercanet::getConfigValue('minimum_amount', 0);
        $max_amount = Mercanet::getConfigValue('maximum_amount', 0);

        return
            $order_total > 0
            &&
            ($min_amount <= 0 || $order_total >= $min_amount) && ($max_amount <= 0 || $order_total <= $max_amount);
    }
}
