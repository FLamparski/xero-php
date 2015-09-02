<?php

namespace XeroPHP\Remote;

use XeroPHP\Application;
use XeroPHP\Exception;

/**
 * Lets you query an API endpoint.
 */
class Query {

    const ORDER_ASC  = 'ASC';
    const ORDER_DESC = 'DESC';

    /** @var  \XeroPHP\Application */
    private $app;

    private $from_class;
    private $where;
    private $order;
    private $modifiedAfter;
    private $page;
    private $offset;

    /**
     * Make a new instance.
     *
     * Normally you would use `$application->load('Accounting\\Contact')`,
     * but you can do the equivalent with `(new Query($application))->from('Accounting\\Contact')`.
     */
    public function __construct(Application $app) {
        $this->app = $app;
        $this->where = array();
        $this->order = null;
        $this->modifiedAfter = null;
        $this->page = null;
        $this->offset = null;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function from($class) {

        $this->from_class = $this->app->validateModelClass($class);

        return $this;
    }

    /**
     * Adds to the Where clause of this query.
     *
     * If called with one string argument, it will add that argument to the
     * Where clause directly, eg: `$query->where('Total>=1000')`.
     * Calling with two arguments like `$query->where('Name', 'Foo Bar')` is equivalent
     * to calling `$query->where('Name=="Foo Bar"')` .
     *
     * You can call this method multiple times; subsequent where clauses will
     * be concatenated in an AND expression.
     *
     * Xero's Where clause expression language is not very well documented,
     * but it behaves like a cross between SQL and C#, which means you'll
     * need to use double quotes for strings or else it thinks it's a character literal.
     *
     * @return $this
     */
    public function where() {
        $args = func_get_args();

        if(func_num_args() === 2) {
            $this->where[] = sprintf('%s=="%s"', $args[0], $args[1]);
        } else {
            $this->where[] = $args[0];
        }

        return $this;
    }

    public function getWhere() {
        return implode(' AND ', $this->where);
    }

    /**
     * Order by a particular field, ascending by default. Once per query.
     * @param string $order
     * @param string $direction
     * @return $this
     */
    public function orderBy($order, $direction = self::ORDER_ASC) {
        $this->order = sprintf('%s %s', $order, $direction);

        return $this;
    }

    /**
     * Modify the query to only return objects modified after a certain date.
     * Useful if you want to sync between your own database and Xero with
     * minimum amount of data transfer.
     *
     * @param \DateTime|null $modifiedAfter
     * @return $this
     */
    public function modifiedAfter(\DateTime $modifiedAfter = null) {
        if($modifiedAfter === null) {
            $modifiedAfter = new \DateTime('@0'); // since ever
        }

        $this->modifiedAfter = $modifiedAfter->format('c');

        return $this;
    }

    /**
     * Return a page of results for endpoints that support paging.
     * @param int $page The page number to return, default 1.
     * @return $this
     * @throws Exception
     */
    public function page($page = 1) {
        /** @var ObjectInterface $from_class */
        $from_class = $this->from_class;
        if(!$from_class::isPageable()){
            throw new Exception(sprintf('%s does not support paging.', $from_class));
        }

        $this->page = intval($page);

        return $this;
    }

    /**
     * @param int $offset
     * @return $this
     */
    public function offset($offset = 0) {
        $this->offset = intval($offset);

        return $this;
    }

    /**
     * Run the query.
     * @return Collection The objects that match your query.
     */
    public function execute() {

        /** @var ObjectInterface $from_class */
        $from_class = $this->from_class;
        $url = new URL($this->app, $from_class::getResourceURI(), $from_class::getAPIStem());
        $request = new Request($this->app, $url, Request::METHOD_GET);

        $where = $this->getWhere();
        if(!empty($where)) {
            $request->setParameter('where', $where);
        }

        if($this->order !== null) {
            $request->setParameter('order', $this->order);
        }

        if($this->modifiedAfter !== null) {
            $request->setHeader('If-Modified-Since', $this->modifiedAfter);
        }

        if($this->page !== null) {
            $request->setParameter('page', $this->page);
        }

        if($this->offset !== null) {
            $request->setParameter('offset', $this->offset);
        }

        $request->send();

        $elements = new Collection();
        foreach($request->getResponse()->getElements() as $element) {
            /** @var Object $built_element */
            $built_element = new $from_class($this->app);
            $built_element->fromStringArray($element);
            $elements->append($built_element);
        }

        return $elements;
    }

    /**
     * @return mixed
     */
    public function getFrom() {
        return $this->from_class;
    }
}
