<?php

declare(strict_types=1);

namespace Rezolve\BrainImportExport\Model;

use Exception;
use Firebear\ImportExport\Model\Export\EntityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\ImportExport\Model\Export\AbstractEntity as AbstractEntity;
use Magento\ImportExport\Model\Export\Entity\AbstractEntity as EntityAbstractEntity;

class Export extends \Firebear\ImportExport\Model\Export
{
    /**
     * Retrieve Entity Model
     *
     * @return EntityInterface
     * @throws LocalizedException
     */
    protected function _getEntityAdapter()
    {
        if (!$this->_entityAdapter) {
            $entities = $this->fireExportDiConfig->get();
            if (isset($entities[$this->getEntity()])) {
                $entity = $entities[$this->getEntity()];
                try {
                    $this->_entityAdapter = $this->_entityFactory->create($entity['model']);
                } catch (Exception $e) {
                    $this->_logger->critical($e);
                    $this->addLogWriteln($e->getMessage(), $this->output, 'error');
                    throw new LocalizedException(__('Please enter a correct entity model.'));
                }
                if (!$this->_entityAdapter instanceof EntityInterface) {
                    throw new LocalizedException(
                        __('The entity adapter object must be an instance of %1.', EntityInterface::class)
                    );
                }

                if (!$this->_entityAdapter instanceof EntityAbstractEntity
                    && !$this->_entityAdapter instanceof AbstractEntity
                ) {
                    throw new LocalizedException(
                        __(
                            'The entity adapter object must be an instance of %1 or %2.',
                            EntityAbstractEntity::class,
                            AbstractEntity::class
                        )
                    );
                }

                if ($this->getEntity() !== 'catalog_product_brain' && $this->getEntity() != $this->_entityAdapter->getEntityTypeCode()) {
                    throw new LocalizedException(__('The input entity code is not equal to entity adapter code.'));
                }

                $data = $this->getData();
                if (empty($data['behavior_data']['deps']) && isset($entity['fields'])) {
                    $data['behavior_data']['deps'] = array_keys($entity['fields']);
                }
                $this->_entityAdapter->setParameters($data);
            } else {
                throw new LocalizedException(__('Please enter a correct entity.'));
            }
        }

        return $this->_entityAdapter;
    }

}
