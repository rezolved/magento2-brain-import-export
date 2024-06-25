<?php

declare(strict_types=1);

namespace Rezolve\BrainImportExport\Model\Export;

use DateTime;
use Firebear\ImportExport\Model\Export\Product\Additional;
use Firebear\ImportExport\Api\Data\SeparatorFormatterInterface;
use Firebear\ImportExport\Model\Export\Product\AdditionalFieldsPool;
use Firebear\ImportExport\Model\ExportJob\Processor;
use IntlDateFormatter;
use Firebear\ImportExport\Model\Export\Product as FirebearExportProduct;
use Magento\Catalog\Model\Product\LinkTypeProvider;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\CatalogImportExport\Model\Export\Product\Type\Factory;
use Magento\CatalogImportExport\Model\Export\RowCustomizerInterface;
use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;
use Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\ImportExport\Model\Export;
use Magento\ImportExport\Model\Export\ConfigInterface;
use Magento\ImportExport\Model\Import;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Helper\Data;
use Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory as SwatchCollectionFactory;
use Psr\Log\LoggerInterface;
use Zend_Db_Statement_Exception;
use Magento\Framework\Url;

class BrainProduct extends FirebearExportProduct
{
    const ENTITY_TYPE_CODE = 'catalog_product';
    const COL_PRODUCT_ID = 'product_id';
    const COL_PARENT_ID = 'parent_id';
    const COL_MODEL = 'model';
    const COL_BRAND = 'brand';
    const COL_GTIN = 'gtin';
    const COL_MPN = 'mpn';
    const COL_CONDITION = 'condition';
    const COL_ADULT = 'adult';
    const COL_CERTIFICATION = 'certification';
    const COL_ENERGY_EFFICIENTY_CLASS = 'energy_efficiency_class';
    const COL_MIN_ENERGY_EFFICIENCY = 'min_energy_efficiency_class';
    const COL_MAX_ENERGY_EFFICIENCY = 'max_energy_efficiency';
    const COL_AGE_GROUP = 'age_group';
    const COL_GENDER = 'gender';
    const COL_MATERIAL = 'material';
    const COL_PATTERN = 'pattern';
    const COL_SIZETYPE = 'size_type';
    const COL_SIZESYTEM = 'size_system';
    const COL_PRODUCT_LENGTH = 'product_length';
    const COL_PRODUCT_WIDTH = 'product_width';
    const COL_PRODUCT_HEIGHT = 'product_height';
    const COL_PRODUCT_WEIGHT = 'product_weight';
    const COL_CURRENCY = 'currency';
    const COL_WEIGHT = 'weight';
    const COL_LINK = 'link';
    const COL_SYSTEM_LINK = 'system_link';

    /**
     * @var Url
     */
    protected Url $url;

    /**
     * @param TimezoneInterface $localeDate
     * @param Config $config
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param ConfigInterface $exportConfig
     * @param ProductFactory $productFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $attrSetColFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryColFactory
     * @param ItemFactory $itemFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\Option\CollectionFactory $optionColFactory
     * @param CollectionFactory $attributeColFactory
     * @param Factory $_typeFactory
     * @param LinkTypeProvider $linkTypeProvider
     * @param RowCustomizerInterface $rowCustomizer
     * @param Additional $additional
     * @param Manager $moduleManager
     * @param Data $swatchesHelperData
     * @param SwatchCollectionFactory $swatchCollectionFactory
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param AdditionalFieldsPool $additionalFieldsPool
     * @param SeparatorFormatterInterface $separatorFormatter
     * @param Url $url
     * @param array $dateAttrCodes
     * @throws LocalizedException
     */
    public function __construct(
        TimezoneInterface $localeDate,
        Config $config,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        ConfigInterface $exportConfig,
        ProductFactory $productFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $attrSetColFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryColFactory,
        ItemFactory $itemFactory, \Magento\Catalog\Model\ResourceModel\Product\Option\CollectionFactory $optionColFactory,
        CollectionFactory $attributeColFactory,
        Factory $_typeFactory,
        LinkTypeProvider $linkTypeProvider,
        RowCustomizerInterface $rowCustomizer,
        Additional $additional,
        Manager $moduleManager,
        Data $swatchesHelperData,
        SwatchCollectionFactory $swatchCollectionFactory,
        CacheInterface $cache,
        SerializerInterface $serializer,
        AdditionalFieldsPool $additionalFieldsPool,
        SeparatorFormatterInterface $separatorFormatter,
        Url $url,
        array $dateAttrCodes = []
    ) {
        parent::__construct(
            $localeDate,
            $config,
            $resource,
            $storeManager,
            $logger,
            $collectionFactory,
            $exportConfig,
            $productFactory,
            $attrSetColFactory,
            $categoryColFactory,
            $itemFactory,
            $optionColFactory,
            $attributeColFactory,
            $_typeFactory,
            $linkTypeProvider,
            $rowCustomizer,
            $additional,
            $moduleManager,
            $swatchesHelperData,
            $swatchCollectionFactory,
            $cache,
            $serializer,
            $additionalFieldsPool,
            $separatorFormatter,
            $dateAttrCodes
        );
        $this->url = $url;
    }

