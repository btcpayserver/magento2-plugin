<?php
declare(strict_types=1);
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * Copyright (C) 2019  Storefront BVBA
 *
 * This file is part of Storefront/BTCPay.
 *
 * Storefront/BTCPay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Storefront\BTCPay\Console\Command;

use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PayOrder extends Command
{


    /**
     * @var BTCPayService
     */
    private $btcPayService;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    private $orderFactory;


    public function __construct(BTCPayService $btcPayService, \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory, string $name = null)
    {
        parent::__construct($name);
        $this->btcPayService = $btcPayService;
        $this->orderFactory = $orderFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orderIncrementId = $input->getArgument('order');
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        if ($order->getId()) {
            $invoiceData = $this->btcPayService->createInvoice($order);
            $output->writeln(__('You can pay order %1 by visiting URL %2', $order->getIncrementId(), $invoiceData['checkoutLink']));
        } else {
            $output->writeln(__('Could not find order %1', $orderIncrementId));
            exit(1);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('btcpay:order:pay');
        $this->setDescription('Generate a payment URL for a given order.');
        $this->addArgument('order', InputArgument::REQUIRED, 'The order increment ID you want to pay');
        parent::configure();
    }
}
