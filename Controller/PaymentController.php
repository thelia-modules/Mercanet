<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Mercanet\Controller;

use Mercanet\Api\MercanetApi;
use Mercanet\Mercanet;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;

/**
 * Class PaymentController
 * @package Mercanet\Controller
 * Franck Allimant <franck@cqfdev.fr>
 */
class PaymentController extends BasePaymentModuleController
{
    protected static $resultCodes = [
        '00' => '	Transaction acceptée',
        '02' => '	Demande d’autorisation par téléphone à la banque à cause d’un dépassement du plafond d’autorisation sur la carte, si vous êtes autorisé à forcer les transactions',
        '03' => '	Contrat commerçant invalide',
        '05' => '	Autorisation refusée',
        '11' => '	Utilisé dans le cas d\'un contrôle différé. Le PAN est en opposition',
        '12' => '	Transaction invalide, vérifier les paramètres transférés dans la requête',
        '14' => '	Coordonnées du moyen de paiement invalides (ex: n° de carte ou cryptogramme visuel de la carte) ou vérification AVS échouée',
        '17' => '	Annulation de l’acheteur',
        '30' => '	Erreur de format',
        '34' => '	Suspicion de fraude (seal erroné)',
        '54' => '	Date de validité du moyen de paiement dépassée',
        '75' => '	Nombre de tentatives de saisie des coordonnées du moyen de paiement sous Sips Paypage dépassé',
        '90' => '	Service temporairement indisponible',
        '94' => '	Transaction dupliquée : la référence de transaction est déjà utilisé',
        '97' => '	Délai expiré, transaction refusée',
        '99' => '	Problème temporaire du serveur de paiement.',
    ];

    /**
     * Traitement de la réponse manuelle. La réponse manuelle est l'URL vers laquelle le client est
     * redirigé une fois le paiement effectué (ou annulé).
     *
     * La validation de commande est efectuée dans le traitement de la réponse automatique (le callback de la banque).
     */
    public function processManualResponse(): void
    {
        $this->getLog()->addInfo(
            $this->getTranslator()->trans(
                "Mercanet manual response processing.",
                [],
                Mercanet::MODULE_DOMAIN
            )
        );

        $paymentResponse = new MercanetApi(Mercanet::getConfigValue('secretKey'));

        $paymentResponse->setResponse($_POST);

        $this->getLog()->addInfo(
            $this->getTranslator()->trans(
                'Response parameters : %resp',
                ['%resp' => print_r($paymentResponse->getDataString(), true)],
                Mercanet::MODULE_DOMAIN
            )
        );

        $order = OrderQuery::create()
            ->filterById($paymentResponse->getParam('ORDERID'))
            ->filterByPaymentModuleId(Mercanet::getModuleId())
            ->findOne();

        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {
            $this->redirectToSuccessPage($order->getId());
        }

        $resultCode = $paymentResponse->getParam('RESPONSECODE');

        // Annulation de la commande
        if ((int) $resultCode === 17) {
            $this->processUserCancel($order->getId());
        }

        $message = self::$resultCodes[$resultCode] ?? 'Raison inconnue';

        $this->redirectToFailurePage($order->getId(), $message);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function processMercanetRequest()
    {
        $this->getLog()->addInfo(
            $this->getTranslator()->trans(
                "Mercanet automatic response processing.",
                [],
                Mercanet::MODULE_DOMAIN
            )
        );

        $paymentResponse = new MercanetApi(Mercanet::getConfigValue('secretKey'));

        $paymentResponse->setResponse($_POST);

        $this->getLog()->addInfo(
            $this->getTranslator()->trans(
                'Response parameters : %resp',
                ['%resp' => print_r($paymentResponse->getDataString(), true)],
                Mercanet::MODULE_DOMAIN
            )
        );

        if ($paymentResponse->isValid()) {
            if (null !== $order = OrderQuery::create()
                    ->filterById($paymentResponse->getParam('ORDERID'))
                    ->filterByPaymentModuleId(Mercanet::getModuleId())
                    ->findOne()) {
                if ($paymentResponse->isSuccessful()) {
                    $this->confirmPayment($order->getId());

                    $this->getLog()->addInfo(
                        $this->getTranslator()->trans(
                            'Order ID %id is confirmed, transaction référence "%trans"',
                            [
                                '%id' => $order->getId(),
                                '%trans' => $paymentResponse->getParam('TRANSACTIONREFERENCE')
                            ],
                            Mercanet::MODULE_DOMAIN
                        )
                    );
                } else {
                    $this->cancelPayment($order->getId());

                    $this->getLog()->addError(
                        $this->getTranslator()->trans(
                            'Cannot validate order. Response code is %resp',
                            ['%resp' => $paymentResponse->getParam('RESPONSECODE')],
                            Mercanet::MODULE_DOMAIN
                        )
                    );
                }
            } else {
                $this->getLog()->addError(
                    $this->getTranslator()->trans(
                        'Cannot find an order for transaction référence "%trans"',
                        ['%trans' => $paymentResponse->getParam('TRANSACTIONREFERENCE')],
                        Mercanet::MODULE_DOMAIN
                    )
                );
            }
        } else {
            $this->getTranslator()->trans(
                'Got invalid response from Mercanet',
                [ ],
                Mercanet::MODULE_DOMAIN
            );
        }

        $this->getLog()->addInfo(
            $this->getTranslator()->trans(
                "Automatic response processing terminated.",
                [],
                Mercanet::MODULE_DOMAIN
            )
        );

        return new Response('OK');
    }

    /*
     * @param $orderId int the order ID
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function processUserCancel($orderId): void
    {
        $this->getLog()->addInfo(
            $this->getTranslator()->trans(
                'User canceled payment of order %id',
                ['%id' => $orderId],
                Mercanet::MODULE_DOMAIN
            )
        );

        try {
            if (null !== $order = OrderQuery::create()->findPk($orderId)) {
                $currentCustomerId = $this->getSecurityContext()->getCustomerUser()->getId();
                $orderCustomerId = $order->getCustomerId();

                if ($orderCustomerId !== $currentCustomerId) {
                    throw new TheliaProcessException(
                        sprintf(
                            "User ID %d is trying to cancel order ID %d ordered by user ID %d",
                            $currentCustomerId,
                            $orderId,
                            $orderCustomerId
                        )
                    );
                }

                $event = new OrderEvent($order);
                $event->setStatus(OrderStatusQuery::getCancelledStatus()->getId());
                $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
            }
        } catch (\Exception $ex) {
            $this->getLog()->addError("Error occurred while canceling order ID $orderId: " . $ex->getMessage());
        }

        $this->redirectToFailurePage(
            $orderId,
            $this->getTranslator()->trans('you cancel the payment', [], Mercanet::MODULE_DOMAIN)
        );
    }

    /**
     * Return a module identifier used to calculate the name of the log file,
     * and in the log messages.
     *
     * @return string the module code
     */
    protected function getModuleCode()
    {
        return 'Mercanet';
    }
}
