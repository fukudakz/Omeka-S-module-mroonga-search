<?php
namespace MroongaSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class MroongaSearch extends AbstractHelper
{
    protected $searchService;
    
    public function __construct($searchService)
    {
        $this->searchService = $searchService;
    }
    
    public function __invoke($query, $options = [])
    {
        return $this->searchService->enhancedSearch($query, $options);
    }
} 