    /**
     * @var array
     */
    protected $_exportMainAttrCodes = [
        self::COL_SKU,
        'name',
        'description'
    ];

    /**
     * @var string[]
     */
    protected $_fieldsMap = [
        self::COL_CATEGORY => 'categories',
    ];

    /**
     * @var array|string[]
     */
    protected array $replaceMap = [
        'product_id' => 'id',
        'name' => 'title',
        'is_in_stock' => 'availability',
        self::COL_MEDIA_IMAGE => 'image_link',
        'special_price' => 'sale_price',
        'parent_id' => 'item_group_id',
        '_category' => 'product_category',
        'weight' => 'product_weight',
    ];

    /**
     * EAV entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'catalog_product';
    }

    /**
     * @var array
     */
    protected array $userDefinedAttributes = [];


    /**
     * Get attributes codes which are appropriate for export and not the part of additional_attributes.
     *
     * @return array
     */
    protected function _getExportMainAttrCodes()
    {
        return $this->_exportMainAttrCodes;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function collectRawData()
    {
        $data = [];
        $items = $this->fireloadCollection();
        $stores = $this->getStores();
        $defaultStoreId = $this->_storeManager->getStore()->getId();

        foreach ($items as $itemId => $itemByStore) {
            /**
             * @var int $itemId
             * @var ProductEntity $item
             */
            foreach ($stores as $storeId => $storeCode) {
                if (!isset($itemByStore[$storeId])) {
                    continue;
                }
                /** @var \Magento\Catalog\Model\Product $item */
                $item = $itemByStore[$storeId];
                $addtionalFields = [];
                $additionalAttributes = [];
                $productLinkId = $item->getData($this->getProductEntityLinkField());

                $exportAttrCodes = array_unique($this->_getExportAttrCodes());
                foreach ($exportAttrCodes as $attrCodes) {
                    $attrValue = $item->getData($attrCodes);
                    if (isset($this->_attributeTypes[$attrCodes]) &&
                        $this->_attributeTypes[$attrCodes] != 'text' &&
                        !empty($attrValue)
                    ) {
                        $attrValue = str_replace(["\r\n", "\n\r", "\n", "\r"], '', $attrValue);
                    }
                    if (!$this->isValidAttributeValue($attrCodes, $attrValue)) {
                        continue;
                    }

                    if (isset($this->attributeStoreValues[$attrCodes][$storeId][$attrValue])
                        && !empty($this->attributeStoreValues[$attrCodes][$storeId])
                    ) {
                        $attrValue = $this->attributeStoreValues[$attrCodes][$storeId][$attrValue];
                    }
                    $fieldName = $this->_fieldsMap[$attrCodes] ?? $attrCodes;

                    if ($this->_attributeTypes[$attrCodes] == 'datetime') {
                        if (in_array($attrCodes, $this->dateAttrCodes) ||
                            in_array($attrCodes, $this->userDefinedAttributes)) {
                            $attrValue = $this->_localeDate
                                ->formatDateTime(
                                    new DateTime($attrValue),
                                    IntlDateFormatter::SHORT,
                                    IntlDateFormatter::NONE,
                                    null,
                                    date_default_timezone_get()
                                );
                        } else {
                            $attrValue = $this->_localeDate
                                ->formatDateTime(
                                    new DateTime($attrValue),
                                    IntlDateFormatter::SHORT,
                                    IntlDateFormatter::SHORT
                                );
                        }
                    }

                    if ($storeId != Store::DEFAULT_STORE_ID
                        && isset($data[$itemId][Store::DEFAULT_STORE_ID][$fieldName])
                        && $data[$itemId][Store::DEFAULT_STORE_ID][$fieldName] == htmlspecialchars_decode($attrValue)
                    ) {
                        continue;
                    }

                    if ($this->_attributeTypes[$attrCodes] !== 'multiselect') {
                        if (is_scalar($attrValue)) {
                            if (!in_array($fieldName, $this->_getExportMainAttrCodes())) {
                                $additionalAttributes[$fieldName] = $fieldName .
                                    ImportProduct::PAIR_NAME_VALUE_SEPARATOR . $this->wrapValue($attrValue);
                                if ($this->checkDivideofAttributes()) {
                                    $addtionalFields[$fieldName] = $attrValue;
                                    if (!in_array($fieldName, $this->keysAdditional)) {
                                        $this->keysAdditional[] = $fieldName;
                                    }
                                }
                            }
                            $data[$itemId][$storeId][$fieldName] = htmlspecialchars_decode($attrValue);
                        }
                    } else {
                        $this->collectMultiselectValues($item, $attrCodes, $storeId);
                        if (!empty($this->collectedMultiselectsData[$storeId][$productLinkId][$attrCodes])) {
                            $additionalAttributes[$attrCodes] = $fieldName .
                                ImportProduct::PAIR_NAME_VALUE_SEPARATOR . implode(
                                    ImportProduct::PSEUDO_MULTI_LINE_SEPARATOR,
                                    $this->wrapValue(
                                        $this->collectedMultiselectsData[$storeId][$productLinkId][$attrCodes]
                                    )
                                );
                            if ($this->checkDivideofAttributes()) {
                                if (!in_array($attrCodes, $this->keysAdditional)) {
                                    $this->keysAdditional[] = $attrCodes;
                                }
                                $addtionalFields[$attrCodes] =
                                    $this->collectedMultiselectsData[$storeId][$productLinkId][$attrCodes];
                            }
                        }
                    }
                }
                if (!empty($additionalAttributes)) {
                    $additionalAttributes = array_map('htmlspecialchars_decode', $additionalAttributes);
                    $data[$itemId][$storeId][self::COL_ADDITIONAL_ATTRIBUTES] =
                        implode($this->multipleValueSeparator, $additionalAttributes);
                } else {
                    unset($data[$itemId][$storeId][self::COL_ADDITIONAL_ATTRIBUTES]);
                }

                if (!empty($data[$itemId][$storeId]) || $this->hasMultiselectData($item, $storeId)) {
                    $attrSetId = $item->getAttributeSetId();
                    $data[$itemId][$storeId][self::COL_STORE] = $storeCode;
                    $data[$itemId][$storeId][self::COL_ATTR_SET] = $this->_attrSetIdToName[$attrSetId];
                    $data[$itemId][$storeId][self::COL_TYPE] = $item->getTypeId();
                }
                if (!empty($addtionalFields)) {
                    foreach ($addtionalFields as $key => $value) {
                        $data[$itemId][$storeId][$key] = $value;
                    }
                }
                if ($item->getData(self::COL_PARENT_ID) && isset($items[$item->getData(self::COL_PARENT_ID)])) {
                    $data[$itemId][$storeId][self::COL_PARENT_ID] =
                        $items[$item->getData(self::COL_PARENT_ID)][$storeId]->getSku();
                }
                $data[$itemId][$storeId][self::COL_SKU] = htmlspecialchars_decode($item->getSku());
                $data[$itemId][$storeId]['status'] = $item->getStatus();
                $data[$itemId][$storeId]['product_online'] = $item->getStatus();
                $data[$itemId][$storeId]['store_id'] = $storeId;
                $data[$itemId][$storeId][self::COL_PRODUCT_ID] = $item->getSku();
                $data[$itemId][$storeId][self::COL_WEIGHT] = $item->getData(self::COL_WEIGHT);
                $data[$itemId][$storeId][self::COL_BRAND] = $item->getData(self::COL_BRAND);
                $data[$itemId][$storeId][self::COL_GTIN] = $item->getData(self::COL_GTIN);
                $data[$itemId][$storeId][self::COL_MPN] = $item->getData(self::COL_MPN);
                $data[$itemId][$storeId][self::COL_CONDITION] = $item->getData(self::COL_CONDITION);
                $data[$itemId][$storeId][self::COL_CERTIFICATION] = $item->getData(self::COL_CERTIFICATION);
                $data[$itemId][$storeId][self::COL_MATERIAL] = $item->getData(self::COL_MATERIAL);
                $data[$itemId][$storeId][self::COL_PATTERN] = $item->getData(self::COL_PATTERN);
                $data[$itemId][$storeId][self::COL_PRODUCT_LENGTH] = $item->getData(self::COL_PRODUCT_LENGTH);
                $data[$itemId][$storeId][self::COL_PRODUCT_WIDTH] = $item->getData(self::COL_PRODUCT_WIDTH);
                $data[$itemId][$storeId][self::COL_PRODUCT_HEIGHT] = $item->getData(self::COL_PRODUCT_HEIGHT);
                $data[$itemId][$storeId][self::COL_WEIGHT] = $item->getData(self::COL_WEIGHT);
                $data[$itemId][$storeId][self::COL_MODEL] = $item->getData(self::COL_MODEL);
                $data[$itemId][$storeId]['product_link_id'] = $productLinkId;
                //$data[$itemId][$storeId][self::COL_MEDIA_IMAGE] = $this->apiProduct->getProductImageUrl($item);

                if (!$storeId) {
                    $item->setStoreId($defaultStoreId);
                }
                $data[$itemId][$storeId][self::COL_LINK] = $item->isVisibleInSiteVisibility() ?
                    $item->getUrlModel()->getUrl($item) :
                    $this->makeSystemLink($item);

                $data[$itemId][$storeId][self::COL_SYSTEM_LINK] = $this->makeSystemLink($item);
            }
        }

        return $data;
    }

    /**
     * @param $item
     * @return string
     */
    protected function makeSystemLink($item): string
    {
        return $this->url->getUrl(
            'catalog/product/view',
            ['id' => $item->getId(), '_nosid' => true]
        );
    }

    /**
     * Plugins may be attached. Be careful in renaming this function
     *
     * @return array
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    public function getExportData()
    {
        $exportData = [];
        try {
            foreach ($this->replaceMap as $key => $attr) {
                if (!in_array($key, $this->_parameters['list'])) {
                    $this->_parameters['list'][] = $key;
                    $this->_parameters['replace_code'][] = $attr;
                }
            }
            $rawData = $this->collectRawData();
            $multirawData = $this->collectMultirawData();

            $productIds = array_keys($rawData);

            $stockItemRows = $this->prepareCatalogInventory($productIds);

            $this->rowCustomizer->prepareData(
                $this->_prepareEntityCollection($this->_entityCollectionFactory->create()),
                $productIds
            );

            $this->clearMediaGalleryCache();
            $this->warmUpMediaGalleryCache(array_keys($rawData));

            $this->setAddHeaderColumns($stockItemRows);
            $rawData = $this->addAdditionalFields($rawData);
            $prevData = [];
            foreach ($rawData as $productId => $productData) {
                foreach ($productData as $storeId => $dataRow) {
                    $dataRow = $this->prepareDataRow($dataRow, $storeId);
                    if (isset($stockItemRows[$productId])) {
                        $stockItemRows[$productId]['is_in_stock'] = (int)$stockItemRows[$productId]['is_in_stock'] === 1 ? 'in_stock' : 'out_of_stock';
                        $dataRow = array_merge($dataRow, $stockItemRows[$productId]);
                    }
                    $this->appendMultirowData($dataRow, $multirawData);

                    if ($dataRow) {
                        if (Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR !== $this->multipleValueSeparator) {
                            $fields = [
                                'bundle_values',
                                'configurable_variations',
                                'configurable_variation_labels'
                            ];

                            foreach ($fields as $field) {
                                if (!empty($dataRow[$field])) {
                                    $dataRow[$field] = str_replace(
                                        Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR,
                                        $this->multipleValueSeparator,
                                        $dataRow[$field]
                                    );
                                }
                            }
                        }

                        if (!empty($prevData)) {
                            if (isset($prevData['sku']) && isset($dataRow['sku'])) {
                                if ($prevData['sku'] == $dataRow['sku']) {
                                    $dataRow = array_merge($prevData, $dataRow);
                                }
                            }
                        }
                        $exportData[] = $dataRow;
                    }
                    $prevData = $dataRow;
                }
            }
        } catch (Exception $e) {
            $this->_logger->critical($e);
        }
        $newData = $this->changeData($exportData, 'product_id');
        $this->addHeaderColumns();
        $this->_headerColumns = $this->changeHeaders($this->_headerColumns);

        return $newData;
    }

    /**
     * @param $dataRow
     * @param $storeId
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function prepareDataRow($dataRow, $storeId): mixed
    {
        $currency = $this->_storeManager->getStore($storeId)->getCurrentCurrency()->getCurrencyCode();
        $dataRow['currency'] = $currency;
        return $dataRow;
    }

    /**
     * @param $rowData
     * @return array
     */
    protected function _customHeadersMapping($rowData)
    {
        return $this->_headerColumns;
    }

    /**
     * @param $value
     * @return array|mixed|string|string[]
     */
    private function wrapValue(
        $value
    ) {
        if (!empty($this->_parameters[Export::FIELDS_ENCLOSURE])) {
            $wrap = function ($value) {
                return sprintf('"%s"', str_replace('"', '""', $value));
            };

            $value = is_array($value) ? array_map($wrap, $value) : $wrap($value);
        }

        return $value;
    }

    /**
     * Prepare catalog inventory
     *
     * @param array $productIds
     * @return array
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    protected function prepareCatalogInventory(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $select = $this->_connection->select()->from(
            $this->_itemFactory->create()->getMainTable(),
            ['is_in_stock', 'product_id']
        )->where(
            'product_id IN (?)',
            $productIds
        );

        $stmt = $this->_connection->query($select);
        $stockItemRows = [];
        while ($stockItemRow = $stmt->fetch()) {
            $productId = $stockItemRow['product_id'];
            $stockItemRows[$productId] = $stockItemRow;
            unset($stockItemRows[$productId]['product_id']);
        }
        return $stockItemRows;
    }

    /**
     * Prepare processor list data parameters
     */
    protected function prepareProcessorListDataParam()
    {
        if (!empty($this->_parameters[Processor::LIST_DATA])) {
            $processorListData = [];
            $flipFieldsMap = array_flip($this->_fieldsMap);
            foreach ($this->_parameters[Processor::LIST_DATA] as $field) {
                $field = $flipFieldsMap[$field] ?? $field;
                $processorListData[] = $field;
            }
            $this->_parameters[Processor::LIST_DATA] = $processorListData;
        }
    }


    /**
     * @param $stockItemRows
     */
    protected function setAddHeaderColumns($stockItemRows)
    {
        $addData = [];

        if (!empty($stockItemRows)) {
            if (reset($stockItemRows)) {
                $addData = array_keys(end($stockItemRows));
                foreach ($addData as $key => $value) {
                    if (is_numeric($value)) {
                        unset($addData[$key]);
                    }
                }
            }
        }
        if (!$this->_headerColumns) {
            $this->_headerColumns = array_merge(
                [
                    self::COL_SKU,
                    self::COL_PRODUCT_ID,
                    self::COL_ATTR_SET,
                    self::COL_CATEGORY,
                    self::COL_PARENT_ID,
                    self::COL_MODEL,
                    self::COL_BRAND,
                    self::COL_GTIN,
                    self::COL_MPN,
                    self::COL_CONDITION,
                    self::COL_ADULT,
                    self::COL_CERTIFICATION,
                    self::COL_ENERGY_EFFICIENTY_CLASS,
                    self::COL_MIN_ENERGY_EFFICIENCY,
                    self::COL_MAX_ENERGY_EFFICIENCY,
                    self::COL_AGE_GROUP,
                    self::COL_GENDER,
                    self::COL_MATERIAL,
                    self::COL_PATTERN,
                    self::COL_SIZETYPE,
                    self::COL_SIZESYTEM,
                    self::COL_PRODUCT_LENGTH,
                    self::COL_PRODUCT_WIDTH,
                    self::COL_PRODUCT_HEIGHT,
                    self::COL_PRODUCT_WEIGHT,
                    self::COL_CURRENCY,
                    self::COL_WEIGHT,
                    self::COL_MEDIA_IMAGE,
                    self::COL_LINK,
                    self::COL_SYSTEM_LINK,
                ],
                $this->_getExportMainAttrCodes(),
                $addData
            );
            if (!$this->checkDivideofAttributes()) {
                $this->_headerColumns = array_merge(
                    $this->_headerColumns,
                    [
                        self::COL_ADDITIONAL_ATTRIBUTES
                    ]
                );
            }
        }
    }

    /**
     * @return array
     */
    protected function fireloadCollection()
    {
        $data = [];
        /** @var ProductCollection $collection */
        $collection = $this->_getEntityCollection()->clear();

        if (isset($this->getParameters()['only_admin'])
            && $this->getParameters()['only_admin'] == 1
        ) {
            $collection->addAttributeToSelect('*');
            $collection->addStoreFilter(Store::DEFAULT_STORE_ID);

            $linkField = 'entity_id';
            $configurableProductRelation = $collection->getTable('catalog_product_super_link');

            $collection->getSelect()->joinLeft(
                ['cpr' => $configurableProductRelation],
                'e.' . $linkField . ' = cpr.product_id',
                ['parent_id' => 'parent_id']
            )->group('entity_id');

            /**
             * @var int $itemId
             * @var \Magento\Catalog\Model\Product $item
             */
            foreach ($collection as $itemId => $item) {
                $data[$itemId][Store::DEFAULT_STORE_ID] = $item;
            }
            $collection->clear();
        } else {
            $collectionByStore = clone $collection;
            foreach (array_keys($this->getStores()) as $storeId) {
                $collectionByStore->addStoreFilter($storeId);
                if ($this->getLastPageExportedStatus($storeId)) {
                    continue;
                }
                $this->setLastPageExportedStatus($collectionByStore, $storeId);
                foreach ($collectionByStore as $itemId => $item) {
                    $data[$itemId][$storeId] = $item;
                }
                $collectionByStore->clear();
            }
            unset($collectionByStore);
        }
        return $data;
    }

    /**
     * Custom fields mapping for changed purposes of fields and field names
     *
     * @param array $rowData
     *
     * @return array
     */
    public function _customFieldsMapping($rowData)
    {
        foreach ($this->_fieldsMap as $systemFieldName => $fileFieldName) {
            if (isset($rowData[$systemFieldName])) {
                $rowData[$fileFieldName] = $rowData[$systemFieldName];
                unset($rowData[$systemFieldName]);
            }
        }
        return $rowData;
    }

    protected function _getExportAttrCodes()
    {
        if (null === self::$attrCodes) {
            $parameters = $this->_parameters;

            if (isset($parameters[Processor::ALL_FIELDS]) && $parameters[Processor::ALL_FIELDS] &&
                isset($parameters[Processor::LIST_DATA]) && is_array($parameters[Processor::LIST_DATA])) {
                $attrCodes = array_merge(
                    $this->_permanentAttributes,
                    $this->addMediaAttributes($parameters[Processor::LIST_DATA], $this->getMediaAttributesMap())
                );
            } else {
                $attrCodes = [
                    'sku',
                    'name',
                    'description',
                    'color',
                    'size',
                    'parent_id',
                    'price',
                    'special_price',
                    self::COL_ADULT,
                    self::COL_ENERGY_EFFICIENTY_CLASS,
                    self::COL_MIN_ENERGY_EFFICIENCY,
                    self::COL_MAX_ENERGY_EFFICIENCY,
                    self::COL_AGE_GROUP,
                    self::COL_GENDER,
                    self::COL_SIZESYTEM,
                    self::COL_SIZETYPE
                ];
            }

            self::$attrCodes = $attrCodes;
        }

        return self::$attrCodes;
    }

}
