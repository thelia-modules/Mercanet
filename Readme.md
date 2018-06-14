# Mercanet Payment Module
------------------------

## English instructions

This module offers to your customers the Mercanet payment system, which is used by BNP Paribas.

The module is based on the "Connecteur POST" (more details here : https://documentation.mercanet.bnpparibas.net/index.php?title=Connecteur_POST )

### Installation

#### Manually

Install the Mercanet module using the Module page of your back office to upload the archive.

You can also extract the archive in the `<thelia root>/local/modules` directory. Be sure that the name of the module's directory is `Mercanet` (and not `Mercanet-master`, for example).

Activate the module from the Modules page of your back-office.

The module is pre-configured with test shop data (see details here : https://documentation.mercanet.bnpparibas.net/index.php?title=Boutique_de_test, and test card data here https://documentation.mercanet.bnpparibas.net/index.php?title=Cartes_de_test ). 

#### composer

```
$ composer require thelia/Mercanet-module:~1.0
```

### Usage

You have to configure the Mercanet module before starting to use it. To do so, go to the "Modules" tab of your Thelia back-office, and activate the Mercanet module.

Then click the "Configure" button, and enter the required information. In most case, you'll receive your merchant ID by e-mail, and you'll receive instructions to download your secret key.

The module performs several checks when the configuration is saved, especially the execution permissions on the Mercanet binaries.

During the test phase, you can define the IP addresses allowed to use the Mercanet module on the front office, so that your customers will not be able to pay with Mercanet during this test phase. 

A log of Mercanet post-payment callbacks is displayed in the configuration page.

## Instructions en français

Ce module permet à vos clients de payer leurs commande par carte bancaire via la plate-forme Mercanet, utilisée par la BNP Paribas.

Le module estbasé sur le "Connecteur POST" (plus de détails technique ici: https://documentation.mercanet.bnpparibas.net/index.php?title=Connecteur_POST)

## Installation

### Manuellement

Installez ce module directement depuis la page Modules de votre back-office, en envoyant le fichier zip du module.

Vous pouvez aussi décompresser le module, et le placer manuellement dans le dossier ```<thelia_root>/local/modules```. Assurez-vous que le nom du dossier est bien ```Mercanet```, et pas ```Mercanet-master```

Le module est préconfiguré avec les données de la boutique de test BNP Paribas (plus de détails ici : https://documentation.mercanet.bnpparibas.net/index.php?title=Boutique_de_test, les détails sur les cartes de test sont ici : https://documentation.mercanet.bnpparibas.net/index.php?title=Cartes_de_test )

### composer

```
$ composer require thelia/Mercanet-module:~1.0
```


## Utilisation

Pour utiliser le module Mercanet, vous devez tout d'abord le configurer. Pour ce faire, rendez-vous dans votre back-office, onglet Modules, et activez le module Mercanet.

Cliquez ensuite sur "Configurer" sur la ligne du module, et renseignez les informations requises. Dans la plupart des cas, l'ID Marchand vous a été communiqué par votre banque par e-mail, et vous devez recevoir les instructions qui vous permettront de télécharger la clef secrète.

Lors de la phase de test, vous pouvez définir les adresses IP qui seront autorisées à utiliser le module en front-office, afin de ne pas laisser vos clients payer leur commandes avec Mercanet pendant cette phase.
