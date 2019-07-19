<?php


/**
 * Description of SCConnectorImportFacoryInterface
 *
 * @author michael
 */
namespace saws\sawsconnector\Model\Import\SawsConnector;

class Factory
{
    /**
     * Object Manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    /**
     * @param string $className
     * @return \Magento\ImportExport\Model\Import\Entity\AbstractEntity|\Magento\ImportExport\Model\Import\AbstractEntity
     * @throws \InvalidArgumentException
     */
    public function create($className)
    {
        if (!$className) {
            throw new \InvalidArgumentException('Incorrect class name');
        }

        return $this->_objectManager->create('saws\\sawsconnector\\Model\\Import\\SawsConnector\\' . ucfirst(strtolower($className)));
    }
}
