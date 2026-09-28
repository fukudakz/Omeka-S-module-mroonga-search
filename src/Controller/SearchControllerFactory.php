<?php
namespace MroongaSearch\Controller;

use Interop\Container\ContainerInterface;

class SearchControllerFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchController($services);
    }
} 