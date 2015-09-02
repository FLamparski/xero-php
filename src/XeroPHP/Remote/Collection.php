<?php

namespace XeroPHP\Remote;

/**
 * An array-like for collections of objects, such as InvoiceItems on an Invoice.
 */
class Collection extends \ArrayObject {

    /**
     * Holds a list of objects that hold child references to the collection.
     * todo - 2.x make this more elegant.
     *
     * @var Object[]
     */
    protected $_associated_objects;


    public function addAssociatedObject($parent_property, Object $object){
        $this->_associated_objects[$parent_property] = $object;
    }

    /**
     * Remove an item at a specific index
     *
     * @param $index
     */
    public function removeAt($index){
        if(isset($this[$index])){
            foreach($this->_associated_objects as $parent_property => $object){
                /** @var Object $object */
                $object->setDirty($parent_property);
            }
            unset($this[$index]);
        }
    }

    /**
     * Remove a specific object from the collection
     *
     * @param \XeroPHP\Remote\Object $object
     */
    public function remove(Object $object){
        foreach($this as $index => $item){
            if($item === $object){
                $this->removeAt($index);
            }
        }
    }

    /**
     * Remove all of the values in the collection.
     *
     * Note that in some cases (one known case is ContactPersons)
     * this might not work as expected due to upstream bugs:
     * https://github.com/calcinai/xero-php/issues/63
     */
    public function removeAll(){
        foreach($this->_associated_objects as $parent_property => $object){
            /** @var Object $object */
            $object->setDirty($parent_property);
        }
        $this->exchangeArray([]);
    }

}
