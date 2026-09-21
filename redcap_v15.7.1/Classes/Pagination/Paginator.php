<?php
namespace Vanderbilt\REDCap\Classes\Pagination;

use Renderer;

class Paginator implements \JsonSerializable
{

    /**
     * total amount of items we are paginating
     *
     * @var int
     */
    protected $total;

    /**
     * items per page
     *
     * @var int
     */
    protected $perPage;

    /**
     * current page
     *
     * @var int
     */
    protected $currentPage;

    /**
     *DDetermine if there are more items in the data source. if "first" and "last" button must be displayed
     *
     * @return bool
     */
    protected $hasMore;

    /**
     * total number of pages
     *
     * @var [type]
     */
    protected $pages_count;

    public $elements;

    /**
     * max number of buttons displayed
     *
     * @var int
     */
    protected $max_items;

    /**
     * Create a new paginator instance.
     *
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options (path, max_items, show_end_buttons)
     * @return void
     */
    public function __construct($total, $perPage, $currentPage = null, $options = [])
    {
        $default_options = ['path'=>'/', 'max_items'=>5, 'show_end_buttons'=>true];
        $this->options = array_merge($default_options, $options);

        foreach ($this->options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage = $perPage;
        $this->currentPage = $this->setCurrentPage($currentPage);
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;
        $this->setItems($total);
    }

    /**
     * helper function for URLs
     * sets the page in the query part of a parsed url
     *
     * @param string $query
     * @return array
     */
    protected function http_parse_query($query) {
        if(empty(trim($query))) return [];
        $parameters = array();
        $queryParts = explode('&', $query);
        foreach ($queryParts as $queryPart) {
            list($key, $value) = explode('=', $queryPart, 2);
            $parameters[$key] = $value;
        }
        return $parameters;
    }
    
    /**
     * helper function for URLs
     * builds a full URL using parts from a parsed URL
     *
     * @param array $parts
     * @return string
     */
    protected function buildUrl(array $parts) {
        return join('', [
            ($parts['scheme'] ?? ''),
            ((isset($parts['user']) || isset($parts['host'])) ? '//' : ''),
            ($parts['user'] ?? ''),
            ($parts['pass'] ?? ''),
            (isset($parts['user']) ? '@' : ''),
            ($parts['host'] ?? ''),
            ($parts['port'] ?? ''),
            ($parts['path'] ?? ''),
            (isset($parts['query']) ? "?{$parts['query']}" : ''),
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : ''),
        ]);
    }

    /**
     * Get the URL for a given page number.
     *
     * @param  int  $page
     * @return string
     */
    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parts = parse_url($this->path);
        $query_params = $this->http_parse_query($parts['query']);
        $query_params['_page'] = $page;
        $parts['query'] = http_build_query($query_params);


        $url = $this->buildUrl($parts);

        return urldecode($url);
    }

    

    /**
     * Get the current page.
     *
     * @return int
     */
    public function currentPage()
    {
        return $this->currentPage;
    }
    

    /**
     *DDetermine if the given value is a valid page number. if "first" and "last" button must be displayed
     *
     * @param  int  $page
     * @return bool
     */
    protected function isValidPageNumber($page)
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * set the total number of pages available
     *
     * @return void
     */
    protected function setPagesCount()
    {
        $this->pages_count = ceil($this->total/$this->perPage);
    }

    /**
     * Get the current page for the request.
     *
     * @param  int  $currentPage
     * @return int
     */
    protected function setCurrentPage($currentPage)
    {
        return $this->isValidPageNumber($currentPage) ? (int) $currentPage : 1;
    }

    /**
     * Set the items for the paginator.
     *
     * @param  mixed  $items
     * @return void
     */
    protected function setItems($total)
    {
        $this->total = intval($total);
        $this->setPagesCount();
        $this->hasMore = $this->currentPage < $this->pages_count;
        
        // start collecting elements
        $elements = [];
        for($i=1;$i<=$this->pages_count;$i++)
        {
            $elements[] = [$i => $this->url($i)];
        }

        $applyMaxItemsLimit = function($elements, $max_items) {
            $items_count = count($elements);
            if($items_count<=$max_items) return $elements;
            else {
                $currentPage = $this->currentPage;
                $first_half = ceil($max_items/2);
                $start = $currentPage-$first_half;
                if($start<0) $start = 0; // start at least from 0
                // adjust start if we are close to the final page
                if($items_count-$start<$max_items) $start = $items_count-$max_items;
    
                $elements = array_slice($elements, $start, $max_items);
                $last_index = $max_items-1;
                // replace first item with dots if is not the current page
                if(key($elements[0])!=$currentPage) {
                    // also check if is the first page
                    if($start>0) $elements[0] = '...';
                }
                // replace last item with dots if not the current page
                if(key($elements[$last_index])!=$currentPage) {
                    // also check if is the last page
                    if($last_index+$start<($items_count-1)) $elements[$last_index] = '...';
                }
                return $elements;
            }
        };
        // apply max items restriction
        $elements = $applyMaxItemsLimit($elements, $this->max_items);
        

        $this->elements = $elements;
    }

    public function getTotal()
    {
        return $this->total;
    }
    
    public function getPagesCount()
    {
        return $this->pages_count;
    }

    /**
     *DDetermine if there are more items in the data source. if "first" and "last" button must be displayed
     *
     * @return bool
     */
    public function hasMorePages()
    {
        return $this->hasMore;
    }

     /**
     *DDetermine if there are enough items to split into multiple pages. if "first" and "last" button must be displayed
     *
     * @return bool
     */

    public function hasPages()
    {
        return $this->pages_count>1 && ($this->currentPage() != 1 || $this->hasMorePages());
    }
    /**
     *DDetermine if the paginator is on the first page. if "first" and "last" button must be displayed
     *
     * @return bool
     */

    public function onFirstPage()
    {
        return $this->currentPage() <= 1;
    }

    /**
     * Get the URL for the previous page.
     *
     * @return string|null
     */
    public function previousPageUrl()
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
    }

    /**
     * Get the URL for the next page.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }
    }

    /**
     * get the URL of the first page
     *
     * @return int
     */
    public function firstPageUrl()
    {
        return $this->url(1);
    }

    /**
     * get the URL of the last page
     *
     * @return int
     */
    public function lastPageUrl()
    {
        return $this->url($this->pages_count);
    }

    /**
     * Determine if "first" and "last" button must be displayed
     *
     * @return bool
     */
    public function showEndButtons()
    {
        $show = $this->show_end_buttons ?: false;
        return boolval($show);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            /* 'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(), */
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function render()
    {
        $blade = Renderer::getBlade();
        $paginator = $this;
        print $blade->run('partials.paginator', compact('paginator'));
    }
}
