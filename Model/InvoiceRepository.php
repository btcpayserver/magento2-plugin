<?php
declare(strict_types=1);
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * @copyright Copyright © 2019-2021 Storefront bv. All rights reserved.
 * @author    Wouter Samaey - wouter.samaey@storefront.be
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
namespace Storefront\BTCPay\Model;

use Storefront\BTCPay\Api\InvoiceRepositoryInterface;
use Storefront\BTCPay\Api\Data\InvoiceSearchResultsInterfaceFactory;
use Storefront\BTCPay\Api\Data\InvoiceInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Storefront\BTCPay\Model\ResourceModel\Invoice as ResourceInvoice;
use Storefront\BTCPay\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\ExtensibleDataObjectConverter;

class InvoiceRepository implements InvoiceRepositoryInterface
{

    protected $resource;

    protected $invoiceFactory;

    protected $invoiceCollectionFactory;

    protected $searchResultsFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataInvoiceFactory;

    protected $extensionAttributesJoinProcessor;

    private $storeManager;

    private $collectionProcessor;

    protected $extensibleDataObjectConverter;

    /**
     * @param ResourceInvoice $resource
     * @param InvoiceFactory $invoiceFactory
     * @param InvoiceInterfaceFactory $dataInvoiceFactory
     * @param InvoiceCollectionFactory $invoiceCollectionFactory
     * @param InvoiceSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     * @param StoreManagerInterface $storeManager
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     */
    public function __construct(
        ResourceInvoice $resource,
        InvoiceFactory $invoiceFactory,
        InvoiceInterfaceFactory $dataInvoiceFactory,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        InvoiceSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter
    ) {
        $this->resource = $resource;
        $this->invoiceFactory = $invoiceFactory;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataInvoiceFactory = $dataInvoiceFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function save(
        \Storefront\BTCPay\Api\Data\InvoiceInterface $invoice
    ) {
        /* if (empty($invoice->getStoreId())) {
            $storeId = $this->storeManager->getStore()->getId();
            $invoice->setStoreId($storeId);
        } */

        $invoiceData = $this->extensibleDataObjectConverter->toNestedArray(
            $invoice,
            [],
            \Storefront\BTCPay\Api\Data\InvoiceInterface::class
        );

        $invoiceModel = $this->invoiceFactory->create()->setData($invoiceData);

        try {
            $this->resource->save($invoiceModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the BTCPay invoice: %1',
                $exception->getMessage()
            ));
        }
        return $invoiceModel->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getById($invoiceId)
    {
        $invoice = $this->invoiceFactory->create();
        $this->resource->load($invoice, $invoiceId);
        if (!$invoice->getId()) {
            throw new NoSuchEntityException(__('BTCPay invoice with id "%1" does not exist.', $invoiceId));
        }
        return $invoice->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->invoiceCollectionFactory->create();

        $this->extensionAttributesJoinProcessor->process(
            $collection,
            \Storefront\BTCPay\Api\Data\InvoiceInterface::class
        );

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model->getDataModel();
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(
        \Storefront\BTCPay\Api\Data\InvoiceInterface $invoice
    ) {
        try {
            $invoiceModel = $this->invoiceFactory->create();
            $this->resource->load($invoiceModel, $invoice->getInvoiceId());
            $this->resource->delete($invoiceModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the BTCPay invoice ID: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($invoiceId)
    {
        return $this->delete($this->getById($invoiceId));
    }
}
