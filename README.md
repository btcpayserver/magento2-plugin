#BTCPay Server integration for Magento 2

## Requirements
- Magento 2.3 installation (tested on Community Edition 2.3.2 with PHP 7.2)
- Magento < 2.3 should also work, but is untested.
- Your BTCPay server must be setup with HTTPS

## Features
- Allows you to pay with BTCPay Server in Magento 2 stores
- Magento receives invoice updates and updates the order statuses 
- Custom order statuses in Magento are supported
- View BTC Pay invoices created in Magento Admin
- Magento also polls for invoice updates as a safety net for when real-time updates didn't reach Magento
- Multi-website and multi-store compatible

## Goal
The goal of this module is to allow Bitcoin, Lightcoin and other crypto payments in Magento 2 without any other 3rd party.
This module is also designed to be robust, low-maintenance and a solid foundation for future customization, should your business need it.

## How to install
Install using composer by running:
```
composer require storefront/magento2-btcpay-module
```

## How to configure
After installation, a new Payment Method will be visible in Stores > Configuration > Sales > Payment Methods. Configure the fields there.

You will need to get a pairing code from BTCPay Server and enter that.

## How does it work?
- When an order is placed in Magento and BTCPay was selected as a payment method, the customer is redirected to the payment page on your BTCPay Server.
- The customer can pay there, or he can cancel his order.
- When he cancels, the unpaid order is canceled freeing up reserved stock and the customer is sent back to the shopping cart page. This module will restore the contents of the shopping cart, so the customer does not need to start from scratch.
- When the customer pays, BTCPay Server will be notified of the payment and will signal Magento on the changed invoice status.
- BTCPay Server pushes payment status changes to Magento, but Magento can also poll for invoice changes on it's own. We've built this as a safety net in case BTCPay Server cannot connect to Magento (i.e. during developement, behind a firewall).
- Invoice updates from BTCPay Server to Magento are instant.
- Magento polls BTCPay Server for updates every 5 minutes.
 
## Which payment methods are supported?
This depends on your configuration of BTCPay Server. All payment methods you have activated on BTCPay Server, will be available to the customer.

## What isn't supported?
- Only 1 domain name can be configured for BTCPay Server, so you cannot have multiple BTCPay Servers. The one is used for the whole Magento installation.
- Connecting to BTCPay server over HTTP is not possible. Only HTTPS.

## Who has created this module?
This module was created by Storefront, a small Magento integrator from Belgium with over 10 years experience. Visit our website at www.storefront.be to learn more about us.

This module does NOT contain any advertising and is 100% open source and free to use.

## Why did you create this module?
- Existing modules had very poor code quality, did not follow Magento 2 best-practises
- Was little supported (in combination with BTCPay Server)
- Was confusing to set up since the previous modules are basically designed for BitPay
- We now have a module dedicated to BTCPay, so both BTCPay Server and this module can innovate freely without having to consider BitPay compatibility
- Higher code quality means less maintenance and easier compatibility with future Magento versions

## What can I do if my BTCPay Server or Magento was offline for some time and invoice updates may not have synchronized?
Magento polls BTCPay Server every 5 minutes for updates to non-complete invoices, so basically you don't need to do anything. This is handled by a cronjob.
If you don't want to wait 5 minutes or prefer to see what is happening, we have prepared a console command to run the invoice sync manually:

```
bin/magento btcpay:invoice:update
```

## What is the future roadmap?
- As this is a first release, we want to learn more from actual day-to-day use and work on stability first.
- We hope to bring you easier automated testing, but for this we need changes in BTCPay Server too: https://github.com/btcpayserver/btcpayserver/issues/917
- Support for configuring multiple BTCPay Servers, so you can have separate installations for different websites/stores (low priority).
- Nothing else is required really, as this module does what it needs to do in a robust and dependable way.

## What if I need help?
Just like with any other open source software, you can get help anywhere from the community, or just open an issue here on Github.

You can talk to Wouter Samaey on the BTCPay Server Mattermost #development channel

If you prefer professional paid support, you can contact Storefront at info@storefront.be.

If this module powers your business, consider getting paid support (we did build this module for free) and also donate to the development of BTCPay Server at https://btcpayserver.org/#makeADonation 
