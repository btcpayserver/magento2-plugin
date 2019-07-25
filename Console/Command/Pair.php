<?php
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

use Storefront\BTCPay\Helper\Data;
use Storefront\BTCPay\Model\BTCPay\InvoiceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Pair extends Command {


    /**
     * @var Data
     */
    private $helper;
    /**
     * @var InvoiceService
     */
    private $invoiceService;


    public function __construct(Data $helper, InvoiceService $invoiceService, string $name = null) {
        parent::__construct($name);
        $this->helper = $helper;
        $this->invoiceService = $invoiceService;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $pairingCode = $input->getArgument('pairing_code');
        $this->invoiceService->pair(0, $pairingCode);
        $output->writeln('Successfully paired and new keys generated.');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this->setName('btcpay:pair');
        $this->setDescription('Connect Magento and BTCPay Server using a pairing code. This will also generate new private and public keys for communication with BTCPay Server.');
        $this->addArgument('pairing_code', InputArgument::REQUIRED, 'The pairing code you got from BTCPay Server > Store > Access Tokens.');
        parent::configure();
    }
}
