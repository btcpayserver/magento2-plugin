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

use Storefront\BTCPay\Helper\Data;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InvoicesUpdate extends Command {


    /**
     * @var BTCPayService
     */
    private $btcPayService;

    public function __construct(BTCPayService $btcPayService, string $name = null) {
        parent::__construct($name);
        $this->btcPayService = $btcPayService;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $numUpdated = $this->btcPayService->updateIncompleteInvoices();
        $output->writeln('Updated ' . $numUpdated . ' invoices from BTCPay Server');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this->setName('btcpay:invoices:update');
        $this->setDescription('Poll your BTCPay Server for the latest invoice updates (in case you missed any)');

        parent::configure();
    }
}
