<?php
namespace MroongaSearch\Service;

use Interop\Container\ContainerInterface;
use MroongaSearch\MroongaSearchService;

class MroongaSearchServiceFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $connection = $services->get('Omeka\Connection');
        
        return new MroongaSearchService($api, $connection);
    }
} 
