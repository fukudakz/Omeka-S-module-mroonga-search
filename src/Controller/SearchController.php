<?php
namespace MroongaSearch\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class SearchController extends AbstractActionController
{
    public function enhancedSearchAction()
    {
        $query = $this->params()->fromQuery('q') ?: $this->params()->fromQuery('fulltext_search');
        $enhancedSearch = $this->params()->fromQuery('enhanced_search', false);
        
        if (!$query) {
            return new JsonModel(['error' => '検索クエリが指定されていません']);
        }
        
        try {
            $searchService = $this->getServiceLocator()->get('MroongaSearch\Service\MroongaSearchService');
            
            if ($enhancedSearch) {
                // 拡張検索を使用
                $results = $searchService->enhancedSearch($query);
                return new JsonModel([
                    'success' => true,
                    'results' => $results,
                    'total' => count($results),
                    'enhanced' => true
                ]);
            } else {
                // 通常の検索を使用
                $results = $this->api()->search('items', [
                    'search' => $query,
                    'sort_by' => 'relevance',
                    'sort_order' => 'desc'
                ])->getContent();
                
                return new JsonModel([
                    'success' => true,
                    'results' => $results,
                    'total' => count($results),
                    'enhanced' => false
                ]);
            }
        } catch (\Exception $e) {
            return new JsonModel([
                'error' => '検索中にエラーが発生しました: ' . $e->getMessage(),
                'enhanced' => false
            ]);
        }
    }
    
    public function searchAction()
    {
        $site = $this->currentSite();
        
        // ItemControllerと同じ方法：setDefaultsを呼ぶ
        $this->browse()->setDefaults('items');
        
        // ItemControllerと同じ方法：クエリパラメータを取得
        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();
        
        // サイト設定でattached_itemsが有効な場合
        if ($this->siteSettings()->get('browse_attached_items', false)) {
            $query['site_attachments_only'] = true;
        }
        
        // 検索クエリを取得
        $searchQuery = $query['q'] ?? $query['fulltext_search'] ?? '';
        $enhancedSearchParam = $query['enhanced_search'] ?? false;
        $enhancedSearch = ($enhancedSearchParam === '1' || $enhancedSearchParam === 1 || $enhancedSearchParam === true);
        
        error_log("SearchController: searchQuery = " . $searchQuery);
        error_log("SearchController: enhancedSearchParam = " . var_export($enhancedSearchParam, true));
        error_log("SearchController: enhancedSearch = " . var_export($enhancedSearch, true));
        
        if (!$searchQuery) {
            return $this->redirect()->toRoute('site/resource', ['controller' => 'item', 'action' => 'browse']);
        }
        
        // 検索時は必ず関連度順にする（fulltext_searchがある場合）
        if (!empty($query['fulltext_search']) || !empty($searchQuery)) {
            // sort_byが設定されていない、または'relevance'の場合、空文字列に設定
            if (empty($query['sort_by']) || $query['sort_by'] === 'relevance') {
                $query['sort_by'] = ''; // 空文字列がOmeka Sの関連度の値
                $query['sort_order'] = 'desc';
            }
        }
        
        try {
            if ($enhancedSearch) {
                error_log("SearchController: Using enhanced search");
                // 拡張検索を使用
                $searchService = $this->getServiceLocator()->get('MroongaSearch\Service\MroongaSearchService');
                $results = $searchService->enhancedSearch($searchQuery);
                
                // 結果をアイテムオブジェクトに変換
                $items = [];
                foreach ($results as $result) {
                    $item = $this->api()->read('items', $result['id'])->getContent();
                    if ($item) {
                        $items[] = $item;
                    }
                }
            } else {
                // 通常の検索を使用（ItemControllerと同じ方法）
                // 不要なパラメータを削除
                unset($query['q']);
                unset($query['enhanced_search']);
                
                // ItemControllerと同じように、クエリをそのままAPIに渡す
                $response = $this->api()->search('items', $query);
                $this->paginator($response->getTotalResults());
                $items = $response->getContent();
            }
            
            // ItemControllerと同じ方法でViewModelを作成
            $view = new ViewModel([
                'items' => $items,
                'query' => $searchQuery,
                'enhanced' => $enhancedSearch,
                'total' => count($items),
                'sort_by' => $query['sort_by'] ?? '',
                'sort_order' => $query['sort_order'] ?? 'desc'
            ]);
            
            $view->setVariable('site', $site);
            $view->setVariable('resources', $items);
            $view->setTemplate('omeka/site/item/browse');
            return $view;
            
        } catch (\Exception $e) {
            // エラーが発生した場合は通常の検索にフォールバック（関連度順）
            // 検索時は必ず関連度順にする
            $query['sort_by'] = '';
            $query['sort_order'] = 'desc';
            unset($query['q']);
            unset($query['enhanced_search']);
            
            $response = $this->api()->search('items', $query);
            $this->paginator($response->getTotalResults());
            $items = $response->getContent();
            
            $view = new ViewModel([
                'items' => $items,
                'query' => $searchQuery,
                'enhanced' => false,
                'total' => count($items),
                'error' => $e->getMessage(),
                'sort_by' => '',
                'sort_order' => 'desc'
            ]);
            
            $view->setVariable('site', $site);
            $view->setVariable('resources', $items);
            $view->setTemplate('omeka/site/item/browse');
            return $view;
        }
    }
} 