<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Mercanet\EventListeners;

use Mercanet\Mercanet;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;

/**
 * Class SendConfirmationEmail
 *
 * @package Mercanet\EventListeners
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SendConfirmationEmail implements EventSubscriberInterface
{
    /** @var MailerFactory */
    protected $mailer;

    public function __construct(MailerFactory $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendConfirmationEmail(OrderEvent $event)
    {
        if (Mercanet::getConfigValue('send_confirmation_message_only_if_paid')) {
            // We send the order confirmation email only if the order is paid
            $order = $event->getOrder();

            if (! $order->isPaid() && $order->getPaymentModuleId() == Mercanet::getModuleId()) {
                $event->stopPropagation();
            }
        }
    }

    /**
     * Checks if order payment module is paypal and if order new status is paid, send an email to the customer.
     *
     * @param OrderEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateStatus(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $order = $event->getOrder();

        if ($order->isPaid() && $order->getPaymentModuleId() == Mercanet::getModuleId()) {
            if (Mercanet::getConfigValue('send_payment_confirmation_message')) {
                $this->mailer->sendEmailToCustomer(
                    Mercanet::CONFIRMATION_MESSAGE_NAME,
                    $order->getCustomer(),
                    [
                        'order_id'  => $order->getId(),
                        'order_ref' => $order->getRef()
                    ]
                );
            }

            // Send confirmation email if required.
            if (Mercanet::getConfigValue('send_confirmation_message_only_if_paid')) {
                $dispatcher->dispatch($event, TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL);
            }

            Tlog::getInstance()->debug("Confirmation email sent to customer " . $order->getCustomer()->getEmail());
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS           => array("updateStatus", 128),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("sendConfirmationEmail", 129)
        );
    }
}
