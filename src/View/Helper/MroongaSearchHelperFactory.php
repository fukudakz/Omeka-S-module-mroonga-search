<?php
namespace MroongaSearch\View\Helper;

use Interop\Container\ContainerInterface;

class MroongaSearchHelperFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $searchService = $services->get('MroongaSearch\Service\MroongaSearchService');
        return new MroongaSearch($searchService);
    }
} 
