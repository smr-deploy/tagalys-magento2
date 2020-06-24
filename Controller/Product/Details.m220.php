<?php

namespace Tagalys\Sync\Controller\Product;

use Magento\Framework\App\Action\Context;

class Details extends \Magento\Framework\App\Action\Action
{
    protected $jsonResultFactory;

    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Tagalys\Sync\Helper\Configuration $tagalysConfiguration,
        \Tagalys\Sync\Helper\Product $tagalysProduct,
        \Magento\Framework\View\Page\Config $pageConfig,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->tagalysConfiguration = $tagalysConfiguration;
        $this->tagalysProduct = $tagalysProduct;
        $this->pageConfig = $pageConfig;
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->pageConfig->setRobots('NOINDEX,NOFOLLOW');

        $resultJson = $this->jsonResultFactory->create();

        $params = $this->getRequest()->getParams();

        $data = json_decode($params['event_json'], true);
        $productsData = array();
        if ($data[1] == 'product_action') {
            if ($data[2] == 'add_to_cart' || $data[2] == 'buy') {
                $productsQtyCouples = $data[3];
                foreach($productsQtyCouples as $productsQtyCouple) {
                    $productsData[] = $this->getProductDetails($productsQtyCouple);
                }
            }
        }

        $resultJson->setData($productsData);

        return $resultJson;
    }

    public function getProductDetails($productsQtyCouple) {
        $qty = $productsQtyCouple[0];
        $mainProduct = $this->getMainProduct($productsQtyCouple);
        $productDetails = array(
            'sku' => $mainProduct->getSku(),
            'quantity' => $qty
        );
        if ($mainProduct->getTypeId() == 'configurable') {
            $simpleProductId = $productsQtyCouple[2];
            $configurableAttributes = array_map(function ($el) {
                return $el['attribute_code'];
            }, $mainProduct->getTypeInstance(true)->getConfigurableAttributesAsArray($mainProduct));
            $simpleProduct = $this->productFactory->create()->load($simpleProductId);
            $simpleProductAttributes = $this->tagalysProduct->getDirectProductTags($simpleProduct, $this->storeManager->getStore()->getId());
            $configurableSimpleProductAttributes = array();
            for ($i = 0; $i < count($simpleProductAttributes); $i++) {
                if (in_array($simpleProductAttributes[$i]['tag_set']['id'], $configurableAttributes)) {
                    $configurableSimpleProductAttributes[] = $simpleProductAttributes[$i];
                }
            }
            if (count($configurableSimpleProductAttributes) > 0) {
                $productDetails['__tags'] = $configurableSimpleProductAttributes;
            }
        }
        return $productDetails;
    }

    public function getMainProduct($productsQtyCouple) {
        if($this->tagalysConfiguration->areChildSimpleProductsVisibleIndividually()) {
            $childProductId = $productsQtyCouple[2];
            $childProduct = $this->productFactory->create()->load($childProductId);
            return $this->tagalysProduct->getClosestVisibleProduct($childProduct);
        }
        $productId = $productsQtyCouple[1];
        return $this->productFactory->create()->load($productId);
    }
}
