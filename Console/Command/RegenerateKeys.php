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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateKeys extends Command {


    /**
     * @var Data
     */
    private $helper;

    public function __construct(Data $helper, string $name = null) {
        parent::__construct($name);
        $this->helper = $helper;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->helper->generateKeys();
        $output->writeln('New keys generated. Reset your access token to re-establish the connection.');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this->setName('btcpay:keys:regenerate');
        $this->setDescription('Regenerate your private and public keys for communication with BTCPay Server. After this reset, you have to enter a new Access Token to make the connection work again.');
        parent::configure();
    }
}