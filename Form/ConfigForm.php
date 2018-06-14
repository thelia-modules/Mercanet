<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Mercanet\Form;

use Mercanet\Mercanet;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class ConfigForm
 * @package Mercanet\Form
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class ConfigForm extends BaseForm
{
    protected function buildForm()
    {
        // If the Multi plugin is not enabled, all multi_fields are hidden
        /** @var Module $multiModule */
        $multiEnabled = (null !== $multiModule = ModuleQuery::create()->findOneByCode('MercanetNx')) && $multiModule->getActivate() != 0;

        $translator = Translator::getInstance();

        $this->formBuilder
            ->add(
                'merchantId',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $translator->trans('Shop Merchant ID', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'merchant_id',
                    ]
                ]
            )
            ->add(
                'mode',
                'choice',
                [
                    'constraints' =>  [
                        new NotBlank()
                    ],
                    'choices' => [
                        'TEST' => $translator->trans('Test', [], Mercanet::MODULE_DOMAIN),
                        'PRODUCTION' => $translator->trans('Production', [], Mercanet::MODULE_DOMAIN),
                    ],
                    'label' => $translator->trans('Operation Mode', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'mode',
                        'help' => $translator->trans('Test or production mode', [], Mercanet::MODULE_DOMAIN)
                    ]
                ]
            )
            ->add(
                'allowed_ip_list',
                'textarea',
                [
                    'required' => false,
                    'label' => $translator->trans('Allowed IPs in test mode', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'platform_url',
                        'help' => $translator->trans(
                            'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                            [ '%ip' => $this->getRequest()->getClientIp() ],
                            Mercanet::MODULE_DOMAIN
                        )
                    ],
                    'attr' => [
                        'rows' => 3
                    ]
                ]
            )
            ->add(
                'minimum_amount',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 0 ])
                    ],
                    'label' => $translator->trans('Minimum order total', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'minimum_amount',
                        'help' => $translator->trans(
                            'Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )
            ->add(
                'maximum_amount',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual([ 'value' => 0 ])
                    ],
                    'label' => $translator->trans('Maximum order total', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'maximum_amount',
                        'help' => $translator->trans(
                            'Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )
            ->add(
                'secretKey',
                'text',
                [
                    'label' => $translator->trans('Mercanet secret key', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'platform_url',
                        'help' => $translator->trans(
                            'Please paste here the secret key you get from Mercanet Download',
                            [],
                            Mercanet::MODULE_DOMAIN
                        ),
                    ],
                    'attr' => [
                        'rows' => 10
                    ]
                ]
            )
            ->add(
                'secretKeyVersion',
                'text',
                [
                    'label' => $translator->trans('Mercanet secret key version number', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'platform_url',
                        'help' => $translator->trans(
                            'The secret key version you get from Mercanet Download, 1 for the first secret key you get',
                            [],
                            Mercanet::MODULE_DOMAIN
                        ),
                    ],
                    'attr' => [
                        'rows' => 10
                    ]
                ]
            )
            ->add(
                'send_confirmation_message_only_if_paid',
                'checkbox',
                [
                    'value' => 1,
                    'required' => false,
                    'label' => $this->translator->trans('Send order confirmation on payment success', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans(
                            'If checked, the order confirmation message is sent to the customer only when the payment is successful. The order notification is always sent to the shop administrator',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )
            ->add(
                'send_payment_confirmation_message',
                'checkbox',
                [
                    'value' => 1,
                    'required' => false,
                    'label' => $this->translator->trans('Send a payment confirmation e-mail', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans(
                            'If checked, a payment confirmation e-mail is sent to the customer.',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )

            // -- Multiple times payement parameters, hidden id the MercanetNx module is not activated.
            ->add(
                'nx_nb_installments',
                $multiEnabled ? 'text' : 'hidden',
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 1 ])
                    ],
                    'required' => $multiEnabled,
                    'label' => $translator->trans('Number of installments', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'nx_nb_installments',
                        'help' => $translator->trans(
                            'Number of installements. Should be more than one',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )
            ->add(
                'nx_minimum_amount',
                $multiEnabled ? 'text' : 'hidden',
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 0 ])
                    ],
                    'required' => $multiEnabled,
                    'label' => $translator->trans('Minimum order total', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'nx_minimum_amount',
                        'help' => $translator->trans(
                            'Minimum order total in the default currency for which the multiple times payment method is available. Enter 0 for no minimum',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )
            ->add(
                'nx_maximum_amount',
                $multiEnabled ? 'text' : 'hidden',
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual([ 'value' => 0 ])
                    ],
                    'required' => $multiEnabled,
                    'label' => $translator->trans('Maximum order total', [], Mercanet::MODULE_DOMAIN),
                    'label_attr' => [
                        'for' => 'nx_maximum_amount',
                        'help' => $translator->trans(
                            'Maximum order total in the default currency for which the multiple times payment method is available. Enter 0 for no maximum',
                            [],
                            Mercanet::MODULE_DOMAIN
                        )
                    ]
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'config';
    }
}
